<?php

/**
 * Feature: Two-Factor Authentication (free, opt-in).
 *
 * Adds TOTP (authenticator-app) two-factor login plus single-use recovery codes.
 * It is **strictly per-user opt-in** (enrol from your profile) and the feature
 * toggle is off by default. Because it changes the login flow there are TWO
 * escape hatches against lockout:
 *   1. **Recovery codes** shown at enrolment.
 *   2. Define `WP_ARZO_2FA_DISABLE` as true in wp-config.php (or use the WP Arzo
 *      emergency tool to clear the user's 2FA meta) to bypass the challenge.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Two_Factor extends WP_Arzo_Feature
{
    const M_ENABLED  = 'wp_arzo_2fa_enabled';
    const M_SECRET   = 'wp_arzo_2fa_secret';   // base32 TOTP secret
    const M_PENDING  = 'wp_arzo_2fa_pending';  // secret awaiting first verification
    const M_RECOVERY = 'wp_arzo_2fa_recovery'; // array of hashed recovery codes
    const TRANSIENT  = 'wp_arzo_2fa_';         // login challenge token prefix

    public function id()
    {
        return 'two_factor';
    }
    public function title()
    {
        return 'Two-Factor Authentication';
    }
    public function description()
    {
        return 'Per-user TOTP (authenticator app) 2FA with recovery codes. Opt-in from your profile. Changes the login flow — test on staging first.';
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
        $roles = array();
        if (function_exists('wp_roles')) {
            foreach (wp_roles()->get_names() as $slug => $name) {
                $roles[$slug] = function_exists('translate_user_role') ? translate_user_role($name) : $name;
            }
        }
        return array(
            array(
                'key'     => 'enforce_roles',
                'type'    => 'multiselect',
                'label'   => 'Require 2FA for these roles',
                'default' => array(),
                'options' => $roles,
                'help'    => 'Users in the selected roles must set up two-factor from their profile before they can use the rest of wp-admin. Lockout recovery: define WP_ARZO_2FA_DISABLE in wp-config.php, or clear the user\'s 2FA with the WP Arzo emergency tool.',
            ),
        );
    }

    public function boot()
    {
        add_action('show_user_profile', array($this, 'profile_section'));
        add_action('edit_user_profile', array($this, 'profile_section'));
        add_action('personal_options_update', array($this, 'profile_save'));
        add_action('edit_user_profile_update', array($this, 'profile_save'));

        // Login challenge (the proven "log out, re-challenge, re-set cookie" pattern).
        add_action('wp_login', array($this, 'after_login'), 10, 2);
        add_action('login_form_wp_arzo_2fa', array($this, 'validate_challenge'));

        // Policy: require enrolled 2FA for selected roles before using wp-admin.
        add_action('admin_init', array($this, 'enforce_policy'));
    }

    /**
     * If the current user's role requires 2FA and they haven't enrolled, hold them on
     * the profile screen (where they set it up) until they do. The profile + the WP Arzo
     * pages stay reachable so the policy can always be changed / 2FA set up.
     */
    public function enforce_policy()
    {
        if (self::bypassed() || wp_doing_ajax()) {
            return;
        }
        $roles = (array) $this->get_setting('enforce_roles', array());
        if (empty($roles)) {
            return;
        }
        $user = wp_get_current_user();
        if (!$user || !$user->ID || self::user_enabled($user->ID)) {
            return;
        }
        if (!array_intersect($roles, (array) $user->roles)) {
            return; // this user's role isn't enforced
        }
        global $pagenow;
        if (in_array($pagenow, array('profile.php', 'user-edit.php'), true)) {
            return; // allow the setup screen
        }
        if (isset($_GET['page']) && strpos((string) $_GET['page'], 'wp-arzo') === 0) {
            return; // allow WP Arzo pages (so an admin can change the policy)
        }
        wp_safe_redirect(add_query_arg('wp_arzo_2fa_required', '1', admin_url('profile.php')));
        exit;
    }

    private static function bypassed()
    {
        return defined('WP_ARZO_2FA_DISABLE') && WP_ARZO_2FA_DISABLE;
    }
    public static function user_enabled($user_id)
    {
        return (bool) get_user_meta((int) $user_id, self::M_ENABLED, true);
    }

    /* -------------------------------------------------------- TOTP crypto */

    public static function generate_secret($length = 16)
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[ord(random_bytes(1)) % 32];
        }
        return $secret;
    }

    public static function base32_decode($b32)
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
        $bits = '';
        for ($i = 0, $n = strlen($b32); $i < $n; $i++) {
            $bits .= str_pad(decbin(strpos($alphabet, $b32[$i])), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $out .= chr(bindec($chunk));
            }
        }
        return $out;
    }

    /** RFC 6238 TOTP code (SHA1, 6 digits, 30s period). */
    public static function totp($secret_b32, $time = null, $digits = 6, $period = 30)
    {
        $time = ($time === null) ? time() : $time;
        $counter = (int) floor($time / $period);
        $key = self::base32_decode($secret_b32);
        $bin = "\0\0\0\0" . pack('N', $counter); // 64-bit big-endian counter
        $hash = hash_hmac('sha1', $bin, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0f;
        $part = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);
        $otp = $part % (10 ** $digits);
        return str_pad((string) $otp, $digits, '0', STR_PAD_LEFT);
    }

    /** Verify a TOTP code within +/- $window periods (clock drift tolerance). */
    public static function verify_totp($secret_b32, $code, $window = 1, $time = null)
    {
        $code = preg_replace('/\D/', '', (string) $code);
        if (strlen($code) !== 6) {
            return false;
        }
        $time = ($time === null) ? time() : $time;
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::totp($secret_b32, $time + ($i * 30)), $code)) {
                return true;
            }
        }
        return false;
    }

    public static function otpauth_uri($secret_b32, $label, $issuer = 'WP Arzo')
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $label)
            . '?secret=' . $secret_b32 . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }

    /* ----------------------------------------------------- recovery codes */

    public static function generate_recovery_codes($count = 10)
    {
        $codes = array();
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtolower(bin2hex(random_bytes(5))); // 10 hex chars
        }
        return $codes;
    }

    private static function store_recovery_codes($user_id, array $plain)
    {
        $hashed = array();
        foreach ($plain as $c) {
            $hashed[] = wp_hash_password($c);
        }
        update_user_meta($user_id, self::M_RECOVERY, $hashed);
    }

    /** Verify + consume a recovery code. Returns true and removes it on success. */
    public static function consume_recovery($user_id, $code)
    {
        $code = strtolower(preg_replace('/[^a-f0-9]/', '', (string) $code));
        $hashed = get_user_meta($user_id, self::M_RECOVERY, true);
        if (!is_array($hashed)) {
            return false;
        }
        foreach ($hashed as $i => $h) {
            if (wp_check_password($code, $h)) {
                unset($hashed[$i]);
                update_user_meta($user_id, self::M_RECOVERY, array_values($hashed));
                return true;
            }
        }
        return false;
    }

    /** TOTP or recovery code. */
    public static function verify_for_user($user_id, $code)
    {
        $secret = get_user_meta($user_id, self::M_SECRET, true);
        if ($secret && self::verify_totp($secret, $code)) {
            return true;
        }
        return self::consume_recovery($user_id, $code);
    }

    /* --------------------------------------------------------- QR helpers */

    /** Lazily load the bundled offline QR library. */
    private static function ensure_qr()
    {
        if (!class_exists('WP_Arzo_QR') && defined('WP_ARZO_PLUGIN_DIR')) {
            $file = WP_ARZO_PLUGIN_DIR . 'includes/lib/class-wp-arzo-qr.php';
            if (is_readable($file)) {
                require_once $file;
            }
        }
        return class_exists('WP_Arzo_QR');
    }

    /** QR markup for an otpauth URI: a PNG <img> (GD) or an HTML-table fallback, or ''. */
    public static function qr_markup($otpauth_uri, $px = 180)
    {
        if (!self::ensure_qr()) {
            return '';
        }
        $data = WP_Arzo_QR::data_uri($otpauth_uri, 5, 4);
        if ($data !== '') {
            return '<img src="' . esc_attr($data) . '" width="' . (int) $px . '" height="' . (int) $px . '" alt="Two-factor QR code" style="display:block;background:#fff;padding:8px;border-radius:8px;image-rendering:pixelated;">';
        }
        // GD unavailable — render the QR as an HTML table on a white tile.
        return '<div style="display:inline-block;background:#fff;padding:8px;border-radius:8px;line-height:0;">' . WP_Arzo_QR::html_table($otpauth_uri, '4px') . '</div>';
    }

    /* ------------------------------------------------------------ profile */

    public function profile_section($user)
    {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }
        $enabled = self::user_enabled($user->ID);
        $pending = get_user_meta($user->ID, self::M_PENDING, true);
        wp_nonce_field('wp_arzo_2fa_profile', 'wp_arzo_2fa_nonce');
        echo '<h2>' . esc_html('Two-Factor Authentication (WP Arzo)') . '</h2>';

        if (!empty($_GET['wp_arzo_2fa_required']) && !$enabled) {
            echo '<div class="notice notice-error" style="padding:12px;"><p><strong>Your account requires two-factor authentication.</strong> Set it up below to continue using the dashboard.</p></div>';
        }

        // Show freshly generated recovery codes exactly once.
        $fresh = get_transient('wp_arzo_2fa_codes_' . $user->ID);
        if (is_array($fresh) && !empty($fresh)) {
            delete_transient('wp_arzo_2fa_codes_' . $user->ID);
            echo '<div class="notice notice-warning" style="padding:12px;"><p><strong>Save your recovery codes now — they are shown only once.</strong> Each works a single time if you lose your authenticator:</p>';
            echo '<p style="font-family:monospace;line-height:1.9;">' . esc_html(implode('   ', $fresh)) . '</p></div>';
        }
        echo '<table class="form-table" role="presentation"><tr><th>2FA</th><td>';
        if ($enabled) {
            $remaining = (array) get_user_meta($user->ID, self::M_RECOVERY, true);
            echo '<p><strong style="color:#1a7f37;">Enabled.</strong> ' . count(array_filter($remaining)) . ' recovery code(s) remaining.</p>';
            echo '<label><input type="checkbox" name="wp_arzo_2fa_disable" value="1"> Turn off two-factor for this account</label><br>';
            echo '<label style="display:inline-block;margin-top:8px;"><input type="checkbox" name="wp_arzo_2fa_regen" value="1"> Regenerate recovery codes</label>';
        } else {
            if (!$pending) {
                $pending = self::generate_secret();
                update_user_meta($user->ID, self::M_PENDING, $pending);
            }
            $uri = self::otpauth_uri($pending, $user->user_login);
            echo '<p>Scan this QR code in an authenticator app (Google Authenticator, Authy, 1Password…), then enter a 6-digit code to turn it on.</p>';
            $qr = self::qr_markup($uri, 180);
            if ($qr) {
                echo '<p style="margin:10px 0;">' . $qr . '</p>';
            }
            echo '<p>Can&rsquo;t scan? Enter this key manually: <code style="user-select:all;font-size:14px;">' . esc_html($pending) . '</code></p>';
            echo '<input type="text" name="wp_arzo_2fa_activate_code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" class="regular-text" style="max-width:160px;"> ';
            echo '<span class="description">Enter a code, then Update Profile to activate.</span>';
        }
        echo '</td></tr></table>';
    }

    public function profile_save($user_id)
    {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        if (!isset($_POST['wp_arzo_2fa_nonce']) || !wp_verify_nonce(wp_unslash($_POST['wp_arzo_2fa_nonce']), 'wp_arzo_2fa_profile')) {
            return;
        }

        // Activate: verify a code against the pending secret.
        if (!self::user_enabled($user_id) && !empty($_POST['wp_arzo_2fa_activate_code'])) {
            $pending = get_user_meta($user_id, self::M_PENDING, true);
            $code = sanitize_text_field(wp_unslash($_POST['wp_arzo_2fa_activate_code']));
            if ($pending && self::verify_totp($pending, $code)) {
                update_user_meta($user_id, self::M_SECRET, $pending);
                update_user_meta($user_id, self::M_ENABLED, 1);
                delete_user_meta($user_id, self::M_PENDING);
                $codes = self::generate_recovery_codes();
                self::store_recovery_codes($user_id, $codes);
                set_transient('wp_arzo_2fa_codes_' . $user_id, $codes, 300); // show once on next load
            }
            return;
        }

        if (self::user_enabled($user_id)) {
            if (!empty($_POST['wp_arzo_2fa_disable'])) {
                delete_user_meta($user_id, self::M_ENABLED);
                delete_user_meta($user_id, self::M_SECRET);
                delete_user_meta($user_id, self::M_RECOVERY);
            } elseif (!empty($_POST['wp_arzo_2fa_regen'])) {
                $codes = self::generate_recovery_codes();
                self::store_recovery_codes($user_id, $codes);
                set_transient('wp_arzo_2fa_codes_' . $user_id, $codes, 300);
            }
        }
    }

    /* ------------------------------------------------------ login challenge */

    public function after_login($user_login, $user)
    {
        if (!($user instanceof WP_User) || self::bypassed() || !self::user_enabled($user->ID)) {
            return;
        }
        // Undo the auth cookie WP just set and present the 2FA challenge instead.
        wp_clear_auth_cookie();
        $token = wp_generate_password(32, false);
        set_transient(self::TRANSIENT . $token, $user->ID, 5 * MINUTE_IN_SECONDS);
        $redirect = isset($_REQUEST['redirect_to']) ? wp_unslash($_REQUEST['redirect_to']) : admin_url();
        $remember = !empty($_REQUEST['rememberme']);
        $this->render_challenge($token, $redirect, $remember);
        exit;
    }

    public function validate_challenge()
    {
        $token = isset($_REQUEST['wp_arzo_2fa_token']) ? sanitize_text_field(wp_unslash($_REQUEST['wp_arzo_2fa_token'])) : '';
        $user_id = $token ? (int) get_transient(self::TRANSIENT . $token) : 0;
        $redirect = isset($_REQUEST['redirect_to']) ? wp_unslash($_REQUEST['redirect_to']) : admin_url();
        $remember = !empty($_REQUEST['rememberme']);

        if ($user_id && isset($_POST['wp_arzo_2fa_code'])) {
            $code = sanitize_text_field(wp_unslash($_POST['wp_arzo_2fa_code']));
            if (self::verify_for_user($user_id, $code)) {
                delete_transient(self::TRANSIENT . $token);
                wp_set_auth_cookie($user_id, $remember);
                wp_safe_redirect($redirect ? $redirect : admin_url());
                exit;
            }
            $this->render_challenge($token, $redirect, $remember, 'Invalid code. Try again, or use a recovery code.');
            exit;
        }
        if (!$user_id) {
            wp_safe_redirect(wp_login_url());
            exit;
        }
        $this->render_challenge($token, $redirect, $remember);
        exit;
    }

    private function render_challenge($token, $redirect, $remember, $error = '')
    {
        $action = esc_url(site_url('wp-login.php?action=wp_arzo_2fa', 'login_post'));
        login_header('Two-Factor Authentication', '', $error ? new WP_Error('wp_arzo_2fa', $error) : null);
        ?>
        <form name="wp_arzo_2fa" id="loginform" action="<?php echo $action; ?>" method="post">
            <p><label for="wp_arzo_2fa_code">Authentication code<br>
                <input type="text" name="wp_arzo_2fa_code" id="wp_arzo_2fa_code" class="input" inputmode="numeric" autocomplete="one-time-code" autofocus></label></p>
            <input type="hidden" name="wp_arzo_2fa_token" value="<?php echo esc_attr($token); ?>">
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect); ?>">
            <input type="hidden" name="rememberme" value="<?php echo $remember ? 'forever' : ''; ?>">
            <p class="submit"><input type="submit" class="button button-primary button-large" value="Log in"></p>
            <p class="description">Enter the 6-digit code from your authenticator app, or one of your recovery codes.</p>
        </form>
        <?php
        login_footer();
    }
}
