<?php
/**
 * Plugin Manager Feature
 *
 * @package WP_Arzo
 * @version 6.2
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Handle AJAX operations for plugins
if (isset($_GET['operation'])) {
    if ($_GET['operation'] === 'get_plugins_page') {
        header('Content-Type: application/json');

        if (isset($_GET['page']) && isset($_GET['per_page'])) {
            $page = intval($_GET['page']);
            $per_page = intval($_GET['per_page']);

            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

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

        echo json_encode($response);
        exit;
    }

    if ($_GET['operation'] === 'toggle_plugin') {
        header('Content-Type: application/json');

        // Verify CSRF nonce AND capability for this state-changing operation.
        if (!check_ajax_referer('wp_arzo_ajax', 'nonce', false) || !current_user_can('activate_plugins')) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            exit;
        }

        $plugin = isset($_POST['plugin']) ? sanitize_text_field($_POST['plugin']) : '';
        $state = isset($_POST['state']) ? sanitize_text_field($_POST['state']) : '';

        if (!$plugin || !$state) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }

        if ($state === 'activate') {
            $result = activate_plugin($plugin);
            if (is_wp_error($result)) {
                echo json_encode(['success' => false, 'message' => $result->get_error_message()]);
            } else {
                echo json_encode(['success' => true, 'message' => 'Plugin activated']);
            }
        } else {
            deactivate_plugins($plugin);
            echo json_encode(['success' => true, 'message' => 'Plugin deactivated']);
        }
        exit;
    }
}

function showPlugins()
{
    $message = '';
    
    // Handle File Upload
    if (isset($_FILES['plugin_zip']) && isset($_POST['upload_plugin_action'])) {
        if (!function_exists('wp_handle_upload')) require_once(ABSPATH . 'wp-admin/includes/file.php');
        if (!function_exists('unzip_file')) require_once(ABSPATH . 'wp-admin/includes/file.php');
        if (!function_exists('request_filesystem_credentials')) require_once(ABSPATH . 'wp-admin/includes/file.php');
        
        $uploadedfile = $_FILES['plugin_zip'];
        $upload_overrides = array('test_form' => false);
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['plugin_upload_nonce'], 'plugin_upload_action')) {
            $message = '<div class="alert alert-error">Security check failed.</div>';
        } else {
            // Check file type
            $file_type = wp_check_filetype($uploadedfile['name']);
            if ($file_type['ext'] !== 'zip') {
                $message = '<div class="alert alert-error">Only ZIP files are allowed.</div>';
            } else {
                $movefile = wp_handle_upload($uploadedfile, $upload_overrides);
                
                if ($movefile && !isset($movefile['error'])) {
                    $zip_path = $movefile['file'];
                    $to = WP_PLUGIN_DIR;
                    
                    // Initialize Filesystem
                    if (false === ($creds = request_filesystem_credentials(site_url()))) {
                        // If we don't have credentials, we can't proceed.
                        $message = '<div class="alert alert-error">Filesystem credentials required.</div>';
                        unlink($zip_path);
                    } else {
                        if (!WP_Filesystem($creds)) {
                            $message = '<div class="alert alert-error">Filesystem initialization failed.</div>';
                            unlink($zip_path);
                        } else {
                            // Unzip
                            $result = unzip_file($zip_path, $to);
                            if (is_wp_error($result)) {
                                $message = '<div class="alert alert-error">Unzip failed: ' . $result->get_error_message() . '</div>';
                            } else {
                                $message = '<div class="alert alert-success">Plugin installed successfully.</div>';
                                
                                // Activate immediately
                                if (isset($_POST['activate_immediately']) && $_POST['activate_immediately'] == '1') {
                                    // Clear plugin cache to recognize new plugin
                                    wp_clean_plugins_cache();
                                    
                                    // Identify the uploaded plugin
                                    // unzip_file extracts to WP_PLUGIN_DIR
                                    // We need to find the folder that was just created.
                                    // Best guess: peek into the zip again
                                    $zip = new ZipArchive;
                                    if ($zip->open($zip_path) === TRUE) {
                                        $stat = $zip->statIndex(0);
                                        $root_folder = explode('/', $stat['name'])[0];
                                        $zip->close();
                                        
                                        if ($root_folder) {
                                            // Scan for .php files with Plugin Name header
                                            $extracted_path = $to . '/' . $root_folder;
                                            // get_plugins requires relative path
                                            if (!function_exists('get_plugins')) {
                                                require_once ABSPATH . 'wp-admin/includes/plugin.php';
                                            }
                                            $plugins = get_plugins('/' . $root_folder);
                                            
                                            if (!empty($plugins)) {
                                                $main_file = key($plugins); // e.g. my-plugin.php
                                                $plugin_slug = $root_folder . '/' . $main_file;
                                                
                                                $result = activate_plugin($plugin_slug);
                                                if (is_wp_error($result)) {
                                                     $message .= ' <span style="color:var(--arzo-error)">Activation failed: ' . $result->get_error_message() . '</span>';
                                                } else {
                                                     $message .= ' And activated.';
                                                }
                                            } else {
                                                $message .= ' <span style="color:var(--arzo-error)">Could not find plugin file to activate.</span>';
                                            }
                                        }
                                    }
                                }
                                // Cleanup zip
                                unlink($zip_path);
                            }
                        }
                    }
                } else {
                    $message = '<div class="alert alert-error">Upload failed: ' . $movefile['error'] . '</div>';
                }
            }
        }
    }

    ?>
    <div class="content">
        <h1>Plugin Management</h1>
        <?php echo $message; ?>

        <!-- Upload Form -->
        <div style="background:var(--background-light); padding:15px; margin:15px 0; border-radius:3px;">
            <h4 style="margin-top:0;">Upload Plugin (ZIP)</h4>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('plugin_upload_action', 'plugin_upload_nonce'); ?>
                <input type="hidden" name="upload_plugin_action" value="1">
                <input type="file" name="plugin_zip" required accept=".zip" style="color:var(--arzo-text-strong);">
                <div style="margin-top:10px; display:flex; align-items:center;">
                    <label class="switch">
                        <input type="checkbox" name="activate_immediately" value="1">
                        <span class="slider round"></span>
                    </label>
                    <span class="toggle-label">Activate immediately</span>
                </div>
                <button type="submit" class="btn btn-sm" style="margin-top:10px;">Install</button>
            </form>
        </div>

        <!-- Search -->
        <div class="form-group">
            <input type="text" id="search-plugins" class="form-control" placeholder="Search plugins..." onkeyup="filterPluginsTable()">
        </div>

        <div class="scrollable-table-container">
            <table id="plugins-table">
                <thead>
                    <tr>
                        <th>Plugin Name</th>
                        <th>Path/Version</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Plugin data will be loaded here via AJAX -->
                    <tr>
                        <td colspan="4" style="text-align: center;">Loading plugins...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="plugins-pagination" class="pagination-container">
            <div class="pagination-info">Loading...</div>
            <div class="pagination-controls">
                <!-- Pagination controls will be added here -->
            </div>
        </div>

        <script>
            // Plugins functionality
            document.addEventListener('DOMContentLoaded', function () {
                let currentPage = 1;
                const perPage = 10;
                const pluginsTable = document.getElementById('plugins-table').querySelector('tbody');
                const paginationInfo = document.querySelector('#plugins-pagination .pagination-info');
                const paginationControls = document.querySelector('#plugins-pagination .pagination-controls');

                function loadPluginsPage(page) {
                    const baseUrl = '<?php echo admin_url('admin-ajax.php?action=wp_arzo_standalone'); ?>';
                    fetch(
                        `${baseUrl}&tab=plugins&operation=get_plugins_page&page=${page}&per_page=${perPage}&nocache=${new Date().getTime()}`
                    )
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                renderPluginsTable(data.plugins);
                                renderPagination(data.current_page, data.total_pages, data.total);
                            } else {
                                pluginsTable.innerHTML =
                                    `<tr><td colspan="4" style="text-align: center;">Error: ${data.message}</td></tr>`;
                            }
                        })
                        .catch(error => {
                            pluginsTable.innerHTML =
                                `<tr><td colspan="4" style="text-align: center;">Error loading plugins: ${error.message}</td></tr>`;
                        });
                }

                function renderPluginsTable(plugins) {
                    if (plugins.length === 0) {
                        pluginsTable.innerHTML =
                            '<tr><td colspan="4" style="text-align: center;">No plugins found</td></tr>';
                        return;
                    }

                    let html = '';
                    plugins.forEach(plugin => {
                        html += `<tr>`;
                        html += `<td>${plugin.name}</td>`;
                        html += `<td><small>${plugin.file}<br>v${plugin.version}</small></td>`;
                        html += `<td><span class="badge ${plugin.is_active ? 'badge-active' : 'badge-inactive'}">${plugin.is_active ? 'ACTIVE' : 'INACTIVE'}</span></td>`;
                        html += '<td>';
                        
                        // Toggle Switch
                        html += `<label class="switch">`;
                        html += `<input type="checkbox" onchange="togglePlugin('${plugin.file}', this)" ${plugin.is_active ? 'checked' : ''}>`;
                        html += `<span class="slider round"></span>`;
                        html += `</label>`;
                        html += `<span class="toggle-label" id="label-${plugin.file.replace(/[^a-zA-Z0-9]/g, '')}">${plugin.is_active ? 'Active' : 'Inactive'}</span>`;
                        
                        html += '</td>';
                        html += '</tr>';
                    });
                    pluginsTable.innerHTML = html;
                }

                window.togglePlugin = function(pluginFile, checkbox) {
                    const label = document.getElementById('label-' + pluginFile.replace(/[^a-zA-Z0-9]/g, ''));
                    const originalState = !checkbox.checked; // State before click
                    const newState = checkbox.checked ? 'activate' : 'deactivate';
                    
                    // Optimistic UI update
                    label.textContent = checkbox.checked ? 'Active' : 'Inactive';
                    const row = checkbox.closest('tr');
                    const badge = row.querySelector('.badge');
                    if(badge) {
                        badge.className = `badge ${checkbox.checked ? 'badge-active' : 'badge-inactive'}`;
                        badge.textContent = checkbox.checked ? 'ACTIVE' : 'INACTIVE';
                    }

                    const formData = new FormData();
                    formData.append('plugin', pluginFile);
                    formData.append('state', newState);
                    formData.append('nonce', '<?php echo esc_js(wp_create_nonce('wp_arzo_ajax')); ?>');

                    fetch('<?php echo admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=plugins&operation=toggle_plugin'); ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if(!data.success) {
                            alert('Error: ' + data.message);
                            // Revert
                            checkbox.checked = originalState;
                            label.textContent = originalState ? 'Active' : 'Inactive';
                            if(badge) {
                                badge.className = `badge ${originalState ? 'badge-active' : 'badge-inactive'}`;
                                badge.textContent = originalState ? 'ACTIVE' : 'INACTIVE';
                            }
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Request failed');
                        checkbox.checked = originalState;
                    });
                };

                function renderPagination(currentPage, totalPages, totalItems) {
                    paginationInfo.textContent =
                        `Showing page ${currentPage} of ${totalPages} (${totalItems} total plugins)`;

                    let controlsHtml = '';
                    controlsHtml += `<button ${currentPage === 1 ? 'disabled' : ''} onclick="loadPluginsPage(${currentPage - 1})">&laquo; Previous</button>`;
                    
                    const startPage = Math.max(1, currentPage - 2);
                    const endPage = Math.min(totalPages, startPage + 4);

                    for (let i = startPage; i <= endPage; i++) {
                        controlsHtml += `<button class="${i === currentPage ? 'active' : ''}" onclick="loadPluginsPage(${i})">${i}</button>`;
                    }

                    controlsHtml += `<button ${currentPage === totalPages ? 'disabled' : ''} onclick="loadPluginsPage(${currentPage + 1})">Next &raquo;</button>`;
                    paginationControls.innerHTML = controlsHtml;
                }

                window.loadPluginsPage = loadPluginsPage;
                
                // Client-side search for current page
                window.filterPluginsTable = function() {
                    const input = document.getElementById('search-plugins');
                    const filter = input.value.toUpperCase();
                    const tr = pluginsTable.getElementsByTagName('tr');
                    for (let i = 0; i < tr.length; i++) {
                        const tds = tr[i].getElementsByTagName('td');
                        let visible = false;
                        for (let j = 0; j < tds.length; j++) {
                            if (tds[j].textContent.toUpperCase().indexOf(filter) > -1) {
                                visible = true;
                                break;
                            }
                        }
                        tr[i].style.display = visible ? '' : 'none';
                    }
                }

                // Initial load
                loadPluginsPage(currentPage);
            });
        </script>
    </div>
    <?php
}

// Call the function
showPlugins();
