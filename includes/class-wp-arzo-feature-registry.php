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

    /** @var array<string,WP_Arzo_Feature> */
    private $features = array();

    /** @var array<string,array> */
    private $groups;

    private function __construct()
    {
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

    public function register($feature)
    {
        if ($feature instanceof WP_Arzo_Feature) {
            $this->features[$feature->id()] = $feature;
        }
    }

    /** @return WP_Arzo_Feature|null */
    public function get($id)
    {
        return isset($this->features[$id]) ? $this->features[$id] : null;
    }

    /** @return array<string,WP_Arzo_Feature> */
    public function all()
    {
        return $this->features;
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
        $feature = $this->get($id);
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

    /** Boot every enabled feature (call once, on plugins_loaded). */
    public function boot_enabled()
    {
        foreach ($this->features as $id => $feature) {
            if ($this->is_enabled($id)) {
                $feature->boot();
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
        foreach ($this->features as $feature) {
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
        foreach (array_keys($this->features) as $id) {
            if ($this->is_enabled($id)) {
                $n++;
            }
        }
        return $n;
    }
}
