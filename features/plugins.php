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
    // Installing a new plugin from an uploaded ZIP is core WordPress functionality
    // (Plugins → Add New → Upload Plugin); this console intentionally does not
    // duplicate it. This tab only lists and toggles plugins already installed.
    ?>
    <div class="content">
        <h1>Plugin Management</h1>

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
                    const baseUrl = '<?php echo esc_url(admin_url('admin-ajax.php?action=wp_arzo_standalone')); ?>';
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

                    fetch('<?php echo esc_url(admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=plugins&operation=toggle_plugin')); ?>', {
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
