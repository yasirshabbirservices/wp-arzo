<?php

/**
 * WP Arzo Code Snippets manager.
 *
 * Stores snippets (PHP / CSS / JS / HTML) and runs the active ones in the right
 * scope. PHP snippets run inside a guard: a syntax/runtime error (caught as a
 * Throwable) OR an uncatchable fatal (caught at shutdown) auto-deactivates the
 * offending snippet, so a bad snippet can never permanently break the site.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Snippets
{
    const OPTION = 'wp_arzo_snippets';

    /** Shortcode that renders a snippet on the front end: [wp_arzo_snippet id="…"]. */
    const SHORTCODE_TAG = 'wp_arzo_snippet';

    /** @var WP_Arzo_Snippets|null */
    private static $instance = null;

    /** Id of the PHP snippet currently executing (for the shutdown fatal guard). */
    private $current_php_id = null;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /* ----------------------------------------------------------- Storage */

    /** @return array[] */
    public function get_all()
    {
        $all = get_option(self::OPTION, array());
        return is_array($all) ? $all : array();
    }

    public function get($id)
    {
        foreach ($this->get_all() as $snippet) {
            if (isset($snippet['id']) && $snippet['id'] === $id) {
                return $snippet;
            }
        }
        return null;
    }

    private function put_all($list)
    {
        update_option(self::OPTION, array_values($list), false);
    }

    /**
     * Create or update a snippet. Returns the snippet id.
     */
    public function save($data)
    {
        $types  = array('php', 'css', 'js', 'html');
        $scopes = array('everywhere', 'admin', 'front', 'shortcode');

        $snippet = array(
            'id'          => (isset($data['id']) && $data['id'] !== '') ? preg_replace('/[^a-z0-9_]/', '', $data['id']) : $this->generate_id(),
            'title'       => sanitize_text_field(isset($data['title']) ? $data['title'] : 'Untitled'),
            'description' => sanitize_text_field(isset($data['description']) ? $data['description'] : ''),
            'type'        => in_array(($data['type'] ?? ''), $types, true) ? $data['type'] : 'php',
            'scope'       => in_array(($data['scope'] ?? ''), $scopes, true) ? $data['scope'] : 'everywhere',
            'priority'    => isset($data['priority']) ? max(1, min(9999, (int) $data['priority'])) : 10,
            'code'        => isset($data['code']) ? (string) $data['code'] : '',
            'active'      => !empty($data['active']) ? 1 : 0,
            // Smart conditional logic: run only when these rules pass (empty = everywhere).
            'cond_mode'   => (isset($data['cond_mode']) && $data['cond_mode'] === 'any') ? 'any' : 'all',
            'conditions'  => $this->sanitize_conditions(isset($data['conditions']) ? $data['conditions'] : array()),
        );

        $list   = $this->get_all();
        $found  = false;
        foreach ($list as $i => $existing) {
            if ($existing['id'] === $snippet['id']) {
                // Preserve a recorded error unless re-activating.
                $snippet['last_error'] = $snippet['active'] ? '' : (isset($existing['last_error']) ? $existing['last_error'] : '');
                $list[$i] = $snippet;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $list[] = $snippet;
        }
        $this->put_all($list);
        return $snippet['id'];
    }

    public function delete($id)
    {
        $list = array_filter($this->get_all(), function ($s) use ($id) {
            return $s['id'] !== $id;
        });
        $this->put_all($list);
        return true;
    }

    public function set_active($id, $active)
    {
        $list = $this->get_all();
        foreach ($list as $i => $s) {
            if ($s['id'] === $id) {
                $list[$i]['active'] = $active ? 1 : 0;
                if ($active) {
                    $list[$i]['last_error'] = '';
                }
                $this->put_all($list);
                return true;
            }
        }
        return false;
    }

    private function generate_id()
    {
        return 'snp_' . substr(md5(uniqid('', true)), 0, 12);
    }

    private function deactivate_with_error($id, $message)
    {
        $list = $this->get_all();
        foreach ($list as $i => $s) {
            if ($s['id'] === $id) {
                $list[$i]['active'] = 0;
                $list[$i]['last_error'] = (string) $message;
                $this->put_all($list);
                break;
            }
        }
    }

    /* ------------------------------------------------ Conditional logic */

    /**
     * The condition schema — types, their operators, and (for enum types) their
     * allowed values. Drives BOTH the builder UI and server-side sanitization, so
     * they can never drift. Query-dependent types are flagged `query`.
     *
     * @return array<string,array>
     */
    public static function condition_schema()
    {
        $is = array('is' => 'is', 'is_not' => 'is not');

        $roles = array();
        if (function_exists('wp_roles')) {
            foreach (wp_roles()->get_names() as $slug => $name) {
                $roles[$slug] = $name;
            }
        }

        $post_types = array();
        if (function_exists('get_post_types')) {
            foreach (get_post_types(array('public' => true), 'objects') as $pt) {
                $post_types[$pt->name] = $pt->labels->singular_name ? $pt->labels->singular_name : $pt->name;
            }
        }

        return array(
            'login_status' => array('label' => 'User login', 'ops' => $is, 'values' => array('logged_in' => 'Logged in', 'logged_out' => 'Logged out')),
            'user_role'    => array('label' => 'User role', 'ops' => $is, 'values' => $roles),
            'post_type'    => array('label' => 'Post type', 'ops' => $is, 'values' => $post_types, 'query' => true),
            'page_type'    => array('label' => 'Page type', 'ops' => $is, 'values' => array(
                'front_page' => 'Front page', 'blog_home' => 'Blog posts index', 'singular' => 'Any single post/page',
                'page' => 'Static page', 'archive' => 'Any archive', 'search' => 'Search results', 'notfound' => '404 page',
            ), 'query' => true),
            'url_path'     => array('label' => 'URL path', 'ops' => array('is' => 'is', 'is_not' => 'is not', 'contains' => 'contains', 'starts' => 'starts with', 'regex' => 'matches regex'), 'values' => null),
            'device'       => array('label' => 'Device', 'ops' => $is, 'values' => array('desktop' => 'Desktop', 'mobile' => 'Mobile')),
            'schedule'     => array('label' => 'Date & time', 'ops' => array('after' => 'on/after', 'before' => 'before', 'between' => 'between'), 'values' => 'datetime'),
        );
    }

    /** Validate/normalize a raw conditions array against the schema. */
    private function sanitize_conditions($raw)
    {
        if (!is_array($raw)) {
            return array();
        }
        $schema = self::condition_schema();
        $out    = array();
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $type = isset($row['type']) ? (string) $row['type'] : '';
            if (!isset($schema[$type])) {
                continue;
            }
            $spec = $schema[$type];
            $op   = isset($row['op']) ? (string) $row['op'] : '';
            if (!isset($spec['ops'][$op])) {
                $op = array_key_first($spec['ops']);
            }
            $val  = isset($row['value']) ? sanitize_text_field((string) $row['value']) : '';
            // Enum types must carry an allowed value; free-text/datetime pass through sanitized.
            if (is_array($spec['values']) && !isset($spec['values'][$val])) {
                continue; // invalid enum selection — drop the rule
            }
            $clean = array('type' => $type, 'op' => $op, 'value' => $val);
            if ($type === 'schedule' && $op === 'between') {
                $clean['value2'] = isset($row['value2']) ? sanitize_text_field((string) $row['value2']) : '';
            }
            $out[] = $clean;
        }
        return $out;
    }

    /** True if any of the snippet's conditions needs the main WP query (post/page type). */
    private function needs_query($snippet)
    {
        $schema = self::condition_schema();
        foreach ((isset($snippet['conditions']) && is_array($snippet['conditions']) ? $snippet['conditions'] : array()) as $c) {
            if (!empty($schema[$c['type']]['query'])) {
                return true;
            }
        }
        return false;
    }

    /** Evaluate a snippet's full condition set (empty = always true). */
    private function passes_conditions($snippet)
    {
        $conds = (isset($snippet['conditions']) && is_array($snippet['conditions'])) ? $snippet['conditions'] : array();
        if (empty($conds)) {
            return true;
        }
        $any = (isset($snippet['cond_mode']) && $snippet['cond_mode'] === 'any');
        foreach ($conds as $c) {
            $pass = $this->eval_condition($c);
            if ($any && $pass) {
                return true;   // match-any: first hit wins
            }
            if (!$any && !$pass) {
                return false;  // match-all: first miss fails
            }
        }
        return !$any; // all-passed (all) OR none-passed (any)
    }

    private function eval_condition($c)
    {
        $type = isset($c['type']) ? $c['type'] : '';
        $op   = isset($c['op']) ? $c['op'] : 'is';
        $val  = isset($c['value']) ? $c['value'] : '';
        $not  = ($op === 'is_not');

        switch ($type) {
            case 'login_status':
                $now = is_user_logged_in() ? 'logged_in' : 'logged_out';
                return $not ? ($now !== $val) : ($now === $val);
            case 'user_role':
                $has = in_array($val, (array) wp_get_current_user()->roles, true);
                return $not ? !$has : $has;
            case 'device':
                $now = wp_is_mobile() ? 'mobile' : 'desktop';
                return $not ? ($now !== $val) : ($now === $val);
            case 'post_type':
                $now = is_singular() ? get_post_type() : '';
                return $not ? ($now !== $val) : ($now === $val);
            case 'page_type':
                $match = $this->is_page_type($val);
                return $not ? !$match : $match;
            case 'url_path':
                $path = (string) strtok(isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '', '?');
                return $this->cmp_string($path, $val, $op);
            case 'schedule':
                return $this->eval_schedule($op, $val, isset($c['value2']) ? $c['value2'] : '');
        }
        return true;
    }

    private function is_page_type($which)
    {
        switch ($which) {
            case 'front_page': return is_front_page();
            case 'blog_home':  return is_home();
            case 'singular':   return is_singular();
            case 'page':       return is_page();
            case 'archive':    return is_archive();
            case 'search':     return is_search();
            case 'notfound':   return is_404();
        }
        return false;
    }

    private function cmp_string($subject, $needle, $op)
    {
        switch ($op) {
            case 'is':       return $subject === $needle;
            case 'is_not':   return $subject !== $needle;
            case 'contains': return $needle !== '' && strpos($subject, $needle) !== false;
            case 'starts':   return $needle !== '' && strpos($subject, $needle) === 0;
            case 'regex':
                if ($needle === '') return false;
                $pattern = '~' . str_replace('~', '\~', $needle) . '~';
                return @preg_match($pattern, $subject) === 1;
        }
        return false;
    }

    private function eval_schedule($op, $from, $to)
    {
        $now = current_time('timestamp');
        $a   = $from !== '' ? strtotime($from) : false;
        $b   = $to !== '' ? strtotime($to) : false;
        switch ($op) {
            case 'after':   return $a !== false && $now >= $a;
            case 'before':  return $a !== false && $now < $a;
            case 'between': return $a !== false && $b !== false && $now >= $a && $now <= $b;
        }
        return true;
    }

    /* --------------------------------------------------------- Execution */

    /** Called by the Code Snippets feature when it is enabled. */
    public function boot()
    {
        register_shutdown_function(array($this, 'shutdown_guard'));

        // Any snippet is embeddable via [wp_arzo_snippet id="…"]; a snippet whose scope is
        // "shortcode" ONLY renders where this shortcode is placed (it never auto-runs).
        add_shortcode(self::SHORTCODE_TAG, array($this, 'do_shortcode'));

        // Run/register snippets in ascending priority order (lower runs first).
        $snippets = $this->get_all();
        usort($snippets, function ($a, $b) {
            $pa = isset($a['priority']) ? (int) $a['priority'] : 10;
            $pb = isset($b['priority']) ? (int) $b['priority'] : 10;
            return $pa <=> $pb;
        });

        foreach ($snippets as $snippet) {
            if (empty($snippet['active']) || !$this->in_scope($snippet['scope'])) {
                continue;
            }
            if ($snippet['type'] === 'php') {
                // Page/post-type conditions need the main query. If a snippet uses one
                // on the front end, defer its run to `wp` (query ready); otherwise the
                // query-independent conditions evaluate fine right here at boot.
                if ($this->needs_query($snippet) && !is_admin()) {
                    add_action('wp', function () use ($snippet) {
                        if ($this->passes_conditions($snippet)) {
                            $this->run_php($snippet);
                        }
                    }, 1);
                } elseif ($this->passes_conditions($snippet)) {
                    $this->run_php($snippet);
                }
            } else {
                // CSS/JS/HTML render on wp_head/footer (after `wp`), so the closure can
                // evaluate ALL conditions — including page/post-type — at output time.
                $this->register_output($snippet);
            }
        }
    }

    private function in_scope($scope)
    {
        if ($scope === 'shortcode') {
            return false; // shortcode-only: never auto-runs — renders where its shortcode is placed.
        }
        if ($scope === 'everywhere') {
            return true;
        }
        return ($scope === 'admin') ? is_admin() : !is_admin();
    }

    private function run_php($snippet)
    {
        $code = preg_replace('/^\s*<\?(php)?/i', '', (string) $snippet['code']);
        $code = preg_replace('/\?>\s*$/', '', $code);

        $this->current_php_id = $snippet['id'];
        try {
            eval($code);
        } catch (\Throwable $e) {
            $this->deactivate_with_error($snippet['id'], $e->getMessage());
        }
        $this->current_php_id = null;
    }

    /**
     * Run a PHP snippet's code on demand — e.g. from a scheduled Cron "snippet job".
     * Runs regardless of the snippet's active state (a snippet can be scheduled-only),
     * with the same Throwable + shutdown-fatal guard as automatic execution. Does NOT
     * evaluate the display conditions (a scheduled run has no request context).
     *
     * @return array{ok:bool,message:string}
     */
    public function run_by_id($id)
    {
        $snippet = $this->get($id);
        if (!$snippet) {
            return array('ok' => false, 'message' => 'Snippet not found.');
        }
        if ((isset($snippet['type']) ? $snippet['type'] : 'php') !== 'php') {
            return array('ok' => false, 'message' => 'Only PHP snippets can run on a schedule.');
        }
        $code = preg_replace('/^\s*<\?(php)?/i', '', (string) $snippet['code']);
        $code = preg_replace('/\?>\s*$/', '', $code);

        $prev = $this->current_php_id;
        $this->current_php_id = $snippet['id']; // shutdown guard covers uncatchable fatals
        try {
            eval($code);
            $result = array('ok' => true, 'message' => 'Ran OK');
        } catch (\Throwable $e) {
            $result = array('ok' => false, 'message' => $e->getMessage());
        }
        $this->current_php_id = $prev;
        return $result;
    }

    /**
     * Render a snippet where its [wp_arzo_snippet id="…"] shortcode is placed.
     * HTML outputs as-is; CSS/JS are wrapped in a tag; PHP runs with output buffering
     * (its echo/return becomes the shortcode output, with $atts + $content available,
     * mirroring core shortcodes). Only an active snippet whose conditions pass renders.
     *
     * @param array|string $atts
     * @param string|null  $content
     * @return string
     */
    public function do_shortcode($atts, $content = null)
    {
        $atts = is_array($atts) ? $atts : array();
        $id   = preg_replace('/[^a-z0-9_]/', '', isset($atts['id']) ? (string) $atts['id'] : '');
        if ($id === '') {
            return '';
        }
        $snippet = $this->get($id);
        if (!$snippet || empty($snippet['active']) || !$this->passes_conditions($snippet)) {
            return '';
        }

        $type = isset($snippet['type']) ? $snippet['type'] : 'php';
        if ($type === 'php') {
            $code = preg_replace('/^\s*<\?(php)?/i', '', (string) $snippet['code']);
            $code = preg_replace('/\?>\s*$/', '', $code);
            $prev = $this->current_php_id;
            $this->current_php_id = $snippet['id']; // shutdown guard covers uncatchable fatals
            ob_start();
            try {
                eval($code);
            } catch (\Throwable $e) {
                ob_end_clean();
                $this->deactivate_with_error($snippet['id'], $e->getMessage());
                $this->current_php_id = $prev;
                return '';
            }
            $this->current_php_id = $prev;
            return (string) ob_get_clean();
        }
        if ($type === 'css') {
            return "<style id='wpa-snippet-" . esc_attr($snippet['id']) . "'>" . wp_strip_all_tags($snippet['code']) . "</style>";
        }
        if ($type === 'js') {
            return "<script id='wpa-snippet-" . esc_attr($snippet['id']) . "'>" . $snippet['code'] . "</script>";
        }
        return (string) $snippet['code']; // html
    }

    /** Backstop for uncatchable fatals (memory, timeouts) during a PHP snippet. */
    public function shutdown_guard()
    {
        if ($this->current_php_id === null) {
            return;
        }
        $err = error_get_last();
        $fatal = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
        if ($err && in_array($err['type'], $fatal, true)) {
            $this->deactivate_with_error($this->current_php_id, $err['message']);
        }
    }

    private function register_output($snippet)
    {
        $front = ($snippet['scope'] !== 'admin');
        $admin = ($snippet['scope'] !== 'front');
        // Snippet priority also drives the hook order, so a lower number renders earlier.
        $prio  = isset($snippet['priority']) ? (int) $snippet['priority'] : 10;

        if ($snippet['type'] === 'css') {
            if ($front) {
                add_action('wp_head', function () use ($snippet) {
                    if (!$this->passes_conditions($snippet)) return;
                    // Admin-authored (manage_options) snippet CSS; tags stripped so it can't break out of <style>.
                    echo "\n<style id='wpa-snippet-" . esc_attr($snippet['id']) . "'>" . wp_strip_all_tags($snippet['code']) . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }, $prio);
            }
            if ($admin) {
                add_action('admin_head', function () use ($snippet) {
                    if (!$this->passes_conditions($snippet)) return;
                    // Admin-authored (manage_options) snippet CSS; tags stripped so it can't break out of <style>.
                    echo "\n<style id='wpa-snippet-" . esc_attr($snippet['id']) . "'>" . wp_strip_all_tags($snippet['code']) . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }, $prio);
            }
        } elseif ($snippet['type'] === 'js') {
            if ($front) {
                add_action('wp_footer', function () use ($snippet) {
                    if (!$this->passes_conditions($snippet)) return;
                    // Admin-authored (manage_options) JS snippet; output verbatim by design (Code-Snippets pattern).
                    echo "\n<script id='wpa-snippet-" . esc_attr($snippet['id']) . "'>" . $snippet['code'] . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }, $prio);
            }
            if ($admin) {
                add_action('admin_footer', function () use ($snippet) {
                    if (!$this->passes_conditions($snippet)) return;
                    // Admin-authored (manage_options) JS snippet; output verbatim by design (Code-Snippets pattern).
                    echo "\n<script id='wpa-snippet-" . esc_attr($snippet['id']) . "'>" . $snippet['code'] . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }, $prio);
            }
        } else { // html
            if ($front) {
                add_action('wp_footer', function () use ($snippet) {
                    if (!$this->passes_conditions($snippet)) return;
                    // Admin-authored (manage_options) HTML snippet; output verbatim by design.
                    echo "\n" . $snippet['code'] . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }, $prio);
            }
        }
    }
}
