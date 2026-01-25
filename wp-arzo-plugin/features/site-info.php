<?php
/**
 * Site Info / Dashboard Feature
 *
 * @package WP_Arzo
 * @version 5.1
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

function showSiteInfo()
{
    global $wpdb;

    // Get additional system information
    $wp_version = get_bloginfo('version');
    $php_version = phpversion();
    $mysql_version = $wpdb->db_version();
    $server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
    $memory_limit = ini_get('memory_limit');
    $max_execution_time = ini_get('max_execution_time');
    $upload_max_filesize = ini_get('upload_max_filesize');
    $post_max_size = ini_get('post_max_size');
    $is_multisite = is_multisite();
    $debug_mode = defined('WP_DEBUG') && WP_DEBUG;
    $ssl_enabled = is_ssl();
    $active_theme = wp_get_theme();
    $active_plugins = get_option('active_plugins');
    $total_plugins = count(get_plugins());
    $active_plugin_count = count($active_plugins);

    // Get disk space info if available
    $disk_free_space = function_exists('disk_free_space') ? disk_free_space(ABSPATH) : false;
    $disk_total_space = function_exists('disk_total_space') ? disk_total_space(ABSPATH) : false;

    // Format bytes function
    if (!function_exists('formatBytes')) {
        function formatBytes($size, $precision = 2)
        {
            if ($size === false)
                return 'N/A';
            $base = log($size, 1024);
            $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
            return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
        }
    }

    ?>
    <style>
        .site-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: var(--background-medium);
            border-radius: 8px;
            padding: 0;
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .info-card-header {
            background: linear-gradient(135deg, var(--accent-color) 0%, #0ea66b 100%);
            color: var(--primary-text);
            padding: 15px 20px;
            font-weight: 600;
            font-size: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .info-card-body {
            padding: 20px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-light);
        }

        .info-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .info-item:first-child {
            padding-top: 0;
        }

        .info-label {
            font-weight: 500;
            color: var(--secondary-text);
            flex: 1;
        }

        .info-value {
            color: var(--primary-text);
            font-weight: 600;
            text-align: right;
            flex: 1;
            word-break: break-all;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-enabled {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }

        .status-disabled {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .status-warning {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--background-light);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent-color), #0ea66b);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .disk-usage {
            margin-top: 10px;
        }

        .disk-usage-text {
            font-size: 12px;
            color: var(--secondary-text);
            margin-bottom: 5px;
        }

        @media (max-width: 768px) {
            .site-info-grid {
                grid-template-columns: 1fr;
            }

            .info-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }

            .info-value {
                text-align: left;
            }
        }
    </style>

    <div class="content">
        <h2>Site Information</h2>

        <div class="site-info-grid">
            <!-- WordPress Information -->
            <div class="info-card">
                <div class="info-card-header">
                    <i class="fab fa-wordpress" style="margin-right: 8px;"></i>
                    WordPress
                </div>
                <div class="info-card-body">
                    <div class="info-item">
                        <span class="info-label">Version:</span>
                        <span class="info-value">
                            <?php echo $wp_version; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Multisite:</span>
                        <span class="info-value">
                            <span class="status-badge <?php echo $is_multisite ? 'status-enabled' : 'status-disabled'; ?>">
                                <?php echo $is_multisite ? 'Yes' : 'No'; ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Debug Mode:</span>
                        <span class="info-value">
                            <span class="status-badge <?php echo $debug_mode ? 'status-warning' : 'status-disabled'; ?>">
                                <?php echo $debug_mode ? 'Enabled' : 'Disabled'; ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">SSL:</span>
                        <span class="info-value">
                            <span class="status-badge <?php echo $ssl_enabled ? 'status-enabled' : 'status-disabled'; ?>">
                                <?php echo $ssl_enabled ? 'Enabled' : 'Disabled'; ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Site URL:</span>
                        <span class="info-value">
                            <?php echo home_url(); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">WordPress Path:</span>
                        <span class="info-value">
                            <?php echo ABSPATH; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- PHP Information -->
            <div class="info-card">
                <div class="info-card-header">
                    <i class="fab fa-php" style="margin-right: 8px;"></i>
                    PHP
                </div>
                <div class="info-card-body">
                    <div class="info-item">
                        <span class="info-label">Version:</span>
                        <span class="info-value">
                            <?php echo $php_version; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Memory Limit:</span>
                        <span class="info-value">
                            <?php echo $memory_limit; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Max Execution:</span>
                        <span class="info-value">
                            <?php echo $max_execution_time; ?>s
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Upload Max:</span>
                        <span class="info-value">
                            <?php echo $upload_max_filesize; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Post Max:</span>
                        <span class="info-value">
                            <?php echo $post_max_size; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Server Information -->
            <div class="info-card">
                <div class="info-card-header">
                    <i class="fas fa-server" style="margin-right: 8px;"></i>
                    Server
                </div>
                <div class="info-card-body">
                    <div class="info-item">
                        <span class="info-label">Software:</span>
                        <span class="info-value">
                            <?php echo $server_software; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Operating System:</span>
                        <span class="info-value">
                            <?php echo PHP_OS; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">MySQL Version:</span>
                        <span class="info-value">
                            <?php echo $mysql_version; ?>
                        </span>
                    </div>
                    <?php if ($disk_free_space !== false && $disk_total_space !== false):
                        $disk_used = $disk_total_space - $disk_free_space;
                        $disk_usage_percent = ($disk_used / $disk_total_space) * 100;
                        ?>
                        <div class="info-item">
                            <span class="info-label">Disk Usage:</span>
                            <span class="info-value">
                                <div class="disk-usage">
                                    <div class="disk-usage-text">
                                        <?php echo formatBytes($disk_used); ?> /
                                        <?php echo formatBytes($disk_total_space); ?>
                                        (
                                        <?php echo number_format($disk_usage_percent, 1); ?>%)
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $disk_usage_percent; ?>%;"></div>
                                    </div>
                                </div>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Database Information -->
            <div class="info-card">
                <div class="info-card-header">
                    <i class="fas fa-database" style="margin-right: 8px;"></i>
                    Database
                </div>
                <div class="info-card-body">
                    <div class="info-item">
                        <span class="info-label">Database Name:</span>
                        <span class="info-value">
                            <?php echo DB_NAME; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Database Host:</span>
                        <span class="info-value">
                            <?php echo DB_HOST; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Table Prefix:</span>
                        <span class="info-value">
                            <?php echo $wpdb->prefix; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Charset:</span>
                        <span class="info-value">
                            <?php echo DB_CHARSET; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Collation:</span>
                        <span class="info-value">
                            <?php echo DB_COLLATE ?: 'Default'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Theme & Plugins Information -->
            <div class="info-card">
                <div class="info-card-header">
                    <i class="fas fa-paint-brush" style="margin-right: 8px;"></i>
                    Theme & Plugins
                </div>
                <div class="info-card-body">
                    <div class="info-item">
                        <span class="info-label">Active Theme:</span>
                        <span class="info-value">
                            <?php echo $active_theme->get('Name'); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Theme Version:</span>
                        <span class="info-value">
                            <?php echo $active_theme->get('Version'); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Active Plugins:</span>
                        <span class="info-value">
                            <?php echo $active_plugin_count; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Total Plugins:</span>
                        <span class="info-value">
                            <?php echo $total_plugins; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Plugin Usage:</span>
                        <span class="info-value">
                            <div class="disk-usage">
                                <div class="disk-usage-text">
                                    <?php echo $active_plugin_count; ?> /
                                    <?php echo $total_plugins; ?>
                                    (
                                    <?php echo $total_plugins > 0 ? number_format(($active_plugin_count / $total_plugins) * 100, 1) : 0; ?>%)
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill"
                                        style="width: <?php echo $total_plugins > 0 ? ($active_plugin_count / $total_plugins) * 100 : 0; ?>%;">
                                    </div>
                                </div>
                            </div>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Security & Performance -->
            <div class="info-card">
                <div class="info-card-header">
                    <i class="fas fa-shield-alt" style="margin-right: 8px;"></i>
                    Security & Performance
                </div>
                <div class="info-card-body">
                    <div class="info-item">
                        <span class="info-label">File Editing:</span>
                        <span class="info-value">
                            <span
                                class="status-badge <?php echo defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT ? 'status-enabled' : 'status-warning'; ?>">
                                <?php echo defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT ? 'Disabled' : 'Enabled'; ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">WP Cron:</span>
                        <span class="info-value">
                            <span
                                class="status-badge <?php echo defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? 'status-disabled' : 'status-enabled'; ?>">
                                <?php echo defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? 'Disabled' : 'Enabled'; ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Auto Updates:</span>
                        <span class="info-value">
                            <span
                                class="status-badge <?php echo defined('AUTOMATIC_UPDATER_DISABLED') && AUTOMATIC_UPDATER_DISABLED ? 'status-disabled' : 'status-enabled'; ?>">
                                <?php echo defined('AUTOMATIC_UPDATER_DISABLED') && AUTOMATIC_UPDATER_DISABLED ? 'Disabled' : 'Enabled'; ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Script Debug:</span>
                        <span class="info-value">
                            <span
                                class="status-badge <?php echo defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? 'status-warning' : 'status-disabled'; ?>">
                                <?php echo defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? 'Enabled' : 'Disabled'; ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">WP Cache:</span>
                        <span class="info-value">
                            <span
                                class="status-badge <?php echo defined('WP_CACHE') && WP_CACHE ? 'status-enabled' : 'status-disabled'; ?>">
                                <?php echo defined('WP_CACHE') && WP_CACHE ? 'Enabled' : 'Disabled'; ?>
                            </span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// Call the function
showSiteInfo();
