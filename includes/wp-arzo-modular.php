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

// Gate disabled console tools for AJAX/file/DB operations BEFORE any handler runs.
// (The nav + page view are gated further down / in the header.) This is the
// security-critical path: a disabled tool must not execute its operations even if
// called directly via admin-ajax.
if (function_exists('wp_arzo_console_tool_for_request')) {
    $wp_arzo_op  = isset($_GET['operation']) ? $_GET['operation'] : '';
    $wp_arzo_tab = isset($_GET['tab']) ? $_GET['tab'] : '';
    $wp_arzo_is_op_call = ($wp_arzo_op !== '')
        || ($wp_arzo_tab === 'ajax')
        || ($wp_arzo_tab === 'files' && isset($_GET['download']));

    if ($wp_arzo_is_op_call) {
        // The emergency-script ops belong to either Site Modes or Extra Options.
        if (in_array($wp_arzo_op, array('generate_emergency_script', 'delete_emergency_script'), true)) {
            $wp_arzo_op_allowed = wp_arzo_console_tool_enabled('site_modes') || wp_arzo_console_tool_enabled('extra_options');
        } else {
            $wp_arzo_owner = wp_arzo_console_tool_for_request($wp_arzo_tab, $wp_arzo_op);
            $wp_arzo_op_allowed = ($wp_arzo_owner === '') ? true : wp_arzo_console_tool_enabled($wp_arzo_owner);
        }

        if (!$wp_arzo_op_allowed) {
            header('Content-Type: application/json');
            echo json_encode(array('success' => false, 'message' => 'This tool is disabled in WP Arzo settings.'));
            exit;
        }
    }
}

// Check for file download or AJAX file operations which need to run before headers if they happen to be in a feature file
if (isset($_GET['tab'])) {
    // Handle File operations (support both 'files' tab and legacy 'ajax' tab calls).
    // The file manager is powered by elFinder; only the connector + downloads are
    // served as raw responses here.
    if (($_GET['tab'] === 'files' || $_GET['tab'] === 'ajax') && (isset($_GET['download']) || (isset($_GET['operation']) && $_GET['operation'] === 'elfinder_connector'))) {
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

if (function_exists('wp_arzo_console_tool_enabled') && !wp_arzo_console_tool_enabled($action)) {
    echo '<div class="content"><h2>Tool disabled</h2><p>The &ldquo;' . esc_html($action) . '&rdquo; tool is disabled. Enable it from <strong>WP Arzo &rarr; Dashboard</strong> (Advanced Tools group) to use it.</p></div>';
} elseif (isset($feature_files[$action]) && file_exists(WP_ARZO_PLUGIN_DIR . 'features/' . $feature_files[$action])) {
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
        pluginUrl: '<?php echo WP_ARZO_PLUGIN_URL; ?>',
        nonce: '<?php echo esc_js(wp_create_nonce('wp_arzo_ajax')); ?>'
    };
</script>
<?php
// Cache-safe JS loading. Component library first, then the console script.
$wp_arzo_scripts = ['assets/js/wp-arzo-components.js', 'assets/js/wp-arzo.js'];
foreach ($wp_arzo_scripts as $wp_arzo_script) {
    if (!file_exists(WP_ARZO_PLUGIN_DIR . $wp_arzo_script)) {
        continue;
    }
    $wp_arzo_js_url = function_exists('wp_arzo_get_asset_url')
        ? wp_arzo_get_asset_url($wp_arzo_script)
        : WP_ARZO_PLUGIN_URL . $wp_arzo_script;
    echo '<script src="' . esc_url($wp_arzo_js_url) . '"></script>' . "\n";
}
?>
</body>

</html>