<?php
/**
 * Plugin Manager Feature
 *
 * @package WP_Arzo
 * @version 5.1
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Handle AJAX operations for plugins
if (isset($_GET['operation']) && $_GET['operation'] === 'get_plugins_page') {
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

function showPlugins()
{
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
        <div class="scrollable-table-container">
            <table id="plugins-table">
                <thead>
                    <tr>
                        <th>Plugin Name</th>
                        <th>Version</th>
                        <th>Status</th>
                        <th>Actions</th>
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
            // Plugins pagination functionality
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
                        html += `<td>${plugin.version}</td>`;
                        html += `<td>${plugin.is_active ? 'Active' : 'Inactive'}</td>`;
                        html += '<td>';
                        html += `<form method="post" style="display:inline;">`;
                        html += `<input type="hidden" name="plugin" value="${plugin.file}">`;
                        if (plugin.is_active) {
                            html +=
                                `<button type="submit" name="deactivate_plugin" class="btn">Deactivate</button>`;
                        } else {
                            html +=
                                `<button type="submit" name="activate_plugin" class="btn">Activate</button>`;
                        }
                        html += `</form>`;
                        html += '</td>';
                        html += '</tr>';
                    });
                    pluginsTable.innerHTML = html;
                }

                function renderPagination(currentPage, totalPages, totalItems) {
                    paginationInfo.textContent =
                        `Showing page ${currentPage} of ${totalPages} (${totalItems} total plugins)`;

                    let controlsHtml = '';

                    // Previous button
                    controlsHtml +=
                        `<button ${currentPage === 1 ? 'disabled' : ''} onclick="loadPluginsPage(${currentPage - 1})">&laquo; Previous</button>`;

                    // Page numbers
                    const startPage = Math.max(1, currentPage - 2);
                    const endPage = Math.min(totalPages, startPage + 4);

                    for (let i = startPage; i <= endPage; i++) {
                        controlsHtml +=
                            `<button class="${i === currentPage ? 'active' : ''}" onclick="loadPluginsPage(${i})">${i}</button>`;
                    }

                    // Next button
                    controlsHtml +=
                        `<button ${currentPage === totalPages ? 'disabled' : ''} onclick="loadPluginsPage(${currentPage + 1})">Next &raquo;</button>`;

                    paginationControls.innerHTML = controlsHtml;
                }

                // Make loadPluginsPage function globally available
                window.loadPluginsPage = loadPluginsPage;

                // Initial load
                loadPluginsPage(currentPage);
            });
        </script>
    </div>
    <?php
}

// Call the function
showPlugins();
