<?php
/**
 * Debug Log Feature
 *
 * @package WP_Arzo
 * @version 5.1
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Handle AJAX operations for debug log
if (isset($_GET['operation'])) {
    $operation = $_GET['operation'];
    $response = ['success' => false, 'message' => 'Unknown operation'];
    
    // Only handle debug specific operations here (if routed correctly)
    // Actually wp-arzo-modular.php routes 'debug' tab and specific operations here
    // But we need to check the operation name if we want to be safe, or if we rely on the loader
    
    if ($operation === 'clear_debug_log') {
        header('Content-Type: application/json');
        
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
        
        echo json_encode($response);
        exit;
    }
    
    if ($operation === 'log_debug_change') {
        header('Content-Type: application/json');
        
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
        
        echo json_encode($response);
        exit;
    }
}

function handleDebug()
{
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
            echo '<script>
            setTimeout(function() {
                location.reload();
            }, 2000);
            </script>';
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
        <strong>Warning:</strong> wp-config.php is not writable. You'll need to manually edit the file or change
        permissions.
        <br><strong>File location:</strong> <?php echo $wp_config_path; ?>
    </div>
    <?php endif; ?>



    <?php if ($config_writable): ?>
    <form method="post" style="background: #2A2A2A; padding: 20px; border-radius: 3px; border: 1px solid #333333;">
        <h3>Update Debug Settings</h3>
        <p style="color: #999; margin-bottom: 20px;">Configure WordPress debug settings. Changes will be written to
            wp-config.php.</p>

        <div class="debug-settings-grid"
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px 15px; margin-bottom: 20px;">
            <?php foreach ($debug_settings as $setting => $info): ?>
            <div class="form-group"
                style="display: flex; align-items: center; justify-content: space-between; padding: 12px 15px; background: #1a1a1a; border-radius: 5px; border: 1px solid #333;">
                <div style="flex: 1; min-width: 0;">
                    <label
                        style="display: block; margin-bottom: 3px; font-weight: bold; color: #fff; font-size: 14px;"><?php echo $setting; ?></label>
                    <p
                        style="font-size: 11px; color: #999; margin: 0; line-height: 1.3; overflow: hidden; text-overflow: ellipsis;">
                        <?php echo $info['description']; ?></p>
                </div>
                <div style="margin-left: 15px; flex-shrink: 0;">
                    <label class="switch">
                        <input type="checkbox" name="<?php echo $setting; ?>" value="true"
                            <?php echo ($info['current'] === 'true') ? 'checked' : ''; ?>
                            onchange="logDebugChange('<?php echo $setting; ?>', this.checked)">
                        <span class="slider round"></span>
                    </label>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <button type="submit" name="update_debug_settings" class="btn" style="margin-top: 15px;">Update Debug
            Settings</button>
    </form>
    <?php endif; ?>

    <div style="background: #2A2A2A; padding: 20px; border-radius: 3px; border: 1px solid #333333; margin-top: 20px;">
        <h3>Debug Information</h3>
        <table>
            <tr>
                <th>Item</th>
                <th>Value</th>
            </tr>
            <?php if (function_exists('wp_arzo_get_plugin_debug_info')):
                $plugin_debug = wp_arzo_get_plugin_debug_info();
                ?>
            <tr>
                <td>WP Arzo Plugin File</td>
                <td><?php echo esc_html($plugin_debug['plugin_file']); ?></td>
            </tr>
            <tr>
                <td>WP Arzo Plugin Directory</td>
                <td><?php echo esc_html($plugin_debug['plugin_dir']); ?></td>
            </tr>
            <tr>
                <td>WP Arzo Plugin URL</td>
                <td><?php echo esc_html($plugin_debug['plugin_url']); ?></td>
            </tr>
            <tr>
                <td>WP Arzo Version (Header)</td>
                <td><?php echo esc_html($plugin_debug['version_header']); ?></td>
            </tr>
            <tr>
                <td>WP Arzo Version (Stored)</td>
                <td><?php echo esc_html($plugin_debug['version_stored']); ?></td>
            </tr>
            <tr>
                <td>WP Arzo Debug Mode</td>
                <td><?php echo esc_html($plugin_debug['debug_mode']); ?></td>
            </tr>
            <tr>
                <td>OPcache Enabled</td>
                <td><?php echo !empty($plugin_debug['opcache_enabled']) ? 'Yes' : 'No'; ?></td>
            </tr>
            <?php if (!empty($plugin_debug['opcache_script'])): ?>
            <tr>
                <td>OPcache: Plugin File Cached</td>
                <td><?php echo !empty($plugin_debug['opcache_script']['file_cached']) ? 'Yes' : 'No'; ?></td>
            </tr>
            <tr>
                <td>OPcache: File mtime</td>
                <td><?php echo !empty($plugin_debug['opcache_script']['file_mtime']) ? date('Y-m-d H:i:s', $plugin_debug['opcache_script']['file_mtime']) : 'N/A'; ?>
                </td>
            </tr>
            <tr>
                <td>OPcache: Compiled Timestamp</td>
                <td><?php echo !empty($plugin_debug['opcache_script']['opcache_timestamp']) ? date('Y-m-d H:i:s', $plugin_debug['opcache_script']['opcache_timestamp']) : 'N/A'; ?>
                </td>
            </tr>
            <tr>
                <td>OPcache: Last Used</td>
                <td><?php echo !empty($plugin_debug['opcache_script']['opcache_last_used']) ? date('Y-m-d H:i:s', $plugin_debug['opcache_script']['opcache_last_used']) : 'N/A'; ?>
                </td>
            </tr>
            <?php endif; ?>
            <?php endif; ?>
            <tr>
                <td>Debug Log File</td>
                <td><?php echo WP_CONTENT_DIR . '/debug.log'; ?></td>
            </tr>
            <tr>
                <td>Log File Exists</td>
                <td><?php echo file_exists(WP_CONTENT_DIR . '/debug.log') ? 'Yes' : 'No'; ?></td>
            </tr>
            <tr>
                <td>Log File Size</td>
                <td>
                    <?php
                        $log_file = WP_CONTENT_DIR . '/debug.log';
                        if (file_exists($log_file)) {
                            $size = filesize($log_file);
                            echo $size > 0 ? size_format($size) : '0 bytes';
                        } else {
                            echo 'N/A';
                        }
                        ?>
                </td>
            </tr>
            <tr>
                <td>Error Reporting Level</td>
                <td><?php echo error_reporting(); ?></td>
            </tr>
            <tr>
                <td>Display Errors</td>
                <td><?php echo ini_get('display_errors') ? 'On' : 'Off'; ?></td>
            </tr>
            <tr>
                <td>Log Errors</td>
                <td><?php echo ini_get('log_errors') ? 'On' : 'Off'; ?></td>
            </tr>
        </table>
    </div>

    <?php if (file_exists(WP_CONTENT_DIR . '/debug.log')): ?>
    <div
        style="background: #2A2A2A; padding: 20px; border-radius: 3px; border: 1px solid #333333; margin-top: 20px; position: relative;">
        <h3>Recent Debug Log Entries</h3>
        <div style="position: absolute; top: 20px; right: 20px;">
            <i class="fas fa-copy" onclick="copyDebugLog()"
                style="cursor: pointer; margin-right: 10px; color: var(--accent-color);" title="Copy debug log"></i>
            <i class="fas fa-trash-alt" onclick="clearDebugLog()" style="cursor: pointer; color: var(--danger-color);"
                title="Clear debug log"></i>
        </div>
        <div id="debug-log-content"
            style="background: #1a1a1a; padding: 15px; border-radius: 3px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px; line-height: 1.4;">
            <?php
                    $log_content = file_get_contents(WP_CONTENT_DIR . '/debug.log');
                    $log_lines = explode("\n", $log_content);
                    $recent_lines = array_slice($log_lines, -50); // Show last 50 lines

                    foreach ($recent_lines as $line) {
                        if (empty(trim($line))) continue;

                        $line = htmlspecialchars($line);
                        $color = '#fff'; // default white
                        $bg_color = 'transparent';
                        $border_left = 'none';

                        // Detect log type and apply colors
                        if (stripos($line, 'warning') !== false || stripos($line, 'warn') !== false) {
                            $color = '#ffc107'; // yellow
                            $border_left = '3px solid #ffc107';
                        } elseif (stripos($line, 'deprecated') !== false) {
                            $color = '#ff9800'; // orange  
                            $border_left = '3px solid #ff9800';
                        } elseif (stripos($line, 'error') !== false || stripos($line, 'fatal') !== false) {
                            $color = '#dc3545'; // red
                            $border_left = '3px solid #dc3545';
                        } elseif (stripos($line, 'notice') !== false || stripos($line, 'info') !== false) {
                            $color = '#17a2b8'; // blue
                            $border_left = '3px solid #17a2b8';
                        } elseif (stripos($line, 'login') !== false || stripos($line, 'performed') !== false) {
                            $color = '#28a745'; // green for login/activity messages
                            $border_left = '3px solid #28a745';
                        } elseif (preg_match('/\[\d{4}-\d{2}-\d{2}/', $line)) {
                            // Date/timestamp lines
                            $color = '#6c757d'; // gray
                        }

                        echo '<div style="color: ' . $color . '; margin-bottom: 2px; padding: 2px 8px; border-left: ' . $border_left . '; padding-left: ' . ($border_left !== 'none' ? '12px' : '8px') . ';">' . $line . '</div>';
                    }
                    ?>
        </div>
        <p style="margin-top: 10px; font-size: 12px; color: #999;">
            Showing last 50 lines. Full log: <?php echo WP_CONTENT_DIR . '/debug.log'; ?>
        </p>
    </div>
    <?php endif; ?>


</div>
<?php
}

// Call the function
handleDebug();
