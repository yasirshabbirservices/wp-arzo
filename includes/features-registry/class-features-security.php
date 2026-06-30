<?php

/**
 * Security features: Disable REST API for guests, Disable File Editor,
 * Block User Enumeration.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Disable_REST_Guests extends WP_Arzo_Feature
{
    public function id()
    {
        return 'disable_rest_api_guests';
    }
    public function title()
    {
        return 'Restrict REST API';
    }
    public function description()
    {
        return 'Require authentication for the REST API (blocks anonymous access).';
    }
    public function group()
    {
        return 'security';
    }
    public function icon()
    {
        return 'shield';
    }
    public function boot()
    {
        add_filter('rest_authentication_errors', function ($result) {
            if (!empty($result)) {
                return $result;
            }
            if (!is_user_logged_in()) {
                return new WP_Error(
                    'rest_not_logged_in',
                    'The REST API is restricted to authenticated users.',
                    array('status' => 401)
                );
            }
            return $result;
        });
    }
}

class WP_Arzo_Feature_Disable_File_Editor extends WP_Arzo_Feature
{
    public function id()
    {
        return 'disable_file_editor';
    }
    public function title()
    {
        return 'Disable Theme/Plugin Editor';
    }
    public function description()
    {
        return 'Remove the built-in code editor (Appearance → Editor, Plugins → Editor).';
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
        if (!defined('DISALLOW_FILE_EDIT')) {
            define('DISALLOW_FILE_EDIT', true);
        }
        add_action('admin_menu', function () {
            remove_submenu_page('themes.php', 'theme-editor.php');
            remove_submenu_page('plugins.php', 'plugin-editor.php');
        }, 999);
    }
}

class WP_Arzo_Feature_Block_User_Enumeration extends WP_Arzo_Feature
{
    public function id()
    {
        return 'block_user_enumeration';
    }
    public function title()
    {
        return 'Block User Enumeration';
    }
    public function description()
    {
        return 'Block ?author=N scans and author-archive username probing.';
    }
    public function group()
    {
        return 'security';
    }
    public function icon()
    {
        return 'shield';
    }
    public function boot()
    {
        // Block the ?author=N enumeration vector on the front end.
        add_action('template_redirect', function () {
            if (!is_admin() && isset($_GET['author']) && is_numeric($_GET['author'])) {
                wp_safe_redirect(home_url('/'), 301);
                exit;
            }
        });
        // Block REST user listing for unauthenticated requests.
        add_filter('rest_endpoints', function ($endpoints) {
            if (!is_user_logged_in()) {
                unset($endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)']);
            }
            return $endpoints;
        });
    }
}
