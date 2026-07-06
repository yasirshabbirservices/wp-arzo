<?php

/**
 * Feature: Config Import / Export.
 *
 * Export WP Arzo's own configuration — the feature enable-map (`wp_arzo_features`),
 * feature settings (`wp_arzo_settings`), and code snippets — to a versioned JSON
 * file, and import it back (on another site, or to roll a config forward). It does
 * NOT touch arbitrary `wp_options`; it only round-trips WP Arzo's own state.
 *
 * The page + AJAX live in WP_Arzo_Admin; this module contributes the dashboard
 * toggle that gates the page and the pure import/export helpers.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Config_IO extends WP_Arzo_Feature
{
    /** Bump if the payload shape changes incompatibly. */
    const SCHEMA = 1;

    public function id()
    {
        return 'config_io';
    }
    public function title()
    {
        return 'Config Import / Export';
    }
    public function description()
    {
        return 'Export WP Arzo settings, feature toggles, and snippets to a JSON file, and import them back.';
    }
    public function group()
    {
        return 'core';
    }
    public function icon()
    {
        return 'sliders';
    }

    /** Page-and-AJAX driven; nothing to hook. */
    public function boot()
    {
    }

    /* ------------------------------------------------------------- export */

    /**
     * Allow-list of dedicated WP Arzo option keys that hold PORTABLE config which is
     * NOT already covered by the feature-settings map (`wp_arzo_settings`). Features
     * whose config lives in their own option — Site Health, Cron Manager, Redirects,
     * Content Types, Custom Fields, Menu Manager, Notifications, … — register their key
     * via the `wp_arzo_config_option_keys` filter (Pro hooks it for its modules).
     *
     * This list is the SECURITY boundary for import: only keys returned here are ever
     * written, so a config file can never inject an arbitrary `wp_options` row. It
     * deliberately EXCLUDES secrets/credentials (backup OAuth tokens, REST API keys,
     * 2FA secrets, license state), logs, and runtime data — those are not portable.
     *
     * @return string[]
     */
    public static function portable_option_keys()
    {
        $keys = array(
            'wp_arzo_sched_freq', // scheduled-backups frequency (free)
        );
        $keys = apply_filters('wp_arzo_config_option_keys', $keys);

        $clean = array();
        foreach ((array) $keys as $k) {
            $k = sanitize_key((string) $k);
            if ($k !== '' && !in_array($k, $clean, true)) {
                $clean[] = $k;
            }
        }
        return $clean;
    }

    /** Build the export payload from the current WP Arzo state. */
    public static function export_payload()
    {
        $features    = get_option(WP_Arzo_Feature_Registry::OPT_FEATURES, array());
        $settings    = get_option(WP_Arzo_Feature_Registry::OPT_SETTINGS, array());
        $snippets    = class_exists('WP_Arzo_Snippets') ? WP_Arzo_Snippets::instance()->get_all() : array();
        $connections = get_option('wp_arzo_smtp_connections', array());

        // Per-feature dedicated config options (Site Health, Cron, Redirects, Content
        // Types, Custom Fields, Menu Manager, Notifications, …) — only the allow-listed
        // portable keys that actually exist are captured.
        $options = array();
        foreach (self::portable_option_keys() as $key) {
            $val = get_option($key, null);
            if ($val !== null) {
                $options[$key] = $val;
            }
        }

        return array(
            'schema'         => self::SCHEMA,
            'plugin_version' => defined('WP_ARZO_VERSION') ? WP_ARZO_VERSION : '',
            'exported_gmt'   => gmdate('Y-m-d H:i:s'),
            'site'           => home_url('/'),
            'features'       => is_array($features) ? $features : array(),
            'settings'       => is_array($settings) ? $settings : array(),
            'snippets'       => is_array($snippets) ? array_values($snippets) : array(),
            'connections'    => is_array($connections) ? $connections : array(),
            'options'        => $options,
        );
    }

    /** Suggested download filename for the current export. */
    public static function export_filename()
    {
        $host = preg_replace('/[^a-z0-9\-]+/i', '-', wp_parse_url(home_url('/'), PHP_URL_HOST) ?: 'site');
        return 'wp-arzo-config-' . strtolower($host) . '-' . gmdate('Ymd-His') . '.json';
    }

    /* ------------------------------------------------------------- import */

    /**
     * Validate + sanitize a decoded payload. Returns a clean
     * ['features','settings','snippets'] array, or WP_Error.
     */
    public static function validate($data)
    {
        if (!is_array($data) || !isset($data['schema'])) {
            return new WP_Error('wp_arzo_bad_config', 'This file is not a WP Arzo config export.');
        }
        if ((int) $data['schema'] !== self::SCHEMA) {
            return new WP_Error('wp_arzo_bad_schema', 'Unsupported config version (schema ' . (int) $data['schema'] . ').');
        }

        $features = array();
        if (!empty($data['features']) && is_array($data['features'])) {
            foreach ($data['features'] as $fid => $on) {
                $features[sanitize_key($fid)] = (!empty($on)) ? 1 : 0;
            }
        }

        $settings = array();
        if (!empty($data['settings']) && is_array($data['settings'])) {
            foreach ($data['settings'] as $fid => $vals) {
                if (is_array($vals)) {
                    $settings[sanitize_key($fid)] = $vals; // produced by our own export; per-feature schema re-sanitizes on save
                }
            }
        }

        $snippets = array();
        if (!empty($data['snippets']) && is_array($data['snippets'])) {
            foreach ($data['snippets'] as $s) {
                if (is_array($s) && isset($s['code'])) {
                    $snippets[] = $s;
                }
            }
        }

        // Email connections (the SureMail-style store) — kept as-is; the engine
        // re-reads/normalizes it (and only sends via enabled connections).
        $connections = (!empty($data['connections']) && is_array($data['connections'])) ? $data['connections'] : array();

        // Per-feature dedicated config options — ONLY keys the CURRENT site allow-lists
        // (portable_option_keys) are accepted, so an import can never write an option
        // that isn't a registered WP Arzo config key (option-injection guard).
        $options = array();
        if (!empty($data['options']) && is_array($data['options'])) {
            $allow = array_flip(self::portable_option_keys());
            foreach ($data['options'] as $key => $val) {
                $key = sanitize_key((string) $key);
                if (isset($allow[$key])) {
                    $options[$key] = $val;
                }
            }
        }

        return array('features' => $features, 'settings' => $settings, 'snippets' => $snippets, 'connections' => $connections, 'options' => $options);
    }

    /**
     * Apply a validated payload. Takes a safety snapshot first (if the backup
     * engine is available). Returns a summary of what changed.
     *
     * @param array $clean Output of validate().
     * @return array{features:int,settings:int,snippets:int,snapshot:bool}
     */
    public static function apply(array $clean)
    {
        $snapshot = false;
        if (class_exists('WP_Arzo_Backup_Manager')) {
            $res = WP_Arzo_Backup_Manager::instance()->create('options', 'Before config import', 'config_import');
            $snapshot = !is_wp_error($res);
        }

        update_option(WP_Arzo_Feature_Registry::OPT_FEATURES, $clean['features'], false);
        update_option(WP_Arzo_Feature_Registry::OPT_SETTINGS, $clean['settings'], false);

        $imported = 0;
        if (!empty($clean['snippets']) && class_exists('WP_Arzo_Snippets')) {
            $snippets = WP_Arzo_Snippets::instance();
            foreach ($clean['snippets'] as $s) {
                // Security: never auto-activate imported code. A config file can be shared
                // between sites and may carry arbitrary PHP snippets; forcing every imported
                // snippet inactive means an admin must review and manually enable each one
                // before it can execute — closing the "import a file → silent RCE" path.
                $s['active'] = 0;
                $snippets->save($s); // preserves id → overwrites match, else appends
                $imported++;
            }
        }

        $connections = 0;
        if (!empty($clean['connections']) && is_array($clean['connections'])) {
            update_option('wp_arzo_smtp_connections', $clean['connections'], false);
            $connections = isset($clean['connections']['connections']) && is_array($clean['connections']['connections'])
                ? count($clean['connections']['connections']) : 0;
        }

        // Per-feature dedicated config options (already allow-list-filtered by validate()).
        $options = 0;
        if (!empty($clean['options']) && is_array($clean['options'])) {
            foreach ($clean['options'] as $key => $val) {
                update_option($key, $val, false);
                $options++;
            }
        }

        return array(
            'features'    => count($clean['features']),
            'settings'    => count($clean['settings']),
            'snippets'    => $imported,
            'connections' => $connections,
            'options'     => $options,
            'snapshot'    => $snapshot,
        );
    }
}
