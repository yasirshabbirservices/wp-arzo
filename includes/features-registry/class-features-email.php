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
 * Reliable email delivery. This feature is the on/off switch + failure-notification
 * layer; the actual multi-connection delivery (providers, presets, fallback) is owned
 * by WP_Arzo_Email_Connections and managed on the dashboard "Email" page.
 */
class WP_Arzo_Feature_SMTP extends WP_Arzo_Feature
{
    public function id()
    {
        return 'smtp';
    }
    public function title()
    {
        return 'Email Delivery (SMTP & API)';
    }
    public function description()
    {
        return 'Reliable email delivery — connect one or more providers (Custom SMTP, Gmail, Outlook, Zoho, Amazon SES, SendGrid, Brevo, Mailgun, Postmark …) with automatic fallback. Manage connections on the Email page.';
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
        // Delivery is configured on the Email → Connections page; only the failure
        // alert lives here as a simple schema setting.
        return array(
            array('key' => 'notify_enabled', 'type' => 'toggle', 'label' => 'Email me when a message fails on every connection', 'default' => 0),
            array('key' => 'notify_email', 'type' => 'email', 'label' => 'Failure notification address', 'default' => '', 'help' => 'Defaults to the site admin email.', 'show_if' => array('field' => 'notify_enabled', 'value' => '1')),
        );
    }

    public function boot()
    {
        // Own the failure alert; the connections engine fires this when every
        // connection has been exhausted for a message.
        add_action('wp_arzo_email_all_failed', array($this, 'on_all_failed'), 10, 2);

        if (!class_exists('WP_Arzo_Email_Connections')) {
            return;
        }
        $engine = WP_Arzo_Email_Connections::instance();
        $engine->maybe_migrate_legacy();
        $engine->boot();
    }

    /** Notify the admin when a message could not be delivered by any connection. */
    public function on_all_failed($data, $error)
    {
        if (!$this->get_setting('notify_enabled', 0)) {
            return;
        }
        $to = sanitize_email((string) $this->get_setting('notify_email', ''));
        if ($to === '') {
            $to = get_option('admin_email');
        }
        $recipient = (isset($data['to']) && is_array($data['to'])) ? implode(', ', $data['to']) : (isset($data['to']) ? $data['to'] : '');
        $body = "An outgoing email could not be delivered by any configured connection.\n\n"
            . 'Site: ' . home_url('/') . "\n"
            . 'To: ' . $recipient . "\n"
            . 'Subject: ' . (isset($data['subject']) ? $data['subject'] : '') . "\n"
            . 'Error: ' . (is_wp_error($error) ? $error->get_error_message() : '') . "\n";
        // Send directly (bypass the connections engine, which is exhausted).
        remove_action('wp_arzo_email_all_failed', array($this, 'on_all_failed'), 10);
        wp_mail($to, '[Email failure] ' . get_bloginfo('name'), $body);
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
