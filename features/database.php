<?php
/**
 * Database (console tab) — thin delegator.
 *
 * The free core does not include any database-browsing/query capability. This tab is
 * a gate only: WP Arzo Pro optionally registers a renderer on the
 * `wp_arzo_console_tool_provider_database` filter; when it does, we delegate to it,
 * otherwise the tab shows a generic Pro upsell. No such capability exists anywhere in
 * the free .org build.
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
        'Database',
        'This console tool is available with WP Arzo Pro.',
        'database'
    );
}
