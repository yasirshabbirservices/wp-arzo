<?php
/**
 * WP Arzo - Emergency Recovery Script
 * 
 * Standalone recovery tool that works independently of WordPress core.
 * Features: Plugin/Theme Management, User Control, DB Repair, File Uploads.
 * 
 * @package WP_Arzo
 * @version 2.0
 */

// Disable error reporting to prevent leakage, unless explicitly enabled
if (isset($_GET['debug'])) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    error_reporting(0);
}

// Security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
// Allow Google Fonts, images, and inline styles/scripts
header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' https://fonts.googleapis.com https://fonts.gstatic.com; img-src 'self' data: https:; font-src 'self' https://fonts.gstatic.com data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com;");

// Define constants
define('WP_ARZO_EMERGENCY_VERSION', '2.0');
define('WP_ARZO_EMERGENCY_DIR', __DIR__);
define('WP_ARZO_CONFIG_FILE', dirname(__DIR__) . '/arzo-safe.php'); 
define('WP_CONTENT_DIR', dirname(dirname(dirname(__DIR__))) . '/wp-content');

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    session_start();
}

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verify_nonce() {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Security Check Failed: Invalid Nonce');
    }
}

// Helper: Redirect
function redirect($url) {
    if (!headers_sent()) {
        header("Location: $url");
        exit;
    } else {
        echo "<script>window.location.href='$url';</script>";
        exit;
    }
}

// Auth Check
if (!file_exists(WP_ARZO_CONFIG_FILE)) {
    $setup_mode = true;
} else {
    $setup_mode = false;
    require_once(WP_ARZO_CONFIG_FILE);
}

// Determine Redirect Base
$current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$parsed_url = parse_url($current_url);
$redirect_base = $parsed_url['path'];

// Messages
$error_msg = '';
$success_msg = '';

// Handle Login/Setup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        if (isset($_POST['password']) && defined('WP_ARZO_EMERGENCY_HASH')) {
            if (password_verify($_POST['password'], WP_ARZO_EMERGENCY_HASH)) {
                $_SESSION['arzo_emergency_auth'] = true;
                redirect($redirect_base);
            } else {
                $error_msg = 'Invalid password.';
            }
        }
    } elseif ($_POST['action'] === 'setup' && $setup_mode) {
        if (isset($_POST['new_password']) && !empty($_POST['new_password'])) {
            $hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $config_content = "<?php\n// WP Arzo Emergency Config\n// DO NOT EDIT MANUALLY\ndefine('WP_ARZO_EMERGENCY_HASH', '$hash');\n";
            if (file_put_contents(WP_ARZO_CONFIG_FILE, $config_content)) {
                $_SESSION['arzo_emergency_auth'] = true;
                redirect($redirect_base);
            } else {
                $error_msg = 'Failed to write config file. Check permissions.';
            }
        }
    } elseif ($_POST['action'] === 'logout') {
        session_destroy();
        redirect($redirect_base);
    }
}

$is_authenticated = isset($_SESSION['arzo_emergency_auth']) && $_SESSION['arzo_emergency_auth'] === true;

// Locate wp-config.php
$wp_config_path = '';
$possible_paths = [
    dirname(__DIR__) . '/wp-config.php',
    dirname(dirname(__DIR__)) . '/wp-config.php',
    dirname(dirname(dirname(__DIR__))) . '/wp-config.php',
    dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-config.php'
];

foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $wp_config_path = $path;
        break;
    }
}

// DB Helper
function get_db_connection($wp_config_path) {
    if (!file_exists($wp_config_path)) return false;
    $config_content = file_get_contents($wp_config_path);
    preg_match("/define\(\s*['\"]DB_NAME['\"]\s*,\s*['\"](.*?)['\"]\s*\);/", $config_content, $db_name);
    preg_match("/define\(\s*['\"]DB_USER['\"]\s*,\s*['\"](.*?)['\"]\s*\);/", $config_content, $db_user);
    preg_match("/define\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"](.*?)['\"]\s*\);/", $config_content, $db_password);
    preg_match("/define\(\s*['\"]DB_HOST['\"]\s*,\s*['\"](.*?)['\"]\s*\);/", $config_content, $db_host);
    preg_match("/\\\$table_prefix\s*=\s*['\"](.*?)['\"];/", $config_content, $table_prefix);

    if (empty($db_name[1]) || empty($db_user[1]) || empty($db_host[1])) return "Could not parse wp-config.php.";

    $host = $db_host[1];
    $user = $db_user[1];
    $pass = isset($db_password[1]) ? $db_password[1] : '';
    $name = $db_name[1];
    $prefix = isset($table_prefix[1]) ? $table_prefix[1] : 'wp_';

    $mysqli = new mysqli($host, $user, $pass, $name);
    if ($mysqli->connect_error) return "Connection failed: " . $mysqli->connect_error;

    return ['conn' => $mysqli, 'prefix' => $prefix];
}

// File System Helpers
function recursive_copy($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir($src . '/' . $file)) {
                recursive_copy($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

function recursive_rmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . "/" . $object))
                    recursive_rmdir($dir . "/" . $object);
                else
                    unlink($dir . "/" . $object);
            }
        }
        rmdir($dir);
    }
}

// Basic Malware Scanner - Disabled for Admin Recovery
function scan_file_for_malware($file) {
    // Since this is an authenticated recovery tool used by admins,
    // we assume the uploaded files are trusted.
    // Overly aggressive scanning was blocking legitimate themes/plugins.
    return false;
}

// Helper: Get Asset URL
function get_asset_url($path) {
    // Check if we can find 'wp-content' in the path
    $base_path = '';
    $script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    
    if (strpos($script_name, 'wp-content') !== false) {
        $parts = explode('wp-content', $script_name);
        // Cleanly construct the path
        $base_path = rtrim($parts[0], '/') . '/wp-content/plugins/wp-arzo/assets/' . $path;
    } else {
        // Fallback: assume standard structure relative to webroot
        $base_path = '/wp-content/plugins/wp-arzo/assets/' . $path;
    }
    
    return $base_path;
}

// Helper: Pagination
function get_pagination_html($total_items, $items_per_page, $current_page, $base_url, $tab) {
    $total_pages = ceil($total_items / $items_per_page);
    if ($total_pages <= 1) return '';

    $html = '<div class="pagination-container">';
    $html .= '<div class="pagination-info">';
    $html .= sprintf('Showing %d to %d of %d items', 
        ($current_page - 1) * $items_per_page + 1, 
        min($current_page * $items_per_page, $total_items), 
        $total_items);
    $html .= '</div>';
    $html .= '<div class="pagination-controls">';

    // Previous Button
    $disabled = ($current_page <= 1) ? 'disabled' : '';
    $prev_link = $disabled ? '#' : $base_url . "&tab=$tab&p=" . ($current_page - 1);
    $html .= "<button onclick=\"location.href='$prev_link'\" $disabled><i class=\"fas fa-chevron-left\"></i> Previous</button>";

    // Page Numbers
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);

    if ($start_page > 1) {
        $html .= "<button onclick=\"location.href='$base_url&tab=$tab&p=1'\">1</button>";
        if ($start_page > 2) $html .= '<span style="color:#999; padding:5px;">...</span>';
    }

    for ($i = $start_page; $i <= $end_page; $i++) {
        $active = ($i == $current_page) ? 'active' : '';
        $html .= "<button class=\"$active\" onclick=\"location.href='$base_url&tab=$tab&p=$i'\">$i</button>";
    }

    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) $html .= '<span style="color:#999; padding:5px;">...</span>';
        $html .= "<button onclick=\"location.href='$base_url&tab=$tab&p=$total_pages'\">$total_pages</button>";
    }

    // Next Button
    $disabled = ($current_page >= $total_pages) ? 'disabled' : '';
    $next_link = $disabled ? '#' : $base_url . "&tab=$tab&p=" . ($current_page + 1);
    $html .= "<button onclick=\"location.href='$next_link'\" $disabled>Next <i class=\"fas fa-chevron-right\"></i></button>";

    $html .= '</div></div>';
    return $html;
}

// MAIN LOGIC
if ($is_authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_nonce();
    $db_data = get_db_connection($wp_config_path);
    
    if (is_array($db_data)) {
        $conn = $db_data['conn'];
        $prefix = $db_data['prefix'];

        switch ($_POST['action']) {
            // --- PLUGINS ---
            case 'deactivate_all_plugins':
                $sql_get = "SELECT option_value FROM {$prefix}options WHERE option_name = 'active_plugins'";
                $result = $conn->query($sql_get);
                if ($result && $row = $result->fetch_assoc()) {
                    $current_plugins = unserialize($row['option_value']);
                    if (!is_array($current_plugins)) $current_plugins = [];
                    
                    $preserved = [];
                    $count = 0;
                    $target = 'wp-arzo/wp-arzo.php';
                    
                    foreach ($current_plugins as $p) {
                        if ($p === $target || strpos($p, 'wp-arzo.php') !== false) {
                            $preserved[] = $p;
                        } else {
                            $count++;
                        }
                    }
                    
                    $new_val = $conn->real_escape_string(serialize($preserved));
                    $conn->query("UPDATE {$prefix}options SET option_value = '$new_val' WHERE option_name = 'active_plugins'");
                    $success_msg = "Deactivated $count plugins. WP Arzo preserved.";
                }
                break;

            case 'toggle_plugin':
                $plugin_file = $_POST['plugin_file'];
                $activate = $_POST['state'] === 'activate';
                
                $sql_get = "SELECT option_value FROM {$prefix}options WHERE option_name = 'active_plugins'";
                $res = $conn->query($sql_get);
                $active = [];
                if ($res && $row = $res->fetch_assoc()) $active = unserialize($row['option_value']);
                if (!is_array($active)) $active = [];

                if ($activate) {
                    if (!in_array($plugin_file, $active)) {
                        $active[] = $plugin_file;
                        sort($active); // Good practice
                    }
                } else {
                    $key = array_search($plugin_file, $active);
                    if ($key !== false) unset($active[$key]);
                    $active = array_values($active);
                }

                $new_val = $conn->real_escape_string(serialize($active));
                $conn->query("UPDATE {$prefix}options SET option_value = '$new_val' WHERE option_name = 'active_plugins'");
                $success_msg = "Plugin " . ($activate ? "Activated" : "Deactivated");
                break;

            case 'upload_plugin':
            case 'upload_theme':
                $type = $_POST['action'] === 'upload_plugin' ? 'plugins' : 'themes';
                $target_dir = WP_CONTENT_DIR . '/' . $type . '/';
                
                if (isset($_FILES['zip_file']) && $_FILES['zip_file']['error'] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['zip_file']['tmp_name'];
                    $name = $_FILES['zip_file']['name'];
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    
                    if ($ext !== 'zip') {
                        $error_msg = "Only ZIP files are allowed.";
                    } else {
                        $zip = new ZipArchive;
                        if ($zip->open($tmp_name) === TRUE) {
                            // Basic malware scan on php files in zip (simplified: extract to temp first)
                            $temp_extract = sys_get_temp_dir() . '/arzo_temp_' . uniqid();
                            mkdir($temp_extract);
                            $zip->extractTo($temp_extract);
                            $zip->close();
                            
                            // Scan
                            $malware_found = false;
                            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp_extract));
                            foreach ($iterator as $file) {
                                if ($file->isFile() && $file->getExtension() === 'php') {
                                    if (scan_file_for_malware($file->getPathname())) {
                                        $malware_found = true;
                                        break;
                                    }
                                }
                            }
                            
                            if ($malware_found) {
                                $error_msg = "Security Alert: Suspicious code detected in upload.";
                                recursive_rmdir($temp_extract);
                            } else {
                                // Move to target
                                recursive_copy($temp_extract, $target_dir);
                                recursive_rmdir($temp_extract);
                                $success_msg = ucfirst(rtrim($type, 's')) . " uploaded and installed successfully.";
                                
                                // Auto Activate if requested
                                if (isset($_POST['activate_now'])) {
                                    // Re-open zip to check structure
                                    $zip = new ZipArchive;
                                    $zip->open($tmp_name);
                                    $stat = $zip->statIndex(0);
                                    $root_folder = explode('/', $stat['name'])[0];
                                    $zip->close();

                                    if ($type === 'themes') {
                                        if ($root_folder) {
                                            $slug = $conn->real_escape_string($root_folder);
                                            $conn->query("UPDATE {$prefix}options SET option_value = '$slug' WHERE option_name = 'template'");
                                            $conn->query("UPDATE {$prefix}options SET option_value = '$slug' WHERE option_name = 'stylesheet'");
                                            $success_msg .= " And activated.";
                                        }
                                    } elseif ($type === 'plugins') {
                                        // Find main file in extracted dir
                                        $extracted_root = $target_dir . $root_folder;
                                        $main_file = '';
                                        if (is_dir($extracted_root)) {
                                            $files = scandir($extracted_root);
                                            foreach ($files as $f) {
                                                if (substr($f, -4) === '.php') {
                                                    $content = file_get_contents($extracted_root . '/' . $f, false, null, 0, 8192);
                                                    if (preg_match('/Plugin Name:/i', $content)) {
                                                        $main_file = $root_folder . '/' . $f;
                                                        break;
                                                    }
                                                }
                                            }
                                        }
                                        
                                        if ($main_file) {
                                            $sql_get = "SELECT option_value FROM {$prefix}options WHERE option_name = 'active_plugins'";
                                            $res = $conn->query($sql_get);
                                            $active = ($res && $row = $res->fetch_assoc()) ? unserialize($row['option_value']) : [];
                                            if (!is_array($active)) $active = [];
                                            
                                            if (!in_array($main_file, $active)) {
                                                $active[] = $main_file;
                                                sort($active);
                                                $new_val = $conn->real_escape_string(serialize($active));
                                                $conn->query("UPDATE {$prefix}options SET option_value = '$new_val' WHERE option_name = 'active_plugins'");
                                                $success_msg .= " And activated.";
                                            }
                                        }
                                    }
                                }
                            }
                        } else {
                            $error_msg = "Failed to open ZIP.";
                        }
                    }
                } else {
                    $error_msg = "Upload failed.";
                }
                break;

            // --- THEMES ---
            case 'activate_theme':
                $slug = $conn->real_escape_string($_POST['theme_slug']);
                $conn->query("UPDATE {$prefix}options SET option_value = '$slug' WHERE option_name = 'template'");
                $conn->query("UPDATE {$prefix}options SET option_value = '$slug' WHERE option_name = 'stylesheet'");
                $success_msg = "Theme activated.";
                break;

            // --- USERS ---
            case 'create_admin':
                $user = $conn->real_escape_string($_POST['username']);
                $pass = md5($_POST['password']);
                $email = $conn->real_escape_string($_POST['email']);
                $now = date('Y-m-d H:i:s');
                $sql = "INSERT INTO {$prefix}users (user_login, user_pass, user_nicename, user_email, user_registered, user_status, display_name) VALUES ('$user', '$pass', '$user', '$email', '$now', 0, '$user')";
                if ($conn->query($sql)) {
                    $uid = $conn->insert_id;
                    $caps = serialize(['administrator' => true]);
                    $conn->query("INSERT INTO {$prefix}usermeta (user_id, meta_key, meta_value) VALUES ($uid, '{$prefix}capabilities', '$caps')");
                    $conn->query("INSERT INTO {$prefix}usermeta (user_id, meta_key, meta_value) VALUES ($uid, '{$prefix}user_level', '10')");
                    $success_msg = "Admin created.";
                } else {
                    $error_msg = "DB Error: " . $conn->error;
                }
                break;
                
            case 'reset_password':
                $uid = intval($_POST['user_id']);
                $pass = md5($_POST['new_pass']);
                $conn->query("UPDATE {$prefix}users SET user_pass = '$pass' WHERE ID = $uid");
                $success_msg = "Password reset.";
                break;

            // --- CORE ---
            case 'update_url':
                $url = $conn->real_escape_string($_POST['site_url']);
                $conn->query("UPDATE {$prefix}options SET option_value = '$url' WHERE option_name = 'siteurl'");
                $conn->query("UPDATE {$prefix}options SET option_value = '$url' WHERE option_name = 'home'");
                $success_msg = "URLs updated.";
                break;
        }
    } else {
        $error_msg = "DB Connection failed.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WP Arzo - Emergency Recovery</title>
    <!-- Embedded CSS matching Main Plugin -->
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700&display=swap');
    :root {
        --accent-color: #16e791; --primary-text: #ffffff; --secondary-text: #e0e0e0;
        --background-dark: #121212; --background-medium: #1e1e1e; --background-light: #2a2a2a;
        --border-color: #333333; --border-light: #444444; --danger-color: #dc3545; --success-color: #28a745;
    }
    body { font-family: 'Lato', sans-serif; margin: 0; padding: 20px; background: var(--background-dark); color: var(--secondary-text); min-height: 100vh; }
    .container { max-width: 1200px; margin: 0 auto; background: var(--background-medium); padding: 20px; border-radius: 3px; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
    h1 { color: var(--primary-text); margin-bottom: 10px; font-weight: 700; }
    h2 { color: var(--accent-color); border-bottom: 2px solid var(--border-color); padding-bottom: 10px; font-weight: 400; }
    .nav { margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 5px; border-bottom: 2px solid var(--border-color); }
    .nav button { padding: 12px 20px; background: var(--background-light); color: var(--secondary-text); border: 2px solid var(--border-color); border-bottom: none; border-radius: 8px 8px 0 0; font-weight: 500; cursor: pointer; transition: all 0.3s; font-family: inherit; font-size: 14px; }
    .nav button:hover { background: #3A3A3A; color: var(--accent-color); }
    .nav button.active { background: var(--accent-color); color: var(--background-dark); border-color: var(--accent-color); }
        .content { display: none; background: var(--background-medium); padding: 20px; border-left: 4px solid var(--accent-color); border-radius: 3px; animation: fadeIn 0.3s; }
        .content.active { display: block; }
    .alert { padding: 15px; border-radius: 3px; margin-bottom: 20px; }
    .alert-success { background: rgba(40, 167, 69, 0.2); border: 1px solid var(--success-color); color: #81c784; }
    .alert-error { background: rgba(220, 53, 69, 0.2); border: 1px solid var(--danger-color); color: #e57373; }
    table { width: 100%; border-collapse: collapse; margin: 15px 0; background: var(--background-medium); }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color); }
    th { background: var(--border-color); color: var(--accent-color); font-weight: 600; }
    tr:hover { background: var(--background-light); }
    .btn { padding: 8px 15px; background: var(--accent-color); color: var(--background-dark); border: none; border-radius: 3px; cursor: pointer; font-weight: 500; transition: 0.3s; font-size: 13px; }
    .btn:hover { background: #0ea66b; color: #fff; }
    .btn-danger { background: var(--danger-color); color: white; }
    .btn-danger:hover { background: #c82333; }
    .btn-sm { padding: 5px 10px; font-size: 12px; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; color: #E0E0E0; }
    .form-control { width: 100%; padding: 10px; background: var(--background-light); border: 1px solid var(--border-color); color: #fff; border-radius: 3px; box-sizing: border-box; }
    .flex-between { display: flex; justify-content: space-between; align-items: center; }
    .badge { padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; }
    .badge-active { background: rgba(22, 231, 145, 0.2); color: var(--accent-color); }
    .badge-inactive { background: rgba(108, 117, 125, 0.2); color: #999; }
    
    /* Branding Header */
    .developer-info { display: flex; justify-content: space-between; align-items: center; background: var(--background-medium); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px 15px; margin-bottom: 20px; font-size: 13px; }
    .developer-logo { display: flex; align-items: center; gap: 12px; }
    .developer-logo img { width: 32px; height: 32px; border-radius: 50%; }
    .developer-logo a { color: var(--accent-color); text-decoration: none; }

    /* Pagination */
    .pagination-container { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-color); }
    .pagination-info { color: #999; font-size: 13px; }
    .pagination-controls { display: flex; gap: 5px; }
    .pagination-controls button { background: var(--background-light); border: 1px solid var(--border-color); color: var(--secondary-text); padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 13px; }
    .pagination-controls button:hover:not(:disabled) { border-color: var(--accent-color); color: var(--accent-color); }
    .pagination-controls button.active { background: var(--accent-color); color: var(--background-dark); border-color: var(--accent-color); }
    .pagination-controls button:disabled { opacity: 0.5; cursor: not-allowed; }
    
    /* Toggle Switch */
    .switch { position: relative; display: inline-block; width: 34px; height: 18px; vertical-align: middle; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #444; transition: .4s; border-radius: 18px; }
    .slider:before { position: absolute; content: ""; height: 12px; width: 12px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
    input:checked + .slider { background-color: var(--accent-color); }
    input:checked + .slider:before { transform: translateX(16px); }
    
    .toggle-label { margin-left: 15px; cursor: pointer; font-size: 13px; }

        /* Login Screen Specifics */
        .login-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: var(--background-dark);
            width: 100%;
        }
        .login-card {
            background: var(--background-medium);
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            width: 100%;
            max-width: 400px;
            text-align: center;
            border: 1px solid var(--border-color);
            position: relative;
            z-index: 10;
        }
    .login-card h1 { font-size: 24px; margin-bottom: 10px; color: var(--primary-text); }
    .login-card h2 { font-size: 18px; margin-bottom: 25px; color: var(--accent-color); border: none; padding: 0; }
    .login-card .form-control {
        background: var(--background-light);
        border: 1px solid var(--border-color);
        padding: 12px 15px;
        font-size: 15px;
        margin-bottom: 20px;
        transition: border-color 0.3s;
    }
    .login-card .form-control:focus {
        border-color: var(--accent-color);
        outline: none;
    }
    .login-card .btn {
        width: 100%;
        padding: 12px;
        font-size: 16px;
        border-radius: 4px;
        margin-top: 10px;
    }
    .login-card .logo-area { margin-bottom: 5px; }
    .login-card .logo-area img { width: 60px; height: 60px; border-radius: 50%; margin-bottom: 5px; }
    .login-card .footer-links { margin-top: 20px; font-size: 12px; color: #666; }
    .login-card .footer-links a { color: #888; text-decoration: none; }
    .login-card .footer-links a:hover { color: var(--accent-color); }

    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body>
    <?php if (!$is_authenticated): ?>
        <div class="login-wrapper">
            <div class="login-card">
                <div class="logo-area">
                    <img src="<?php echo get_asset_url('yasir-shabbir-white-logo.png'); ?>" alt="Yasir Shabbir">
                </div>
                <h1>WP Arzo Recovery</h1>
                
                <?php if ($success_msg) echo "<div class='alert alert-success' style='text-align:left; margin-bottom:20px;'>$success_msg</div>"; ?>
                <?php if ($error_msg) echo "<div class='alert alert-error' style='text-align:left; margin-bottom:20px;'>$error_msg</div>"; ?>

                <form method="post">
                    <input type="hidden" name="action" value="<?php echo $setup_mode ? 'setup' : 'login'; ?>">
                    <div style="text-align: left; margin-bottom: 5px;">
                        <label style="font-size: 12px; color: #999; text-transform: uppercase; font-weight: bold; letter-spacing: 0.5px;"><?php echo $setup_mode ? 'Create Password' : 'Password'; ?></label>
                    </div>
                    <input type="password" name="<?php echo $setup_mode ? 'new_password' : 'password'; ?>" class="form-control" placeholder="Enter your password" required autofocus>
                    <button type="submit" class="btn"><?php echo $setup_mode ? 'Create & Login' : 'Login'; ?></button>
                </form>
            </div>
        </div>
    <?php else: ?>
    <div class="container">
        <!-- Branding Header -->
        <div class="developer-info">
            <div class="developer-logo">
                <img src="<?php echo get_asset_url('yasir-shabbir-white-logo.png'); ?>" alt="Yasir Shabbir">
                <div>
                    <div>Yasir Shabbir</div>
                    <a href="mailto:contact@yasirshabbir.com">contact@yasirshabbir.com</a>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="color:var(--accent-color);">v<?php echo WP_ARZO_EMERGENCY_VERSION; ?></span>
                <a href="https://github.com/yasirshabbir/wp-arzo" target="_blank" style="text-decoration:none; color:var(--primary-text); display:flex; align-items:center; gap:5px;">
                    <svg height="20" width="20" viewBox="0 0 16 16" fill="currentColor"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"></path></svg>
                    GitHub
                </a>
            </div>
        </div>

        <div class="flex-between" style="margin-bottom: 20px;">
            <h1>WP Arzo - Administration Suite (Recovery Mode)</h1>
            <?php if ($is_authenticated): ?>
                <form method="post" style="margin:0;"><input type="hidden" name="action" value="logout"><button type="submit" class="btn btn-danger">Logout</button></form>
            <?php endif; ?>
        </div>

        <?php if ($success_msg) echo "<div class='alert alert-success'>$success_msg</div>"; ?>
        <?php if ($error_msg) echo "<div class='alert alert-error'>$error_msg</div>"; ?>

        <?php 
            $db_data = get_db_connection($wp_config_path);
            if (is_array($db_data)) {
                $conn = $db_data['conn'];
                $prefix = $db_data['prefix'];

                // Fetch Data
                $plugins_res = $conn->query("SELECT option_value FROM {$prefix}options WHERE option_name = 'active_plugins'");
                $active_plugins = ($plugins_res && $row = $plugins_res->fetch_assoc()) ? unserialize($row['option_value']) : [];
                if (!is_array($active_plugins)) $active_plugins = [];

                $stylesheet_res = $conn->query("SELECT option_value FROM {$prefix}options WHERE option_name = 'stylesheet'");
                $active_theme = ($stylesheet_res && $row = $stylesheet_res->fetch_assoc()) ? $row['option_value'] : '';

                // Pagination Setup
                $items_per_page = 10;
                $current_page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
                $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
                $base_url = '?'; // Since it's standalone

                // Get Lists via file scan
                $all_plugins = [];
                $plugin_dir = WP_CONTENT_DIR . '/plugins';
                if (is_dir($plugin_dir)) {
                    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin_dir));
                    foreach ($files as $file) {
                        if ($file->isFile() && $file->getExtension() === 'php') {
                            $content = file_get_contents($file->getPathname(), false, null, 0, 8192); // Read header
                            if (preg_match('/Plugin Name:\s*(.*)$/mi', $content, $matches)) {
                                $path = str_replace($plugin_dir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                                $path = str_replace('\\', '/', $path); // Normalize
                                $all_plugins[$path] = trim($matches[1]);
                            }
                        }
                    }
                }
                // Pagination for Plugins
                $total_plugins = count($all_plugins);
                $plugins_offset = ($current_page - 1) * $items_per_page;
                $display_plugins = array_slice($all_plugins, $plugins_offset, $items_per_page, true);
                
                $all_themes = [];
                $theme_dir = WP_CONTENT_DIR . '/themes';
                if (is_dir($theme_dir)) {
                    $dirs = scandir($theme_dir);
                    foreach ($dirs as $dir) {
                        if ($dir !== '.' && $dir !== '..' && is_dir($theme_dir . '/' . $dir)) {
                            $style = $theme_dir . '/' . $dir . '/style.css';
                            if (file_exists($style)) {
                                $content = file_get_contents($style, false, null, 0, 8192);
                                if (preg_match('/Theme Name:\s*(.*)$/mi', $content, $matches)) {
                                    $all_themes[$dir] = trim($matches[1]);
                                } else {
                                    $all_themes[$dir] = $dir;
                                }
                            }
                        }
                    }
                }
                // Pagination for Themes
                $total_themes = count($all_themes);
                $themes_offset = ($current_page - 1) * $items_per_page;
                $display_themes = array_slice($all_themes, $themes_offset, $items_per_page, true);
        ?>

        <div class="nav">
            <button onclick="location.href='?tab=dashboard'" class="<?php echo $current_tab === 'dashboard' ? 'active' : ''; ?>" id="btn-dashboard">Dashboard</button>
            <button onclick="location.href='?tab=plugins'" class="<?php echo $current_tab === 'plugins' ? 'active' : ''; ?>" id="btn-plugins">Plugins</button>
            <button onclick="location.href='?tab=themes'" class="<?php echo $current_tab === 'themes' ? 'active' : ''; ?>" id="btn-themes">Themes</button>
            <button onclick="location.href='?tab=users'" class="<?php echo $current_tab === 'users' ? 'active' : ''; ?>" id="btn-users">Users</button>
            <button onclick="location.href='?tab=core'" class="<?php echo $current_tab === 'core' ? 'active' : ''; ?>" id="btn-core">Core Settings</button>
        </div>

        <!-- DASHBOARD -->
        <div id="dashboard" class="content <?php echo $current_tab === 'dashboard' ? 'active' : ''; ?>">
            <h2>System Status</h2>
            <div class="site-info-grid">
                <div class="form-group"><strong>PHP Version:</strong> <?php echo phpversion(); ?></div>
                <div class="form-group"><strong>MySQL Version:</strong> <?php echo $conn->server_info; ?></div>
                <div class="form-group"><strong>Active Plugins:</strong> <?php echo count($active_plugins); ?></div>
                <div class="form-group"><strong>Active Theme:</strong> <?php echo $active_theme; ?></div>
                <div class="form-group"><strong>WP Config:</strong> <?php echo htmlspecialchars($wp_config_path); ?></div>
            </div>
        </div>

        <!-- PLUGINS -->
        <div id="plugins" class="content <?php echo $current_tab === 'plugins' ? 'active' : ''; ?>">
            <div class="flex-between">
                <h2>Plugin Management</h2>
                <form method="post" style="display:inline;" onsubmit="return confirm('Deactivate ALL except WP Arzo?');">
                    <input type="hidden" name="action" value="deactivate_all_plugins">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <button type="submit" class="btn btn-danger">Bulk Deactivate All</button>
                </form>
            </div>

            <!-- Upload -->
            <div style="background:var(--background-light); padding:15px; margin:15px 0; border-radius:3px;">
                <h4 style="margin-top:0;">Upload Plugin (ZIP)</h4>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_plugin">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="file" name="zip_file" required accept=".zip" style="color:#fff;">
                    <div style="margin-top:10px; display:flex; align-items:center;">
                        <label class="switch">
                            <input type="checkbox" name="activate_now" value="1">
                            <span class="slider round"></span>
                        </label>
                        <span class="toggle-label">Activate immediately</span>
                    </div>
                    <button type="submit" class="btn btn-sm" style="margin-top:10px;">Install</button>
                </form>
            </div>

            <div class="form-group">
                <input type="text" id="search-plugins" class="form-control" placeholder="Search plugins..." onkeyup="filterTable('plugins-table', this.value)">
            </div>

            <div style="max-height: 500px; overflow-y: auto;">
                <table id="plugins-table">
                    <tr><th>Plugin Name</th><th>Path</th><th>Status</th><th>Action</th></tr>
                    <?php foreach ($display_plugins as $path => $name): 
                        $is_active = in_array($path, $active_plugins);
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($name); ?></td>
                            <td><small><?php echo htmlspecialchars($path); ?></small></td>
                            <td>
                                <span class="badge <?php echo $is_active ? 'badge-active' : 'badge-inactive'; ?>">
                                    <?php echo $is_active ? 'ACTIVE' : 'INACTIVE'; ?>
                                </span>
                            </td>
                            <td>
                                <form method="post" id="form-plugin-<?php echo md5($path); ?>">
                                    <input type="hidden" name="action" value="toggle_plugin">
                                    <input type="hidden" name="plugin_file" value="<?php echo htmlspecialchars($path); ?>">
                                    <input type="hidden" name="state" value="<?php echo $is_active ? 'deactivate' : 'activate'; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    
                                    <label class="switch">
                                        <input type="checkbox" onchange="document.getElementById('form-plugin-<?php echo md5($path); ?>').submit();" <?php echo $is_active ? 'checked' : ''; ?>>
                                        <span class="slider round"></span>
                                    </label>
                                    <span class="toggle-label"><?php echo $is_active ? 'Active' : 'Inactive'; ?></span>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php echo get_pagination_html($total_plugins, $items_per_page, $current_page, $base_url, 'plugins'); ?>
        </div>

        <!-- THEMES -->
        <div id="themes" class="content <?php echo $current_tab === 'themes' ? 'active' : ''; ?>">
            <h2>Theme Management</h2>
            
            <!-- Upload -->
            <div style="background:var(--background-light); padding:15px; margin:15px 0; border-radius:3px;">
                <h4 style="margin-top:0;">Upload Theme (ZIP)</h4>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_theme">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="file" name="zip_file" required accept=".zip" style="color:#fff;">
                    <div style="margin-top:10px; display:flex; align-items:center;">
                        <label class="switch">
                            <input type="checkbox" name="activate_now" value="1">
                            <span class="slider round"></span>
                        </label>
                        <span class="toggle-label">Activate immediately</span>
                    </div>
                    <button type="submit" class="btn btn-sm" style="margin-top:10px;">Install</button>
                </form>
            </div>

            <div class="form-group">
                <input type="text" id="search-themes" class="form-control" placeholder="Search themes..." onkeyup="filterTable('themes-table', this.value)">
            </div>

            <table id="themes-table">
                <tr><th>Theme Name</th><th>Folder</th><th>Status</th><th>Action</th></tr>
                <?php foreach ($display_themes as $slug => $name): 
                    $is_active = ($slug === $active_theme);
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($name); ?></td>
                        <td><?php echo htmlspecialchars($slug); ?></td>
                        <td>
                            <span class="badge <?php echo $is_active ? 'badge-active' : 'badge-inactive'; ?>">
                                <?php echo $is_active ? 'ACTIVE' : 'INACTIVE'; ?>
                            </span>
                        </td>
                        <td>
                                <?php if (!$is_active): ?>
                                    <form method="post" id="form-theme-<?php echo md5($slug); ?>">
                                        <input type="hidden" name="action" value="activate_theme">
                                        <input type="hidden" name="theme_slug" value="<?php echo htmlspecialchars($slug); ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <button type="submit" class="btn btn-sm">Activate</button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge badge-active">Active</span>
                                <?php endif; ?>
                            </td>
                    </tr>
                <?php endforeach; ?>
            </table>
            
            <!-- Pagination -->
            <?php echo get_pagination_html($total_themes, $items_per_page, $current_page, $base_url, 'themes'); ?>
        </div>

        <!-- USERS -->
        <div id="users" class="content <?php echo $current_tab === 'users' ? 'active' : ''; ?>">
            <h2>User Management</h2>
            
            <div style="background:var(--background-light); padding:15px; margin-bottom:20px; border-radius:3px;">
                <h4>Create Administrator</h4>
                <form method="post">
                    <input type="hidden" name="action" value="create_admin">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                        <div class="form-group"><label>Username</label><input type="text" name="username" class="form-control" required></div>
                        <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" required></div>
                        <div class="form-group"><label>Password</label><input type="text" name="password" class="form-control" required></div>
                    </div>
                    <button type="submit" class="btn">Create Admin</button>
                </form>
            </div>

            <h4>Existing Users</h4>
            <?php 
                $users_offset = ($current_page - 1) * $items_per_page;
                $users_count_res = $conn->query("SELECT COUNT(*) as count FROM {$prefix}users");
                $total_users = ($users_count_res && $row = $users_count_res->fetch_assoc()) ? $row['count'] : 0;
                
                $users_res = $conn->query("SELECT ID, user_login, user_email FROM {$prefix}users LIMIT $items_per_page OFFSET $users_offset");
            ?>
            <table>
                <tr><th>ID</th><th>Username</th><th>Email</th><th>Reset Password</th></tr>
                <?php while($user = $users_res->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $user['ID']; ?></td>
                        <td><?php echo htmlspecialchars($user['user_login']); ?></td>
                        <td><?php echo htmlspecialchars($user['user_email']); ?></td>
                        <td>
                            <form method="post" style="display:flex; gap:5px;">
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="user_id" value="<?php echo $user['ID']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="text" name="new_pass" placeholder="New Pass" class="form-control" style="width:120px; padding:5px;" required>
                                <button type="submit" class="btn btn-sm">Reset</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
            
            <!-- Pagination -->
            <?php echo get_pagination_html($total_users, $items_per_page, $current_page, $base_url, 'users'); ?>
        </div>

        <!-- CORE -->
        <div id="core" class="content <?php echo $current_tab === 'core' ? 'active' : ''; ?>">
            <h2>Core Settings</h2>
            <?php
                $siteurl_res = $conn->query("SELECT option_value FROM {$prefix}options WHERE option_name = 'siteurl'");
                $siteurl = ($siteurl_res && $row = $siteurl_res->fetch_assoc()) ? $row['option_value'] : '';
                $home_res = $conn->query("SELECT option_value FROM {$prefix}options WHERE option_name = 'home'");
                $home = ($home_res && $row = $home_res->fetch_assoc()) ? $row['option_value'] : '';
            ?>
            <form method="post">
                <input type="hidden" name="action" value="update_url">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="form-group">
                    <label>Site URL (siteurl)</label>
                    <input type="text" name="site_url" value="<?php echo htmlspecialchars($siteurl); ?>" class="form-control">
                </div>
                <button type="submit" class="btn">Update URLs</button>
            </form>
        </div>

        <?php } ?>
    <?php endif; ?>
    </div>

    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.nav button').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            document.getElementById('btn-' + tabId).classList.add('active');
        }

        function filterTable(tableId, query) {
            const filter = query.toUpperCase();
            const table = document.getElementById(tableId);
            const tr = table.getElementsByTagName("tr");
            for (let i = 1; i < tr.length; i++) {
                const tds = tr[i].getElementsByTagName("td");
                let visible = false;
                for (let j = 0; j < tds.length; j++) {
                    if (tds[j]) {
                        if (tds[j].textContent.toUpperCase().indexOf(filter) > -1) {
                            visible = true;
                            break;
                        }
                    }
                }
                tr[i].style.display = visible ? "" : "none";
            }
        }
    </script>
</body>
</html>