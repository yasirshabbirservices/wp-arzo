<?php
/**
 * Theme Management Feature
 *
 * @package WP_Arzo
 * @version 6.2
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Handle AJAX operations for themes
if (isset($_GET['operation'])) {
    if ($_GET['operation'] === 'get_themes_page') {
        header('Content-Type: application/json');

        if (isset($_GET['page']) && isset($_GET['per_page'])) {
            $page = intval($_GET['page']);
            $per_page = intval($_GET['per_page']);

            $themes = wp_get_themes();
            $total_themes = count($themes);
            $current_theme = wp_get_theme();

            $themes_data = [];
            $theme_slugs = array_keys($themes);
            
            $start = ($page - 1) * $per_page;
            $end = min($start + $per_page, $total_themes);

            for ($i = $start; $i < $end; $i++) {
                if (isset($theme_slugs[$i])) {
                    $slug = $theme_slugs[$i];
                    $theme = $themes[$slug];
                    $is_active = ($theme->get_stylesheet() === $current_theme->get_stylesheet());

                    $themes_data[] = [
                        'slug' => $slug,
                        'name' => $theme->get('Name'),
                        'version' => $theme->get('Version'),
                        'is_active' => $is_active
                    ];
                }
            }

            $response = [
                'success' => true,
                'themes' => $themes_data,
                'total' => $total_themes,
                'total_pages' => ceil($total_themes / $per_page),
                'current_page' => $page
            ];
        } else {
            $response = ['success' => false, 'message' => 'Missing page parameters'];
        }

        echo json_encode($response);
        exit;
    }

    if ($_GET['operation'] === 'activate_theme') {
        header('Content-Type: application/json');

        // Verify CSRF nonce AND capability for this state-changing operation.
        if (!check_ajax_referer('wp_arzo_ajax', 'nonce', false) || !current_user_can('switch_themes')) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            exit;
        }

        $theme_slug = isset($_POST['theme_slug']) ? sanitize_text_field($_POST['theme_slug']) : '';

        if (!$theme_slug) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }

        // Validate the theme exists and is error-free before switching, otherwise
        // switch_theme() can point the site at a non-existent stylesheet.
        $theme = wp_get_theme($theme_slug);
        if (!$theme->exists() || $theme->errors()) {
            echo json_encode(['success' => false, 'message' => 'Theme not found or invalid']);
            exit;
        }

        switch_theme($theme_slug);
        echo json_encode(['success' => true, 'message' => 'Theme activated']);
        exit;
    }
}

function showThemes()
{
    // Installing a new theme from an uploaded ZIP is core WordPress functionality
    // (Appearance → Themes → Add New → Upload Theme); this console intentionally does
    // not duplicate it. This tab only lists and activates themes already installed.
    ?>
    <div class="content">
        <h1>Theme Management</h1>

        <!-- Search -->
        <div class="form-group">
            <input type="text" id="search-themes" class="form-control" placeholder="Search themes..." onkeyup="filterThemesTable()">
        </div>

        <div class="scrollable-table-container">
            <table id="themes-table">
                <thead>
                    <tr>
                        <th>Theme Name</th>
                        <th>Folder/Version</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Theme data will be loaded here via AJAX -->
                    <tr>
                        <td colspan="4" style="text-align: center;">Loading themes...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="themes-pagination" class="pagination-container">
            <div class="pagination-info">Loading...</div>
            <div class="pagination-controls">
                <!-- Pagination controls will be added here -->
            </div>
        </div>

        <script>
            // Themes functionality
            document.addEventListener('DOMContentLoaded', function () {
                let currentPage = 1;
                const perPage = 10;
                const themesTable = document.getElementById('themes-table').querySelector('tbody');
                const paginationInfo = document.querySelector('#themes-pagination .pagination-info');
                const paginationControls = document.querySelector('#themes-pagination .pagination-controls');

                function loadThemesPage(page) {
                    const baseUrl = '<?php echo esc_url(admin_url('admin-ajax.php?action=wp_arzo_standalone')); ?>';
                    fetch(
                        `${baseUrl}&tab=themes&operation=get_themes_page&page=${page}&per_page=${perPage}&nocache=${new Date().getTime()}`
                    )
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                renderThemesTable(data.themes);
                                renderPagination(data.current_page, data.total_pages, data.total);
                            } else {
                                themesTable.innerHTML =
                                    `<tr><td colspan="4" style="text-align: center;">Error: ${data.message}</td></tr>`;
                            }
                        })
                        .catch(error => {
                            themesTable.innerHTML =
                                `<tr><td colspan="4" style="text-align: center;">Error loading themes: ${error.message}</td></tr>`;
                        });
                }

                function renderThemesTable(themes) {
                    if (themes.length === 0) {
                        themesTable.innerHTML =
                            '<tr><td colspan="4" style="text-align: center;">No themes found</td></tr>';
                        return;
                    }

                    let html = '';
                    themes.forEach(theme => {
                        html += `<tr>`;
                        html += `<td>${theme.name}</td>`;
                        html += `<td><small>${theme.slug}<br>v${theme.version}</small></td>`;
                        html += `<td><span class="badge ${theme.is_active ? 'badge-active' : 'badge-inactive'}">${theme.is_active ? 'ACTIVE' : 'INACTIVE'}</span></td>`;
                        html += '<td>';
                        
                        html += `<div style="display:flex; align-items:center;">`;
                        html += `<label class="switch">`;
                        // If active, checked and disabled (cannot deactivate a theme, only switch)
                        html += `<input type="checkbox" onchange="activateTheme('${theme.slug}', this)" ${theme.is_active ? 'checked disabled' : ''}>`;
                        html += `<span class="slider round"></span>`;
                        html += `</label>`;
                        html += `<span class="toggle-label" style="margin-left:10px;">${theme.is_active ? 'Active' : 'Activate'}</span>`;
                        html += `</div>`;
                        
                        html += '</td>';
                        html += '</tr>';
                    });
                    themesTable.innerHTML = html;
                }

                window.activateTheme = function(themeSlug, checkbox) {
                    if(!confirm('Activate this theme?')) {
                        if(checkbox) checkbox.checked = !checkbox.checked;
                        return;
                    }

                    const formData = new FormData();
                    formData.append('theme_slug', themeSlug);
                    formData.append('nonce', '<?php echo esc_js(wp_create_nonce('wp_arzo_ajax')); ?>');

                    fetch('<?php echo esc_url(admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=themes&operation=activate_theme')); ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if(data.success) {
                            alert('Theme activated!');
                            loadThemesPage(currentPage); // Reload to reflect changes
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Request failed');
                    });
                };

                function renderPagination(currentPage, totalPages, totalItems) {
                    paginationInfo.textContent =
                        `Showing page ${currentPage} of ${totalPages} (${totalItems} total themes)`;

                    let controlsHtml = '';
                    controlsHtml += `<button ${currentPage === 1 ? 'disabled' : ''} onclick="loadThemesPage(${currentPage - 1})">&laquo; Previous</button>`;
                    
                    const startPage = Math.max(1, currentPage - 2);
                    const endPage = Math.min(totalPages, startPage + 4);

                    for (let i = startPage; i <= endPage; i++) {
                        controlsHtml += `<button class="${i === currentPage ? 'active' : ''}" onclick="loadThemesPage(${i})">${i}</button>`;
                    }

                    controlsHtml += `<button ${currentPage === totalPages ? 'disabled' : ''} onclick="loadThemesPage(${currentPage + 1})">Next &raquo;</button>`;
                    paginationControls.innerHTML = controlsHtml;
                }

                window.loadThemesPage = loadThemesPage;
                
                // Client-side search for current page
                window.filterThemesTable = function() {
                    const input = document.getElementById('search-themes');
                    const filter = input.value.toUpperCase();
                    const tr = themesTable.getElementsByTagName('tr');
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
                loadThemesPage(currentPage);
            });
        </script>
    </div>
    <?php
}

// Call the function
showThemes();
