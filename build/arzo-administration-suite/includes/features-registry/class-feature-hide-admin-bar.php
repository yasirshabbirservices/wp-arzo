<?php

/**
 * Feature: Hide Admin Bar — hide the front-end admin bar.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Hide_Admin_Bar extends WP_Arzo_Feature
{
    public function id()
    {
        return 'hide_admin_bar';
    }

    public function title()
    {
        return 'Hide Admin Bar';
    }

    public function description()
    {
        return 'Hide the front-end admin toolbar for the selected audience.';
    }

    public function group()
    {
        return 'utilities';
    }

    public function icon()
    {
        return 'settings';
    }

    public function settings_schema()
    {
        return array(
            array(
                'key'     => 'scope',
                'type'    => 'select',
                'label'   => 'Hide for',
                'help'    => 'Choose who should not see the front-end toolbar.',
                'default' => 'non_admins',
                'options' => array(
                    'everyone'   => 'Everyone',
                    'non_admins' => 'Non-administrators only',
                ),
            ),
        );
    }

    public function boot()
    {
        add_filter('show_admin_bar', function ($show) {
            // Never affect wp-admin itself, only the front end.
            if (is_admin()) {
                return $show;
            }
            $scope = $this->get_setting('scope', 'non_admins');
            if ($scope === 'everyone') {
                return false;
            }
            return current_user_can('manage_options') ? $show : false;
        }, 20);
    }
}
