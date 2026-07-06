<?php

/**
 * Emergency Mode AJAX operations for the Advanced Tools → Site Modes console.
 *
 * Extracted from features/site-modes.php so it ships ONLY with the standalone
 * emergency-recovery tool. Both live under wp-arzo-emergency/, which .distignore
 * strips from the WordPress.org build (the tool writes the credential file that
 * the recovery script reads while WordPress is down — a plugin-directory write
 * that only makes sense alongside that tool). features/site-modes.php includes
 * this file only when wp_arzo_has_emergency_tool() is true.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle Emergency Script Generation
if (isset($_GET['operation']) && $_GET['operation'] === 'generate_emergency_script') {
    header('Content-Type: application/json');
    $password = isset($_POST['password']) ? $_POST['password'] : wp_generate_password(20, true, true);
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $config_file = WP_ARZO_PLUGIN_DIR . 'arzo-safe.php';
    $config_content = "<?php\n// WP Arzo Emergency Config\n// DO NOT EDIT MANUALLY\ndefine('WP_ARZO_EMERGENCY_HASH', '$hash');\n";

    if (file_put_contents($config_file, $config_content)) {
        $script_url = home_url('/wp-arzo/emergency/');
        echo json_encode(['success' => true, 'url' => $script_url, 'password' => $password]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to write config file.']);
    }
    exit;
}

// Handle Emergency Script Deletion
if (isset($_GET['operation']) && $_GET['operation'] === 'delete_emergency_script') {
    header('Content-Type: application/json');
    $config_file = WP_ARZO_PLUGIN_DIR . 'arzo-safe.php';
    if (file_exists($config_file)) {
        if (unlink($config_file)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete config file.']);
        }
    } else {
        echo json_encode(['success' => true]); // Already gone
    }
    exit;
}
