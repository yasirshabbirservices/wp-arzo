<?php

/**
 * "Disable All Updates" feature.
 *
 * ISOLATED INTO ITS OWN FILE ON PURPOSE: it hooks the WordPress update
 * transients (pre_site_transient_update_core/plugins/themes) and auto-update
 * filters. WordPress.org's Plugin Check flags that pattern as a forbidden
 * "plugin updater / update modification", so this file is listed in .distignore
 * and STRIPPED from the wordpress.org build (see bin/build-wporg.sh). The
 * feature registration in wp-arzo.php is guarded with file_exists(), so when
 * this file is absent the feature is simply not registered (graceful) — the
 * self-hosted / GitHub / Pro builds ship it as normal.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Disable_Updates extends WP_Arzo_Feature
{
    public function id()
    {
        return 'disable_updates';
    }
    public function title()
    {
        return 'Disable All Updates';
    }
    public function description()
    {
        return 'Stop WordPress core, plugin and theme update checks, notifications and auto-updates.';
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
        $empty = function () {
            $obj = new stdClass();
            $obj->last_checked = time();
            $obj->updates = array();
            $obj->response = array();
            $obj->translations = array();
            return $obj;
        };
        add_filter('pre_site_transient_update_core', $empty);
        add_filter('pre_site_transient_update_plugins', $empty);
        add_filter('pre_site_transient_update_themes', $empty);

        add_filter('automatic_updater_disabled', '__return_true');
        add_filter('auto_update_core', '__return_false');
        add_filter('auto_update_plugin', '__return_false');
        add_filter('auto_update_theme', '__return_false');
        add_filter('auto_core_update_send_email', '__return_false');

        remove_action('admin_init', '_maybe_update_core');
        remove_action('admin_init', '_maybe_update_plugins');
        remove_action('admin_init', '_maybe_update_themes');
    }
}
