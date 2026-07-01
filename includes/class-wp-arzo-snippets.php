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
        $scopes = array('everywhere', 'admin', 'front');

        $snippet = array(
            'id'          => (isset($data['id']) && $data['id'] !== '') ? preg_replace('/[^a-z0-9_]/', '', $data['id']) : $this->generate_id(),
            'title'       => sanitize_text_field(isset($data['title']) ? $data['title'] : 'Untitled'),
            'description' => sanitize_text_field(isset($data['description']) ? $data['description'] : ''),
            'type'        => in_array(($data['type'] ?? ''), $types, true) ? $data['type'] : 'php',
            'scope'       => in_array(($data['scope'] ?? ''), $scopes, true) ? $data['scope'] : 'everywhere',
            'priority'    => isset($data['priority']) ? max(1, min(9999, (int) $data['priority'])) : 10,
            'code'        => isset($data['code']) ? (string) $data['code'] : '',
            'active'      => !empty($data['active']) ? 1 : 0,
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

    /* --------------------------------------------------------- Execution */

    /** Called by the Code Snippets feature when it is enabled. */
    public function boot()
    {
        register_shutdown_function(array($this, 'shutdown_guard'));

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
                $this->run_php($snippet);
            } else {
                $this->register_output($snippet);
            }
        }
    }

    private function in_scope($scope)
    {
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
                    echo "\n<style id='wpa-snippet-" . esc_attr($snippet['id']) . "'>" . wp_strip_all_tags($snippet['code']) . "</style>\n";
                }, $prio);
            }
            if ($admin) {
                add_action('admin_head', function () use ($snippet) {
                    echo "\n<style id='wpa-snippet-" . esc_attr($snippet['id']) . "'>" . wp_strip_all_tags($snippet['code']) . "</style>\n";
                }, $prio);
            }
        } elseif ($snippet['type'] === 'js') {
            if ($front) {
                add_action('wp_footer', function () use ($snippet) {
                    echo "\n<script id='wpa-snippet-" . esc_attr($snippet['id']) . "'>" . $snippet['code'] . "</script>\n";
                }, $prio);
            }
            if ($admin) {
                add_action('admin_footer', function () use ($snippet) {
                    echo "\n<script id='wpa-snippet-" . esc_attr($snippet['id']) . "'>" . $snippet['code'] . "</script>\n";
                }, $prio);
            }
        } else { // html
            if ($front) {
                add_action('wp_footer', function () use ($snippet) {
                    echo "\n" . $snippet['code'] . "\n";
                }, $prio);
            }
        }
    }
}
