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

// Handle login actions before any output
$login_message = '';
$login_redirect = '';

if (isset($_POST['login_as_user'])) {
    $user_id = intval($_POST['user_id']);
    $user = get_user_by('id', $user_id);

    if ($user) {
        // Clean output buffer before setting headers
        ob_clean();

        // Clear any existing authentication
        wp_clear_auth_cookie();

        // Set new authentication
        wp_set_current_user($user_id, $user->user_login);
        wp_set_auth_cookie($user_id, true);
        do_action('wp_login', $user->user_login, $user);

        $login_redirect = admin_url();
        $login_message = 'success|Login successful! Opening WordPress admin in new tab...';
    } else {
        $login_message = 'error|User not found!';
    }
}

if (isset($_POST['create_temp_admin'])) {
    $temp_username = 'temp_admin_' . time();
    $temp_password = wp_generate_password(16, true, true);
    $temp_email = 'temp@' . parse_url(home_url(), PHP_URL_HOST);

    $user_id = wp_create_user($temp_username, $temp_password, $temp_email);

    if (!is_wp_error($user_id)) {
        $user = new WP_User($user_id);
        $user->set_role('administrator');

        // Clean output buffer before setting headers
        ob_clean();

        // Clear any existing authentication
        wp_clear_auth_cookie();

        // Set new authentication
        wp_set_current_user($user_id, $temp_username);
        wp_set_auth_cookie($user_id, true);
        do_action('wp_login', $temp_username, $user);

        $login_redirect = admin_url();
        $login_message = 'success|<strong>Temporary Admin Created!</strong><br>Username: ' . $temp_username . '<br>Password: ' . $temp_password . '<br><em>Login successful! Opening WordPress admin in new tab...</em>';
    } else {
        $login_message = 'error|Error creating temporary admin: ' . $user_id->get_error_message();
    }
}

// Handle direct admin access
if (isset($_GET['maintenance_access']) && isset($_GET['maintenance_access'])) {
    $nonce = $_GET['maintenance_access'];
    if (get_transient('maintenance_access_' . $nonce)) {
        // Delete the transient so it can't be reused
        delete_transient('maintenance_access_' . $nonce);

        // Get the first admin user
        $admin_users = get_users(['role' => 'administrator', 'number' => 1]);
        if (!empty($admin_users)) {
            $admin_user = $admin_users[0];

            // Clean output buffer before setting headers
            ob_clean();

            // Clear any existing authentication
            wp_clear_auth_cookie();

            // Set new authentication
            wp_set_current_user($admin_user->ID, $admin_user->user_login);
            wp_set_auth_cookie($admin_user->ID, true);
            do_action('wp_login', $admin_user->user_login, $admin_user);

            // Redirect to admin
            wp_redirect(admin_url());
            exit;
        }
    }
}

// Handle file download BEFORE any HTML output
if (isset($_GET['download'])) {
    // Normalize file path for Windows
    $file_path = $_GET['download'];
    if (DIRECTORY_SEPARATOR === '\\') {
        $file_path = str_replace('/', '\\', $file_path);
    }
    if (file_exists($file_path) && is_file($file_path)) {
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo WP_ARZO_PLUGIN_URL . 'assets/css/wp-arzo.css?v=' . WP_ARZO_VERSION; ?>">
</head>

<body>
    <div class="container">
        <div class="developer-info">
            <div class="developer-logo">
                <img src="<?php echo WP_ARZO_PLUGIN_URL . 'assets/yasir-shabbir-white-logo.png'; ?>"
                    alt="Yasir Shabbir">
                <div>
                    <div>Yasir Shabbir</div>
                    <a href="mailto:contact@yasirshabbir.com">contact@yasirshabbir.com</a>
                </div>
            </div>
            <div>
                v6.0
                <a href="https://github.com/yasirshabbirservices/wp-arzo" target="_blank"
                    style="color: #fff; text-decoration: none; font-size: 14px; margin-left: 10px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"
                        style="vertical-align: middle; margin-right: 5px;">
                        <path
                            d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z" />
                    </svg>
                    GitHub
                </a>
            </div>
        </div>

        <h1>WP Arzo - Administration Suite</h1>

        <div class="nav">
            <a href="<?php echo admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=info'); ?>"
                <?php echo ($action === 'info') ? 'class="active"' : ''; ?>>Site Info</a>
            <a href="<?php echo admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=users'); ?>"
                <?php echo ($action === 'users') ? 'class="active"' : ''; ?>>Users</a>
            <a href="<?php echo admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=database'); ?>"
                <?php echo ($action === 'database') ? 'class="active"' : ''; ?>>Database</a>
            <a href="<?php echo admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=files'); ?>"
                <?php echo ($action === 'files') ? 'class="active"' : ''; ?>>Files</a>
            <a href="<?php echo admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=plugins'); ?>"
                <?php echo ($action === 'plugins') ? 'class="active"' : ''; ?>>Plugins</a>
            <a href="<?php echo admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=themes'); ?>"
                <?php echo ($action === 'themes') ? 'class="active"' : ''; ?>>Themes</a>
            <a href="<?php echo admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=debug'); ?>"
                <?php echo ($action === 'debug') ? 'class="active"' : ''; ?>>Debug</a>
            <a href="<?php echo admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=site_modes'); ?>"
                <?php echo ($action === 'site_modes' || $action === 'maintenance') ? 'class="active"' : ''; ?>>Site
                Modes</a>
            <a href="<?php echo admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=extra_options'); ?>"
                <?php echo ($action === 'extra_options') ? 'class="active"' : ''; ?>>Extra Options</a>
            <a href="<?php echo admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=login'); ?>"
                <?php echo ($action === 'login') ? 'class="active"' : ''; ?>>Quick Login</a>
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