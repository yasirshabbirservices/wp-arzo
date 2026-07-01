<?php

/**
 * WP Arzo — Email Connections.
 *
 * A SureMail-style connection manager for outgoing email: store any number of
 * named "connections" (Custom SMTP, Gmail, Outlook, Zoho, Amazon SES, SendGrid,
 * Brevo, Mailgun, Postmark, …), each configured from a per-provider field schema,
 * ordered so the first is primary and the rest are fallbacks.
 *
 * This class owns:
 *   - the provider registry (providers() — drives the card picker + drawer form),
 *   - the connections store (option `wp_arzo_smtp_connections`),
 *   - CRUD + reorder + set-primary + delete,
 *   - a standalone test-send (independent of wp_mail) per connection,
 *   - the live send path: it configures the PRIMARY connection for wp_mail, with a
 *     single-step fallback to the next connection on failure (the full N-step
 *     fallback chain lands in a later phase — see the Fallback engine).
 *
 * The management UI lives on the dashboard "Email" page (class-wp-arzo-admin.php);
 * the `smtp` feature (class-features-email.php) enables this engine when on.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Email_Connections
{
    const OPTION = 'wp_arzo_smtp_connections';

    /** @var WP_Arzo_Email_Connections|null */
    private static $instance = null;

    /** Index into the fallback order of the connection currently being sent. */
    private $active = 0;
    /** Re-entrancy guard while a fallback retry / notification is in flight. */
    private $busy = false;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /* ---------------------------------------------------- Provider registry */

    /**
     * The provider catalog. Each entry:
     *   label      – display name (card + drawer heading)
     *   icon       – wp_arzo_icon() key for the card
     *   transport  – 'smtp' | 'api'
     *   badge      – optional pill label (e.g. 'Popular')
     *   preset     – SMTP host/port/encryption/auth pre-filled for named SMTP providers
     *   host_tmpl  – printf template building host from a field (e.g. SES region)
     *   fields     – provider-specific fields appended to the common fields
     *
     * @return array<string,array>
     */
    public static function providers()
    {
        $enc = array('none' => 'None', 'ssl' => 'SSL', 'tls' => 'TLS');

        $smtp_auth_fields = array(
            array('key' => 'username', 'type' => 'text', 'label' => 'Username', 'required' => true),
            array('key' => 'password', 'type' => 'password', 'label' => 'Password', 'required' => true),
        );

        return array(
            'smtp' => array(
                'label' => 'Custom SMTP', 'icon' => 'mail', 'transport' => 'smtp', 'badge' => 'Any host',
                'fields' => array_merge(array(
                    array('key' => 'host', 'type' => 'text', 'label' => 'SMTP host', 'required' => true, 'placeholder' => 'smtp.example.com'),
                    array('key' => 'port', 'type' => 'number', 'label' => 'Port', 'default' => 587),
                    array('key' => 'encryption', 'type' => 'select', 'label' => 'Encryption', 'options' => $enc, 'default' => 'tls'),
                    array('key' => 'auth', 'type' => 'toggle', 'label' => 'Use authentication', 'default' => 1),
                ), $smtp_auth_fields),
            ),
            'gmail' => array(
                'label' => 'Gmail / Google Workspace', 'icon' => 'mail', 'transport' => 'smtp', 'badge' => 'Popular',
                'preset' => array('host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls', 'auth' => 1),
                'fields' => array(
                    array('key' => 'username', 'type' => 'text', 'label' => 'Gmail address', 'required' => true, 'placeholder' => 'you@gmail.com'),
                    array('key' => 'password', 'type' => 'password', 'label' => 'App password', 'required' => true, 'help' => 'Create a Google App Password — your normal login password will not work.'),
                ),
            ),
            'outlook' => array(
                'label' => 'Outlook / Microsoft 365', 'icon' => 'mail', 'transport' => 'smtp',
                'preset' => array('host' => 'smtp.office365.com', 'port' => 587, 'encryption' => 'tls', 'auth' => 1),
                'fields' => $smtp_auth_fields,
            ),
            'zoho' => array(
                'label' => 'Zoho Mail', 'icon' => 'mail', 'transport' => 'smtp',
                'preset' => array('host' => 'smtp.zoho.com', 'port' => 587, 'encryption' => 'tls', 'auth' => 1),
                'fields' => $smtp_auth_fields,
            ),
            'yahoo' => array(
                'label' => 'Yahoo Mail', 'icon' => 'mail', 'transport' => 'smtp',
                'preset' => array('host' => 'smtp.mail.yahoo.com', 'port' => 465, 'encryption' => 'ssl', 'auth' => 1),
                'fields' => $smtp_auth_fields,
            ),
            'fastmail' => array(
                'label' => 'Fastmail', 'icon' => 'mail', 'transport' => 'smtp',
                'preset' => array('host' => 'smtp.fastmail.com', 'port' => 465, 'encryption' => 'ssl', 'auth' => 1),
                'fields' => $smtp_auth_fields,
            ),
            'ses' => array(
                'label' => 'Amazon SES (SMTP)', 'icon' => 'cloud', 'transport' => 'smtp',
                'preset' => array('port' => 587, 'encryption' => 'tls', 'auth' => 1),
                'host_tmpl' => array('field' => 'region', 'tmpl' => 'email-smtp.%s.amazonaws.com'),
                'fields' => array(
                    array('key' => 'region', 'type' => 'select', 'label' => 'AWS region', 'options' => self::ses_regions(), 'default' => 'us-east-1'),
                    array('key' => 'username', 'type' => 'text', 'label' => 'SMTP username', 'required' => true, 'help' => 'Your SES SMTP credentials (not your AWS access keys).'),
                    array('key' => 'password', 'type' => 'password', 'label' => 'SMTP password', 'required' => true),
                ),
            ),
            'mailjet' => array(
                'label' => 'Mailjet (SMTP)', 'icon' => 'bolt', 'transport' => 'smtp',
                'preset' => array('host' => 'in-v3.mailjet.com', 'port' => 587, 'encryption' => 'tls', 'auth' => 1),
                'fields' => array(
                    array('key' => 'username', 'type' => 'text', 'label' => 'API key', 'required' => true),
                    array('key' => 'password', 'type' => 'password', 'label' => 'Secret key', 'required' => true),
                ),
            ),

            // API-transport providers.
            'sendgrid' => array(
                'label' => 'SendGrid', 'icon' => 'bolt', 'transport' => 'api',
                'fields' => array(array('key' => 'api_key', 'type' => 'password', 'label' => 'API key', 'required' => true)),
            ),
            'brevo' => array(
                'label' => 'Brevo (Sendinblue)', 'icon' => 'bolt', 'transport' => 'api',
                'fields' => array(array('key' => 'api_key', 'type' => 'password', 'label' => 'API key', 'required' => true)),
            ),
            'mailgun' => array(
                'label' => 'Mailgun', 'icon' => 'bolt', 'transport' => 'api',
                'fields' => array(
                    array('key' => 'api_key', 'type' => 'password', 'label' => 'API key', 'required' => true),
                    array('key' => 'domain', 'type' => 'text', 'label' => 'Sending domain', 'required' => true, 'placeholder' => 'mg.yourdomain.com'),
                    array('key' => 'region', 'type' => 'select', 'label' => 'Region', 'options' => array('us' => 'US', 'eu' => 'EU'), 'default' => 'us'),
                ),
            ),
            'postmark' => array(
                'label' => 'Postmark', 'icon' => 'bolt', 'transport' => 'api',
                'fields' => array(array('key' => 'api_key', 'type' => 'password', 'label' => 'Server API token', 'required' => true)),
            ),
        );
    }

    /** Common fields shown at the top of every connection drawer. */
    public static function common_fields()
    {
        return array(
            array('key' => 'title', 'type' => 'text', 'label' => 'Connection name', 'required' => true, 'placeholder' => 'e.g. Primary Gmail', 'help' => 'A label to recognise this connection.'),
            array('key' => 'from_email', 'type' => 'email', 'label' => 'From email', 'help' => 'The address mail is sent from. Leave blank to keep WordPress’s default.'),
            array('key' => 'from_name', 'type' => 'text', 'label' => 'From name'),
            array('key' => 'force_from', 'type' => 'toggle', 'label' => 'Force from name/email on all mail', 'default' => 1),
        );
    }

    /** The full field schema (common + provider) for a provider key. */
    public static function fields_for($provider)
    {
        $providers = self::providers();
        if (!isset($providers[$provider])) {
            return array();
        }
        $pfields = isset($providers[$provider]['fields']) ? $providers[$provider]['fields'] : array();
        return array_merge(self::common_fields(), $pfields);
    }

    public static function ses_regions()
    {
        $regions = array(
            'us-east-1', 'us-east-2', 'us-west-1', 'us-west-2',
            'eu-west-1', 'eu-west-2', 'eu-central-1', 'eu-north-1',
            'ap-south-1', 'ap-southeast-1', 'ap-southeast-2', 'ap-northeast-1',
            'ca-central-1', 'sa-east-1',
        );
        return array_combine($regions, $regions);
    }

    /* ------------------------------------------------------------ Storage */

    private function store()
    {
        $store = get_option(self::OPTION, array());
        if (!is_array($store)) {
            $store = array();
        }
        if (!isset($store['connections']) || !is_array($store['connections'])) {
            $store['connections'] = array();
        }
        if (!isset($store['order']) || !is_array($store['order'])) {
            $store['order'] = array_keys($store['connections']);
        }
        // Drop stale order ids and append any missing ones.
        $store['order'] = array_values(array_filter($store['order'], function ($id) use ($store) {
            return isset($store['connections'][$id]);
        }));
        foreach (array_keys($store['connections']) as $id) {
            if (!in_array($id, $store['order'], true)) {
                $store['order'][] = $id;
            }
        }
        return $store;
    }

    private function put($store)
    {
        update_option(self::OPTION, $store, false);
    }

    /** All connections in fallback order (primary first). */
    public function all()
    {
        $store = $this->store();
        $out = array();
        foreach ($store['order'] as $id) {
            $out[] = $store['connections'][$id];
        }
        return $out;
    }

    public function get($id)
    {
        $store = $this->store();
        return isset($store['connections'][$id]) ? $store['connections'][$id] : null;
    }

    public function count()
    {
        return count($this->store()['connections']);
    }

    /**
     * Create or update a connection from posted data. Only fields the provider
     * declares are kept; passwords blank-on-save preserve the stored secret.
     *
     * @return string|WP_Error connection id
     */
    public function save($data)
    {
        $provider = isset($data['provider']) ? sanitize_key($data['provider']) : '';
        $providers = self::providers();
        if (!isset($providers[$provider])) {
            return new WP_Error('provider', 'Unknown email provider.');
        }

        $id = (isset($data['id']) && $data['id'] !== '')
            ? preg_replace('/[^a-z0-9_]/', '', (string) $data['id'])
            : 'conn_' . substr(md5(uniqid('', true)), 0, 12);

        $store    = $this->store();
        $existing = isset($store['connections'][$id]) ? $store['connections'][$id] : array();

        $conn = array('id' => $id, 'provider' => $provider);
        foreach (self::fields_for($provider) as $field) {
            $key  = $field['key'];
            $type = $field['type'];
            $raw  = isset($data[$key]) ? $data[$key] : null;

            if ($type === 'password') {
                // Blank keeps the previously stored secret.
                $val = ($raw === null || $raw === '') ? (isset($existing[$key]) ? $existing[$key] : '') : (string) $raw;
            } elseif ($type === 'toggle') {
                $val = !empty($raw) ? 1 : 0;
            } elseif ($type === 'number') {
                $val = (int) $raw;
            } elseif ($type === 'email') {
                $val = sanitize_email((string) $raw);
            } elseif ($type === 'select') {
                $opts = isset($field['options']) ? array_map('strval', array_keys($field['options'])) : array();
                $val  = in_array((string) $raw, $opts, true) ? (string) $raw : (isset($field['default']) ? $field['default'] : '');
            } else {
                $val = sanitize_text_field((string) $raw);
            }
            $conn[$key] = $val;
        }

        if (empty($conn['title'])) {
            $conn['title'] = $providers[$provider]['label'];
        }

        $store['connections'][$id] = $conn;
        if (!in_array($id, $store['order'], true)) {
            $store['order'][] = $id;
        }
        $this->put($store);
        return $id;
    }

    public function delete($id)
    {
        $store = $this->store();
        if (!isset($store['connections'][$id])) {
            return false;
        }
        unset($store['connections'][$id]);
        $store['order'] = array_values(array_filter($store['order'], function ($x) use ($id) {
            return $x !== $id;
        }));
        $this->put($store);
        return true;
    }

    /** Move a connection to the front of the fallback order (make it primary). */
    public function set_primary($id)
    {
        $store = $this->store();
        if (!isset($store['connections'][$id])) {
            return false;
        }
        $store['order'] = array_values(array_filter($store['order'], function ($x) use ($id) {
            return $x !== $id;
        }));
        array_unshift($store['order'], $id);
        $this->put($store);
        return true;
    }

    /** Replace the fallback order with the given id list (unknown ids dropped). */
    public function reorder($ids)
    {
        $store = $this->store();
        $clean = array();
        foreach ((array) $ids as $id) {
            $id = preg_replace('/[^a-z0-9_]/', '', (string) $id);
            if (isset($store['connections'][$id]) && !in_array($id, $clean, true)) {
                $clean[] = $id;
            }
        }
        foreach (array_keys($store['connections']) as $id) {
            if (!in_array($id, $clean, true)) {
                $clean[] = $id;
            }
        }
        $store['order'] = $clean;
        $this->put($store);
        return true;
    }

    /* ------------------------------------------------ Effective transport */

    /**
     * Resolve a connection's effective SMTP parameters, applying the provider
     * preset and any host-from-field template.
     *
     * @return array{host:string,port:int,encryption:string,auth:int,username:string,password:string}
     */
    public function effective_smtp($conn)
    {
        $providers = self::providers();
        $provider  = isset($conn['provider']) ? $conn['provider'] : 'smtp';
        $def       = isset($providers[$provider]) ? $providers[$provider] : array();
        $preset    = isset($def['preset']) ? $def['preset'] : array();

        $host = isset($conn['host']) ? (string) $conn['host'] : (isset($preset['host']) ? $preset['host'] : '');
        if (isset($def['host_tmpl'])) {
            $f = $def['host_tmpl']['field'];
            $host = sprintf($def['host_tmpl']['tmpl'], isset($conn[$f]) ? $conn[$f] : '');
        }
        return array(
            'host'       => $host,
            'port'       => (int) (isset($conn['port']) ? $conn['port'] : (isset($preset['port']) ? $preset['port'] : 587)),
            'encryption' => isset($conn['encryption']) ? $conn['encryption'] : (isset($preset['encryption']) ? $preset['encryption'] : 'tls'),
            'auth'       => (int) (isset($conn['auth']) ? $conn['auth'] : (isset($preset['auth']) ? $preset['auth'] : 1)),
            'username'   => isset($conn['username']) ? (string) $conn['username'] : '',
            'password'   => isset($conn['password']) ? (string) $conn['password'] : '',
        );
    }

    public function transport_of($conn)
    {
        $providers = self::providers();
        $provider  = isset($conn['provider']) ? $conn['provider'] : 'smtp';
        return isset($providers[$provider]['transport']) ? $providers[$provider]['transport'] : 'smtp';
    }

    /* ------------------------------------------------------ Live send path */

    public function boot()
    {
        $store = $this->store();
        if (empty($store['order'])) {
            return; // No connections — leave WordPress's default transport alone.
        }

        add_action('phpmailer_init', array($this, 'configure_active'));
        add_filter('pre_wp_mail', array($this, 'maybe_send_api'), 10, 2);
        add_action('wp_mail_failed', array($this, 'on_failed'));

        // Always attach the from filters; they no-op unless the ACTIVE connection has
        // force_from + a from address, so the correct from is used on fallback too.
        add_filter('wp_mail_from', array($this, 'filter_from'), 99);
        add_filter('wp_mail_from_name', array($this, 'filter_from_name'), 99);
    }

    private function active_conn()
    {
        $store = $this->store();
        if (!isset($store['order'][$this->active])) {
            return null;
        }
        return $store['connections'][$store['order'][$this->active]];
    }

    public function filter_from($email)
    {
        $c = $this->active_conn();
        return ($c && !empty($c['force_from']) && !empty($c['from_email'])) ? $c['from_email'] : $email;
    }
    public function filter_from_name($name)
    {
        $c = $this->active_conn();
        return ($c && !empty($c['force_from']) && !empty($c['from_name'])) ? $c['from_name'] : $name;
    }

    /** phpmailer_init: configure SMTP for the active connection. */
    public function configure_active($phpmailer)
    {
        $c = $this->active_conn();
        if (!$c || $this->transport_of($c) !== 'smtp') {
            return;
        }
        $s = $this->effective_smtp($c);
        if ($s['host'] === '') {
            return;
        }
        $phpmailer->isSMTP();
        $phpmailer->Host = $s['host'];
        $phpmailer->Port = $s['port'];
        if ($s['encryption'] === 'ssl' || $s['encryption'] === 'tls') {
            $phpmailer->SMTPSecure = $s['encryption'];
        } else {
            $phpmailer->SMTPSecure  = '';
            $phpmailer->SMTPAutoTLS = false;
        }
        if ($s['auth']) {
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = $s['username'];
            $phpmailer->Password = $s['password'];
        } else {
            $phpmailer->SMTPAuth = false;
        }
    }

    /** pre_wp_mail: when the active connection is an API provider, send via API. */
    public function maybe_send_api($short, $atts)
    {
        $c = $this->active_conn();
        if (!$c || $this->transport_of($c) !== 'api') {
            return $short;
        }
        if (!empty($atts['attachments'])) {
            return $short; // Let the default transport handle attachments.
        }
        $ok = $this->send_via_api($c, $atts);
        if ($ok) {
            return true;
        }
        // API send failed: try one fallback connection.
        return $this->fallback($atts) ? true : false;
    }

    /** wp_mail_failed (SMTP path): try one fallback connection. */
    public function on_failed($error)
    {
        if ($this->busy || !is_wp_error($error)) {
            return;
        }
        $data = $error->get_error_data();
        if (!is_array($data) || empty($data['to'])) {
            return;
        }
        if (!$this->fallback($data)) {
            do_action('wp_arzo_email_all_failed', $data, $error);
        }
    }

    /**
     * Advance to the next connection and re-send once. Single-step for now; the
     * full N-step chain is added by the Fallback engine phase.
     */
    private function fallback($atts)
    {
        $store = $this->store();
        if ($this->active + 1 >= count($store['order'])) {
            return false; // No further connection to try.
        }
        $this->active++;
        $this->busy = true;
        $ok = wp_mail(
            isset($atts['to']) ? $atts['to'] : array(),
            isset($atts['subject']) ? $atts['subject'] : '',
            isset($atts['message']) ? $atts['message'] : '',
            isset($atts['headers']) ? $atts['headers'] : '',
            isset($atts['attachments']) ? $atts['attachments'] : array()
        );
        $this->busy = false;
        $this->active = 0;
        return (bool) $ok;
    }

    /* ------------------------------------------------------- API transports */

    /** Deliver $atts through a connection's provider API. Returns bool. */
    private function send_via_api($conn, $atts)
    {
        $provider = isset($conn['provider']) ? $conn['provider'] : '';
        $key = trim((string) (isset($conn['api_key']) ? $conn['api_key'] : ''));
        if ($key === '') {
            return false;
        }

        $to = isset($atts['to']) ? $atts['to'] : array();
        if (!is_array($to)) {
            $to = explode(',', $to);
        }
        $to = array_filter(array_map('trim', $to));
        if (empty($to)) {
            return false;
        }

        $subject = isset($atts['subject']) ? (string) $atts['subject'] : '';
        $message = isset($atts['message']) ? (string) $atts['message'] : '';
        $parsed  = $this->parse_headers(isset($atts['headers']) ? $atts['headers'] : '');

        $from_email = trim((string) (isset($conn['from_email']) ? $conn['from_email'] : '')) ?: $parsed['from_email'];
        if ($from_email === '') {
            $from_email = apply_filters('wp_mail_from', get_option('admin_email'));
        }
        $from_name = trim((string) (isset($conn['from_name']) ? $conn['from_name'] : '')) ?: $parsed['from_name'];
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
                return $this->via_mailgun($conn, $key, $to, $subject, $message, $html, $from_email, $from_name);
            case 'postmark':
                return $this->via_postmark($key, $to, $subject, $message, $html, $from_email, $from_name);
        }
        return false;
    }

    private function parse_headers($headers)
    {
        $out = array('content_type' => 'text/plain', 'from_email' => '', 'from_name' => '');
        if (!is_array($headers)) {
            $headers = explode("\n", str_replace("\r\n", "\n", (string) $headers));
        }
        foreach ($headers as $h) {
            $h = trim((string) $h);
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

    private function via_mailgun($conn, $key, $to, $subject, $message, $html, $from_email, $from_name)
    {
        $domain = trim((string) (isset($conn['domain']) ? $conn['domain'] : ''));
        if ($domain === '') {
            return false;
        }
        $base = ((isset($conn['region']) ? $conn['region'] : 'us') === 'eu') ? 'https://api.eu.mailgun.net' : 'https://api.mailgun.net';
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

    private function via_postmark($key, $to, $subject, $message, $html, $from_email, $from_name)
    {
        $body = array(
            'From'    => $from_name ? sprintf('%s <%s>', $from_name, $from_email) : $from_email,
            'To'      => implode(',', $to),
            'Subject' => $subject,
            'MessageStream' => 'outbound',
        );
        $body[$html ? 'HtmlBody' : 'TextBody'] = $message;
        return $this->api_ok(wp_remote_post('https://api.postmarkapp.com/email', array(
            'timeout' => 15,
            'headers' => array(
                'X-Postmark-Server-Token' => $key,
                'Content-Type'            => 'application/json',
                'Accept'                  => 'application/json',
            ),
            'body'    => wp_json_encode($body),
        )));
    }

    /* --------------------------------------------------------- Test send */

    /**
     * Send a one-off test message through a specific connection, independent of
     * wp_mail (so it never disturbs the live send path).
     *
     * @return true|WP_Error
     */
    public function test($id, $to)
    {
        $conn = $this->get($id);
        if (!$conn) {
            return new WP_Error('not_found', 'Connection not found.');
        }
        $to = sanitize_email((string) $to);
        if (!is_email($to)) {
            return new WP_Error('to', 'Enter a valid recipient email.');
        }

        $site    = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $subject = sprintf('SureMail-style test from %s', $site);
        $message = sprintf("This is a test email sent from %s via the \"%s\" connection.\n\nIf you received this, the connection works.", $site, isset($conn['title']) ? $conn['title'] : $conn['provider']);
        $atts    = array(
            'to'          => array($to),
            'subject'     => $subject,
            'message'     => $message,
            'headers'     => 'From: ' . (($conn['from_name'] ?? '') ? $conn['from_name'] . ' ' : '') . '<' . (($conn['from_email'] ?? '') ?: get_option('admin_email')) . ">\n",
            'attachments' => array(),
        );

        if ($this->transport_of($conn) === 'api') {
            return $this->send_via_api($conn, $atts) ? true : new WP_Error('send', 'The provider API rejected the test message. Check the API key and details.');
        }
        return $this->test_smtp($conn, $to, $subject, $message);
    }

    /** Build a throwaway PHPMailer for an SMTP connection and send the test. */
    private function test_smtp($conn, $to, $subject, $message)
    {
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
        }
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $s = $this->effective_smtp($conn);
        try {
            $mail->isSMTP();
            $mail->Host = $s['host'];
            $mail->Port = $s['port'];
            if ($s['encryption'] === 'ssl' || $s['encryption'] === 'tls') {
                $mail->SMTPSecure = $s['encryption'];
            } else {
                $mail->SMTPSecure  = '';
                $mail->SMTPAutoTLS = false;
            }
            if ($s['auth']) {
                $mail->SMTPAuth = true;
                $mail->Username = $s['username'];
                $mail->Password = $s['password'];
            }
            $from_email = (isset($conn['from_email']) && $conn['from_email']) ? $conn['from_email'] : get_option('admin_email');
            $from_name  = isset($conn['from_name']) ? $conn['from_name'] : '';
            $mail->setFrom($from_email, $from_name);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->send();
            return true;
        } catch (\Throwable $e) {
            $err = $mail->ErrorInfo ? $mail->ErrorInfo : $e->getMessage();
            return new WP_Error('smtp', 'SMTP test failed: ' . $err);
        }
    }

    /* ---------------------------------------------------------- Migration */

    /**
     * One-time import of the legacy single-connection `smtp` feature settings into
     * a connection, so upgrading users keep working without reconfiguring.
     */
    public function maybe_migrate_legacy()
    {
        if ($this->count() > 0) {
            return;
        }
        $settings = get_option('wp_arzo_settings', array());
        $legacy   = isset($settings['smtp']) && is_array($settings['smtp']) ? $settings['smtp'] : array();
        if (empty($legacy)) {
            return;
        }
        $method = isset($legacy['method']) ? $legacy['method'] : 'smtp';
        $map    = array('sendgrid' => 'sendgrid', 'brevo' => 'brevo', 'mailgun' => 'mailgun');
        $provider = isset($map[$method]) ? $map[$method] : 'smtp';

        $data = array(
            'provider'   => $provider,
            'title'      => 'Imported connection',
            'from_email' => isset($legacy['from_email']) ? $legacy['from_email'] : '',
            'from_name'  => isset($legacy['from_name']) ? $legacy['from_name'] : '',
            'force_from' => isset($legacy['force_from']) ? $legacy['force_from'] : 1,
        );
        if ($provider === 'smtp') {
            $data += array(
                'host'       => isset($legacy['host']) ? $legacy['host'] : '',
                'port'       => isset($legacy['port']) ? $legacy['port'] : 587,
                'encryption' => isset($legacy['encryption']) ? $legacy['encryption'] : 'tls',
                'auth'       => isset($legacy['auth']) ? $legacy['auth'] : 1,
                'username'   => isset($legacy['username']) ? $legacy['username'] : '',
                'password'   => isset($legacy['password']) ? $legacy['password'] : '',
            );
            if (trim((string) $data['host']) === '') {
                return; // Nothing usable to import.
            }
        } else {
            $data['api_key'] = isset($legacy['api_key']) ? $legacy['api_key'] : '';
            if ($provider === 'mailgun') {
                $data['domain'] = isset($legacy['mailgun_domain']) ? $legacy['mailgun_domain'] : '';
                $data['region'] = isset($legacy['mailgun_region']) ? $legacy['mailgun_region'] : 'us';
            }
            if (trim((string) $data['api_key']) === '') {
                return;
            }
        }
        $this->save($data);
    }

    /** Remove the store — used by uninstall. */
    public static function delete_all()
    {
        delete_option(self::OPTION);
    }
}
