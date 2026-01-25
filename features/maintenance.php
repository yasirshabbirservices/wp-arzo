<?php
/**
 * Maintenance Mode Feature
 *
 * @package WP_Arzo
 * @version 5.1
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

function handleMaintenanceModes()
{
    // Handle form submissions
    $message = '';
    $developer_email = get_option('maintenance_tool_developer_email', 'contact@yasirshabbir.com');

    // Handle social contact settings update
    if (isset($_POST['update_social_contacts'])) {
        $email = sanitize_email($_POST['developer_email']);
        $phone = sanitize_text_field($_POST['developer_phone']);
        $whatsapp = sanitize_text_field($_POST['developer_whatsapp']);
        $skype = sanitize_text_field($_POST['developer_skype']);

        update_option('maintenance_tool_developer_email', $email);
        update_option('maintenance_tool_developer_phone', $phone);
        update_option('maintenance_tool_developer_whatsapp', $whatsapp);
        update_option('maintenance_tool_developer_skype', $skype);

        $developer_email = $email;
        $message = '<div class="success">Social contact settings updated successfully!</div>';
    }

    // Handle mode activation
    if (isset($_POST['activate_mode'])) {
        $mode = sanitize_text_field($_POST['mode']);
        $custom_message = wp_kses_post($_POST['custom_message'] ?? '');
        $custom_title = sanitize_text_field($_POST['custom_title'] ?? '');
        $custom_css = wp_strip_all_tags($_POST['custom_css'] ?? '');
        $show_social_contacts = isset($_POST['show_social_contacts']) ? 1 : 0;

        // Save mode settings
        update_option('maintenance_tool_active_mode', $mode);
        update_option('maintenance_tool_custom_message', $custom_message);
        update_option('maintenance_tool_custom_title', $custom_title);
        update_option('maintenance_tool_custom_css', $custom_css);
        update_option('maintenance_tool_show_social_contacts', $show_social_contacts);

        $message = '<div class="success">Maintenance mode "' . ucfirst($mode) . '" activated successfully!</div>';
    }

    // Handle mode deactivation
    if (isset($_POST['deactivate_mode'])) {
        delete_option('maintenance_tool_active_mode');
        $message = '<div class="success">Maintenance mode deactivated successfully!</div>';
    }

    $current_mode = get_option('maintenance_tool_active_mode', '');
    $custom_message = get_option('maintenance_tool_custom_message', '');
    $custom_title = get_option('maintenance_tool_custom_title', '');
    $custom_css = get_option('maintenance_tool_custom_css', '');
    $show_social_contacts = get_option('maintenance_tool_show_social_contacts', 1);

    // Get social contact settings
    $developer_phone = get_option('maintenance_tool_developer_phone', '');
    $developer_whatsapp = get_option('maintenance_tool_developer_whatsapp', '');
    $developer_skype = get_option('maintenance_tool_developer_skype', '');

    ?>
    <div class="content">
        <h2>Maintenance Modes</h2>

        <?php echo $message; ?>

        <?php if ($current_mode): ?>
            <div class="success" style="margin-bottom: 20px;">
                <strong>Active Mode:</strong>
                <?php echo ucfirst($current_mode); ?> Mode
                <form method="post" style="display: inline; margin-left: 15px;">
                    <button type="submit" name="deactivate_mode" class="btn btn-danger"
                        onclick="return confirm('Are you sure you want to deactivate maintenance mode?')">Deactivate
                        Mode</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Social Contact Settings -->
        <div
            style="background: #2A2A2A; padding: 20px; border-radius: 3px; border: 1px solid #333333; margin-bottom: 20px;">
            <h3>Social Contact Settings</h3>
            <form method="post">
                <div
                    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" name="developer_email" value="<?php echo esc_attr($developer_email); ?>"
                            style="width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #333; color: #fff; border-radius: 3px;">
                    </div>
                    <div class="form-group">
                        <label>Phone:</label>
                        <input type="text" name="developer_phone" value="<?php echo esc_attr($developer_phone); ?>"
                            placeholder="+1234567890"
                            style="width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #333; color: #fff; border-radius: 3px;">
                    </div>
                    <div class="form-group">
                        <label>WhatsApp:</label>
                        <input type="text" name="developer_whatsapp" value="<?php echo esc_attr($developer_whatsapp); ?>"
                            placeholder="+1234567890"
                            style="width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #333; color: #fff; border-radius: 3px;">
                    </div>
                    <div class="form-group">
                        <label>Skype:</label>
                        <input type="text" name="developer_skype" value="<?php echo esc_attr($developer_skype); ?>"
                            placeholder="your.skype.username"
                            style="width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #333; color: #fff; border-radius: 3px;">
                    </div>
                </div>
                <button type="submit" name="update_social_contacts" class="btn btn-primary">Update Contact Settings</button>
            </form>
        </div>

        <!-- Mode Selection -->
        <div
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin-bottom: 30px;">

            <!-- Maintenance Mode -->
            <div style="background: #2A2A2A; padding: 20px; border-radius: 3px; border: 1px solid #333333;">
                <h3 style="color: #ff9800;">🔧 Maintenance Mode</h3>
                <p>Display a maintenance message while you work on the site. Includes noindex meta tag to prevent search
                    engine indexing.</p>
                <form method="post">
                    <input type="hidden" name="mode" value="maintenance">
                    <div class="form-group">
                        <label>Custom Title:</label>
                        <input type="text" name="custom_title"
                            value="<?php echo $current_mode === 'maintenance' ? esc_attr($custom_title) : 'Site Under Maintenance'; ?>"
                            style="width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #333; color: #fff; border-radius: 3px; margin-bottom: 10px;">
                    </div>
                    <div class="form-group">
                        <label>Custom Message:</label>
                        <textarea name="custom_message" rows="3"
                            style="width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #333; color: #fff; border-radius: 3px; margin-bottom: 10px;"><?php echo $current_mode === 'maintenance' ? esc_textarea($custom_message) : 'We are currently performing scheduled maintenance. Please check back soon.'; ?></textarea>
                    </div>

                    <div class="form-group" style="margin-top: 10px; margin-bottom: 15px;">
                        <label class="switch">
                            <input type="checkbox" name="show_social_contacts" value="1" <?php echo ($current_mode === 'maintenance' && $show_social_contacts) || ($current_mode !== 'maintenance') ? 'checked' : ''; ?>>
                            <span class="slider round"></span>
                        </label>
                        <span style="margin-left: 10px; vertical-align: middle;">Show Social Contacts</span>
                    </div>

                    <button type="submit" name="activate_mode" class="btn btn-warning">Activate Maintenance Mode</button>
                </form>
            </div>

            <!-- Coming Soon Mode -->
            <div style="background: #2A2A2A; padding: 20px; border-radius: 3px; border: 1px solid #333333;">
                <h3 style="color: #4CAF50;">🚀 Coming Soon Mode</h3>
                <p>Show a coming soon page for new websites. Includes noindex meta tag and email collection form.</p>
                <form method="post">
                    <input type="hidden" name="mode" value="coming_soon">
                    <div class="form-group">
                        <label>Custom Title:</label>
                        <input type="text" name="custom_title"
                            value="<?php echo $current_mode === 'coming_soon' ? esc_attr($custom_title) : 'Coming Soon'; ?>"
                            style="width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #333; color: #fff; border-radius: 3px; margin-bottom: 10px;">
                    </div>
                    <div class="form-group">
                        <label>Custom Message:</label>
                        <textarea name="custom_message" rows="3"
                            style="width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #333; color: #fff; border-radius: 3px; margin-bottom: 10px;"><?php echo $current_mode === 'coming_soon' ? esc_textarea($custom_message) : 'Something amazing is coming soon! Stay tuned for updates.'; ?></textarea>
                    </div>

                    <div class="form-group" style="margin-top: 10px; margin-bottom: 15px;">
                        <label class="switch">
                            <input type="checkbox" name="show_social_contacts" value="1" <?php echo ($current_mode === 'coming_soon' && $show_social_contacts) || ($current_mode !== 'coming_soon') ? 'checked' : ''; ?>>
                            <span class="slider round"></span>
                        </label>
                        <span style="margin-left: 10px; vertical-align: middle;">Show Social Contacts</span>
                    </div>

                    <button type="submit" name="activate_mode" class="btn btn-success">Activate Coming Soon Mode</button>
                </form>
            </div>

            <!-- Money Request Mode -->
            <div style="background: #2A2A2A; padding: 20px; border-radius: 3px; border: 1px solid #333333;">
                <h3 style="color: #dc3545;">💰 Payment Request Mode</h3>
                <p>Display a payment request message for clients who haven't paid. Includes noindex and contact form.</p>
                <form method="post">
                    <input type="hidden" name="mode" value="payment_request">
                    <div class="form-group">
                        <label>Custom Title:</label>
                        <input type="text" name="custom_title"
                            value="<?php echo $current_mode === 'payment_request' ? esc_attr($custom_title) : 'Payment Required'; ?>"
                            style="width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #333; color: #fff; border-radius: 3px; margin-bottom: 10px;">
                    </div>
                    <div class="form-group">
                        <label>Custom Message:</label>
                        <textarea name="custom_message" rows="3"
                            style="width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #333; color: #fff; border-radius: 3px; margin-bottom: 10px;"><?php echo $current_mode === 'payment_request' ? esc_textarea($custom_message) : 'This website has been completed but payment is still pending. Please contact us to resolve this matter and restore full access to your website.'; ?></textarea>
                    </div>

                    <div class="form-group" style="margin-top: 10px; margin-bottom: 15px;">
                        <label class="switch">
                            <input type="checkbox" name="show_social_contacts" value="1" <?php echo ($current_mode === 'payment_request' && $show_social_contacts) || ($current_mode !== 'payment_request') ? 'checked' : ''; ?>>
                            <span class="slider round"></span>
                        </label>
                        <span style="margin-left: 10px; vertical-align: middle;">Show Social Contacts</span>
                    </div>

                    <button type="submit" name="activate_mode" class="btn btn-danger">Activate Payment Request Mode</button>
                </form>
            </div>
        </div>

        <!-- Custom CSS -->
        <div
            style="background: #2A2A2A; padding: 20px; border-radius: 3px; border: 1px solid #333333; margin-bottom: 20px;">
            <h3>Custom CSS (Optional)</h3>
            <p style="color: #999; margin-bottom: 10px;">Add custom CSS to style your maintenance page:</p>
            <textarea name="custom_css" rows="6"
                style="width: 100%; padding: 10px; background: #1a1a1a; border: 1px solid #333; color: #fff; border-radius: 3px; font-family: monospace;"
                placeholder="/* Add your custom CSS here */"><?php echo esc_textarea($custom_css); ?></textarea>
        </div>

        <!-- Preview Links -->
        <?php if ($current_mode): ?>
            <div style="background: #2A2A2A; padding: 20px; border-radius: 3px; border: 1px solid #333333;">
                <h3>Preview & Management</h3>
                <p><strong>Current Mode:</strong>
                    <?php echo ucfirst(str_replace('_', ' ', $current_mode)); ?>
                </p>
                <p><strong>Frontend URL:</strong> <a href="<?php echo home_url(); ?>" target="_blank"
                        style="color: var(--accent-color);">
                        <?php echo home_url(); ?>
                    </a></p>
                <p><strong>Bypass URL:</strong> <a
                        href="<?php echo home_url('?maintenance_bypass=' . wp_create_nonce('maintenance_bypass')); ?>"
                        target="_blank" style="color: var(--accent-color);">
                        <?php echo home_url('?maintenance_bypass=' . wp_create_nonce('maintenance_bypass')); ?>
                    </a></p>
                <p style="font-size: 12px; color: #999;">Use the bypass URL to view your site normally while maintenance mode is
                    active.</p>
            </div>
        <?php endif; ?>

        <!-- SEO Information -->
        <div
            style="background: #2A2A2A; padding: 20px; border-radius: 3px; border-left: 4px solid var(--accent-color); margin-top: 20px;">
            <h3>SEO & Search Engine Information</h3>
            <ul style="line-height: 1.6;">
                <li><strong>Maintenance Mode:</strong> Returns 503 status code + noindex meta tag (prevents indexing)</li>
                <li><strong>Coming Soon Mode:</strong> Returns 200 status code + noindex meta tag (prevents indexing)</li>
                <li><strong>Payment Request Mode:</strong> Returns 402 status code + noindex meta tag (prevents indexing)
                </li>
                <li><strong>Bypass Access:</strong> Logged-in administrators and bypass URL users see normal site</li>
                <li><strong>Search Engines:</strong> Will not index pages while any mode is active</li>
            </ul>
        </div>
    </div>
    <?php
}

// Call the function
handleMaintenanceModes();
