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

        if (!current_user_can('manage_options') || !check_ajax_referer('wp_arzo_ajax', 'nonce', false)) {
            echo json_encode(['success' => false, 'message' => 'Security check failed']);
            exit;
        }

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

        if (!current_user_can('manage_options') || !check_ajax_referer('wp_arzo_ajax', 'nonce', false)) {
            echo json_encode(['success' => false, 'message' => 'Security check failed']);
            exit;
        }

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

    if ($operation === 'read_debug_log') {
        header('Content-Type: application/json');
        if (!current_user_can('manage_options') || !check_ajax_referer('wp_arzo_ajax', 'nonce', false)) {
            echo json_encode(['success' => false, 'message' => 'Security check failed']);
            exit;
        }
        $n    = isset($_GET['lines']) ? (int) $_GET['lines'] : 200;
        $n    = max(50, min(2000, $n));
        $file = WP_CONTENT_DIR . '/debug.log';
        $tail = wp_arzo_debug_tail($file, $n);
        echo json_encode([
            'success' => true,
            'exists'  => is_file($file),
            'lines'   => $tail['lines'],
            'size'    => $tail['size'],
            'size_h'  => size_format((int) $tail['size']),
            'partial' => !empty($tail['partial']),
        ]);
        exit;
    }

    if ($operation === 'download_debug_log') {
        if (!current_user_can('manage_options') || !check_ajax_referer('wp_arzo_ajax', 'nonce', false)) {
            status_header(403);
            header('Content-Type: text/plain');
            echo 'Security check failed';
            exit;
        }
        $file = WP_CONTENT_DIR . '/debug.log';
        if (!is_file($file)) {
            status_header(404);
            header('Content-Type: text/plain');
            echo 'No debug.log file.';
            exit;
        }
        nocache_headers();
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="debug-log-' . gmdate('Ymd-His') . '.txt"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }

    if ($operation === 'read_config_file') {
        header('Content-Type: application/json');
        if (!current_user_can('manage_options') || !check_ajax_referer('wp_arzo_ajax', 'nonce', false)) {
            echo json_encode(['success' => false, 'message' => 'Security check failed']);
            exit;
        }
        $which = isset($_GET['file']) ? sanitize_key($_GET['file']) : 'wpconfig';
        // Fixed, server-defined paths only (never user input) — no traversal risk.
        if ($which === 'wpconfig') {
            $path = is_file(ABSPATH . 'wp-config.php') ? ABSPATH . 'wp-config.php' : dirname(ABSPATH) . '/wp-config.php';
        } elseif ($which === 'htaccess') {
            $path = ABSPATH . '.htaccess';
        } else {
            echo json_encode(['success' => false, 'message' => 'Unknown file']);
            exit;
        }
        if (!is_file($path)) {
            echo json_encode(['success' => true, 'exists' => false, 'content' => '', 'path' => $path]);
            exit;
        }
        $content = (string) file_get_contents($path);
        if ($which === 'wpconfig') {
            $content = wp_arzo_mask_config($content); // never expose DB password / salts
        }
        echo json_encode(['success' => true, 'exists' => true, 'content' => $content, 'path' => $path, 'size' => strlen($content)]);
        exit;
    }
}

/**
 * Mask secret values in wp-config.php (DB password + all auth keys/salts) so the
 * read-only viewer never reveals credentials. Pure/harnessable.
 */
function wp_arzo_mask_config($content)
{
    $secrets = array(
        'DB_PASSWORD',
        'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
        'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT',
    );
    foreach ($secrets as $k) {
        $content = preg_replace_callback(
            '/(define\(\s*[\'"]' . preg_quote($k, '/') . '[\'"]\s*,\s*([\'"]))(.*?)(\2\s*\))/s',
            function ($m) {
                return $m[1] . str_repeat('•', 12) . $m[4];
            },
            $content
        );
    }
    return $content;
}

/**
 * Read the last $lines lines of a log file without loading the whole thing —
 * only the trailing ~512 KB is scanned. The path is a fixed, server-defined
 * location (never user input). @return array{lines:string[],size:int,partial:bool}
 */
function wp_arzo_debug_tail($file, $lines)
{
    $lines = max(1, (int) $lines);
    if (!is_file($file)) {
        return array('lines' => array(), 'size' => 0, 'partial' => false);
    }
    $size = (int) filesize($file);
    $f    = @fopen($file, 'rb');
    if (!$f) {
        return array('lines' => array(), 'size' => $size, 'partial' => false);
    }
    $max     = 512 * 1024; // bound the tail read
    $start   = ($size > $max) ? $size - $max : 0;
    $partial = $start > 0;
    if ($start > 0) {
        fseek($f, $start);
    }
    $data = stream_get_contents($f);
    fclose($f);
    if ($partial) {
        $nl = strpos($data, "\n"); // drop the (likely partial) first line
        if ($nl !== false) {
            $data = substr($data, $nl + 1);
        }
    }
    $arr = preg_split('/\r\n|\r|\n/', rtrim((string) $data, "\r\n"));
    if (count($arr) > $lines) {
        $arr = array_slice($arr, -$lines);
    }
    return array('lines' => array_values($arr), 'size' => $size, 'partial' => $partial);
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
    if (isset($_POST['update_debug_settings']) && $config_writable &&
        isset($_POST['wp_arzo_debug_nonce']) &&
        wp_verify_nonce(wp_unslash($_POST['wp_arzo_debug_nonce']), 'wp_arzo_debug_settings') &&
        current_user_can('manage_options')) {
        $new_config = $config_content;

        foreach ($debug_settings as $setting => $info) {
            // STRICT allow-list. The value is written verbatim into wp-config.php as
            // PHP, so it must only ever be the literal boolean true/false — never raw
            // POST data (which would allow PHP code injection).
            $new_value = (isset($_POST[$setting]) && $_POST[$setting] === 'true') ? 'true' : 'false';
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
            echo '<div class="success">Debug settings updated successfully!</div>';
            // IMPORTANT: this page was reached by a POST form submit. location.reload()
            // re-issues that POST, which re-writes the config and re-emits this reload
            // script — an infinite auto-refresh loop. Navigate to the clean GET URL
            // (replace, so Back doesn't re-POST) to reflect the new constants once.
            $debug_get_url = admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=debug');
            echo '<script>setTimeout(function () { window.location.replace(' . wp_json_encode($debug_get_url) . '); }, 1200);</script>';
        } else {
            echo '<div class="error">Failed to update wp-config.php. Check file permissions.</div>';
        }
    }

?>
    <div class="content">
        <h1>WordPress Debug Settings</h1>

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
            <form method="post" style="background: var(--arzo-bg-hover); padding: 20px; border-radius: var(--radius-global); border: 1px solid var(--arzo-border);">
                <?php wp_nonce_field('wp_arzo_debug_settings', 'wp_arzo_debug_nonce'); ?>
                <h3>Update Debug Settings</h3>
                <p style="color: var(--arzo-text-secondary); margin-bottom: 20px;">Configure WordPress debug settings. Changes will be written to
                    wp-config.php.</p>

                <div class="debug-settings-grid"
                    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px 15px; margin-bottom: 20px;">
                    <?php foreach ($debug_settings as $setting => $info): ?>
                        <div class="form-group"
                            style="display: flex; align-items: center; justify-content: space-between; padding: 12px 15px; background: var(--arzo-bg-input); border-radius: 5px; border: 1px solid var(--arzo-border);">
                            <div style="flex: 1; min-width: 0;">
                                <label
                                    style="display: block; margin-bottom: 3px; font-weight: bold; color: var(--arzo-text-strong); font-size: 14px;"><?php echo $setting; ?></label>
                                <p
                                    style="font-size: 11px; color: var(--arzo-text-secondary); margin: 0; line-height: 1.3; overflow: hidden; text-overflow: ellipsis;">
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

                <button type="submit" name="update_debug_settings" class="btn" style="margin-top: 15px;"><?php echo wp_arzo_icon('check', array('class' => 'wpa-icon wpa-icon--sm')); ?> Update Debug
                    Settings</button>
            </form>
        <?php endif; ?>

        <div style="background: var(--arzo-bg-hover); padding: 20px; border-radius: var(--radius-global); border: 1px solid var(--arzo-border); margin-top: 20px;">
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

        <?php
        $wpa_dbg_nonce = wp_create_nonce('wp_arzo_ajax');
        $wpa_dbg_ajax  = admin_url('admin-ajax.php');
        $wpa_dbg_dl    = add_query_arg(array('action' => 'wp_arzo_standalone', 'tab' => 'debug', 'operation' => 'download_debug_log', 'nonce' => $wpa_dbg_nonce), $wpa_dbg_ajax);
        ?>
        <div class="wpa-card" id="wpa-dbg" data-nonce="<?php echo esc_attr($wpa_dbg_nonce); ?>" data-ajax="<?php echo esc_url($wpa_dbg_ajax); ?>" style="margin-top:20px;padding:0;overflow:hidden;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;padding:14px 18px;border-bottom:1px solid var(--arzo-border);">
                <h3 style="margin:0;">Debug log <span id="wpa-dbg-meta" style="color:var(--arzo-text-secondary);font-weight:400;font-size:.82em;"></span></h3>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <select class="wpa-select" data-wpa-select id="wpa-dbg-lines" aria-label="Lines to show">
                        <option value="50">Last 50</option>
                        <option value="200" selected>Last 200</option>
                        <option value="500">Last 500</option>
                        <option value="2000">Last 2000</option>
                    </select>
                    <select class="wpa-select" data-wpa-select id="wpa-dbg-filter" aria-label="Severity filter">
                        <option value="">All levels</option>
                        <option value="error">Errors</option>
                        <option value="warning">Warnings</option>
                        <option value="notice">Notices</option>
                    </select>
                    <button type="button" class="wpa-btn wpa-btn--ghost wpa-btn--sm" id="wpa-dbg-refresh"><?php echo wp_arzo_icon('refresh', array('class' => 'wpa-icon wpa-icon--sm')); ?> Refresh</button>
                    <label class="wpa-toggle" style="margin:0;"><input class="wpa-toggle__input" type="checkbox" role="switch" id="wpa-dbg-auto"><span class="wpa-toggle__track"><span class="wpa-toggle__thumb"></span></span><span class="wpa-toggle__label" style="font-size:.85em;">Auto</span></label>
                    <a class="wpa-btn wpa-btn--secondary wpa-btn--sm" id="wpa-dbg-download" href="<?php echo esc_url($wpa_dbg_dl); ?>"><?php echo wp_arzo_icon('download', array('class' => 'wpa-icon wpa-icon--sm')); ?> Download</a>
                    <button type="button" class="wpa-btn wpa-btn--danger wpa-btn--sm" id="wpa-dbg-clear"><?php echo wp_arzo_icon('trash', array('class' => 'wpa-icon wpa-icon--sm')); ?> Clear</button>
                </div>
            </div>
            <pre id="wpa-dbg-log" style="margin:0;max-height:420px;overflow:auto;padding:14px 18px;font-family:var(--arzo-font-mono,monospace);font-size:12px;line-height:1.5;background:var(--arzo-bg-input);white-space:pre-wrap;word-break:break-word;color:var(--arzo-text-secondary);">Loading…</pre>
        </div>
        <script>
        (function () {
            var root = document.getElementById('wpa-dbg');
            if (!root) { return; }
            var nonce = root.dataset.nonce, ajax = root.dataset.ajax,
                pre = document.getElementById('wpa-dbg-log'),
                linesSel = document.getElementById('wpa-dbg-lines'),
                filterSel = document.getElementById('wpa-dbg-filter'),
                meta = document.getElementById('wpa-dbg-meta'),
                auto = document.getElementById('wpa-dbg-auto'),
                timer = null, raw = [];
            var CMAP = { error: 'var(--arzo-error)', warning: 'var(--arzo-warning)', notice: 'var(--arzo-info)' };
            function esc(s) { return String(s).replace(/[&<>]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]; }); }
            function sevOf(line) {
                var l = line.toLowerCase();
                if (l.indexOf('fatal') > -1 || l.indexOf('error') > -1) { return 'error'; }
                if (l.indexOf('deprecated') > -1 || l.indexOf('warning') > -1 || l.indexOf('warn') > -1) { return 'warning'; }
                if (l.indexOf('notice') > -1 || l.indexOf('info') > -1) { return 'notice'; }
                return '';
            }
            function render() {
                var filt = filterSel.value, html = '', shown = 0;
                raw.forEach(function (line) {
                    if (!line) { return; }
                    var sev = sevOf(line);
                    if (filt && sev !== filt) { return; }
                    shown++;
                    var col = CMAP[sev] || 'var(--arzo-text-strong)';
                    var bl = sev ? ('border-left:3px solid ' + col + ';padding-left:9px;') : '';
                    html += '<div style="color:' + col + ';' + bl + '">' + esc(line) + '</div>';
                });
                pre.innerHTML = html || '<div style="color:var(--arzo-text-secondary)">No matching lines.</div>';
            }
            function load() {
                var url = new URL(ajax);
                url.searchParams.set('action', 'wp_arzo_standalone');
                url.searchParams.set('tab', 'debug');
                url.searchParams.set('operation', 'read_debug_log');
                url.searchParams.set('lines', linesSel.value);
                var fd = new FormData(); fd.append('nonce', nonce);
                fetch(url.toString(), { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (!d || !d.success) { pre.textContent = 'Could not read the log.'; return; }
                        raw = d.lines || [];
                        meta.textContent = '· ' + (d.size_h || '0 B') + (d.partial ? ' (tail)' : '') + ' · ' + raw.length + ' lines';
                        render();
                        pre.scrollTop = pre.scrollHeight;
                    })
                    .catch(function () { pre.textContent = 'Request failed.'; });
            }
            linesSel.addEventListener('change', load);
            filterSel.addEventListener('change', render);
            document.getElementById('wpa-dbg-refresh').addEventListener('click', load);
            auto.addEventListener('change', function () {
                if (auto.checked) { timer = setInterval(load, 5000); } else if (timer) { clearInterval(timer); timer = null; }
            });
            document.getElementById('wpa-dbg-clear').addEventListener('click', function () {
                if (!confirm('Clear the debug log? This permanently empties the file.')) { return; }
                var url = new URL(ajax);
                url.searchParams.set('action', 'wp_arzo_standalone');
                url.searchParams.set('tab', 'debug');
                url.searchParams.set('operation', 'clear_debug_log');
                var fd = new FormData(); fd.append('nonce', nonce);
                fetch(url.toString(), { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); }).then(function () { load(); });
            });
            load();
        })();
        </script>

        <?php $wpa_cfg_nonce = wp_create_nonce('wp_arzo_ajax'); $wpa_cfg_ajax = admin_url('admin-ajax.php'); ?>
        <div class="wpa-card" id="wpa-cfg" data-nonce="<?php echo esc_attr($wpa_cfg_nonce); ?>" data-ajax="<?php echo esc_url($wpa_cfg_ajax); ?>" style="margin-top:20px;padding:0;overflow:hidden;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;padding:14px 18px;border-bottom:1px solid var(--arzo-border);">
                <h3 style="margin:0;"><?php echo wp_arzo_icon('code', array('class' => 'wpa-icon wpa-icon--sm')); ?> Configuration files <span style="color:var(--arzo-text-secondary);font-weight:400;font-size:.8em;">read-only · secrets masked</span></h3>
                <select class="wpa-select" data-wpa-select id="wpa-cfg-file" aria-label="Configuration file to view">
                    <option value="wpconfig">wp-config.php</option>
                    <option value="htaccess">.htaccess</option>
                </select>
            </div>
            <pre id="wpa-cfg-view" style="margin:0;max-height:420px;overflow:auto;padding:14px 18px;font-family:var(--arzo-font-mono,monospace);font-size:12px;line-height:1.5;background:var(--arzo-bg-input);white-space:pre;color:var(--arzo-text-secondary);">Loading…</pre>
        </div>
        <script>
        (function () {
            var root = document.getElementById('wpa-cfg');
            if (!root) { return; }
            var nonce = root.dataset.nonce, ajax = root.dataset.ajax,
                pre = document.getElementById('wpa-cfg-view'), sel = document.getElementById('wpa-cfg-file');
            function load() {
                var url = new URL(ajax);
                url.searchParams.set('action', 'wp_arzo_standalone');
                url.searchParams.set('tab', 'debug');
                url.searchParams.set('operation', 'read_config_file');
                url.searchParams.set('file', sel.value);
                var fd = new FormData(); fd.append('nonce', nonce);
                fetch(url.toString(), { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (!d || !d.success) { pre.textContent = 'Could not read the file.'; return; }
                        if (!d.exists) { pre.textContent = 'This file does not exist on this install.'; return; }
                        pre.textContent = d.content;
                        pre.scrollTop = 0;
                    })
                    .catch(function () { pre.textContent = 'Request failed.'; });
            }
            sel.addEventListener('change', load);
            load();
        })();
        </script>


    </div>
<?php
}

// Call the function
handleDebug();
