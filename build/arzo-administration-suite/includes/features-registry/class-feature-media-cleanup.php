<?php

/**
 * Free feature: Media Cleanup (toggle).
 *
 * Media Cleanup is a scan/delete maintenance tool with its own admin page
 * (rendered by WP_Arzo_Admin::render_media_cleanup + the wp_arzo_media_scan /
 * wp_arzo_media_delete AJAX handlers). This module exposes it as a normal
 * dashboard toggle so it can be enabled/disabled like every other feature; the
 * admin page and its AJAX handlers are gated on this being enabled.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Media_Cleanup extends WP_Arzo_Feature
{
    public function id()
    {
        return 'media_cleanup';
    }
    public function title()
    {
        return 'Media Cleanup';
    }
    public function description()
    {
        return 'Scan the media library for unused/large files and safely delete them in batches (WP Arzo → Media Cleanup).';
    }
    public function group()
    {
        return 'media';
    }
    public function tier()
    {
        return 'free';
    }
    public function icon()
    {
        return 'trash';
    }
    public function default_enabled()
    {
        return false;
    }
    public function settings_schema()
    {
        return array();
    }
    public function boot()
    {
        // Inert: the page + AJAX handlers live in WP_Arzo_Admin and are gated on
        // this feature being enabled (page_visible() / the AJAX handlers).
    }
}
