<?php
/**
 * Database Viewer Feature
 *
 * @package WP_Arzo
 * @version 5.1
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Handle AJAX operations for database
if (isset($_GET['operation']) && $_GET['operation'] === 'get_db_tables_page') {
    header('Content-Type: application/json');

    if (isset($_GET['page']) && isset($_GET['per_page'])) {
        global $wpdb;
        $page = intval($_GET['page']);
        $per_page = intval($_GET['per_page']);

        $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
        $total_tables = count($tables);

        $tables_data = [];
        $start = ($page - 1) * $per_page;
        $end = min($start + $per_page, $total_tables);

        for ($i = $start; $i < $end; $i++) {
            if (isset($tables[$i])) {
                $table_name = $tables[$i][0];
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                $tables_data[] = [
                    'name' => $table_name,
                    'rows' => $count
                ];
            }
        }

        $response = [
            'success' => true,
            'tables' => $tables_data,
            'total' => $total_tables,
            'total_pages' => ceil($total_tables / $per_page),
            'current_page' => $page
        ];
    } else {
        $response = ['success' => false, 'message' => 'Missing page parameters'];
    }

    echo json_encode($response);
    exit;
}

function handleDatabase()
{
    global $wpdb;

    if (isset($_POST['execute_query'])) {
        $query = $_POST['query'];
        // Basic security check - in a real world scenario this should be more robust
        // but this is an admin maintenance tool so we assume capability checks are done by WP core/plugin loader
        $result = $wpdb->query($query);

        if ($result !== false) {
            echo '<div class="success">Query executed successfully. Affected rows: ' . $result . '</div>';
        } else {
            echo '<div class="error">Query failed: ' . $wpdb->last_error . '</div>';
        }
    }

    ?>
    <div class="content">
        <h2>Database Access</h2>

        <h3>Execute Query</h3>
        <form method="post">
            <div class="form-group">
                <label>SQL Query:</label>
                <textarea name="query" rows="5" placeholder="SELECT * FROM wp_users LIMIT 10"></textarea>
            </div>
            <button type="submit" name="execute_query" class="btn">Execute Query</button>
        </form>

        <h3>Database Tables</h3>
        <div class="scrollable-table-container">
            <table id="db-tables">
                <thead>
                    <tr>
                        <th>Table Name</th>
                        <th>Rows</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Table data will be loaded here via AJAX -->
                    <tr>
                        <td colspan="2" style="text-align: center;">Loading tables...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="tables-pagination" class="pagination-container">
            <div class="pagination-info">Loading...</div>
            <div class="pagination-controls">
                <!-- Pagination controls will be added here -->
            </div>
        </div>

        <script>
            // Database tables pagination functionality
            document.addEventListener('DOMContentLoaded', function () {
                let currentPage = 1;
                const perPage = 10;
                const tablesTable = document.getElementById('db-tables').querySelector('tbody');
                const paginationInfo = document.querySelector('#tables-pagination .pagination-info');
                const paginationControls = document.querySelector('#tables-pagination .pagination-controls');

                function loadTablesPage(page) {
                    const baseUrl = '<?php echo admin_url('admin-ajax.php?action=wp_arzo_standalone'); ?>';
                    fetch(
                        `${baseUrl}&tab=database&operation=get_db_tables_page&page=${page}&per_page=${perPage}&nocache=${new Date().getTime()}`
                    )
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                renderTablesTable(data.tables);
                                renderPagination(data.current_page, data.total_pages, data.total);
                            } else {
                                tablesTable.innerHTML =
                                    `<tr><td colspan="2" style="text-align: center;">Error: ${data.message}</td></tr>`;
                            }
                        })
                        .catch(error => {
                            tablesTable.innerHTML =
                                `<tr><td colspan="2" style="text-align: center;">Error loading tables: ${error.message}</td></tr>`;
                        });
                }

                function renderTablesTable(tables) {
                    if (tables.length === 0) {
                        tablesTable.innerHTML =
                            '<tr><td colspan="2" style="text-align: center;">No tables found</td></tr>';
                        return;
                    }

                    let html = '';
                    tables.forEach(table => {
                        html += `<tr>`;
                        html += `<td>${table.name}</td>`;
                        html += `<td>${table.rows}</td>`;
                        html += '</tr>';
                    });
                    tablesTable.innerHTML = html;
                }

                function renderPagination(currentPage, totalPages, totalItems) {
                    paginationInfo.textContent =
                        `Showing page ${currentPage} of ${totalPages} (${totalItems} total tables)`;

                    let controlsHtml = '';

                    // Previous button
                    controlsHtml +=
                        `<button ${currentPage === 1 ? 'disabled' : ''} onclick="loadTablesPage(${currentPage - 1})">&laquo; Previous</button>`;

                    // Page numbers
                    const startPage = Math.max(1, currentPage - 2);
                    const endPage = Math.min(totalPages, startPage + 4);

                    for (let i = startPage; i <= endPage; i++) {
                        controlsHtml +=
                            `<button class="${i === currentPage ? 'active' : ''}" onclick="loadTablesPage(${i})">${i}</button>`;
                    }

                    // Next button
                    controlsHtml +=
                        `<button ${currentPage === totalPages ? 'disabled' : ''} onclick="loadTablesPage(${currentPage + 1})">Next &raquo;</button>`;

                    paginationControls.innerHTML = controlsHtml;
                }

                // Make loadTablesPage function globally available
                window.loadTablesPage = loadTablesPage;

                // Initial load
                loadTablesPage(currentPage);
            });
        </script>
    </div>
    <?php
}

// Call the function
handleDatabase();
