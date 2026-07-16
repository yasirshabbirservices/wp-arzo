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
            return $time ? esc_html(date_i18n('Y-m-d H:i', $time)) : '—';
        }
        return $output;
    }
}

// WP_Arzo_Feature_Custom_Code (Header/Body/Footer script insertion) and
// WP_Arzo_Feature_Custom_CSS (raw CSS code-editor field) moved to the Pro add-on —
// WordPress.org treats any raw code-entry field, including CSS, as arbitrary code
// insertion that a free directory listing may not offer. See wp-arzo-pro:
// includes/features/class-feature-custom-code.php and class-feature-custom-css.php.

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
