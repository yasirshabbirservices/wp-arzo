<?php

/**
 * Feature: Disable Comments — turns comments off site-wide.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Disable_Comments extends WP_Arzo_Feature
{
    public function id()
    {
        return 'disable_comments';
    }

    public function title()
    {
        return 'Disable Comments';
    }

    public function description()
    {
        return 'Disable comments and pingbacks everywhere, and remove the comment UI.';
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
        // Close comments and pings on the front end.
        add_filter('comments_open', '__return_false', 20);
        add_filter('pings_open', '__return_false', 20);
        add_filter('comments_array', '__return_empty_array', 10);

        // Hide existing comments.
        add_filter('comments_number', function () {
            return 0;
        });

        // Remove the admin menu item + dashboard support.
        add_action('admin_menu', function () {
            remove_menu_page('edit-comments.php');
        });
        add_action('admin_init', function () {
            global $pagenow;
            if ($pagenow === 'edit-comments.php') {
                wp_safe_redirect(admin_url());
                exit;
            }
            // Remove comment support from every post type.
            foreach (get_post_types() as $post_type) {
                if (post_type_supports($post_type, 'comments')) {
                    remove_post_type_support($post_type, 'comments');
                    remove_post_type_support($post_type, 'trackbacks');
                }
            }
        });

        // Remove the admin-bar comments bubble.
        add_action('wp_before_admin_bar_render', function () {
            global $wp_admin_bar;
            if ($wp_admin_bar) {
                $wp_admin_bar->remove_menu('comments');
            }
        });

        // Remove the dashboard "Recent Comments" widget.
        add_action('wp_dashboard_setup', function () {
            remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
        });
    }
}
