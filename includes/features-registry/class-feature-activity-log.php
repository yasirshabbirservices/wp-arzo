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
    const FAILWIN_OPT = 'wp_arzo_activity_failwin'; // rolling failed-login window state.
    const MAX = 1000; // hard ceiling regardless of the retention setting.

    private static $instance = null;
    private $retention = 300;

    // Failed-login burst detection (brute-force anomaly alert).
    private $anomaly = false;
    private $anomaly_threshold = 10;
    private $anomaly_window = 15; // minutes

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

    /** Configure failed-login burst detection. */
    public function set_anomaly($enabled, $threshold, $window_min)
    {
        $this->anomaly = (bool) $enabled;
        $this->anomaly_threshold = max(3, min(1000, (int) $threshold));
        $this->anomaly_window    = max(1, min(1440, (int) $window_min));
    }

    /**
     * Track a failed sign-in in a rolling window and, when the burst threshold is
     * crossed, record a Critical `security_alert` event (which fans out to the Pro
     * Audit Log + Notifications via wp_arzo_activity_recorded). A cooldown equal to
     * the window prevents alert spam; the window resets after each alert.
     */
    private function maybe_anomaly()
    {
        if (!$this->anomaly) {
            return;
        }
        $now = time();
        $win = $this->anomaly_window * MINUTE_IN_SECONDS;

        $state = get_option(self::FAILWIN_OPT, array());
        if (!is_array($state)) {
            $state = array();
        }
        $hits       = (isset($state['hits']) && is_array($state['hits'])) ? $state['hits'] : array();
        $last_alert = isset($state['alert']) ? (int) $state['alert'] : 0;

        $cutoff = $now - $win;
        $hits[] = $now;
        $hits = array_values(array_filter($hits, function ($t) use ($cutoff) {
            return (int) $t >= $cutoff;
        }));

        $fire = false;
        if (count($hits) >= $this->anomaly_threshold && ($now - $last_alert) >= $win) {
            $fire       = true;
            $last_alert = $now;
            $hits       = array(); // reset so a single burst fires exactly once.
        }
        update_option(self::FAILWIN_OPT, array('hits' => $hits, 'alert' => $last_alert), false);

        if ($fire) {
            $this->record('security_alert', sprintf(
                '%d failed sign-ins within %d min — possible brute-force attempt',
                $this->anomaly_threshold,
                $this->anomaly_window
            ));
        }
    }

    /**
     * At-a-glance counters for the Activity Log page header (computed from the
     * capped log; cheap for the free tier's small dataset).
     *
     * @return array{total:int,last24:int,failed24:int,high24:int}
     */
    public function stats()
    {
        $log = $this->get_log();
        $day = time() - DAY_IN_SECONDS;
        $total = count($log);
        $last24 = 0;
        $failed24 = 0;
        $high24 = 0;
        foreach ($log as $r) {
            if ((isset($r['t']) ? (int) $r['t'] : 0) < $day) {
                continue;
            }
            $last24++;
            $a = isset($r['a']) ? $r['a'] : '';
            if ($a === 'login_failed') {
                $failed24++;
            }
            $sev = self::action_severity($a);
            if ($sev === 'critical' || $sev === 'high') {
                $high24++;
            }
        }
        return compact('total', 'last24', 'failed24', 'high24');
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
    public function record($action, $object = '', $user_id = 0, $link = null)
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
        // Optional deep-link to the acted-on object (post/user/comment) so the log row
        // can jump straight to it. Only set for non-destructive events (a deleted object
        // has nothing to link to). Old entries simply lack lt/li → plain text.
        if (is_array($link) && !empty($link['type']) && !empty($link['id'])) {
            $entry['lt'] = sanitize_key($link['type']);
            $entry['li'] = (int) $link['id'];
        }
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
        $this->maybe_anomaly();
    }

    public function on_logout($user_id = 0)
    {
        $this->record('logout', 'Signed out', (int) $user_id);
    }

    public function on_user_register($user_id)
    {
        $u = get_userdata($user_id);
        $this->record('user_created', 'New user: ' . ($u ? $u->user_login : '#' . $user_id), 0, array('type' => 'user', 'id' => $user_id));
    }

    public function on_user_deleted($user_id)
    {
        $u = get_userdata($user_id);
        $this->record('user_deleted', 'Deleted user: ' . ($u ? $u->user_login : '#' . $user_id));
    }

    public function on_role_change($user_id, $role, $old_roles = array())
    {
        $u = get_userdata($user_id);
        $this->record('role_changed', ($u ? $u->user_login : '#' . $user_id) . ' → ' . (string) $role, 0, array('type' => 'user', 'id' => $user_id));
    }

    public function on_password_reset($user)
    {
        $login = ($user instanceof WP_User) ? $user->user_login : '#' . (is_object($user) ? $user->ID : $user);
        $this->record('password_reset', 'Password reset for ' . $login, ($user instanceof WP_User) ? $user->ID : 0);
    }

    public function on_profile_update($user_id)
    {
        $u = get_userdata($user_id);
        $this->record('profile_updated', 'Updated profile: ' . ($u ? $u->user_login : '#' . $user_id), 0, array('type' => 'user', 'id' => $user_id));
    }

    /** Post types that are internal plumbing — never worth an audit entry. */
    private function ignored_post_type($type)
    {
        return in_array($type, array(
            'revision', 'nav_menu_item', 'custom_css', 'customize_changeset',
            'oembed_cache', 'user_request', 'wp_global_styles', 'wp_navigation',
            'wp_template', 'wp_template_part', 'attachment',
        ), true);
    }

    private function post_type_label($post)
    {
        $obj = get_post_type_object($post->post_type);
        return $obj ? $obj->labels->singular_name : $post->post_type;
    }

    public function on_post_updated($post_id, $post_after, $post_before)
    {
        if (!$post_after instanceof WP_Post || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if ($this->ignored_post_type($post_after->post_type) || $post_after->post_status === 'auto-draft') {
            return;
        }
        // Status transitions (publish/trash) are logged by on_post_transition; only log
        // pure edits here so a single action isn't double-recorded.
        if ($post_after->post_status !== $post_before->post_status) {
            return;
        }
        $this->record('post_updated', $this->post_type_label($post_after) . ' updated: ' . $post_after->post_title, 0, array('type' => 'post', 'id' => $post_id));
    }

    public function on_post_deleted($post_id)
    {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || $this->ignored_post_type($post->post_type)) {
            return;
        }
        $this->record('post_deleted', $this->post_type_label($post) . ' deleted: ' . $post->post_title);
    }

    public function on_attachment_added($post_id)
    {
        $this->record('media_uploaded', 'Uploaded media: ' . get_the_title($post_id), 0, array('type' => 'post', 'id' => $post_id));
    }

    public function on_attachment_deleted($post_id)
    {
        $this->record('media_deleted', 'Deleted media: ' . get_the_title($post_id));
    }

    /** Internal taxonomies that aren't meaningful audit events (theme/template plumbing). */
    private function ignored_taxonomy($taxonomy)
    {
        return in_array($taxonomy, array(
            'wp_theme', 'wp_template_part_area', 'wp_pattern_category',
            'nav_menu', 'link_category', 'post_format',
        ), true);
    }

    public function on_term_created($term_id, $tt_id, $taxonomy)
    {
        if ($this->ignored_taxonomy($taxonomy)) {
            return;
        }
        $term = get_term($term_id, $taxonomy);
        $name = ($term && !is_wp_error($term)) ? $term->name : '#' . $term_id;
        $this->record('term_created', $taxonomy . ' created: ' . $name);
    }

    public function on_term_deleted($term, $tt_id, $taxonomy, $deleted_term)
    {
        if ($this->ignored_taxonomy($taxonomy)) {
            return;
        }
        $name = (is_object($deleted_term) && isset($deleted_term->name)) ? $deleted_term->name : '#' . $term;
        $this->record('term_deleted', $taxonomy . ' deleted: ' . $name);
    }

    public function on_comment_post($comment_id, $approved)
    {
        $c = get_comment($comment_id);
        $on = $c ? get_the_title($c->comment_post_ID) : '';
        $this->record('comment_posted', 'Comment' . ($on ? ' on: ' . $on : '') . ($approved === 'spam' ? ' (spam)' : ''), 0, array('type' => 'comment', 'id' => $comment_id));
    }

    public function on_comment_status($new_status, $old_status, $comment)
    {
        if ($new_status === $old_status) {
            return;
        }
        $on  = ($comment && isset($comment->comment_post_ID)) ? get_the_title($comment->comment_post_ID) : '';
        $map = array(
            'approved'   => array('comment_approved', 'Comment approved'),
            'unapproved' => array('comment_unapproved', 'Comment unapproved'),
            'spam'       => array('comment_spam', 'Comment marked spam'),
            'trash'      => array('comment_trashed', 'Comment trashed'),
        );
        if (isset($map[$new_status])) {
            $cid  = ($comment && isset($comment->comment_ID)) ? (int) $comment->comment_ID : 0;
            // A trashed comment can still be opened; spam/approved definitely.
            $link = $cid ? array('type' => 'comment', 'id' => $cid) : null;
            $this->record($map[$new_status][0], $map[$new_status][1] . ($on ? ' on: ' . $on : ''), 0, $link);
        }
    }

    public function on_comment_deleted($comment_id)
    {
        $this->record('comment_deleted', 'Comment permanently deleted (#' . (int) $comment_id . ')');
    }

    public function on_post_transition($new_status, $old_status, $post)
    {
        if (!$post instanceof WP_Post || $this->ignored_post_type($post->post_type)) {
            return;
        }
        if ($new_status === $old_status) {
            return;
        }
        $label = get_post_type_object($post->post_type);
        $label = $label ? $label->labels->singular_name : $post->post_type;
        $link = array('type' => 'post', 'id' => (int) $post->ID);
        if ($new_status === 'publish') {
            $this->record('post_published', $label . ' published: ' . $post->post_title, 0, $link);
        } elseif ($new_status === 'trash') {
            $this->record('post_trashed', $label . ' trashed: ' . $post->post_title, 0, $link);
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

    public function on_plugin_deleted($plugin_file, $deleted)
    {
        if ($deleted) {
            $this->record('plugin_deleted', 'Deleted plugin: ' . (string) $plugin_file);
        }
    }

    public function on_theme_deleted($stylesheet, $deleted)
    {
        if ($deleted) {
            $this->record('theme_deleted', 'Deleted theme: ' . (string) $stylesheet);
        }
    }

    /** upgrader_process_complete: log installs/updates of plugins, themes, and core. */
    public function on_upgrade($upgrader, $hook_extra)
    {
        if (!is_array($hook_extra)) {
            return;
        }
        $type   = isset($hook_extra['type']) ? $hook_extra['type'] : '';
        $action = isset($hook_extra['action']) ? $hook_extra['action'] : 'update';
        $verb   = ($action === 'install') ? 'installed' : 'updated';

        if ($type === 'core') {
            $this->record('core_updated', 'WordPress core ' . $verb);
            return;
        }

        $items = array();
        if (!empty($hook_extra['plugins']) && is_array($hook_extra['plugins'])) {
            $items = $hook_extra['plugins'];
        } elseif (!empty($hook_extra['plugin'])) {
            $items = array($hook_extra['plugin']);
        } elseif (!empty($hook_extra['themes']) && is_array($hook_extra['themes'])) {
            $items = $hook_extra['themes'];
        } elseif (!empty($hook_extra['theme'])) {
            $items = array($hook_extra['theme']);
        }
        $desc = $items ? implode(', ', array_map('sanitize_text_field', $items)) : $type;

        if ($type === 'plugin') {
            $this->record('plugin_' . $verb, 'Plugin ' . $verb . ': ' . $desc);
        } elseif ($type === 'theme') {
            $this->record('theme_' . $verb, 'Theme ' . $verb . ': ' . $desc);
        }
    }

    /** Curated whitelist of "important" options worth logging when they change. */
    private static function watched_options()
    {
        return array(
            'blogname', 'blogdescription', 'admin_email', 'siteurl', 'home',
            'users_can_register', 'default_role', 'timezone_string', 'gmt_offset',
            'date_format', 'time_format', 'start_of_week', 'WPLANG',
            'permalink_structure', 'template', 'stylesheet', 'posts_per_page',
            'default_comment_status', 'blog_public', 'default_pingback_flag',
            'show_on_front', 'page_on_front', 'page_for_posts',
        );
    }

    public function on_option_updated($option, $old_value, $value)
    {
        if (!in_array($option, self::watched_options(), true)) {
            return;
        }
        $this->record('option_changed', 'Setting changed: ' . (string) $option);
    }

    public function on_menu_updated($menu_id)
    {
        $menu = wp_get_nav_menu_object($menu_id);
        $this->record('menu_updated', 'Menu updated: ' . ($menu ? $menu->name : '#' . (int) $menu_id));
    }

    public function on_export()
    {
        $this->record('export', 'Exported site content (Tools → Export)');
    }

    public function on_feature_toggle($id, $enabled)
    {
        $this->record('feature_toggled', 'WP Arzo feature "' . (string) $id . '" ' . ($enabled ? 'enabled' : 'disabled'));
    }

    /* ------------------------------------------------- display helpers */

    /**
     * Severity levels, most→least serious. Each: [label, badge tone, icon, rank].
     * Severity is DERIVED from the action key (see action_severity) so it applies
     * retroactively to entries logged before this model existed.
     */
    public static function severities()
    {
        return array(
            'critical' => array('Critical', 'error',   'alert',  4),
            'high'     => array('High',     'warning', 'shield', 3),
            'medium'   => array('Medium',   'info',    'info',   2),
            'low'      => array('Low',      'neutral', 'dot',    1),
        );
    }

    /** Map an action key to its severity key (critical|high|medium|low). */
    public static function action_severity($action)
    {
        static $map = null;
        if ($map === null) {
            $map = array();
            $groups = array(
                'critical' => array('security_alert', 'user_deleted', 'plugin_deleted', 'theme_deleted'),
                'high'     => array('login_failed', 'role_changed', 'user_created', 'password_reset', 'option_changed', 'plugin_activated', 'plugin_deactivated', 'core_updated', 'session_terminated'),
                'medium'   => array('post_deleted', 'post_trashed', 'media_deleted', 'comment_spam', 'comment_deleted', 'theme_switched', 'plugin_installed', 'plugin_updated', 'theme_installed', 'theme_updated', 'term_deleted', 'menu_updated', 'export'),
            );
            foreach ($groups as $sev => $keys) {
                foreach ($keys as $k) {
                    $map[$k] = $sev;
                }
            }
        }
        return isset($map[$action]) ? $map[$action] : 'low';
    }

    /** severity meta ([label, tone, icon, rank]) for an action key. */
    public static function severity_meta($action)
    {
        $sevs = self::severities();
        $key  = self::action_severity($action);
        return isset($sevs[$key]) ? $sevs[$key] : $sevs['low'];
    }

    /**
     * Build an admin edit URL for a logged object reference ({lt,li}), or '' if
     * none applies / it no longer resolves. Used to deep-link a log row.
     */
    public static function object_edit_url($lt, $li)
    {
        $li = (int) $li;
        if (!$li) {
            return '';
        }
        switch ((string) $lt) {
            case 'post':
                return function_exists('get_edit_post_link') ? (string) get_edit_post_link($li, 'raw') : '';
            case 'user':
                return function_exists('get_edit_user_link') ? (string) get_edit_user_link($li) : '';
            case 'comment':
                return function_exists('get_edit_comment_link') ? (string) get_edit_comment_link($li) : '';
        }
        return '';
    }

    /** Map an action key to a label, badge tone, and icon for the admin table. */
    public static function action_meta($action)
    {
        $map = array(
            'security_alert'      => array('Security alert', 'error', 'alert'),
            'session_terminated'  => array('Session terminated', 'warning', 'lock'),
            'login'               => array('Login', 'success', 'shield'),
            'login_failed'        => array('Failed login', 'error', 'shield'),
            'logout'              => array('Logout', 'neutral', 'shield'),
            'password_reset'      => array('Password reset', 'warning', 'lock'),
            'user_created'        => array('User created', 'info', 'users'),
            'user_deleted'        => array('User deleted', 'error', 'users'),
            'role_changed'        => array('Role changed', 'warning', 'users'),
            'profile_updated'     => array('Profile updated', 'info', 'user'),
            'post_published'      => array('Published', 'success', 'file'),
            'post_updated'        => array('Updated', 'info', 'edit'),
            'post_trashed'        => array('Trashed', 'warning', 'trash'),
            'post_deleted'        => array('Deleted', 'error', 'trash'),
            'media_uploaded'      => array('Media uploaded', 'success', 'image'),
            'media_deleted'       => array('Media deleted', 'warning', 'image'),
            'term_created'        => array('Term created', 'success', 'folder'),
            'term_deleted'        => array('Term deleted', 'warning', 'folder'),
            'comment_posted'      => array('Comment', 'info', 'list'),
            'comment_approved'    => array('Comment approved', 'success', 'check'),
            'comment_unapproved'  => array('Comment unapproved', 'neutral', 'list'),
            'comment_spam'        => array('Comment spam', 'warning', 'shield'),
            'comment_trashed'     => array('Comment trashed', 'warning', 'trash'),
            'comment_deleted'     => array('Comment deleted', 'error', 'trash'),
            'plugin_activated'    => array('Plugin on', 'success', 'tools'),
            'plugin_deactivated'  => array('Plugin off', 'neutral', 'tools'),
            'plugin_deleted'      => array('Plugin deleted', 'error', 'plugin'),
            'plugin_installed'    => array('Plugin installed', 'success', 'plugin'),
            'plugin_updated'      => array('Plugin updated', 'info', 'plugin'),
            'theme_switched'      => array('Theme switched', 'info', 'sparkles'),
            'theme_deleted'       => array('Theme deleted', 'error', 'theme'),
            'theme_installed'     => array('Theme installed', 'success', 'theme'),
            'theme_updated'       => array('Theme updated', 'info', 'theme'),
            'core_updated'        => array('WordPress updated', 'info', 'sparkles'),
            'option_changed'      => array('Setting changed', 'warning', 'settings'),
            'menu_updated'        => array('Menu updated', 'info', 'list'),
            'export'              => array('Export', 'info', 'download'),
            'feature_toggled'     => array('WP Arzo toggle', 'info', 'settings'),
        );
        return isset($map[$action]) ? $map[$action] : array(ucfirst(str_replace('_', ' ', $action)), 'neutral', 'bolt');
    }
}

/* =================================================== Live sessions */

/**
 * Read + manage live WordPress login sessions (the `session_tokens` user-meta
 * that core's WP_Session_Tokens writes). Powers the Activity Log → Sessions tab:
 * see who is signed in right now, from where, and terminate a session (force
 * logout) — a security tool the free Activity Log competitors gate behind Pro.
 */
class WP_Arzo_Activity_Sessions
{
    const MAX_USERS = 500; // safety cap on how many session-bearing users we scan.

    /** Replicates WP_User_Meta_Session_Tokens::hash_token so we can match by verifier. */
    public static function hash_token($token)
    {
        $token = (string) $token;
        return function_exists('hash') ? hash('sha256', $token) : sha1($token);
    }

    /** Verifier (stored key) of the CURRENT request's session, or '' if none. */
    public static function current_verifier()
    {
        if (!function_exists('wp_get_session_token')) {
            return '';
        }
        $token = wp_get_session_token();
        return $token ? self::hash_token($token) : '';
    }

    /**
     * Flatten raw `session_tokens` meta rows into a per-session list. Pure (no WP
     * calls beyond maybe_unserialize) so it can be unit-harnessed.
     *
     * @param array  $rows              [{user_id, meta_value(serialized)}...]
     * @param int    $now               reference timestamp for the expired flag.
     * @param string $current_verifier  verifier of the viewer's own session.
     * @return array session rows, newest login first.
     */
    public static function parse_sessions($rows, $now, $current_verifier)
    {
        $out = array();
        foreach ((array) $rows as $r) {
            $uid  = (int) (is_array($r) ? ($r['user_id'] ?? 0) : 0);
            $data = maybe_unserialize(is_array($r) ? ($r['meta_value'] ?? '') : '');
            if (!is_array($data)) {
                continue;
            }
            foreach ($data as $verifier => $s) {
                if (!is_array($s)) {
                    continue;
                }
                $exp = isset($s['expiration']) ? (int) $s['expiration'] : 0;
                $out[] = array(
                    'user_id'    => $uid,
                    'verifier'   => (string) $verifier,
                    'login'      => isset($s['login']) ? (int) $s['login'] : 0,
                    'expiration' => $exp,
                    'ip'         => isset($s['ip']) ? (string) $s['ip'] : '',
                    'ua'         => isset($s['ua']) ? (string) $s['ua'] : '',
                    'expired'    => ($exp > 0 && $exp < $now),
                    'is_current' => ($current_verifier !== '' && hash_equals((string) $current_verifier, (string) $verifier)),
                );
            }
        }
        usort($out, function ($a, $b) {
            if ($b['login'] === $a['login']) {
                return strcmp($a['verifier'], $b['verifier']);
            }
            return $b['login'] - $a['login'];
        });
        return $out;
    }

    /** All active sessions site-wide, decorated with user info. */
    public static function all()
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'session_tokens' LIMIT %d",
                self::MAX_USERS
            ),
            ARRAY_A
        );
        $sessions = self::parse_sessions($rows, time(), self::current_verifier());

        $cache = array();
        foreach ($sessions as &$s) {
            $uid = $s['user_id'];
            if (!isset($cache[$uid])) {
                $u = get_userdata($uid);
                // NB: key is 'user_login' (not 'login') — 'login' is the session's
                // timestamp and must not be clobbered by the array_merge below.
                $cache[$uid] = array(
                    'user_login' => $u ? $u->user_login : '#' . $uid,
                    'display'    => $u ? $u->display_name : '',
                    'roles'      => $u ? implode(', ', (array) $u->roles) : '',
                    'edit'       => $u ? get_edit_user_link($uid) : '',
                );
            }
            $s = array_merge($s, $cache[$uid]);
        }
        unset($s);
        return $sessions;
    }

    /** Count of active (non-expired) sessions — used for the tab badge. */
    public static function count_active()
    {
        $n = 0;
        foreach (self::all() as $s) {
            if (empty($s['expired'])) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * Terminate one session by its stored verifier (force logout of that device).
     * Manipulates the same `session_tokens` meta core uses.
     *
     * @return bool true if a session was removed.
     */
    public static function terminate($user_id, $verifier)
    {
        $user_id  = (int) $user_id;
        $verifier = (string) $verifier;
        if (!$user_id || $verifier === '') {
            return false;
        }
        $data = get_user_meta($user_id, 'session_tokens', true);
        if (!is_array($data) || !isset($data[$verifier])) {
            return false;
        }
        unset($data[$verifier]);
        if (empty($data)) {
            delete_user_meta($user_id, 'session_tokens');
        } else {
            update_user_meta($user_id, 'session_tokens', $data);
        }
        return true;
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
        return 'Record a full, severity-graded audit trail of site activity — logins & password resets, user/profile/role changes, content edits & deletes, media, comments, plugin/theme/core installs & updates, and settings changes — with a live summary and brute-force burst alerts (view under WP Arzo → Activity Log).';
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
            array('key' => 'log_auth', 'type' => 'toggle', 'label' => 'Log authentication', 'help' => 'Logins (success + failed), logouts, and password resets.', 'default' => true),
            array('key' => 'log_users', 'type' => 'toggle', 'label' => 'Log user changes', 'help' => 'User created/deleted, role changes, and profile updates.', 'default' => true),
            array('key' => 'log_content', 'type' => 'toggle', 'label' => 'Log content changes', 'help' => 'Posts/pages/CPTs published, updated, trashed or deleted; media uploads/deletes; categories & tags.', 'default' => true),
            array('key' => 'log_comments', 'type' => 'toggle', 'label' => 'Log comments', 'help' => 'Comments posted, approved/unapproved, spammed, trashed or deleted.', 'default' => true),
            array('key' => 'log_plugins', 'type' => 'toggle', 'label' => 'Log plugin & theme activity', 'help' => 'Activate/deactivate/delete, theme switch/delete, and install/update of plugins, themes & core.', 'default' => true),
            array('key' => 'log_settings', 'type' => 'toggle', 'label' => 'Log settings & site changes', 'help' => 'Key option/settings changes, menu edits, and site exports.', 'default' => true),
            array('key' => 'anomaly_alerts', 'type' => 'toggle', 'label' => 'Brute-force alert', 'help' => 'Record a Critical security alert (and fire notifications) when many sign-ins fail in a short window. Requires “Log authentication”.', 'default' => true),
            array('key' => 'anomaly_threshold', 'type' => 'number', 'label' => 'Alert after N failed sign-ins', 'help' => 'How many failed logins within the window trigger the alert (3–1000).', 'default' => 10, 'min' => 3, 'max' => 1000),
            array('key' => 'anomaly_window', 'type' => 'number', 'label' => 'Within (minutes)', 'help' => 'The rolling window the failed sign-ins are counted over (1–1440).', 'default' => 15, 'min' => 1, 'max' => 1440),
            array('key' => 'retention', 'type' => 'number', 'label' => 'Entries to keep', 'help' => 'Oldest entries are dropped past this many (20–1000).', 'default' => 300, 'min' => 20, 'max' => 1000),
        );
    }

    public function boot()
    {
        $engine = WP_Arzo_Activity_Log::instance();
        $engine->set_retention($this->get_setting('retention', 300));
        $engine->set_anomaly(
            $this->get_setting('anomaly_alerts', true),
            $this->get_setting('anomaly_threshold', 10),
            $this->get_setting('anomaly_window', 15)
        );

        if ($this->get_setting('log_auth', true)) {
            add_action('wp_login', array($engine, 'on_login'), 10, 2);
            add_action('wp_login_failed', array($engine, 'on_login_failed'));
            add_action('wp_logout', array($engine, 'on_logout'));
            add_action('after_password_reset', array($engine, 'on_password_reset'));
        }
        if ($this->get_setting('log_users', true)) {
            add_action('user_register', array($engine, 'on_user_register'));
            add_action('deleted_user', array($engine, 'on_user_deleted'));
            add_action('set_user_role', array($engine, 'on_role_change'), 10, 3);
            add_action('profile_update', array($engine, 'on_profile_update'), 10, 1);
        }
        if ($this->get_setting('log_content', true)) {
            add_action('transition_post_status', array($engine, 'on_post_transition'), 10, 3);
            add_action('post_updated', array($engine, 'on_post_updated'), 10, 3);
            add_action('before_delete_post', array($engine, 'on_post_deleted'));
            add_action('add_attachment', array($engine, 'on_attachment_added'));
            add_action('delete_attachment', array($engine, 'on_attachment_deleted'));
            add_action('created_term', array($engine, 'on_term_created'), 10, 3);
            add_action('delete_term', array($engine, 'on_term_deleted'), 10, 4);
        }
        if ($this->get_setting('log_comments', true)) {
            add_action('comment_post', array($engine, 'on_comment_post'), 10, 2);
            add_action('transition_comment_status', array($engine, 'on_comment_status'), 10, 3);
            add_action('delete_comment', array($engine, 'on_comment_deleted'));
        }
        if ($this->get_setting('log_plugins', true)) {
            add_action('activated_plugin', array($engine, 'on_plugin_activated'));
            add_action('deactivated_plugin', array($engine, 'on_plugin_deactivated'));
            add_action('deleted_plugin', array($engine, 'on_plugin_deleted'), 10, 2);
            add_action('switch_theme', array($engine, 'on_theme_switch'));
            add_action('deleted_theme', array($engine, 'on_theme_deleted'), 10, 2);
            add_action('upgrader_process_complete', array($engine, 'on_upgrade'), 10, 2);
        }
        if ($this->get_setting('log_settings', true)) {
            add_action('updated_option', array($engine, 'on_option_updated'), 10, 3);
            add_action('wp_update_nav_menu', array($engine, 'on_menu_updated'));
            add_action('export_wp', array($engine, 'on_export'));
        }

        // Always log our own feature toggles when the log is enabled.
        add_action('wp_arzo_feature_toggled', array($engine, 'on_feature_toggle'), 10, 2);
    }
}
