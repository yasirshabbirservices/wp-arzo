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
        return 'Advanced SMTP';
    }
    public function description()
    {
        return 'Reliable email: send through SMTP with a backup connection, automatic failover/retry and failure alerts.';
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
        return array(
            array('key' => 'from_name', 'type' => 'text', 'label' => 'From name'),
            array('key' => 'from_email', 'type' => 'email', 'label' => 'From email'),
            array('key' => 'force_from', 'type' => 'toggle', 'label' => 'Force from name/email on all mail', 'default' => 1),

            array('key' => 'host', 'type' => 'text', 'label' => 'Primary SMTP host', 'help' => 'e.g. smtp.yourhost.com'),
            array('key' => 'port', 'type' => 'number', 'label' => 'Primary port', 'default' => 587),
            array('key' => 'encryption', 'type' => 'select', 'label' => 'Primary encryption', 'default' => 'tls', 'options' => $enc),
            array('key' => 'auth', 'type' => 'toggle', 'label' => 'Primary uses authentication', 'default' => 1),
            array('key' => 'username', 'type' => 'text', 'label' => 'Primary username'),
            array('key' => 'password', 'type' => 'password', 'label' => 'Primary password'),

            array('key' => 'backup_enabled', 'type' => 'toggle', 'label' => 'Enable backup connection (failover)', 'default' => 0, 'help' => 'If the primary connection fails, the email is retried through the backup.'),
            array('key' => 'backup_host', 'type' => 'text', 'label' => 'Backup SMTP host'),
            array('key' => 'backup_port', 'type' => 'number', 'label' => 'Backup port', 'default' => 587),
            array('key' => 'backup_encryption', 'type' => 'select', 'label' => 'Backup encryption', 'default' => 'tls', 'options' => $enc),
            array('key' => 'backup_auth', 'type' => 'toggle', 'label' => 'Backup uses authentication', 'default' => 1),
            array('key' => 'backup_username', 'type' => 'text', 'label' => 'Backup username'),
            array('key' => 'backup_password', 'type' => 'password', 'label' => 'Backup password'),

            array('key' => 'auto_retry', 'type' => 'toggle', 'label' => 'Auto-retry via backup on failure', 'default' => 1),
            array('key' => 'notify_enabled', 'type' => 'toggle', 'label' => 'Email me when a message fails', 'default' => 0),
            array('key' => 'notify_email', 'type' => 'email', 'label' => 'Failure notification address', 'help' => 'Defaults to the site admin email.'),

            array('key' => 'test_email', 'type' => 'test_email', 'label' => 'Send a test email'),
        );
    }

    public function boot()
    {
        add_action('phpmailer_init', array($this, 'configure'));
        add_action('wp_mail_failed', array($this, 'on_failed'));

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
