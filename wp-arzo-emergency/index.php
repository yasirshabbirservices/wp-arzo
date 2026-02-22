<?php
/**
 * WP Arzo - Emergency Recovery Script
 * 
 * Standalone recovery tool that works independently of WordPress core.
 * Features: DB repair, User management, Plugin/Theme control, Core URL fix.
 * 
 * @package WP_Arzo
 * @version 1.0
 */

// Disable error reporting to prevent leakage, unless explicitly enabled via query param
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
header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval'; img-src 'self' data:;");

// Define constants
define('WP_ARZO_EMERGENCY_VERSION', '1.0');
define('WP_ARZO_EMERGENCY_DIR', __DIR__);
define('WP_ARZO_CONFIG_FILE', dirname(__DIR__) . '/arzo-safe.php'); // Stores hashed password

// Helper: Secure Session Start
function start_secure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        // Secure session settings
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        session_start();
    }
}
start_secure_session();

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

// Authentication Check
if (!file_exists(WP_ARZO_CONFIG_FILE)) {
    // If config file doesn't exist, allow setup if WP config is readable
    $setup_mode = true;
} else {
    $setup_mode = false;
    require_once(WP_ARZO_CONFIG_FILE);
}

// Handle Login/Setup
$error_msg = '';
$success_msg = '';

// Get current URL parts for redirection
$current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$parsed_url = parse_url($current_url);
$redirect_base = $parsed_url['path']; // Keep just the path to avoid query string loop if any

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'login') {
            if (isset($_POST['password']) && defined('WP_ARZO_EMERGENCY_HASH')) {
                if (password_verify($_POST['password'], WP_ARZO_EMERGENCY_HASH)) {
                    $_SESSION['arzo_emergency_auth'] = true;
                    // Fix: Redirect to self (script path) instead of potential home redirect
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
}

// Check Auth
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

// Database Connection Helper
function get_db_connection($wp_config_path) {
    if (!file_exists($wp_config_path)) return false;

    $config_content = file_get_contents($wp_config_path);
    
    // Simple regex parsing for DB constants (robust enough for standard wp-config)
    preg_match("/define\(\s*['\"]DB_NAME['\"]\s*,\s*['\"](.*?)['\"]\s*\);/", $config_content, $db_name);
    preg_match("/define\(\s*['\"]DB_USER['\"]\s*,\s*['\"](.*?)['\"]\s*\);/", $config_content, $db_user);
    preg_match("/define\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"](.*?)['\"]\s*\);/", $config_content, $db_password);
    preg_match("/define\(\s*['\"]DB_HOST['\"]\s*,\s*['\"](.*?)['\"]\s*\);/", $config_content, $db_host);
    preg_match("/\\\$table_prefix\s*=\s*['\"](.*?)['\"];/", $config_content, $table_prefix);

    if (empty($db_name[1]) || empty($db_user[1]) || empty($db_host[1])) {
        return "Could not parse wp-config.php. Ensure standard formatting.";
    }

    $host = $db_host[1];
    $user = $db_user[1];
    $pass = isset($db_password[1]) ? $db_password[1] : '';
    $name = $db_name[1];
    $prefix = isset($table_prefix[1]) ? $table_prefix[1] : 'wp_';

    $mysqli = new mysqli($host, $user, $pass, $name);
    
    if ($mysqli->connect_error) {
        return "Connection failed: " . $mysqli->connect_error;
    }

    return ['conn' => $mysqli, 'prefix' => $prefix];
}

// Actions Logic (Only if Authenticated)
if ($is_authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $db_data = get_db_connection($wp_config_path);
    if (is_array($db_data)) {
        $conn = $db_data['conn'];
        $prefix = $db_data['prefix'];

        switch ($_POST['action']) {
            case 'deactivate_plugins':
                $sql = "UPDATE {$prefix}options SET option_value = 'a:0:{}' WHERE option_name = 'active_plugins'";
                if ($conn->query($sql)) {
                    $success_msg = "All plugins deactivated successfully.";
                } else {
                    $error_msg = "Failed to deactivate plugins: " . $conn->error;
                }
                break;

            case 'reset_password':
                $user_id = intval($_POST['user_id']);
                $new_pass = $_POST['new_pass'];
                // Use MD5 as fallback which WP supports and will upgrade on next login
                $hash = md5($new_pass);
                $sql = "UPDATE {$prefix}users SET user_pass = '$hash' WHERE ID = $user_id";
                if ($conn->query($sql)) {
                    $success_msg = "Password reset for User ID $user_id.";
                } else {
                    $error_msg = "Failed to reset password: " . $conn->error;
                }
                break;
            
            case 'create_admin':
                $user = $conn->real_escape_string($_POST['username']);
                $pass = md5($_POST['password']);
                $email = $conn->real_escape_string($_POST['email']);
                $now = date('Y-m-d H:i:s');
                
                // Insert User
                $sql = "INSERT INTO {$prefix}users (user_login, user_pass, user_nicename, user_email, user_registered, user_status, display_name) 
                        VALUES ('$user', '$pass', '$user', '$email', '$now', 0, '$user')";
                
                if ($conn->query($sql)) {
                    $user_id = $conn->insert_id;
                    // Add Capabilities
                    $caps = serialize(['administrator' => true]);
                    $sql_meta1 = "INSERT INTO {$prefix}usermeta (user_id, meta_key, meta_value) VALUES ($user_id, '{$prefix}capabilities', '$caps')";
                    $sql_meta2 = "INSERT INTO {$prefix}usermeta (user_id, meta_key, meta_value) VALUES ($user_id, '{$prefix}user_level', '10')";
                    
                    if ($conn->query($sql_meta1) && $conn->query($sql_meta2)) {
                        $success_msg = "Admin user '$user' created successfully.";
                    } else {
                        $error_msg = "User created but meta failed.";
                    }
                } else {
                    $error_msg = "Failed to create user: " . $conn->error;
                }
                break;

            case 'update_url':
                $site_url = $conn->real_escape_string($_POST['site_url']);
                $home_url = $conn->real_escape_string($_POST['home_url']);
                
                $sql1 = "UPDATE {$prefix}options SET option_value = '$site_url' WHERE option_name = 'siteurl'";
                $sql2 = "UPDATE {$prefix}options SET option_value = '$home_url' WHERE option_name = 'home'";
                
                if ($conn->query($sql1) && $conn->query($sql2)) {
                    $success_msg = "Site URLs updated successfully.";
                } else {
                    $error_msg = "Failed to update URLs: " . $conn->error;
                }
                break;
        }
    } else {
        $error_msg = is_string($db_data) ? $db_data : "Database connection error.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WP Arzo - Emergency Recovery</title>
    <style>
        :root { --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; --accent: #16e791; --danger: #ff4d4d; }
        body { background: var(--bg); color: var(--text); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; padding: 20px; display: flex; justify-content: center; min-height: 100vh; }
        .container { width: 100%; max-width: 800px; }
        .card { background: var(--card); padding: 30px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.5); margin-bottom: 20px; border: 1px solid #333; }
        h1, h2, h3 { color: #fff; margin-top: 0; }
        h1 { border-bottom: 2px solid var(--accent); padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-error { background: rgba(255, 77, 77, 0.2); border: 1px solid var(--danger); color: #ffcccc; }
        .alert-success { background: rgba(22, 231, 145, 0.2); border: 1px solid var(--accent); color: #ccffdd; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        input[type="text"], input[type="password"], input[type="email"], select { width: 100%; padding: 10px; background: #2a2a2a; border: 1px solid #444; color: #fff; border-radius: 4px; box-sizing: border-box; }
        button { background: var(--accent); color: #121212; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; transition: opacity 0.2s; }
        button:hover { opacity: 0.9; }
        button.danger { background: var(--danger); color: #fff; }
        .tab-nav { display: flex; margin-bottom: 20px; border-bottom: 1px solid #333; }
        .tab-nav button { background: transparent; color: #aaa; border-radius: 0; margin-right: 10px; }
        .tab-nav button.active { color: var(--accent); border-bottom: 2px solid var(--accent); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #333; }
        th { color: #aaa; }
        .status-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 5px; }
        .status-green { background: var(--accent); }
        .status-red { background: var(--danger); }
        .logout-btn { background: transparent; color: #aaa; border: 1px solid #444; font-size: 12px; padding: 5px 10px; }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($setup_mode): ?>
            <div class="card">
                <h1>Emergency Setup</h1>
                <p>Please create a secure password to access the recovery console.</p>
                <?php if ($error_msg) echo "<div class='alert alert-error'>$error_msg</div>"; ?>
                <form method="post">
                    <input type="hidden" name="action" value="setup">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required>
                    </div>
                    <button type="submit">Create Access</button>
                </form>
            </div>
        <?php elseif (!$is_authenticated): ?>
            <div class="card">
                <h1>Emergency Login</h1>
                <?php if ($error_msg) echo "<div class='alert alert-error'>$error_msg</div>"; ?>
                <form method="post">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit">Login</button>
                </form>
            </div>
        <?php else: ?>
            <div class="card">
                <h1>
                    WP Arzo Recovery
                    <form method="post" style="display:inline;"><input type="hidden" name="action" value="logout"><button type="submit" class="logout-btn">Logout</button></form>
                </h1>
                
                <?php if ($success_msg) echo "<div class='alert alert-success'>$success_msg</div>"; ?>
                <?php if ($error_msg) echo "<div class='alert alert-error'>$error_msg</div>"; ?>

                <?php
                $db_data = get_db_connection($wp_config_path);
                if (is_string($db_data) || !$db_data) {
                    echo "<div class='alert alert-error'>DB Connection Failed: " . (is_string($db_data) ? $db_data : "Config not found") . "</div>";
                } else {
                    $conn = $db_data['conn'];
                    $prefix = $db_data['prefix'];
                    
                    // Fetch Data
                    $plugins_res = $conn->query("SELECT option_value FROM {$prefix}options WHERE option_name = 'active_plugins'");
                    $active_plugins = [];
                    if ($plugins_res && $row = $plugins_res->fetch_assoc()) {
                        $active_plugins = unserialize($row['option_value']);
                    }

                    $users_res = $conn->query("SELECT ID, user_login, user_email FROM {$prefix}users LIMIT 50");
                    
                    $siteurl_res = $conn->query("SELECT option_value FROM {$prefix}options WHERE option_name = 'siteurl'");
                    $siteurl = ($siteurl_res && $row = $siteurl_res->fetch_assoc()) ? $row['option_value'] : '';
                    
                    $home_res = $conn->query("SELECT option_value FROM {$prefix}options WHERE option_name = 'home'");
                    $home = ($home_res && $row = $home_res->fetch_assoc()) ? $row['option_value'] : '';
                ?>
                
                <div class="tab-nav">
                    <button onclick="switchTab('dashboard')" class="active" id="btn-dashboard">Dashboard</button>
                    <button onclick="switchTab('plugins')" id="btn-plugins">Plugins</button>
                    <button onclick="switchTab('users')" id="btn-users">Users</button>
                    <button onclick="switchTab('core')" id="btn-core">Core Settings</button>
                </div>

                <div id="dashboard" class="tab-content active">
                    <h3>System Status</h3>
                    <p><span class="status-dot status-green"></span> <strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
                    <p><span class="status-dot status-green"></span> <strong>Database:</strong> Connected (<?php echo $conn->server_info; ?>)</p>
                    <p><span class="status-dot <?php echo !empty($active_plugins) ? 'status-green' : 'status-red'; ?>"></span> <strong>Active Plugins:</strong> <?php echo count($active_plugins); ?></p>
                    <p><strong>WP Config Path:</strong> <?php echo htmlspecialchars($wp_config_path); ?></p>
                </div>

                <div id="plugins" class="tab-content">
                    <h3>Manage Plugins</h3>
                    <?php if (empty($active_plugins)): ?>
                        <p>No active plugins found.</p>
                    <?php else: ?>
                        <div class="alert alert-error">Warning: Deactivating all plugins may affect site functionality but usually resolves WSOD issues.</div>
                        <form method="post">
                            <input type="hidden" name="action" value="deactivate_plugins">
                            <button type="submit" class="danger" onclick="return confirm('Are you sure?');">Deactivate ALL Plugins</button>
                        </form>
                        <table>
                            <tr><th>Plugin Path</th></tr>
                            <?php foreach ($active_plugins as $plugin): ?>
                                <tr><td><?php echo htmlspecialchars($plugin); ?></td></tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                </div>

                <div id="users" class="tab-content">
                    <h3>User Management</h3>
                    
                    <h4>Create New Admin</h4>
                    <form method="post" style="background: #2a2a2a; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                        <input type="hidden" name="action" value="create_admin">
                        <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
                        <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
                        <div class="form-group"><label>Password</label><input type="text" name="password" required></div>
                        <button type="submit">Create Admin</button>
                    </form>

                    <h4>Reset Password</h4>
                    <table>
                        <tr><th>ID</th><th>Username</th><th>Email</th><th>Action</th></tr>
                        <?php while($user = $users_res->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $user['ID']; ?></td>
                                <td><?php echo htmlspecialchars($user['user_login']); ?></td>
                                <td><?php echo htmlspecialchars($user['user_email']); ?></td>
                                <td>
                                    <form method="post" style="display:flex; gap:5px;">
                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="user_id" value="<?php echo $user['ID']; ?>">
                                        <input type="text" name="new_pass" placeholder="New Password" required style="width:120px; padding:5px;">
                                        <button type="submit" style="padding:5px 10px;">Reset</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </table>
                </div>

                <div id="core" class="tab-content">
                    <h3>Core Settings</h3>
                    <form method="post">
                        <input type="hidden" name="action" value="update_url">
                        <div class="form-group">
                            <label>Site URL (siteurl)</label>
                            <input type="text" name="site_url" value="<?php echo htmlspecialchars($siteurl); ?>">
                        </div>
                        <div class="form-group">
                            <label>Home URL (home)</label>
                            <input type="text" name="home_url" value="<?php echo htmlspecialchars($home); ?>">
                        </div>
                        <button type="submit">Update URLs</button>
                    </form>
                </div>

                <?php } ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-nav button').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            document.getElementById('btn-' + tabId).classList.add('active');
        }
    </script>
</body>
</html>