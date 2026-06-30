<?php

/**
 * Free email features: SMTP delivery + Email Log.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Route wp_mail() through a configured SMTP server (fixes deliverability).
 */
class WP_Arzo_Feature_SMTP extends WP_Arzo_Feature
{
    public function id()
    {
        return 'smtp';
    }
    public function title()
    {
        return 'SMTP Email Delivery';
    }
    public function description()
    {
        return 'Send WordPress email through your SMTP server for reliable delivery.';
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
        return array(
            array('key' => 'from_name', 'type' => 'text', 'label' => 'From name'),
            array('key' => 'from_email', 'type' => 'email', 'label' => 'From email'),
            array('key' => 'force_from', 'type' => 'toggle', 'label' => 'Force from name/email on all mail', 'default' => 1),
            array('key' => 'host', 'type' => 'text', 'label' => 'SMTP host', 'help' => 'e.g. smtp.yourhost.com'),
            array('key' => 'port', 'type' => 'number', 'label' => 'SMTP port', 'default' => 587),
            array('key' => 'encryption', 'type' => 'select', 'label' => 'Encryption', 'default' => 'tls', 'options' => array('none' => 'None', 'ssl' => 'SSL', 'tls' => 'TLS')),
            array('key' => 'auth', 'type' => 'toggle', 'label' => 'Use authentication', 'default' => 1),
            array('key' => 'username', 'type' => 'text', 'label' => 'SMTP username'),
            array('key' => 'password', 'type' => 'password', 'label' => 'SMTP password'),
        );
    }

    public function boot()
    {
        add_action('phpmailer_init', array($this, 'configure'));

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
        $host = trim((string) $this->get_setting('host', ''));
        if ($host === '') {
            return; // not configured yet — leave WP's default transport
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = $host;
        $phpmailer->Port = (int) $this->get_setting('port', 587);

        $enc = $this->get_setting('encryption', 'tls');
        if ($enc === 'ssl' || $enc === 'tls') {
            $phpmailer->SMTPSecure = $enc;
        } else {
            $phpmailer->SMTPSecure = '';
            $phpmailer->SMTPAutoTLS = false;
        }

        if ($this->get_setting('auth', 1)) {
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = (string) $this->get_setting('username', '');
            $phpmailer->Password = (string) $this->get_setting('password', '');
        } else {
            $phpmailer->SMTPAuth = false;
        }
    }
}

/**
 * Record every outgoing email (to / subject / status) for troubleshooting.
 * Stored newest-first in an option, capped to the most recent entries.
 */
class WP_Arzo_Feature_Email_Log extends WP_Arzo_Feature
{
    const OPTION = 'wp_arzo_email_log';
    const CAP = 200;

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
        return 'Log outgoing emails (recipient, subject, status) — view them under WP Arzo → Email Log.';
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
            'time'    => time(),
            'to'      => sanitize_text_field((string) $to),
            'subject' => sanitize_text_field(isset($args['subject']) ? (string) $args['subject'] : ''),
            'status'  => 'sent',
            'error'   => '',
        ));
        return $args;
    }

    public function log_failed($wp_error)
    {
        $log = get_option(self::OPTION, array());
        if (is_array($log) && !empty($log)) {
            // The failed message is the most recent wp_mail() call.
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
