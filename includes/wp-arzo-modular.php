<?php
/**
 * WP Arzo Modular Loader
 * Loads HTML structure and routes to modular feature files
 *
 * @package WP_Arzo
 * @version 6.0
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Get current tab
$action = $_GET['tab'] ?? $_GET['action'] ?? 'info';
if ($action === 'wp_arzo_standalone') {
    $action = 'info';
}

// Handle login actions before any output
$login_message = '';
$login_redirect = '';

// Check for file download or AJAX file operations which need to run before headers if they happen to be in a feature file
if (isset($_GET['tab'])) {
    // Handle File operations (support both 'files' tab and legacy 'ajax' tab calls)
    if (($_GET['tab'] === 'files' || $_GET['tab'] === 'ajax') && (isset($_GET['download']) || (isset($_GET['operation']) && in_array($_GET['operation'], ['view_file', 'edit_file', 'save_file', 'elfinder_connector'])))) {
        if (file_exists(WP_ARZO_PLUGIN_DIR . 'features/files.php')) {
            include(WP_ARZO_PLUGIN_DIR . 'features/files.php');
            exit;
        }
    }
    // Handle Database operations
    if (($_GET['tab'] === 'database' || $_GET['tab'] === 'ajax') && isset($_GET['operation']) && $_GET['operation'] === 'get_db_tables_page') {
        if (file_exists(WP_ARZO_PLUGIN_DIR . 'features/database.php')) {
            include(WP_ARZO_PLUGIN_DIR . 'features/database.php');
            exit;
        }
    }
    // Handle Plugin operations
    if (($_GET['tab'] === 'plugins' || $_GET['tab'] === 'ajax') && isset($_GET['operation']) && in_array($_GET['operation'], ['get_plugins_page', 'toggle_plugin'])) {
        if (file_exists(WP_ARZO_PLUGIN_DIR . 'features/plugins.php')) {
            include(WP_ARZO_PLUGIN_DIR . 'features/plugins.php');
            exit;
        }
    }
    // Handle Theme operations
    if (($_GET['tab'] === 'themes' || $_GET['tab'] === 'ajax') && isset($_GET['operation']) && in_array($_GET['operation'], ['get_themes_page', 'activate_theme'])) {
        if (file_exists(WP_ARZO_PLUGIN_DIR . 'features/themes.php')) {
            include(WP_ARZO_PLUGIN_DIR . 'features/themes.php');
            exit;
        }
    }
    // Handle User operations
    if (($_GET['tab'] === 'users' || $_GET['tab'] === 'ajax') && isset($_GET['operation']) && $_GET['operation'] === 'get_users_page') {
        if (file_exists(WP_ARZO_PLUGIN_DIR . 'features/users.php')) {
            include(WP_ARZO_PLUGIN_DIR . 'features/users.php');
            exit;
        }
    }
    // Handle Debug operations
    if (($_GET['tab'] === 'debug' || $_GET['tab'] === 'ajax') && isset($_GET['operation']) && in_array($_GET['operation'], ['clear_debug_log', 'log_debug_change'])) {
        if (file_exists(WP_ARZO_PLUGIN_DIR . 'features/debug.php')) {
            include(WP_ARZO_PLUGIN_DIR . 'features/debug.php');
            exit;
        }
    }
    if (($_GET['tab'] === 'site_modes' || $_GET['tab'] === 'ajax') && isset($_GET['operation']) && in_array($_GET['operation'], ['update_maintenance_option', 'activate_mode', 'deactivate_mode', 'generate_emergency_script', 'delete_emergency_script'])) {
        if (file_exists(WP_ARZO_PLUGIN_DIR . 'features/site-modes.php')) {
            include(WP_ARZO_PLUGIN_DIR . 'features/site-modes.php');
            exit;
        }
    }
    // Handle Extra Options operations
    if (($_GET['tab'] === 'extra_options' || $_GET['tab'] === 'ajax') && isset($_GET['operation']) && in_array($_GET['operation'], ['generate_emergency_script', 'delete_emergency_script'])) {
        if (file_exists(WP_ARZO_PLUGIN_DIR . 'features/site-modes.php')) {
            include(WP_ARZO_PLUGIN_DIR . 'features/site-modes.php');
            exit;
        }
    }
}

// Include the header (everything before switch statement - lines 1-1464)
include(WP_ARZO_PLUGIN_DIR . 'includes/wp-arzo-header.php');

// Route to modular feature files
$feature_files = [
    'info' => 'site-info.php',
    'users' => 'users.php',
    'database' => 'database.php',
    'files' => 'files.php',
    'plugins' => 'plugins.php',
    'themes' => 'themes.php',
    'debug' => 'debug.php',
    'site_modes' => 'site-modes.php',
    'maintenance' => 'site-modes.php', // Backward compatibility
    'extra_options' => 'extra-options.php',
    'login' => 'login.php',
];

if (isset($feature_files[$action]) && file_exists(WP_ARZO_PLUGIN_DIR . 'features/' . $feature_files[$action])) {
    include(WP_ARZO_PLUGIN_DIR . 'features/' . $feature_files[$action]);
} else {
    echo '<div class="content"><h2>Feature Not Found</h2><p>The requested feature "' . esc_html($action) . '" is not available.</p></div>';
}
?>
</div>

<!-- External JavaScript -->
<script>
    var wpArzoConfig = {
        ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
        adminUrl: '<?php echo admin_url(); ?>',
        pluginUrl: '<?php echo WP_ARZO_PLUGIN_URL; ?>'
    };
</script>
<?php
// Use centralized asset helper for cache‑safe JS loading.
if (function_exists('wp_arzo_get_asset_url')) {
    $wp_arzo_js_url = wp_arzo_get_asset_url('assets/js/wp-arzo.js');
} else {
    $wp_arzo_js_url = WP_ARZO_PLUGIN_URL . 'assets/js/wp-arzo.js';
}
?>
<script src="<?php echo esc_url($wp_arzo_js_url); ?>"></script>
</body>

</html>