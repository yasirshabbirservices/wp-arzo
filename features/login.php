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
    <div class="content">
        <h2>Quick Login Options</h2>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;">

            <!-- Login as Existing User -->
            <div style="background: #2A2A2A; padding: 20px; border-radius: 3px; border: 1px solid #333333;">
                <h3>Login as Existing User</h3>
                <p>Select any existing user to login as them instantly.</p>

                <form method="post">
                    <div class="form-group">
                        <label>Select User:</label>
                        <div class="scrollable-select">
                            <select name="user_id" required>
                                <option value="">Choose a user...</option>
                                <?php
                                $users = get_users();
                                foreach ($users as $user) {
                                    $roles = implode(', ', $user->roles);
                                    echo '<option value="' . $user->ID . '">' . $user->user_login . ' (' . $roles . ')</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="login_as_user" class="btn">Login as Selected User</button>
                </form>
            </div>

            <!-- Create Temporary Admin -->
            <div style="background: #2A2A2A; padding: 20px; border-radius: 3px; border: 1px solid #333333;">
                <h3>Create Temporary Admin</h3>
                <p>Creates a new admin user with random credentials and logs you in instantly.</p>

                <form method="post">
                    <button type="submit" name="create_temp_admin" class="btn">Create & Login as Temp Admin</button>
                </form>

                <p style="font-size: 12px; color: #999; margin-top: 10px;">
                    <em>Note: Remember to delete temporary users after use.</em>
                </p>
            </div>

            <!-- Direct Admin Access -->
            <div style="background: #2A2A2A; padding: 20px; border-radius: 3px; border: 1px solid #333333;">
                <h3>Direct Admin Access</h3>
                <p>Generate a special link for direct WordPress admin access.</p>

                <form method="post">
                    <button type="submit" name="direct_admin_access" class="btn">Generate Admin Access Link</button>
                </form>

                <p style="font-size: 12px; color: #999; margin-top: 10px;">
                    <em>Link expires in 1 hour for security.</em>
                </p>
            </div>

        </div>

        <div
            style="background: #2A2A2A; padding: 15px; border-radius: 3px; border-left: 4px solid var(--accent-color); margin-top: 20px;">
            <h3>Current Login Status</h3>
            <?php
            $current_user = wp_get_current_user();
            if ($current_user->ID) {
                echo '<p><strong>Currently logged in as:</strong> ' . $current_user->user_login . ' (' . implode(', ', $current_user->roles) . ')</p>';
                echo '<p><a href="' . admin_url() . '" target="_blank" style="color: #ff7e3a;">Open WordPress Admin</a></p>';
            } else {
                echo '<p>Not currently logged in to WordPress.</p>';
            }
            ?>
        </div>

    </div>
    <?php
}

// Call the function
handleQuickLogin();
