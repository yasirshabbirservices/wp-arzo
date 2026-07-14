<?php
/**
 * Files (console tab) — thin delegator.
 *
 * The free core does not include any file-browsing/editing capability. This tab is a
 * gate only: WP Arzo Pro optionally registers a renderer on the
 * `wp_arzo_console_tool_provider_files` filter; when it does, we delegate to it,
 * otherwise the tab shows a generic Pro upsell (and connector/download requests get a
 * 403). No such capability exists anywhere in the free .org build.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

$wp_arzo_files_provider = function_exists('wp_arzo_console_tool_provider')
    ? wp_arzo_console_tool_provider('files')
    : null;

// A raw connector / download request (served before HTML by the router).
$wp_arzo_files_is_op = (isset($_GET['operation']) && $_GET['operation'] === 'elfinder_connector')
    || isset($_GET['download']);

if ($wp_arzo_files_provider) {
    call_user_func($wp_arzo_files_provider, $wp_arzo_files_is_op);
    if ($wp_arzo_files_is_op) {
        exit;
    }
    return;
}

// No provider — Pro not installed/licensed.
if ($wp_arzo_files_is_op) {
    header('Content-Type: application/json');
    status_header(403);
    echo json_encode(array('error' => array('This tool is available with WP Arzo Pro.')));
    exit;
}

if (function_exists('wp_arzo_console_pro_upsell')) {
    wp_arzo_console_pro_upsell(
        'Files',
        'This console tool is available with WP Arzo Pro.',
        'folder'
    );
}
