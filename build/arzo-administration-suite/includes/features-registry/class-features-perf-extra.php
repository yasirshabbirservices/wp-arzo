<?php

/**
 * Free extras: Site Verification, Remove jQuery Migrate, Disable Front-end Dashicons.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Site_Verification extends WP_Arzo_Feature
{
    public function id()
    {
        return 'site_verification';
    }
    public function title()
    {
        return 'Site Verification';
    }
    public function description()
    {
        return 'Add search-engine / platform verification meta tags (Google, Bing, Pinterest, Yandex, Baidu).';
    }
    public function group()
    {
        return 'marketing';
    }
    public function icon()
    {
        return 'check-circle';
    }
    public function settings_schema()
    {
        return array(
            array('key' => 'google', 'type' => 'text', 'label' => 'Google (google-site-verification)'),
            array('key' => 'bing', 'type' => 'text', 'label' => 'Bing (msvalidate.01)'),
            array('key' => 'pinterest', 'type' => 'text', 'label' => 'Pinterest (p:domain_verify)'),
            array('key' => 'yandex', 'type' => 'text', 'label' => 'Yandex (yandex-verification)'),
            array('key' => 'baidu', 'type' => 'text', 'label' => 'Baidu (baidu-site-verification)'),
        );
    }
    public function boot()
    {
        add_action('wp_head', array($this, 'print_tags'), 1);
    }
    public function print_tags()
    {
        $map = array(
            'google'    => 'google-site-verification',
            'bing'      => 'msvalidate.01',
            'pinterest' => 'p:domain_verify',
            'yandex'    => 'yandex-verification',
            'baidu'     => 'baidu-site-verification',
        );
        foreach ($map as $key => $name) {
            $val = trim((string) $this->get_setting($key, ''));
            if ($val !== '') {
                echo '<meta name="' . esc_attr($name) . '" content="' . esc_attr($val) . '" />' . "\n";
            }
        }
    }
}

class WP_Arzo_Feature_Remove_JQuery_Migrate extends WP_Arzo_Feature
{
    public function id()
    {
        return 'remove_jquery_migrate';
    }
    public function title()
    {
        return 'Remove jQuery Migrate';
    }
    public function description()
    {
        return 'Stop loading jquery-migrate.js on the front end (small performance win).';
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
        add_action('wp_default_scripts', function ($scripts) {
            if (is_admin() || empty($scripts->registered['jquery'])) {
                return;
            }
            $deps = $scripts->registered['jquery']->deps;
            $scripts->registered['jquery']->deps = array_diff($deps, array('jquery-migrate'));
        });
    }
}

class WP_Arzo_Feature_Disable_Front_Dashicons extends WP_Arzo_Feature
{
    public function id()
    {
        return 'disable_front_dashicons';
    }
    public function title()
    {
        return 'Disable Front Dashicons';
    }
    public function description()
    {
        return 'Don’t load the Dashicons stylesheet on the front end for logged-out visitors.';
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
        add_action('wp_enqueue_scripts', function () {
            if (!is_user_logged_in()) {
                wp_dequeue_style('dashicons');
                wp_deregister_style('dashicons');
            }
        }, 100);
    }
}
