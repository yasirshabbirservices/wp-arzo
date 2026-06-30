<?php

/**
 * WP Arzo Header & Shared Utilities
 * Loaded by wp-arzo-modular.php before each feature
 *
 * @package WP_Arzo
 * @version 6.0
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Load WordPress admin functions
if (!function_exists('wp_delete_user')) {
    require_once(ABSPATH . 'wp-admin/includes/user.php');
}

// The old "create temporary admin" / "direct admin access" Quick Login helpers were
// replaced by the Temporary Login links system (features/login.php +
// includes/class-wp-arzo-temp-login.php). These vars remain only so the legacy
// message/redirect blocks below stay inert.
$login_message = '';
$login_redirect = '';

// NOTE: The one-time "maintenance_access" emergency login is handled earlier, in
// wp_arzo_handle_standalone() (wp-arzo.php), so it can run before the capability
// gate. Do not re-handle it here.

// Handle file download BEFORE any HTML output
if (isset($_GET['download'])) {
    // Re-assert capability on this state-/data-exposing entry point.
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to download files.');
    }

    // Normalize file path for Windows
    $file_path = wp_unslash($_GET['download']);
    if (DIRECTORY_SEPARATOR === '\\') {
        $file_path = str_replace('/', '\\', $file_path);
    }

    // Confine downloads to the WordPress install root to prevent path traversal
    // (e.g. /etc/passwd, private keys, parent-directory secrets).
    $real_target = realpath($file_path);
    $real_root   = realpath(ABSPATH);
    $is_contained = ($real_target !== false && $real_root !== false &&
        strpos($real_target, $real_root) === 0);

    if ($is_contained && file_exists($real_target) && is_file($real_target)) {
        $file_path = $real_target;
        // Clean any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        $file_name = basename($file_path);
        $file_size = filesize($file_path);
        $mime_type = mime_content_type($file_path) ?: 'application/octet-stream';

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . $file_size);

        // Read file in chunks to handle large files
        $handle = fopen($file_path, 'rb');
        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
        }
        fclose($handle);
        exit;
    }
}

// --- Shared Helper Functions ---

function normalizePath($path)
{
    if (DIRECTORY_SEPARATOR === '\\') {
        $path = str_replace('/', '\\', $path);
    }
    return $path;
}

function isEditableFile($file_path)
{
    $editable_extensions = ['php', 'html', 'htm', 'css', 'js', 'json', 'xml', 'txt', 'md', 'sql', 'htaccess', 'log', 'ini', 'conf', 'yml', 'yaml'];
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    return in_array($extension, $editable_extensions) || basename($file_path) === '.htaccess';
}

function isImageFile($file_path)
{
    $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    return in_array($extension, $image_extensions);
}

function isBinaryFile($file_path)
{
    if (!file_exists($file_path) || !is_file($file_path)) {
        return false;
    }

    $binary_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'pdf', 'zip', 'rar', 'tar', 'gz', 'exe', 'dll', 'so', 'dylib'];
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

    if (in_array($extension, $binary_extensions)) {
        return true;
    }

    // Check file content for binary data
    $handle = fopen($file_path, 'rb');
    $chunk = fread($handle, 1024);
    fclose($handle);

    return strpos($chunk, "\0") !== false;
}

function getSyntaxClass($file_path)
{
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $syntax_map = [
        'php' => 'php',
        'html' => 'html',
        'htm' => 'html',
        'css' => 'css',
        'js' => 'javascript',
        'json' => 'json',
        'xml' => 'xml',
        'sql' => 'sql',
        'md' => 'markdown'
    ];
    return isset($syntax_map[$extension]) ? $syntax_map[$extension] : 'text';
}

function getFileIcon($file_path)
{
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $icon_map = [
        'pdf' => '📄',
        'doc' => '📝',
        'docx' => '📝',
        'xls' => '📊',
        'xlsx' => '📊',
        'ppt' => '📽️',
        'pptx' => '📽️',
        'zip' => '🗜️',
        'rar' => '🗜️',
        'tar' => '🗜️',
        'gz' => '🗜️',
        'mp3' => '🎵',
        'wav' => '🎵',
        'flac' => '🎵',
        'mp4' => '🎬',
        'avi' => '🎬',
        'mov' => '🎬',
        'mkv' => '🎬',
        'jpg' => '🖼️',
        'jpeg' => '🖼️',
        'png' => '🖼️',
        'gif' => '🖼️',
        'bmp' => '🖼️',
        'webp' => '🖼️',
        'exe' => '⚙️',
        'msi' => '⚙️',
        'sql' => '🗃️',
        'php' => '🐘',
        'js' => '📜',
        'css' => '🎨',
        'html' => '🌐'
    ];
    return isset($icon_map[$extension]) ? $icon_map[$extension] : '📄';
}

// --- AJAX Handler for Users Pagination ---
// (users.php routes AJAX calls through tab=ajax, so this handler must stay here)
if (isset($_GET['tab']) && $_GET['tab'] === 'ajax' && isset($_GET['operation'])) {
    header('Content-Type: application/json');

    $operation = $_GET['operation'];
    $response = ['success' => false, 'message' => 'Unknown operation'];

    switch ($operation) {
        case 'get_users_page':
            if (isset($_GET['page']) && isset($_GET['per_page'])) {
                $page = intval($_GET['page']);
                $per_page = intval($_GET['per_page']);

                $users = get_users(['number' => $per_page, 'offset' => ($page - 1) * $per_page]);
                $total_users = count_users();
                $total_users = $total_users['total_users'];

                $users_data = [];
                $current_user_id = get_current_user_id();

                foreach ($users as $user) {
                    $is_current = ($user->ID == $current_user_id);
                    $users_data[] = [
                        'id' => $user->ID,
                        'username' => $user->user_login,
                        'email' => $user->user_email,
                        'roles' => implode(', ', $user->roles),
                        'is_current' => $is_current
                    ];
                }

                $response = [
                    'success' => true,
                    'users' => $users_data,
                    'total' => $total_users,
                    'total_pages' => ceil($total_users / $per_page),
                    'current_page' => $page
                ];
            } else {
                $response = ['success' => false, 'message' => 'Missing page parameters'];
            }
            break;
    }

    echo json_encode($response);
    exit;
}

// --- Get Current Tab ---
$action = $_GET['tab'] ?? $_GET['action'] ?? 'info';
if ($action === 'wp_arzo_standalone') {
    $action = 'info';
}

?>
<!DOCTYPE html>
<html>

<head>
    <title>WP Arzo - Administration Suite</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php
    // Centralized, cache-safe CSS loading. Order matters: design tokens first (the
    // single source of truth), then the component library, then the base console CSS.
    $wp_arzo_styles = ['assets/css/design-tokens.css', 'assets/css/wp-arzo-components.css', 'assets/css/wp-arzo.css'];
    foreach ($wp_arzo_styles as $wp_arzo_style) {
        if (!file_exists(WP_ARZO_PLUGIN_DIR . $wp_arzo_style)) {
            continue;
        }
        $wp_arzo_css_url = function_exists('wp_arzo_get_asset_url')
            ? wp_arzo_get_asset_url($wp_arzo_style)
            : WP_ARZO_PLUGIN_URL . $wp_arzo_style;
        echo '<link rel="stylesheet" href="' . esc_url($wp_arzo_css_url) . '">' . "\n    ";
    }
    ?>
</head>

<body>
    <div class="container">
        <?php // Shared brand bar — identical to the dashboard header (see render_brand_bar()). ?>
        <div class="wpa-brandbar">
            <div class="wpa-brandbar__id">
                <img class="wpa-brandbar__logo" src="<?php echo esc_url(WP_ARZO_PLUGIN_URL . 'assets/wp-arzo-icon.svg'); ?>" alt="WP Arzo">
                <div>
                    <div class="wpa-brandbar__name">WP Arzo</div>
                    <a class="wpa-brandbar__email" href="https://yasirshabbir.com" target="_blank" rel="noopener">by Yasir Shabbir</a>
                </div>
            </div>
            <div class="wpa-brandbar__meta">
                <span class="wpa-brandbar__ver">v<?php echo esc_html(defined('WP_ARZO_VERSION') ? WP_ARZO_VERSION : ''); ?></span>
                <a class="wpa-brandbar__gh" href="https://github.com/yasirshabbirservices/wp-arzo" target="_blank" rel="noopener">
                    <?php echo function_exists('wp_arzo_icon') ? wp_arzo_icon('github', array('class' => 'wpa-icon wpa-icon--sm')) : ''; ?> GitHub
                </a>
            </div>
        </div>

        <div class="nav">
            <?php
            // Console nav: Site Info is always present (the home); every other tool
            // appears only while its dashboard toggle is enabled.
            $wp_arzo_nav_items = array(
                'info'          => 'Site Info',
                'users'         => 'Users',
                'database'      => 'Database',
                'files'         => 'Files',
                'plugins'       => 'Plugins',
                'themes'        => 'Themes',
                'debug'         => 'Debug',
                'site_modes'    => 'Site Modes',
                'extra_options' => 'Extra Options',
                'login'         => 'Temporary Logins',
            );
            foreach ($wp_arzo_nav_items as $wp_arzo_nav_tab => $wp_arzo_nav_label) {
                if ($wp_arzo_nav_tab !== 'info' && function_exists('wp_arzo_console_tool_enabled') && !wp_arzo_console_tool_enabled($wp_arzo_nav_tab)) {
                    continue;
                }
                $wp_arzo_nav_active = ($action === $wp_arzo_nav_tab || ($wp_arzo_nav_tab === 'site_modes' && $action === 'maintenance')) ? ' class="active"' : '';
                $wp_arzo_nav_url = admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=' . $wp_arzo_nav_tab);
                echo '<a href="' . esc_url($wp_arzo_nav_url) . '"' . $wp_arzo_nav_active . '>' . esc_html($wp_arzo_nav_label) . '</a>';
            }
            ?>
        </div>

        <?php
        // Display login messages
        if ($login_message) {
            $message_parts = explode('|', $login_message);
            $message_type = $message_parts[0];
            $message_text = $message_parts[1];
            echo '<div class="' . $message_type . '">' . $message_text . '</div>';
        }

        // Add redirect script if needed
        if ($login_redirect) {
            echo '<script>setTimeout(function() { window.open("' . $login_redirect . '", "_blank"); }, 1000);</script>';
        }
        ?>