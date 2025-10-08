<?php
/**
 * Plugin Name: WP Arzo - Maintenance & Administration Suite
 * Plugin URI: https://github.com/yasirshabbirservices/maintenance-tool
 * Description: Ultimate WordPress Maintenance & Administration Suite
 * Version: 5.1
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

// Define plugin constants
define('WP_ARZO_VERSION', '5.1');
define('WP_ARZO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_ARZO_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Add admin menu
 */
function wp_arzo_add_admin_menu() {
    add_menu_page(
        'WP Arzo',                    // Page title
        'WP Arzo',                    // Menu title
        'manage_options',             // Capability required
        'wp-arzo-tool',               // Menu slug
        'wp_arzo_redirect_page',      // Callback function
        'dashicons-admin-tools',      // Icon
        100                           // Position
    );
}
add_action('admin_menu', 'wp_arzo_add_admin_menu');

/**
 * Redirect callback - opens tool in new tab
 */
function wp_arzo_redirect_page() {
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
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
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
            <a href="<?php echo esc_url($tool_url); ?>" target="_blank" class="btn" id="openBtn" style="display:none;">Open WP Arzo</a>
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
function wp_arzo_handle_standalone() {
    // Check if user is logged in and has admin capabilities
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }

    // Include the modular tool file
    include(WP_ARZO_PLUGIN_DIR . 'includes/wp-arzo-modular.php');
    exit;
}
add_action('wp_ajax_wp_arzo_standalone', 'wp_arzo_handle_standalone');

/**
 * Handle direct access to tool (when opened in new tab)
 * This allows the tool to work without wp_ajax prefix
 */
function wp_arzo_handle_direct_access() {
    // Check if this is a request for our tool
    if (isset($_GET['action']) &&
        (strpos($_SERVER['REQUEST_URI'], 'admin-ajax.php') !== false ||
         strpos($_SERVER['REQUEST_URI'], 'wp-arzo') !== false)) {

        // Let AJAX handler take care of it
        return;
    }
}
add_action('init', 'wp_arzo_handle_direct_access');

/**
 * Activation hook
 */
function wp_arzo_activate() {
    // Nothing special needed on activation
}
register_activation_hook(__FILE__, 'wp_arzo_activate');

/**
 * Deactivation hook
 */
function wp_arzo_deactivate() {
    // Nothing special needed on deactivation
}
register_deactivation_hook(__FILE__, 'wp_arzo_deactivate');
