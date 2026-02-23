<?php
/**
 * Quick Login Feature
 *
 * @package WP_Arzo
 * @version 6.0
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

function handleQuickLogin()
{
    global $login_message, $login_redirect;

    if (isset($_POST['direct_admin_access'])) {
        // Create a session-based admin access
        $nonce = wp_create_nonce('direct_admin_access_' . time());
        $admin_url = add_query_arg([
            'maintenance_access' => $nonce
        ], home_url() . '/' . basename(__FILE__));

        // Store the nonce temporarily
        set_transient('maintenance_access_' . $nonce, true, 3600); // 1 hour

        echo '<script>setTimeout(function() { window.open("' . $admin_url . '", "_blank"); }, 1000);</script>';
        echo '<div class="success">Direct admin access link generated! Opening in new tab...</div>';
    }

    ?>
    <div class="content quick-login-layout">
        <h2 class="quick-login-title">Quick Login Options</h2>

        <?php
        $current_user = wp_get_current_user();
        $is_logged_in = (bool) $current_user->ID;
        $roles = $is_logged_in ? implode(', ', $current_user->roles) : '';
        ?>

        <!-- Current Login Status (primary card) -->
        <div class="quick-login-status-card">
            <div class="quick-login-status-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path
                        d="M12 12c2.486 0 4.5-2.014 4.5-4.5S14.486 3 12 3 7.5 5.014 7.5 7.5 9.514 12 12 12Zm0 2c-3.038 0-9 1.523-9 4.5V20a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-1.5C21 15.523 15.038 14 12 14Z" />
                </svg>
            </div>
            <div class="quick-login-status-content">
                <div class="quick-login-status-label">Current Login Status</div>
                <?php if ($is_logged_in): ?>
                    <div class="quick-login-status-main">
                        <span class="quick-login-status-user">
                            <?php echo esc_html($current_user->user_login); ?>
                        </span>
                        <?php if ($roles): ?>
                            <span class="quick-login-status-roles">
                                <?php echo esc_html($roles); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="quick-login-status-actions">
                        <a href="<?php echo esc_url(admin_url()); ?>" target="_blank" class="btn quick-login-status-btn">
                            <span class="quick-login-status-btn-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path
                                        d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3ZM5 5h6v2H6.5A1.5 1.5 0 0 0 5 8.5v9A1.5 1.5 0 0 0 6.5 19h9A1.5 1.5 0 0 0 17 17.5V13h2v4.5A3.5 3.5 0 0 1 15.5 21h-9A3.5 3.5 0 0 1 3 17.5v-9A3.5 3.5 0 0 1 6.5 5H11Z" />
                                </svg>
                            </span>
                            Open WordPress Admin
                        </a>
                    </div>
                <?php else: ?>
                    <div class="quick-login-status-main">
                        <span class="quick-login-status-user quick-login-status-user--muted">
                            Not currently logged in to WordPress.
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Action cards grid -->
        <div class="quick-login-grid">
            <!-- Create Temporary Admin -->
            <div class="quick-login-card">
                <div class="quick-login-card-header">
                    <div class="quick-login-card-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path
                                d="M12 12a4 4 0 1 0-4-4 4.005 4.005 0 0 0 4 4Zm0 2c-3.033 0-9 1.518-9 4.5V20a1 1 0 0 0 1 1h8.268A6.48 6.48 0 0 1 11 17.5c0-1.388.448-2.672 1.207-3.722C12.135 13.515 12.07 13.5 12 13.5Z" />
                            <path
                                d="M19.5 13a3.5 3.5 0 1 0 3.5 3.5A3.504 3.504 0 0 0 19.5 13Zm0-2a5.5 5.5 0 1 1-5.5 5.5A5.507 5.507 0 0 1 19.5 11Zm-1 3h2v1.5H22v2h-1.5V19h-2v-1.5H17v-2h1.5Z" />
                        </svg>
                    </div>
                    <div class="quick-login-card-title-group">
                        <h3>Create Temporary Admin</h3>
                        <p>Spin up a throwaway admin user and log in instantly.</p>
                    </div>
                </div>
                <div class="quick-login-card-body">
                    <form method="post">
                        <button type="submit" name="create_temp_admin" class="btn">
                            Create &amp; Login as Temp Admin
                        </button>
                    </form>
                    <p class="quick-login-card-note">
                        This creates a new administrator with random credentials. Remember to remove temporary accounts when you are done.
                    </p>
                </div>
            </div>

            <!-- Direct Admin Access -->
            <div class="quick-login-card">
                <div class="quick-login-card-header">
                    <div class="quick-login-card-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path
                                d="M10.586 4H9A5 5 0 0 0 9 14h2v-2H9a3 3 0 0 1 0-6h1.586l-1.293 1.293 1.414 1.414L14.414 4 10.707.293 9.293 1.707 10.586 3H9A7 7 0 0 0 9 16h3v-2H9A5 5 0 0 1 9 4h1.586Z" />
                            <path
                                d="M15 8a1 1 0 0 1 1-1h1.5a4.5 4.5 0 1 1 0 9H16a1 1 0 0 1 0-2h1.5a2.5 2.5 0 1 0 0-5H16a1 1 0 0 1-1-1Z" />
                        </svg>
                    </div>
                    <div class="quick-login-card-title-group">
                        <h3>Direct Admin Access</h3>
                        <p>Generate a one‑time secure link to WordPress admin.</p>
                    </div>
                </div>
                <div class="quick-login-card-body">
                    <form method="post">
                        <button type="submit" name="direct_admin_access" class="btn">
                            Generate Admin Access Link
                        </button>
                    </form>
                    <p class="quick-login-card-note">
                        The link is valid for 1 hour and is stored as a transient for security. Use it for emergency access only.
                    </p>
                </div>
            </div>
        </div>

    </div>
    <?php
}

// Call the function
handleQuickLogin();
