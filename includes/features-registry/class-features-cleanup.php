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
        return 'Clean up the admin toolbar — hide the WordPress logo, site menu, comments, updates, “New”, Customize, search, the Help tab, and the “Howdy,” greeting.';
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
            array('key' => 'remove_site_menu', 'type' => 'toggle', 'label' => 'Remove site name / Visit Site', 'default' => 0),
            array('key' => 'remove_comments', 'type' => 'toggle', 'label' => 'Remove comments', 'default' => 0),
            array('key' => 'remove_updates', 'type' => 'toggle', 'label' => 'Remove updates', 'default' => 0),
            array('key' => 'remove_new', 'type' => 'toggle', 'label' => 'Remove “New” menu', 'default' => 0),
            array('key' => 'remove_customize', 'type' => 'toggle', 'label' => 'Remove Customize', 'default' => 0),
            array('key' => 'remove_search', 'type' => 'toggle', 'label' => 'Remove search', 'default' => 0),
            array('key' => 'remove_help', 'type' => 'toggle', 'label' => 'Remove Help tab', 'default' => 0),
            array('key' => 'remove_howdy', 'type' => 'toggle', 'label' => 'Remove “Howdy,” greeting', 'default' => 0),
        );
    }
    public function boot()
    {
        add_action('admin_bar_menu', array($this, 'clean_toolbar'), 999);
        if ($this->get_setting('remove_help', 0)) {
            add_action('admin_head', array($this, 'remove_help_tabs'), 99);
        }
        // "Howdy, %s" is a translated string — strip it at the gettext layer (reliable),
        // scoped to admin requests only.
        if (is_admin() && $this->get_setting('remove_howdy', 0)) {
            add_filter('gettext', array($this, 'strip_howdy'), 10, 2);
        }
    }

    public function clean_toolbar($bar)
    {
        if (!is_object($bar) || !method_exists($bar, 'remove_node')) {
            return;
        }
        $map = array(
            'remove_logo'      => 'wp-logo',
            'remove_site_menu' => 'site-name',
            'remove_comments'  => 'comments',
            'remove_updates'   => 'updates',
            'remove_new'       => 'new-content',
            'remove_customize' => 'customize',
            'remove_search'    => 'search',
        );
        foreach ($map as $setting => $node) {
            $default = ($setting === 'remove_logo') ? 1 : 0;
            if ($this->get_setting($setting, $default)) {
                $bar->remove_node($node);
            }
        }
    }

    public function remove_help_tabs()
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && method_exists($screen, 'remove_help_tabs')) {
            $screen->remove_help_tabs();
        }
    }

    public function strip_howdy($translation, $text)
    {
        return ($text === 'Howdy, %s') ? '%s' : $translation;
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
        return 'Add an ID column and a featured-image thumbnail to classic list tables (posts, pages, and custom post types).';
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
        // WordPress 7.0 converts the core Posts / Pages / Media screens to the React
        // DataViews grid, where the classic `manage_*_columns` hooks no longer fire — so
        // these columns apply to classic list tables (custom post types, and Posts/Pages
        // while still classic). This is a graceful no-op on DataViews screens, never a break.
        return array(
            array('key' => 'show_id', 'type' => 'toggle', 'label' => 'Show ID column', 'default' => 1, 'help' => 'Adds a numeric ID column to classic list tables. On WordPress 7.0+ the core Posts/Pages/Media screens use the new DataViews grid, where this does not apply — custom post types are unaffected.'),
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
