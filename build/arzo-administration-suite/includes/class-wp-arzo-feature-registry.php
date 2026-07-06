<?php

/**
 * WP Arzo Feature Registry.
 *
 * Central registry of feature modules. Persists which features are enabled
 * (option `wp_arzo_features`) and their per-feature settings (`wp_arzo_settings`),
 * boots enabled features, groups them for the dashboard, and fires lifecycle
 * hooks on toggle (the integration point for the future auto-snapshot system).
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Registry
{
    const OPT_FEATURES = 'wp_arzo_features';
    const OPT_SETTINGS = 'wp_arzo_settings';

    /** @var WP_Arzo_Feature_Registry|null */
    private static $instance = null;

    /** @var array<string,WP_Arzo_Feature> Instantiated features (eager + lazily-loaded). */
    private $features = array();

    /**
     * Lazily-registered features: id => ['class'=>string,'file'=>string,'default'=>bool].
     * The class is NOT instantiated (and its file NOT loaded) until the feature is
     * enabled/booted or its page/settings are opened. This is the on-demand loader:
     * front-end / cron / REST requests load only ENABLED feature classes.
     *
     * @var array<string,array>
     */
    private $lazy = array();

    /** @var array<string,string> class name => absolute file path (drives the autoloader). */
    private $class_map = array();

    /** @var string[] Registration order of ids (eager + lazy), for stable listing. */
    private $order = array();

    /** @var array<string,array> */
    private $groups;

    private function __construct()
    {
        // On-demand autoloader for WP Arzo feature/helper classes. Only classes we
        // explicitly mapped (via register_lazy()/map_class()) are resolved; everything
        // else is ignored so we never interfere with other autoloaders.
        spl_autoload_register(array($this, 'autoload'));

        $this->groups = array(
            'utilities' => array('label' => 'Utilities & Admin', 'icon' => 'tools'),
            'content'   => array('label' => 'Content & Modeling', 'icon' => 'file'),
            'media'     => array('label' => 'Media', 'icon' => 'image'),
            'analytics' => array('label' => 'Analytics', 'icon' => 'chart'),
            'marketing' => array('label' => 'Marketing & Tracking', 'icon' => 'bolt'),
            'email'     => array('label' => 'Email', 'icon' => 'mail'),
            'security'  => array('label' => 'Security & Access', 'icon' => 'shield'),
            'branding'  => array('label' => 'Branding & UI', 'icon' => 'sparkles'),
            'developer' => array('label' => 'Developer', 'icon' => 'code'),
            'backup'    => array('label' => 'Backup & Restore', 'icon' => 'database'),
            'ai'        => array('label' => 'AI', 'icon' => 'sparkles'),
            'core'      => array('label' => 'Core Controls', 'icon' => 'settings'),
            'ops'       => array('label' => 'Ops & Monitoring', 'icon' => 'bug'),
            'advanced_tools' => array('label' => 'Advanced Tools (Console)', 'icon' => 'tools'),
        );
    }

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * The autoloader callback. Resolves a WP Arzo class from the class→file map that
     * register_lazy()/map_class() populate. No-op for anything not in the map.
     */
    public function autoload($class)
    {
        if (isset($this->class_map[$class])) {
            $file = $this->class_map[$class];
            if (is_string($file) && $file !== '' && file_exists($file)) {
                require_once $file;
            }
        }
    }

    /** Eagerly register an already-instantiated feature (console tools, placeholders). */
    public function register($feature)
    {
        if ($feature instanceof WP_Arzo_Feature) {
            $id = $feature->id();
            $this->features[$id] = $feature;
            if (!in_array($id, $this->order, true)) {
                $this->order[] = $id;
            }
        }
    }

    /**
     * Lazily register a feature: record its id/class/file/default WITHOUT loading the
     * class file. The class is loaded on demand (autoloader) the first time get() /
     * boot_enabled() / all() needs a real instance.
     *
     * @param string $id              Stable feature id.
     * @param string $class           Feature class name.
     * @param string $file            Absolute path to the file defining $class.
     * @param bool   $default_enabled Whether the feature is on before the user toggles it.
     */
    public function register_lazy($id, $class, $file, $default_enabled = false)
    {
        $this->lazy[$id] = array(
            'class'   => $class,
            'file'    => $file,
            'default' => (bool) $default_enabled,
        );
        $this->class_map[$class] = $file;
        if (!in_array($id, $this->order, true)) {
            $this->order[] = $id;
        }
    }

    /**
     * Map a class name to its file for the autoloader WITHOUT registering a feature.
     * For always-available helper classes referenced statically (e.g. Config Import/Export)
     * that used to be pulled in by the now-removed features-registry glob.
     */
    public function map_class($class, $file)
    {
        $this->class_map[$class] = $file;
    }

    /** Is an id registered (eager or lazy) — cheap, never loads the class. */
    public function is_registered($id)
    {
        return isset($this->features[$id]) || isset($this->lazy[$id]);
    }

    /** @return WP_Arzo_Feature|null Instantiating a lazily-registered feature on demand. */
    public function get($id)
    {
        if (isset($this->features[$id])) {
            return $this->features[$id];
        }
        if (isset($this->lazy[$id])) {
            $class = $this->lazy[$id]['class'];
            // class_exists() triggers the autoloader, which loads the mapped file.
            if (class_exists($class)) {
                $this->features[$id] = new $class();
                return $this->features[$id];
            }
        }
        return null;
    }

    /**
     * Every feature as an instantiated object, in registration order. This FORCE-LOADS
     * all lazily-registered classes, so only admin surfaces that need metadata for every
     * card (dashboard grid, command palette, setup wizard) should call it — front-end and
     * background requests must not.
     *
     * @return array<string,WP_Arzo_Feature>
     */
    public function all()
    {
        $out = array();
        foreach ($this->order as $id) {
            $feature = $this->get($id);
            if ($feature) {
                $out[$id] = $feature;
            }
        }
        return $out;
    }

    public function groups()
    {
        return $this->groups;
    }

    public function group_label($key)
    {
        return isset($this->groups[$key]) ? $this->groups[$key]['label'] : ucfirst($key);
    }

    public function group_icon($key)
    {
        return isset($this->groups[$key]) ? $this->groups[$key]['icon'] : 'bolt';
    }

    /** @return array<string,int> id => 0|1 */
    private function enabled_map()
    {
        $map = get_option(self::OPT_FEATURES, array());
        return is_array($map) ? $map : array();
    }

    public function is_enabled($id)
    {
        $map = $this->enabled_map();
        if (array_key_exists($id, $map)) {
            return (bool) $map[$id];
        }
        // Resolve the "on before first toggle" default WITHOUT loading a lazy feature's
        // class — the descriptor carries it, so boot_enabled() stays lazy.
        if (isset($this->lazy[$id])) {
            return (bool) $this->lazy[$id]['default'];
        }
        $feature = isset($this->features[$id]) ? $this->features[$id] : null;
        return $feature ? (bool) $feature->default_enabled() : false;
    }

    /**
     * Enable/disable a feature. Persists and fires lifecycle hooks.
     *
     * `wp_arzo_feature_enabled` / `wp_arzo_feature_disabled` are where the future
     * auto-snapshot/backup system hooks in (snapshot before a toggle takes effect).
     */
    public function set_enabled($id, $enabled)
    {
        $feature = $this->get($id);
        if (!$feature) {
            return false;
        }

        $enabled = (bool) $enabled;
        $was = $this->is_enabled($id);

        /**
         * Fires before a feature's enabled-state is persisted. The auto-snapshot
         * system can hook this to take a backup first.
         */
        do_action('wp_arzo_before_feature_toggle', $id, $enabled, $feature);

        $map = $this->enabled_map();
        $map[$id] = $enabled ? 1 : 0;
        update_option(self::OPT_FEATURES, $map);

        if ($enabled && !$was) {
            do_action('wp_arzo_feature_enabled', $id, $feature);
        } elseif (!$enabled && $was) {
            do_action('wp_arzo_feature_disabled', $id, $feature);
        }
        do_action('wp_arzo_feature_toggled', $id, $enabled, $feature);

        return true;
    }

    public function get_settings($id)
    {
        $all = get_option(self::OPT_SETTINGS, array());
        return (is_array($all) && isset($all[$id]) && is_array($all[$id])) ? $all[$id] : array();
    }

    public function save_settings($id, array $values)
    {
        $all = get_option(self::OPT_SETTINGS, array());
        if (!is_array($all)) {
            $all = array();
        }
        $all[$id] = $values;
        update_option(self::OPT_SETTINGS, $all);
    }

    /**
     * Boot every enabled feature (call once, on plugins_loaded). Only ENABLED features'
     * classes are loaded here — disabled features are never instantiated, which is the
     * whole point of the on-demand loader on the front-end/cron/REST path.
     */
    public function boot_enabled()
    {
        foreach ($this->order as $id) {
            if ($this->is_enabled($id)) {
                $feature = $this->get($id);
                if ($feature) {
                    $feature->boot();
                }
            }
        }
    }

    /**
     * Features grouped by their group key, preserving the canonical group order.
     *
     * @return array<string,WP_Arzo_Feature[]>
     */
    public function grouped()
    {
        $out = array();
        foreach (array_keys($this->groups) as $gk) {
            $out[$gk] = array();
        }
        foreach ($this->all() as $feature) {
            $g = $feature->group();
            if (!isset($out[$g])) {
                $out[$g] = array();
            }
            $out[$g][] = $feature;
        }
        foreach ($out as $gk => $items) {
            if (empty($items)) {
                unset($out[$gk]);
            }
        }
        return $out;
    }

    public function count_enabled()
    {
        $n = 0;
        foreach ($this->order as $id) {
            if ($this->is_enabled($id)) {
                $n++;
            }
        }
        return $n;
    }
}
