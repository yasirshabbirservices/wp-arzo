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

// CSRF + capability guard for every state-changing site-mode operation. These can
// toggle the public site offline and write/delete the emergency recovery file, so
// they must not be forgeable cross-site.
$wp_arzo_site_mode_ops = ['update_maintenance_option', 'activate_mode', 'deactivate_mode', 'generate_emergency_script', 'delete_emergency_script'];
if (isset($_GET['operation']) && in_array($_GET['operation'], $wp_arzo_site_mode_ops, true)) {
    if (!current_user_can('manage_options') || !check_ajax_referer('wp_arzo_ajax', 'nonce', false)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Security check failed']);
        exit;
    }
}

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
    } else {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
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

// Emergency Mode generate/delete operations write the standalone recovery tool's
// credential file (a plugin-directory write) and only make sense when that tool
// ships. They live in wp-arzo-emergency/, which is stripped from the WordPress.org
// build, so include them only when the tool is present.
if (function_exists('wp_arzo_has_emergency_tool') && wp_arzo_has_emergency_tool()) {
    include WP_ARZO_PLUGIN_DIR . 'wp-arzo-emergency/site-modes-ops.php';
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
            background: var(--arzo-bg-elev);
            border-radius: 6px;
            border: 1px solid var(--arzo-border);
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
            border: 2px solid var(--accent-color, var(--arzo-accent));
            box-shadow: 0 0 20px rgba(22, 231, 145, 0.15);
            background: var(--arzo-bg-elev);
        }

        /* Active Badge */
        .status-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--accent-color, var(--arzo-accent));
            color: var(--arzo-text-on-accent);
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
            background: var(--arzo-bg-dark);
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
            color: var(--arzo-text-strong);
        }

        .mode-maintenance .mode-icon {
            color: var(--arzo-warning);
        }

        .mode-coming-soon .mode-icon {
            color: var(--arzo-success);
        }

        .mode-payment .mode-icon {
            color: var(--arzo-error);
        }

        /* Active Icon Override */
        .mode-card.active .mode-icon {
            color: var(--accent-color, var(--arzo-accent)) !important;
        }

        /* Description */
        .mode-desc {
            color: var(--arzo-text-secondary);
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 20px;
            flex-grow: 1;
        }

        /* Info Alert (Active only) */
        .info-alert {
            background: rgba(22, 231, 145, 0.1);
            border: 1px solid rgba(22, 231, 145, 0.3);
            border-radius: var(--radius-global);
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
            fill: var(--accent-color, var(--arzo-accent));
        }

        .info-alert-text {
            color: var(--accent-color, var(--arzo-accent));
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
            color: var(--arzo-text-secondary);
            font-size: 13px;
            margin-bottom: 5px;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            background: var(--arzo-bg-input);
            /* Standard Dark background */
            border: 1px solid var(--arzo-border-strong);
            /* Distinct border */
            color: var(--arzo-text-strong);
            border-radius: 4px;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: var(--accent-color, var(--arzo-accent));
            background: var(--arzo-bg-input);
            outline: none;
        }

        /* Toggle Switch */
        .switch-wrapper {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding: 10px 0;
            border-top: 1px solid var(--arzo-border);
            border-bottom: 1px solid var(--arzo-border);
        }

        .switch-label {
            margin-left: 10px;
            font-size: 13px;
            color: var(--arzo-text-secondary);
        }

        /* Buttons */
        .btn-mode {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: var(--arzo-radius);
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
            color: var(--arzo-white);
        }

        .mode-maintenance .btn-activate {
            background: var(--arzo-warning);
            color: var(--arzo-bg-dark);
        }

        .mode-maintenance .btn-activate:hover {
            background: var(--arzo-warning);
            filter: brightness(0.9);
            box-shadow: var(--arzo-shadow);
        }

        .mode-coming-soon .btn-activate {
            background: var(--arzo-success);
            color: var(--arzo-text-on-accent);
        }

        .mode-coming-soon .btn-activate:hover {
            background: var(--arzo-success);
            filter: brightness(0.9);
            box-shadow: var(--arzo-shadow);
        }

        .mode-payment .btn-activate {
            background: var(--arzo-error);
        }

        .mode-payment .btn-activate:hover {
            background: var(--arzo-error);
            filter: brightness(0.9);
            box-shadow: var(--arzo-shadow);
        }

        /* Deactivate State */
        .btn-deactivate {
            display: none;
            /* Hidden by default */
            background: var(--arzo-error);
            color: var(--arzo-text-strong);
        }

        .mode-card.active .btn-deactivate {
            display: flex;
        }

        .mode-card.active .btn-activate {
            display: none;
        }

        .btn-deactivate:hover {
            background: var(--arzo-error);
            filter: brightness(0.9);
            box-shadow: var(--arzo-shadow);
        }

        /* Preview Button */
        .btn-preview {
            display: none;
            align-items: center;
            justify-content: center;
            gap: var(--arzo-space-2);
            width: 100%;
            padding: 10px;
            background: transparent;
            border: 1px solid var(--arzo-border-strong);
            color: var(--arzo-text-secondary);
            border-radius: var(--arzo-radius-sm);
            font-size: var(--arzo-fs-xs);
            cursor: pointer;
            text-decoration: none;
        }

        .mode-card.active .btn-preview {
            display: flex;
        }

        .btn-preview:hover {
            border-color: var(--arzo-text-strong);
            color: var(--arzo-text-strong);
            background: rgba(255, 255, 255, 0.05);
        }

        /* Emergency Script Section */
        .emergency-section {
            display: none;
        }

        /* Emergency Mode — full-width card, clean stacked layout (header row,
           description, then the actions/note only when active). */
        .mode-emergency {
            border-color: var(--arzo-error) !important;
            grid-column: 1 / -1;
            /* Make it full width */
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        /* Top row: icon + title (+ ACTIVE badge) on the left, toggle on the right. */
        .mode-emergency .emergency-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .mode-emergency .card-header {
            margin-bottom: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .emergency-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 10px;
            border-radius: var(--arzo-radius-pill);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.02em;
            background: var(--arzo-error-soft);
            color: var(--arzo-error);
        }

        .emergency-badge::before {
            content: '';
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
        }

        .mode-emergency .emergency-toggle {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
        }

        .mode-emergency .mode-desc {
            margin: 0;
            max-width: 78ch;
            color: var(--arzo-text-secondary);
            font-size: 13px;
        }

        /* Body: action buttons row + explanatory note, revealed when configured. */
        .mode-emergency .emergency-body {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding-top: 14px;
            border-top: 1px solid var(--arzo-border);
        }

        .mode-emergency .active-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .emergency-note {
            margin: 0;
            font-size: 12px;
            line-height: 1.6;
            color: var(--arzo-text-secondary);
            max-width: 82ch;
        }

        .emergency-note code {
            background: var(--arzo-bg-input);
            padding: 1px 6px;
            border-radius: var(--arzo-radius-sm);
            font-size: 11px;
            color: var(--arzo-text-primary);
        }

        .btn-action {
            background: var(--arzo-bg-elev);
            border: 1px solid var(--arzo-border-strong);
            color: var(--arzo-text-primary);
            padding: 8px 14px;
            border-radius: var(--arzo-radius-sm);
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: all 0.2s;
        }

        .btn-action:hover {
            border-color: var(--arzo-accent);
            color: var(--arzo-accent);
        }

        .btn-action.success {
            border-color: var(--accent-color, var(--arzo-accent));
            color: var(--accent-color, var(--arzo-accent));
        }

        /* Loading Spinner */
        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-top: 2px solid var(--arzo-white);
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
            color: var(--arzo-error);
        }

        .mode-emergency .btn-activate {
            background: var(--arzo-error);
        }

        .mode-emergency .btn-activate:hover {
            background: var(--arzo-error);
            box-shadow: 0 4px 12px rgba(255, 77, 79, 0.3);
        }

        /* Responsive: let the head wrap and buttons stack on narrow viewports. */
        @media (max-width: 640px) {
            .mode-emergency .emergency-head {
                align-items: flex-start;
            }

            .mode-emergency .active-controls .btn-action {
                flex: 1 1 auto;
                justify-content: center;
            }
        }

        /* Settings Box */
        .settings-box {
            background: var(--arzo-bg-elev);
            padding: 25px;
            border-radius: 6px;
            border: 1px solid var(--arzo-border);
            margin-bottom: 30px;
        }

        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--arzo-bg-hover);
            color: var(--arzo-text-strong);
            padding: 12px 24px;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            border-left: 4px solid var(--accent-color, var(--arzo-accent));
            transform: translateY(100px);
            transition: transform 0.3s ease;
            z-index: 9999;
        }

        .toast-show {
            transform: translateY(0);
        }
    </style>

    <div class="content maintenance-container">
        <h1>Site Modes</h1>
        <p style="color: var(--arzo-text-secondary); margin-bottom: 30px;">Manage access to your site during maintenance or development.</p>

        <!-- Social Contact Settings -->
        <div class="settings-box">
            <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 16px;"><?php wp_arzo_icon_e('users', ['class' => 'wpa-icon wpa-hicon']); ?> Social
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
                    <?php wp_arzo_icon_e('tools', ['class' => 'wpa-icon mode-icon', 'size' => 28]); ?>
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
                        value="<?php echo esc_attr($current_mode === 'maintenance' && $custom_title !== '' ? $custom_title : 'Site Under Maintenance'); ?>">
                </div>

                <div class="form-group">
                    <label>Message</label>
                    <textarea class="form-control auto-save" rows="3"
                        data-option="maintenance_tool_custom_message"><?php echo esc_textarea($current_mode === 'maintenance' && $custom_message !== '' ? $custom_message : 'We are currently performing scheduled maintenance. Please check back soon.'); ?></textarea>
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
                    <?php wp_arzo_icon_e('check', ['size' => 15]); ?> Activate Mode
                </button>
                <button type="button" class="btn-mode btn-deactivate" onclick="deactivateMode()">
                    <?php wp_arzo_icon_e('x', ['size' => 15]); ?> Deactivate Mode
                </button>
                <a href="<?php echo esc_url(home_url('/?maintenance_preview=true')); ?>" target="_blank" class="btn-preview">
                    <?php wp_arzo_icon_e('eye', ['size' => 15]); ?> View Active Mode
                </a>
            </div>

            <!-- Coming Soon Mode -->
            <div class="mode-card mode-coming-soon <?php echo $current_mode === 'coming_soon' ? 'active' : ''; ?>"
                id="card-coming_soon">
                <div class="status-badge">ACTIVE</div>

                <div class="card-header">
                    <?php wp_arzo_icon_e('rocket', ['class' => 'wpa-icon mode-icon', 'size' => 28]); ?>
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
                        value="<?php echo esc_attr($current_mode === 'coming_soon' && $custom_title !== '' ? $custom_title : 'Coming Soon'); ?>">
                </div>

                <div class="form-group">
                    <label>Message</label>
                    <textarea class="form-control auto-save" rows="3"
                        data-option="maintenance_tool_custom_message"><?php echo esc_textarea($current_mode === 'coming_soon' && $custom_message !== '' ? $custom_message : 'Something amazing is coming soon! Stay tuned for updates.'); ?></textarea>
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
                    <?php wp_arzo_icon_e('check', ['size' => 15]); ?> Activate Mode
                </button>
                <button type="button" class="btn-mode btn-deactivate" onclick="deactivateMode()">
                    <?php wp_arzo_icon_e('x', ['size' => 15]); ?> Deactivate Mode
                </button>
                <a href="<?php echo esc_url(home_url('/?maintenance_preview=true')); ?>" target="_blank" class="btn-preview">
                    <?php wp_arzo_icon_e('eye', ['size' => 15]); ?> View Active Mode
                </a>
            </div>

            <!-- Payment Request Mode -->
            <div class="mode-card mode-payment <?php echo $current_mode === 'payment_request' ? 'active' : ''; ?>"
                id="card-payment_request">
                <div class="status-badge">ACTIVE</div>

                <div class="card-header">
                    <?php wp_arzo_icon_e('credit-card', ['class' => 'wpa-icon mode-icon', 'size' => 28]); ?>
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
                        value="<?php echo esc_attr($current_mode === 'payment_request' && $custom_title !== '' ? $custom_title : 'Payment Required'); ?>">
                </div>

                <div class="form-group">
                    <label>Message</label>
                    <textarea class="form-control auto-save" rows="3"
                        data-option="maintenance_tool_custom_message"><?php echo esc_textarea($current_mode === 'payment_request' && $custom_message !== '' ? $custom_message : 'This website has been completed but payment is still pending.'); ?></textarea>
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
                    <?php wp_arzo_icon_e('check', ['size' => 15]); ?> Activate Mode
                </button>
                <button type="button" class="btn-mode btn-deactivate" onclick="deactivateMode()">
                    <?php wp_arzo_icon_e('x', ['size' => 15]); ?> Deactivate Mode
                </button>
                <a href="<?php echo esc_url(home_url('/?maintenance_preview=true')); ?>" target="_blank" class="btn-preview">
                    <?php wp_arzo_icon_e('eye', ['size' => 15]); ?> View Active Mode
                </a>
            </div>

            <!-- Emergency Mode (self-hosted build only; the recovery tool is stripped from the WordPress.org build) -->
            <?php if (function_exists('wp_arzo_has_emergency_tool') && wp_arzo_has_emergency_tool()) : ?>
            <div class="mode-card mode-emergency <?php echo $emergency_configured ? 'active' : ''; ?>" id="card-emergency">
                <div class="emergency-head">
                    <div class="card-header">
                        <?php wp_arzo_icon_e('heartbeat', ['class' => 'wpa-icon mode-icon', 'size' => 28]); ?>
                        <h3>Emergency Mode</h3>
                        <span class="emergency-badge" id="emergency-badge"<?php echo $emergency_configured ? '' : ' style="display:none;"'; ?>>ACTIVE</span>
                    </div>
                    <label class="switch emergency-toggle">
                        <input type="checkbox" id="emergency-toggle"
                            onchange="toggleEmergencyMode(this.checked)"
                            <?php echo $emergency_configured ? 'checked' : ''; ?>>
                        <span class="slider round"></span>
                    </label>
                </div>

                <p class="mode-desc">Standalone recovery script to access your site if WordPress breaks (WSOD, plugin conflicts, etc.).</p>

                <div class="emergency-body" id="emergency-body"<?php echo $emergency_configured ? '' : ' style="display:none;"'; ?>>
                    <div class="active-controls" id="emergency-active-controls">
                        <button type="button" class="btn-action" onclick="copyToClipboard('<?php echo esc_js(home_url('/wp-arzo/emergency/')); ?>', this)">
                            <?php wp_arzo_icon_e('link', ['size' => 15]); ?> Copy Link
                        </button>
                        <button type="button" class="btn-action" title="Works even when WordPress rewrites are down" onclick="copyToClipboard('<?php echo esc_js(WP_ARZO_PLUGIN_URL . 'wp-arzo-emergency/index.php'); ?>', this)">
                            <?php wp_arzo_icon_e('shield', ['size' => 15]); ?> Copy Direct Link
                        </button>
                        <button type="button" class="btn-action" onclick="resetEmergencyPassword(this)">
                            <?php wp_arzo_icon_e('key', ['size' => 15]); ?> Reset Password
                        </button>
                    </div>
                    <p class="emergency-note">
                        <?php wp_arzo_icon_e('shield', ['class' => 'wpa-icon wpa-hicon']); ?> <strong>Direct Link</strong> is the file URL — bookmark it. It keeps working even when WordPress is fully down (WSOD) and the pretty <code>/wp-arzo/emergency/</code> rewrite can't load.
                    </p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast-notification">Settings saved</div>

    <script>
        const baseUrl = '<?php echo esc_url(admin_url('admin-ajax.php?action=wp_arzo_standalone')); ?>&tab=site_modes&nonce=<?php echo esc_js(wp_create_nonce('wp_arzo_ajax')); ?>';
        let currentMode = '<?php echo esc_js($current_mode); ?>';
        const emergencyPrettyUrl = '<?php echo esc_js(home_url('/wp-arzo/emergency/')); ?>';
        const emergencyDirectUrl = '<?php echo esc_js(WP_ARZO_PLUGIN_URL . 'wp-arzo-emergency/index.php'); ?>';

        // Build an action button for the emergency card.
        function makeEmergencyBtn(iconSvg, label, onClick) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn-action';
            btn.innerHTML = iconSvg + ' ' + label;
            btn.onclick = onClick;
            return btn;
        }

        // --- Emergency Mode Logic ---
        function toggleEmergencyMode(isChecked) {
            const card = document.getElementById('card-emergency');
            const badge = document.getElementById('emergency-badge');
            const body = document.getElementById('emergency-body');
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
                            card.classList.add('active');
                            badge.style.display = '';
                            body.style.display = '';

                            // Rebuild the action buttons (Copy Link / Direct Link / Password).
                            activeControls.innerHTML = '';
                            activeControls.appendChild(makeEmergencyBtn(<?php echo json_encode(wp_arzo_icon('link', ['size' => 15])); ?>, 'Copy Link', function() {
                                copyToClipboard(data.url || emergencyPrettyUrl, this);
                            }));
                            activeControls.appendChild(makeEmergencyBtn(<?php echo json_encode(wp_arzo_icon('shield', ['size' => 15])); ?>, 'Copy Direct Link', function() {
                                copyToClipboard(emergencyDirectUrl, this);
                            }));
                            activeControls.appendChild(makeEmergencyBtn(<?php echo json_encode(wp_arzo_icon('key', ['size' => 15])); ?>, 'Copy Password', function() {
                                copyToClipboard(data.password, this);
                            }));
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
                            card.classList.remove('active');
                            badge.style.display = 'none';
                            body.style.display = 'none';
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
                        btn.innerHTML = <?php echo json_encode(wp_arzo_icon('key', ['size' => 15])); ?> + ' Copy Password';
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
                btn.innerHTML = <?php echo json_encode(wp_arzo_icon('check', ['size' => 15])); ?> + ' Copied!';
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
