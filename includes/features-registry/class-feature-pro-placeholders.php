<?php

/**
 * Pro feature placeholders (free core).
 *
 * The free core advertises the Pro catalog directly in the dashboard: every Pro
 * module shows as a card with a "PRO" badge and a locked "Unlock" action, so
 * users can see exactly what the paid add-on offers — even when the Pro plugin
 * is not installed.
 *
 * When the real WP Arzo Pro plugin IS active it registers the genuine modules
 * first; we then only register placeholders for catalog entries that aren't
 * already present (see wp_arzo_register_pro_placeholders), so there is never a
 * duplicate card. Placeholders are inert: tier `pro`, no settings, no boot, and
 * the freemium gate keeps them un-toggleable until Pro is active.
 *
 * Keep this catalog in sync with the Pro repo's registered modules.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * A lightweight, inert stand-in for a Pro feature, driven by catalog metadata.
 */
class WP_Arzo_Feature_Pro_Placeholder extends WP_Arzo_Feature
{
    private $meta;

    public function __construct(array $meta)
    {
        $this->meta = $meta;
    }

    public function id()
    {
        return $this->meta['id'];
    }
    public function title()
    {
        return $this->meta['title'];
    }
    public function description()
    {
        return isset($this->meta['description']) ? $this->meta['description'] : '';
    }
    public function group()
    {
        return isset($this->meta['group']) ? $this->meta['group'] : 'utilities';
    }
    public function icon()
    {
        return isset($this->meta['icon']) ? $this->meta['icon'] : 'sparkles';
    }
    public function tier()
    {
        return 'pro';
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
        // Intentionally inert — the real Pro module provides behaviour.
    }
}

/**
 * The Pro feature catalog shown to free users. One entry per registered Pro
 * module (mirror the Pro repo's wp_arzo_register_features list).
 *
 * @return array<int,array>
 */
function wp_arzo_pro_feature_catalog()
{
    return array(
        // Marketing & Tracking
        array('id' => 'meta_pixel', 'title' => 'Meta (Facebook) Pixel', 'group' => 'marketing', 'icon' => 'bolt', 'description' => 'Inject your Meta Pixel base code on every page and track PageView.'),
        array('id' => 'tiktok_pixel', 'title' => 'TikTok Pixel', 'group' => 'marketing', 'icon' => 'bolt', 'description' => 'Add the TikTok Pixel base code and track page views.'),
        array('id' => 'linkedin_insight', 'title' => 'LinkedIn Insight Tag', 'group' => 'marketing', 'icon' => 'bolt', 'description' => 'Add the LinkedIn Insight Tag (Partner ID) for conversion tracking.'),
        array('id' => 'pinterest_tag', 'title' => 'Pinterest Tag', 'group' => 'marketing', 'icon' => 'bolt', 'description' => 'Add the Pinterest Tag base code and track page visits.'),
        array('id' => 'snapchat_pixel', 'title' => 'Snapchat Pixel', 'group' => 'marketing', 'icon' => 'bolt', 'description' => 'Add the Snapchat Pixel and track page views.'),
        array('id' => 'x_pixel', 'title' => 'X (Twitter) Pixel', 'group' => 'marketing', 'icon' => 'bolt', 'description' => 'Add the X / Twitter conversion tracking pixel.'),
        array('id' => 'bing_uet', 'title' => 'Microsoft / Bing UET', 'group' => 'marketing', 'icon' => 'bolt', 'description' => 'Add the Microsoft Advertising (Bing) Universal Event Tracking tag.'),
        array('id' => 'google_analytics_4', 'title' => 'Google Analytics 4', 'group' => 'marketing', 'icon' => 'search', 'description' => 'Add the GA4 global site tag (gtag.js) with your Measurement ID.'),
        array('id' => 'google_tag_manager', 'title' => 'Google Tag Manager', 'group' => 'marketing', 'icon' => 'bolt', 'description' => 'Add the GTM container (head script + body noscript) by container ID.'),
        array('id' => 'google_ads', 'title' => 'Google Ads Tag', 'group' => 'marketing', 'icon' => 'bolt', 'description' => 'Add the Google Ads global site tag (gtag.js) for remarketing & conversions.'),

        // Content & Modeling
        array('id' => 'content_types', 'title' => 'Content Types (CPT/CCT)', 'group' => 'content', 'icon' => 'file', 'description' => 'Build custom post types and taxonomies from a UI.'),
        array('id' => 'custom_fields', 'title' => 'Custom Fields (Meta Boxes)', 'group' => 'content', 'icon' => 'edit', 'description' => 'Define field groups and attach them to post types as editor meta boxes.'),

        // Media
        array('id' => 'media_folders', 'title' => 'Media Folders', 'group' => 'media', 'icon' => 'folder', 'description' => 'Organise the media library into nestable folders, with library filters and per-file assignment.'),

        // Branding & UI
        array('id' => 'admin_branding', 'title' => 'Admin Branding & Dashboard', 'group' => 'branding', 'icon' => 'sparkles', 'description' => 'White-label wp-admin: accent color, custom footer, and a branded Custom Dashboard.'),
        array('id' => 'text_replacement', 'title' => 'Text Replacement (White-label)', 'group' => 'branding', 'icon' => 'edit', 'description' => 'Rebrand wp-admin — replace any text (e.g. “WordPress” → your brand) across menus, toolbar and labels.'),

        // Ops & Monitoring
        array('id' => 'redirects', 'title' => 'Redirects & 404 Monitor', 'group' => 'ops', 'icon' => 'external', 'description' => 'Create URL redirects (exact or regex, 301/302/307) and monitor 404s.'),
        array('id' => 'cron_manager', 'title' => 'Cron Manager', 'group' => 'ops', 'icon' => 'clock', 'description' => 'View, run, and delete WP-Cron events and inspect schedules.'),

        // Email

        // Backup & Restore
        array('id' => 'backup_ftp', 'title' => 'Off-site Backups: FTP', 'group' => 'backup', 'icon' => 'upload', 'description' => 'Automatically upload each new database snapshot (zipped) to an FTP server.'),
    );
}

/**
 * Register a placeholder for every Pro catalog entry not already provided by the
 * real Pro add-on. Call AFTER the `wp_arzo_register_features` action so genuine
 * modules win.
 *
 * @param WP_Arzo_Feature_Registry $registry
 */
function wp_arzo_register_pro_placeholders($registry)
{
    foreach (wp_arzo_pro_feature_catalog() as $meta) {
        if (!$registry->get($meta['id'])) {
            $registry->register(new WP_Arzo_Feature_Pro_Placeholder($meta));
        }
    }
}
