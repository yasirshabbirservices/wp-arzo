<?php

/**
 * Feature: Disable Dashboard Widgets — declutter the WP dashboard.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Disable_Dashboard_Widgets extends WP_Arzo_Feature
{
    public function id()
    {
        return 'disable_dashboard_widgets';
    }

    public function title()
    {
        return 'Disable Dashboard Widgets';
    }

    public function description()
    {
        return 'Remove the default WordPress dashboard widgets for a cleaner home screen.';
    }

    public function group()
    {
        return 'utilities';
    }

    public function icon()
    {
        return 'settings';
    }

    public function boot()
    {
        add_action('wp_dashboard_setup', function () {
            $widgets = array(
                'dashboard_activity'         => 'normal',
                'dashboard_right_now'        => 'normal',
                'dashboard_recent_comments'  => 'normal',
                'dashboard_incoming_links'   => 'normal',
                'dashboard_plugins'          => 'normal',
                'dashboard_quick_press'      => 'side',
                'dashboard_recent_drafts'    => 'side',
                'dashboard_primary'          => 'side',
                'dashboard_secondary'        => 'side',
                'dashboard_site_health'      => 'normal',
            );
            foreach ($widgets as $widget_id => $context) {
                remove_meta_box($widget_id, 'dashboard', $context);
            }
        }, 99);
    }
}
