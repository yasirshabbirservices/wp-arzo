<?php

/**
 * Plugin Name: WP Arzo - Maintenance & Administration Suite
 * Plugin URI: https://github.com/yasirshabbirservices/wp-arzo
 * Description: Ultimate WordPress Maintenance & Administration Suite
 * Version: 6.143.0
 * Author: Yasir Shabbir
 * Author URI: https://yasirshabbir.com
 * Text Domain: wp-arzo
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Prevent duplicate loading if multiple copies of the plugin exist.
if (defined('WP_ARZO_PLUGIN_FILE') && realpath(WP_ARZO_PLUGIN_FILE) !== realpath(__FILE__)) {
    return;
}

if (!defined('WP_ARZO_PLUGIN_FILE')) {
    define('WP_ARZO_PLUGIN_FILE', __FILE__);
}

// Define plugin constants (allowing overrides for advanced setups)
if (!defined('WP_ARZO_VERSION')) {
    define('WP_ARZO_VERSION', '6.143.0');
}

if (!defined('WP_ARZO_PLUGIN_DIR')) {
    define('WP_ARZO_PLUGIN_DIR', plugin_dir_path(WP_ARZO_PLUGIN_FILE));
}

if (!defined('WP_ARZO_PLUGIN_URL')) {
    define('WP_ARZO_PLUGIN_URL', plugin_dir_url(WP_ARZO_PLUGIN_FILE));
}

// Optional debug mode (can be hard-defined in wp-config.php)
if (!defined('WP_ARZO_DEBUG')) {
    define('WP_ARZO_DEBUG', (defined('WP_DEBUG') && WP_DEBUG));
}

/**
 * Return a cache‑busting version string for a given asset.
 *
 * The buster changes whenever the file's content changes — so CSS/JS edits take
 * effect WITHOUT bumping the plugin version. It does NOT rely on filemtime alone
 * (some hosts / LiteSpeed setups return a falsy mtime, which previously forced a
 * fallback to the static plugin version and required a manual bump):
 *
 *   1. WP_DEBUG/dev → unique per request, so edits show instantly.
 *   2. filemtime + filesize → changes on any edit (size catches same-second edits).
 *   3. short content hash → bulletproof when stat() is locked down.
 *   4. plugin version → last resort.
 *
 * @param string $relative_path Path relative to plugin root, e.g. 'assets/css/wp-arzo.css'.
 * @return string
 */
if (!function_exists('wp_arzo_get_asset_version')) {
    function wp_arzo_get_asset_version($relative_path)
    {
        $relative_path = ltrim($relative_path, '/\\');
        $file = WP_ARZO_PLUGIN_DIR . $relative_path;

        clearstatcache(false, $file);
        $mtime = @filemtime($file);

        // Dev mode: never cache between edits.
        if (defined('WP_ARZO_DEBUG') && WP_ARZO_DEBUG) {
            return ($mtime ? $mtime : WP_ARZO_VERSION) . '.' . time();
        }

        $size = @filesize($file);
        if ($mtime && $size) {
            return $mtime . '-' . $size;
        }
        if ($mtime) {
            return (string) $mtime;
        }

        // filemtime unavailable (locked-down host): hash the contents instead so the
        // buster still changes on every real edit.
        if (is_readable($file)) {
            $hash = @md5_file($file);
            if ($hash) {
                return substr($hash, 0, 12);
            }
        }

        return WP_ARZO_VERSION;
    }
}

/**
 * Build a cache‑safe asset URL with automatic minified/production support.
 *
 * - In non‑debug mode it prefers `.min.*` files when present.
 * - Appends a `ver` query arg based on filemtime() for hard cache busting.
 *
 * @param string $relative_path Path relative to plugin root.
 * @param array  $args          Optional args: ['use_minified' => bool].
 * @return string
 */
if (!function_exists('wp_arzo_get_asset_url')) {
    function wp_arzo_get_asset_url($relative_path, $args = array())
    {
        if (!function_exists('wp_parse_args')) {
            $args = array();
        }

        $defaults = array(
            'use_minified' => !WP_ARZO_DEBUG,
        );

        $args = function_exists('wp_parse_args') ? wp_parse_args($args, $defaults) : $defaults;

        $relative_path = ltrim($relative_path, '/\\');
        $candidate_path = $relative_path;

        if (!empty($args['use_minified'])) {
            $min_path = preg_replace('/\.([^.]+)$/', '.min.$1', $relative_path);
            if ($min_path && file_exists(WP_ARZO_PLUGIN_DIR . $min_path)) {
                $candidate_path = $min_path;
            }
        }

        $url = WP_ARZO_PLUGIN_URL . str_replace(DIRECTORY_SEPARATOR, '/', $candidate_path);
        $ver = wp_arzo_get_asset_version($candidate_path);

        $sep = (strpos($url, '?') === false) ? '?' : '&';

        return $url . $sep . 'ver=' . rawurlencode($ver);
    }
}

/**
 * Invalidate OPcache for all plugin PHP files (best‑effort).
 *
 * This is called on activation and on version changes to avoid stale bytecode.
 */
if (!function_exists('wp_arzo_invalidate_opcache_for_plugin')) {
    function wp_arzo_invalidate_opcache_for_plugin()
    {
        if (!function_exists('opcache_invalidate')) {
            return;
        }

        $dir = WP_ARZO_PLUGIN_DIR;
        if (!is_dir($dir)) {
            return;
        }

        // Fail‑safe: if SPL iterators are not available, invalidate just main file.
        if (!class_exists('RecursiveIteratorIterator') || !class_exists('RecursiveDirectoryIterator')) {
            @opcache_invalidate(WP_ARZO_PLUGIN_FILE, true);
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file_info) {
            /** @var SplFileInfo $file_info */
            if ($file_info->isFile() && strtolower($file_info->getExtension()) === 'php') {
                @opcache_invalidate($file_info->getPathname(), true);
            }
        }
    }
}

/**
 * Return structured debug information about the currently‑loaded plugin instance.
 *
 * Used by the Debug feature to show path, version, and OPcache state.
 *
 * @return array
 */
if (!function_exists('wp_arzo_get_plugin_debug_info')) {
    function wp_arzo_get_plugin_debug_info()
    {
        $info = array(
            'plugin_file'    => WP_ARZO_PLUGIN_FILE,
            'plugin_dir'     => WP_ARZO_PLUGIN_DIR,
            'plugin_url'     => WP_ARZO_PLUGIN_URL,
            'version_header' => WP_ARZO_VERSION,
            'version_stored' => get_option('wp_arzo_version'),
            'debug_mode'     => WP_ARZO_DEBUG ? 'enabled' : 'disabled',
        );

        if (function_exists('opcache_get_status')) {
            $status = @opcache_get_status(false);

            $info['opcache_enabled'] = (is_array($status) && !empty($status['opcache_enabled']));

            if (!empty($info['opcache_enabled']) && !empty($status['scripts']) && is_array($status['scripts'])) {
                $plugin_realpath = realpath(WP_ARZO_PLUGIN_FILE);

                foreach ($status['scripts'] as $script_path => $script_info) {
                    if (realpath($script_path) === $plugin_realpath) {
                        $info['opcache_script'] = array(
                            'file_cached'        => true,
                            'file_mtime'         => @filemtime($plugin_realpath),
                            'opcache_timestamp'  => isset($script_info['timestamp']) ? $script_info['timestamp'] : null,
                            'opcache_last_used'  => isset($script_info['last_used_timestamp']) ? $script_info['last_used_timestamp'] : null,
                            'memory_consumption' => isset($script_info['memory_consumption']) ? $script_info['memory_consumption'] : null,
                        );
                        break;
                    }
                }
            }
        } else {
            $info['opcache_enabled'] = false;
        }

        return $info;
    }
}

/**
 * Add rewrite rule for emergency script
 */
function wp_arzo_add_rewrite_rules()
{
    add_rewrite_rule('^wp-arzo/emergency/?$', 'index.php?wp_arzo_emergency=1', 'top');
}
add_action('init', 'wp_arzo_add_rewrite_rules');

/**
 * Register query var
 */
function wp_arzo_register_query_vars($vars)
{
    $vars[] = 'wp_arzo_emergency';
    return $vars;
}
add_filter('query_vars', 'wp_arzo_register_query_vars');

/**
 * Handle template redirect for emergency script
 */
function wp_arzo_template_redirect()
{
    if (get_query_var('wp_arzo_emergency')) {
        include(WP_ARZO_PLUGIN_DIR . 'wp-arzo-emergency/index.php');
        exit;
    }
}
add_action('template_redirect', 'wp_arzo_template_redirect');

/**
 * Flush rewrite rules on activation
 */
function wp_arzo_activate()
{
    wp_arzo_add_rewrite_rules();
    flush_rewrite_rules();

    // Store current version and proactively refresh OPcache for this plugin.
    if (function_exists('update_option')) {
        update_option('wp_arzo_version', WP_ARZO_VERSION, false);
    }

    // First-run: send the user to the Setup Wizard (once) unless they've already
    // completed it on a prior install.
    if (function_exists('get_option') && !get_option('wp_arzo_wizard_done')) {
        set_transient('wp_arzo_wizard_redirect', 1, 60);
    }

    wp_arzo_invalidate_opcache_for_plugin();
}
register_activation_hook(__FILE__, 'wp_arzo_activate');

/**
 * Deactivation hook.
 *
 * - Ensures maintenance mode is turned off.
 * - Clears short‑lived transients created by the plugin.
 * - Flushes rewrite rules related to the emergency endpoint.
 */
function wp_arzo_deactivate()
{
    // Always disable any active maintenance mode on deactivation.
    delete_option('maintenance_tool_active_mode');

    // Clear scheduled-backup cron so it doesn't linger while deactivated.
    wp_clear_scheduled_hook('wp_arzo_scheduled_backup');
    // Clear the email retry-queue cron.
    wp_clear_scheduled_hook('wp_arzo_email_retry');

    // Clean up transient‑based access tokens.
    if (function_exists('delete_transient')) {
        global $wpdb;

        // Find all transients that start with "maintenance_access_".
        $like = $wpdb->esc_like('maintenance_access_') . '%';
        $option_names = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_' . $like,
                '_transient_timeout_' . $like
            )
        );

        if (!empty($option_names)) {
            foreach ($option_names as $option_name) {
                $key = str_replace(array('_transient_', '_transient_timeout_'), '', $option_name);
                delete_transient($key);
            }
        }
    }

    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'wp_arzo_deactivate');

/**
 * Uninstall hook.
 *
 * Fully removes plugin data (options, transients, emergency config) when the
 * plugin is uninstalled via the WordPress UI.
 */
function wp_arzo_uninstall()
{
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        return;
    }

    // Remove all plugin‑specific options.
    $options = array(
        'maintenance_tool_active_mode',
        'maintenance_tool_custom_title',
        'maintenance_tool_custom_message',
        'maintenance_tool_custom_css',
        'maintenance_tool_show_social_contacts',
        'maintenance_tool_developer_email',
        'maintenance_tool_developer_phone',
        'maintenance_tool_developer_whatsapp',
        'maintenance_tool_developer_skype',
        'wp_arzo_version',
        'wp_arzo_features',
        'wp_arzo_settings',
        'wp_arzo_email_log',
        'wp_arzo_email_stats',
        'wp_arzo_email_queue',
        'wp_arzo_snippets',
        'wp_arzo_sched_freq',
        'wp_arzo_activity_log',
        'wp_arzo_activity_failwin',
        'wp_arzo_wizard_done',
        'wp_arzo_rest_api_keys',
        'wp_arzo_leads',
        'wp_arzo_ll_lockouts',
        'wp_arzo_smtp_connections',
        'wp_arzo_analytics_db',
        'wp_arzo_analytics_salt',
    );

    foreach ($options as $option) {
        delete_option($option);
    }

    // Drop the analytics tables + clear its prune/rollup crons.
    if (function_exists('wp_clear_scheduled_hook')) {
        wp_clear_scheduled_hook('wp_arzo_analytics_prune');
        wp_clear_scheduled_hook('wp_arzo_analytics_rollup');
    }
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wp_arzo_analytics_hits");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wp_arzo_analytics_events");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wp_arzo_analytics_orders");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wp_arzo_analytics_daily");

    // Remove temporary-login users + their scheduled cleanup.
    $temp_login = WP_ARZO_PLUGIN_DIR . 'includes/class-wp-arzo-temp-login.php';
    if (file_exists($temp_login)) {
        require_once $temp_login;
        if (class_exists('WP_Arzo_Temp_Login')) {
            WP_Arzo_Temp_Login::delete_all();
        }
    }

    // Clean up any remaining transients.
    if (function_exists('delete_transient')) {
        global $wpdb;

        $like = $wpdb->esc_like('maintenance_access_') . '%';
        $option_names = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_' . $like,
                '_transient_timeout_' . $like
            )
        );

        if (!empty($option_names)) {
            foreach ($option_names as $option_name) {
                $key = str_replace(array('_transient_', '_transient_timeout_'), '', $option_name);
                delete_transient($key);
            }
        }
    }

    // Remove emergency config file if present.
    $config_file = WP_ARZO_PLUGIN_DIR . 'arzo-safe.php';
    if (file_exists($config_file)) {
        @unlink($config_file);
    }

    // Remove the runtime-generated AdminNeo connection config (contains DB credentials).
    $adminneo_config = WP_ARZO_PLUGIN_DIR . 'assets/libs/adminneo/adminneo-config.php';
    if (file_exists($adminneo_config)) {
        @unlink($adminneo_config);
    }

    // Remove the emergency-tool brute-force throttle file if present.
    $throttle_file = WP_ARZO_PLUGIN_DIR . '.arzo-throttle.json';
    if (file_exists($throttle_file)) {
        @unlink($throttle_file);
    }

    // Final rewrite flush for emergency route cleanup.
    flush_rewrite_rules();
}
register_uninstall_hook(__FILE__, 'wp_arzo_uninstall');

/**
 * Version bump handler.
 *
 * Runs on every request and only acts when the stored version differs from the
 * current header version. This ensures that OPcache and any stale runtime
 * caches are cleared immediately after an update without requiring manual steps.
 */
function wp_arzo_maybe_upgrade_plugin()
{
    $stored = get_option('wp_arzo_version');

    if ($stored === WP_ARZO_VERSION) {
        return;
    }

    update_option('wp_arzo_version', WP_ARZO_VERSION, false);

    // Best‑effort OPcache invalidation for all plugin PHP files.
    wp_arzo_invalidate_opcache_for_plugin();
}
add_action('plugins_loaded', 'wp_arzo_maybe_upgrade_plugin');

// Ensure rewrite rules are flushed if they don't exist
function wp_arzo_check_rewrite_rules()
{
    $rules = get_option('rewrite_rules');
    if (!isset($rules['^wp-arzo/emergency/?$'])) {
        wp_arzo_add_rewrite_rules();
        flush_rewrite_rules();
    }
}
add_action('admin_init', 'wp_arzo_check_rewrite_rules');

// Shared icon system (inline SVG helper used across the console & features).
require_once(WP_ARZO_PLUGIN_DIR . 'includes/wp-arzo-icons.php');

// GitHub-release self-updater (admin only): one-click / auto updates from Releases.
require_once(WP_ARZO_PLUGIN_DIR . 'includes/class-wp-arzo-updater.php');
if (is_admin()) {
    WP_Arzo_Updater::boot(plugin_basename(WP_ARZO_PLUGIN_FILE), WP_ARZO_VERSION, 'yasirshabbirservices/wp-arzo');
}

// Load Maintenance Mode Frontend
require_once(WP_ARZO_PLUGIN_DIR . 'includes/maintenance-frontend.php');

// Feature-manager: base class + registry + built-in feature modules + admin dashboard.
require_once(WP_ARZO_PLUGIN_DIR . 'includes/class-wp-arzo-feature.php');
require_once(WP_ARZO_PLUGIN_DIR . 'includes/class-wp-arzo-feature-registry.php');
require_once(WP_ARZO_PLUGIN_DIR . 'includes/class-wp-arzo-backup-manager.php');
require_once(WP_ARZO_PLUGIN_DIR . 'includes/class-wp-arzo-snippets.php');
require_once(WP_ARZO_PLUGIN_DIR . 'includes/class-wp-arzo-media-cleanup.php');
require_once(WP_ARZO_PLUGIN_DIR . 'includes/class-wp-arzo-temp-login.php');
require_once(WP_ARZO_PLUGIN_DIR . 'includes/class-wp-arzo-email-connections.php');
require_once(WP_ARZO_PLUGIN_DIR . 'includes/class-wp-arzo-email-queue.php');
require_once(WP_ARZO_PLUGIN_DIR . 'includes/admin/class-wp-arzo-admin.php');
require_once(WP_ARZO_PLUGIN_DIR . 'includes/admin/class-wp-arzo-setup-wizard.php');

// Temporary-login engine runs site-wide so magic links work outside the console.
add_action('plugins_loaded', function () {
    if (class_exists('WP_Arzo_Temp_Login')) {
        WP_Arzo_Temp_Login::instance()->init();
    }
}, 15);

// On-demand feature loading: feature module classes are NO LONGER eagerly required here.
// The registry autoloader pulls a feature's class file only when it's enabled/booted or
// its page/settings are opened (see register_lazy() in wp_arzo_bootstrap_features()), so a
// front-end / cron / REST request parses only the classes for ENABLED features.
//
// Two files define bootstrap-time FUNCTIONS (not just feature classes) and so must load
// eagerly: the console-tools registrar + the Pro-catalog placeholder registrar.
require_once(WP_ARZO_PLUGIN_DIR . 'includes/features-registry/class-features-advanced-tools.php');
require_once(WP_ARZO_PLUGIN_DIR . 'includes/features-registry/class-feature-pro-placeholders.php');

/**
 * Register built-in features, let add-ons (e.g. WP Arzo Pro) register theirs, then
 * boot every enabled feature. Runs on plugins_loaded so features can hook init etc.
 */
function wp_arzo_bootstrap_features()
{
    $registry = WP_Arzo_Feature_Registry::instance();

    $features_dir = WP_ARZO_PLUGIN_DIR . 'includes/features-registry/';

    // Lazily register every built-in feature: id => [class, file]. The class file is NOT
    // loaded here — the registry autoloader pulls it in only when the feature is enabled
    // (booted) or its page/settings are opened. None of these default to enabled, so no
    // fourth argument is needed (the console tools below are the only default-on set).
    // Order is preserved for the dashboard grid (grouped() sorts by group, then by this
    // registration order within each group).
    $lazy_features = array(
        // Analytics (built-in, cookieless, first-party) + Google tag insertion (free)
        'analytics'                 => array('WP_Arzo_Feature_Analytics', 'class-feature-analytics.php'),
        'google_analytics_4'        => array('WP_Arzo_Feature_GA4', 'class-feature-google-tags.php'),
        'google_tag_manager'        => array('WP_Arzo_Feature_GTM', 'class-feature-google-tags.php'),
        'google_ads'                => array('WP_Arzo_Feature_Google_Ads', 'class-feature-google-tags.php'),

        'disable_comments'          => array('WP_Arzo_Feature_Disable_Comments', 'class-feature-disable-comments.php'),
        'hide_admin_bar'            => array('WP_Arzo_Feature_Hide_Admin_Bar', 'class-feature-hide-admin-bar.php'),
        'disable_xmlrpc'            => array('WP_Arzo_Feature_Disable_XMLRPC', 'class-feature-disable-xmlrpc.php'),
        'disable_dashboard_widgets' => array('WP_Arzo_Feature_Disable_Dashboard_Widgets', 'class-feature-disable-dashboard-widgets.php'),

        // Core controls
        'disable_gutenberg'         => array('WP_Arzo_Feature_Disable_Gutenberg', 'class-features-core.php'),
        'disable_feeds'             => array('WP_Arzo_Feature_Disable_Feeds', 'class-features-core.php'),
        'disable_embeds'            => array('WP_Arzo_Feature_Disable_Embeds', 'class-features-core.php'),
        'disable_updates'           => array('WP_Arzo_Feature_Disable_Updates', 'class-features-admin-tweaks.php'),

        // Content & media (Media Folders is a Pro feature — advertised via the catalog placeholder)
        'duplicate_posts'           => array('WP_Arzo_Feature_Duplicate_Posts', 'class-features-content.php'),
        'missed_schedule'           => array('WP_Arzo_Feature_Missed_Schedule', 'class-features-content.php'),
        'svg_upload'                => array('WP_Arzo_Feature_SVG_Upload', 'class-features-content.php'),
        'webp_convert'              => array('WP_Arzo_Feature_WebP', 'class-feature-webp.php'),
        'media_cleanup'             => array('WP_Arzo_Feature_Media_Cleanup', 'class-feature-media-cleanup.php'),
        'media_replace'             => array('WP_Arzo_Feature_Media_Replace', 'class-feature-media-replace.php'),
        'image_seo'                 => array('WP_Arzo_Feature_Image_SEO', 'class-feature-image-seo.php'),
        'disable_archives'          => array('WP_Arzo_Feature_Disable_Archives', 'class-feature-disable-archives.php'),
        'content_order'             => array('WP_Arzo_Feature_Content_Order', 'class-feature-content-order.php'),

        // Developer
        'code_snippets'             => array('WP_Arzo_Feature_Snippets', 'class-feature-snippets.php'),

        // Security & Access — audit trail
        'activity_log'              => array('WP_Arzo_Feature_Activity_Log', 'class-feature-activity-log.php'),

        // Admin tweaks
        'last_login'                => array('WP_Arzo_Feature_Last_Login', 'class-features-admin-tweaks.php'),
        'custom_code'               => array('WP_Arzo_Feature_Custom_Code', 'class-features-admin-tweaks.php'),
        'custom_css'                => array('WP_Arzo_Feature_Custom_CSS', 'class-features-admin-tweaks.php'),
        'login_redirect'            => array('WP_Arzo_Feature_Login_Redirect', 'class-features-admin-tweaks.php'),
        'custom_body_class'         => array('WP_Arzo_Feature_Custom_Body_Class', 'class-features-cleanup.php'),
        'clean_admin_bar'           => array('WP_Arzo_Feature_Clean_Admin_Bar', 'class-features-cleanup.php'),
        'enhance_list_tables'       => array('WP_Arzo_Feature_Enhance_List_Tables', 'class-features-cleanup.php'),
        'crawl_optimizations'       => array('WP_Arzo_Feature_Crawl_Optimizations', 'class-features-cleanup.php'),

        // Performance / content
        'disable_emojis'            => array('WP_Arzo_Feature_Disable_Emojis', 'class-features-performance.php'),
        'heartbeat_control'         => array('WP_Arzo_Feature_Heartbeat_Control', 'class-features-performance.php'),
        'disable_self_pingbacks'    => array('WP_Arzo_Feature_Disable_Self_Pingbacks', 'class-features-performance.php'),
        'limit_revisions'           => array('WP_Arzo_Feature_Limit_Revisions', 'class-features-performance.php'),

        // Security
        'disable_rest_api_guests'   => array('WP_Arzo_Feature_Disable_REST_Guests', 'class-features-security.php'),
        'disable_file_editor'       => array('WP_Arzo_Feature_Disable_File_Editor', 'class-features-security.php'),
        'block_user_enumeration'    => array('WP_Arzo_Feature_Block_User_Enumeration', 'class-features-security.php'),
        'custom_login_url'          => array('WP_Arzo_Feature_Custom_Login_URL', 'class-features-security-extra.php'),
        'limit_login'               => array('WP_Arzo_Feature_Limit_Login', 'class-features-security-extra.php'),
        'disable_app_passwords'     => array('WP_Arzo_Feature_Disable_App_Passwords', 'class-features-cleanup.php'),
        'rest_api_auth'             => array('WP_Arzo_Feature_REST_API_Auth', 'class-feature-rest-api-auth.php'),
        // Two-Factor Authentication is a PRO feature (see wp-arzo-pro) — advertised as a catalog placeholder.

        // Core controls / admin tooling
        'role_manager'              => array('WP_Arzo_Feature_Role_Manager', 'class-feature-role-manager.php'),

        // Marketing / SEO
        'manage_robots_txt'         => array('WP_Arzo_Feature_Manage_Robots_Txt', 'class-features-marketing.php'),
        'manage_ads_txt'            => array('WP_Arzo_Feature_Manage_Ads_Txt', 'class-features-marketing.php'),
        'site_verification'         => array('WP_Arzo_Feature_Site_Verification', 'class-features-perf-extra.php'),

        // Performance extras
        'remove_jquery_migrate'     => array('WP_Arzo_Feature_Remove_JQuery_Migrate', 'class-features-perf-extra.php'),
        'disable_front_dashicons'   => array('WP_Arzo_Feature_Disable_Front_Dashicons', 'class-features-perf-extra.php'),

        // Email
        'smtp'                      => array('WP_Arzo_Feature_SMTP', 'class-features-email.php'),
        'email_log'                 => array('WP_Arzo_Feature_Email_Log', 'class-features-email.php'),

        // Branding
        'custom_login'              => array('WP_Arzo_Feature_Custom_Login', 'class-feature-custom-login.php'),

        // Backup & restore
        'auto_snapshots'            => array('WP_Arzo_Feature_Backups', 'class-feature-backups.php'),
        'scheduled_backups'         => array('WP_Arzo_Feature_Scheduled_Backups', 'class-feature-scheduled-backups.php'),
    );
    foreach ($lazy_features as $fid => $meta) {
        $registry->register_lazy($fid, $meta[0], $features_dir . $meta[1]);
    }

    // Config Import/Export is a permanent tool (always available, no toggle). It is NOT a
    // registered feature — its helper class is only referenced statically from the Settings
    // hub. Map its class so the autoloader can resolve it on demand (it used to be pulled in
    // by the now-removed features-registry glob).
    $registry->map_class('WP_Arzo_Feature_Config_IO', $features_dir . 'class-feature-config-io.php');

    // Advanced Tools (standalone console) — per-tool enable/disable toggles. These share one
    // class (WP_Arzo_Feature_Console_Tool, differing only by constructor args) and default to
    // enabled, so they stay on the eager register() path (their file is loaded above).
    if (function_exists('wp_arzo_register_console_tools')) {
        wp_arzo_register_console_tools($registry);
    }

    /**
     * Add-ons register their own feature modules here.
     *
     * @param WP_Arzo_Feature_Registry $registry
     */
    do_action('wp_arzo_register_features', $registry);

    // Advertise the Pro catalog: register a locked placeholder for every Pro
    // module the add-on did not already register (i.e. when Pro is not installed),
    // so free users still see what the paid tier offers. Runs after the action so
    // genuine Pro modules always take precedence.
    if (function_exists('wp_arzo_register_pro_placeholders')) {
        wp_arzo_register_pro_placeholders($registry);
    }

    $registry->boot_enabled();

    if (is_admin()) {
        WP_Arzo_Admin::instance()->init();
        if (class_exists('WP_Arzo_Setup_Wizard')) {
            WP_Arzo_Setup_Wizard::instance()->init();
        }
    }
}

// Clear the scheduled-backup cron when that feature is turned off.
add_action('wp_arzo_feature_disabled', function ($id) {
    if ($id === 'scheduled_backups') {
        wp_clear_scheduled_hook('wp_arzo_scheduled_backup');
        delete_option('wp_arzo_sched_freq');
    }
});
add_action('plugins_loaded', 'wp_arzo_bootstrap_features', 20);

/**
 * Freemium gate. Free-tier features are always available; pro-tier features are
 * available only when a Pro signal is present. The WP Arzo Pro add-on flips
 * `wp_arzo_pro_active` (true) once it is installed and licensed via Freemius.
 */
if (!function_exists('wp_arzo_is_pro_active')) {
    function wp_arzo_is_pro_active()
    {
        return (bool) apply_filters('wp_arzo_pro_active', false);
    }
}

add_filter('wp_arzo_feature_is_available', function ($available, $feature) {
    if (!($feature instanceof WP_Arzo_Feature)) {
        return $available;
    }
    if ($feature->tier() !== 'pro') {
        return true;
    }
    return wp_arzo_is_pro_active();
}, 10, 2);

/**
 * Upgrade / "get Pro" URL used by the dashboard's locked-feature CTA. Filterable so
 * the Pro add-on (or Freemius) can point it at the real checkout/account URL.
 */
if (!function_exists('wp_arzo_pro_upgrade_url')) {
    function wp_arzo_pro_upgrade_url()
    {
        return apply_filters('wp_arzo_pro_upgrade_url', 'https://yasirshabbir.com/wp-arzo/');
    }
}

/**
 * The admin menu (top-level "WP Arzo" dashboard + "Advanced Tools" submenu) is
 * registered by WP_Arzo_Admin (see includes/admin/class-wp-arzo-admin.php), which
 * boots from wp_arzo_bootstrap_features(). The console launcher below is used by the
 * "Advanced Tools" submenu.
 */

/**
 * Redirect callback - opens the standalone console in a new tab
 */
function wp_arzo_redirect_page()
{
    // Renders INSIDE the normal wp-admin page (WP already emitted the admin header),
    // so this must be a content fragment — never a full <html> document, and never
    // exit() (that would drop the admin footer and produce a cut-off page). It is a
    // self-contained launcher card (brand palette inline) that opens the standalone
    // Advanced Tools console in a new tab, with a button fallback when popups are blocked.
    $tool_url = admin_url('admin-ajax.php?action=wp_arzo_standalone');
    $dash_url = admin_url('admin.php?page=wp-arzo');
    $glyph    = defined('WP_ARZO_PLUGIN_URL') ? WP_ARZO_PLUGIN_URL . 'assets/wp-arzo-glyph.svg' : '';
    ?>
    <style>
        /* Self-contained WP Arzo token subset (same values as design-tokens.css) so the
           whole launcher is on-brand even before any plugin CSS is enqueued here. */
        :root {
            --arzo-bg-dark:#121212; --arzo-bg-panel:#1e1e1e; --arzo-bg-elev:#242424;
            --arzo-border:#333333; --arzo-border-strong:#444444;
            --arzo-text-strong:#ffffff; --arzo-text-primary:#e0e0e0; --arzo-text-secondary:#999999;
            --arzo-accent:#16e791; --arzo-accent-hover:#0ea66b; --arzo-accent-soft:rgba(22,231,145,.12);
            --arzo-radius:8px; --arzo-radius-lg:14px; --arzo-text-on-accent:#121212;
        }
        /* Paint the ENTIRE admin content area with the brand dark surface (not white). */
        #wpwrap, #wpcontent, #wpbody, #wpbody-content { background: var(--arzo-bg-dark) !important; }
        #wpbody-content { padding-bottom: 0; }
        #wpfooter, #wpfooter a { color: var(--arzo-text-secondary) !important; }
        .wpa-launch-wrap { margin: 0; }
        .wpa-launch-inner { min-height: calc(100vh - 120px); display: flex; align-items: center; justify-content: center; padding: 40px 20px; box-sizing: border-box; }
        .wpa-launch { max-width: 540px; width: 100%; text-align: center; background: var(--arzo-bg-panel); border: 1px solid var(--arzo-border); border-radius: var(--arzo-radius-lg); padding: 48px 40px; box-shadow: 0 24px 70px rgba(0, 0, 0, .5); }
        .wpa-launch__badge { width: 76px; height: 76px; margin: 0 auto 22px; border-radius: 20px; background: var(--arzo-accent-soft); border: 1px solid var(--arzo-border); display: flex; align-items: center; justify-content: center; }
        .wpa-launch__badge img { width: 42px; height: 42px; display: block; }
        .wpa-launch__spin { width: 18px; height: 18px; border: 2px solid var(--arzo-border-strong); border-top-color: var(--arzo-accent); border-radius: 50%; display: inline-block; vertical-align: -3px; margin-right: 6px; animation: wpaLaunchSpin .8s linear infinite; }
        @keyframes wpaLaunchSpin { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .wpa-launch__spin { animation: none; } }
        .wpa-launch__title { color: var(--arzo-text-strong); font-size: 24px; font-weight: 700; margin: 0 0 8px; padding: 0; line-height: 1.2; }
        .wpa-launch__msg { color: var(--arzo-text-secondary); font-size: 14px; margin: 0 0 26px; line-height: 1.55; }
        .wpa-launch__btn { display: inline-flex; align-items: center; gap: 8px; padding: 14px 30px; background: var(--arzo-accent); color: var(--arzo-text-on-accent); text-decoration: none; border-radius: var(--arzo-radius); font-weight: 600; font-size: 15px; transition: background .18s ease, transform .18s ease, box-shadow .18s ease; }
        .wpa-launch__btn:hover, .wpa-launch__btn:active { background: var(--arzo-accent-hover); color: var(--arzo-text-on-accent); transform: translateY(-1px); box-shadow: 0 10px 26px var(--arzo-accent-soft); }
        .wpa-launch__btn:focus-visible { outline: 2px solid var(--arzo-accent); outline-offset: 3px; }
        .wpa-launch__btn svg { width: 18px; height: 18px; }
        .wpa-launch__status { margin: 18px 0 0; font-size: 13px; color: var(--arzo-accent); line-height: 1.6; min-height: 1.6em; }
        .wpa-launch__foot { margin: 24px 0 0; font-size: 13px; color: var(--arzo-text-secondary); }
        .wpa-launch__foot a { color: var(--arzo-text-primary); text-decoration: none; border-bottom: 1px solid var(--arzo-border-strong); padding-bottom: 1px; }
        .wpa-launch__foot a:hover, .wpa-launch__foot a:focus { color: var(--arzo-accent); border-bottom-color: var(--arzo-accent); }
    </style>
    <div class="wrap wpa-launch-wrap">
        <div class="wpa-launch-inner">
            <div class="wpa-launch">
                <?php if ($glyph !== '') : ?>
                    <div class="wpa-launch__badge"><img src="<?php echo esc_url($glyph); ?>" alt="" width="42" height="42"></div>
                <?php endif; ?>
                <h1 class="wpa-launch__title">Advanced Tools</h1>
                <p class="wpa-launch__msg" id="wpa-launch-msg"><span class="wpa-launch__spin" id="wpa-launch-spin" aria-hidden="true"></span>Opening the standalone power-tools console in a new browser tab&hellip;</p>
                <a href="<?php echo esc_url($tool_url); ?>" target="_blank" rel="noopener" class="wpa-launch__btn" id="wpa-launch-btn"><?php echo wp_arzo_icon('external', array()); ?> Open Advanced Tools</a>
                <p class="wpa-launch__status" id="wpa-launch-status"></p>
                <p class="wpa-launch__foot"><a href="<?php echo esc_url($dash_url); ?>">&larr; Back to the WP Arzo dashboard</a></p>
            </div>
        </div>
    </div>
    <script>
        (function () {
            var url = <?php echo wp_json_encode($tool_url); ?>;
            var win = window.open(url, '_blank');
            setTimeout(function () {
                var blocked = !win || win.closed || typeof win.closed === 'undefined';
                var sp = document.getElementById('wpa-launch-spin');
                if (sp) { sp.style.display = 'none'; }
                var msg = document.getElementById('wpa-launch-msg');
                var status = document.getElementById('wpa-launch-status');
                if (blocked) {
                    if (msg) { msg.textContent = 'Your browser blocked the pop-up.'; }
                    if (status) { status.textContent = 'Click “Open Advanced Tools” above to launch it.'; }
                } else {
                    if (msg) { msg.textContent = 'Advanced Tools opened in a new tab.'; }
                    if (status) { status.innerHTML = 'You can close this and continue in the other tab.'; }
                }
            }, 600);
        })();
    </script>
    <?php
}

/**
 * Handle the standalone page request
 */
function wp_arzo_handle_standalone()
{
    // --- Emergency one-time admin access link ---------------------------------
    // A logged-out admin can re-enter via a single-use, time-limited token
    // generated from the Quick Login tab. This MUST run before the capability
    // gate below, otherwise the link could never work for a logged-out user.
    if (isset($_GET['maintenance_access'])) {
        $token = sanitize_text_field(wp_unslash($_GET['maintenance_access']));
        $stored_user_id = get_transient('maintenance_access_' . $token);

        if ($stored_user_id) {
            // Single use: invalidate immediately.
            delete_transient('maintenance_access_' . $token);

            $target = get_user_by('id', (int) $stored_user_id);
            if ($target && user_can($target, 'manage_options')) {
                wp_clear_auth_cookie();
                wp_set_current_user($target->ID, $target->user_login);
                wp_set_auth_cookie($target->ID, true);
                do_action('wp_login', $target->user_login, $target);

                wp_safe_redirect(admin_url());
                exit;
            }
        }

        wp_die('This access link is invalid or has expired.');
    }

    // Check if user is logged in and has admin capabilities
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }

    // Include the modular tool file
    include(WP_ARZO_PLUGIN_DIR . 'includes/wp-arzo-modular.php');
    exit;
}
add_action('wp_ajax_wp_arzo_standalone', 'wp_arzo_handle_standalone');
