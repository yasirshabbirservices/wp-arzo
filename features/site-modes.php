<?php

/**
 * Maintenance Mode Feature
 *
 * @package WP_Arzo
 * @version 6.0
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// --- AJAX Handlers ---

// Handle Option Updates (Auto-save)
if (isset($_GET['operation']) && $_GET['operation'] === 'update_maintenance_option') {
    header('Content-Type: application/json');

    if (isset($_POST['option_name']) && isset($_POST['option_value'])) {
        $option = sanitize_text_field($_POST['option_name']);
        // Allow HTML for message, strict for others
        if ($option === 'maintenance_tool_custom_message') {
            $value = wp_kses_post($_POST['option_value']);
        } else {
            $value = sanitize_text_field($_POST['option_value']);
        }

        // whitelist options
        $allowed = [
            'maintenance_tool_show_social_contacts',
            'maintenance_tool_custom_title',
            'maintenance_tool_custom_message',
            'maintenance_tool_developer_email',
            'maintenance_tool_developer_phone',
            'maintenance_tool_developer_whatsapp',
            'maintenance_tool_developer_skype'
        ];

        if (in_array($option, $allowed)) {
            update_option($option, $value);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid option']);
        }
    }
    exit;
}

// Handle Activation
if (isset($_GET['operation']) && $_GET['operation'] === 'activate_mode') {
    header('Content-Type: application/json');
    if (isset($_POST['mode'])) {
        $mode = sanitize_text_field($_POST['mode']);
        update_option('maintenance_tool_active_mode', $mode);
        echo json_encode(['success' => true]);
    }
    exit;
}

// Handle Deactivation
if (isset($_GET['operation']) && $_GET['operation'] === 'deactivate_mode') {
    header('Content-Type: application/json');
    delete_option('maintenance_tool_active_mode');
    echo json_encode(['success' => true]);
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

// ---------------------

function handleMaintenanceModes()
{
    // Get current values
    $current_mode = get_option('maintenance_tool_active_mode', '');
    $custom_message = get_option('maintenance_tool_custom_message', '');
    $custom_title = get_option('maintenance_tool_custom_title', '');
    $show_social_contacts = get_option('maintenance_tool_show_social_contacts', 1);

    // Social contacts
    $developer_email = get_option('maintenance_tool_developer_email', '');
    $developer_phone = get_option('maintenance_tool_developer_phone', '');
    $developer_whatsapp = get_option('maintenance_tool_developer_whatsapp', '');
    $developer_skype = get_option('maintenance_tool_developer_skype', '');

    // Emergency Script Status
    // Force check file existence to ensure toggle state is accurate on reload
    clearstatcache();
    $emergency_configured = file_exists(WP_ARZO_PLUGIN_DIR . 'arzo-safe.php');

?>
    <style>
        /* Main Container & Grid */
        .maintenance-container {
            max-width: 1200px;
            margin: 20px 0;
        }

        .mode-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        /* Card Styling (Plugin Colors) */
        .mode-card {
            background: #252525;
            border-radius: 6px;
            border: 1px solid #333;
            padding: 25px;
            position: relative;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .mode-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        /* Active State */
        .mode-card.active {
            border: 2px solid var(--accent-color, #16e791);
            box-shadow: 0 0 20px rgba(22, 231, 145, 0.15);
            background: #252525;
        }

        /* Active Badge */
        .status-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--accent-color, #16e791);
            color: #000;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 5px;
            animation: pulse 2s infinite;
            display: none;
            /* Hidden by default */
        }

        .mode-card.active .status-badge {
            display: flex;
        }

        .status-badge::before {
            content: '';
            width: 6px;
            height: 6px;
            background: #000;
            border-radius: 50%;
            animation: blink 1.5s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(22, 231, 145, 0.7);
            }

            50% {
                box-shadow: 0 0 0 8px rgba(22, 231, 145, 0);
            }
        }

        @keyframes blink {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.3;
            }
        }

        /* Header & Icon */
        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }

        .mode-icon {
            font-size: 20px;
        }

        .card-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #fff;
        }

        .mode-maintenance .mode-icon {
            color: #ff9800;
        }

        .mode-coming-soon .mode-icon {
            color: #4CAF50;
        }

        .mode-payment .mode-icon {
            color: #dc3545;
        }

        /* Active Icon Override */
        .mode-card.active .mode-icon {
            color: var(--accent-color, #16e791) !important;
        }

        /* Description */
        .mode-desc {
            color: #999;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 20px;
            flex-grow: 1;
        }

        /* Info Alert (Active only) */
        .info-alert {
            background: rgba(22, 231, 145, 0.1);
            border: 1px solid rgba(22, 231, 145, 0.3);
            border-radius: 3px;
            padding: 12px;
            margin-bottom: 20px;
            display: none;
            align-items: center;
            gap: 10px;
        }

        .mode-card.active .info-alert {
            display: flex;
        }

        .info-alert svg {
            width: 16px;
            height: 16px;
            fill: var(--accent-color, #16e791);
        }

        .info-alert-text {
            color: var(--accent-color, #16e791);
            font-size: 12px;
        }

        /* Header Icon Spacing */
        .settings-box h3 i {
            margin-right: 10px;
        }

        /* Form Groups - IMproved Inputs */
        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            color: #ccc;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            background: #151515;
            /* Standard Dark background */
            border: 1px solid #444;
            /* Distinct border */
            color: #fff;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: var(--accent-color, #16e791);
            background: #1a1a1a;
            outline: none;
        }

        /* Toggle Switch */
        .switch-wrapper {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding: 10px 0;
            border-top: 1px solid #333;
            border-bottom: 1px solid #333;
        }

        .switch-label {
            margin-left: 10px;
            font-size: 13px;
            color: #ccc;
        }

        /* Buttons */
        .btn-mode {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 10px;
        }

        /* Activate States */
        .btn-activate {
            color: #fff;
        }

        .mode-maintenance .btn-activate {
            background: #ff9800;
        }

        .mode-maintenance .btn-activate:hover {
            background: #f57c00;
            box-shadow: 0 4px 12px rgba(255, 152, 0, 0.3);
        }

        .mode-coming-soon .btn-activate {
            background: #4CAF50;
        }

        .mode-coming-soon .btn-activate:hover {
            background: #388E3C;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }

        .mode-payment .btn-activate {
            background: #dc3545;
        }

        .mode-payment .btn-activate:hover {
            background: #c82333;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        /* Deactivate State */
        .btn-deactivate {
            display: none;
            /* Hidden by default */
            background: #dc3545;
            color: #fff;
        }

        .mode-card.active .btn-deactivate {
            display: flex;
        }

        .mode-card.active .btn-activate {
            display: none;
        }

        .btn-deactivate:hover {
            background: #c82333;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        /* Preview Button */
        .btn-preview {
            display: none;
            width: 100%;
            padding: 10px;
            background: transparent;
            border: 1px solid #444;
            color: #999;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
        }

        .mode-card.active .btn-preview {
            display: block;
        }

        .btn-preview:hover {
            border-color: #fff;
            color: #fff;
            background: rgba(255, 255, 255, 0.05);
        }

        /* Emergency Script Section */
        .emergency-section {
            display: none;
        }

        /* Emergency Mode Specifics */
        .mode-emergency {
            border-color: #ff4d4d !important;
            grid-column: 1 / -1;
            /* Make it full width */
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .mode-emergency .card-header {
            margin-bottom: 5px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .mode-emergency .mode-desc {
            margin-bottom: 0;
            margin-right: auto;
            max-width: 500px;
            color: #999;
            font-size: 13px;
        }

        .mode-emergency .controls-container {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-left: auto;
        }

        .mode-emergency .active-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-action {
            background: #2A2A2A;
            border: 1px solid #444;
            color: #ccc;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }

        .btn-action:hover {
            border-color: #fff;
            color: #fff;
        }

        .btn-action.success {
            border-color: var(--accent-color, #16e791);
            color: var(--accent-color, #16e791);
        }

        /* Loading Spinner */
        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-top: 2px solid #fff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: inline-block;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .mode-emergency .mode-icon {
            color: #ff4d4d;
        }

        .mode-emergency .btn-activate {
            background: #ff4d4d;
        }

        .mode-emergency .btn-activate:hover {
            background: #cc0000;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        .emergency-active-link {
            display: inline-block;
            margin-top: 0;
            padding: 10px;
            background: rgba(255, 77, 77, 0.1);
            border: 1px solid #ff4d4d;
            border-radius: 4px;
            color: #ff4d4d;
            text-decoration: none;
            word-break: break-all;
            font-size: 12px;
        }

        .emergency-active-link:hover {
            background: rgba(255, 77, 77, 0.2);
        }

        /* Responsive adjustments for emergency card */
        @media (max-width: 900px) {
            .mode-emergency {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .mode-emergency .controls-container {
                width: 100%;
                justify-content: space-between;
                margin-left: 0;
                flex-wrap: wrap;
            }

            .mode-emergency .active-controls {
                width: 100%;
                flex-wrap: wrap;
            }
        }

        /* Settings Box */
        .settings-box {
            background: #252525;
            padding: 25px;
            border-radius: 6px;
            border: 1px solid #333;
            margin-bottom: 30px;
        }

        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #333;
            color: #fff;
            padding: 12px 24px;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            border-left: 4px solid var(--accent-color, #16e791);
            transform: translateY(100px);
            transition: transform 0.3s ease;
            z-index: 9999;
        }

        .toast-show {
            transform: translateY(0);
        }
    </style>

    <div class="content maintenance-container">
        <h2>Site Modes</h2>
        <p style="color: #999; margin-bottom: 30px;">Manage access to your site during maintenance or development.</p>

        <!-- Social Contact Settings -->
        <div class="settings-box">
            <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 16px;"><i class="fas fa-address-book"></i> Social
                Contact Settings</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" class="form-control auto-save" data-option="maintenance_tool_developer_email"
                        value="<?php echo esc_attr($developer_email); ?>" placeholder="support@example.com">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" class="form-control auto-save" data-option="maintenance_tool_developer_phone"
                        value="<?php echo esc_attr($developer_phone); ?>" placeholder="+1234567890">
                </div>
                <div class="form-group">
                    <label>WhatsApp Number</label>
                    <input type="text" class="form-control auto-save" data-option="maintenance_tool_developer_whatsapp"
                        value="<?php echo esc_attr($developer_whatsapp); ?>" placeholder="+1234567890">
                </div>
                <div class="form-group">
                    <label>Skype Username</label>
                    <input type="text" class="form-control auto-save" data-option="maintenance_tool_developer_skype"
                        value="<?php echo esc_attr($developer_skype); ?>" placeholder="skype_username">
                </div>
            </div>
        </div>

        <!-- Mode Grid -->
        <div class="mode-grid">

            <!-- Maintenance Mode -->
            <div class="mode-card mode-maintenance <?php echo $current_mode === 'maintenance' ? 'active' : ''; ?>"
                id="card-maintenance">
                <div class="status-badge">ACTIVE</div>

                <div class="card-header">
                    <i class="fas fa-tools mode-icon"></i>
                    <h3>Maintenance Mode</h3>
                </div>

                <p class="mode-desc">Standard maintenance mode. Returns 503 Service Unavailable status code.</p>

                <div class="info-alert">
                    <svg viewBox="0 0 20 20">
                        <path
                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" />
                    </svg>
                    <span class="info-alert-text">Your site is currently in maintenance mode.</span>
                </div>

                <div class="form-group">
                    <label>Page Title</label>
                    <input type="text" class="form-control auto-save" data-option="maintenance_tool_custom_title"
                        value="<?php echo $current_mode === 'maintenance' ? esc_attr($custom_title) : 'Site Under Maintenance'; ?>">
                </div>

                <div class="form-group">
                    <label>Message</label>
                    <textarea class="form-control auto-save" rows="3"
                        data-option="maintenance_tool_custom_message"><?php echo $current_mode === 'maintenance' ? esc_textarea($custom_message) : 'We are currently performing scheduled maintenance. Please check back soon.'; ?></textarea>
                </div>

                <div class="switch-wrapper">
                    <label class="switch">
                        <input type="checkbox"
                            onchange="toggleOption('maintenance_tool_show_social_contacts', this.checked)" <?php echo $show_social_contacts ? 'checked' : ''; ?>>
                        <span class="slider round"></span>
                    </label>
                    <span class="switch-label">Show Social Contacts</span>
                </div>

                <button type="button" class="btn-mode btn-activate" onclick="toggleMode('maintenance')">
                    <i class="fas fa-check"></i> Activate Mode
                </button>
                <button type="button" class="btn-mode btn-deactivate" onclick="deactivateMode()">
                    <i class="fas fa-times"></i> Deactivate Mode
                </button>
                <a href="<?php echo home_url('/?maintenance_preview=true'); ?>" target="_blank" class="btn-preview">
                    <i class="fas fa-eye"></i> View Active Mode
                </a>
            </div>

            <!-- Coming Soon Mode -->
            <div class="mode-card mode-coming-soon <?php echo $current_mode === 'coming_soon' ? 'active' : ''; ?>"
                id="card-coming_soon">
                <div class="status-badge">ACTIVE</div>

                <div class="card-header">
                    <i class="fas fa-rocket mode-icon"></i>
                    <h3>Coming Soon Mode</h3>
                </div>

                <p class="mode-desc">For new site launches. Returns 200 OK status code but prevents indexing.</p>

                <div class="info-alert">
                    <svg viewBox="0 0 20 20">
                        <path
                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" />
                    </svg>
                    <span class="info-alert-text">Your site is currently in Coming Soon mode.</span>
                </div>

                <div class="form-group">
                    <label>Page Title</label>
                    <input type="text" class="form-control auto-save" data-option="maintenance_tool_custom_title"
                        value="<?php echo $current_mode === 'coming_soon' ? esc_attr($custom_title) : 'Coming Soon'; ?>">
                </div>

                <div class="form-group">
                    <label>Message</label>
                    <textarea class="form-control auto-save" rows="3"
                        data-option="maintenance_tool_custom_message"><?php echo $current_mode === 'coming_soon' ? esc_textarea($custom_message) : 'Something amazing is coming soon! Stay tuned for updates.'; ?></textarea>
                </div>

                <div class="switch-wrapper">
                    <label class="switch">
                        <input type="checkbox"
                            onchange="toggleOption('maintenance_tool_show_social_contacts', this.checked)" <?php echo $show_social_contacts ? 'checked' : ''; ?>>
                        <span class="slider round"></span>
                    </label>
                    <span class="switch-label">Show Social Contacts</span>
                </div>

                <button type="button" class="btn-mode btn-activate" onclick="toggleMode('coming_soon')">
                    <i class="fas fa-check"></i> Activate Mode
                </button>
                <button type="button" class="btn-mode btn-deactivate" onclick="deactivateMode()">
                    <i class="fas fa-times"></i> Deactivate Mode
                </button>
                <a href="<?php echo home_url('/?maintenance_preview=true'); ?>" target="_blank" class="btn-preview">
                    <i class="fas fa-eye"></i> View Active Mode
                </a>
            </div>

            <!-- Payment Request Mode -->
            <div class="mode-card mode-payment <?php echo $current_mode === 'payment_request' ? 'active' : ''; ?>"
                id="card-payment_request">
                <div class="status-badge">ACTIVE</div>

                <div class="card-header">
                    <i class="fas fa-credit-card mode-icon"></i>
                    <h3>Payment Required</h3>
                </div>

                <p class="mode-desc">For pending payments. Returns 402 Payment Required status code.</p>

                <div class="info-alert">
                    <svg viewBox="0 0 20 20">
                        <path
                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" />
                    </svg>
                    <span class="info-alert-text">Your site is currently in Payment Required mode.</span>
                </div>

                <div class="form-group">
                    <label>Page Title</label>
                    <input type="text" class="form-control auto-save" data-option="maintenance_tool_custom_title"
                        value="<?php echo $current_mode === 'payment_request' ? esc_attr($custom_title) : 'Payment Required'; ?>">
                </div>

                <div class="form-group">
                    <label>Message</label>
                    <textarea class="form-control auto-save" rows="3"
                        data-option="maintenance_tool_custom_message"><?php echo $current_mode === 'payment_request' ? esc_textarea($custom_message) : 'This website has been completed but payment is still pending.'; ?></textarea>
                </div>

                <div class="switch-wrapper">
                    <label class="switch">
                        <input type="checkbox"
                            onchange="toggleOption('maintenance_tool_show_social_contacts', this.checked)" <?php echo $show_social_contacts ? 'checked' : ''; ?>>
                        <span class="slider round"></span>
                    </label>
                    <span class="switch-label">Show Social Contacts</span>
                </div>

                <button type="button" class="btn-mode btn-activate" onclick="toggleMode('payment_request')">
                    <i class="fas fa-check"></i> Activate Mode
                </button>
                <button type="button" class="btn-mode btn-deactivate" onclick="deactivateMode()">
                    <i class="fas fa-times"></i> Deactivate Mode
                </button>
                <a href="<?php echo home_url('/?maintenance_preview=true'); ?>" target="_blank" class="btn-preview">
                    <i class="fas fa-eye"></i> View Active Mode
                </a>
            </div>

            <!-- Emergency Mode -->
            <div class="mode-card mode-emergency <?php echo $emergency_configured ? 'active' : ''; ?>" id="card-emergency">
                <?php if ($emergency_configured): ?>
                    <div class="status-badge" style="background: #ff4d4d; color: #fff;">ACTIVE</div>
                <?php endif; ?>

                <div style="display: flex; flex-direction: column; gap: 5px;">
                    <div class="card-header">
                        <i class="fas fa-ambulance mode-icon"></i>
                        <h3>Emergency Mode</h3>
                    </div>
                    <p class="mode-desc">Standalone recovery script to access your site if WordPress breaks (WSOD, plugin conflicts, etc.).</p>
                </div>

                <div class="controls-container">
                    <?php if ($emergency_configured): ?>
                        <div class="active-controls" id="emergency-active-controls">
                            <button type="button" class="btn-action" onclick="copyToClipboard('<?php echo home_url('/wp-arzo/emergency/'); ?>', this)">
                                <i class="fas fa-link"></i> Copy Link
                            </button>
                            <button type="button" class="btn-action" onclick="resetEmergencyPassword(this)">
                                <i class="fas fa-key"></i> Reset Password
                            </button>
                        </div>
                    <?php else: ?>
                        <div id="emergency-inactive-controls">
                            <!-- Placeholder for consistency -->
                        </div>
                    <?php endif; ?>

                    <div class="switch-wrapper" style="margin: 0; padding: 0; border: none;">
                        <label class="switch">
                            <input type="checkbox" id="emergency-toggle"
                                onchange="toggleEmergencyMode(this.checked)"
                                <?php echo $emergency_configured ? 'checked' : ''; ?>>
                            <span class="slider round"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast-notification">Settings saved</div>

    <script>
        const baseUrl = '<?php echo admin_url('admin-ajax.php?action=wp_arzo_standalone'); ?>&tab=site_modes';
        let currentMode = '<?php echo $current_mode; ?>';

        // --- Emergency Mode Logic ---
        function toggleEmergencyMode(isChecked) {
            const controlsContainer = document.querySelector('.controls-container');
            const activeControls = document.getElementById('emergency-active-controls');

            // Show loading state
            const toggle = document.getElementById('emergency-toggle');
            const originalDisplay = toggle.style.display;
            toggle.style.display = 'none';
            const spinner = document.createElement('span');
            spinner.className = 'spinner';
            toggle.parentElement.appendChild(spinner);

            if (isChecked) {
                // Generate Script
                fetch(`${baseUrl}&operation=generate_emergency_script`, {
                        method: 'POST'
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Emergency Mode Activated');
                            document.getElementById('card-emergency').classList.add('active');

                            // Create persistent buttons dynamically
                            const btnContainer = document.createElement('div');
                            btnContainer.className = 'active-controls';
                            btnContainer.id = 'emergency-active-controls';

                            // Copy Link Button
                            const btnLink = document.createElement('button');
                            btnLink.className = 'btn-action';
                            btnLink.innerHTML = '<i class="fas fa-link"></i> Copy Link';
                            btnLink.onclick = function() {
                                copyToClipboard(data.url, this);
                            };

                            // Copy Password Button
                            const btnPass = document.createElement('button');
                            btnPass.className = 'btn-action';
                            btnPass.innerHTML = '<i class="fas fa-key"></i> Copy Password';
                            btnPass.onclick = function() {
                                copyToClipboard(data.password, this);
                            };

                            btnContainer.appendChild(btnLink);
                            btnContainer.appendChild(btnPass);

                            // Replace or Append
                            const existing = document.getElementById('emergency-active-controls');
                            if (existing) existing.remove();

                            controlsContainer.insertBefore(btnContainer, controlsContainer.lastElementChild);
                        } else {
                            alert('Error: ' + data.message);
                            toggle.checked = false;
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Request failed');
                        toggle.checked = false;
                    })
                    .finally(() => {
                        spinner.remove();
                        toggle.style.display = originalDisplay;
                    });
            } else {
                // Deactivate / Delete Script
                if (!confirm('Are you sure? This will delete the recovery script.')) {
                    toggle.checked = true;
                    spinner.remove();
                    toggle.style.display = originalDisplay;
                    return;
                }

                fetch(`${baseUrl}&operation=delete_emergency_script`, {
                        method: 'POST'
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Emergency Mode Deactivated');
                            document.getElementById('card-emergency').classList.remove('active');
                            const controls = document.getElementById('emergency-active-controls');
                            if (controls) controls.remove();
                        } else {
                            alert('Error: ' + data.message);
                            toggle.checked = true;
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        toggle.checked = true;
                    })
                    .finally(() => {
                        spinner.remove();
                        toggle.style.display = originalDisplay;
                    });
            }
        }

        function resetEmergencyPassword(btn) {
            if (!confirm('This will generate a new password. The old one will stop working. Continue?')) return;

            // Show loading state on button
            const originalContent = btn.innerHTML;
            btn.innerHTML = '<span class="spinner" style="width:12px;height:12px;border-width:1px;"></span>';
            btn.disabled = true;

            fetch(`${baseUrl}&operation=generate_emergency_script`, {
                    method: 'POST'
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('Password Reset Successfully');

                        // Transform button to Copy Password
                        btn.innerHTML = '<i class="fas fa-key"></i> Copy Password';
                        btn.disabled = false;
                        btn.onclick = function() {
                            copyToClipboard(data.password, this);
                        };
                    } else {
                        alert('Error: ' + data.message);
                        btn.innerHTML = originalContent;
                        btn.disabled = false;
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Request failed');
                    btn.innerHTML = originalContent;
                    btn.disabled = false;
                });
        }

        function copyToClipboard(text, btn) {
            navigator.clipboard.writeText(text).then(() => {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                btn.classList.add('success');
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.classList.remove('success');
                }, 2000);
            }).catch(err => {
                console.error('Copy failed', err);
                alert('Failed to copy');
            });
        }

        // --- Toast ---
        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.classList.add('toast-show');
            setTimeout(() => {
                toast.classList.remove('toast-show');
            }, 3000);
        }

        // --- Toggle Option (Switch) ---
        function toggleOption(optionName, isChecked) {
            const formData = new FormData();
            formData.append('option_name', optionName);
            formData.append('option_value', isChecked ? '1' : '0');

            fetch(`${baseUrl}&operation=update_maintenance_option`, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('Setting updated');
                        document.querySelectorAll(`input[onchange*="${optionName}"]`).forEach(el => el.checked = isChecked);
                    } else {
                        console.error('Update failed:', data.message);
                    }
                })
                .catch(err => console.error('AJAX Error:', err));
        }

        // --- Auto Save (Debounced) ---
        let timeoutId;
        document.querySelectorAll('.auto-save').forEach(input => {
            input.addEventListener('input', function() {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => {
                    const option = this.dataset.option;
                    const value = this.value;

                    const formData = new FormData();
                    formData.append('option_name', option);
                    formData.append('option_value', value);

                    fetch(`${baseUrl}&operation=update_maintenance_option`, {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) showToast('Saved');
                        })
                        .catch(err => console.error('Save Error:', err));
                }, 800); // 800ms debounce
            });
        });

        // --- Mode Activation/Deactivation ---
        function toggleMode(mode) {
            if (currentMode === mode) return; // Already active

            // Activate
            const formData = new FormData();
            formData.append('mode', mode);

            fetch(`${baseUrl}&operation=activate_mode`, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        currentMode = mode;
                        updateUIState();
                        showToast(mode.replace('_', ' ').toUpperCase() + ' activated');
                    } else {
                        alert('Failed to activate: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(err => {
                    console.error('Activation Error:', err);
                    alert('Error activating mode. Check console.');
                });
        }

        function deactivateMode() {
            fetch(`${baseUrl}&operation=deactivate_mode`, {
                    method: 'POST'
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        currentMode = '';
                        updateUIState();
                        showToast('Maintenance mode deactivated');
                    } else {
                        alert('Failed to deactivate');
                    }
                })
                .catch(err => {
                    console.error('Deactivation Error:', err);
                    alert('Error deactivating mode.');
                });
        }

        function updateUIState() {
            // Reset all cards
            document.querySelectorAll('.mode-card').forEach(card => {
                card.classList.remove('active');
            });

            // Activate target card
            if (currentMode) {
                const activeCard = document.getElementById('card-' + currentMode);
                if (activeCard) {
                    activeCard.classList.add('active');
                }
            }
        }
    </script>
<?php
}

// Call the function
handleMaintenanceModes();
