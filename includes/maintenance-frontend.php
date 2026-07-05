<?php
/**
 * Maintenance Tool Frontend Handler
 * 
 * This file handles the frontend display of maintenance modes.
 * It is loaded by the main plugin file.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Hook to display maintenance mode on frontend
add_action('template_redirect', 'wp_arzo_maintenance_display_mode', 1);

function wp_arzo_maintenance_display_mode()
{
    // Define the access key (should match the one in maintenance-tool.php)
    $access_key = 'YS_maint_7x9K2pQ8vL4nB6wE3rT5uA1cF8dG';

    // Check for preview mode (Admins only)
    if (isset($_GET['maintenance_preview']) && current_user_can('manage_options')) {
        // Proceed to show maintenance page (bypass the bypass)
    }
    // Skip if user is an admin or using the bypass key.
    // Note: use the capability, not the role name — current_user_can('administrator')
    // is unreliable and discouraged.
    elseif (
        current_user_can('manage_options') ||
        (isset($_GET['maintenance_bypass']) && $_GET['maintenance_bypass'] === $access_key) ||
        is_admin() ||
        (defined('WP_CLI') && WP_CLI)
    ) {
        return;
    }

    $active_mode = get_option('maintenance_tool_active_mode', '');

    // Only render for known modes; an unknown/legacy value would otherwise cause
    // "array offset on null" warnings (PHP 8) and a blank page below.
    $valid_modes = ['maintenance', 'coming_soon', 'payment_request'];
    if (!in_array($active_mode, $valid_modes, true)) {
        return;
    }

    // No email handling needed for social contacts

    // Set appropriate HTTP status code
    switch ($active_mode) {
        case 'maintenance':
            http_response_code(503);
            header('Retry-After: 3600'); // 1 hour
            break;
        case 'coming_soon':
            http_response_code(200);
            break;
        case 'payment_request':
            http_response_code(402); // Payment Required
            break;
    }

    $custom_title = get_option('maintenance_tool_custom_title', '');
    $custom_message = get_option('maintenance_tool_custom_message', '');
    $custom_css = get_option('maintenance_tool_custom_css', '');
    $show_social_contacts = get_option('maintenance_tool_show_social_contacts', 1);
    $developer_email = get_option('maintenance_tool_developer_email', '');
    $developer_phone = get_option('maintenance_tool_developer_phone', '');
    $developer_whatsapp = get_option('maintenance_tool_developer_whatsapp', '');
    $developer_skype = get_option('maintenance_tool_developer_skype', '');

    // Get default messages
    $default_messages = [
        'maintenance' => 'We are currently performing scheduled maintenance. Please check back soon.',
        'coming_soon' => 'Something amazing is coming soon! Stay tuned for updates.',
        'payment_request' => 'This website has been completed but payment is still pending. Please contact us to resolve this matter and restore full access to your website.'
    ];

    $default_titles = [
        'maintenance' => 'Site Under Maintenance',
        'coming_soon' => 'Coming Soon',
        'payment_request' => 'Payment Required'
    ];

    $title = $custom_title ?: $default_titles[$active_mode];
    $message = $custom_message ?: $default_messages[$active_mode];

    // Color schemes for different modes
    $color_schemes = [
        'maintenance' => ['primary' => '#faad14', 'bg' => '#121212', 'text' => '#ffffff'],
        'coming_soon' => ['primary' => '#16e791', 'bg' => '#121212', 'text' => '#ffffff'],
        'payment_request' => ['primary' => '#ff4d4f', 'bg' => '#121212', 'text' => '#ffffff']
    ];

    $colors = $color_schemes[$active_mode];

    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>

    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title><?php echo esc_html($title . ' - ' . get_bloginfo('name')); ?></title>
        <style>
            /* No CDN assets: icons are inline SVG (wp_arzo_icon) and the font falls back to the
               system stack — WordPress.org forbids offloaded fonts/styles, and a public page
               must not send visitor IPs to Google/Cloudflare. */

            /* Dashboard token palette (embedded: this page must stay self-contained). */
            :root {
                --arzo-bg-dark: #121212;
                --arzo-bg-panel: #1e1e1e;
                --arzo-bg-elev: #242424;
                --arzo-bg-hover: #2a2a2a;
                --arzo-border: #333333;
                --arzo-border-strong: #444444;
                --arzo-text-strong: #ffffff;
                --arzo-text-primary: #e0e0e0;
                --arzo-text-secondary: #999999;
                --arzo-accent: #16e791;
                --arzo-warning: #faad14;
                --arzo-error: #ff4d4f;
                --arzo-success: #16e791;
            }


            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Lato', sans-serif;
                background:
                    <?php echo $colors['bg']; ?>
                ;
                color:
                    <?php echo $colors['text']; ?>
                ;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                line-height: 1.6;
            }

            .maintenance-container {
                max-width: 600px;
                padding: 40px;
                text-align: center;
                background: var(--arzo-bg-panel);
                border: 1px solid var(--arzo-border);
                border-radius: 14px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
                margin: 20px;
            }

            .maintenance-icon {
                color:
                    <?php echo $colors['primary']; ?>
                ;
                margin-bottom: 20px;
            }

            .maintenance-icon svg {
                width: 4rem;
                height: 4rem;
            }

            .social-contacts h3 svg,
            .contact-icon svg {
                width: 1.25rem;
                height: 1.25rem;
                vertical-align: middle;
            }

            .maintenance-title {
                font-size: 2.5rem;
                font-weight: 700;
                color:
                    <?php echo $colors['primary']; ?>
                ;
                margin-bottom: 20px;
            }

            .maintenance-message {
                font-size: 1.2rem;
                margin-bottom: 30px;
                color: var(--arzo-text-primary);
            }

            .social-contacts {
                background: var(--arzo-bg-elev);
                padding: 30px;
                border-radius: 8px;
                margin-top: 30px;
                border: 1px solid var(--arzo-border);
            }

            .social-contacts h3 {
                color:
                    <?php echo $colors['primary']; ?>
                ;
                margin-bottom: 20px;
            }

            .contact-icons {
                display: flex;
                justify-content: center;
                gap: 20px;
                flex-wrap: wrap;
            }

            .contact-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 60px;
                height: 60px;
                background:
                    <?php echo $colors['primary']; ?>
                ;
                color: var(--arzo-text-strong);
                border-radius: 50%;
                text-decoration: none;
                font-size: 24px;
                transition: all 0.3s ease;
            }

            .contact-icon:hover {
                transform: translateY(-3px);
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
                opacity: 0.9;
            }

            .site-info {
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid var(--arzo-border);
                font-size: 14px;
                color: var(--arzo-text-secondary);
            }

            <?php echo wp_strip_all_tags($custom_css); ?>
        </style>
    </head>

    <body>
        <div class="maintenance-container">


            <div class="maintenance-icon">
                <?php
                $icons = [
                    'maintenance' => 'tools',
                    'coming_soon' => 'rocket',
                    'payment_request' => 'credit-card',
                ];
                $mode_icon = isset($icons[$active_mode]) ? $icons[$active_mode] : 'tools';
                echo wp_arzo_icon($mode_icon, ['size' => 64]);
                ?>
            </div>

            <h1 class="maintenance-title"><?php echo esc_html($title); ?></h1>
            <p class="maintenance-message"><?php echo wp_kses_post($message); ?></p>

            <?php if ($show_social_contacts && ($developer_email || $developer_phone || $developer_whatsapp || $developer_skype)): ?>
                <div class="social-contacts">
                    <h3><?php echo wp_arzo_icon('users'); ?> Contact Information</h3>
                    <div class="contact-icons">
                        <?php if ($developer_email): ?>
                            <a href="mailto:<?php echo esc_attr($developer_email); ?>" class="contact-icon" title="Email" aria-label="Email">
                                <?php echo wp_arzo_icon('mail'); ?>
                            </a>
                        <?php endif; ?>

                        <?php if ($developer_phone): ?>
                            <a href="tel:<?php echo esc_attr($developer_phone); ?>" class="contact-icon" title="Phone" aria-label="Phone">
                                <?php echo wp_arzo_icon('phone'); ?>
                            </a>
                        <?php endif; ?>

                        <?php if ($developer_whatsapp): ?>
                            <a href="https://wa.me/<?php echo esc_attr(preg_replace('/[^0-9]/', '', $developer_whatsapp)); ?>"
                                class="contact-icon" title="WhatsApp" aria-label="WhatsApp" target="_blank" rel="noopener">
                                <?php echo wp_arzo_icon('chat'); ?>
                            </a>
                        <?php endif; ?>

                        <?php if ($developer_skype): ?>
                            <a href="skype:<?php echo esc_attr($developer_skype); ?>?chat" class="contact-icon" title="Skype" aria-label="Skype">
                                <?php echo wp_arzo_icon('chat'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>


        </div>
    </body>

    </html>
    <?php
    exit;
}
