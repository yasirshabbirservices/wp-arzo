<?php
/**
 * Database Manager (console tab) — thin delegator.
 *
 * The Database manager is powered by AdminNeo (a bundled Adminer fork). To keep the
 * free .org core small and reduce its security/audit surface, the library and its
 * WP-gated loader now ship with WP Arzo Pro, which registers a renderer on the
 * `wp_arzo_console_tool_provider_database` filter. When Pro provides it we delegate;
 * otherwise the tab shows a Pro upsell.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

$wp_arzo_db_provider = function_exists('wp_arzo_console_tool_provider')
    ? wp_arzo_console_tool_provider('database')
    : null;

if ($wp_arzo_db_provider) {
    call_user_func($wp_arzo_db_provider);
    return;
}

if (function_exists('wp_arzo_console_pro_upsell')) {
    wp_arzo_console_pro_upsell(
        'Database Manager',
        'A full database manager — browse and edit tables, export/import, and run SQL, auto-connected with your site credentials.',
        'database'
    );
}
