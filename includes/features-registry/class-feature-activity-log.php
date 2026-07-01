<?php

/**
 * Free feature: Activity Log.
 *
 * A lightweight audit trail of important site events — logins (success/failed/
 * logout), user changes, content publish/trash, plugin/theme activation, and
 * WP Arzo feature toggles. Entries are stored in a capped option so the free
 * tier needs no custom table; the Pro audit log (a separate module) can extend
 * this with a real table, retention windows, and export.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================= Engine */

class WP_Arzo_Activity_Log
{
    const OPT = 'wp_arzo_activity_log';
    const MAX = 1000; // hard ceiling regardless of the retention setting.

    private static $instance = null;
    private $retention = 300;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function set_retention($n)
    {
        $n = (int) $n;
        $this->retention = max(20, min(self::MAX, $n));
    }

    /** @return array list of entries, newest first. */
    public function get_log()
    {
        $v = get_option(self::OPT, array());
        return is_array($v) ? $v : array();
    }

    public function clear()
    {
        update_option(self::OPT, array(), false);
    }

    /**
     * Record an event.
     *
     * @param string $action machine key (e.g. 'login', 'plugin_activated').
     * @param string $object human description of what was acted on.
     * @param int    $user_id optional explicit actor (defaults to current user).
     */
    public function record($action, $object = '', $user_id = 0)
    {
        $user = $user_id ? get_userdata($user_id) : wp_get_current_user();
        $entry = array(
            't'  => time(),
            'u'  => $user ? (int) $user->ID : 0,
            'ul' => $user && $user->user_login ? $user->user_login : '—',
            'a'  => sanitize_key($action),
            'o'  => sanitize_text_field($object),
            'ip' => $this->ip(),
        );
        $log = $this->get_log();
        array_unshift($log, $entry);
        if (count($log) > $this->retention) {
            $log = array_slice($log, 0, $this->retention);
        }
        update_option(self::OPT, $log, false);

        /**
         * Fires after an activity entry is recorded. Extension point for the Pro
         * Advanced Audit Log, which mirrors each event into a durable DB table.
         *
         * @param array $entry { t, u, ul, a, o, ip }
         */
        do_action('wp_arzo_activity_recorded', $entry);
    }

    private function ip()
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? wp_unslash($_SERVER['REMOTE_ADDR']) : '';
        $ip = preg_replace('/[^0-9a-f:.]/i', '', (string) $ip);
        return substr($ip, 0, 45);
    }

    /* ---------------------------------------------------- hook callbacks */

    public function on_login($user_login, $user = null)
    {
        $this->record('login', 'Signed in', $user instanceof WP_User ? $user->ID : 0);
    }

    public function on_login_failed($username)
    {
        // No actor — store the attempted username in the object field.
        $this->record('login_failed', 'Failed sign-in for "' . (string) $username . '"');
    }

    public function on_logout($user_id = 0)
    {
        $this->record('logout', 'Signed out', (int) $user_id);
    }

    public function on_user_register($user_id)
    {
        $u = get_userdata($user_id);
        $this->record('user_created', 'New user: ' . ($u ? $u->user_login : '#' . $user_id));
    }

    public function on_user_deleted($user_id)
    {
        $u = get_userdata($user_id);
        $this->record('user_deleted', 'Deleted user: ' . ($u ? $u->user_login : '#' . $user_id));
    }

    public function on_role_change($user_id, $role, $old_roles = array())
    {
        $u = get_userdata($user_id);
        $this->record('role_changed', ($u ? $u->user_login : '#' . $user_id) . ' → ' . (string) $role);
    }

    public function on_post_transition($new_status, $old_status, $post)
    {
        if (!$post instanceof WP_Post || in_array($post->post_type, array('revision', 'nav_menu_item'), true)) {
            return;
        }
        if ($new_status === $old_status) {
            return;
        }
        $label = get_post_type_object($post->post_type);
        $label = $label ? $label->labels->singular_name : $post->post_type;
        if ($new_status === 'publish') {
            $this->record('post_published', $label . ' published: ' . $post->post_title);
        } elseif ($new_status === 'trash') {
            $this->record('post_trashed', $label . ' trashed: ' . $post->post_title);
        }
    }

    public function on_plugin_activated($plugin)
    {
        $this->record('plugin_activated', 'Activated plugin: ' . (string) $plugin);
    }

    public function on_plugin_deactivated($plugin)
    {
        $this->record('plugin_deactivated', 'Deactivated plugin: ' . (string) $plugin);
    }

    public function on_theme_switch($new_name)
    {
        $this->record('theme_switched', 'Switched theme to: ' . (string) $new_name);
    }

    public function on_feature_toggle($id, $enabled)
    {
        $this->record('feature_toggled', 'WP Arzo feature "' . (string) $id . '" ' . ($enabled ? 'enabled' : 'disabled'));
    }

    /* ------------------------------------------------- display helpers */

    /** Map an action key to a label, badge tone, and icon for the admin table. */
    public static function action_meta($action)
    {
        $map = array(
            'login'               => array('Login', 'success', 'shield'),
            'login_failed'        => array('Failed login', 'error', 'shield'),
            'logout'              => array('Logout', 'neutral', 'shield'),
            'user_created'        => array('User created', 'info', 'users'),
            'user_deleted'        => array('User deleted', 'error', 'users'),
            'role_changed'        => array('Role changed', 'warning', 'users'),
            'post_published'      => array('Published', 'success', 'file'),
            'post_trashed'        => array('Trashed', 'warning', 'trash'),
            'plugin_activated'    => array('Plugin on', 'success', 'tools'),
            'plugin_deactivated'  => array('Plugin off', 'neutral', 'tools'),
            'theme_switched'      => array('Theme switched', 'info', 'sparkles'),
            'feature_toggled'     => array('WP Arzo toggle', 'info', 'settings'),
        );
        return isset($map[$action]) ? $map[$action] : array(ucfirst(str_replace('_', ' ', $action)), 'neutral', 'bolt');
    }
}

/* ============================================================== Feature */

class WP_Arzo_Feature_Activity_Log extends WP_Arzo_Feature
{
    public function id()
    {
        return 'activity_log';
    }
    public function title()
    {
        return 'Activity Log';
    }
    public function description()
    {
        return 'Record an audit trail of logins, user/content changes, and plugin/theme activity (view under WP Arzo → Activity Log).';
    }
    public function group()
    {
        return 'security';
    }
    public function tier()
    {
        return 'free';
    }
    public function icon()
    {
        return 'shield';
    }

    public function settings_schema()
    {
        return array(
            'log_auth' => array(
                'type'    => 'toggle',
                'label'   => 'Log authentication',
                'help'    => 'Successful + failed logins and logouts.',
                'default' => true,
            ),
            'log_users' => array(
                'type'    => 'toggle',
                'label'   => 'Log user changes',
                'help'    => 'User created/deleted and role changes.',
                'default' => true,
            ),
            'log_content' => array(
                'type'    => 'toggle',
                'label'   => 'Log content changes',
                'help'    => 'Posts/pages/CPTs published or trashed.',
                'default' => true,
            ),
            'log_plugins' => array(
                'type'    => 'toggle',
                'label'   => 'Log plugin & theme activity',
                'help'    => 'Plugin activate/deactivate and theme switches.',
                'default' => true,
            ),
            'retention' => array(
                'type'    => 'number',
                'label'   => 'Entries to keep',
                'help'    => 'Oldest entries are dropped past this many (20–1000).',
                'default' => 300,
                'min'     => 20,
                'max'     => 1000,
            ),
        );
    }

    public function boot()
    {
        $engine = WP_Arzo_Activity_Log::instance();
        $engine->set_retention($this->get_setting('retention', 300));

        if ($this->get_setting('log_auth', true)) {
            add_action('wp_login', array($engine, 'on_login'), 10, 2);
            add_action('wp_login_failed', array($engine, 'on_login_failed'));
            add_action('wp_logout', array($engine, 'on_logout'));
        }
        if ($this->get_setting('log_users', true)) {
            add_action('user_register', array($engine, 'on_user_register'));
            add_action('deleted_user', array($engine, 'on_user_deleted'));
            add_action('set_user_role', array($engine, 'on_role_change'), 10, 3);
        }
        if ($this->get_setting('log_content', true)) {
            add_action('transition_post_status', array($engine, 'on_post_transition'), 10, 3);
        }
        if ($this->get_setting('log_plugins', true)) {
            add_action('activated_plugin', array($engine, 'on_plugin_activated'));
            add_action('deactivated_plugin', array($engine, 'on_plugin_deactivated'));
            add_action('switch_theme', array($engine, 'on_theme_switch'));
        }

        // Always log our own feature toggles when the log is enabled.
        add_action('wp_arzo_feature_toggled', array($engine, 'on_feature_toggle'), 10, 2);
    }
}
