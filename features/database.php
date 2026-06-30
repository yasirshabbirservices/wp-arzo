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
        $page = max(1, intval($_GET['page']));
        // Guard against division-by-zero (fatal on PHP 8) when per_page is 0/blank.
        $per_page = max(1, intval($_GET['per_page']));

        $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
        $total_tables = count($tables);

        $tables_data = [];
        $start = ($page - 1) * $per_page;
        $end = min($start + $per_page, $total_tables);

        for ($i = $start; $i < $end; $i++) {
            if (isset($tables[$i])) {
                $table_name = $tables[$i][0];
                // Backtick the identifier so reserved-word table names don't error.
                $count = $wpdb->get_var('SELECT COUNT(*) FROM `' . str_replace('`', '``', $table_name) . '`');
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

    // Prefer the bundled AdminNeo database manager when present. It runs WP-gated in an
    // iframe (assets/libs/adminneo/loader.php), giving a full browse/edit/export/SQL UI.
    // The lightweight viewer below remains as a fallback if the library is removed.
    if (file_exists(WP_ARZO_PLUGIN_DIR . 'assets/libs/adminneo/adminneo.php') && defined('WP_ARZO_PLUGIN_FILE')) {
        $wp_arzo_db_src = plugins_url('assets/libs/adminneo/loader.php', WP_ARZO_PLUGIN_FILE);
        ?>
        <div class="content">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:12px;">
                <h2 style="margin:0; border:none; padding:0;">Database Manager <span style="font-size:12px; color:var(--muted-text); font-weight:400;">powered by AdminNeo</span></h2>
                <a class="btn btn-sm" href="<?php echo esc_url($wp_arzo_db_src); ?>" target="_blank" rel="noopener">Open full screen</a>
            </div>
            <iframe title="AdminNeo database manager" src="<?php echo esc_url($wp_arzo_db_src); ?>"
                style="width:100%; height:80vh; border:1px solid var(--border-color); border-radius:var(--radius-global); background:#fff;"></iframe>
        </div>
        <?php
        return;
    }

    if (isset($_POST['execute_query'])) {
        // CSRF protection: this endpoint can run arbitrary SQL, so it must be
        // protected against forged cross-site requests even though the page is
        // already gated by manage_options.
        if (!current_user_can('manage_options') ||
            !isset($_POST['wp_arzo_db_nonce']) ||
            !wp_verify_nonce(wp_unslash($_POST['wp_arzo_db_nonce']), 'wp_arzo_db_query')) {
            echo '<div class="error">Security check failed. Please reload the page and try again.</div>';
        } else {
            // wp_unslash() so queries containing quotes are not corrupted by the
            // slashes WordPress adds to superglobals.
            $query = wp_unslash($_POST['query']);
            $result = $wpdb->query($query);

            if ($result !== false) {
                echo '<div class="success">Query executed successfully. Affected rows: ' . esc_html($result) . '</div>';
            } else {
                echo '<div class="error">Query failed: ' . esc_html($wpdb->last_error) . '</div>';
            }
        }
    }

    ?>
    <div class="content">
        <h2>Database Access</h2>

        <h3>Execute Query</h3>
        <form method="post">
            <?php wp_nonce_field('wp_arzo_db_query', 'wp_arzo_db_nonce'); ?>
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
