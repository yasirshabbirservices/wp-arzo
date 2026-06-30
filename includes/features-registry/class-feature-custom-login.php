<?php

/**
 * Feature: Custom Login Page (free).
 *
 * Restyles wp-login.php with a custom logo, colors and optional extra CSS, and
 * points the logo link back to the site.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Custom_Login extends WP_Arzo_Feature
{
    public function id()
    {
        return 'custom_login';
    }

    public function title()
    {
        return 'Custom Login Page';
    }

    public function description()
    {
        return 'Brand the wp-login.php screen with your logo, colors and custom CSS.';
    }

    public function group()
    {
        return 'branding';
    }

    public function icon()
    {
        return 'sparkles';
    }

    public function settings_schema()
    {
        return array(
            array('key' => 'logo_url', 'type' => 'text', 'label' => 'Logo image URL', 'help' => 'Square or wide PNG/SVG works best. Leave blank to keep the WordPress logo.'),
            array('key' => 'bg_color', 'type' => 'color', 'label' => 'Page background', 'default' => '#121212'),
            array('key' => 'form_bg', 'type' => 'color', 'label' => 'Form background', 'default' => '#1e1e1e'),
            array('key' => 'text_color', 'type' => 'color', 'label' => 'Form text', 'default' => '#e0e0e0'),
            array('key' => 'accent', 'type' => 'color', 'label' => 'Button / link color', 'default' => '#16e791'),
            array('key' => 'custom_css', 'type' => 'textarea', 'label' => 'Additional CSS', 'help' => 'Advanced: extra CSS applied to the login screen.'),
        );
    }

    public function boot()
    {
        add_action('login_enqueue_scripts', array($this, 'print_styles'));
        add_filter('login_headerurl', function () {
            return home_url('/');
        });
        add_filter('login_headertext', function () {
            return get_bloginfo('name');
        });
    }

    public function print_styles()
    {
        $logo   = trim((string) $this->get_setting('logo_url', ''));
        $bg     = $this->color('bg_color', '#121212');
        $formbg = $this->color('form_bg', '#1e1e1e');
        $text   = $this->color('text_color', '#e0e0e0');
        $accent = $this->color('accent', '#16e791');
        $extra  = (string) $this->get_setting('custom_css', '');

        $logo_css = '';
        if ($logo !== '') {
            $logo_css = '#login h1 a{background-image:url(' . esc_url($logo) . ') !important;background-size:contain !important;width:100% !important;height:72px !important;}';
        }
        // `login_enqueue_scripts` fires on every wp-login.php view, and `.login form`
        // matches them all — sign-in, lost password, reset password, register, and
        // confirm-action — so a single ruleset brands the entire login flow.
        ?>
        <style id="wp-arzo-custom-login">
            body.login { background: <?php echo esc_html($bg); ?> !important; }
            .login label, .login #nav a, .login #backtoblog a, .login p, .login form .forgetmenot label {
                color: <?php echo esc_html($text); ?> !important;
            }
            .login #nav a:hover, .login #backtoblog a:hover, .login a:hover { color: <?php echo esc_html($accent); ?> !important; }
            .login a { color: <?php echo esc_html($accent); ?> !important; }
            .login form, .login .message, .login #login_error, .login .notice {
                background: <?php echo esc_html($formbg); ?> !important;
                border: 1px solid rgba(255,255,255,.08) !important;
                color: <?php echo esc_html($text); ?> !important;
                border-radius: 8px !important;
            }
            .login form input[type="text"],
            .login form input[type="password"],
            .login form input[type="email"] {
                background: <?php echo esc_html($bg); ?> !important;
                color: <?php echo esc_html($text); ?> !important;
                border-color: rgba(255,255,255,.15) !important;
            }
            .wp-core-ui .button-primary {
                background: <?php echo esc_html($accent); ?> !important;
                border-color: <?php echo esc_html($accent); ?> !important;
                color: #121212 !important;
                text-shadow: none !important;
                box-shadow: none !important;
            }
            <?php echo $logo_css; ?>
            <?php echo wp_strip_all_tags($extra); ?>
        </style>
        <?php
    }

    private function color($key, $default)
    {
        $val = sanitize_hex_color((string) $this->get_setting($key, $default));
        return $val ? $val : $default;
    }
}
