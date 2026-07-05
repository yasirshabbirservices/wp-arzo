<?php

/**
 * Feature: REST API Authentication.
 *
 * Lets external apps authenticate to the WP REST API with issuable / revocable
 * **API keys** (sent as a Bearer token, an X-API-Key header, or the password of
 * an HTTP Basic credential). This is the COMPLEMENT of the "Restrict REST API"
 * security feature (`disable_rest_api_guests`), which only *blocks* anonymous
 * access — this one lets trusted clients *in* as a chosen WordPress user.
 *
 * Keys are stored hashed (only an 8-char lookup prefix is kept in clear), exactly
 * like WordPress Application Passwords — the full key is shown once at creation.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_REST_API_Auth extends WP_Arzo_Feature
{
    /** Option holding the list of issued keys. */
    const OPT = 'wp_arzo_rest_api_keys';

    /** Throttle last-used writes to at most once per this many seconds. */
    const TOUCH_INTERVAL = 300;

    public function id()
    {
        return 'rest_api_auth';
    }
    public function title()
    {
        return 'REST API Authentication';
    }
    public function description()
    {
        return 'Issue API keys — with optional read-only or MCP-only scope, auto-expiry and last-used tracking — so external apps (and AI agents) can authenticate to the REST API (Bearer / X-API-Key / Basic).';
    }
    public function group()
    {
        return 'security';
    }
    public function icon()
    {
        return 'key';
    }
    public function settings_schema()
    {
        return array(
            array('key' => 'enable_key_auth', 'type' => 'toggle', 'label' => 'Accept API keys via Bearer / X-API-Key header', 'default' => 1),
            array('key' => 'enable_basic_auth', 'type' => 'toggle', 'label' => 'Accept API keys via HTTP Basic (key as the password)', 'default' => 1),
            array('key' => 'require_https', 'type' => 'toggle', 'label' => 'Only accept keys over HTTPS (recommended)', 'default' => 1),
        );
    }

    public function boot()
    {
        // Authenticate API requests that present a valid key. Runs late so cookie
        // / nonce auth wins when present; we only act when nobody is logged in yet.
        add_filter('determine_current_user', array($this, 'authenticate'), 20);
    }

    /**
     * @param int|false $user_id Current resolution from earlier filters.
     * @return int|false
     */
    public function authenticate($user_id)
    {
        if (!empty($user_id)) {
            return $user_id; // already authenticated (cookie / app password / etc.)
        }

        $key = $this->presented_key();
        if ($key === '') {
            return $user_id;
        }

        // HTTPS gate (front-controller may sit behind a proxy — is_ssl() covers the
        // common X-Forwarded cases WordPress already understands).
        if ($this->get_setting('require_https', 1) && !is_ssl()) {
            return $user_id;
        }

        $entry = self::match($key);
        if (!$entry) {
            return $user_id;
        }

        $scope = isset($entry['scope']) ? $entry['scope'] : 'full';

        // MCP-scoped keys authenticate ONLY for the WP Arzo MCP endpoint (a key you can
        // safely hand to an AI agent). On every other route they resolve to nothing, so
        // they can't drive the general REST API. Within MCP, write actions stay gated by
        // the MCP "Allow write actions" toggle + per-call confirm — this scope just bounds
        // *where* the key works, not what MCP itself permits.
        if ($scope === 'mcp') {
            if (!self::is_mcp_request()) {
                return $user_id;
            }
            self::touch($entry['id']);
            return (int) $entry['user_id'];
        }

        // Read-only keys may only make safe (read) requests — a write with a read-only
        // key is treated as unauthenticated, so the REST API answers 401 as usual.
        if ($scope === 'read'
            && self::is_write_method(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET')) {
            return $user_id;
        }

        self::touch($entry['id']);
        return (int) $entry['user_id'];
    }

    /** Pure: is this HTTP method a state-changing (write) request? */
    public static function is_write_method($method)
    {
        $method = strtoupper(trim((string) $method));
        return !in_array($method, array('GET', 'HEAD', 'OPTIONS'), true);
    }

    /**
     * Pure: does this request target the WP Arzo MCP endpoint (wp-arzo/v1/mcp)?
     * Matches both pretty permalinks (/wp-json/wp-arzo/v1/mcp) and the plain
     * `?rest_route=/wp-arzo/v1/mcp` form. Used to confine `mcp`-scoped keys.
     */
    public static function is_mcp_request($uri = null, $rest_route = null)
    {
        if ($uri === null) {
            $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        }
        if ($rest_route === null) {
            $rest_route = isset($_GET['rest_route']) ? (string) $_GET['rest_route'] : '';
        }
        $needle = 'wp-arzo/v1/mcp';
        return strpos((string) $uri, $needle) !== false || strpos((string) $rest_route, $needle) !== false;
    }

    /** Read the presented key from the enabled transports, or '' if none. */
    private function presented_key()
    {
        // Bearer / X-API-Key
        if ($this->get_setting('enable_key_auth', 1)) {
            $auth = '';
            if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
                $auth = $_SERVER['HTTP_AUTHORIZATION'];
            } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            }
            if ($auth !== '' && stripos($auth, 'bearer ') === 0) {
                $candidate = trim(substr($auth, 7));
                if (self::looks_like_key($candidate)) {
                    return $candidate;
                }
            }
            if (!empty($_SERVER['HTTP_X_API_KEY'])) {
                $candidate = trim((string) $_SERVER['HTTP_X_API_KEY']);
                if (self::looks_like_key($candidate)) {
                    return $candidate;
                }
            }
        }

        // HTTP Basic — the key is sent as the password (username is ignored).
        if ($this->get_setting('enable_basic_auth', 1) && !empty($_SERVER['PHP_AUTH_PW'])) {
            $candidate = trim((string) $_SERVER['PHP_AUTH_PW']);
            if (self::looks_like_key($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /* ----------------------------------------------------------- key store */

    /** Cheap shape check before any DB work. Keys look like arzo_<8 prefix><32 secret>. */
    public static function looks_like_key($key)
    {
        return is_string($key) && strlen($key) === 45 && strpos($key, 'arzo_') === 0
            && ctype_xdigit(substr($key, 5));
    }

    /** @return array<int,array> Stored key entries (without the secret hash exposed by callers). */
    public static function all_keys()
    {
        $keys = get_option(self::OPT, array());
        return is_array($keys) ? array_values($keys) : array();
    }

    /**
     * Create a key for the given user. Returns the stored entry plus the one-time
     * plaintext key under 'plain' (never persisted in clear), or WP_Error.
     */
    public static function create_key($label, $user_id, $expires_days = 0, $scope = 'full')
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0 || !get_userdata($user_id)) {
            return new WP_Error('wp_arzo_bad_user', 'Choose a valid user for the key.');
        }
        $prefix = bin2hex(random_bytes(4));   // 8 hex chars, kept in clear for lookup
        $secret = bin2hex(random_bytes(16));  // 32 hex chars
        $plain  = 'arzo_' . $prefix . $secret;

        $expires_days = max(0, (int) $expires_days);
        $scope = in_array($scope, array('read', 'mcp'), true) ? $scope : 'full';
        $entry = array(
            'id'            => 'rk_' . substr(md5(uniqid('', true)), 0, 12),
            'label'         => sanitize_text_field($label) ?: 'API key',
            'prefix'        => $prefix,
            'hash'          => password_hash($plain, PASSWORD_DEFAULT),
            'user_id'       => $user_id,
            'created_gmt'   => gmdate('Y-m-d H:i:s'),
            'last_used_gmt' => '',
            // Optional auto-expiry — an expired key stops authenticating (never deleted).
            'expires_gmt'   => $expires_days > 0 ? gmdate('Y-m-d H:i:s', time() + $expires_days * DAY_IN_SECONDS) : '',
            // Access scope: 'full' (default), 'read' (safe GET/HEAD/OPTIONS only), or
            // 'mcp' (only authenticates for the WP Arzo MCP endpoint — safe for AI agents).
            'scope'         => $scope,
        );

        $keys = get_option(self::OPT, array());
        if (!is_array($keys)) {
            $keys = array();
        }
        $keys[$entry['id']] = $entry;
        update_option(self::OPT, $keys, false);

        $public = $entry;
        unset($public['hash']);
        $public['plain'] = $plain;
        return $public;
    }

    /** Permanently remove a key by id. */
    public static function revoke_key($id)
    {
        $keys = get_option(self::OPT, array());
        if (!is_array($keys) || !isset($keys[$id])) {
            return false;
        }
        unset($keys[$id]);
        update_option(self::OPT, $keys, false);
        return true;
    }

    /** Pure: has this key entry passed its expiry? Empty expiry = never expires. */
    public static function is_expired($entry, $now_ts)
    {
        if (empty($entry['expires_gmt'])) {
            return false;
        }
        $exp = strtotime($entry['expires_gmt'] . ' UTC');
        return $exp !== false && (int) $now_ts >= $exp;
    }

    /** Find the stored entry a presented plaintext key matches (prefix + hash), or null. */
    private static function match($key)
    {
        $prefix = substr($key, 5, 8);
        $keys = get_option(self::OPT, array());
        if (!is_array($keys)) {
            return null;
        }
        $now = time();
        foreach ($keys as $entry) {
            if (!isset($entry['prefix'], $entry['hash'], $entry['user_id'])) {
                continue;
            }
            if (!hash_equals($entry['prefix'], $prefix)) {
                continue;
            }
            if (self::is_expired($entry, $now)) {
                continue; // expired keys no longer authenticate
            }
            if (password_verify($key, $entry['hash']) && get_userdata((int) $entry['user_id'])) {
                return $entry;
            }
        }
        return null;
    }

    /** Stamp last-used, throttled to avoid a DB write on every single request. */
    private static function touch($id)
    {
        $keys = get_option(self::OPT, array());
        if (!is_array($keys) || !isset($keys[$id])) {
            return;
        }
        $last = !empty($keys[$id]['last_used_gmt']) ? strtotime($keys[$id]['last_used_gmt'] . ' UTC') : 0;
        if ((time() - $last) < self::TOUCH_INTERVAL) {
            return;
        }
        $keys[$id]['last_used_gmt'] = gmdate('Y-m-d H:i:s');
        update_option(self::OPT, $keys, false);
    }
}
