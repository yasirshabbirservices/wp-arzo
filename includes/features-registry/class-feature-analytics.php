<?php

/**
 * Free feature: Analytics (built-in, first-party, cookieless).
 *
 * A privacy-first website analytics engine that records traffic entirely in the
 * site's own database — no cookies, no external services, no personal data at
 * rest (IPs are only used transiently to derive a daily-rotating salted visitor
 * hash, then discarded). The Independent Analytics / Plausible model, built in.
 *
 * Phase 1 (this file): tracking beacon → REST collector → hits table, with
 * bot/role/IP/DNT exclusion, daily retention pruning, and Overview / Pages /
 * Referrers reports (see the admin page render_analytics()). Later phases add
 * geo + device + behaviour reports, CSV export, and the Pro advanced suite.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================= Engine */

class WP_Arzo_Analytics
{
    const DB_VERSION = '3';
    const OPT_DB     = 'wp_arzo_analytics_db';
    const OPT_SALT   = 'wp_arzo_analytics_salt';
    const CRON_PRUNE = 'wp_arzo_analytics_prune';

    private static $instance = null;

    private $enabled       = true;
    private $respect_dnt   = true;
    private $track_admins  = false;
    private $retention_days = 365;
    private $exclude_roles = array('administrator');
    private $exclude_ips   = array();

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function configure($args)
    {
        $this->enabled        = !empty($args['enabled']);
        $this->respect_dnt    = !empty($args['respect_dnt']);
        $this->track_admins   = !empty($args['track_admins']);
        $this->retention_days = max(0, min(3650, (int) ($args['retention_days'] ?? 365)));
        $this->exclude_roles  = is_array($args['exclude_roles'] ?? null) ? array_map('sanitize_key', $args['exclude_roles']) : array();
        $ips = is_array($args['exclude_ips'] ?? null) ? $args['exclude_ips'] : array();
        $this->exclude_ips = array_values(array_filter(array_map('trim', $ips)));
    }

    public function table()
    {
        global $wpdb;
        return $wpdb->prefix . 'wp_arzo_analytics_hits';
    }

    /** The events table (clicks / downloads / outbound / form submits / custom events). */
    public function events_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'wp_arzo_analytics_events';
    }

    public function maybe_install()
    {
        if (get_option(self::OPT_DB) === self::DB_VERSION) {
            return;
        }
        global $wpdb;
        $table   = $this->table();
        $events  = $this->events_table();
        $collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ts INT UNSIGNED NOT NULL DEFAULT 0,
            path VARCHAR(190) NOT NULL DEFAULT '',
            title VARCHAR(190) NOT NULL DEFAULT '',
            ref_host VARCHAR(190) NOT NULL DEFAULT '',
            ref VARCHAR(255) NOT NULL DEFAULT '',
            utm_source VARCHAR(100) NOT NULL DEFAULT '',
            utm_medium VARCHAR(100) NOT NULL DEFAULT '',
            utm_campaign VARCHAR(100) NOT NULL DEFAULT '',
            country CHAR(2) NOT NULL DEFAULT '',
            device VARCHAR(10) NOT NULL DEFAULT '',
            browser VARCHAR(30) NOT NULL DEFAULT '',
            os VARCHAR(30) NOT NULL DEFAULT '',
            visitor CHAR(16) NOT NULL DEFAULT '',
            session CHAR(16) NOT NULL DEFAULT '',
            is_404 TINYINT NOT NULL DEFAULT 0,
            search VARCHAR(190) NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            KEY ts (ts),
            KEY visitor (visitor),
            KEY session (session),
            KEY path (path)
        ) {$collate};";
        $sql_events = "CREATE TABLE {$events} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ts INT UNSIGNED NOT NULL DEFAULT 0,
            etype VARCHAR(20) NOT NULL DEFAULT '',
            name VARCHAR(190) NOT NULL DEFAULT '',
            path VARCHAR(190) NOT NULL DEFAULT '',
            target VARCHAR(255) NOT NULL DEFAULT '',
            country CHAR(2) NOT NULL DEFAULT '',
            device VARCHAR(10) NOT NULL DEFAULT '',
            visitor CHAR(16) NOT NULL DEFAULT '',
            session CHAR(16) NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            KEY ts (ts),
            KEY etype (etype),
            KEY visitor (visitor)
        ) {$collate};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        dbDelta($sql_events);
        update_option(self::OPT_DB, self::DB_VERSION, false);
    }

    /* ------------------------------------------------ pure helpers (harnessed) */

    /** Cookieless visitor id: salted hash of the day's salt ⊕ IP ⊕ UA. */
    public static function visitor_hash($salt, $ip, $ua)
    {
        return substr(hash('sha256', $salt . '|' . $ip . '|' . $ua), 0, 16);
    }

    /** Session id: visitor bucketed into a 30-minute window (stateless, cookieless). */
    public static function session_hash($salt, $ip, $ua, $ts)
    {
        $bucket = (int) floor(((int) $ts) / 1800);
        return substr(hash('sha256', $salt . '|' . $ip . '|' . $ua . '|' . $bucket), 0, 16);
    }

    /** Classify a User-Agent into [device, browser, os]. */
    public static function parse_ua($ua)
    {
        $ua = (string) $ua;
        $device = 'desktop';
        if (preg_match('/iPad|Tablet|PlayBook|Silk/i', $ua) || (stripos($ua, 'Android') !== false && stripos($ua, 'Mobile') === false)) {
            $device = 'tablet';
        } elseif (preg_match('/Mobi|iPhone|iPod|Android.*Mobile|Windows Phone|BlackBerry/i', $ua)) {
            $device = 'mobile';
        }
        $browser = 'Other';
        foreach (array('Edg' => 'Edge', 'OPR' => 'Opera', 'SamsungBrowser' => 'Samsung', 'Chrome' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari', 'MSIE' => 'IE', 'Trident' => 'IE') as $n => $name) {
            if (stripos($ua, $n) !== false) {
                $browser = $name;
                break;
            }
        }
        $os = 'Other';
        foreach (array('Windows' => 'Windows', 'Mac OS' => 'macOS', 'iPhone' => 'iOS', 'iPad' => 'iPadOS', 'Android' => 'Android', 'CrOS' => 'ChromeOS', 'Linux' => 'Linux') as $n => $name) {
            if (stripos($ua, $n) !== false) {
                $os = $name;
                break;
            }
        }
        return array($device, $browser, $os);
    }

    /** Is this User-Agent a bot/crawler/monitor (skip it)? Empty UA is treated as a bot. */
    public static function is_bot($ua)
    {
        $ua = (string) $ua;
        if ($ua === '') {
            return true;
        }
        return (bool) preg_match('/bot|crawl|spider|slurp|mediapartners|facebookexternalhit|embedly|quora|pinterest\/|preview|scan|monitor|uptime|headless|lighthouse|pagespeed|gtmetrix|curl|wget|python|java\/|okhttp|go-http|phantom|puppeteer|playwright|semrush|ahrefs|mj12|dotbot|dataprovider|bytespider|petalbot|yandex|baidu|duckduck|applebot/i', $ua);
    }

    /** Normalize a referrer URL to its bare host (no www), or '' for none/internal. */
    public static function referer_host($ref)
    {
        $ref = (string) $ref;
        if ($ref === '') {
            return '';
        }
        $h = function_exists('wp_parse_url') ? wp_parse_url($ref, PHP_URL_HOST) : parse_url($ref, PHP_URL_HOST);
        return $h ? preg_replace('/^www\./', '', strtolower($h)) : '';
    }

    /** Best-effort 2-letter country from CDN/host geo headers (no external calls). */
    public static function country_from_server($server)
    {
        foreach (array('HTTP_CF_IPCOUNTRY', 'HTTP_X_GEO_COUNTRY', 'HTTP_X_COUNTRY_CODE', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY', 'GEOIP_COUNTRY_CODE') as $k) {
            if (!empty($server[$k])) {
                $c = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $server[$k]));
                if (strlen($c) === 2 && $c !== 'XX' && $c !== 'T1') {
                    return $c;
                }
            }
        }
        return '';
    }

    /** Split "/path?utm_source=x" → ['/path', ['source'=>x,'medium'=>...,'campaign'=>...]]. */
    public static function split_path($raw)
    {
        $raw = (string) $raw;
        $path = $raw;
        $utm  = array('source' => '', 'medium' => '', 'campaign' => '');
        $qpos = strpos($raw, '?');
        if ($qpos !== false) {
            $path = substr($raw, 0, $qpos);
            parse_str(substr($raw, $qpos + 1), $q);
            foreach (array('source', 'medium', 'campaign') as $k) {
                if (!empty($q['utm_' . $k])) {
                    $utm[$k] = substr((string) $q['utm_' . $k], 0, 100);
                }
            }
        }
        $path = '/' . ltrim(preg_replace('/[#].*$/', '', $path), '/');
        if ($path === '/') {
            // keep root as '/'
        }
        return array(substr($path, 0, 190), $utm);
    }

    /** Whitelist of event types the collector accepts — never store raw input. */
    public static function allowed_event_type($t)
    {
        $t = preg_replace('/[^a-z]/', '', strtolower((string) $t));
        return in_array($t, array('click', 'outbound', 'download', 'mailto', 'tel', 'form', 'custom'), true) ? $t : '';
    }

    /** Central gate: should this hit be recorded? Pure for harnessing. */
    public static function should_record($enabled, $respect_dnt, $dnt_on, $excluded_user, $ip_excluded, $is_bot)
    {
        if (!$enabled) {
            return false;
        }
        if ($respect_dnt && $dnt_on) {
            return false;
        }
        return !($excluded_user || $ip_excluded || $is_bot);
    }

    /* ------------------------------------------------------------ recording */

    private function current_salt()
    {
        $today = gmdate('Ymd');
        $store = get_option(self::OPT_SALT, array());
        if (!is_array($store) || (isset($store['day']) ? $store['day'] : '') !== $today || empty($store['salt'])) {
            $store = array('day' => $today, 'salt' => wp_generate_password(40, false, false));
            update_option(self::OPT_SALT, $store, false);
        }
        return $store['salt'];
    }

    private function client_ip($server)
    {
        // Used only to derive the daily hash — never stored.
        if (!empty($server['HTTP_CF_CONNECTING_IP'])) {
            return (string) $server['HTTP_CF_CONNECTING_IP'];
        }
        return isset($server['REMOTE_ADDR']) ? (string) $server['REMOTE_ADDR'] : '';
    }

    private function user_excluded()
    {
        if (!is_user_logged_in()) {
            return false;
        }
        if (!$this->track_admins && current_user_can('manage_options')) {
            return true;
        }
        if (empty($this->exclude_roles)) {
            return false;
        }
        $user = wp_get_current_user();
        foreach ((array) $user->roles as $r) {
            if (in_array($r, $this->exclude_roles, true)) {
                return true;
            }
        }
        return false;
    }

    /** Record a page hit from the beacon payload ({p:path,t:title,r:referrer}). */
    public function record($data)
    {
        if (!$this->enabled || !is_array($data)) {
            return false;
        }
        $server = $_SERVER;
        $ua  = isset($server['HTTP_USER_AGENT']) ? (string) $server['HTTP_USER_AGENT'] : '';
        $ip  = $this->client_ip($server);
        $dnt = isset($server['HTTP_DNT']) && $server['HTTP_DNT'] === '1';

        if (!self::should_record(
            $this->enabled,
            $this->respect_dnt,
            $dnt,
            $this->user_excluded(),
            in_array($ip, $this->exclude_ips, true),
            self::is_bot($ua)
        )) {
            return false;
        }

        list($path, $utm) = self::split_path(isset($data['p']) ? $data['p'] : '');
        if ($path === '') {
            return false;
        }
        $ref      = isset($data['r']) ? esc_url_raw((string) $data['r']) : '';
        $ref_host = self::referer_host($ref);
        // Drop self-referrals (internal navigation) from the referrer dimension.
        $home = self::referer_host(home_url('/'));
        if ($ref_host !== '' && $ref_host === $home) {
            $ref_host = '';
            $ref = '';
        }
        list($device, $browser, $os) = self::parse_ua($ua);

        $ts    = time();
        $salt  = $this->current_salt();

        global $wpdb;
        $wpdb->insert(
            $this->table(),
            array(
                'ts'           => $ts,
                'path'         => $path,
                'title'        => substr(sanitize_text_field(isset($data['t']) ? $data['t'] : ''), 0, 190),
                'ref_host'     => substr($ref_host, 0, 190),
                'ref'          => substr($ref, 0, 255),
                'utm_source'   => $utm['source'],
                'utm_medium'   => $utm['medium'],
                'utm_campaign' => $utm['campaign'],
                'country'      => self::country_from_server($server),
                'device'       => $device,
                'browser'      => $browser,
                'os'           => $os,
                'visitor'      => self::visitor_hash($salt, $ip, $ua),
                'session'      => self::session_hash($salt, $ip, $ua, $ts),
                'is_404'       => !empty($data['f']) ? 1 : 0,
                'search'       => isset($data['s']) ? substr(sanitize_text_field((string) $data['s']), 0, 190) : '',
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
        );
        return true;
    }

    /**
     * Record an interaction event from the beacon ({k:'e', e:type, n:name, p:path, u:target}).
     * Same privacy gate as a page hit — cookieless, no PII at rest.
     */
    public function record_event($data)
    {
        if (!$this->enabled || !is_array($data)) {
            return false;
        }
        $server = $_SERVER;
        $ua  = isset($server['HTTP_USER_AGENT']) ? (string) $server['HTTP_USER_AGENT'] : '';
        $ip  = $this->client_ip($server);
        $dnt = isset($server['HTTP_DNT']) && $server['HTTP_DNT'] === '1';

        if (!self::should_record(
            $this->enabled,
            $this->respect_dnt,
            $dnt,
            $this->user_excluded(),
            in_array($ip, $this->exclude_ips, true),
            self::is_bot($ua)
        )) {
            return false;
        }

        $etype = self::allowed_event_type(isset($data['e']) ? $data['e'] : '');
        if ($etype === '') {
            return false;
        }
        list($path) = self::split_path(isset($data['p']) ? $data['p'] : '');
        list($device) = self::parse_ua($ua);

        $ts   = time();
        $salt = $this->current_salt();

        global $wpdb;
        $wpdb->insert(
            $this->events_table(),
            array(
                'ts'      => $ts,
                'etype'   => $etype,
                'name'    => substr(sanitize_text_field(isset($data['n']) ? (string) $data['n'] : ''), 0, 190),
                'path'    => $path,
                'target'  => substr(sanitize_text_field(isset($data['u']) ? (string) $data['u'] : ''), 0, 255),
                'country' => self::country_from_server($server),
                'device'  => $device,
                'visitor' => self::visitor_hash($salt, $ip, $ua),
                'session' => self::session_hash($salt, $ip, $ua, $ts),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        return true;
    }

    /* ------------------------------------------------------------ reporting */

    /** Headline totals + a daily series for a [from,to] unix range. */
    public function overview($from, $to)
    {
        global $wpdb;
        $t = $this->table();
        $from = (int) $from;
        $to   = (int) $to;

        $views    = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE ts BETWEEN %d AND %d", $from, $to));
        $visitors = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT visitor) FROM {$t} WHERE ts BETWEEN %d AND %d", $from, $to));
        $sessions = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT session) FROM {$t} WHERE ts BETWEEN %d AND %d", $from, $to));
        $bounces  = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM (SELECT session FROM {$t} WHERE ts BETWEEN %d AND %d GROUP BY session HAVING COUNT(*) = 1) x",
            $from,
            $to
        ));
        $avg_dur  = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(d) FROM (SELECT MAX(ts) - MIN(ts) d FROM {$t} WHERE ts BETWEEN %d AND %d GROUP BY session) x",
            $from,
            $to
        ));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT FLOOR(ts/86400) d, COUNT(*) v, COUNT(DISTINCT visitor) u FROM {$t} WHERE ts BETWEEN %d AND %d GROUP BY d ORDER BY d ASC",
            $from,
            $to
        ), ARRAY_A);

        return array(
            'views'      => $views,
            'visitors'   => $visitors,
            'sessions'   => $sessions,
            'bounce'     => $sessions ? round(($bounces / $sessions) * 100, 1) : 0.0,
            'avg_dur'    => (int) round($avg_dur),
            'per_sess'   => $sessions ? round($views / $sessions, 2) : 0.0,
            'series'     => self::fill_series($rows, $from, $to),
        );
    }

    /** Turn sparse day-buckets into a continuous [{date,views,visitors}] series. */
    public static function fill_series($rows, $from, $to)
    {
        $map = array();
        foreach ((array) $rows as $r) {
            $map[(int) $r['d']] = array('v' => (int) $r['v'], 'u' => (int) $r['u']);
        }
        $out   = array();
        $start = (int) floor(((int) $from) / 86400);
        $end   = (int) floor(((int) $to) / 86400);
        // Cap at ~370 points so a huge range can't blow up the payload.
        if ($end - $start > 370) {
            $start = $end - 370;
        }
        for ($d = $start; $d <= $end; $d++) {
            $out[] = array(
                'date'     => gmdate('Y-m-d', $d * 86400),
                'views'    => isset($map[$d]) ? $map[$d]['v'] : 0,
                'visitors' => isset($map[$d]) ? $map[$d]['u'] : 0,
            );
        }
        return $out;
    }

    /** Top pages (by views) for a range. */
    public function pages($from, $to, $limit = 25)
    {
        global $wpdb;
        $t = $this->table();
        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT path, MAX(title) title, COUNT(*) views, COUNT(DISTINCT visitor) visitors
             FROM {$t} WHERE ts BETWEEN %d AND %d GROUP BY path ORDER BY views DESC LIMIT %d",
            (int) $from,
            (int) $to,
            (int) $limit
        ), ARRAY_A);
    }

    /** Top referrers (by views); '' (direct) excluded. */
    public function referrers($from, $to, $limit = 25)
    {
        global $wpdb;
        $t = $this->table();
        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT ref_host, COUNT(*) views, COUNT(DISTINCT visitor) visitors
             FROM {$t} WHERE ts BETWEEN %d AND %d AND ref_host <> '' GROUP BY ref_host ORDER BY views DESC LIMIT %d",
            (int) $from,
            (int) $to,
            (int) $limit
        ), ARRAY_A);
    }

    /** Whitelist of GROUP-BY columns for breakdown() — never interpolate raw input. */
    public static function allowed_dimension($col)
    {
        return in_array($col, array('country', 'device', 'browser', 'os'), true) ? $col : '';
    }

    /** Generic single-column breakdown → rows of [label, views, visitors]. */
    public function breakdown($col, $from, $to, $limit = 25)
    {
        $col = self::allowed_dimension($col);
        if ($col === '') {
            return array();
        }
        global $wpdb;
        $t = $this->table();
        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT {$col} label, COUNT(*) views, COUNT(DISTINCT visitor) visitors
             FROM {$t} WHERE ts BETWEEN %d AND %d AND {$col} <> '' GROUP BY {$col} ORDER BY views DESC LIMIT %d",
            (int) $from,
            (int) $to,
            (int) $limit
        ), ARRAY_A);
    }

    /** Entry (landing) pages — the first hit of each session. */
    public function landing($from, $to, $limit = 25)
    {
        global $wpdb;
        $t = $this->table();
        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT h.path label, COUNT(*) views, COUNT(DISTINCT h.visitor) visitors
             FROM {$t} h JOIN (SELECT session, MIN(ts) mt FROM {$t} WHERE ts BETWEEN %d AND %d GROUP BY session) f
               ON h.session = f.session AND h.ts = f.mt
             GROUP BY h.path ORDER BY views DESC LIMIT %d",
            (int) $from,
            (int) $to,
            (int) $limit
        ), ARRAY_A);
    }

    /** Exit pages — the last hit of each session. */
    public function exiting($from, $to, $limit = 25)
    {
        global $wpdb;
        $t = $this->table();
        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT h.path label, COUNT(*) views, COUNT(DISTINCT h.visitor) visitors
             FROM {$t} h JOIN (SELECT session, MAX(ts) mt FROM {$t} WHERE ts BETWEEN %d AND %d GROUP BY session) f
               ON h.session = f.session AND h.ts = f.mt
             GROUP BY h.path ORDER BY views DESC LIMIT %d",
            (int) $from,
            (int) $to,
            (int) $limit
        ), ARRAY_A);
    }

    /** 404 hits by path. */
    public function not_found($from, $to, $limit = 25)
    {
        global $wpdb;
        $t = $this->table();
        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT path label, COUNT(*) views, COUNT(DISTINCT visitor) visitors
             FROM {$t} WHERE ts BETWEEN %d AND %d AND is_404 = 1 GROUP BY path ORDER BY views DESC LIMIT %d",
            (int) $from,
            (int) $to,
            (int) $limit
        ), ARRAY_A);
    }

    /** On-site search terms. */
    public function searches($from, $to, $limit = 25)
    {
        global $wpdb;
        $t = $this->table();
        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT search label, COUNT(*) views, COUNT(DISTINCT visitor) visitors
             FROM {$t} WHERE ts BETWEEN %d AND %d AND search <> '' GROUP BY search ORDER BY views DESC LIMIT %d",
            (int) $from,
            (int) $to,
            (int) $limit
        ), ARRAY_A);
    }

    /** UTM campaign performance (rows: campaign, source, medium, views, visitors). */
    public function campaigns($from, $to, $limit = 50)
    {
        global $wpdb;
        $t = $this->table();
        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT utm_campaign, utm_source, utm_medium, COUNT(*) views, COUNT(DISTINCT visitor) visitors
             FROM {$t}
             WHERE ts BETWEEN %d AND %d AND (utm_campaign <> '' OR utm_source <> '' OR utm_medium <> '')
             GROUP BY utm_campaign, utm_source, utm_medium
             ORDER BY views DESC LIMIT %d",
            (int) $from,
            (int) $to,
            (int) $limit
        ), ARRAY_A);
    }

    /** Interaction events grouped by (type, name, target) for a range. */
    public function events($from, $to, $limit = 100)
    {
        global $wpdb;
        $t = $this->events_table();
        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT etype, name, target, COUNT(*) count, COUNT(DISTINCT visitor) visitors
             FROM {$t} WHERE ts BETWEEN %d AND %d
             GROUP BY etype, name, target ORDER BY count DESC LIMIT %d",
            (int) $from,
            (int) $to,
            (int) $limit
        ), ARRAY_A);
    }

    /** Event totals grouped by type (for the summary chips). */
    public function events_by_type($from, $to)
    {
        global $wpdb;
        $t = $this->events_table();
        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT etype, COUNT(*) count, COUNT(DISTINCT visitor) visitors
             FROM {$t} WHERE ts BETWEEN %d AND %d
             GROUP BY etype ORDER BY count DESC",
            (int) $from,
            (int) $to
        ), ARRAY_A);
    }

    /** Total events recorded in a range. */
    public function events_total($from, $to)
    {
        global $wpdb;
        $t = $this->events_table();
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE ts BETWEEN %d AND %d", (int) $from, (int) $to));
    }

    /** Active visitors in the last N minutes (distinct visitor). */
    public function realtime_active($minutes = 5)
    {
        global $wpdb;
        $t   = $this->table();
        $cut = time() - max(1, (int) $minutes) * MINUTE_IN_SECONDS;
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT visitor) FROM {$t} WHERE ts >= %d", $cut));
    }

    /** Most recent hits (for the live view). */
    public function realtime_recent($minutes = 30, $limit = 25)
    {
        global $wpdb;
        $t   = $this->table();
        $cut = time() - max(1, (int) $minutes) * MINUTE_IN_SECONDS;
        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT ts, path, ref_host, country, device, browser FROM {$t} WHERE ts >= %d ORDER BY ts DESC LIMIT %d",
            $cut,
            (int) $limit
        ), ARRAY_A);
    }

    /** Per-minute active-visitor sparkline for the last N minutes. */
    public function realtime_series($minutes = 30)
    {
        global $wpdb;
        $t   = $this->table();
        $cut = time() - max(1, (int) $minutes) * MINUTE_IN_SECONDS;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT FLOOR(ts/60) m, COUNT(*) v FROM {$t} WHERE ts >= %d GROUP BY m ORDER BY m ASC",
            $cut
        ), ARRAY_A);
        $map = array();
        foreach ((array) $rows as $r) {
            $map[(int) $r['m']] = (int) $r['v'];
        }
        $out   = array();
        $start = (int) floor($cut / 60);
        $end   = (int) floor(time() / 60);
        for ($m = $start; $m <= $end; $m++) {
            $out[] = isset($map[$m]) ? $map[$m] : 0;
        }
        return $out;
    }

    /** Lightweight today's counts for the admin-bar peek (60s transient-cached). */
    public function quick_today()
    {
        $cached = get_transient('wp_arzo_analytics_today');
        if (is_array($cached)) {
            return $cached;
        }
        global $wpdb;
        $t    = $this->table();
        $from = strtotime(gmdate('Y-m-d 00:00:00'));
        $out  = array(
            'views'    => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE ts >= %d", $from)),
            'visitors' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT visitor) FROM {$t} WHERE ts >= %d", $from)),
        );
        set_transient('wp_arzo_analytics_today', $out, MINUTE_IN_SECONDS);
        return $out;
    }

    /** Views for a single path (per-post view counter). */
    public function views_for_path($path)
    {
        global $wpdb;
        $t = $this->table();
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE path = %s", (string) $path));
    }

    /** Daily retention prune (hits + events). */
    public function prune()
    {
        if ($this->retention_days <= 0) {
            return 0;
        }
        global $wpdb;
        $cutoff  = time() - ($this->retention_days * DAY_IN_SECONDS);
        $deleted = (int) $wpdb->query($wpdb->prepare("DELETE FROM {$this->table()} WHERE ts < %d", $cutoff));
        $deleted += (int) $wpdb->query($wpdb->prepare("DELETE FROM {$this->events_table()} WHERE ts < %d", $cutoff));
        return $deleted;
    }

    /* ------------------------------------------------------------ collector */

    public function register_rest()
    {
        register_rest_route('wp-arzo/v1', '/hit', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'rest_hit'),
            'permission_callback' => '__return_true',
        ));
    }

    public function rest_hit($request)
    {
        // sendBeacon posts text/plain — read the raw body ourselves.
        $data = json_decode($request->get_body(), true);
        if (is_array($data)) {
            if (isset($data['k']) && $data['k'] === 'e') {
                $this->record_event($data);
            } else {
                $this->record($data);
            }
        }
        // Always 204; never leak whether the hit was counted.
        return new WP_REST_Response(null, 204);
    }
}

/* ============================================================== Feature */

class WP_Arzo_Feature_Analytics extends WP_Arzo_Feature
{
    public function id()
    {
        return 'analytics';
    }
    public function title()
    {
        return 'Analytics';
    }
    public function description()
    {
        return 'Built-in, cookieless, privacy-first website analytics — pageviews, visitors, sessions, bounce rate, top pages and referrers — recorded entirely in your own database with no cookies and no external services (view under WP Arzo → Analytics).';
    }
    public function group()
    {
        return 'analytics';
    }
    public function tier()
    {
        return 'free';
    }
    public function icon()
    {
        return 'chart';
    }

    public function settings_schema()
    {
        return array(
            array('key' => 'respect_dnt', 'type' => 'toggle', 'label' => 'Respect “Do Not Track”', 'help' => 'Skip visitors whose browser sends a Do-Not-Track signal.', 'default' => true),
            array('key' => 'track_admins', 'type' => 'toggle', 'label' => 'Count logged-in admins', 'help' => 'Off by default so your own visits don’t inflate the numbers.', 'default' => false),
            array('key' => 'exclude_roles', 'type' => 'text', 'label' => 'Also ignore roles (comma-separated)', 'help' => 'e.g. editor, shop_manager — these logged-in roles are never counted.', 'default' => ''),
            array('key' => 'exclude_ips', 'type' => 'text', 'label' => 'Ignore IP addresses (comma-separated)', 'help' => 'Visits from these IPs are never counted (e.g. your office).', 'default' => ''),
            array('key' => 'retention_days', 'type' => 'number', 'label' => 'Keep data for (days)', 'help' => 'Hits older than this are pruned daily. 0 = keep forever.', 'default' => 365, 'min' => 0, 'max' => 3650),
        );
    }

    private function csv_to_array($v)
    {
        $v = (string) $v;
        if ($v === '') {
            return array();
        }
        return array_values(array_filter(array_map('trim', explode(',', $v))));
    }

    public function boot()
    {
        $engine = WP_Arzo_Analytics::instance();
        $engine->configure(array(
            'enabled'        => true,
            'respect_dnt'    => (bool) $this->get_setting('respect_dnt', true),
            'track_admins'   => (bool) $this->get_setting('track_admins', false),
            'retention_days' => (int) $this->get_setting('retention_days', 365),
            'exclude_roles'  => $this->csv_to_array($this->get_setting('exclude_roles', '')),
            'exclude_ips'    => $this->csv_to_array($this->get_setting('exclude_ips', '')),
        ));
        $engine->maybe_install();

        add_action('rest_api_init', array($engine, 'register_rest'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_beacon'));

        add_action(WP_Arzo_Analytics::CRON_PRUNE, array($engine, 'prune'));
        if (!wp_next_scheduled(WP_Arzo_Analytics::CRON_PRUNE)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', WP_Arzo_Analytics::CRON_PRUNE);
        }
        add_action('wp_arzo_feature_disabled', array($this, 'on_disabled'));

        // Surfacing: wp-admin dashboard widget + a Views column on post-type list tables.
        if (is_admin()) {
            add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
            add_action('load-edit.php', array($this, 'setup_post_columns'));
        }
        // Admin-bar "today" peek (front-end + admin, for admins).
        add_action('admin_bar_menu', array($this, 'admin_bar_today'), 80);
    }

    public function admin_bar_today($bar)
    {
        if (!current_user_can('manage_options') || !is_object($bar)) {
            return;
        }
        $c = WP_Arzo_Analytics::instance()->quick_today();
        $bar->add_node(array(
            'id'    => 'wp-arzo-analytics',
            'title' => '<span class="ab-icon dashicons dashicons-chart-bar" style="top:2px;"></span>' . esc_html(number_format_i18n($c['views'])) . ' today',
            'href'  => admin_url('admin.php?page=wp-arzo-analytics'),
            'meta'  => array('title' => sprintf('WP Arzo Analytics — %s views · %s visitors today', number_format_i18n($c['views']), number_format_i18n($c['visitors']))),
        ));
        $bar->add_node(array(
            'parent' => 'wp-arzo-analytics',
            'id'     => 'wp-arzo-analytics-visitors',
            'title'  => esc_html(number_format_i18n($c['visitors'])) . ' unique visitors today',
            'href'   => admin_url('admin.php?page=wp-arzo-analytics'),
        ));
    }

    public function add_dashboard_widget()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        wp_add_dashboard_widget('wp_arzo_analytics_widget', 'Analytics — last 7 days', array($this, 'render_dashboard_widget'));
    }

    public function render_dashboard_widget()
    {
        $engine = WP_Arzo_Analytics::instance();
        $to     = time();
        $from   = $to - 7 * DAY_IN_SECONDS;
        $o      = $engine->overview($from, $to);
        $pages  = $engine->pages($from, $to, 5);
        $url    = admin_url('admin.php?page=wp-arzo-analytics');

        $tiles = array(
            array('Views', number_format_i18n($o['views'])),
            array('Visitors', number_format_i18n($o['visitors'])),
            array('Sessions', number_format_i18n($o['sessions'])),
            array('Bounce', $o['bounce'] . '%'),
        );
        echo '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:12px;">';
        foreach ($tiles as $t) {
            echo '<div style="text-align:center;padding:8px;background:var(--arzo-bg-elev,#f6f7f7);border-radius:8px;">'
                . '<strong style="display:block;font-size:1.3rem;">' . esc_html($t[1]) . '</strong>'
                . '<span style="color:var(--arzo-text-muted,#646970);font-size:.8rem;">' . esc_html($t[0]) . '</span></div>';
        }
        echo '</div>';
        if (!empty($pages)) {
            echo '<table class="widefat striped" style="margin-bottom:10px;"><thead><tr><th>Top page</th><th style="text-align:right;">Views</th></tr></thead><tbody>';
            foreach ($pages as $p) {
                echo '<tr><td>' . esc_html($p['path']) . '</td><td style="text-align:right;">' . esc_html(number_format_i18n($p['views'])) . '</td></tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p style="color:#646970;">No visits recorded in the last 7 days yet.</p>';
        }
        echo '<p style="margin:0;"><a class="button button-secondary" href="' . esc_url($url) . '">View full analytics →</a></p>';
    }

    public function setup_post_columns()
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || empty($screen->post_type)) {
            return;
        }
        $pt = $screen->post_type;
        add_filter("manage_edit-{$pt}_columns", array($this, 'views_column'));
        add_action("manage_{$pt}_posts_custom_column", array($this, 'views_column_content'), 10, 2);
    }

    public function views_column($cols)
    {
        $cols['wp_arzo_views'] = __('Views', 'wp-arzo');
        return $cols;
    }

    public function views_column_content($col, $post_id)
    {
        if ($col !== 'wp_arzo_views') {
            return;
        }
        $link = get_permalink($post_id);
        if (!$link) {
            echo '—';
            return;
        }
        $path = wp_make_link_relative($link);
        $views = WP_Arzo_Analytics::instance()->views_for_path($path);
        echo $views ? esc_html(number_format_i18n($views)) : '<span style="color:#a7aaad;">0</span>';
    }

    public function enqueue_beacon()
    {
        if (is_admin() || is_preview() || is_customize_preview() || is_feed()) {
            return;
        }
        wp_enqueue_script(
            'wp-arzo-analytics',
            wp_arzo_get_asset_url('assets/js/wp-arzo-analytics.js'),
            array(),
            null,
            true
        );
        wp_localize_script('wp-arzo-analytics', 'wpArzoAnalytics', array(
            'endpoint' => esc_url_raw(rest_url('wp-arzo/v1/hit')),
            'is404'    => is_404() ? 1 : 0,
            'search'   => is_search() ? (string) get_search_query() : '',
            /**
             * Interaction-event tracking rules for the beacon. Empty by default —
             * the beacon attaches no extra listeners (zero overhead). WP Arzo Pro
             * (Analytics Pro) populates this via the filter when event tracking is on.
             *
             * @param array $rules ['outbound'=>0|1,'downloads'=>0|1,'email'=>0|1,
             *                       'forms'=>0|1,'exts'=>'pdf,zip,…','selectors'=>[{sel,name,type}]]
             */
            'events'   => (array) apply_filters('wp_arzo_analytics_event_rules', array()),
        ));
    }

    public function on_disabled($id)
    {
        if ($id === $this->id()) {
            wp_clear_scheduled_hook(WP_Arzo_Analytics::CRON_PRUNE);
        }
    }
}
