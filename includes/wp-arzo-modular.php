<?php
/**
 * WP Arzo Modular Loader
 * Loads HTML structure and routes to modular feature files
 *
 * @package WP_Arzo
 * @version 5.1
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
    if ($_GET['tab'] === 'files' && (isset($_GET['download']) || (isset($_GET['operation']) && in_array($_GET['operation'], ['view_file', 'edit_file', 'save_file'])))) {
        if (file_exists(WP_ARZO_PLUGIN_DIR . 'features/files.php')) {
            include(WP_ARZO_PLUGIN_DIR . 'features/files.php');
            exit;
        }
    }
    if ($_GET['tab'] === 'database' && isset($_GET['operation']) && $_GET['operation'] === 'get_db_tables_page') {
        if (file_exists(WP_ARZO_PLUGIN_DIR . 'features/database.php')) {
            include(WP_ARZO_PLUGIN_DIR . 'features/database.php');
            exit;
        }
    }
    if ($_GET['tab'] === 'plugins' && isset($_GET['operation']) && $_GET['operation'] === 'get_plugins_page') {
        if (file_exists(WP_ARZO_PLUGIN_DIR . 'features/plugins.php')) {
            include(WP_ARZO_PLUGIN_DIR . 'features/plugins.php');
            exit;
        }
    }
    if ($_GET['tab'] === 'debug' && isset($_GET['operation']) && in_array($_GET['operation'], ['clear_debug_log', 'log_debug_change'])) {
        if (file_exists(WP_ARZO_PLUGIN_DIR . 'features/debug.php')) {
            include(WP_ARZO_PLUGIN_DIR . 'features/debug.php');
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
    // Others will be added one by one
];


if (isset($feature_files[$action]) && file_exists(WP_ARZO_PLUGIN_DIR . 'features/' . $feature_files[$action])) {
    // Load modular feature
    include(WP_ARZO_PLUGIN_DIR . 'features/' . $feature_files[$action]);
} else {
    // Fallback: Load all functions from standalone and call the appropriate one
    // Read standalone file starting from line 1771 (where functions begin)
    $standalone_file = WP_ARZO_PLUGIN_DIR . 'includes/wp-arzo-standalone.php';
    $all_lines = file($standalone_file);

    // Extract just the functions part (from line 1771 onwards, but skip the closing HTML/JS at end)
    // The functions start at line 1771 (index 1770) and we need to find where they end
    $functions_code = '';
    for ($i = 1770; $i < count($all_lines); $i++) {
        $functions_code .= $all_lines[$i];
    }

    // Execute the functions code in current scope
    eval ('?>' . $functions_code);

    // Call the appropriate function based on action
    switch ($action) {
        case 'users':
            if (function_exists('handleUsers'))
                handleUsers();
            break;
        case 'database':
            if (function_exists('handleDatabase'))
                handleDatabase();
            break;
        case 'files':
            if (function_exists('handleFiles'))
                handleFiles();
            break;
        case 'plugins':
            if (function_exists('showPlugins'))
                showPlugins();
            break;
        case 'themes':
            if (function_exists('showThemes'))
                showThemes();
            break;
        case 'debug':
            if (function_exists('handleDebug'))
                handleDebug();
            break;
        case 'maintenance':
            if (function_exists('handleMaintenanceModes'))
                handleMaintenanceModes();
            break;
        case 'extra_options':
            if (function_exists('handleExtraOptions'))
                handleExtraOptions();
            break;
        case 'login':
            if (function_exists('handleQuickLogin'))
                handleQuickLogin();
            break;
        case 'info':
        default:
            if (function_exists('showSiteInfo'))
                showSiteInfo();
    }
}
?>
</div>

<!-- External JavaScript -->
<script>
    // Configuration for external JavaScript file
    var wpArzoConfig = {
        ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
        adminUrl: '<?php echo admin_url(); ?>',
        pluginUrl: '<?php echo WP_ARZO_PLUGIN_URL; ?>'
    };
</script>
<script src="<?php echo WP_ARZO_PLUGIN_URL . 'assets/js/wp-arzo.js?v=' . WP_ARZO_VERSION; ?>"></script>
</body>

</html>