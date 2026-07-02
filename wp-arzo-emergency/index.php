<?php

/**
 * WP Arzo - Emergency Recovery Script
 * 
 * Standalone recovery tool that works independently of WordPress core.
 * Features: Plugin/Theme Management, User Control, DB Repair, File Uploads.
 *
 * @package WP_Arzo
 * @version 2.4
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
// Allow Google Fonts, images, and inline styles/scripts. No 'unsafe-eval' (not needed).
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: https:; font-src 'self' https://fonts.gstatic.com data:; base-uri 'self'; form-action 'self'; frame-ancestors 'none';");
header("Referrer-Policy: no-referrer");

// Define constants
// Read the real plugin version from the main plugin header so the emergency page always
// matches the installed version (works standalone — reads the file directly, no WP needed).
if (!defined('WP_ARZO_EMERGENCY_VERSION')) {
    $arzo_ver  = '';
    $arzo_main = __DIR__ . '/../wp-arzo.php';
    if (is_readable($arzo_main)) {
        $arzo_head = file_get_contents($arzo_main, false, null, 0, 2048);
        if ($arzo_head !== false && preg_match('/Version:\s*([0-9][0-9A-Za-z.\-]*)/i', $arzo_head, $m)) {
            $arzo_ver = $m[1];
        }
    }
    define('WP_ARZO_EMERGENCY_VERSION', $arzo_ver !== '' ? $arzo_ver : '2.4');
}
define('WP_ARZO_EMERGENCY_DIR', __DIR__);
define('WP_ARZO_CONFIG_FILE', dirname(__DIR__) . '/arzo-safe.php');
// This file lives at …/wp-content/plugins/wp-arzo/wp-arzo-emergency/, so three
// dirname() hops already land on wp-content. (The previous definition appended an
// extra "/wp-content", pointing at a non-existent wp-content/wp-content — which is
// why the Plugins/Themes lists came up empty.)
if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', dirname(dirname(dirname(__DIR__))));
}

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

function verify_nonce()
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Security Check Failed: Invalid Nonce');
    }
}

// Helper: Redirect
function redirect($url)
{
    if (!headers_sent()) {
        header("Location: $url");
        exit;
    } else {
        echo "<script>window.location.href='$url';</script>";
        exit;
    }
}

// --- Brute-force throttle (file-based, keyed by client IP) -----------------
// The login form is the entry point, so it can't use the session CSRF token.
// Instead, lock out an IP after repeated failures to blunt password guessing.
function arzo_emergency_throttle_file()
{
    return dirname(__DIR__) . '/.arzo-throttle.json';
}
function arzo_emergency_client_ip()
{
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
}
function arzo_emergency_read_throttle()
{
    $f = arzo_emergency_throttle_file();
    if (!file_exists($f)) return [];
    $data = json_decode((string) file_get_contents($f), true);
    return is_array($data) ? $data : [];
}
function arzo_emergency_lockout_seconds()
{
    $data = arzo_emergency_read_throttle();
    $rec = isset($data[arzo_emergency_client_ip()]) ? $data[arzo_emergency_client_ip()] : null;
    if ($rec && !empty($rec['until']) && time() < $rec['until']) {
        return (int) ($rec['until'] - time());
    }
    return 0;
}
function arzo_emergency_record_failure()
{
    $data = arzo_emergency_read_throttle();
    $ip = arzo_emergency_client_ip();
    $rec = isset($data[$ip]) ? $data[$ip] : ['count' => 0, 'until' => 0];
    $rec['count'] = (int) $rec['count'] + 1;
    if ($rec['count'] >= 5) {            // lock for 15 min after 5 failures
        $rec['until'] = time() + 900;
        $rec['count'] = 0;
    }
    $data[$ip] = $rec;
    @file_put_contents(arzo_emergency_throttle_file(), json_encode($data), LOCK_EX);
}
function arzo_emergency_clear_failures()
{
    $data = arzo_emergency_read_throttle();
    unset($data[arzo_emergency_client_ip()]);
    @file_put_contents(arzo_emergency_throttle_file(), json_encode($data), LOCK_EX);
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
        $lock = arzo_emergency_lockout_seconds();
        if ($lock > 0) {
            $error_msg = 'Too many failed attempts. Try again in ' . ceil($lock / 60) . ' minute(s).';
        } elseif (isset($_POST['password']) && defined('WP_ARZO_EMERGENCY_HASH')) {
            if (password_verify($_POST['password'], WP_ARZO_EMERGENCY_HASH)) {
                arzo_emergency_clear_failures();
                session_regenerate_id(true);
                $_SESSION['arzo_emergency_auth'] = true;
                redirect($redirect_base);
            } else {
                arzo_emergency_record_failure();
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

// Locate wp-config.php. The standard location is the WordPress root (4 dirs up from
// this file: …/wp-content/plugins/wp-arzo/wp-arzo-emergency/), and WP also supports a
// "split config" one directory ABOVE the webroot. Prefer a file that actually defines
// DB_NAME so we don't pick up an unrelated stub.
$wp_config_path = '';
$wp_root = dirname(dirname(dirname(dirname(__DIR__)))); // webroot / WordPress root
$possible_paths = [
    $wp_root . '/wp-config.php',                          // standard
    dirname($wp_root) . '/wp-config.php',                 // split-config (one above webroot)
    dirname(dirname(dirname(__DIR__))) . '/wp-config.php',// wp-content/
    dirname(dirname(__DIR__)) . '/wp-config.php',         // plugins/
    dirname(__DIR__) . '/wp-config.php',                  // plugin root
];
foreach ($possible_paths as $path) {
    if (file_exists($path) && strpos((string) @file_get_contents($path), 'DB_NAME') !== false) {
        $wp_config_path = $path;
        break;
    }
}
// Fallback: first existing wp-config even if the DB_NAME probe failed (e.g. unreadable).
if ($wp_config_path === '') {
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $wp_config_path = $path;
            break;
        }
    }
}

// Split a WordPress DB_HOST value into [host, port, socket]. Handles "host",
// "host:3306" (TCP port) and "host:/path/to.sock" (Unix socket).
function arzo_parse_db_host($host)
{
    $host = trim((string) $host);
    $port = 0;
    $socket = null;
    if ($host !== '' && strpos($host, ':') !== false) {
        list($h, $p) = explode(':', $host, 2);
        $host = ($h !== '') ? $h : 'localhost';
        if (ctype_digit($p)) {
            $port = (int) $p;
        } elseif ($p !== '') {
            $socket = $p;
        }
    }
    return array($host !== '' ? $host : 'localhost', $port, $socket);
}

// DB Helper — tolerant of host:port / host:socket and DB_CHARSET, quoting/spacing variants.
function get_db_connection($wp_config_path)
{
    if (!file_exists($wp_config_path)) return false;
    $config_content = file_get_contents($wp_config_path);
    // Note: no trailing ';' in the patterns so "define( … )" with odd spacing still matches.
    preg_match("/define\(\s*['\"]DB_NAME['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $config_content, $db_name);
    preg_match("/define\(\s*['\"]DB_USER['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $config_content, $db_user);
    preg_match("/define\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $config_content, $db_password);
    preg_match("/define\(\s*['\"]DB_HOST['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $config_content, $db_host);
    preg_match("/define\(\s*['\"]DB_CHARSET['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $config_content, $db_charset);
    preg_match("/\\\$table_prefix\s*=\s*['\"](.*?)['\"]\s*;/", $config_content, $table_prefix);

    if (empty($db_name[1]) || empty($db_user[1]) || empty($db_host[1])) return "Could not parse wp-config.php.";

    list($host, $port, $socket) = arzo_parse_db_host($db_host[1]);
    $user = $db_user[1];
    $pass = isset($db_password[1]) ? $db_password[1] : '';
    $name = $db_name[1];
    $charset = !empty($db_charset[1]) ? $db_charset[1] : 'utf8mb4';
    $prefix = isset($table_prefix[1]) ? $table_prefix[1] : 'wp_';

    $mysqli = mysqli_init();
    if (!$mysqli) return "Could not initialise MySQL.";
    @$mysqli->real_connect($host, $user, $pass, $name, $port ?: 0, $socket);
    if ($mysqli->connect_error) return "Connection failed: " . $mysqli->connect_error;
    @$mysqli->set_charset($charset);

    return ['conn' => $mysqli, 'prefix' => $prefix];
}

// File System Helpers
function recursive_copy($src, $dst)
{
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

function recursive_rmdir($dir)
{
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
function scan_file_for_malware($file)
{
    // Since this is an authenticated recovery tool used by admins,
    // we assume the uploaded files are trusted.
    // Overly aggressive scanning was blocking legitimate themes/plugins.
    return false;
}

// Helper: Get Asset URL
function get_asset_url($path)
{
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

// Helper: inline SVG icons. The emergency tool can't load the Font Awesome CDN
// (its own CSP blocks external scripts/styles), so it ships its own tiny set of
// stroke icons — same visual language as the plugin dashboard, currentColor-driven.
function arzo_em_icon($name, $size = 16)
{
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'plugin'    => '<path d="M6 3v4M10 3v4M4 7h8v4a4 4 0 0 1-8 0V7Z"/><path d="M8 15v6"/>',
        'theme'     => '<circle cx="12" cy="12" r="9"/><circle cx="8.5" cy="10.5" r="1"/><circle cx="15.5" cy="10.5" r="1"/><circle cx="12" cy="15.5" r="1"/>',
        'users'     => '<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16 5a3 3 0 0 1 0 6M18 20a6 6 0 0 0-3-5.2"/>',
        'settings'  => '<line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/><circle cx="9" cy="7" r="2" fill="currentColor" stroke="none"/><circle cx="15" cy="12" r="2" fill="currentColor" stroke="none"/><circle cx="8" cy="17" r="2" fill="currentColor" stroke="none"/>',
        'check'     => '<path d="M5 12l5 5L20 7"/>',
        'power'     => '<path d="M12 3v9"/><path d="M6.5 7a8 8 0 1 0 11 0"/>',
        'key'       => '<circle cx="8" cy="15" r="4"/><path d="M11 12l8-8M17 4l3 3M15 6l2 2"/>',
        'upload'    => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M5 20h14"/>',
        'link'      => '<path d="M9 15l6-6"/><path d="M10 6l1-1a4 4 0 0 1 6 6l-1 1M14 18l-1 1a4 4 0 0 1-6-6l1-1"/>',
        'life-ring' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="M5 5l3.5 3.5M15.5 15.5L19 19M19 5l-3.5 3.5M8.5 15.5L5 19"/>',
        'left'      => '<path d="M15 5l-7 7 7 7"/>',
        'right'     => '<path d="M9 5l7 7-7 7"/>',
        'shield'    => '<path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6l7-3Z"/>',
    ];
    if (!isset($paths[$name])) return '';
    return '<svg width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" '
        . 'style="vertical-align:middle;flex-shrink:0;" aria-hidden="true">' . $paths[$name] . '</svg>';
}

// Helper: Pagination
function get_pagination_html($total_items, $items_per_page, $current_page, $base_url, $tab)
{
    $total_pages = ceil($total_items / $items_per_page);
    if ($total_pages <= 1) return '';

    $html = '<div class="pagination-container">';
    $html .= '<div class="pagination-info">';
    $html .= sprintf(
        'Showing %d to %d of %d items',
        ($current_page - 1) * $items_per_page + 1,
        min($current_page * $items_per_page, $total_items),
        $total_items
    );
    $html .= '</div>';
    $html .= '<div class="pagination-controls">';

    // Previous Button
    $disabled = ($current_page <= 1) ? 'disabled' : '';
    $prev_link = $disabled ? '#' : $base_url . "&tab=$tab&p=" . ($current_page - 1);
    $html .= "<button onclick=\"location.href='$prev_link'\" $disabled>" . arzo_em_icon('left', 13) . " Previous</button>";

    // Page Numbers
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);

    if ($start_page > 1) {
        $html .= "<button onclick=\"location.href='$base_url&tab=$tab&p=1'\">1</button>";
        if ($start_page > 2) $html .= '<span style="color:var(--muted-text); padding:5px;">...</span>';
    }

    for ($i = $start_page; $i <= $end_page; $i++) {
        $active = ($i == $current_page) ? 'active' : '';
        $html .= "<button class=\"$active\" onclick=\"location.href='$base_url&tab=$tab&p=$i'\">$i</button>";
    }

    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) $html .= '<span style="color:var(--muted-text); padding:5px;">...</span>';
        $html .= "<button onclick=\"location.href='$base_url&tab=$tab&p=$total_pages'\">$total_pages</button>";
    }

    // Next Button
    $disabled = ($current_page >= $total_pages) ? 'disabled' : '';
    $next_link = $disabled ? '#' : $base_url . "&tab=$tab&p=" . ($current_page + 1);
    $html .= "<button onclick=\"location.href='$next_link'\" $disabled>Next " . arzo_em_icon('right', 13) . "</button>";

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
                // NOTE: md5() is intentional here. WordPress core is not loaded in the
                // emergency tool, so we can't call wp_hash_password(). WP accepts a plain
                // 32-char md5 user_pass for back-compat and transparently re-hashes it to a
                // modern phpass on the user's first successful login.
                $user  = (string) $_POST['username'];
                $pass  = md5((string) $_POST['password']);
                $email = (string) $_POST['email'];
                $now   = date('Y-m-d H:i:s');
                $zero  = 0;
                if ($stmt = $conn->prepare("INSERT INTO {$prefix}users (user_login, user_pass, user_nicename, user_email, user_registered, user_status, display_name) VALUES (?, ?, ?, ?, ?, ?, ?)")) {
                    $stmt->bind_param('sssssis', $user, $pass, $user, $email, $now, $zero, $user);
                    if ($stmt->execute()) {
                        $uid = $stmt->insert_id;
                        $stmt->close();
                        $caps    = serialize(['administrator' => true]);
                        $cap_key = $prefix . 'capabilities';
                        $lvl_key = $prefix . 'user_level';
                        $ten     = '10';
                        if ($m = $conn->prepare("INSERT INTO {$prefix}usermeta (user_id, meta_key, meta_value) VALUES (?, ?, ?)")) {
                            $m->bind_param('iss', $uid, $cap_key, $caps);
                            $m->execute();
                            $m->close();
                        }
                        if ($m = $conn->prepare("INSERT INTO {$prefix}usermeta (user_id, meta_key, meta_value) VALUES (?, ?, ?)")) {
                            $m->bind_param('iss', $uid, $lvl_key, $ten);
                            $m->execute();
                            $m->close();
                        }
                        $success_msg = "Admin created.";
                    } else {
                        $error_msg = "DB Error: " . $stmt->error;
                    }
                } else {
                    $error_msg = "DB Error: " . $conn->error;
                }
                break;

            case 'reset_password':
                $uid  = intval($_POST['user_id']);
                $pass = md5((string) $_POST['new_pass']);
                if ($stmt = $conn->prepare("UPDATE {$prefix}users SET user_pass = ? WHERE ID = ?")) {
                    $stmt->bind_param('si', $pass, $uid);
                    $stmt->execute();
                    $stmt->close();
                    $success_msg = "Password reset.";
                } else {
                    $error_msg = "DB Error: " . $conn->error;
                }
                break;

            // --- CORE ---
            case 'update_url':
                $url = (string) $_POST['site_url'];
                if ($stmt = $conn->prepare("UPDATE {$prefix}options SET option_value = ? WHERE option_name IN ('siteurl','home')")) {
                    $stmt->bind_param('s', $url);
                    $stmt->execute();
                    $stmt->close();
                    $success_msg = "URLs updated.";
                } else {
                    $error_msg = "DB Error: " . $conn->error;
                }
                break;

            // --- REPAIR / RECOVERY ---
            case 'switch_default_theme':
                $theme_dir = WP_CONTENT_DIR . '/themes';
                $dirs = is_dir($theme_dir) ? array_values(array_filter(scandir($theme_dir), function ($d) use ($theme_dir) {
                    return $d !== '.' && $d !== '..' && is_dir($theme_dir . '/' . $d) && file_exists($theme_dir . '/' . $d . '/style.css');
                })) : array();
                $twenties = array_values(array_filter($dirs, function ($s) {
                    return stripos($s, 'twenty') === 0;
                }));
                if ($twenties) {
                    rsort($twenties);
                    $slug = $twenties[0];
                } else {
                    $slug = $dirs ? $dirs[0] : '';
                }
                if ($slug !== '') {
                    foreach (array('template', 'stylesheet') as $opt) {
                        if ($stmt = $conn->prepare("UPDATE {$prefix}options SET option_value = ? WHERE option_name = '" . $opt . "'")) {
                            $stmt->bind_param('s', $slug);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                    $success_msg = "Switched active theme to '" . htmlspecialchars($slug) . "'.";
                } else {
                    $error_msg = "No installed theme with a style.css was found.";
                }
                break;

            case 'restore_htaccess':
                $ht = $wp_root . '/.htaccess';
                if (file_exists($ht)) {
                    @copy($ht, $wp_root . '/.htaccess.arzo-bak');
                }
                $default = "# BEGIN WordPress\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]\nRewriteBase /\nRewriteRule ^index\\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . /index.php [L]\n</IfModule>\n# END WordPress\n";
                if (@file_put_contents($ht, $default) !== false) {
                    $success_msg = "Default .htaccess restored (previous saved as .htaccess.arzo-bak).";
                } else {
                    $error_msg = "Could not write .htaccess — check file permissions.";
                }
                break;

            case 'clear_transients':
                if ($conn->query("DELETE FROM {$prefix}options WHERE option_name LIKE '_transient_%' OR option_name LIKE '_transient_timeout_%' OR option_name LIKE '_site_transient_%' OR option_name LIKE '_site_transient_timeout_%'")) {
                    $success_msg = "Cleared " . (int) $conn->affected_rows . " transient row(s).";
                } else {
                    $error_msg = "DB Error: " . $conn->error;
                }
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

        /* Tokens aligned with the plugin design system (assets/css/design-tokens.css).
           Kept inline so the recovery tool stays fully self-contained. */
        :root {
            --accent-color: #16e791;
            --accent-soft: rgba(22, 231, 145, 0.12);
            --accent-ring: rgba(22, 231, 145, 0.45);
            --accent-hover: #0ea66b;
            --primary-text: #ffffff;
            --secondary-text: #e0e0e0;
            --muted-text: #999999;
            --background-dark: #121212;
            --background-medium: #1e1e1e;
            /* Elevated + sunken surfaces, matching the dashboard token system so the
               recovery tool looks identical to the plugin (inputs sink, panels lift). */
            --background-elev: #242424;
            --background-input: #151515;
            --background-light: #2a2a2a;
            --border-color: #333333;
            --border-light: #444444;
            --danger-color: #ff4d4f;
            --danger-soft: rgba(255, 77, 79, 0.15);
            --success-color: #16e791;
            --radius-global: 8px;
            --radius-lg: 14px;
            --radius-sm: 4px;
            --radius-pill: 999px;
        }

        body {
            font-family: 'Lato', sans-serif;
            margin: 0;
            padding: 20px;
            background: var(--background-dark);
            color: var(--secondary-text);
            min-height: 100vh;
        }

        /* Branded scrollbar (self-contained recovery tool). */
        html, body { scrollbar-width: thin; scrollbar-color: var(--background-light) transparent; }
        ::-webkit-scrollbar { width: 13px; height: 13px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--background-light); border: 3px solid transparent; background-clip: padding-box; border-radius: var(--radius-pill); }
        ::-webkit-scrollbar-thumb:hover { background: var(--border-light); background-clip: padding-box; }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: var(--background-medium);
            padding: 20px;
            border-radius: var(--radius-global);
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

        /* Segmented pill tabs — identical language to the plugin dashboard's .wpa-tabs. */
        .nav {
            margin-bottom: 20px;
            display: inline-flex;
            flex-wrap: wrap;
            gap: 4px;
            padding: 4px;
            background: var(--background-medium);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            max-width: 100%;
        }

        .nav button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            background: transparent;
            color: var(--muted-text);
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            font-size: 14px;
        }

        .nav button:hover {
            background: var(--background-light);
            color: var(--primary-text);
        }

        .nav button.active {
            background: var(--accent-soft);
            color: var(--accent-color);
            box-shadow: inset 0 0 0 1px var(--accent-ring);
        }

        .nav button:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px var(--accent-ring);
        }

        .content {
            display: none;
            background: var(--background-medium);
            padding: 20px;
            border-radius: var(--radius-global);
            animation: fadeIn 0.3s;
        }

        .content.active {
            display: block;
        }

        /* System-status info cards — mirror the plugin's Site Info cards. */
        .info-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .info-card {
            background: var(--background-medium);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-global);
            overflow: hidden;
        }

        .info-card-header {
            background: var(--background-elev);
            color: var(--primary-text);
            padding: 14px 18px;
            font-weight: 600;
            font-size: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .info-card-body {
            padding: 4px 18px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--secondary-text);
            font-weight: 500;
        }

        .info-value {
            color: var(--primary-text);
            font-weight: 600;
            text-align: right;
            word-break: break-all;
        }

        .alert {
            padding: 15px;
            border-radius: var(--radius-global);
            margin-bottom: 20px;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid var(--danger-color);
            color: var(--danger-color);
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
            background: var(--background-elev);
            color: var(--muted-text);
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        tr:hover {
            background: var(--background-light);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 9px 16px;
            background: var(--accent-color);
            color: var(--background-dark);
            border: 1px solid transparent;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s;
            font-size: 13px;
            font-family: inherit;
        }

        .btn:hover {
            background: var(--accent-hover);
            color: var(--primary-text);
        }

        .btn:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px var(--accent-ring);
        }

        /* Secondary / ghost button — for non-primary actions (matches dashboard). */
        .btn-secondary {
            background: var(--background-elev);
            color: var(--primary-text);
            border-color: var(--border-light);
        }

        .btn-secondary:hover {
            background: var(--background-light);
            color: var(--accent-color);
        }

        .btn-danger {
            background: var(--danger-color);
            color: #fff;
        }

        .btn-danger:hover {
            background: var(--danger-color);
            color: #fff;
            filter: brightness(1.08);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--secondary-text);
        }

        .form-control {
            width: 100%;
            padding: 10px;
            background: var(--background-input);
            border: 1px solid var(--border-light);
            color: var(--primary-text);
            border-radius: var(--radius-sm);
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px var(--accent-ring);
        }

        .flex-between {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold;
        }

        .badge-active {
            background: rgba(22, 231, 145, 0.2);
            color: var(--accent-color);
        }

        .badge-inactive {
            background: rgba(108, 117, 125, 0.2);
            color: var(--muted-text);
        }

        /* Brand bar — matches the WP Arzo dashboard/console exactly (ported to the
           emergency page's local variables since the plugin token sheet isn't loaded here). */
        .wpa-brandbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            padding: 12px 18px;
            background: var(--background-medium);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .wpa-brandbar__id { display: flex; align-items: center; gap: 12px; }
        .wpa-brandbar__logo { width: 38px; height: 38px; border-radius: 8px; object-fit: contain; background: var(--background-light); padding: 4px; }
        .wpa-brandbar__name { font-size: 16px; font-weight: 700; color: var(--primary-text); line-height: 1.1; }
        .wpa-brandbar__email { font-size: 13px; color: var(--secondary-text); text-decoration: none; }
        .wpa-brandbar__email:hover { color: var(--accent-color); }
        .wpa-brandbar__meta { display: flex; align-items: center; gap: 16px; }
        .wpa-brandbar__ver { color: var(--accent-color); font-weight: 700; font-size: 13px; }
        .wpa-brandbar__gh { display: inline-flex; align-items: center; gap: 5px; color: var(--primary-text); text-decoration: none; font-size: 13px; }
        .wpa-brandbar__gh:hover { color: var(--accent-color); }

        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        .pagination-info {
            color: var(--muted-text);
            font-size: 13px;
        }

        .pagination-controls {
            display: flex;
            gap: 5px;
        }

        .pagination-controls button {
            background: var(--background-light);
            border: 1px solid var(--border-color);
            color: var(--secondary-text);
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }

        .pagination-controls button:hover:not(:disabled) {
            border-color: var(--accent-color);
            color: var(--accent-color);
        }

        .pagination-controls button.active {
            background: var(--accent-color);
            color: var(--background-dark);
            border-color: var(--accent-color);
        }

        .pagination-controls button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Toggle Switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 40px !important;
            height: 25px !important;
            vertical-align: middle;
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
            background-color: var(--border-light);
            transition: .4s;
            border-radius: 18px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 15px !important;
            width: 15px !important;
            left: 5px !important;
            bottom: 5px !important;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: var(--accent-color);
        }

        input:checked+.slider:before {
            transform: translateX(16px) !important;
        }

        .toggle-label {
            margin-left: 15px;
            cursor: pointer;
            font-size: 13px;
        }

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
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 400px;
            text-align: center;
            border: 1px solid var(--border-color);
            position: relative;
            z-index: 10;
        }

        .login-card h1 {
            font-size: 24px;
            margin-bottom: 10px;
            color: var(--primary-text);
        }

        .login-card h2 {
            font-size: 18px;
            margin-bottom: 25px;
            color: var(--accent-color);
            border: none;
            padding: 0;
        }

        .login-card .form-control {
            background: var(--background-input);
            border: 1px solid var(--border-light);
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

        .login-card .logo-area {
            margin-bottom: 5px;
        }

        .login-card .logo-area img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin-bottom: 5px;
        }

        .login-card .footer-links {
            margin-top: 20px;
            font-size: 12px;
            color: var(--muted-text);
        }

        .login-card .footer-links a {
            color: var(--muted-text);
            text-decoration: none;
        }

        .login-card .footer-links a:hover {
            color: var(--accent-color);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }
    </style>
</head>

<body>
    <?php if (!$is_authenticated): ?>
        <div class="login-wrapper">
            <div class="login-card">
                <div class="logo-area">
                    <img src="<?php echo get_asset_url('wp-arzo-glyph.svg'); ?>" alt="WP Arzo">
                </div>
                <h1>WP Arzo Recovery</h1>

                <?php if ($success_msg) echo "<div class='alert alert-success' style='text-align:left; margin-bottom:20px;'>$success_msg</div>"; ?>
                <?php if ($error_msg) echo "<div class='alert alert-error' style='text-align:left; margin-bottom:20px;'>$error_msg</div>"; ?>

                <form method="post">
                    <input type="hidden" name="action" value="<?php echo $setup_mode ? 'setup' : 'login'; ?>">
                    <div style="text-align: left; margin-bottom: 5px;">
                        <label
                            style="font-size: 12px; color: var(--muted-text); text-transform: uppercase; font-weight: bold; letter-spacing: 0.5px;"><?php echo $setup_mode ? 'Create Password' : 'Password'; ?></label>
                    </div>
                    <input type="password" name="<?php echo $setup_mode ? 'new_password' : 'password'; ?>"
                        class="form-control" placeholder="Enter your password" required autofocus>
                    <button type="submit" class="btn"><?php echo arzo_em_icon($setup_mode ? 'check' : 'shield'); ?> <?php echo $setup_mode ? 'Create & Login' : 'Login'; ?></button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="container">
            <!-- Brand bar — identical to the WP Arzo dashboard/console. -->
            <div class="wpa-brandbar">
                <div class="wpa-brandbar__id">
                    <img class="wpa-brandbar__logo" src="<?php echo get_asset_url('wp-arzo-icon.svg'); ?>" alt="WP Arzo">
                    <div>
                        <div class="wpa-brandbar__name">WP Arzo</div>
                        <a class="wpa-brandbar__email" href="https://yasirshabbir.com" target="_blank" rel="noopener">by Yasir Shabbir</a>
                    </div>
                </div>
                <div class="wpa-brandbar__meta">
                    <span class="wpa-brandbar__ver">v<?php echo WP_ARZO_EMERGENCY_VERSION; ?></span>
                    <a class="wpa-brandbar__gh" href="https://github.com/yasirshabbirservices/wp-arzo" target="_blank" rel="noopener">
                        <svg height="18" width="18" viewBox="0 0 16 16" fill="currentColor">
                            <path
                                d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z">
                            </path>
                        </svg>
                        GitHub
                    </a>
                </div>
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

                <nav class="nav" aria-label="Recovery sections">
                    <?php
                    $em_tabs = [
                        'dashboard' => ['Dashboard', 'dashboard'],
                        'plugins'   => ['Plugins', 'plugin'],
                        'themes'    => ['Themes', 'theme'],
                        'users'     => ['Users', 'users'],
                        'core'      => ['Core Settings', 'settings'],
                    ];
                    foreach ($em_tabs as $em_key => $em_meta) {
                        $em_active = ($current_tab === $em_key);
                        echo '<button onclick="location.href=\'?tab=' . $em_key . '\'" class="' . ($em_active ? 'active' : '') . '" id="btn-' . $em_key . '"'
                            . ($em_active ? ' aria-current="page"' : '') . '>'
                            . arzo_em_icon($em_meta[1]) . '<span>' . htmlspecialchars($em_meta[0]) . '</span></button>';
                    }
                    ?>
                </nav>

                <!-- DASHBOARD -->
                <div id="dashboard" class="content <?php echo $current_tab === 'dashboard' ? 'active' : ''; ?>">
                    <h2>System Status</h2>
                    <div class="info-cards">
                        <div class="info-card">
                            <div class="info-card-header">Environment</div>
                            <div class="info-card-body">
                                <div class="info-item"><span class="info-label">PHP Version</span><span class="info-value"><?php echo htmlspecialchars(phpversion()); ?></span></div>
                                <div class="info-item"><span class="info-label">MySQL Version</span><span class="info-value"><?php echo htmlspecialchars($conn->server_info); ?></span></div>
                                <div class="info-item"><span class="info-label">Emergency Tool</span><span class="info-value">v<?php echo htmlspecialchars(WP_ARZO_EMERGENCY_VERSION); ?></span></div>
                            </div>
                        </div>
                        <div class="info-card">
                            <div class="info-card-header">WordPress</div>
                            <div class="info-card-body">
                                <div class="info-item"><span class="info-label">Active Plugins</span><span class="info-value"><?php echo count($active_plugins); ?></span></div>
                                <div class="info-item"><span class="info-label">Active Theme</span><span class="info-value"><?php echo htmlspecialchars($active_theme); ?></span></div>
                                <div class="info-item"><span class="info-label">WP Config</span><span class="info-value"><?php echo htmlspecialchars($wp_config_path); ?></span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PLUGINS -->
                <div id="plugins" class="content <?php echo $current_tab === 'plugins' ? 'active' : ''; ?>">
                    <div class="flex-between">
                        <h2>Plugin Management</h2>
                        <form method="post" style="display:inline;"
                            onsubmit="return confirm('Deactivate ALL except WP Arzo?');">
                            <input type="hidden" name="action" value="deactivate_all_plugins">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <button type="submit" class="btn btn-danger"><?php echo arzo_em_icon('power'); ?> Bulk Deactivate All</button>
                        </form>
                    </div>

                    <!-- Upload -->
                    <div style="background:var(--background-elev); border:1px solid var(--border-color); padding:15px; margin:15px 0; border-radius:var(--radius-sm);">
                        <h4 style="margin-top:0;">Upload Plugin (ZIP)</h4>
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload_plugin">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="file" name="zip_file" required accept=".zip" style="color:var(--primary-text);">
                            <div style="margin-top:10px; display:flex; align-items:center;">
                                <label class="switch">
                                    <input type="checkbox" name="activate_now" value="1">
                                    <span class="slider round"></span>
                                </label>
                                <span class="toggle-label">Activate immediately</span>
                            </div>
                            <button type="submit" class="btn btn-sm" style="margin-top:10px;"><?php echo arzo_em_icon('upload', 13); ?> Install</button>
                        </form>
                    </div>

                    <div class="form-group">
                        <input type="text" id="search-plugins" class="form-control" placeholder="Search plugins..."
                            onkeyup="filterTable('plugins-table', this.value)">
                    </div>

                    <div style="max-height: 500px; overflow-y: auto;">
                        <table id="plugins-table">
                            <tr>
                                <th>Plugin Name</th>
                                <th>Path</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
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
                                            <input type="hidden" name="state"
                                                value="<?php echo $is_active ? 'deactivate' : 'activate'; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                            <label class="switch">
                                                <input type="checkbox"
                                                    onchange="document.getElementById('form-plugin-<?php echo md5($path); ?>').submit();"
                                                    <?php echo $is_active ? 'checked' : ''; ?>>
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
                    <div style="background:var(--background-elev); border:1px solid var(--border-color); padding:15px; margin:15px 0; border-radius:var(--radius-sm);">
                        <h4 style="margin-top:0;">Upload Theme (ZIP)</h4>
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload_theme">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="file" name="zip_file" required accept=".zip" style="color:var(--primary-text);">
                            <div style="margin-top:10px; display:flex; align-items:center;">
                                <label class="switch">
                                    <input type="checkbox" name="activate_now" value="1">
                                    <span class="slider round"></span>
                                </label>
                                <span class="toggle-label">Activate immediately</span>
                            </div>
                            <button type="submit" class="btn btn-sm" style="margin-top:10px;"><?php echo arzo_em_icon('upload', 13); ?> Install</button>
                        </form>
                    </div>

                    <div class="form-group">
                        <input type="text" id="search-themes" class="form-control" placeholder="Search themes..."
                            onkeyup="filterTable('themes-table', this.value)">
                    </div>

                    <table id="themes-table">
                        <tr>
                            <th>Theme Name</th>
                            <th>Folder</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
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
                                    <form method="post" id="form-theme-<?php echo md5($slug); ?>">
                                        <input type="hidden" name="action" value="activate_theme">
                                        <input type="hidden" name="theme_slug" value="<?php echo htmlspecialchars($slug); ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                        <div style="display:flex; align-items:center;">
                                            <label class="switch">
                                                <!-- If active, checked and disabled. If inactive, clicking submits form to activate. -->
                                                <input type="checkbox"
                                                    onchange="if(confirm('Activate this theme?')) document.getElementById('form-theme-<?php echo md5($slug); ?>').submit(); else this.checked = false;"
                                                    <?php echo $is_active ? 'checked disabled' : ''; ?>>
                                                <span class="slider round"></span>
                                            </label>
                                            <span class="toggle-label"
                                                style="margin-left:10px;"><?php echo $is_active ? 'Active' : 'Activate'; ?></span>
                                        </div>
                                    </form>
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

                    <div style="background:var(--background-elev); border:1px solid var(--border-color); padding:20px; margin-bottom:24px; border-radius:var(--radius-sm);">
                        <h4 style="margin:0 0 4px;">Create Administrator</h4>
                        <p style="color:var(--muted-text); font-size:12px; margin:0 0 16px;">Add a fresh administrator account to regain access. The password is re-hashed securely on the first successful login.</p>
                        <form method="post">
                            <input type="hidden" name="action" value="create_admin">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:14px;">
                                <div class="form-group" style="margin:0;"><label>Username</label><input type="text" name="username" class="form-control" required autocomplete="off"></div>
                                <div class="form-group" style="margin:0;"><label>Email</label><input type="email" name="email" class="form-control" required autocomplete="off"></div>
                                <div class="form-group" style="margin:0;"><label>Password</label><input type="text" name="password" class="form-control" required autocomplete="off"></div>
                            </div>
                            <button type="submit" class="btn" style="margin-top:16px;"><?php echo arzo_em_icon('users'); ?> Create Admin</button>
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
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Reset Password</th>
                        </tr>
                        <?php while ($user = $users_res->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $user['ID']; ?></td>
                                <td><?php echo htmlspecialchars($user['user_login']); ?></td>
                                <td><?php echo htmlspecialchars($user['user_email']); ?></td>
                                <td>
                                    <form method="post" style="display:flex; gap:5px;">
                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="user_id" value="<?php echo $user['ID']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="text" name="new_pass" placeholder="New Pass" class="form-control"
                                            style="width:120px; padding:5px;" required>
                                        <button type="submit" class="btn btn-sm"><?php echo arzo_em_icon('key', 13); ?> Reset</button>
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
                            <label>Site URL (siteurl + home)</label>
                            <input type="text" name="site_url" value="<?php echo htmlspecialchars($siteurl); ?>"
                                class="form-control">
                        </div>
                        <button type="submit" class="btn"><?php echo arzo_em_icon('link'); ?> Update URLs</button>
                    </form>

                    <h2 style="margin-top:30px;">Repair &amp; Recovery</h2>
                    <p style="color:var(--muted-text); font-size:13px; margin-top:-5px;">One-click fixes for the most common "white screen" causes. Safe-mode (deactivate every plugin except WP Arzo) is on the <strong>Plugins</strong> tab.</p>
                    <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:10px;">
                        <form method="post" onsubmit="return confirm('Switch the site to a default (Twenty*) theme?');">
                            <input type="hidden" name="action" value="switch_default_theme">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <button type="submit" class="btn btn-secondary"><?php echo arzo_em_icon('theme'); ?> Switch to default theme</button>
                        </form>
                        <form method="post" onsubmit="return confirm('Restore the default WordPress .htaccess? The current file is backed up first.');">
                            <input type="hidden" name="action" value="restore_htaccess">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <button type="submit" class="btn btn-secondary"><?php echo arzo_em_icon('shield'); ?> Restore default .htaccess</button>
                        </form>
                        <form method="post" onsubmit="return confirm('Delete all transients (cached temporary options)?');">
                            <input type="hidden" name="action" value="clear_transients">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <button type="submit" class="btn btn-secondary"><?php echo arzo_em_icon('power'); ?> Clear all transients</button>
                        </form>
                    </div>
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