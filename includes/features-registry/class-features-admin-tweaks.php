<?php

/**
 * Free admin/utility tweaks: Last Login column, custom header/body/footer code,
 * custom CSS, disable all updates, login/logout redirects.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Last_Login extends WP_Arzo_Feature
{
    public function id()
    {
        return 'last_login';
    }
    public function title()
    {
        return 'Last Login Column';
    }
    public function description()
    {
        return 'Record each user’s last login time and show it as a column in the Users list.';
    }
    public function group()
    {
        return 'utilities';
    }
    public function icon()
    {
        return 'clock';
    }
    public function boot()
    {
        add_action('wp_login', array($this, 'record'), 10, 2);
        add_filter('manage_users_columns', array($this, 'column'));
        add_filter('manage_users_custom_column', array($this, 'cell'), 10, 3);
    }
    public function record($user_login, $user)
    {
        if ($user instanceof WP_User) {
            update_user_meta($user->ID, 'wp_arzo_last_login', time());
        }
    }
    public function column($cols)
    {
        $cols['wp_arzo_last_login'] = __('Last Login', 'arzo-administration-suite');
        return $cols;
    }
    public function cell($output, $column, $user_id)
    {
        if ($column === 'wp_arzo_last_login') {
            $time = (int) get_user_meta($user_id, 'wp_arzo_last_login', true);
            return $time ? esc_html(wp_date('Y-m-d H:i', $time)) : '—';
        }
        return $output;
    }
}

class WP_Arzo_Feature_Custom_Code extends WP_Arzo_Feature
{
    public function id()
    {
        return 'custom_code';
    }
    public function title()
    {
        return 'Header / Body / Footer Code';
    }
    public function description()
    {
        return 'Insert custom code (analytics, verification tags, scripts) into the head, after <body>, or before </body>.';
    }
    public function group()
    {
        return 'developer';
    }
    public function icon()
    {
        return 'code';
    }
    public function settings_schema()
    {
        return array(
            array('key' => 'head', 'type' => 'code', 'label' => 'Inside <head>'),
            array('key' => 'body_open', 'type' => 'code', 'label' => 'After opening <body>'),
            array('key' => 'footer', 'type' => 'code', 'label' => 'Before closing </body>'),
        );
    }
    public function boot()
    {
        // Insert-Headers-and-Footers pattern: raw custom code entered by an admin
        // (manage_options) is output verbatim by design; escaping would defeat the feature.
        add_action('wp_head', function () {
            echo (string) $this->get_setting('head', ''); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }, 99);
        add_action('wp_body_open', function () {
            echo (string) $this->get_setting('body_open', ''); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        });
        add_action('wp_footer', function () {
            echo (string) $this->get_setting('footer', ''); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }, 99);
    }
}

class WP_Arzo_Feature_Custom_CSS extends WP_Arzo_Feature
{
    public function id()
    {
        return 'custom_css';
    }
    public function title()
    {
        return 'Custom CSS';
    }
    public function description()
    {
        return 'Add custom CSS to the front end and/or the WordPress admin.';
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
            array('key' => 'frontend_css', 'type' => 'code', 'label' => 'Front-end CSS'),
            array('key' => 'admin_css', 'type' => 'code', 'label' => 'Admin CSS'),
        );
    }
    public function boot()
    {
        add_action('wp_head', function () {
            $this->emit('frontend_css');
        }, 100);
        add_action('admin_head', function () {
            $this->emit('admin_css');
        }, 100);
    }
    private function emit($key)
    {
        $css = trim((string) $this->get_setting($key, ''));
        if ($css !== '') {
            // Admin-supplied (manage_options) custom CSS; tags stripped so it can't break out of <style>.
            echo "\n<style id='wp-arzo-" . esc_attr($key) . "'>" . wp_strip_all_tags($css) . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}

class WP_Arzo_Feature_Login_Redirect extends WP_Arzo_Feature
{
    public function id()
    {
        return 'login_redirect';
    }
    public function title()
    {
        return 'Login / Logout Redirects';
    }
    public function description()
    {
        return 'Send users to a custom URL after they log in or log out.';
    }
    public function group()
    {
        return 'utilities';
    }
    public function icon()
    {
        return 'external';
    }
    public function settings_schema()
    {
        return array(
            array('key' => 'login_url', 'type' => 'text', 'label' => 'After-login URL', 'help' => 'Leave blank for the WordPress default.'),
            array('key' => 'login_scope', 'type' => 'select', 'label' => 'Apply after-login redirect to', 'default' => 'non_admins', 'options' => array('all' => 'Everyone', 'non_admins' => 'Non-administrators only')),
            array('key' => 'logout_url', 'type' => 'text', 'label' => 'After-logout URL'),
        );
    }
    public function boot()
    {
        add_filter('login_redirect', array($this, 'after_login'), 10, 3);
        add_action('wp_logout', array($this, 'after_logout'));
    }
    public function after_login($redirect_to, $requested, $user)
    {
        $url = trim((string) $this->get_setting('login_url', ''));
        if ($url === '' || !($user instanceof WP_User)) {
            return $redirect_to;
        }
        if ($this->get_setting('login_scope', 'non_admins') === 'non_admins' && user_can($user, 'manage_options')) {
            return $redirect_to;
        }
        return $url;
    }
    public function after_logout()
    {
        $url = trim((string) $this->get_setting('logout_url', ''));
        if ($url !== '') {
            wp_safe_redirect($url);
            exit;
        }
    }
}
