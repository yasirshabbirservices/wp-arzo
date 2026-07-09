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
        // GA4 / GTM / Google Ads are now FREE features (Analytics group) — no Pro placeholder.
        array('id' => 'analytics_pro', 'title' => 'Analytics Pro', 'group' => 'analytics', 'icon' => 'chart', 'description' => 'Advanced analytics on the free built-in engine — a UTM Campaigns report, a live Real-time visitor view, no-code Event tracking (clicks, downloads, outbound links, form submits), and visitor Journeys that replay each visit’s path through your site.'),
        array('id' => 'analytics_reports', 'title' => 'Analytics Email Reports', 'group' => 'analytics', 'icon' => 'mail', 'description' => 'Scheduled HTML analytics digests (daily, weekly or monthly) — traffic summary, top pages, referrers and countries — emailed to you through your WP Arzo email connections.'),
        array('id' => 'analytics_ecommerce', 'title' => 'eCommerce Analytics', 'group' => 'analytics', 'icon' => 'cart', 'description' => 'Attribute WooCommerce revenue to your traffic — revenue, orders, average order value and conversion rate, plus revenue by source and top converting landing pages, in the Analytics dashboard.'),
        array('id' => 'analytics_content', 'title' => 'Content Analytics', 'group' => 'analytics', 'icon' => 'user', 'description' => 'Author, post-type and category reports on top of the built-in engine — see which authors, content types and categories drive your traffic, with traffic-share %.'),
        array('id' => 'analytics_rollups', 'title' => 'Analytics Rollups', 'group' => 'analytics', 'icon' => 'database', 'description' => 'For busy sites: keep a tiny daily rollup so you can shorten raw-data retention (faster queries, leaner database) while keeping the daily trend and totals forever — old ranges still report after their raw hits are pruned.'),
        array('id' => 'google_tags_pro', 'title' => 'Advanced Google Tags', 'group' => 'analytics', 'icon' => 'shield', 'description' => 'Consent Mode v2 (GDPR-friendly defaults for your GA4 / GTM / Ads tags) and server-side GTM (route tags through your own tagging server).'),

        // Developer — script-insertion power tools (Pro-only; not on the WordPress.org build)
        array('id' => 'code_snippets', 'title' => 'Code Snippets', 'group' => 'developer', 'icon' => 'editor-code', 'description' => 'Run your own PHP, CSS, JS or HTML snippets with a syntax-highlighted editor, per-snippet run location and priority, smart conditional logic, a safe error guard that auto-disables a broken snippet, and a front-end shortcode. An advanced-scripts-class power tool.'),
        array('id' => 'custom_code', 'title' => 'Header / Body / Footer Code', 'group' => 'developer', 'icon' => 'code', 'description' => 'Insert custom code (analytics, verification tags, scripts) into the &lt;head&gt;, after &lt;body&gt;, or before &lt;/body&gt; site-wide.'),

        // Content & Modeling
        array('id' => 'content_types', 'title' => 'Content Types (CPT/CCT)', 'group' => 'content', 'icon' => 'file', 'description' => 'Build custom post types and taxonomies from a UI.'),
        array('id' => 'custom_fields', 'title' => 'Custom Fields (Meta Boxes)', 'group' => 'content', 'icon' => 'edit', 'description' => 'Define field groups and attach them to post types as editor meta boxes.'),

        // Media
        array('id' => 'media_folders', 'title' => 'Media Folders', 'group' => 'media', 'icon' => 'folder', 'description' => 'Organise the media library into nestable folders, with library filters and per-file assignment.'),

        // Branding & UI
        array('id' => 'admin_branding', 'title' => 'Admin Branding & Dashboard', 'group' => 'branding', 'icon' => 'sparkles', 'description' => 'White-label wp-admin: accent color, custom footer, and a Custom Dashboard — a branded welcome panel, or render any Bricks / Elementor / Divi / WordPress page or template as the entire dashboard (optionally only for non-admins).'),
        array('id' => 'text_replacement', 'title' => 'Text Replacement (White-label)', 'group' => 'branding', 'icon' => 'edit', 'description' => 'Rebrand wp-admin — replace any text (e.g. “WordPress” → your brand) across menus, toolbar and labels.'),
        array('id' => 'menu_manager', 'title' => 'Admin Menu Manager', 'group' => 'branding', 'icon' => 'menu', 'description' => 'Reorder, hide and rename wp-admin menu items with drag-and-drop — saved per role (a lean menu for clients, the full menu for admins).'),

        // Security
        array('id' => 'two_factor', 'title' => 'Two-Factor Authentication', 'group' => 'security', 'icon' => 'lock', 'description' => 'Add an authenticator-app second factor (TOTP) to login, with one-time recovery codes and an admin reset. Strictly opt-in per user.'),

        // Ops & Monitoring
        array('id' => 'redirects', 'title' => 'Redirects & 404 Monitor', 'group' => 'ops', 'icon' => 'external', 'description' => 'Create URL redirects (exact or regex, 301/302/307) and monitor 404s.'),
        array('id' => 'cron_manager', 'title' => 'Advanced Cron Manager', 'group' => 'ops', 'icon' => 'clock', 'description' => 'Full WP-Cron control center: create, edit, run, pause and delete events — one at a time or in bulk (run/pause/resume/delete selected); scheduled URL jobs (webhooks, cache warmers) and Code-Snippet jobs (run a PHP snippet on a schedule); custom intervals; a timed run log with CSV export and error capture (manual runs and fatals on the real cron tick); and cron health diagnostics with a spawn test.'),
        array('id' => 'audit_log', 'title' => 'Advanced Audit Log', 'group' => 'ops', 'icon' => 'shield', 'description' => 'A durable, database-backed audit trail extending the free Activity Log — retention windows, advanced filters (action, user, date range, search), and CSV export.'),
        array('id' => 'notifications', 'title' => 'Notifications', 'group' => 'ops', 'icon' => 'bolt', 'description' => 'Push site events — security, backups, email failures, system changes — to Slack, Discord, n8n (cloud or self-hosted), or any webhook, with per-channel event selection, a per-channel severity floor, and quiet hours (critical alerts always break through).'),
        array('id' => 'site_health', 'title' => 'Site Health Monitor', 'group' => 'ops', 'icon' => 'heartbeat', 'description' => 'Automated checks for disk, SSL expiry, updates, PHP EOL, WP-Cron, loopback reachability &amp; response time, object cache, autoloaded-option weight and database overhead — with uptime %, response-time trends, alerts to your Notifications channels, an outbound heartbeat ping (Healthchecks.io-style dead-man switch), a token-guarded JSON status endpoint for external monitors, a shareable branded status page, and a scheduled email digest (daily/weekly/monthly).'),
        array('id' => 'mcp_server', 'title' => 'AI / MCP Server', 'group' => 'ops', 'icon' => 'sparkles', 'description' => 'Expose the site to AI agents (Claude, etc.) via a Model Context Protocol endpoint — read tools plus confirm-gated write tools, authenticated with your REST API keys.'),

        // Email
        array('id' => 'email_tracking', 'title' => 'Email Open & Click Tracking', 'group' => 'email', 'icon' => 'eye', 'description' => 'See which emails get opened and which links get clicked. Adds a privacy-respecting tracking pixel + signed click-through links to your HTML emails, then surfaces opens &amp; clicks right in the Email Log. Requires the free Email Log.'),

        // Backup & Restore
        array('id' => 'backup_ftp', 'title' => 'Off-site Backups: FTP', 'group' => 'backup', 'icon' => 'upload', 'description' => 'Automatically upload each new database snapshot (zipped) to an FTP server.'),
        array('id' => 'backup_gdrive', 'title' => 'Off-site Backups: Google Drive', 'group' => 'backup', 'icon' => 'cloud', 'description' => 'Connect Google Drive (OAuth) and automatically upload each new database snapshot, with retention and a remote-file manager.'),
        array('id' => 'backup_pcloud', 'title' => 'Off-site Backups: pCloud', 'group' => 'backup', 'icon' => 'cloud', 'description' => 'Connect pCloud (OAuth) and automatically upload each new database snapshot, with retention and a remote-file manager.'),
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
        // is_registered() is a cheap membership test — it does NOT force-load (instantiate)
        // a lazily-registered real module just to check whether the id is taken.
        if (!$registry->is_registered($meta['id'])) {
            $registry->register(new WP_Arzo_Feature_Pro_Placeholder($meta));
        }
    }
}
