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

    /** Build the export payload from the current WP Arzo state. */
    public static function export_payload()
    {
        $features    = get_option(WP_Arzo_Feature_Registry::OPT_FEATURES, array());
        $settings    = get_option(WP_Arzo_Feature_Registry::OPT_SETTINGS, array());
        $snippets    = class_exists('WP_Arzo_Snippets') ? WP_Arzo_Snippets::instance()->get_all() : array();
        $connections = get_option('wp_arzo_smtp_connections', array());

        return array(
            'schema'         => self::SCHEMA,
            'plugin_version' => defined('WP_ARZO_VERSION') ? WP_ARZO_VERSION : '',
            'exported_gmt'   => gmdate('Y-m-d H:i:s'),
            'site'           => home_url('/'),
            'features'       => is_array($features) ? $features : array(),
            'settings'       => is_array($settings) ? $settings : array(),
            'snippets'       => is_array($snippets) ? array_values($snippets) : array(),
            'connections'    => is_array($connections) ? $connections : array(),
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

        return array('features' => $features, 'settings' => $settings, 'snippets' => $snippets, 'connections' => $connections);
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

        return array(
            'features'    => count($clean['features']),
            'settings'    => count($clean['settings']),
            'snippets'    => $imported,
            'connections' => $connections,
            'snapshot'    => $snapshot,
        );
    }
}
