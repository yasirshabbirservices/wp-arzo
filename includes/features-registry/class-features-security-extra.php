<?php

/**
 * Free security features: Custom Login URL + Limit Login Attempts.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Move wp-login.php to a custom slug and 404 the default endpoint. Login links
 * (emails, logout, password reset, register) are rewritten to the new slug, so the
 * normal flows keep working while bots hitting /wp-login.php are turned away.
 *
 * Recovery: the new URL is shown in this feature's settings; you can always disable
 * the feature from the dashboard (you're already logged in there), or deactivate the
 * plugin, to restore /wp-login.php.
 */
class WP_Arzo_Feature_Custom_Login_URL extends WP_Arzo_Feature
{
    private $slug = '';

    public function id()
    {
        return 'custom_login_url';
    }
    public function title()
    {
        return 'Custom Login URL';
    }
    public function description()
    {
        return 'Move wp-login.php to a secret slug to cut down brute-force/bot login traffic.';
    }
    public function group()
    {
        return 'security';
    }
    public function icon()
    {
        return 'lock';
    }
    public function settings_schema()
    {
        // Read the saved value directly (NOT via get_setting(), which resolves
        // defaults through settings_schema() and would recurse).
        $saved   = WP_Arzo_Feature_Registry::instance()->get_settings($this->id());
        $current = isset($saved['slug']) ? sanitize_title((string) $saved['slug']) : '';
        $current = $current !== '' ? $current : 'login';
        return array(
            array(
                'key'     => 'slug',
                'type'    => 'text',
                'label'   => 'Login slug',
                'default' => 'login',
                'help'    => 'Your login page will be ' . home_url('/') . $current . ' — bookmark it. Avoid an existing page slug.',
            ),
        );
    }

    private function sanitized_slug()
    {
        $slug = sanitize_title((string) $this->get_setting('slug', 'login'));
        // Never allow values that would break core or be meaningless.
        if (in_array($slug, array('', 'wp-login', 'wp-login.php', 'wp-admin', 'admin'), true)) {
            return '';
        }
        return $slug;
    }

    /** The secret query key that unlocks wp-login.php (the slug itself). */
    private function key()
    {
        return $this->slug;
    }

    private function request_path()
    {
        $uri  = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $path = trim((string) parse_url($uri, PHP_URL_PATH), '/');
        $home = trim((string) parse_url(home_url(), PHP_URL_PATH), '/');
        if ($home !== '' && strpos($path, $home) === 0) {
            $path = trim(substr($path, strlen($home)), '/');
        }
        return $path;
    }

    public function boot()
    {
        $this->slug = $this->sanitized_slug();
        if ($this->slug === '') {
            return;
        }

        // Keep wp-login.php as the real login page (loaded natively — never require()d,
        // which would leave its internal $user_login/$error undefined), but gate it
        // behind a secret query key appended to every login link.
        add_filter('site_url', array($this, 'filter_site_url'), 10, 4);
        add_filter('network_site_url', array($this, 'filter_network_site_url'), 10, 3);
        add_filter('wp_redirect', array($this, 'filter_redirect'), 10, 2);
        add_filter('login_url', array($this, 'filter_plain_login_url'), 10, 3);

        add_action('init', array($this, 'handle_request'));
        add_action('login_init', array($this, 'guard'));
    }

    /** Pretty /slug → the real login page (wp-login.php?<key>). */
    public function handle_request()
    {
        if ($this->request_path() === $this->slug) {
            wp_safe_redirect(site_url('wp-login.php?' . rawurlencode($this->key())));
            exit;
        }
    }

    /** Runs when wp-login.php loads: block it unless the secret key is present. */
    public function guard()
    {
        if (isset($_REQUEST[$this->key()])) {
            return; // unlocked
        }
        // Programmatic flows that may arrive without the key.
        $action = isset($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : '';
        if (in_array($action, array('logout', 'postpass'), true)) {
            return;
        }
        wp_safe_redirect(is_user_logged_in() ? admin_url() : home_url('/'));
        exit;
    }

    /** Append the secret key to any wp-login.php URL so all login links carry it. */
    private function with_key($url)
    {
        if (strpos($url, 'wp-login.php') === false) {
            return $url;
        }
        return add_query_arg($this->key(), '', $url);
    }

    public function filter_site_url($url, $path, $scheme, $blog_id)
    {
        return $this->with_key($url);
    }
    public function filter_network_site_url($url, $path, $scheme)
    {
        return $this->with_key($url);
    }
    public function filter_redirect($location, $status)
    {
        return $this->with_key($location);
    }
    public function filter_plain_login_url($login_url, $redirect, $force_reauth)
    {
        return $this->with_key($login_url);
    }
}

/**
 * Lock out an IP after too many failed logins (transient-based, auto-expiring).
 */
class WP_Arzo_Feature_Limit_Login extends WP_Arzo_Feature
{
    public function id()
    {
        return 'limit_login';
    }
    public function title()
    {
        return 'Limit Login Attempts';
    }
    public function description()
    {
        return 'Temporarily lock out an IP address after repeated failed logins.';
    }
    public function group()
    {
        return 'security';
    }
    public function icon()
    {
        return 'shield';
    }
    public function settings_schema()
    {
        return array(
            array('key' => 'max_attempts', 'type' => 'number', 'label' => 'Max failed attempts', 'default' => 5),
            array('key' => 'lockout_minutes', 'type' => 'number', 'label' => 'Lockout (minutes)', 'default' => 15),
            array(
                'key'     => 'trusted_ips',
                'type'    => 'textarea',
                'label'   => 'Trusted IP allowlist',
                'default' => '',
                'help'    => 'One IP or CIDR range per line (e.g. 203.0.113.10 or 203.0.113.0/24). These addresses are never counted or locked out — add your own office/VPN IP so you can’t lock yourself out.',
            ),
            array(
                'key'     => 'alert_enabled',
                'type'    => 'toggle',
                'label'   => 'Email me when an IP is locked out',
                'default' => 0,
            ),
            array(
                'key'     => 'alert_email',
                'type'    => 'email',
                'label'   => 'Alert recipient',
                'default' => get_option('admin_email'),
                'help'    => 'Where lockout alerts are sent.',
                'show_if' => array('field' => 'alert_enabled', 'value' => 1),
            ),
        );
    }

    private function ip()
    {
        return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
    }

    /** Whether the current request IP is on the trusted allowlist. */
    private function is_trusted()
    {
        $ip = filter_var($this->ip(), FILTER_VALIDATE_IP);
        if (!$ip) {
            return false;
        }
        foreach (preg_split('/\r\n|\r|\n/', (string) $this->get_setting('trusted_ips', '')) as $line) {
            $entry = trim($line);
            if ($entry === '') {
                continue;
            }
            if (strpos($entry, '/') !== false) {
                if (self::ip_in_cidr($ip, $entry)) {
                    return true;
                }
            } elseif ($entry === $ip) {
                return true;
            }
        }
        return false;
    }

    /**
     * Pure: is an IPv4/IPv6 address inside a CIDR range? Returns false on malformed input.
     */
    public static function ip_in_cidr($ip, $cidr)
    {
        if (strpos($cidr, '/') === false) {
            return $ip === $cidr;
        }
        list($subnet, $bits) = explode('/', $cidr, 2);
        $bits = (int) $bits;
        $ip_bin     = @inet_pton($ip);
        $subnet_bin = @inet_pton(trim($subnet));
        if ($ip_bin === false || $subnet_bin === false || strlen($ip_bin) !== strlen($subnet_bin)) {
            return false; // address family mismatch or invalid
        }
        $max = strlen($ip_bin) * 8;
        if ($bits < 0 || $bits > $max) {
            return false;
        }
        $bytes = intdiv($bits, 8);
        $rem   = $bits % 8;
        if ($bytes > 0 && strncmp($ip_bin, $subnet_bin, $bytes) !== 0) {
            return false;
        }
        if ($rem === 0) {
            return true;
        }
        $mask = chr(0xff << (8 - $rem) & 0xff);
        return (ord($ip_bin[$bytes]) & ord($mask)) === (ord($subnet_bin[$bytes]) & ord($mask));
    }
    private function key($prefix = '')
    {
        return 'wp_arzo_ll_' . $prefix . md5($this->ip());
    }
    private function minutes()
    {
        return max(1, (int) $this->get_setting('lockout_minutes', 15));
    }

    public function boot()
    {
        add_filter('authenticate', array($this, 'check_lockout'), 30);
        add_action('wp_login_failed', array($this, 'record_failure'));
        add_action('wp_login', array($this, 'clear'), 10, 2);
    }

    public function check_lockout($user)
    {
        if ($this->is_trusted()) {
            return $user;
        }
        if (get_transient($this->key('lock_'))) {
            return new WP_Error(
                'wp_arzo_locked_out',
                __('Too many failed login attempts. Please try again later.', 'wp-arzo')
            );
        }
        return $user;
    }

    public function record_failure($username)
    {
        // Trusted addresses are never counted or locked.
        if ($this->is_trusted()) {
            return;
        }
        // Don't count attempts while already locked.
        if (get_transient($this->key('lock_'))) {
            return;
        }
        $max     = max(1, (int) $this->get_setting('max_attempts', 5));
        $minutes = $this->minutes();
        $count   = (int) get_transient($this->key()) + 1;

        if ($count >= $max) {
            set_transient($this->key('lock_'), 1, $minutes * MINUTE_IN_SECONDS);
            delete_transient($this->key());
            $this->maybe_alert($username, $minutes);
        } else {
            set_transient($this->key(), $count, $minutes * MINUTE_IN_SECONDS);
        }
    }

    /** Email the admin about a fresh lockout, if the alert is enabled. */
    private function maybe_alert($username, $minutes)
    {
        if (!(int) $this->get_setting('alert_enabled', 0)) {
            return;
        }
        $to = sanitize_email((string) $this->get_setting('alert_email', get_option('admin_email')));
        if (!is_email($to)) {
            return;
        }
        $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $ip   = $this->ip();
        $subject = sprintf('[%s] Login lockout: %s', $site, $ip);
        $lines = array(
            sprintf('An IP address has been locked out of %s after too many failed login attempts.', $site),
            '',
            'IP address: ' . $ip,
            'Attempted username: ' . ($username !== '' ? $username : '(unknown)'),
            'Locked for: ' . $minutes . ' minute(s)',
            'Time (UTC): ' . gmdate('Y-m-d H:i:s'),
            '',
            'If this was you, add your IP to the trusted allowlist in WP Arzo → Limit Login Attempts.',
        );
        wp_mail($to, $subject, implode("\n", $lines));
    }

    public function clear($user_login, $user)
    {
        delete_transient($this->key());
        delete_transient($this->key('lock_'));
    }
}
