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
 *   - the live send path: a single `pre_wp_mail` orchestrator that walks the full
 *     ordered connection chain (primary → fallbacks) across MIXED transports —
 *     SMTP and API — trying each until one delivers, then records which connection
 *     sent the message. Because the whole chain is driven from `pre_wp_mail` (never
 *     the lossy `wp_mail_failed` retry path), wp_mail returns the true delivery
 *     result to the caller and API + SMTP failures are unified into one retry loop.
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

    /** Re-entrancy guard while the chain is being walked. */
    private $busy = false;
    /** Details of the connection that delivered the most recent message. */
    private $last_delivery = null;

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
            // Brevo first + "Recommended": a reliable free-tier API provider that avoids
            // SMTP port/auth headaches — the easiest good default for most sites.
            'brevo' => array(
                'label' => 'Brevo (Sendinblue)', 'icon' => 'bolt', 'transport' => 'api', 'badge' => 'Recommended',
                'fields' => array(array('key' => 'api_key', 'type' => 'password', 'label' => 'API key', 'required' => true)),
            ),
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
        // One orchestrator owns the whole send: it walks the ordered chain across
        // mixed SMTP/API transports and returns the true delivery result. We never
        // hook wp_mail_failed for retry (that path cannot correct wp_mail's return).
        add_filter('pre_wp_mail', array($this, 'send'), 10, 2);
    }

    /** The most recent delivery record: ['id','title','attempts'] or null. */
    public function last_delivery()
    {
        return $this->last_delivery;
    }

    /**
     * pre_wp_mail orchestrator: attempt each connection in order until one delivers.
     *
     * @param null|bool $short Short-circuit value from earlier filters.
     * @param array     $atts  wp_mail() arguments (already run through the `wp_mail` filter).
     * @return bool|null true delivered, false all failed, null to defer to WP (nothing attempted).
     */
    public function send($short, $atts)
    {
        if (null !== $short) {
            return $short; // Another plugin already handled it.
        }
        if ($this->busy) {
            return $short; // Never re-enter our own chain.
        }
        $store = $this->store();
        if (empty($store['order'])) {
            return $short;
        }

        $this->busy = true;
        $msg     = $this->parse_atts($atts);
        $has_att = !empty($msg['attachments']);

        $attempts     = array();
        $attempted    = 0;
        $delivered_id = null;
        foreach ($store['order'] as $id) {
            $conn      = $store['connections'][$id];
            $transport = $this->transport_of($conn);
            $title     = $this->title_of($conn);

            // API providers can't carry attachments — skip (don't count as an attempt)
            // so a later SMTP connection can deliver, or WP's native transport can.
            if ($transport === 'api' && $has_att) {
                $attempts[] = array('id' => $id, 'title' => $title, 'transport' => $transport, 'ok' => false, 'skipped' => true, 'error' => 'Skipped — API providers cannot send attachments.');
                continue;
            }

            $res = ($transport === 'api') ? $this->deliver_api($conn, $msg) : $this->deliver_smtp($conn, $msg);
            $attempted++;
            $attempts[] = array('id' => $id, 'title' => $title, 'transport' => $transport, 'ok' => $res['ok'], 'error' => $res['error']);
            if ($res['ok']) {
                $delivered_id = $id;
                break;
            }
        }
        $this->busy = false;

        if ($delivered_id !== null) {
            $delivered_title = $this->title_of($store['connections'][$delivered_id]);
            $this->last_delivery = array('id' => $delivered_id, 'title' => $delivered_title, 'attempts' => $attempts);
            do_action('wp_arzo_email_delivered', $delivered_id, $delivered_title, $atts, $attempts);
            do_action('wp_mail_succeeded', array(
                'to'          => $msg['to'],
                'subject'     => $msg['subject'],
                'message'     => $msg['message'],
                'headers'     => isset($atts['headers']) ? $atts['headers'] : '',
                'attachments' => $msg['attachments'],
            ));
            return true;
        }

        if ($attempted === 0) {
            // Every connection was skipped (all API + this message has attachments):
            // defer to WordPress's native transport rather than silently dropping it.
            return $short;
        }

        // All attempted connections failed.
        $data = array(
            'to'          => $msg['to'],
            'subject'     => $msg['subject'],
            'message'     => $msg['message'],
            'headers'     => isset($atts['headers']) ? $atts['headers'] : '',
            'attachments' => $msg['attachments'],
            'attempts'    => $attempts,
        );
        $last_error = '';
        foreach (array_reverse($attempts) as $a) {
            if (empty($a['skipped'])) {
                $last_error = $a['error'];
                break;
            }
        }
        $error = new WP_Error('wp_arzo_email_all_failed', $last_error !== '' ? $last_error : 'All email connections failed.', $data);
        // Flip the log entry to failed first, then notify (notification churns entries).
        do_action('wp_mail_failed', $error);
        do_action('wp_arzo_email_all_failed', $data, $error, $attempts);
        return false;
    }

    private function title_of($conn)
    {
        if (isset($conn['title']) && $conn['title'] !== '') {
            return $conn['title'];
        }
        return isset($conn['provider']) ? $conn['provider'] : 'connection';
    }

    /**
     * Normalize wp_mail() arguments into a structured message, parsing headers the
     * same way WordPress core's wp_mail() does (From / Cc / Bcc / Reply-To /
     * Content-Type / charset / boundary / custom headers).
     *
     * @return array
     */
    private function parse_atts($atts)
    {
        $to = isset($atts['to']) ? $atts['to'] : array();
        if (!is_array($to)) {
            $to = explode(',', $to);
        }
        $subject = isset($atts['subject']) ? (string) $atts['subject'] : '';
        $message = isset($atts['message']) ? (string) $atts['message'] : '';
        $headers = isset($atts['headers']) ? $atts['headers'] : '';
        $attachments = isset($atts['attachments']) ? $atts['attachments'] : array();
        if (!is_array($attachments)) {
            $attachments = array_filter(explode("\n", str_replace("\r\n", "\n", (string) $attachments)));
        }

        $cc = array();
        $bcc = array();
        $reply_to = array();
        $from_email = '';
        $from_name = '';
        $content_type = '';
        $charset = '';
        $boundary = '';
        $custom = array();

        if (!empty($headers)) {
            $tempheaders = is_array($headers) ? $headers : explode("\n", str_replace("\r\n", "\n", (string) $headers));
            foreach ((array) $tempheaders as $header) {
                $header = (string) $header;
                if (strpos($header, ':') === false) {
                    if (stripos($header, 'boundary=') !== false) {
                        $parts    = preg_split('/boundary=/i', trim($header));
                        $boundary = trim(str_replace(array("'", '"'), '', $parts[1]));
                    }
                    continue;
                }
                list($name, $content) = explode(':', trim($header), 2);
                $name    = trim($name);
                $content = trim($content);
                switch (strtolower($name)) {
                    case 'from':
                        $bracket = strpos($content, '<');
                        if ($bracket !== false) {
                            if ($bracket > 0) {
                                $from_name = trim(str_replace('"', '', substr($content, 0, $bracket)));
                            }
                            $from_email = trim(str_replace('>', '', substr($content, $bracket + 1)));
                        } elseif (trim($content) !== '') {
                            $from_email = trim($content);
                        }
                        break;
                    case 'content-type':
                        if (strpos($content, ';') !== false) {
                            list($type, $charset_content) = explode(';', $content, 2);
                            $content_type = trim($type);
                            if (stripos($charset_content, 'charset=') !== false) {
                                $charset = trim(str_replace(array('charset=', '"'), '', $charset_content));
                            } elseif (stripos($charset_content, 'boundary=') !== false) {
                                $boundary = trim(str_replace(array('BOUNDARY=', 'boundary=', '"'), '', $charset_content));
                                $charset  = '';
                            }
                        } elseif (trim($content) !== '') {
                            $content_type = trim($content);
                        }
                        break;
                    case 'cc':
                        $cc = array_merge($cc, explode(',', $content));
                        break;
                    case 'bcc':
                        $bcc = array_merge($bcc, explode(',', $content));
                        break;
                    case 'reply-to':
                        $reply_to = array_merge($reply_to, explode(',', $content));
                        break;
                    default:
                        $custom[trim($name)] = trim($content);
                        break;
                }
            }
        }
        if ($content_type === '') {
            $content_type = 'text/plain';
        }
        if ($charset === '') {
            $charset = get_bloginfo('charset');
        }

        return compact('to', 'cc', 'bcc', 'reply_to', 'from_email', 'from_name', 'subject', 'message', 'content_type', 'charset', 'boundary', 'custom', 'attachments');
    }

    /**
     * Resolve the effective From for a connection: force_from wins, else the
     * message's own From, else the connection's From, else WordPress's default.
     *
     * @return array{0:string,1:string} [email, name]
     */
    private function resolve_from($conn, $msg)
    {
        $force  = !empty($conn['force_from']);
        $cEmail = trim((string) (isset($conn['from_email']) ? $conn['from_email'] : ''));
        $cName  = trim((string) (isset($conn['from_name']) ? $conn['from_name'] : ''));

        if ($force && $cEmail !== '') {
            $email = $cEmail;
        } elseif ($msg['from_email'] !== '') {
            $email = $msg['from_email'];
        } elseif ($cEmail !== '') {
            $email = $cEmail;
        } else {
            $email = $this->default_from_email();
        }

        if ($force && $cName !== '') {
            $name = $cName;
        } elseif ($msg['from_name'] !== '') {
            $name = $msg['from_name'];
        } elseif ($cName !== '') {
            $name = $cName;
        } else {
            $name = apply_filters('wp_mail_from_name', 'WordPress');
        }
        return array($email, $name);
    }

    private function default_from_email()
    {
        $sitename = strtolower((string) wp_parse_url(network_home_url(), PHP_URL_HOST));
        if (strpos($sitename, 'www.') === 0) {
            $sitename = substr($sitename, 4);
        }
        $from = $sitename !== '' ? 'wordpress@' . $sitename : (string) get_option('admin_email');
        return apply_filters('wp_mail_from', $from);
    }

    /* ---------------------------------------------------------- Transports */

    /**
     * Deliver a parsed message through a connection's SMTP transport by building a
     * throwaway PHPMailer (so a failed attempt never disturbs the next connection).
     *
     * @return array{ok:bool,error:string}
     */
    private function deliver_smtp($conn, $msg)
    {
        $s = $this->effective_smtp($conn);
        if ($s['host'] === '') {
            return array('ok' => false, 'error' => 'No SMTP host configured.');
        }
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
        }
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
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
            } else {
                $mail->SMTPAuth = false;
            }

            list($from_email, $from_name) = $this->resolve_from($conn, $msg);
            $mail->setFrom($from_email, $from_name, false);

            $addr_methods = array('to' => 'addAddress', 'cc' => 'addCc', 'bcc' => 'addBcc', 'reply_to' => 'addReplyTo');
            foreach ($addr_methods as $key => $method) {
                foreach ((array) $msg[$key] as $address) {
                    $address = trim((string) $address);
                    if ($address === '') {
                        continue;
                    }
                    $recipient_name = '';
                    if (preg_match('/(.*)<(.+)>/', $address, $m) && count($m) === 3) {
                        $recipient_name = trim($m[1]);
                        $address        = trim($m[2]);
                    }
                    $mail->{$method}($address, $recipient_name);
                }
            }

            $mail->Subject = $msg['subject'];
            $mail->Body    = $msg['message'];

            $content_type = apply_filters('wp_mail_content_type', $msg['content_type']);
            if ($content_type === 'text/html') {
                $mail->isHTML(true);
            }
            $mail->ContentType = $content_type;
            $mail->CharSet     = apply_filters('wp_mail_charset', $msg['charset']);

            foreach ($msg['custom'] as $name => $content) {
                if (strtolower($name) === 'mime-version') {
                    continue;
                }
                $mail->addCustomHeader(sprintf('%1$s: %2$s', $name, $content));
            }
            if ($msg['boundary'] !== '') {
                $mail->addCustomHeader(sprintf('Content-Type: %s; boundary="%s"', $content_type, $msg['boundary']));
            }
            foreach ((array) $msg['attachments'] as $filename => $attachment) {
                $attachment = (string) $attachment;
                if ($attachment === '') {
                    continue;
                }
                $mail->addAttachment($attachment, is_string($filename) ? $filename : '');
            }

            // Let other plugins tweak the message (DKIM, etc.), like core does.
            do_action_ref_array('phpmailer_init', array(&$mail));
            $mail->send();
            return array('ok' => true, 'error' => '');
        } catch (\Throwable $e) {
            $err = (isset($mail->ErrorInfo) && $mail->ErrorInfo) ? $mail->ErrorInfo : $e->getMessage();
            return array('ok' => false, 'error' => $err);
        }
    }

    /**
     * Deliver a parsed message through a connection's provider API.
     *
     * @return array{ok:bool,error:string}
     */
    private function deliver_api($conn, $msg)
    {
        $provider = isset($conn['provider']) ? $conn['provider'] : '';
        $key      = trim((string) (isset($conn['api_key']) ? $conn['api_key'] : ''));
        if ($key === '') {
            return array('ok' => false, 'error' => 'Missing API key.');
        }

        $to = array();
        foreach ((array) $msg['to'] as $address) {
            $address = trim((string) $address);
            if ($address === '') {
                continue;
            }
            if (preg_match('/(.*)<(.+)>/', $address, $m) && count($m) === 3) {
                $address = trim($m[2]);
            }
            $to[] = $address;
        }
        if (empty($to)) {
            return array('ok' => false, 'error' => 'No recipient address.');
        }

        list($from_email, $from_name) = $this->resolve_from($conn, $msg);
        $subject = $msg['subject'];
        $message = $msg['message'];
        $html    = (apply_filters('wp_mail_content_type', $msg['content_type']) === 'text/html');

        $ok = false;
        switch ($provider) {
            case 'sendgrid':
                $ok = $this->via_sendgrid($key, $to, $subject, $message, $html, $from_email, $from_name);
                break;
            case 'brevo':
                $ok = $this->via_brevo($key, $to, $subject, $message, $html, $from_email, $from_name);
                break;
            case 'mailgun':
                $ok = $this->via_mailgun($conn, $key, $to, $subject, $message, $html, $from_email, $from_name);
                break;
            case 'postmark':
                $ok = $this->via_postmark($key, $to, $subject, $message, $html, $from_email, $from_name);
                break;
            default:
                return array('ok' => false, 'error' => 'Unsupported API provider.');
        }
        return array('ok' => $ok, 'error' => $ok ? '' : 'The provider API rejected the message.');
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
        $subject = sprintf('Test email from %s', $site);
        $message = sprintf("This is a test email sent from %s via the \"%s\" connection.\n\nIf you received this, the connection works.", $site, $this->title_of($conn));

        // Route the test through the same deliver path as live mail, so a passing
        // test genuinely exercises the connection the chain would use.
        $msg = array(
            'to'           => array($to),
            'cc'           => array(),
            'bcc'          => array(),
            'reply_to'     => array(),
            'from_email'   => '',
            'from_name'    => '',
            'subject'      => $subject,
            'message'      => $message,
            'content_type' => 'text/plain',
            'charset'      => get_bloginfo('charset'),
            'boundary'     => '',
            'custom'       => array(),
            'attachments'  => array(),
        );

        $is_api = ($this->transport_of($conn) === 'api');
        $res    = $is_api ? $this->deliver_api($conn, $msg) : $this->deliver_smtp($conn, $msg);
        if ($res['ok']) {
            return true;
        }
        $prefix = $is_api ? 'API test failed: ' : 'SMTP test failed: ';
        return new WP_Error('send', $prefix . ($res['error'] !== '' ? $res['error'] : 'the connection could not send the test message.'));
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
