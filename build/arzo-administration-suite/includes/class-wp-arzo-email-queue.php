<?php

/**
 * WP Arzo — Email retry queue.
 *
 * When the whole connection chain fails (`wp_arzo_email_all_failed`), the message is
 * queued and re-attempted on a schedule with exponential backoff. Transient failures
 * (SMTP timeouts, provider rate limits, brief outages) get a second/third chance
 * instead of the email being silently lost. Gives up after MAX_ATTEMPTS and prunes
 * given-up items after RETAIN_DAYS.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Email_Queue
{
    const OPTION       = 'wp_arzo_email_queue';
    const CRON         = 'wp_arzo_email_retry';
    const INTERVAL     = 'wp_arzo_5min';
    const MAX_ATTEMPTS = 4;   // give up after this many re-tries
    const MAX_PER_RUN  = 20;  // process at most N due items per cron tick
    const RETAIN_DAYS  = 7;   // drop given-up items older than this

    /** @var WP_Arzo_Email_Queue|null */
    private static $instance = null;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Backoff (seconds) before attempt N (1-indexed): 5m · 15m · 1h · 6h. */
    public static function backoff($attempt)
    {
        $steps = array(300, 900, 3600, 21600);
        $i = max(0, (int) $attempt - 1);
        return isset($steps[$i]) ? $steps[$i] : end($steps);
    }

    public function boot()
    {
        add_filter('cron_schedules', array($this, 'cron_interval'));
        add_action(self::CRON, array($this, 'run'));
        if (!wp_next_scheduled(self::CRON)) {
            wp_schedule_event(time() + 120, self::INTERVAL, self::CRON);
        }
        // Queue a message once every connection has failed.
        add_action('wp_arzo_email_all_failed', array($this, 'on_all_failed'), 20, 3);
    }

    public function cron_interval($schedules)
    {
        if (!isset($schedules[self::INTERVAL])) {
            $schedules[self::INTERVAL] = array('interval' => 300, 'display' => 'Every 5 minutes (WP Arzo)');
        }
        return $schedules;
    }

    /* --------------------------------------------------------- Storage */

    public function all()
    {
        $q = get_option(self::OPTION, array());
        return is_array($q) ? $q : array();
    }

    private function put($q)
    {
        update_option(self::OPTION, array_values($q), false);
    }

    public function count()
    {
        return count($this->all());
    }

    public function pending_count()
    {
        return count(array_filter($this->all(), function ($i) {
            return isset($i['status']) && $i['status'] === 'pending';
        }));
    }

    public function get($id)
    {
        foreach ($this->all() as $i) {
            if ($i['id'] === $id) {
                return $i;
            }
        }
        return null;
    }

    public function delete($id)
    {
        $this->put(array_filter($this->all(), function ($i) use ($id) {
            return $i['id'] !== $id;
        }));
        return true;
    }

    public function clear()
    {
        $this->put(array());
        return true;
    }

    /* --------------------------------------------------------- Enqueue */

    public function on_all_failed($data, $error = null, $attempts = array())
    {
        if (empty($data['to'])) {
            return; // nothing to retry
        }
        $msg = $this->last_error_of($attempts);
        if ($msg === '' && is_wp_error($error)) {
            $msg = $error->get_error_message();
        }
        $this->enqueue(array(
            'to'          => $data['to'],
            'subject'     => isset($data['subject']) ? $data['subject'] : '',
            'message'     => isset($data['message']) ? $data['message'] : '',
            'headers'     => isset($data['headers']) ? $data['headers'] : '',
            'attachments' => isset($data['attachments']) ? $data['attachments'] : array(),
        ), $msg !== '' ? $msg : 'All connections failed.');
    }

    public function enqueue($atts, $last_error = '')
    {
        $q   = $this->all();
        $q[] = array(
            'id'          => 'eq_' . substr(md5(uniqid('', true)), 0, 12),
            'atts'        => $atts,
            'to'          => is_array($atts['to']) ? implode(', ', $atts['to']) : (string) $atts['to'],
            'subject'     => (string) (isset($atts['subject']) ? $atts['subject'] : ''),
            'attempts'    => 0,
            'status'      => 'pending',
            'last_error'  => (string) $last_error,
            'created_gmt' => gmdate('Y-m-d H:i:s'),
            'next_gmt'    => gmdate('Y-m-d H:i:s', time() + self::backoff(1)),
        );
        $this->put($q);
    }

    /* ---------------------------------------------------------- Worker */

    /** Cron worker: re-attempt every due pending item; prune old given-up items. */
    public function run()
    {
        $q = $this->all();
        if (empty($q)) {
            return;
        }
        $now       = time();
        $processed = 0;
        $engine    = class_exists('WP_Arzo_Email_Connections') ? WP_Arzo_Email_Connections::instance() : null;

        foreach ($q as $k => $item) {
            if ($item['status'] !== 'pending') {
                if ($item['status'] === 'failed' && strtotime($item['created_gmt']) < $now - self::RETAIN_DAYS * DAY_IN_SECONDS) {
                    unset($q[$k]);
                }
                continue;
            }
            if ($processed >= self::MAX_PER_RUN) {
                continue;
            }
            if (strtotime($item['next_gmt']) > $now) {
                continue; // not due yet
            }
            $processed++;
            $q[$k] = $this->attempt($item, $now);
            if ($q[$k] === null) {
                unset($q[$k]); // delivered
            }
        }
        $this->put($q);
    }

    /**
     * Attempt one item. Returns the updated item, or null if delivered (caller drops it).
     */
    private function attempt($item, $now)
    {
        $engine = class_exists('WP_Arzo_Email_Connections') ? WP_Arzo_Email_Connections::instance() : null;
        $res    = $engine ? $engine->retry_deliver($item['atts']) : array('ok' => false, 'attempts' => array());
        if (!empty($res['ok'])) {
            return null;
        }
        $item['attempts']   = (int) $item['attempts'] + 1;
        $item['last_error'] = $this->last_error_of(isset($res['attempts']) ? $res['attempts'] : array());
        if ($item['attempts'] >= self::MAX_ATTEMPTS) {
            $item['status'] = 'failed';
        } else {
            $item['status']   = 'pending';
            $item['next_gmt'] = gmdate('Y-m-d H:i:s', $now + self::backoff($item['attempts'] + 1));
        }
        return $item;
    }

    /** Manual "retry now" for a single item (ignores the schedule). */
    public function retry($id)
    {
        $q = $this->all();
        foreach ($q as $k => $item) {
            if ($item['id'] !== $id) {
                continue;
            }
            $updated = $this->attempt($item, time());
            if ($updated === null) {
                unset($q[$k]);
                $this->put($q);
                return array('ok' => true);
            }
            $q[$k] = $updated;
            $this->put($q);
            return array('ok' => false, 'error' => $updated['last_error']);
        }
        return array('ok' => false, 'error' => 'Not found');
    }

    /** Retry every pending item now. */
    public function retry_all()
    {
        $out = array('ok' => 0, 'fail' => 0);
        foreach ($this->all() as $item) {
            if ($item['status'] !== 'pending') {
                continue;
            }
            $r = $this->retry($item['id']);
            if (!empty($r['ok'])) {
                $out['ok']++;
            } else {
                $out['fail']++;
            }
        }
        return $out;
    }

    private function last_error_of($attempts)
    {
        foreach (array_reverse((array) $attempts) as $a) {
            if (empty($a['skipped']) && !empty($a['error'])) {
                return (string) $a['error'];
            }
        }
        return '';
    }

    /** Clear the scheduled cron event (deactivate / uninstall). */
    public function unschedule()
    {
        $ts = wp_next_scheduled(self::CRON);
        if ($ts) {
            wp_unschedule_event($ts, self::CRON);
        }
    }
}
