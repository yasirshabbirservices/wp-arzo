/**
 * WP Arzo JavaScript
 * All JavaScript functionality for the WordPress Maintenance Tool
 *
 * @package WP_Arzo
 * @version 5.1
 */

// Configuration object (will be populated by PHP via wp_localize_script pattern)
var wpArzoConfig = wpArzoConfig || {
    ajaxUrl: '',
    adminUrl: '',
    pluginUrl: '',
    nonce: ''
};

// ============================================================================
// LIGHTBOX FUNCTIONALITY
// ============================================================================

function openLightbox() {
    document.getElementById('fileLightbox').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    document.getElementById('fileLightbox').classList.remove('active');
    document.body.style.overflow = 'auto';
}

// Create User lightbox functionality
function showCreateUserLightbox() {
    document.getElementById('createUserLightbox').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeCreateUserLightbox() {
    document.getElementById('createUserLightbox').classList.remove('active');
    document.body.style.overflow = 'auto';
}

// Frontend instructions lightbox functionality
function showFrontendInstructions() {
    document.getElementById('frontend-instructions-lightbox').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeFrontendInstructions() {
    document.getElementById('frontend-instructions-lightbox').classList.remove('active');
    document.body.style.overflow = 'auto';
}

// ============================================================================
// EVENT LISTENERS
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
    // Close lightbox when clicking outside
    const fileLightbox = document.getElementById('fileLightbox');
    if (fileLightbox) {
        fileLightbox.addEventListener('click', function(e) {
            if (e.target === this) {
                closeLightbox();
            }
        });
    }

    // Close Create User lightbox when clicking outside
    const createUserLightbox = document.getElementById('createUserLightbox');
    if (createUserLightbox) {
        createUserLightbox.addEventListener('click', function(e) {
            if (e.target === this) {
                closeCreateUserLightbox();
            }
        });
    }

    // Close frontend instructions lightbox when clicking outside
    const frontendLightbox = document.getElementById('frontend-instructions-lightbox');
    if (frontendLightbox) {
        frontendLightbox.addEventListener('click', function(e) {
            if (e.target === this) {
                closeFrontendInstructions();
            }
        });
    }

    // Close lightbox with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeLightbox();
            closeFrontendInstructions();
            closeCreateUserLightbox();
        }
    });
});

// ============================================================================
// FILE OPERATIONS
// ============================================================================

function viewFile(filePath) {
    const url = new URL(wpArzoConfig.ajaxUrl);
    url.searchParams.set('action', 'wp_arzo_standalone');
    url.searchParams.set('tab', 'files');
    url.searchParams.set('operation', 'view_file');
    url.searchParams.set('file', filePath);

    fetch(url.toString())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('lightboxTitle').textContent = 'Viewing: ' + data.filename;
                document.getElementById('lightboxBody').innerHTML = data.content;
                document.getElementById('lightboxActions').innerHTML = data.actions;
                openLightbox();
            } else {
                console.log('Debug info:', data.debug);
                alert('Error: ' + data.message + (data.debug ? '\nCheck console for debug info' : ''));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading file');
        });
}

function editFile(filePath) {
    const url = new URL(wpArzoConfig.ajaxUrl);
    url.searchParams.set('action', 'wp_arzo_standalone');
    url.searchParams.set('tab', 'files');
    url.searchParams.set('operation', 'edit_file');
    url.searchParams.set('file', filePath);

    fetch(url.toString())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('lightboxTitle').textContent = 'Editing: ' + data.filename;
                document.getElementById('lightboxBody').innerHTML = data.content;
                document.getElementById('lightboxActions').innerHTML = data.actions;
                openLightbox();
            } else {
                console.log('Debug info:', data.debug);
                alert('Error: ' + data.message + (data.debug ? '\nCheck console for debug info' : ''));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading file for editing');
        });
}

function saveFile(filePath) {
    const content = document.getElementById('fileContentEditor').value;
    const formData = new FormData();
    formData.append('file_path', filePath);
    formData.append('file_content', content);
    formData.append('save_file', '1');

    const url = new URL(wpArzoConfig.ajaxUrl);
    url.searchParams.set('action', 'wp_arzo_standalone');
    url.searchParams.set('tab', 'files');
    url.searchParams.set('operation', 'save_file');

    fetch(url.toString(), {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('File saved successfully!');
                closeLightbox();
                location.reload();
            } else {
                alert('Error saving file: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error saving file');
        });
}

// ============================================================================
// DEBUG FUNCTIONALITY
// ============================================================================

function logDebugChange(settingName, newValue) {
    const timestamp = new Date().toISOString();
    const logEntry = `[${timestamp}] Debug setting '${settingName}' changed to: ${newValue ? 'enabled' : 'disabled'}`;

    const formData = new FormData();
    formData.append('log_entry', logEntry);
    formData.append('setting_name', settingName);
    formData.append('new_value', newValue ? '1' : '0');
    formData.append('nonce', wpArzoConfig.nonce);

    const url = new URL(wpArzoConfig.ajaxUrl);
    url.searchParams.set('action', 'wp_arzo_standalone');
    url.searchParams.set('tab', 'debug');
    url.searchParams.set('operation', 'log_debug_change');

    fetch(url.toString(), {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Debug change logged successfully');
            } else {
                console.error('Failed to log debug change:', data.message);
            }
        })
        .catch(error => {
            console.error('Error logging debug change:', error);
        });
}

function copyDebugLog() {
    const logContent = document.getElementById('debug-log-content').innerText;
    const textarea = document.createElement('textarea');
    textarea.value = logContent;
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);

    const copyIcon = document.querySelector('.fa-copy');
    const originalColor = copyIcon.style.color;

    copyIcon.classList.remove('fa-copy');
    copyIcon.classList.add('fa-check');
    copyIcon.style.color = 'var(--success-color)';

    setTimeout(function() {
        copyIcon.classList.remove('fa-check');
        copyIcon.classList.add('fa-copy');
        copyIcon.style.color = originalColor;
    }, 1500);
}

function clearDebugLog() {
    if (confirm('Are you sure you want to clear the debug log?')) {
        const url = new URL(wpArzoConfig.ajaxUrl);
        url.searchParams.set('action', 'wp_arzo_standalone');
        url.searchParams.set('tab', 'debug');
        url.searchParams.set('operation', 'clear_debug_log');

        const clearFormData = new FormData();
        clearFormData.append('nonce', wpArzoConfig.nonce);

        fetch(url.toString(), {
                method: 'POST',
                body: clearFormData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('debug-log-content').innerHTML =
                        '<div style="color: #28a745; margin-bottom: 2px; padding: 2px 8px; border-left: 3px solid #28a745; padding-left: 12px;">Debug log has been cleared.</div>';

                    const message = document.createElement('div');
                    message.textContent = 'Debug log cleared successfully!';
                    message.style.cssText = 'position:fixed;top:20px;left:50%;transform:translateX(-50%);padding:10px 20px;background:var(--accent-color);color:#fff;border-radius:3px;z-index:9999;';
                    document.body.appendChild(message);

                    setTimeout(function() {
                        document.body.removeChild(message);
                    }, 2000);
                } else {
                    alert('Error clearing debug log: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error clearing debug log');
            });
    }
}

// ============================================================================
// USERS PAGINATION
// ============================================================================

if (typeof usersPageFunctions !== 'undefined') {
    const perPage = 10;
    const usersTable = document.getElementById('users-table') ? document.getElementById('users-table').querySelector('tbody') : null;
    const paginationInfo = document.querySelector('#users-pagination .pagination-info');
    const paginationControls = document.querySelector('#users-pagination .pagination-controls');

    function loadUsersPage(page) {
        const url = new URL(wpArzoConfig.ajaxUrl);
        url.searchParams.set('action', 'wp_arzo_standalone');
        url.searchParams.set('tab', 'ajax');
        url.searchParams.set('operation', 'get_users_page');
        url.searchParams.set('page', page);
        url.searchParams.set('per_page', perPage);
        url.searchParams.set('nocache', new Date().getTime());

        fetch(url.toString())
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderUsersTable(data.users);
                    renderPagination(data.current_page, data.total_pages, data.total);
                } else {
                    usersTable.innerHTML = `<tr><td colspan="6" style="text-align: center;">Error: ${data.message}</td></tr>`;
                }
            })
            .catch(error => {
                console.error('Error loading users:', error);
                usersTable.innerHTML = '<tr><td colspan="6" style="text-align: center;">Error loading users</td></tr>';
            });
    }

    function renderUsersTable(users) {
        if (!users || users.length === 0) {
            usersTable.innerHTML = '<tr><td colspan="6" style="text-align: center;">No users found</td></tr>';
            return;
        }

        usersTable.innerHTML = '';
        users.forEach(user => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${user.ID}</td>
                <td>${user.user_login}</td>
                <td>${user.user_email}</td>
                <td>${user.display_name}</td>
                <td><span class="badge">${user.roles.join(', ')}</span></td>
                <td class="actions">
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="user_id" value="${user.ID}">
                        <button type="submit" name="login_as_user" class="btn btn-view">Login as User</button>
                    </form>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="user_id" value="${user.ID}">
                        <button type="submit" name="delete_user" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this user?');">Delete</button>
                    </form>
                </td>
            `;
            usersTable.appendChild(row);
        });
    }

    function renderPagination(currentPage, totalPages, totalUsers) {
        if (paginationInfo) {
            paginationInfo.textContent = `Showing page ${currentPage} of ${totalPages} (${totalUsers} total users)`;
        }

        if (paginationControls) {
            paginationControls.innerHTML = '';

            if (currentPage > 1) {
                const prevBtn = document.createElement('button');
                prevBtn.textContent = '← Previous';
                prevBtn.className = 'btn btn-secondary';
                prevBtn.onclick = () => loadUsersPage(currentPage - 1);
                paginationControls.appendChild(prevBtn);
            }

            if (currentPage < totalPages) {
                const nextBtn = document.createElement('button');
                nextBtn.textContent = 'Next →';
                nextBtn.className = 'btn btn-secondary';
                nextBtn.onclick = () => loadUsersPage(currentPage + 1);
                paginationControls.appendChild(nextBtn);
            }
        }
    }

    // Auto-load first page
    if (usersTable) {
        loadUsersPage(1);
    }
}

// ============================================================================
// DATABASE TABLES PAGINATION
// ============================================================================

if (typeof tablesPageFunctions !== 'undefined') {
    const perPage = 10;
    const tablesTable = document.getElementById('db-tables') ? document.getElementById('db-tables').querySelector('tbody') : null;
    const paginationInfo = document.querySelector('#tables-pagination .pagination-info');
    const paginationControls = document.querySelector('#tables-pagination .pagination-controls');

    function loadTablesPage(page) {
        const url = new URL(wpArzoConfig.ajaxUrl);
        url.searchParams.set('action', 'wp_arzo_standalone');
        url.searchParams.set('tab', 'database');
        url.searchParams.set('operation', 'get_db_tables_page');
        url.searchParams.set('page', page);
        url.searchParams.set('per_page', perPage);
        url.searchParams.set('nocache', new Date().getTime());

        fetch(url.toString())
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderTablesTable(data.tables);
                    renderTablesPagination(data.current_page, data.total_pages, data.total);
                } else {
                    tablesTable.innerHTML = `<tr><td colspan="2" style="text-align: center;">Error: ${data.message}</td></tr>`;
                }
            })
            .catch(error => {
                console.error('Error loading tables:', error);
                tablesTable.innerHTML = '<tr><td colspan="2" style="text-align: center;">Error loading tables</td></tr>';
            });
    }

    function renderTablesTable(tables) {
        if (!tables || tables.length === 0) {
            tablesTable.innerHTML = '<tr><td colspan="2" style="text-align: center;">No tables found</td></tr>';
            return;
        }

        tablesTable.innerHTML = '';
        tables.forEach(table => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${table.table_name}</td>
                <td>${table.rows}</td>
            `;
            tablesTable.appendChild(row);
        });
    }

    function renderTablesPagination(currentPage, totalPages, totalTables) {
        if (paginationInfo) {
            paginationInfo.textContent = `Showing page ${currentPage} of ${totalPages} (${totalTables} total tables)`;
        }

        if (paginationControls) {
            paginationControls.innerHTML = '';

            if (currentPage > 1) {
                const prevBtn = document.createElement('button');
                prevBtn.textContent = '← Previous';
                prevBtn.className = 'btn btn-secondary';
                prevBtn.onclick = () => loadTablesPage(currentPage - 1);
                paginationControls.appendChild(prevBtn);
            }

            if (currentPage < totalPages) {
                const nextBtn = document.createElement('button');
                nextBtn.textContent = 'Next →';
                nextBtn.className = 'btn btn-secondary';
                nextBtn.onclick = () => loadTablesPage(currentPage + 1);
                paginationControls.appendChild(nextBtn);
            }
        }
    }

    // Auto-load first page
    if (tablesTable) {
        loadTablesPage(1);
    }
}

// ============================================================================
// PLUGINS PAGINATION
// ============================================================================

if (typeof pluginsPageFunctions !== 'undefined') {
    const perPage = 10;
    const pluginsTable = document.getElementById('plugins-table') ? document.getElementById('plugins-table').querySelector('tbody') : null;
    const paginationInfo = document.querySelector('#plugins-pagination .pagination-info');
    const paginationControls = document.querySelector('#plugins-pagination .pagination-controls');

    function loadPluginsPage(page) {
        const url = new URL(wpArzoConfig.ajaxUrl);
        url.searchParams.set('action', 'wp_arzo_standalone');
        url.searchParams.set('tab', 'plugins');
        url.searchParams.set('operation', 'get_plugins_page');
        url.searchParams.set('page', page);
        url.searchParams.set('per_page', perPage);
        url.searchParams.set('nocache', new Date().getTime());

        fetch(url.toString())
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderPluginsTable(data.plugins);
                    renderPluginsPagination(data.current_page, data.total_pages, data.total);
                } else {
                    pluginsTable.innerHTML = `<tr><td colspan="4" style="text-align: center;">Error: ${data.message}</td></tr>`;
                }
            })
            .catch(error => {
                console.error('Error loading plugins:', error);
                pluginsTable.innerHTML = '<tr><td colspan="4" style="text-align: center;">Error loading plugins</td></tr>';
            });
    }

    function renderPluginsTable(plugins) {
        if (!plugins || plugins.length === 0) {
            pluginsTable.innerHTML = '<tr><td colspan="4" style="text-align: center;">No plugins found</td></tr>';
            return;
        }

        pluginsTable.innerHTML = '';
        plugins.forEach(plugin => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${plugin.name}</td>
                <td>${plugin.version}</td>
                <td><span class="badge ${plugin.is_active ? 'success' : 'secondary'}">${plugin.is_active ? 'Active' : 'Inactive'}</span></td>
                <td class="actions">
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="plugin_file" value="${plugin.file}">
                        ${plugin.is_active ?
                            '<button type="submit" name="deactivate_plugin" class="btn btn-danger">Deactivate</button>' :
                            '<button type="submit" name="activate_plugin" class="btn btn-success">Activate</button>'
                        }
                    </form>
                </td>
            `;
            pluginsTable.appendChild(row);
        });
    }

    function renderPluginsPagination(currentPage, totalPages, totalPlugins) {
        if (paginationInfo) {
            paginationInfo.textContent = `Showing page ${currentPage} of ${totalPages} (${totalPlugins} total plugins)`;
        }

        if (paginationControls) {
            paginationControls.innerHTML = '';

            if (currentPage > 1) {
                const prevBtn = document.createElement('button');
                prevBtn.textContent = '← Previous';
                prevBtn.className = 'btn btn-secondary';
                prevBtn.onclick = () => loadPluginsPage(currentPage - 1);
                paginationControls.appendChild(prevBtn);
            }

            if (currentPage < totalPages) {
                const nextBtn = document.createElement('button');
                nextBtn.textContent = 'Next →';
                nextBtn.className = 'btn btn-secondary';
                nextBtn.onclick = () => loadPluginsPage(currentPage + 1);
                paginationControls.appendChild(nextBtn);
            }
        }
    }

    // Auto-load first page
    if (pluginsTable) {
        loadPluginsPage(1);
    }
}
