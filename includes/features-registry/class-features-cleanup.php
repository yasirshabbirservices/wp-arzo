<?php

/**
 * Free cleanup/utility features: Crawl Optimizations, Custom Body Class,
 * Disable Application Passwords, Clean Up Admin Bar, Enhance List Tables.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Crawl_Optimizations extends WP_Arzo_Feature
{
    public function id()
    {
        return 'crawl_optimizations';
    }
    public function title()
    {
        return 'Crawl Optimizations';
    }
    public function description()
    {
        return 'Remove generator, RSD, WLW manifest, shortlink and REST/oEmbed link tags from <head>.';
    }
    public function group()
    {
        return 'core';
    }
    public function icon()
    {
        return 'search';
    }
    public function boot()
    {
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wp_shortlink_wp_head', 10);
        remove_action('wp_head', 'rest_output_link_wp_head', 10);
        remove_action('wp_head', 'wp_oembed_add_discovery_links', 10);
        remove_action('template_redirect', 'rest_output_link_header', 11);
        add_filter('the_generator', '__return_empty_string');
    }
}

class WP_Arzo_Feature_Custom_Body_Class extends WP_Arzo_Feature
{
    public function id()
    {
        return 'custom_body_class';
    }
    public function title()
    {
        return 'Custom Body Class';
    }
    public function description()
    {
        return 'Add one or more custom classes to the front-end <body> tag.';
    }
    public function group()
    {
        return 'branding';
    }
    public function icon()
    {
        return 'code';
    }
    public function settings_schema()
    {
        return array(array('key' => 'classes', 'type' => 'text', 'label' => 'Body classes', 'help' => 'Space-separated, e.g. my-theme custom-layout'));
    }
    public function boot()
    {
        add_filter('body_class', function ($classes) {
            $extra = preg_split('/\s+/', trim((string) $this->get_setting('classes', '')));
            $extra = array_filter(array_map('sanitize_html_class', (array) $extra));
            return array_merge($classes, $extra);
        });
    }
}

class WP_Arzo_Feature_Disable_App_Passwords extends WP_Arzo_Feature
{
    public function id()
    {
        return 'disable_app_passwords';
    }
    public function title()
    {
        return 'Disable Application Passwords';
    }
    public function description()
    {
        return 'Turn off the WordPress Application Passwords feature site-wide.';
    }
    public function group()
    {
        return 'security';
    }
    public function icon()
    {
        return 'lock';
    }
    public function boot()
    {
        add_filter('wp_is_application_passwords_available', '__return_false');
    }
}

class WP_Arzo_Feature_Clean_Admin_Bar extends WP_Arzo_Feature
{
    public function id()
    {
        return 'clean_admin_bar';
    }
    public function title()
    {
        return 'Clean Up Admin Bar';
    }
    public function description()
    {
        return 'Remove clutter from the toolbar (WordPress logo, comments, updates, “New”).';
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
            array('key' => 'remove_logo', 'type' => 'toggle', 'label' => 'Remove WordPress logo', 'default' => 1),
            array('key' => 'remove_comments', 'type' => 'toggle', 'label' => 'Remove comments', 'default' => 0),
            array('key' => 'remove_updates', 'type' => 'toggle', 'label' => 'Remove updates', 'default' => 0),
            array('key' => 'remove_new', 'type' => 'toggle', 'label' => 'Remove “New” menu', 'default' => 0),
        );
    }
    public function boot()
    {
        add_action('admin_bar_menu', function ($bar) {
            if ($this->get_setting('remove_logo', 1)) {
                $bar->remove_node('wp-logo');
            }
            if ($this->get_setting('remove_comments', 0)) {
                $bar->remove_node('comments');
            }
            if ($this->get_setting('remove_updates', 0)) {
                $bar->remove_node('updates');
            }
            if ($this->get_setting('remove_new', 0)) {
                $bar->remove_node('new-content');
            }
        }, 999);
    }
}

class WP_Arzo_Feature_Enhance_List_Tables extends WP_Arzo_Feature
{
    public function id()
    {
        return 'enhance_list_tables';
    }
    public function title()
    {
        return 'Enhance List Tables';
    }
    public function description()
    {
        return 'Add an ID column and a featured-image thumbnail to the posts/pages list tables.';
    }
    public function group()
    {
        return 'utilities';
    }
    public function icon()
    {
        return 'file';
    }
    public function settings_schema()
    {
        return array(
            array('key' => 'show_id', 'type' => 'toggle', 'label' => 'Show ID column', 'default' => 1),
            array('key' => 'show_thumb', 'type' => 'toggle', 'label' => 'Show featured image', 'default' => 1),
        );
    }
    public function boot()
    {
        add_filter('manage_posts_columns', array($this, 'columns'));
        add_filter('manage_pages_columns', array($this, 'columns'));
        add_action('manage_posts_custom_column', array($this, 'cell'), 10, 2);
        add_action('manage_pages_custom_column', array($this, 'cell'), 10, 2);
    }
    public function columns($cols)
    {
        $show_thumb = $this->get_setting('show_thumb', 1);
        $out = array();
        foreach ($cols as $key => $label) {
            if ($key === 'title' && $show_thumb) {
                $out['wp_arzo_thumb'] = __('Image', 'wp-arzo');
            }
            $out[$key] = $label;
        }
        if ($this->get_setting('show_id', 1)) {
            $out['wp_arzo_id'] = __('ID', 'wp-arzo');
        }
        return $out;
    }
    public function cell($column, $post_id)
    {
        if ($column === 'wp_arzo_id') {
            echo (int) $post_id;
        } elseif ($column === 'wp_arzo_thumb') {
            $thumb = get_the_post_thumbnail((int) $post_id, array(40, 40));
            echo $thumb ? $thumb : '—';
        }
    }
}
