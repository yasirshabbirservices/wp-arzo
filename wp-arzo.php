<?php

/**
 * Plugin Name: WP Arzo - Maintenance & Administration Suite
 * Plugin URI: https://github.com/yasirshabbirservices/wp-arzo
 * Description: Ultimate WordPress Maintenance & Administration Suite
 * Version: 6.53.0
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
    define('WP_ARZO_VERSION', '6.53.0');
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
        'wp_arzo_snippets',
        'wp_arzo_sched_freq',
        'wp_arzo_activity_log',
        'wp_arzo_wizard_done',
        'wp_arzo_rest_api_keys',
        'wp_arzo_leads',
    );

    foreach ($options as $option) {
        delete_option($option);
    }

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
require_once(WP_ARZO_PLUGIN_DIR . 'includes/admin/class-wp-arzo-admin.php');
require_once(WP_ARZO_PLUGIN_DIR . 'includes/admin/class-wp-arzo-setup-wizard.php');

// Temporary-login engine runs site-wide so magic links work outside the console.
add_action('plugins_loaded', function () {
    if (class_exists('WP_Arzo_Temp_Login')) {
        WP_Arzo_Temp_Login::instance()->init();
    }
}, 15);

foreach ((array) glob(WP_ARZO_PLUGIN_DIR . 'includes/features-registry/*.php') as $wp_arzo_feature_file) {
    if ($wp_arzo_feature_file) {
        require_once($wp_arzo_feature_file);
    }
}

/**
 * Register built-in features, let add-ons (e.g. WP Arzo Pro) register theirs, then
 * boot every enabled feature. Runs on plugins_loaded so features can hook init etc.
 */
function wp_arzo_bootstrap_features()
{
    $registry = WP_Arzo_Feature_Registry::instance();

    $registry->register(new WP_Arzo_Feature_Disable_Comments());
    $registry->register(new WP_Arzo_Feature_Hide_Admin_Bar());
    $registry->register(new WP_Arzo_Feature_Disable_XMLRPC());
    $registry->register(new WP_Arzo_Feature_Disable_Dashboard_Widgets());

    // Core controls
    $registry->register(new WP_Arzo_Feature_Disable_Gutenberg());
    $registry->register(new WP_Arzo_Feature_Disable_Feeds());
    $registry->register(new WP_Arzo_Feature_Disable_Embeds());
    $registry->register(new WP_Arzo_Feature_Disable_Updates());

    // Content & media
    $registry->register(new WP_Arzo_Feature_Duplicate_Posts());
    $registry->register(new WP_Arzo_Feature_Missed_Schedule());
    $registry->register(new WP_Arzo_Feature_SVG_Upload());
    $registry->register(new WP_Arzo_Feature_WebP());
    $registry->register(new WP_Arzo_Feature_Media_Cleanup());
    // Media Folders is a Pro feature (the Pro add-on registers the full nested manager).
    // The free tier advertises it as a locked PRO card via wp_arzo_pro_feature_catalog().
    $registry->register(new WP_Arzo_Feature_Media_Replace());
    $registry->register(new WP_Arzo_Feature_Image_SEO());
    $registry->register(new WP_Arzo_Feature_Disable_Archives());
    $registry->register(new WP_Arzo_Feature_Content_Order());

    // Developer
    $registry->register(new WP_Arzo_Feature_Snippets());

    // Security & Access — audit trail
    $registry->register(new WP_Arzo_Feature_Activity_Log());

    // Advanced Tools (standalone console) — per-tool enable/disable toggles.
    if (function_exists('wp_arzo_register_console_tools')) {
        wp_arzo_register_console_tools($registry);
    }

    // Admin tweaks
    $registry->register(new WP_Arzo_Feature_Last_Login());
    $registry->register(new WP_Arzo_Feature_Custom_Code());
    $registry->register(new WP_Arzo_Feature_Custom_CSS());
    $registry->register(new WP_Arzo_Feature_Login_Redirect());
    $registry->register(new WP_Arzo_Feature_Custom_Body_Class());
    $registry->register(new WP_Arzo_Feature_Clean_Admin_Bar());
    $registry->register(new WP_Arzo_Feature_Enhance_List_Tables());
    $registry->register(new WP_Arzo_Feature_Crawl_Optimizations());

    // Performance / content
    $registry->register(new WP_Arzo_Feature_Disable_Emojis());
    $registry->register(new WP_Arzo_Feature_Heartbeat_Control());
    $registry->register(new WP_Arzo_Feature_Disable_Self_Pingbacks());
    $registry->register(new WP_Arzo_Feature_Limit_Revisions());

    // Security
    $registry->register(new WP_Arzo_Feature_Disable_REST_Guests());
    $registry->register(new WP_Arzo_Feature_Disable_File_Editor());
    $registry->register(new WP_Arzo_Feature_Block_User_Enumeration());
    $registry->register(new WP_Arzo_Feature_Custom_Login_URL());
    $registry->register(new WP_Arzo_Feature_Limit_Login());
    $registry->register(new WP_Arzo_Feature_Disable_App_Passwords());
    $registry->register(new WP_Arzo_Feature_REST_API_Auth());
    $registry->register(new WP_Arzo_Feature_Two_Factor());

    // Core controls / admin tooling. Config Import/Export is a permanent tool (always
    // available, no toggle) — its page is always registered; only the helper class loads.
    $registry->register(new WP_Arzo_Feature_Role_Manager());

    // Marketing / SEO
    $registry->register(new WP_Arzo_Feature_Manage_Robots_Txt());
    $registry->register(new WP_Arzo_Feature_Manage_Ads_Txt());
    $registry->register(new WP_Arzo_Feature_Site_Verification());

    // Performance extras
    $registry->register(new WP_Arzo_Feature_Remove_JQuery_Migrate());
    $registry->register(new WP_Arzo_Feature_Disable_Front_Dashicons());

    // Email
    $registry->register(new WP_Arzo_Feature_SMTP());
    $registry->register(new WP_Arzo_Feature_Email_Log());

    // Branding
    $registry->register(new WP_Arzo_Feature_Custom_Login());

    // Backup & restore
    $registry->register(new WP_Arzo_Feature_Backups());
    $registry->register(new WP_Arzo_Feature_Scheduled_Backups());

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
        return apply_filters('wp_arzo_pro_upgrade_url', 'https://yasirshabbir.com/wp-arzo-pro');
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
    $tool_url = admin_url('admin-ajax.php?action=wp_arzo_standalone');
?>
    <!DOCTYPE html>
    <html>

    <head>
        <title>Opening WP Arzo...</title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100vh;
                margin: 0;
                background: #121212;
            }

            .message {
                text-align: center;
                background: #1e1e1e;
                padding: 50px;
                border-radius: 8px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
                max-width: 450px;
                border: 1px solid #333333;
            }

            .spinner {
                border: 4px solid #2a2a2a;
                border-top: 4px solid #16e791;
                border-radius: 50%;
                width: 50px;
                height: 50px;
                animation: spin 1s linear infinite;
                margin: 0 auto 25px;
            }

            @keyframes spin {
                0% {
                    transform: rotate(0deg);
                }

                100% {
                    transform: rotate(360deg);
                }
            }

            h2 {
                color: #ffffff;
                margin: 0 0 15px;
                font-size: 24px;
                font-weight: 600;
            }

            p {
                color: #cccccc;
                margin: 0;
                font-size: 14px;
            }

            .btn {
                display: inline-block;
                margin-top: 25px;
                padding: 14px 32px;
                background: #16e791;
                color: #121212;
                text-decoration: none;
                border-radius: 4px;
                font-weight: 600;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(22, 231, 145, 0.3);
            }

            .btn:hover {
                background: #0ea66b;
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(22, 231, 145, 0.4);
            }

            #status {
                margin-top: 20px;
                font-size: 13px;
                color: #16e791;
                line-height: 1.6;
            }

            #status a {
                color: #16e791;
                text-decoration: underline;
            }

            #status a:hover {
                color: #ffffff;
            }
        </style>
    </head>

    <body>
        <div class="message">
            <div class="spinner" id="spinner"></div>
            <h2>Opening WP Arzo Tool...</h2>
            <p id="message">Please wait...</p>
            <a href="<?php echo esc_url($tool_url); ?>" target="_blank" class="btn" id="openBtn" style="display:none;">Open
                WP Arzo</a>
            <p id="status"></p>
        </div>
        <script>
            (function() {
                var toolUrl = '<?php echo esc_js($tool_url); ?>';
                var newWindow = window.open(toolUrl, '_blank');

                // Check if popup was blocked
                setTimeout(function() {
                    if (!newWindow || newWindow.closed || typeof newWindow.closed == 'undefined') {
                        // Popup was blocked
                        document.getElementById('spinner').style.display = 'none';
                        document.getElementById('message').textContent = 'Popup was blocked by your browser.';
                        document.getElementById('openBtn').style.display = 'inline-block';
                        document.getElementById('status').textContent = 'Please click the button above to open the tool.';
                    } else {
                        // Popup opened successfully
                        document.getElementById('message').textContent = 'Tool opened in new tab!';
                        document.getElementById('status').innerHTML = 'You can close this tab or <a href="<?php echo admin_url(); ?>">return to dashboard</a>';
                        document.getElementById('status').style.color = 'rgba(255, 255, 255, 0.8)';
                        document.getElementById('spinner').style.display = 'none';
                    }
                }, 500);
            })();
        </script>
    </body>

    </html>
<?php
    exit;
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
