<?php

/**
 * Feature: Scheduled Backups (free). Creates database snapshots automatically on a
 * WP-Cron schedule (daily / weekly / monthly), with retention. Local snapshots are
 * free; pushing them off-site is handled by the (Pro) remote destinations.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Scheduled_Backups extends WP_Arzo_Feature
{
    const HOOK = 'wp_arzo_scheduled_backup';

    public function id()
    {
        return 'scheduled_backups';
    }
    public function title()
    {
        return 'Scheduled Backups';
    }
    public function description()
    {
        return 'Automatically create database snapshots on a schedule (daily / weekly / monthly) with retention.';
    }
    public function group()
    {
        return 'backup';
    }
    public function icon()
    {
        return 'clock';
    }
    public function settings_schema()
    {
        return array(
            array('key' => 'frequency', 'type' => 'select', 'label' => 'Frequency', 'default' => 'daily', 'options' => array(
                'daily'   => 'Daily',
                'weekly'  => 'Weekly',
                'monthly' => 'Monthly',
            )),
            array('key' => 'scope', 'type' => 'select', 'label' => 'What to back up', 'default' => 'full_db', 'options' => array(
                'options' => 'Options table only',
                'full_db' => 'Full database',
            )),
            array('key' => 'retention', 'type' => 'number', 'label' => 'Snapshots to keep', 'default' => 14),
        );
    }

    public function boot()
    {
        add_filter('cron_schedules', array($this, 'cron_schedules'));
        add_action(self::HOOK, array($this, 'run'));
        add_action('init', array($this, 'ensure_schedule'));

        add_filter('wp_arzo_backup_retention', function ($keep) {
            return max(1, (int) $this->get_setting('retention', 14));
        });
    }

    public function cron_schedules($schedules)
    {
        if (!isset($schedules['weekly'])) {
            $schedules['weekly'] = array('interval' => WEEK_IN_SECONDS, 'display' => __('Once Weekly', 'arzo-administration-suite'));
        }
        if (!isset($schedules['monthly'])) {
            $schedules['monthly'] = array('interval' => 30 * DAY_IN_SECONDS, 'display' => __('Once Monthly', 'arzo-administration-suite'));
        }
        return $schedules;
    }

    /** Keep the WP-Cron event in sync with the chosen frequency. */
    public function ensure_schedule()
    {
        $freq = $this->get_setting('frequency', 'daily');
        if (!in_array($freq, array('daily', 'weekly', 'monthly'), true)) {
            $freq = 'daily';
        }
        $next   = wp_next_scheduled(self::HOOK);
        $stored = get_option('wp_arzo_sched_freq', '');

        if ($next && $stored === $freq) {
            return; // already scheduled at the right cadence
        }
        if ($next) {
            wp_clear_scheduled_hook(self::HOOK);
        }
        wp_schedule_event(time() + MINUTE_IN_SECONDS, $freq, self::HOOK);
        update_option('wp_arzo_sched_freq', $freq, false);
    }

    public function run()
    {
        if (class_exists('WP_Arzo_Backup_Manager')) {
            WP_Arzo_Backup_Manager::instance()->create($this->get_setting('scope', 'full_db'), 'Scheduled backup', 'scheduled');
        }
    }
}
