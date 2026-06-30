<?php

/**
 * Performance / content features: Disable Emojis, Heartbeat Control,
 * Disable Self Pingbacks, Limit Revisions.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Disable_Emojis extends WP_Arzo_Feature
{
    public function id()
    {
        return 'disable_emojis';
    }
    public function title()
    {
        return 'Disable Emojis';
    }
    public function description()
    {
        return 'Remove the extra emoji script/styles WordPress injects on every page.';
    }
    public function group()
    {
        return 'utilities';
    }
    public function icon()
    {
        return 'bolt';
    }
    public function boot()
    {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        add_filter('emoji_svg_url', '__return_false');
        add_filter('tiny_mce_plugins', function ($plugins) {
            return is_array($plugins) ? array_diff($plugins, array('wpemoji')) : $plugins;
        });
    }
}

class WP_Arzo_Feature_Heartbeat_Control extends WP_Arzo_Feature
{
    public function id()
    {
        return 'heartbeat_control';
    }
    public function title()
    {
        return 'Heartbeat Control';
    }
    public function description()
    {
        return 'Limit or disable the WordPress Heartbeat API to reduce admin-ajax load.';
    }
    public function group()
    {
        return 'developer';
    }
    public function icon()
    {
        return 'bolt';
    }
    public function settings_schema()
    {
        return array(
            array(
                'key'     => 'behavior',
                'type'    => 'select',
                'label'   => 'Heartbeat behavior',
                'default' => 'dashboard_only',
                'options' => array(
                    'allow'          => 'Allow everywhere',
                    'dashboard_only' => 'Allow on Dashboard only',
                    'disable_all'    => 'Disable everywhere',
                ),
            ),
            array(
                'key'     => 'frequency',
                'type'    => 'number',
                'label'   => 'Frequency (seconds, 15–120)',
                'default' => 60,
                'help'    => 'Higher = fewer requests. Ignored when disabled.',
            ),
        );
    }
    public function boot()
    {
        $behavior = $this->get_setting('behavior', 'dashboard_only');

        // Core's `wp-auth-check` (the "session expired" modal) declares a `heartbeat`
        // dependency. Removing heartbeat alone leaves that dependency dangling, which
        // WordPress 6.9+ reports as a "Function WP_Scripts::add was called incorrectly"
        // notice. So whenever we kill heartbeat we also drop wp-auth-check and stop core
        // from enqueuing it. Done at enqueue time (priority 100) so the scripts exist first.
        $kill_heartbeat = function () {
            wp_deregister_script('heartbeat');
            wp_dequeue_script('heartbeat');
            wp_deregister_script('wp-auth-check');
            wp_dequeue_script('wp-auth-check');
        };

        if ($behavior === 'disable_all') {
            add_action('admin_enqueue_scripts', $kill_heartbeat, 100);
            add_action('wp_enqueue_scripts', $kill_heartbeat, 100);
            // Prevent core from enqueuing the auth-check modal (admin-only) in the first place.
            remove_action('admin_enqueue_scripts', 'wp_auth_check_load');
            return;
        }

        if ($behavior === 'dashboard_only') {
            add_action('admin_enqueue_scripts', function ($hook) use ($kill_heartbeat) {
                if ($hook !== 'index.php') {
                    $kill_heartbeat();
                    remove_action('admin_print_footer_scripts', 'wp_auth_check_html');
                }
            }, 100);
        }

        add_filter('heartbeat_settings', function ($settings) {
            $freq = (int) $this->get_setting('frequency', 60);
            $settings['interval'] = max(15, min(120, $freq));
            return $settings;
        });
    }
}

class WP_Arzo_Feature_Disable_Self_Pingbacks extends WP_Arzo_Feature
{
    public function id()
    {
        return 'disable_self_pingbacks';
    }
    public function title()
    {
        return 'Disable Self Pingbacks';
    }
    public function description()
    {
        return 'Stop WordPress pinging your own site when you link internally.';
    }
    public function group()
    {
        return 'utilities';
    }
    public function icon()
    {
        return 'x-circle';
    }
    public function boot()
    {
        add_action('pre_ping', function (&$links) {
            $home = get_option('home');
            foreach ($links as $i => $link) {
                if (strpos($link, $home) === 0) {
                    unset($links[$i]);
                }
            }
        });
    }
}

class WP_Arzo_Feature_Limit_Revisions extends WP_Arzo_Feature
{
    public function id()
    {
        return 'limit_revisions';
    }
    public function title()
    {
        return 'Revisions Control';
    }
    public function description()
    {
        return 'Cap how many post revisions WordPress keeps per post.';
    }
    public function group()
    {
        return 'content';
    }
    public function icon()
    {
        return 'file';
    }
    public function settings_schema()
    {
        return array(
            array(
                'key'     => 'max',
                'type'    => 'number',
                'label'   => 'Revisions to keep',
                'default' => 5,
                'help'    => 'Use 0 to disable revisions entirely.',
            ),
        );
    }
    public function boot()
    {
        add_filter('wp_revisions_to_keep', function ($num) {
            return (int) $this->get_setting('max', 5);
        }, 10);
    }
}
