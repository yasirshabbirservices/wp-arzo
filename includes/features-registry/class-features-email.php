<?php

/**
 * Free email features: Advanced SMTP (primary + backup/failover, auto-retry,
 * failure notifications, test email) and Email Log (with resend + analytics).
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Route wp_mail() through SMTP with a backup connection and automatic failover.
 */
class WP_Arzo_Feature_SMTP extends WP_Arzo_Feature
{
    /** When true, configure() uses the backup connection. */
    private $use_backup = false;
    /** Re-entrancy guard while retrying / notifying. */
    private $busy = false;

    public function id()
    {
        return 'smtp';
    }
    public function title()
    {
        return 'Advanced SMTP & Email API';
    }
    public function description()
    {
        return 'Reliable email delivery — route all outgoing mail through an SMTP server (with a backup connection + failover) or a provider API (SendGrid, Brevo, Mailgun).';
    }
    public function group()
    {
        return 'email';
    }
    public function icon()
    {
        return 'mail';
    }
    public function settings_schema()
    {
        $enc = array('none' => 'None', 'ssl' => 'SSL', 'tls' => 'TLS');
        // Reveal only the fields relevant to the chosen delivery method + toggle (core
        // `show_if` support, single condition or an AND-list).
        $if_smtp        = array('field' => 'method', 'value' => 'smtp');
        $if_api         = array('field' => 'method', 'value' => array('sendgrid', 'brevo', 'mailgun'));
        $if_mailgun     = array('field' => 'method', 'value' => 'mailgun');
        $if_auth        = array('field' => 'auth', 'value' => '1');
        $if_backup      = array('field' => 'backup_enabled', 'value' => '1');
        $if_backup_auth = array('field' => 'backup_auth', 'value' => '1');
        $if_notify      = array('field' => 'notify_enabled', 'value' => '1');
        return array(
            array('key' => 'method', 'type' => 'select', 'label' => 'Delivery method', 'default' => 'smtp', 'options' => array(
                'smtp'     => 'SMTP server',
                'sendgrid' => 'SendGrid (API)',
                'brevo'    => 'Brevo (API)',
                'mailgun'  => 'Mailgun (API)',
            ), 'help' => 'Send WordPress email through an SMTP server or straight to a provider API.'),

            array('key' => 'from_name', 'type' => 'text', 'label' => 'From name'),
            array('key' => 'from_email', 'type' => 'email', 'label' => 'From email'),
            array('key' => 'force_from', 'type' => 'toggle', 'label' => 'Force from name/email on all mail', 'default' => 1),

            // Provider API (shown only for an API method)
            array('key' => 'api_key', 'type' => 'password', 'label' => 'API key', 'show_if' => $if_api),
            array('key' => 'mailgun_domain', 'type' => 'text', 'label' => 'Mailgun sending domain', 'help' => 'e.g. mg.yourdomain.com', 'show_if' => $if_mailgun),
            array('key' => 'mailgun_region', 'type' => 'select', 'label' => 'Mailgun region', 'default' => 'us', 'options' => array('us' => 'US', 'eu' => 'EU'), 'show_if' => $if_mailgun),

            // SMTP (shown only for the SMTP method)
            array('key' => 'host', 'type' => 'text', 'label' => 'Primary SMTP host', 'help' => 'e.g. smtp.yourhost.com', 'show_if' => $if_smtp),
            array('key' => 'port', 'type' => 'number', 'label' => 'Primary port', 'default' => 587, 'show_if' => $if_smtp),
            array('key' => 'encryption', 'type' => 'select', 'label' => 'Primary encryption', 'default' => 'tls', 'options' => $enc, 'show_if' => $if_smtp),
            array('key' => 'auth', 'type' => 'toggle', 'label' => 'Primary uses authentication', 'default' => 1, 'show_if' => $if_smtp),
            array('key' => 'username', 'type' => 'text', 'label' => 'Primary username', 'show_if' => array($if_smtp, $if_auth)),
            array('key' => 'password', 'type' => 'password', 'label' => 'Primary password', 'show_if' => array($if_smtp, $if_auth)),

            array('key' => 'backup_enabled', 'type' => 'toggle', 'label' => 'Enable backup connection (failover)', 'default' => 0, 'help' => 'If the primary connection fails, the email is retried through the backup.', 'show_if' => $if_smtp),
            array('key' => 'backup_host', 'type' => 'text', 'label' => 'Backup SMTP host', 'show_if' => array($if_smtp, $if_backup)),
            array('key' => 'backup_port', 'type' => 'number', 'label' => 'Backup port', 'default' => 587, 'show_if' => array($if_smtp, $if_backup)),
            array('key' => 'backup_encryption', 'type' => 'select', 'label' => 'Backup encryption', 'default' => 'tls', 'options' => $enc, 'show_if' => array($if_smtp, $if_backup)),
            array('key' => 'backup_auth', 'type' => 'toggle', 'label' => 'Backup uses authentication', 'default' => 1, 'show_if' => array($if_smtp, $if_backup)),
            array('key' => 'backup_username', 'type' => 'text', 'label' => 'Backup username', 'show_if' => array($if_smtp, $if_backup, $if_backup_auth)),
            array('key' => 'backup_password', 'type' => 'password', 'label' => 'Backup password', 'show_if' => array($if_smtp, $if_backup, $if_backup_auth)),
            array('key' => 'auto_retry', 'type' => 'toggle', 'label' => 'Auto-retry via backup on failure', 'default' => 1, 'show_if' => array($if_smtp, $if_backup)),

            array('key' => 'notify_enabled', 'type' => 'toggle', 'label' => 'Email me when a message fails', 'default' => 0, 'show_if' => $if_smtp),
            array('key' => 'notify_email', 'type' => 'email', 'label' => 'Failure notification address', 'help' => 'Defaults to the site admin email.', 'show_if' => array($if_smtp, $if_notify)),

            array('key' => 'test_email', 'type' => 'test_email', 'label' => 'Send a test email'),
        );
    }

    private function is_api_method()
    {
        return in_array($this->get_setting('method', 'smtp'), array('sendgrid', 'brevo', 'mailgun'), true);
    }

    public function boot()
    {
        if ($this->is_api_method()) {
            // Send straight to the provider API, short-circuiting wp_mail.
            add_filter('pre_wp_mail', array($this, 'send_api'), 10, 2);
        } else {
            add_action('phpmailer_init', array($this, 'configure'));
            add_action('wp_mail_failed', array($this, 'on_failed'));
        }

        if ($this->get_setting('force_from', 1)) {
            add_filter('wp_mail_from', function ($email) {
                $from = sanitize_email((string) $this->get_setting('from_email', ''));
                return $from !== '' ? $from : $email;
            }, 99);
            add_filter('wp_mail_from_name', function ($name) {
                $from = (string) $this->get_setting('from_name', '');
                return $from !== '' ? $from : $name;
            }, 99);
        }
    }

    /* ------------------------------------------------- Provider API sending */

    /** pre_wp_mail short-circuit: deliver via the selected provider API. */
    public function send_api($short, $atts)
    {
        $provider = $this->get_setting('method', 'smtp');
        $key = trim((string) $this->get_setting('api_key', ''));
        if (!in_array($provider, array('sendgrid', 'brevo', 'mailgun'), true) || $key === '') {
            return $short; // not configured — fall through to the default transport
        }

        $to = isset($atts['to']) ? $atts['to'] : array();
        if (!is_array($to)) {
            $to = explode(',', $to);
        }
        $to = array_filter(array_map('trim', $to));
        if (empty($to)) {
            return $short;
        }
        if (!empty($atts['attachments'])) {
            return $short; // let the default transport handle attachments
        }

        $subject = isset($atts['subject']) ? (string) $atts['subject'] : '';
        $message = isset($atts['message']) ? (string) $atts['message'] : '';
        $parsed  = $this->parse_headers(isset($atts['headers']) ? $atts['headers'] : '');

        $from_email = trim((string) $this->get_setting('from_email', '')) ?: $parsed['from_email'];
        if ($from_email === '') {
            $from_email = apply_filters('wp_mail_from', get_option('admin_email'));
        }
        $from_name = trim((string) $this->get_setting('from_name', '')) ?: $parsed['from_name'];
        if ($from_name === '') {
            $from_name = apply_filters('wp_mail_from_name', get_bloginfo('name'));
        }
        $html = ($parsed['content_type'] === 'text/html');

        switch ($provider) {
            case 'sendgrid':
                return $this->via_sendgrid($key, $to, $subject, $message, $html, $from_email, $from_name);
            case 'brevo':
                return $this->via_brevo($key, $to, $subject, $message, $html, $from_email, $from_name);
            case 'mailgun':
                return $this->via_mailgun($key, $to, $subject, $message, $html, $from_email, $from_name);
        }
        return $short;
    }

    private function parse_headers($headers)
    {
        $out = array('content_type' => 'text/plain', 'from_email' => '', 'from_name' => '');
        if (!is_array($headers)) {
            $headers = explode("\n", str_replace("\r\n", "\n", (string) $headers));
        }
        foreach ($headers as $h) {
            $h = trim($h);
            if ($h === '' || strpos($h, ':') === false) {
                continue;
            }
            list($k, $v) = explode(':', $h, 2);
            $k = strtolower(trim($k));
            $v = trim($v);
            if ($k === 'content-type' && stripos($v, 'text/html') !== false) {
                $out['content_type'] = 'text/html';
            } elseif ($k === 'from') {
                if (preg_match('/(.*)<(.+)>/', $v, $m)) {
                    $out['from_name']  = trim($m[1], ' "');
                    $out['from_email'] = trim($m[2]);
                } else {
                    $out['from_email'] = $v;
                }
            }
        }
        return $out;
    }

    private function api_ok($resp)
    {
        if (is_wp_error($resp)) {
            return false;
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        return $code >= 200 && $code < 300;
    }

    private function via_sendgrid($key, $to, $subject, $message, $html, $from_email, $from_name)
    {
        $body = array(
            'personalizations' => array(array('to' => array_map(function ($e) {
                return array('email' => $e);
            }, $to))),
            'from'    => array('email' => $from_email, 'name' => $from_name),
            'subject' => $subject,
            'content' => array(array('type' => $html ? 'text/html' : 'text/plain', 'value' => $message)),
        );
        return $this->api_ok(wp_remote_post('https://api.sendgrid.com/v3/mail/send', array(
            'timeout' => 15,
            'headers' => array('Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'),
            'body'    => wp_json_encode($body),
        )));
    }

    private function via_brevo($key, $to, $subject, $message, $html, $from_email, $from_name)
    {
        $body = array(
            'sender'  => array('email' => $from_email, 'name' => $from_name),
            'to'      => array_map(function ($e) {
                return array('email' => $e);
            }, $to),
            'subject' => $subject,
        );
        $body[$html ? 'htmlContent' : 'textContent'] = $message;
        return $this->api_ok(wp_remote_post('https://api.brevo.com/v3/smtp/email', array(
            'timeout' => 15,
            'headers' => array('api-key' => $key, 'Content-Type' => 'application/json', 'accept' => 'application/json'),
            'body'    => wp_json_encode($body),
        )));
    }

    private function via_mailgun($key, $to, $subject, $message, $html, $from_email, $from_name)
    {
        $domain = trim((string) $this->get_setting('mailgun_domain', ''));
        if ($domain === '') {
            return false;
        }
        $base = ($this->get_setting('mailgun_region', 'us') === 'eu') ? 'https://api.eu.mailgun.net' : 'https://api.mailgun.net';
        $body = array(
            'from'    => $from_name ? sprintf('%s <%s>', $from_name, $from_email) : $from_email,
            'to'      => implode(',', $to),
            'subject' => $subject,
        );
        $body[$html ? 'html' : 'text'] = $message;
        return $this->api_ok(wp_remote_post($base . '/v3/' . rawurlencode($domain) . '/messages', array(
            'timeout' => 15,
            'headers' => array('Authorization' => 'Basic ' . base64_encode('api:' . $key)),
            'body'    => $body,
        )));
    }

    public function configure($phpmailer)
    {
        $p    = $this->use_backup ? 'backup_' : '';
        $host = trim((string) $this->get_setting($p . 'host', ''));
        if ($host === '') {
            return; // not configured — leave WP's default transport
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = $host;
        $phpmailer->Port = (int) $this->get_setting($p . 'port', 587);

        $enc = $this->get_setting($p . 'encryption', 'tls');
        if ($enc === 'ssl' || $enc === 'tls') {
            $phpmailer->SMTPSecure = $enc;
        } else {
            $phpmailer->SMTPSecure = '';
            $phpmailer->SMTPAutoTLS = false;
        }

        if ($this->get_setting($p . 'auth', 1)) {
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = (string) $this->get_setting($p . 'username', '');
            $phpmailer->Password = (string) $this->get_setting($p . 'password', '');
        } else {
            $phpmailer->SMTPAuth = false;
        }
    }

    public function on_failed($error)
    {
        if ($this->busy || !is_wp_error($error)) {
            return;
        }
        $data = $error->get_error_data();
        if (!is_array($data) || empty($data['to'])) {
            return;
        }

        $retried = false;
        $delivered = false;

        if ($this->get_setting('auto_retry', 1)
            && $this->get_setting('backup_enabled', 0)
            && trim((string) $this->get_setting('backup_host', '')) !== ''
        ) {
            $retried = true;
            $this->busy = true;
            $this->use_backup = true;
            $delivered = wp_mail(
                $data['to'],
                isset($data['subject']) ? $data['subject'] : '',
                isset($data['message']) ? $data['message'] : '',
                isset($data['headers']) ? $data['headers'] : '',
                isset($data['attachments']) ? $data['attachments'] : array()
            );
            $this->use_backup = false;
            $this->busy = false;
        }

        if (!$delivered) {
            $this->maybe_notify($data, $error, $retried);
        }
    }

    private function maybe_notify($data, $error, $retried)
    {
        if (!$this->get_setting('notify_enabled', 0)) {
            return;
        }
        $to = sanitize_email((string) $this->get_setting('notify_email', ''));
        if ($to === '') {
            $to = get_option('admin_email');
        }
        $recipient = is_array($data['to']) ? implode(', ', $data['to']) : $data['to'];
        $body = "An outgoing email failed to send" . ($retried ? " (the backup connection was also tried)" : "") . ".\n\n"
            . 'Site: ' . home_url('/') . "\n"
            . 'To: ' . $recipient . "\n"
            . 'Subject: ' . (isset($data['subject']) ? $data['subject'] : '') . "\n"
            . 'Error: ' . $error->get_error_message() . "\n";

        // Guard so a failing notification can't recurse.
        $this->busy = true;
        wp_mail($to, '[Email failure] ' . get_bloginfo('name'), $body);
        $this->busy = false;
    }
}

/**
 * Record outgoing email (with body/headers so failed messages can be resent).
 */
class WP_Arzo_Feature_Email_Log extends WP_Arzo_Feature
{
    const OPTION = 'wp_arzo_email_log';
    const CAP = 150;

    public function id()
    {
        return 'email_log';
    }
    public function title()
    {
        return 'Email Log';
    }
    public function description()
    {
        return 'Log outgoing emails (recipient, subject, status) with resend + analytics — under WP Arzo → Email Log.';
    }
    public function group()
    {
        return 'email';
    }
    public function icon()
    {
        return 'mail';
    }

    public function boot()
    {
        add_filter('wp_mail', array($this, 'log_mail'));
        add_action('wp_mail_failed', array($this, 'log_failed'));
    }

    public function log_mail($args)
    {
        $to = isset($args['to']) ? $args['to'] : '';
        if (is_array($to)) {
            $to = implode(', ', $to);
        }
        self::push(array(
            'id'      => 'eml_' . substr(md5(uniqid('', true)), 0, 12),
            'time'    => time(),
            'to'      => sanitize_text_field((string) $to),
            'subject' => sanitize_text_field(isset($args['subject']) ? (string) $args['subject'] : ''),
            'message' => isset($args['message']) ? (string) $args['message'] : '',
            'headers' => isset($args['headers']) ? $args['headers'] : '',
            'status'  => 'sent',
            'error'   => '',
        ));
        return $args;
    }

    public function log_failed($wp_error)
    {
        $log = get_option(self::OPTION, array());
        if (is_array($log) && !empty($log)) {
            $log[0]['status'] = 'failed';
            $log[0]['error']  = is_wp_error($wp_error) ? $wp_error->get_error_message() : 'Unknown error';
            update_option(self::OPTION, $log, false);
        }
    }

    private static function push($entry)
    {
        $log = get_option(self::OPTION, array());
        if (!is_array($log)) {
            $log = array();
        }
        array_unshift($log, $entry);
        if (count($log) > self::CAP) {
            $log = array_slice($log, 0, self::CAP);
        }
        update_option(self::OPTION, $log, false);
    }
}
