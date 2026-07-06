<?php

/**
 * WP Arzo — Temporary Login links.
 *
 * Passwordless, time-limited, single-/multi-use login links that sign a visitor
 * in as a chosen role. A temporary login is a real WordPress user marked with
 * `wp_arzo_tl_*` usermeta (token, role, expiry, redirect, usage), so it plugs into
 * the normal auth/content lifecycle. The engine runs site-wide (loaded in
 * wp-arzo.php) — links work even outside the console — while the management UI
 * lives in the Advanced Tools "Quick Login" tab (features/login.php).
 *
 * Improvements over typical temp-login plugins: a capability guard (you can't mint
 * a link more privileged than your own account), enforced max-use limits, and a
 * daily cron that deletes expired accounts (reassigning their content to the creator).
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Temp_Login
{
    const META_MARKER     = 'wp_arzo_tl_user';
    const META_TOKEN      = 'wp_arzo_tl_token';
    const META_ROLE       = 'wp_arzo_tl_role';
    const META_REDIRECT   = 'wp_arzo_tl_redirect';
    const META_EXPIRE     = 'wp_arzo_tl_expire';      // absolute UNIX ts (UTC)
    const META_CREATED_BY = 'wp_arzo_tl_created_by';
    const META_CREATED    = 'wp_arzo_tl_created_gmt';
    const META_LAST_LOGIN = 'wp_arzo_tl_last_login_gmt';
    const META_LAST_IP    = 'wp_arzo_tl_last_ip';
    const META_COUNT      = 'wp_arzo_tl_login_count';
    const META_MAX        = 'wp_arzo_tl_max_logins';  // 0 = unlimited
    const META_STATUS     = 'wp_arzo_tl_status';      // active | disabled

    const QUERY_VAR = 'wp_arzo_tl';
    const CRON_HOOK = 'wp_arzo_tl_gc';

    /** @var WP_Arzo_Temp_Login|null */
    private static $instance = null;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init()
    {
        add_action('init', array($this, 'handle_login'), 1);
        add_action('init', array($this, 'enforce_session_expiry'), 2);
        add_action('admin_init', array($this, 'restrict_temp_users'));
        add_filter('allow_password_reset', array($this, 'block_password_reset'), 10, 2);

        add_action(self::CRON_HOOK, array($this, 'gc'));
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    /* --------------------------------------------------------- login flow */

    /** Authenticate a request carrying a valid one-time token. */
    public function handle_login()
    {
        if (empty($_GET[self::QUERY_VAR])) {
            return;
        }
        $token = preg_replace('/[^a-f0-9]/', '', (string) wp_unslash($_GET[self::QUERY_VAR]));
        if (strlen($token) !== 64) {
            $this->reject();
        }

        $found = get_users(array(
            'meta_key'   => self::META_TOKEN,
            'meta_value' => $token,
            'number'     => 1,
            'fields'     => 'ID',
        ));
        if (empty($found)) {
            $this->reject();
        }
        $uid = (int) $found[0];

        $verdict = self::evaluate(
            get_user_meta($uid, self::META_STATUS, true),
            (int) get_user_meta($uid, self::META_EXPIRE, true),
            (int) get_user_meta($uid, self::META_MAX, true),
            (int) get_user_meta($uid, self::META_COUNT, true)
        );
        if ($verdict !== 'ok') {
            $messages = array(
                'disabled' => 'This login link has been disabled.',
                'expired'  => 'This login link has expired.',
                'limit'    => 'This login link has reached its usage limit.',
            );
            $this->reject(isset($messages[$verdict]) ? $messages[$verdict] : 'This login link is invalid.');
        }
        $count = (int) get_user_meta($uid, self::META_COUNT, true);

        // Sign in.
        update_user_meta($uid, self::META_LAST_LOGIN, gmdate('Y-m-d H:i:s'));
        update_user_meta($uid, self::META_LAST_IP, self::client_ip());
        update_user_meta($uid, self::META_COUNT, $count + 1);
        wp_set_current_user($uid);
        wp_set_auth_cookie($uid, true);
        $user = get_userdata($uid);
        if ($user) {
            do_action('wp_login', $user->user_login, $user);
        }

        $redirect = get_user_meta($uid, self::META_REDIRECT, true);
        if (!$redirect) {
            $redirect = admin_url();
        }
        wp_safe_redirect($redirect);
        exit;
    }

    private function reject($msg = 'This login link is invalid.')
    {
        wp_die(esc_html($msg), 'Temporary login', array('response' => 403, 'back_link' => true));
    }

    /** Log out a temp user whose link has expired mid-session. */
    public function enforce_session_expiry()
    {
        if (is_user_logged_in() && self::is_temp(get_current_user_id()) && self::is_expired(get_current_user_id())) {
            wp_logout();
            if (!is_admin()) {
                wp_safe_redirect(home_url('/'));
                exit;
            }
        }
    }

    /** Temp users can't manage users/profiles. */
    public function restrict_temp_users()
    {
        if (!is_user_logged_in() || !self::is_temp(get_current_user_id())) {
            return;
        }
        global $pagenow;
        if (in_array($pagenow, array('profile.php', 'user-edit.php', 'users.php', 'user-new.php'), true)) {
            wp_die('Temporary accounts cannot manage users or profiles.', 'Not allowed', array('response' => 403));
        }
    }

    public function block_password_reset($allow, $user_id)
    {
        return self::is_temp($user_id) ? false : $allow;
    }

    /* ------------------------------------------------------------- CRUD */

    /**
     * Create a temporary login. Returns ['user_id','token','login_url','expire'] or WP_Error.
     *
     * @param array $args role, email, name, redirect, expiry, expire_at, max_logins
     */
    public function create($args)
    {
        if (!current_user_can('manage_options')) {
            return new WP_Error('cap', 'You do not have permission to create logins.');
        }
        $role = isset($args['role']) ? sanitize_key($args['role']) : 'administrator';
        $role_obj = get_role($role);
        if (!$role_obj) {
            return new WP_Error('role', 'Unknown role.');
        }
        // Capability guard: never mint a link more privileged than the creator.
        if (is_array($role_obj->capabilities)) {
            foreach ($role_obj->capabilities as $cap => $on) {
                if ($on && !current_user_can($cap)) {
                    return new WP_Error('elevate', 'You can’t create a login more privileged than your own account.');
                }
            }
        }

        $email = isset($args['email']) ? sanitize_email($args['email']) : '';
        if ($email !== '' && email_exists($email)) {
            return new WP_Error('email', 'That email already belongs to an account.');
        }
        $name  = isset($args['name']) ? sanitize_text_field($args['name']) : '';
        $login = $this->unique_login($name);
        if ($email === '') {
            $host  = wp_parse_url(home_url('/'), PHP_URL_HOST);
            $email = $login . '@' . ($host ? $host : 'example.com');
        }

        $user_id = wp_insert_user(array(
            'user_login'   => $login,
            'user_pass'    => wp_generate_password(24, true, true),
            'user_email'   => $email,
            'role'         => $role,
            'display_name' => $name !== '' ? $name : $login,
        ));
        if (is_wp_error($user_id)) {
            return $user_id;
        }

        $token  = bin2hex(random_bytes(32));
        $expire = $this->compute_expire($args);

        update_user_meta($user_id, self::META_MARKER, 1);
        update_user_meta($user_id, self::META_TOKEN, $token);
        update_user_meta($user_id, self::META_ROLE, $role);
        update_user_meta($user_id, self::META_REDIRECT, isset($args['redirect']) ? esc_url_raw($args['redirect']) : '');
        update_user_meta($user_id, self::META_EXPIRE, $expire);
        update_user_meta($user_id, self::META_CREATED_BY, get_current_user_id());
        update_user_meta($user_id, self::META_CREATED, gmdate('Y-m-d H:i:s'));
        update_user_meta($user_id, self::META_COUNT, 0);
        update_user_meta($user_id, self::META_MAX, isset($args['max_logins']) ? max(0, (int) $args['max_logins']) : 0);
        update_user_meta($user_id, self::META_STATUS, 'active');

        return array(
            'user_id'   => $user_id,
            'token'     => $token,
            'login_url' => $this->login_url($token),
            'expire'    => $expire,
        );
    }

    /**
     * Email a branded login-link invitation to the temp user's address.
     *
     * @param int    $id      Temp user ID.
     * @param string $message Optional personal note prepended to the email.
     * @return true|WP_Error
     */
    public function send_invite($id, $message = '')
    {
        $id = (int) $id;
        if (!self::is_temp($id)) {
            return new WP_Error('not_temp', 'Not a temporary login.');
        }
        $user = get_userdata($id);
        if (!$user || !is_email($user->user_email)) {
            return new WP_Error('email', 'This login has no valid email address to send to.');
        }

        $token = get_user_meta($id, self::META_TOKEN, true);
        if (!$token) {
            return new WP_Error('token', 'This login link is unavailable.');
        }
        $verdict = self::evaluate(
            get_user_meta($id, self::META_STATUS, true),
            (int) get_user_meta($id, self::META_EXPIRE, true),
            (int) get_user_meta($id, self::META_MAX, true),
            (int) get_user_meta($id, self::META_COUNT, true)
        );
        if ($verdict !== 'ok') {
            return new WP_Error('inactive', 'This login link is not active, so it wasn’t emailed.');
        }

        $url    = $this->login_url($token);
        $site   = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $expire = (int) get_user_meta($id, self::META_EXPIRE, true);
        $role   = get_user_meta($id, self::META_ROLE, true);

        $subject = sprintf('Your login link for %s', $site);
        $lines   = array();
        $lines[] = sprintf('Hello%s,', $user->display_name ? ' ' . $user->display_name : '');
        $lines[] = '';
        $note = trim(wp_strip_all_tags((string) $message));
        if ($note !== '') {
            $lines[] = $note;
            $lines[] = '';
        }
        $lines[] = sprintf('You have been given temporary "%s" access to %s. Use this one-tap link to sign in — no password needed:', $role, $site);
        $lines[] = '';
        $lines[] = $url;
        $lines[] = '';
        if ($expire) {
            $lines[] = sprintf('This link expires on %s UTC.', gmdate('Y-m-d H:i', $expire));
        }
        $lines[] = 'If you weren’t expecting this, you can safely ignore this email.';

        $sent = wp_mail($user->user_email, $subject, implode("\n", $lines));
        return $sent ? true : new WP_Error('send', 'The email could not be sent (check the site’s mail configuration).');
    }

    public function set_status($id, $status)
    {
        $id = (int) $id;
        if (!self::is_temp($id)) {
            return false;
        }
        $status = ($status === 'disabled') ? 'disabled' : 'active';
        update_user_meta($id, self::META_STATUS, $status);
        return true;
    }

    public function delete($id)
    {
        $id = (int) $id;
        if (!self::is_temp($id)) {
            return false;
        }
        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        $reassign = (int) get_user_meta($id, self::META_CREATED_BY, true);
        if (!$reassign || !get_userdata($reassign) || $reassign === $id) {
            $reassign = get_current_user_id();
        }
        return wp_delete_user($id, $reassign ?: null);
    }

    /** Daily cleanup: delete expired temp users. */
    public function gc()
    {
        $ids = get_users(array(
            'meta_key'   => self::META_MARKER,
            'meta_value' => 1,
            'fields'     => 'ID',
        ));
        foreach ($ids as $uid) {
            if (self::is_expired($uid)) {
                $this->delete($uid);
            }
        }
    }

    /** All temp logins as display rows. */
    public function all()
    {
        $users = get_users(array(
            'meta_key' => self::META_MARKER,
            'meta_value' => 1,
            'orderby' => 'registered',
            'order' => 'DESC',
        ));
        $out = array();
        foreach ($users as $u) {
            $token = get_user_meta($u->ID, self::META_TOKEN, true);
            $out[] = array(
                'id'         => $u->ID,
                'login'      => $u->user_login,
                'email'      => $u->user_email,
                'name'       => $u->display_name,
                'role'       => get_user_meta($u->ID, self::META_ROLE, true),
                'expire'     => (int) get_user_meta($u->ID, self::META_EXPIRE, true),
                'count'      => (int) get_user_meta($u->ID, self::META_COUNT, true),
                'max'        => (int) get_user_meta($u->ID, self::META_MAX, true),
                'last_login' => get_user_meta($u->ID, self::META_LAST_LOGIN, true),
                'last_ip'    => get_user_meta($u->ID, self::META_LAST_IP, true),
                'status'     => get_user_meta($u->ID, self::META_STATUS, true) ?: 'active',
                'login_url'  => $this->login_url($token),
            );
        }
        return $out;
    }

    /* --------------------------------------------------------- helpers */

    /**
     * Pure decision: may a link with this (status, expiry, max, count) sign in now?
     *
     * @return string 'ok' | 'disabled' | 'expired' | 'limit'
     */
    public static function evaluate($status, $expire, $max, $count, $now = null)
    {
        $now = $now !== null ? (int) $now : time();
        if ($status === 'disabled') {
            return 'disabled';
        }
        if ($expire && $now > (int) $expire) {
            return 'expired';
        }
        if ((int) $max > 0 && (int) $count >= (int) $max) {
            return 'limit';
        }
        return 'ok';
    }

    public static function is_temp($uid)
    {
        return (bool) get_user_meta((int) $uid, self::META_MARKER, true);
    }

    public static function is_expired($uid)
    {
        $e = (int) get_user_meta((int) $uid, self::META_EXPIRE, true);
        return $e && time() > $e;
    }

    public function login_url($token)
    {
        return add_query_arg(self::QUERY_VAR, $token, home_url('/'));
    }

    /** Best-effort client IP, validated; empty string if none. */
    public static function client_ip()
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        $ip = filter_var(trim($ip), FILTER_VALIDATE_IP);
        return $ip ? $ip : '';
    }

    private function unique_login($name)
    {
        $base = sanitize_user($name !== '' ? $name : 'temp-user', true);
        $base = $base !== '' ? strtolower(str_replace(' ', '', $base)) : 'temp-user';
        $login = 'tl-' . $base . '-' . substr(bin2hex(random_bytes(4)), 0, 6);
        $i = 0;
        while (username_exists($login) && $i < 5) {
            $login = 'tl-' . $base . '-' . substr(bin2hex(random_bytes(4)), 0, 6);
            $i++;
        }
        return $login;
    }

    /** Map an expiry preset (or custom datetime) to an absolute UTC timestamp. */
    private function compute_expire($args)
    {
        $preset = isset($args['expiry']) ? $args['expiry'] : '1day';
        if ($preset === 'custom' && !empty($args['expire_at'])) {
            $ts = strtotime((string) $args['expire_at'] . ' UTC');
            if ($ts && $ts > time()) {
                return $ts;
            }
        }
        $map = array(
            '1hour'  => HOUR_IN_SECONDS,
            '6hours' => 6 * HOUR_IN_SECONDS,
            '1day'   => DAY_IN_SECONDS,
            '1week'  => WEEK_IN_SECONDS,
            '1month' => MONTH_IN_SECONDS,
        );
        $dur = isset($map[$preset]) ? $map[$preset] : DAY_IN_SECONDS;
        return time() + $dur;
    }

    /** Delete every temp user — used by uninstall. */
    public static function delete_all()
    {
        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        $ids = get_users(array('meta_key' => self::META_MARKER, 'meta_value' => 1, 'fields' => 'ID'));
        foreach ($ids as $uid) {
            wp_delete_user((int) $uid);
        }
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }
}
