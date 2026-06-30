<?php

/**
 * Feature: Role Manager.
 *
 * View user roles, edit their capabilities, and add / clone / delete custom roles.
 * The behaviour lives entirely in a dedicated admin page + AJAX handlers (see
 * WP_Arzo_Admin); this module only contributes the dashboard toggle that gates
 * the page, plus shared helpers (capability discovery + the lockout guard).
 *
 * Changes go through the core Roles API ($wp_roles / WP_Role), which persists to
 * the `wp_user_roles` option — we keep no parallel storage.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Role_Manager extends WP_Arzo_Feature
{
    /** Built-in roles we never delete and (for administrator) never lock out. */
    const BUILTIN = array('administrator', 'editor', 'author', 'contributor', 'subscriber');

    public function id()
    {
        return 'role_manager';
    }
    public function title()
    {
        return 'Role Manager';
    }
    public function description()
    {
        return 'View roles, edit their capabilities, and add, clone, or delete custom roles.';
    }
    public function group()
    {
        return 'core';
    }
    public function icon()
    {
        return 'users';
    }

    /** Page-and-AJAX driven; nothing to hook on the front end. */
    public function boot()
    {
    }

    /* ------------------------------------------------------------- helpers */

    public static function is_builtin($slug)
    {
        return in_array($slug, self::BUILTIN, true);
    }

    /**
     * Every distinct capability known across all roles, sorted. Used to render the
     * capability grid so admins can grant caps a role doesn't yet have.
     *
     * @return array<int,string>
     */
    public static function all_capabilities()
    {
        $caps = array();
        $roles = wp_roles();
        foreach ($roles->roles as $role) {
            if (!empty($role['capabilities']) && is_array($role['capabilities'])) {
                foreach ($role['capabilities'] as $cap => $granted) {
                    $caps[$cap] = true;
                }
            }
        }
        // A few core caps that may not be present on any current role.
        foreach (array('manage_options', 'edit_posts', 'publish_posts', 'upload_files', 'edit_theme_options', 'list_users', 'manage_categories') as $cap) {
            $caps[$cap] = true;
        }
        $list = array_keys($caps);
        sort($list);
        return $list;
    }

    /**
     * Overview rows for the roles table: slug, name, user count, cap count, builtin.
     *
     * @return array<int,array>
     */
    public static function roles_overview()
    {
        $roles = wp_roles();
        $counts = count_users();
        $by_role = isset($counts['avail_roles']) ? $counts['avail_roles'] : array();
        $out = array();
        foreach ($roles->roles as $slug => $role) {
            $granted = 0;
            if (!empty($role['capabilities']) && is_array($role['capabilities'])) {
                foreach ($role['capabilities'] as $cap => $on) {
                    if ($on) {
                        $granted++;
                    }
                }
            }
            $out[] = array(
                'slug'       => $slug,
                'name'       => isset($role['name']) ? $role['name'] : $slug,
                'users'      => isset($by_role[$slug]) ? (int) $by_role[$slug] : 0,
                'caps'       => $granted,
                'is_builtin' => self::is_builtin($slug),
            );
        }
        return $out;
    }
}
