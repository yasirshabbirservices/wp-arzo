<?php

/**
 * Feature: Custom Login Page (free).
 *
 * Fully restyles wp-login.php — the card, logo, labels, inputs, password toggle,
 * remember-me checkbox, submit button, links and messages — using configurable
 * colors, and applies to every login screen (sign-in, lost password, reset,
 * register). The logo links back to the site.
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
        return 'Brand wp-login.php — logo, colors, fully styled form, links and buttons.';
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
            array('key' => 'logo_url', 'type' => 'text', 'label' => 'Logo image URL', 'help' => 'Wide or square PNG/SVG. Leave blank to keep the WordPress logo.'),
            array('key' => 'bg_color', 'type' => 'color', 'label' => 'Page background', 'default' => '#121212'),
            array('key' => 'form_bg', 'type' => 'color', 'label' => 'Form / card background', 'default' => '#1e1e1e'),
            array('key' => 'input_bg', 'type' => 'color', 'label' => 'Input background', 'default' => '#151515'),
            array('key' => 'text_color', 'type' => 'color', 'label' => 'Text', 'default' => '#e0e0e0'),
            array('key' => 'accent', 'type' => 'color', 'label' => 'Accent (button, links, focus)', 'default' => '#16e791'),
            array('key' => 'button_text', 'type' => 'color', 'label' => 'Button text', 'default' => '#121212'),
            array('key' => 'rounded', 'type' => 'toggle', 'label' => 'Rounded corners', 'default' => 1),
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

    private function c($key, $default)
    {
        $v = sanitize_hex_color((string) $this->get_setting($key, $default));
        return $v ? $v : $default;
    }

    public function print_styles()
    {
        $logo   = trim((string) $this->get_setting('logo_url', ''));
        $bg     = $this->c('bg_color', '#121212');
        $form   = $this->c('form_bg', '#1e1e1e');
        $input  = $this->c('input_bg', '#151515');
        $text   = $this->c('text_color', '#e0e0e0');
        $accent = $this->c('accent', '#16e791');
        $btext  = $this->c('button_text', '#121212');
        $radius = $this->get_setting('rounded', 1) ? '10px' : '0';
        $ir     = $this->get_setting('rounded', 1) ? '6px' : '0';

        $logo_css = '';
        if ($logo !== '') {
            $logo_css = '#login h1 a{background-image:url(' . esc_url($logo) . ')!important;background-size:contain!important;background-position:center!important;width:auto!important;max-width:320px!important;height:80px!important;margin:0 auto 8px!important;}';
        }
        // `.login form` matches every wp-login.php view (sign-in, lostpassword, resetpass, register).
        ?>
        <style id="wp-arzo-custom-login">
            body.login {
                background: <?php echo esc_html($bg); ?> !important;
                color: <?php echo esc_html($text); ?>;
            }
            #login { width: 340px; max-width: 92vw; padding: 6% 0 4%; }

            /* Card */
            .login form,
            .login .message,
            .login .notice,
            .login #login_error {
                background: <?php echo esc_html($form); ?> !important;
                border: 1px solid rgba(255,255,255,.08) !important;
                color: <?php echo esc_html($text); ?> !important;
                border-radius: <?php echo esc_html($radius); ?> !important;
                box-shadow: 0 8px 32px rgba(0,0,0,.45) !important;
            }
            .login form { padding: 26px 24px !important; margin-top: 18px; }

            /* Labels + text */
            .login label,
            .login form p,
            .login .forgetmenot label {
                color: <?php echo esc_html($text); ?> !important;
                font-size: 14px;
            }

            /* Inputs */
            .login input[type="text"],
            .login input[type="password"],
            .login input[type="email"] {
                background: <?php echo esc_html($input); ?> !important;
                color: <?php echo esc_html($text); ?> !important;
                border: 1px solid rgba(255,255,255,.15) !important;
                border-radius: <?php echo esc_html($ir); ?> !important;
                padding: 10px 12px !important;
                box-shadow: none !important;
                transition: border-color .15s ease, box-shadow .15s ease;
            }
            .login input[type="text"]:focus,
            .login input[type="password"]:focus,
            .login input[type="email"]:focus {
                border-color: <?php echo esc_html($accent); ?> !important;
                box-shadow: 0 0 0 3px <?php echo esc_html($accent); ?>40 !important;
                outline: none !important;
            }

            /* Show/hide password button */
            .login .wp-pwd .button.wp-hide-pw {
                background: transparent !important;
                border: 0 !important;
                color: <?php echo esc_html($text); ?> !important;
            }
            .login .wp-pwd .button.wp-hide-pw:hover { color: <?php echo esc_html($accent); ?> !important; }
            .login .wp-pwd .button.wp-hide-pw .dashicons { color: inherit !important; }

            /* Remember-me checkbox */
            .login .forgetmenot input[type="checkbox"] {
                accent-color: <?php echo esc_html($accent); ?>;
                border-color: rgba(255,255,255,.3) !important;
                background: <?php echo esc_html($input); ?> !important;
                border-radius: 3px !important;
            }
            .login .forgetmenot input[type="checkbox"]:checked {
                background: <?php echo esc_html($accent); ?> !important;
                border-color: <?php echo esc_html($accent); ?> !important;
            }
            .login .forgetmenot input[type="checkbox"]:checked::before { color: <?php echo esc_html($btext); ?>; }

            /* Submit button */
            .wp-core-ui .button-primary,
            .login .button-primary {
                background: <?php echo esc_html($accent); ?> !important;
                border-color: <?php echo esc_html($accent); ?> !important;
                color: <?php echo esc_html($btext); ?> !important;
                text-shadow: none !important;
                box-shadow: none !important;
                border-radius: <?php echo esc_html($ir); ?> !important;
                padding: 4px 16px !important;
                font-weight: 600 !important;
                transition: filter .15s ease, transform .05s ease;
            }
            .wp-core-ui .button-primary:hover { filter: brightness(1.08); }
            .wp-core-ui .button-primary:active { transform: translateY(1px); }
            .wp-core-ui .button-primary:focus {
                box-shadow: 0 0 0 3px <?php echo esc_html($accent); ?>55 !important;
                outline: none !important;
            }

            /* Links */
            .login #nav a,
            .login #backtoblog a,
            .login a {
                color: <?php echo esc_html($text); ?> !important;
                opacity: .85;
                transition: color .15s ease, opacity .15s ease;
            }
            .login #nav a:hover,
            .login #backtoblog a:hover,
            .login a:hover { color: <?php echo esc_html($accent); ?> !important; opacity: 1; }
            .login #nav, .login #backtoblog { text-align: center; }

            /* Message / error accents */
            .login .message, .login .notice { border-left: 4px solid <?php echo esc_html($accent); ?> !important; }
            .login #login_error { border-left: 4px solid #ff4d4f !important; }

            <?php
            // $logo_css is built entirely from static CSS + esc_url($logo); safe to print as-is.
            echo $logo_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
        </style>
        <?php
    }
}
