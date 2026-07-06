<?php

/**
 * Core control features: Disable Gutenberg / Feeds / Embeds.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Disable_Gutenberg extends WP_Arzo_Feature
{
    public function id()
    {
        return 'disable_gutenberg';
    }
    public function title()
    {
        return 'Disable Gutenberg';
    }
    public function description()
    {
        return 'Use the classic editor and stop loading block-editor assets.';
    }
    public function group()
    {
        return 'core';
    }
    public function icon()
    {
        return 'edit';
    }
    public function boot()
    {
        add_filter('use_block_editor_for_post', '__return_false', 100);
        add_filter('use_block_editor_for_post_type', '__return_false', 100);
        add_filter('use_widgets_block_editor', '__return_false');
        add_filter('gutenberg_use_widgets_block_editor', '__return_false');
        add_action('wp_enqueue_scripts', function () {
            wp_dequeue_style('wp-block-library');
            wp_dequeue_style('wp-block-library-theme');
            wp_dequeue_style('global-styles');
        }, 100);
    }
}

class WP_Arzo_Feature_Disable_Feeds extends WP_Arzo_Feature
{
    public function id()
    {
        return 'disable_feeds';
    }
    public function title()
    {
        return 'Disable RSS Feeds';
    }
    public function description()
    {
        return 'Redirect all RSS/Atom feeds to the homepage and remove feed links.';
    }
    public function group()
    {
        return 'core';
    }
    public function icon()
    {
        return 'x-circle';
    }
    public function boot()
    {
        $redirect = function () {
            wp_safe_redirect(home_url('/'), 301);
            exit;
        };
        foreach (array('do_feed', 'do_feed_rdf', 'do_feed_rss', 'do_feed_rss2', 'do_feed_atom', 'do_feed_rss2_comments', 'do_feed_atom_comments') as $hook) {
            add_action($hook, $redirect, 1);
        }
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);
    }
}

class WP_Arzo_Feature_Disable_Embeds extends WP_Arzo_Feature
{
    public function id()
    {
        return 'disable_embeds';
    }
    public function title()
    {
        return 'Disable Embeds';
    }
    public function description()
    {
        return 'Disable WordPress oEmbed discovery and the wp-embed script.';
    }
    public function group()
    {
        return 'core';
    }
    public function icon()
    {
        return 'x-circle';
    }
    public function boot()
    {
        add_action('init', function () {
            remove_action('wp_head', 'wp_oembed_add_discovery_links');
            remove_action('wp_head', 'wp_oembed_add_host_js');
            add_filter('embed_oembed_discover', '__return_false');
            wp_deregister_script('wp-embed');
        }, 9999);
    }
}
