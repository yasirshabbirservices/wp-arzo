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
    if (isset($_POST['password'])) {
        $password = $_POST['password'];
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        $config_file = WP_ARZO_PLUGIN_DIR . 'arzo-safe.php';
        $config_content = "<?php\n// WP Arzo Emergency Config\n// DO NOT EDIT MANUALLY\ndefine('WP_ARZO_EMERGENCY_HASH', '$hash');\n";
        
        if (file_put_contents($config_file, $config_content)) {
            $script_url = WP_ARZO_PLUGIN_URL . 'wp-arzo-emergency/';
            echo json_encode(['success' => true, 'url' => $script_url]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to write config file.']);
        }
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
            background: #2a2a2a;
            border: 1px solid #ff4d4d;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 30px;
            color: #fff;
        }
        .emergency-section h3 { color: #ff4d4d; display: flex; align-items: center; gap: 10px; margin-top: 0; }
        .emergency-section button { background: #ff4d4d; color: #fff; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .emergency-section button:hover { background: #cc0000; }

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
        <h2>Maintenance Modes</h2>
        <p style="color: #999; margin-bottom: 30px;">Manage access to your site during maintenance or development.</p>

        <!-- Emergency Script -->
        <div class="emergency-section">
            <h3><i class="fas fa-life-ring"></i> Emergency Recovery Script</h3>
            <p>Create a standalone recovery script to access your site if WordPress breaks (WSOD, plugin conflicts, etc.).</p>
            
            <?php if ($emergency_configured): ?>
                <div style="background: rgba(22, 231, 145, 0.1); padding: 10px; border-left: 3px solid #16e791; margin-bottom: 10px;">
                    <strong>Status: Active</strong><br>
                    Your emergency script is ready at: <a href="<?php echo WP_ARZO_PLUGIN_URL . 'wp-arzo-emergency/'; ?>" target="_blank" style="color: #16e791;"><?php echo WP_ARZO_PLUGIN_URL . 'wp-arzo-emergency/'; ?></a>
                </div>
                <p><small>Save this URL! If you lose access to WP Admin, you can use this script to deactivate plugins or create a new admin.</small></p>
            <?php else: ?>
                <p>The script is not yet configured. Set a secure password to generate it.</p>
                <div style="display: flex; gap: 10px;">
                    <input type="password" id="emergency-pass" placeholder="Set Recovery Password" class="form-control" style="max-width: 250px;">
                    <button type="button" onclick="generateEmergencyScript()">Generate Script</button>
                </div>
            <?php endif; ?>
        </div>

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
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast-notification">Settings saved</div>

    <script>
        const baseUrl = '<?php echo admin_url('admin-ajax.php?action=wp_arzo_standalone'); ?>&tab=maintenance';
        let currentMode = '<?php echo $current_mode; ?>';

        function generateEmergencyScript() {
            const pass = document.getElementById('emergency-pass').value;
            if (!pass) {
                alert('Please enter a password');
                return;
            }
            
            const formData = new FormData();
            formData.append('password', pass);
            
            fetch(`${baseUrl}&operation=generate_emergency_script`, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Emergency script generated! Reloading page...');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert('Request failed');
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
            input.addEventListener('input', function () {
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
