<?php
/**
 * WP Arzo Main Loader
 * Routes requests to modular feature files
 *
 * @package WP_Arzo
 * @version 5.1
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Load the standalone file for now (we'll gradually replace parts)
// Get current tab/action
$current_tab = $_GET['tab'] ?? $_GET['action'] ?? 'info';
if ($current_tab === 'wp_arzo_standalone') {
    $current_tab = 'info';
}

// Check if modular version exists for this feature
$feature_files = [
    'info' => 'site-info.php',
    // Add more as we extract them
];

// If modular version exists, use it
if (isset($feature_files[$current_tab]) && file_exists(WP_ARZO_PLUGIN_DIR . 'features/' . $feature_files[$current_tab])) {
    // Load standalone file for structure (header, nav, etc) but stop before switch
    include_once(WP_ARZO_PLUGIN_DIR . 'includes/wp-arzo-standalone-loader-helper.php');
} else {
    // Fall back to full standalone file
    include_once(WP_ARZO_PLUGIN_DIR . 'includes/wp-arzo-standalone.php');
}
