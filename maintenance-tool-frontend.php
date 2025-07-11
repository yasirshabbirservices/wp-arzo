<?php
/**
 * Maintenance Tool Frontend Handler
 * Must-Use Plugin for WordPress Maintenance Tool
 * 
 * This file handles the frontend display of maintenance modes.
 * It's automatically loaded by WordPress as a must-use plugin.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Hook to display maintenance mode on frontend
add_action('template_redirect', 'maintenance_tool_display_mode');

function maintenance_tool_display_mode() {
    // Define the access key (should match the one in maintenance-tool.php)
    $access_key = 'YS_maint_7x9K2pQ8vL4nB6wE3rT5uA1cF8dG';
    
    // Skip if user is admin or using bypass
    if (current_user_can('administrator') || 
        (isset($_GET['maintenance_bypass']) && $_GET['maintenance_bypass'] === $access_key) ||
        is_admin() || 
        (defined('WP_CLI') && WP_CLI)) {
        return;
    }
    
    $active_mode = get_option('maintenance_tool_active_mode', '');
    
    if (!$active_mode) {
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
    
    // No email submission handling needed
    
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
        'maintenance' => ['primary' => '#ff9800', 'bg' => '#1a1a1a', 'text' => '#ffffff'],
        'coming_soon' => ['primary' => '#4CAF50', 'bg' => '#1a1a1a', 'text' => '#ffffff'],
        'payment_request' => ['primary' => '#dc3545', 'bg' => '#1a1a1a', 'text' => '#ffffff']
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
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700&display=swap');
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Lato', sans-serif;
                background: <?php echo $colors['bg']; ?>;
                color: <?php echo $colors['text']; ?>;
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
                background: #2a2a2a;
                border-radius: 10px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                margin: 20px;
            }
            
            .maintenance-icon {
                font-size: 4rem;
                color: <?php echo $colors['primary']; ?>;
                margin-bottom: 20px;
            }
            
            .maintenance-title {
                font-size: 2.5rem;
                font-weight: 700;
                color: <?php echo $colors['primary']; ?>;
                margin-bottom: 20px;
            }
            
            .maintenance-message {
                font-size: 1.2rem;
                margin-bottom: 30px;
                color: #e0e0e0;
            }
            
            .social-contacts {
                background: #1a1a1a;
                padding: 30px;
                border-radius: 8px;
                margin-top: 30px;
                border: 1px solid #333;
            }
            
            .social-contacts h3 {
                color: <?php echo $colors['primary']; ?>;
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
                background: <?php echo $colors['primary']; ?>;
                color: #fff;
                border-radius: 50%;
                text-decoration: none;
                font-size: 24px;
                transition: all 0.3s ease;
            }
            
            .contact-icon:hover {
                transform: translateY(-3px);
                box-shadow: 0 5px 15px rgba(0,0,0,0.3);
                opacity: 0.9;
            }
            
            .site-info {
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid #333;
                font-size: 14px;
                color: #999;
            }
            
            <?php echo $custom_css; ?>
        </style>
    </head>
    <body>
        <div class="maintenance-container">

            
            <div class="maintenance-icon">
                <?php 
                $icons = [
                    'maintenance' => 'fas fa-tools',
                    'coming_soon' => 'fas fa-rocket',
                    'payment_request' => 'fas fa-credit-card'
                ];
                ?>
                <i class="<?php echo $icons[$active_mode]; ?>"></i>
            </div>
            
            <h1 class="maintenance-title"><?php echo esc_html($title); ?></h1>
            <p class="maintenance-message"><?php echo wp_kses_post($message); ?></p>
            
            <?php if ($developer_email || $developer_phone || $developer_whatsapp || $developer_skype): ?>
                <div class="social-contacts">
                    <h3><i class="fas fa-address-book"></i> Contact Information</h3>
                    <div class="contact-icons">
                        <?php if ($developer_email): ?>
                            <a href="mailto:<?php echo esc_attr($developer_email); ?>" class="contact-icon" title="Email">
                                <i class="fas fa-envelope"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($developer_phone): ?>
                            <a href="tel:<?php echo esc_attr($developer_phone); ?>" class="contact-icon" title="Phone">
                                <i class="fas fa-phone"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($developer_whatsapp): ?>
                            <a href="https://wa.me/<?php echo esc_attr(preg_replace('/[^0-9]/', '', $developer_whatsapp)); ?>" class="contact-icon" title="WhatsApp" target="_blank">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($developer_skype): ?>
                            <a href="skype:<?php echo esc_attr($developer_skype); ?>?chat" class="contact-icon" title="Skype">
                                <i class="fab fa-skype"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="site-info">
                <p><?php echo get_bloginfo('name'); ?></p>
                <?php if ($active_mode === 'maintenance'): ?>
                    <p>Expected completion: Please check back in a few hours</p>
                <?php endif; ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}