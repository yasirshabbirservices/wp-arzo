<?php
/**
 * WP Arzo Standalone Loader Helper
 * Loads the HTML structure and routes to modular features
 *
 * @package WP_Arzo
 * @version 5.1
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// This file includes everything from standalone EXCEPT the switch statement
// It loads the structure, then includes the modular feature file

// Read the standalone file and output everything up to the switch statement
$standalone_file = file_get_contents(WP_ARZO_PLUGIN_DIR . 'includes/wp-arzo-standalone.php');

// Find where the switch statement starts
$switch_start_marker = 'switch ($action) {';
$switch_pos = strpos($standalone_file, $switch_start_marker);

if ($switch_pos !== false) {
    // Output everything before the switch
    $before_switch = substr($standalone_file, 0, $switch_pos);

    // Execute the PHP code before switch
    eval('?>' . $before_switch);

    // Now include the modular feature file
    $feature_files = [
        'info' => 'site-info.php',
    ];

    $current_tab = $_GET['tab'] ?? $_GET['action'] ?? 'info';
    if ($current_tab === 'wp_arzo_standalone') {
        $current_tab = 'info';
    }

    if (isset($feature_files[$current_tab])) {
        include(WP_ARZO_PLUGIN_DIR . 'features/' . $feature_files[$current_tab]);
    }

    // Close the container and body
    echo '</div>'; // Close container
    echo '</body>';
    echo '</html>';
} else {
    // Fallback to full standalone
    include(WP_ARZO_PLUGIN_DIR . 'includes/wp-arzo-standalone.php');
}
