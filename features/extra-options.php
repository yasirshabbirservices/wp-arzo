<?php

/**
 * Extra Options Feature (PHP Limits Configuration)
 *
 * @package WP_Arzo
 * @version 6.0
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// --- Helper Functions for PHP Limits ---

// Validate a PHP "size" shorthand value (e.g. 128M, 512K, 2G, 64, -1).
function wp_arzo_is_valid_php_size($value)
{
    return (bool) preg_match('/^-?\d+[KMG]?$/i', trim((string) $value));
}

// Validate a plain integer value (e.g. max_execution_time in seconds).
function wp_arzo_is_valid_php_int($value)
{
    return (bool) preg_match('/^-?\d+$/', trim((string) $value));
}

// Validate the full set of submitted limit values before any file write.
// Prevents config/.htaccess/php.ini injection via newlines or stray directives.
function wp_arzo_validate_php_limits($memory_limit, $max_execution_time, $upload_max_filesize, $post_max_size)
{
    return wp_arzo_is_valid_php_size($memory_limit)
        && wp_arzo_is_valid_php_int($max_execution_time)
        && wp_arzo_is_valid_php_size($upload_max_filesize)
        && wp_arzo_is_valid_php_size($post_max_size);
}

// Function to update PHP limits in wp-config.php
function updateWpConfigPhpLimits($wp_config_path, $memory_limit, $max_execution_time, $upload_max_filesize, $post_max_size)
{
    if (!file_exists($wp_config_path) || !is_writable($wp_config_path)) {
        return false;
    }

    $config_content = file_get_contents($wp_config_path);

    // Define PHP limit settings
    $php_limit_settings = [
        'WP_MEMORY_LIMIT' => $memory_limit,
        'WP_MAX_MEMORY_LIMIT' => $memory_limit
    ];

    // Update or add PHP limit settings
    foreach ($php_limit_settings as $setting => $value) {
        $define_pattern = "/define\s*\(\s*['\"]" . $setting . "['\"]\s*,\s*[^)]+\s*\)\s*;/";
        $new_define = "define('" . $setting . "', '" . $value . "');";

        if (preg_match($define_pattern, $config_content)) {
            // Replace existing define
            $config_content = preg_replace($define_pattern, $new_define, $config_content);
        } else {
            // Add new define before the "That's all" comment or at the end
            $insert_position = strpos($config_content, "/* That's all, stop editing!");
            if ($insert_position === false) {
                $insert_position = strpos($config_content, "?>");
            }
            if ($insert_position !== false) {
                $config_content = substr_replace($config_content, $new_define . "\n\n", $insert_position, 0);
            } else {
                $config_content .= "\n" . $new_define;
            }
        }
    }

    // Add custom PHP settings comment if not exists
    if (strpos($config_content, '/* Custom PHP Settings */') === false) {
        $insert_position = strpos($config_content, "/* That's all, stop editing!");
        if ($insert_position === false) {
            $insert_position = strpos($config_content, "?>");
        }
        if ($insert_position !== false) {
            $custom_settings = "\n/* Custom PHP Settings */\n";
            $config_content = substr_replace($config_content, $custom_settings, $insert_position, 0);
        }
    }

    // Write the updated config
    return file_put_contents($wp_config_path, $config_content) !== false;
}

// Function to update PHP limits in .htaccess
function updateHtaccessPhpLimits(
    $htaccess_path,
    $memory_limit,
    $max_execution_time,
    $upload_max_filesize,
    $post_max_size
) {
    // Create file if it doesn't exist
    if (!file_exists($htaccess_path)) {
        @touch($htaccess_path);
    }

    if (!is_writable($htaccess_path)) {
        return false;
    }

    $htaccess_content = file_exists($htaccess_path) ? file_get_contents($htaccess_path) : '';

    // Remove existing PHP limits section if it exists
    $htaccess_content = preg_replace('/\n?# BEGIN PHP Limits.*?# END PHP Limits\n?/s', "\n", $htaccess_content);

    // Create new PHP limits section
    $php_limits = "\n# BEGIN PHP Limits\n";
    $php_limits .= "<IfModule mod_php7.c>\n";
    $php_limits .= " php_value memory_limit {$memory_limit}\n";
    $php_limits .= " php_value max_execution_time {$max_execution_time}\n";
    $php_limits .= " php_value upload_max_filesize {$upload_max_filesize}\n";
    $php_limits .= " php_value post_max_size {$post_max_size}\n";
    $php_limits .= "</IfModule>\n";
    $php_limits .= "<IfModule mod_php.c>\n";
    $php_limits .= " php_value memory_limit {$memory_limit}\n";
    $php_limits .= " php_value max_execution_time {$max_execution_time}\n";
    $php_limits .= " php_value upload_max_filesize {$upload_max_filesize}\n";
    $php_limits .= " php_value post_max_size {$post_max_size}\n";
    $php_limits .= "</IfModule>\n";
    $php_limits .= "# END PHP Limits\n";

    // Append PHP limits section to .htaccess
    $htaccess_content .= $php_limits;

    // Write the updated .htaccess
    return file_put_contents($htaccess_path, $htaccess_content) !== false;
}

// Function to update PHP limits in php.ini
function updatePhpIniLimits($php_ini_path, $memory_limit, $max_execution_time, $upload_max_filesize, $post_max_size)
{
    // Create file if it doesn't exist
    if (!file_exists($php_ini_path)) {
        @touch($php_ini_path);
    }

    if (!is_writable($php_ini_path)) {
        return false;
    }

    $php_ini_content = file_exists($php_ini_path) ? file_get_contents($php_ini_path) : '';

    // Define PHP settings to update
    $php_settings = [
        'memory_limit' => $memory_limit,
        'max_execution_time' => $max_execution_time,
        'upload_max_filesize' => $upload_max_filesize,
        'post_max_size' => $post_max_size
    ];

    // Update or add each PHP setting
    foreach ($php_settings as $setting => $value) {
        $pattern = '/^\s*' . preg_quote($setting) . '\s*=.*$/m';
        $replacement = $setting . ' = ' . $value;

        if (preg_match($pattern, $php_ini_content)) {
            // Replace existing setting
            $php_ini_content = preg_replace($pattern, $replacement, $php_ini_content);
        } else {
            // Add new setting
            $php_ini_content .= "\n" . $replacement;
        }
    }

    // Write the updated php.ini
    return file_put_contents($php_ini_path, $php_ini_content) !== false;
}

// --- Main Feature Function ---

function handleExtraOptions()
{
    // Define paths to configuration files
    $wp_config_path = ABSPATH . 'wp-config.php';
    $htaccess_path = ABSPATH . '.htaccess';
    $php_ini_path = ABSPATH . 'php.ini';

    // Check if files exist and are writable
    $wp_config_exists = file_exists($wp_config_path);
    $wp_config_writable = $wp_config_exists && is_writable($wp_config_path);
    $htaccess_exists = file_exists($htaccess_path);
    $htaccess_writable = $htaccess_exists && is_writable($htaccess_path);
    $php_ini_exists = file_exists($php_ini_path);
    $php_ini_writable = $php_ini_exists && is_writable($php_ini_path);

    // If php.ini doesn't exist, create an empty one
    if (!$php_ini_exists) {
        @touch($php_ini_path);
        $php_ini_exists = file_exists($php_ini_path);
        $php_ini_writable = $php_ini_exists && is_writable($php_ini_path);
    }

    // Get current PHP limits
    $memory_limit = ini_get('memory_limit');
    $max_execution_time = ini_get('max_execution_time');
    $upload_max_filesize = ini_get('upload_max_filesize');
    $post_max_size = ini_get('post_max_size');

    // Define default PHP limits
    $default_memory_limit = '128M';
    $default_max_execution_time = '30';
    $default_upload_max_filesize = '64M';
    $default_post_max_size = '64M';

    // CSRF guard for both state-changing actions (these rewrite server config files).
    $is_limits_action = isset($_POST['reset_php_limits']) || isset($_POST['update_php_limits']);
    if ($is_limits_action) {
        if (!current_user_can('manage_options') ||
            !isset($_POST['wp_arzo_php_nonce']) ||
            !wp_verify_nonce(wp_unslash($_POST['wp_arzo_php_nonce']), 'wp_arzo_php_limits')) {
            echo "<div class='error'>Security check failed. Please reload the page and try again.</div>";
            $is_limits_action = false; // skip the handlers below
        }
    }

    $allowed_targets = ['wp-config', 'htaccess', 'php-ini'];

    // Handle reset settings
    if ($is_limits_action && isset($_POST['reset_php_limits'])) {
        $target_file = in_array($_POST['target_file'] ?? '', $allowed_targets, true) ? $_POST['target_file'] : '';

        // Log the reset action
        error_log("PHP Limits reset requested. Target file: {$target_file}");

        $update_success = false;
        $update_message = '';

        switch ($target_file) {
            case 'wp-config':
                if ($wp_config_writable) {
                    $update_success = updateWpConfigPhpLimits($wp_config_path, $default_memory_limit, $default_max_execution_time, $default_upload_max_filesize, $default_post_max_size);
                    $update_message = $update_success ? 'PHP limits reset to defaults in wp-config.php' : 'Failed to reset wp-config.php';
                } else {
                    $update_message = 'wp-config.php is not writable';
                }
                break;

            case 'htaccess':
                if ($htaccess_writable) {
                    $update_success = updateHtaccessPhpLimits($htaccess_path, $default_memory_limit, $default_max_execution_time, $default_upload_max_filesize, $default_post_max_size);
                    $update_message = $update_success ? 'PHP limits reset to defaults in .htaccess' : 'Failed to reset .htaccess';
                } else {
                    $update_message = '.htaccess is not writable';
                }
                break;

            case 'php-ini':
                if ($php_ini_writable) {
                    $update_success = updatePhpIniLimits($php_ini_path, $default_memory_limit, $default_max_execution_time, $default_upload_max_filesize, $default_post_max_size);
                    $update_message = $update_success ? 'PHP limits reset to defaults in php.ini' : 'Failed to reset php.ini';
                } else {
                    $update_message = 'php.ini is not writable';
                }
                break;
        }

        // Log the result
        error_log("PHP Limits reset result: {$update_message}");

        // Display message
        echo $update_success ?
            "<div class='success'>{$update_message}. Changes may require server restart to take effect.</div>" :
            "<div class='error'>{$update_message}. Check file permissions.</div>";

        // Update current values to show defaults
        if ($update_success) {
            $memory_limit = $default_memory_limit;
            $max_execution_time = $default_max_execution_time;
            $upload_max_filesize = $default_upload_max_filesize;
            $post_max_size = $default_post_max_size;
        }
    }

    // Handle form submission for updates
    if ($is_limits_action && isset($_POST['update_php_limits'])) {
        $target_file = in_array($_POST['target_file'] ?? '', $allowed_targets, true) ? $_POST['target_file'] : '';
        $new_memory_limit = trim(wp_unslash($_POST['memory_limit'] ?? ''));
        $new_max_execution_time = trim(wp_unslash($_POST['max_execution_time'] ?? ''));
        $new_upload_max_filesize = trim(wp_unslash($_POST['upload_max_filesize'] ?? ''));
        $new_post_max_size = trim(wp_unslash($_POST['post_max_size'] ?? ''));

        // Reject anything that isn't a clean size/integer value before writing files.
        if (!$target_file || !wp_arzo_validate_php_limits($new_memory_limit, $new_max_execution_time, $new_upload_max_filesize, $new_post_max_size)) {
            echo "<div class='error'>Invalid values. Use formats like 512M / 64M and a whole number of seconds.</div>";
            $skip_update = true;
        }

        if (empty($skip_update)) {
            // Log the action
            error_log("PHP Limits update requested. Target file: {$target_file}");
            error_log("New values - Memory: {$new_memory_limit}, Execution: {$new_max_execution_time}, Upload: {$new_upload_max_filesize}, Post: {$new_post_max_size}");

            $update_success = false;
            $update_message = '';

            switch ($target_file) {
                case 'wp-config':
                    if ($wp_config_writable) {
                        $update_success = updateWpConfigPhpLimits($wp_config_path, $new_memory_limit, $new_max_execution_time, $new_upload_max_filesize, $new_post_max_size);
                        // wp-config.php can only persist the memory constants; the other
                        // three limits cannot be set via PHP constants.
                        $update_message = $update_success
                            ? 'Memory limit updated in wp-config.php. Note: execution time, upload and post size cannot be set via wp-config.php — use .htaccess or php.ini for those.'
                            : 'Failed to update wp-config.php';
                    } else {
                        $update_message = 'wp-config.php is not writable';
                    }
                    break;

                case 'htaccess':
                    if ($htaccess_writable) {
                        $update_success = updateHtaccessPhpLimits($htaccess_path, $new_memory_limit, $new_max_execution_time, $new_upload_max_filesize, $new_post_max_size);
                        $update_message = $update_success ? 'PHP limits updated in .htaccess' : 'Failed to update .htaccess';
                    } else {
                        $update_message = '.htaccess is not writable';
                    }
                    break;

                case 'php-ini':
                    if ($php_ini_writable) {
                        $update_success = updatePhpIniLimits($php_ini_path, $new_memory_limit, $new_max_execution_time, $new_upload_max_filesize, $new_post_max_size);
                        $update_message = $update_success ? 'PHP limits updated in php.ini' : 'Failed to update php.ini';
                    } else {
                        $update_message = 'php.ini is not writable';
                    }
                    break;
            }

            // Log the result
            error_log("PHP Limits update result: {$update_message}");

            // Display message
            echo $update_success ?
                "<div class='success'>" . esc_html($update_message) . " Changes may require server restart to take effect.</div>" :
                "<div class='error'>" . esc_html($update_message) . ". Check file permissions.</div>";
        }
    }

?>
    <div class="content">
        <h2>Extra Options</h2>

        <div
            style="background: #2A2A2A; padding: 20px; border-radius: var(--radius-global); border: 1px solid #333333; margin-bottom: 20px;">
            <h3>PHP Limits Configuration</h3>
            <p style="color: #999; margin-bottom: 20px;">Modify PHP limits by updating configuration files. Choose which
                file to update based on your server setup.</p>

            <style>
                .php-limits-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 15px;
                    margin-bottom: 20px;
                }

                .php-limits-input {
                    background: #151515;
                    border: 1px solid #444;
                    color: #fff;
                    padding: 10px;
                    border-radius: 4px;
                    width: 100%;
                }

                .php-limits-input:focus {
                    border-color: var(--accent-color);
                    outline: none;
                }

                .form-group label {
                    color: #ccc;
                    font-size: 13px;
                    margin-bottom: 5px;
                    display: block;
                }

                .modern-select {
                    background: #151515;
                    border: 1px solid #444;
                    color: #fff;
                    padding: 10px;
                    border-radius: 4px;
                    width: 100%;
                }
            </style>

            <form method="post">
                <?php wp_nonce_field('wp_arzo_php_limits', 'wp_arzo_php_nonce'); ?>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Target Configuration File</label>
                    <select name="target_file" class="modern-select" required>
                        <option value="wp-config" <?php echo $wp_config_writable ? '' : 'disabled'; ?>>wp-config.php
                            <?php echo $wp_config_writable ? '' : '(Not writable)'; ?>
                        </option>
                        <option value="htaccess" <?php echo $htaccess_writable ? '' : 'disabled'; ?>>.htaccess
                            <?php echo $htaccess_writable ? '' : '(Not writable)'; ?>
                        </option>
                        <option value="php-ini" <?php echo $php_ini_writable ? '' : 'disabled'; ?>>php.ini
                            <?php echo $php_ini_writable ? '' : '(Not writable)'; ?>
                        </option>
                    </select>
                </div>

                <div class="php-limits-grid">
                    <div class="form-group">
                        <label>Memory Limit (e.g. 512M)</label>
                        <input type="text" name="memory_limit" value="<?php echo $memory_limit; ?>" class="php-limits-input"
                            required>
                    </div>

                    <div class="form-group">
                        <label>Max Execution Time (seconds)</label>
                        <input type="number" name="max_execution_time" value="<?php echo $max_execution_time; ?>"
                            class="php-limits-input" required>
                    </div>

                    <div class="form-group">
                        <label>Upload Max Filesize (e.g. 64M)</label>
                        <input type="text" name="upload_max_filesize" value="<?php echo $upload_max_filesize; ?>"
                            class="php-limits-input" required>
                    </div>

                    <div class="form-group">
                        <label>Post Max Size (e.g. 64M)</label>
                        <input type="text" name="post_max_size" value="<?php echo $post_max_size; ?>"
                            class="php-limits-input" required>
                    </div>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="update_php_limits" class="btn" style="flex: 1;">Update Limits</button>
                    <button type="submit" name="reset_php_limits" class="btn"
                        style="background-color: transparent; border: 1px solid #444; color: #999; flex:1;"
                        onclick="return confirm('Are you sure you want to reset to default PHP limits?');"
                        onmouseover="this.style.borderColor='#fff'; this.style.color='#fff';"
                        onmouseout="this.style.borderColor='#444'; this.style.color='#999';">
                        Reset to Defaults
                    </button>
                </div>
            </form>
        </div>

        <?php if (file_exists(WP_CONTENT_DIR . '/debug.log')): ?>
            <div
                style="background: #2A2A2A; padding: 20px; border-radius: var(--radius-global); border: 1px solid #333333; margin-top: 20px; position: relative;">
                <h3>PHP Limits Update Log</h3>
                <div style="position: absolute; top: 20px; right: 20px;">
                    <i class="fas fa-copy" onclick="copyDebugLog()"
                        style="cursor: pointer; margin-right: 10px; color: var(--accent-color);" title="Copy debug log"></i>
                    <i class="fas fa-trash-alt" onclick="clearDebugLog()" style="cursor: pointer; color: var(--danger-color);"
                        title="Clear debug log"></i>
                </div>
                <div id="debug-log-content"
                    style="background: #1a1a1a; padding: 15px; border-radius: var(--radius-global); max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px; line-height: 1.4;">
                    <?php
                    $log_content = file_get_contents(WP_CONTENT_DIR . '/debug.log');
                    $log_lines = explode("\n", $log_content);
                    $php_limits_lines = [];

                    // Filter lines related to PHP limits
                    foreach ($log_lines as $line) {
                        if (stripos($line, 'PHP Limits') !== false) {
                            $php_limits_lines[] = $line;
                        }
                    }

                    // Show last 50 PHP limits related lines
                    $recent_lines = array_slice($php_limits_lines, -50);

                    if (empty($recent_lines)) {
                        echo '<div style="color: #999;">No PHP limits update logs found.</div>';
                    } else {
                        foreach ($recent_lines as $line) {
                            if (empty(trim($line)))
                                continue;

                            $line = htmlspecialchars($line);
                            $color = '#fff'; // default white
                            $border_left = 'none';

                            // Detect log type and apply colors
                            if (stripos($line, 'update requested') !== false) {
                                $color = '#17a2b8'; // blue
                                $border_left = '3px solid #17a2b8';
                            } elseif (stripos($line, 'update result') !== false) {
                                if (stripos($line, 'Failed') !== false || stripos($line, 'not writable') !== false) {
                                    $color = '#dc3545'; // red
                                    $border_left = '3px solid #dc3545';
                                } else {
                                    $color = '#28a745'; // green
                                    $border_left = '3px solid #28a745';
                                }
                            }

                            echo '<div style="color: ' . $color . '; margin-bottom: 2px; padding: 2px 8px; border-left: ' . $border_left . '; padding-left: ' . ($border_left !== 'none' ? '12px' : '8px') . ';">' . $line . '</div>';
                        }
                    }
                    ?>
                </div>
                <p style="margin-top: 10px; font-size: 12px; color: #999;">
                    Showing PHP limits related log entries. Full log:
                    <?php echo WP_CONTENT_DIR . '/debug.log'; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
<?php
}

// Call the function
handleExtraOptions();
