<?php
/**
 * File Manager (console tab) — thin delegator.
 *
 * The File Manager is powered by elFinder, a large third-party library. To keep the
 * free .org core small and reduce its security/audit surface, the library and its
 * connector now ship with WP Arzo Pro, which registers a renderer on the
 * `wp_arzo_console_tool_provider_files` filter. When Pro provides it we delegate;
 * otherwise the tab shows a Pro upsell (and connector/download requests get a 403).
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
    echo json_encode(array('error' => array('The File Manager is a WP Arzo Pro feature.')));
    exit;
}

if (function_exists('wp_arzo_console_pro_upsell')) {
    wp_arzo_console_pro_upsell(
        'File Manager',
        'Browse, edit, upload and download files across your WordPress install — right from the console.',
        'folder'
    );
}
