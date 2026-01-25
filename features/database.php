<?php
/**
 * Database Management Feature
 *
 * @package WP_Arzo
 * @version 5.1
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

function handleDatabase()
{
    global $wpdb;

    if (isset($_POST['execute_query'])) {
        $query = $_POST['query'];
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
    document.addEventListener('DOMContentLoaded', function() {
        let currentPage = 1;
        const perPage = 10;
        const tablesTable = document.getElementById('db-tables').querySelector('tbody');
        const paginationInfo = document.querySelector('#tables-pagination .pagination-info');
        const paginationControls = document.querySelector('#tables-pagination .pagination-controls');

        function loadTablesPage(page) {
            const baseUrl = '<?php echo admin_url('admin-ajax.php?action=wp_arzo_standalone'); ?>';
            fetch(
                    `${baseUrl}&tab=ajax&operation=get_db_tables_page&page=${page}&per_page=${perPage}&nocache=${new Date().getTime()}`
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
