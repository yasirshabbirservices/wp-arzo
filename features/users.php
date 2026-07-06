<?php

/**
 * Users Management Feature
 *
 * @package WP_Arzo
 * @version 6.2
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Handle AJAX operations for users
if (isset($_GET['operation'])) {
    if ($_GET['operation'] === 'get_users_page') {
        header('Content-Type: application/json');

        if (isset($_GET['page']) && isset($_GET['per_page'])) {
            $page = intval($_GET['page']);
            $per_page = intval($_GET['per_page']);
            $offset = ($page - 1) * $per_page;

            $args = [
                'number' => $per_page,
                'offset' => $offset,
            ];

            $user_query = new WP_User_Query($args);
            $users = $user_query->get_results();
            $total_users = $user_query->get_total();

            $users_data = [];
            $current_user_id = get_current_user_id();

            foreach ($users as $user) {
                $users_data[] = [
                    'id' => $user->ID,
                    'username' => $user->user_login,
                    'email' => $user->user_email,
                    'roles' => implode(', ', $user->roles),
                    // Cast both sides: WP_User->ID can be a numeric string, so a
                    // strict === would wrongly mark the current user as "not current".
                    'is_current' => ((int) $user->ID === (int) $current_user_id)
                ];
            }

            $response = [
                'success' => true,
                'users' => $users_data,
                'total' => $total_users,
                'total_pages' => ceil($total_users / $per_page),
                'current_page' => $page
            ];
        } else {
            $response = ['success' => false, 'message' => 'Missing page parameters'];
        }

        echo json_encode($response);
        exit;
    }
}

function handleUsers()
{
    // CSRF guard for all state-changing user actions (create / delete / impersonate).
    $is_user_action = isset($_POST['create_user']) || isset($_POST['delete_user']) || isset($_POST['quick_login']);
    if ($is_user_action) {
        if (!current_user_can('manage_options') ||
            !isset($_POST['wp_arzo_user_nonce']) ||
            !wp_verify_nonce(wp_unslash($_POST['wp_arzo_user_nonce']), 'wp_arzo_user_action')) {
            echo '<div class="error">Security check failed. Please reload the page and try again.</div>';
            return;
        }
    }

    if (isset($_POST['create_user'])) {
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        // Whitelist the role so arbitrary/empty role strings can't be injected.
        $allowed_roles = ['administrator', 'editor', 'author', 'contributor', 'subscriber'];
        $role = in_array($_POST['role'] ?? '', $allowed_roles, true) ? $_POST['role'] : 'subscriber';

        if (!username_exists($username) && !email_exists($email)) {
            $user_id = wp_create_user($username, $password, $email);
            if (!is_wp_error($user_id)) {
                $user = new WP_User($user_id);
                $user->set_role($role);
                echo '<div class="success">User created successfully!</div>';
            } else {
                echo '<div class="error">Error creating user: ' . esc_html($user_id->get_error_message()) . '</div>';
            }
        } else {
            echo '<div class="error">Username or email already exists!</div>';
        }
    }

    if (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id']);
        if (wp_delete_user($user_id)) {
            echo '<div class="success">User deleted successfully!</div>';
        } else {
            echo '<div class="error">Error deleting user!</div>';
        }
    }

    if (isset($_POST['quick_login'])) {
        $user_id = intval($_POST['user_id']);
        $user = get_user_by('id', $user_id);

        if ($user) {
            // Set authentication cookies
            wp_set_current_user($user_id, $user->user_login);
            wp_set_auth_cookie($user_id);
            do_action('wp_login', $user->user_login, $user);

            // Create log entry (only if the log directory is writable, so we don't
            // emit a PHP warning into the response on read-only hosts).
            $timestamp = gmdate('Y-m-d H:i:s');
            $log_entry = "[{$timestamp}] Quick login performed for user: {$user->user_login} (ID: {$user_id})\n";
            $log_file = WP_CONTENT_DIR . '/debug.log';
            if (wp_is_writable($log_file) || (!file_exists($log_file) && wp_is_writable(WP_CONTENT_DIR))) {
                @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            }

            echo '<div class="success">Successfully logged in as ' . esc_html($user->user_login) . '! Opening WordPress Admin in new tab...
</div>';
            echo '<script>
window.open("' . esc_url(admin_url()) . '", "_blank");
</script>';
        } else {
            echo '<div class="error">User not found!</div>';
        }
    }

?>
    <div class="content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>User Management</h1>
            <button type="button" onclick="showCreateUserLightbox()" class="btn btn-primary"><?php wp_arzo_icon_e('users', ['size' => 16]); ?> Create New User</button>
        </div>

        <h3>Existing Users</h3>
        <div
            style="background: var(--arzo-bg-hover); padding: 15px; border-radius: var(--radius-global); border-left: 4px solid var(--accent-color); margin-bottom: 20px;">
            <?php
            $current_user = wp_get_current_user();
            if ($current_user->ID) {
                echo '<p><strong>Currently logged in as:</strong> ' . esc_html($current_user->user_login) . ' (' . esc_html(implode(', ', $current_user->roles)) . ') | <a href="' . esc_url(admin_url()) . '" target="_blank" style="color: var(--accent-color);">Dashboard</a></p>';
            } else {
                echo '<p><strong>Status:</strong> Not currently logged in to WordPress</p>';
            }
            ?>
        </div>

        <!-- Search -->
        <div class="form-group">
            <input type="text" id="search-users" class="form-control" placeholder="Search users..." onkeyup="filterUsersTable()">
        </div>

        <div class="scrollable-table-container">
            <table id="users-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- User data will be loaded here via AJAX -->
                    <tr>
                        <td colspan="6" style="text-align: center;">Loading users...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="users-pagination" class="pagination-container">
            <div class="pagination-info">Loading...</div>
            <div class="pagination-controls">
                <!-- Pagination controls will be added here -->
            </div>
        </div>

        <script>
            // Users functionality
            document.addEventListener('DOMContentLoaded', function() {
                let currentPage = 1;
                const perPage = 10;
                const usersTable = document.getElementById('users-table').querySelector('tbody');
                const paginationInfo = document.querySelector('#users-pagination .pagination-info');
                const paginationControls = document.querySelector('#users-pagination .pagination-controls');

                function loadUsersPage(page) {
                    const baseUrl = '<?php echo esc_url(admin_url('admin-ajax.php?action=wp_arzo_standalone')); ?>';
                    fetch(
                            `${baseUrl}&tab=users&operation=get_users_page&page=${page}&per_page=${perPage}&nocache=${new Date().getTime()}`
                        )
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! Status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                renderUsersTable(data.users);
                                renderPagination(data.current_page, data.total_pages, data.total);
                            } else {
                                usersTable.innerHTML =
                                    `<tr><td colspan="6" style="text-align: center;">Error: ${data.message}</td></tr>`;
                            }
                        })
                        .catch(error => {
                            usersTable.innerHTML =
                                `<tr><td colspan="6" style="text-align: center;">Error loading users: ${error.message}</td></tr>`;
                        });
                }

                const userActionNonce = '<?php echo esc_js(wp_create_nonce('wp_arzo_user_action')); ?>';

                function renderUsersTable(users) {
                    if (users.length === 0) {
                        usersTable.innerHTML =
                            '<tr><td colspan="6" style="text-align: center;">No users found</td></tr>';
                        return;
                    }

                    let html = '';
                    users.forEach(user => {
                        const rowClass = user.is_current ?
                            'style="background-color: rgba(22, 231, 145, 0.1);"' : '';
                        html += `<tr ${rowClass}>`;
                        html += `<td>${user.id}</td>`;
                        html += `<td>${user.username}${user.is_current ? ' <span style="color: var(--accent-color);" title="Current User"><?php wp_arzo_icon_e('user', ['size' => 14]); ?></span>' : ''}</td>`;
                        html += `<td>${user.email}</td>`;
                        html += `<td>${user.roles}</td>`;
                        html += `<td>${user.is_current ? '<span style="color: var(--accent-color); font-weight: bold;">Logged In</span>' : '<span style="color: var(--arzo-text-secondary);">Offline</span>'}</td>`;
                        html += '<td style="white-space: nowrap;">';

                        // Quick Login Button
                        if (!user.is_current) {
                            html += `<form method="post" style="display:inline; margin-right: 5px;">`;
                            html += `<input type="hidden" name="wp_arzo_user_nonce" value="${userActionNonce}">`;
                            html += `<input type="hidden" name="user_id" value="${user.id}">`;
                            html +=
                                `<button type="submit" name="quick_login" class="btn btn-sm btn-success" title="Login as ${user.username}"><?php wp_arzo_icon_e('login', ['size' => 14]); ?> Login</button>`;
                            html += `</form>`;
                        } else {
                            html +=
                                `<span style="color: var(--accent-color); font-size: 12px; margin-right: 5px;"><?php wp_arzo_icon_e('check-circle', ['size' => 14]); ?> Active</span>`;
                        }

                        // Delete Button
                        if (!user.is_current) {
                            html += `<form method="post" style="display:inline;">`;
                            html += `<input type="hidden" name="wp_arzo_user_nonce" value="${userActionNonce}">`;
                            html += `<input type="hidden" name="user_id" value="${user.id}">`;
                            html +=
                                `<button type="submit" name="delete_user" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete user: ${user.username}?');" title="Delete ${user.username}"><?php wp_arzo_icon_e('trash', ['size' => 14]); ?> Delete</button>`;
                            html += `</form>`;
                        } else {
                            html +=
                                `<span style="color: var(--arzo-text-muted); font-size: 12px;">Cannot delete current user</span>`;
                        }

                        html += '</td>';
                        html += '</tr>';
                    });
                    usersTable.innerHTML = html;
                }

                function renderPagination(currentPage, totalPages, totalItems) {
                    paginationInfo.textContent =
                        `Showing page ${currentPage} of ${totalPages} (${totalItems} total users)`;

                    let controlsHtml = '';

                    // Previous button
                    controlsHtml +=
                        `<button ${currentPage === 1 ? 'disabled' : ''} onclick="loadUsersPage(${currentPage - 1})">&laquo; Previous</button>`;

                    // Page numbers
                    const startPage = Math.max(1, currentPage - 2);
                    const endPage = Math.min(totalPages, startPage + 4);

                    for (let i = startPage; i <= endPage; i++) {
                        controlsHtml +=
                            `<button class="${i === currentPage ? 'active' : ''}" onclick="loadUsersPage(${i})">${i}</button>`;
                    }

                    // Next button
                    controlsHtml +=
                        `<button ${currentPage === totalPages ? 'disabled' : ''} onclick="loadUsersPage(${currentPage + 1})">Next &raquo;</button>`;

                    paginationControls.innerHTML = controlsHtml;
                }

                window.loadUsersPage = loadUsersPage;

                // Client-side search for current page
                window.filterUsersTable = function() {
                    const input = document.getElementById('search-users');
                    const filter = input.value.toUpperCase();
                    const tr = usersTable.getElementsByTagName('tr');
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
                loadUsersPage(currentPage);
            });
        </script>

        <!-- Hidden form that will be shown in lightbox -->
        <div id="createUserLightbox" class="lightbox">
            <div class="lightbox-content">
                <div class="lightbox-header">
                    <h3>Create New User</h3>
                    <button class="lightbox-close" onclick="closeCreateUserLightbox()">&times;</button>
                </div>
                <div class="lightbox-body">
                    <form method="post" id="createUserForm">
                        <?php wp_nonce_field('wp_arzo_user_action', 'wp_arzo_user_nonce'); ?>
                        <div class="form-group">
                            <label>Username:</label>
                            <input type="text" name="username" required>
                        </div>
                        <div class="form-group">
                            <label>Email:</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>Password:</label>
                            <input type="password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label>Role:</label>
                            <select name="role" data-wpa-select>
                                <option value="administrator">Administrator</option>
                                <option value="editor">Editor</option>
                                <option value="author">Author</option>
                                <option value="contributor">Contributor</option>
                                <option value="subscriber">Subscriber</option>
                            </select>
                        </div>
                        <div class="lightbox-actions">
                            <button type="button" class="btn btn-secondary"
                                onclick="closeCreateUserLightbox()">Cancel</button>
                            <button type="submit" name="create_user" class="btn btn-primary">Create User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php
}


// Call the function
handleUsers();
