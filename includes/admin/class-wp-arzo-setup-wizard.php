<?php

/**
 * WP Arzo Setup Wizard.
 *
 * A friendly onboarding screen that lets users apply a curated "preset" — a
 * named bundle of features that get enabled in one click — instead of toggling
 * dozens of switches by hand. Presets are additive (they enable their features;
 * they never disable anything), and any feature that isn't available (e.g. a Pro
 * module without an active license) is skipped and reported.
 *
 * The wizard is shown automatically once after activation (first run) and is
 * always reachable from WP Arzo → Setup Wizard.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Setup_Wizard
{
    const PAGE = 'wp-arzo-setup';
    const NONCE = 'wp_arzo_wizard';
    const OPT_DONE = 'wp_arzo_wizard_done';

    private static $instance = null;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init()
    {
        // Priority 11 runs AFTER WP_Arzo_Admin::add_menu() (priority 10) creates the
        // parent "wp-arzo" menu, so this submenu attaches correctly.
        add_action('admin_menu', array($this, 'menu'), 11);
        add_action('admin_init', array($this, 'maybe_redirect'));
        add_action('wp_ajax_wp_arzo_apply_preset', array($this, 'ajax_apply_preset'));
        add_action('wp_ajax_wp_arzo_wizard_skip', array($this, 'ajax_skip'));
    }

    private function registry()
    {
        return WP_Arzo_Feature_Registry::instance();
    }

    public function menu()
    {
        add_submenu_page(
            'wp-arzo',
            'Setup Wizard',
            'Setup Wizard',
            'manage_options',
            self::PAGE,
            array($this, 'render')
        );
    }

    /** First-run: redirect to the wizard once after activation. */
    public function maybe_redirect()
    {
        if (!get_transient('wp_arzo_wizard_redirect')) {
            return;
        }
        delete_transient('wp_arzo_wizard_redirect');

        // Don't hijack bulk activations or non-interactive contexts.
        if (wp_doing_ajax() || isset($_GET['activate-multi']) || !current_user_can('manage_options')) {
            return;
        }
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE));
        exit;
    }

    /* ------------------------------------------------------------ Presets */

    /**
     * The preset catalog. Each preset enables the listed feature ids that are
     * available; `all_available` (The Works) enables every available feature
     * except the opinionated "disable WP behaviour" toggles in $this->opinionated().
     *
     * @return array<string,array>
     */
    public function presets()
    {
        return array(
            'essentials' => array(
                'name'    => 'Essentials',
                'tagline' => 'The smart starting set every site wants.',
                'icon'    => 'sparkles',
                'features' => array(
                    'smtp', 'email_log', 'activity_log', 'limit_login', 'disable_xmlrpc',
                    'block_user_enumeration', 'missed_schedule', 'enhance_list_tables',
                    'last_login', 'auto_snapshots',
                ),
            ),
            'velocity' => array(
                'name'    => 'Velocity',
                'tagline' => 'Trim the bloat and load faster.',
                'icon'    => 'bolt',
                'features' => array(
                    'disable_emojis', 'heartbeat_control', 'remove_jquery_migrate',
                    'disable_embeds', 'limit_revisions', 'disable_front_dashicons',
                    'disable_self_pingbacks', 'crawl_optimizations',
                ),
            ),
            'fortress' => array(
                'name'    => 'Fortress',
                'tagline' => 'Lock every door — harden WordPress.',
                'icon'    => 'shield',
                'features' => array(
                    'disable_xmlrpc', 'block_user_enumeration', 'disable_file_editor',
                    'disable_app_passwords', 'disable_rest_api_guests', 'limit_login',
                    'custom_login_url', 'activity_log',
                ),
            ),
            'creator' => array(
                'name'    => 'Creator',
                'tagline' => 'Built for writers and publishers.',
                'icon'    => 'edit',
                'features' => array(
                    'duplicate_posts', 'limit_revisions', 'svg_upload', 'webp_convert',
                    'missed_schedule', 'media_cleanup', 'custom_code', 'custom_css',
                    // Pro (skipped if unavailable):
                    'content_types', 'custom_fields', 'media_folders',
                ),
            ),
            'growth' => array(
                'name'    => 'Growth',
                'tagline' => 'Track, measure, and grow your traffic.',
                'icon'    => 'search',
                'features' => array(
                    'site_verification', 'manage_robots_txt', 'manage_ads_txt',
                    // Pro (skipped if unavailable):
                    'google_analytics_4', 'google_tag_manager', 'meta_pixel',
                ),
            ),
            'command_center' => array(
                'name'    => 'Command Center',
                'tagline' => 'Power tools for admins and developers.',
                'icon'    => 'tools',
                'features' => array(
                    'code_snippets', 'custom_code', 'custom_css', 'heartbeat_control',
                    'auto_snapshots', 'scheduled_backups', 'media_cleanup',
                    'tool_users', 'tool_database', 'tool_files', 'tool_plugins',
                    'tool_themes', 'tool_debug', 'tool_site_modes', 'tool_extra_options',
                    'tool_login',
                    // Pro (skipped if unavailable):
                    'cron_manager', 'redirects',
                ),
            ),
            'the_works' => array(
                'name'    => 'The Works',
                'tagline' => 'Everything sensible, switched on at once.',
                'icon'    => 'check-circle',
                'all_available' => true,
            ),
        );
    }

    /**
     * Opinionated toggles that change/disable native WordPress behaviour — excluded
     * from "The Works" so the catch-all stays safe.
     *
     * @return array<int,string>
     */
    private function opinionated()
    {
        return array(
            'disable_gutenberg', 'disable_comments', 'disable_feeds', 'disable_updates',
            'disable_rest_api_guests', 'custom_login_url',
        );
    }

    /** Resolve a preset's target feature ids (handles the "all available" case). */
    private function preset_feature_ids($preset)
    {
        if (!empty($preset['all_available'])) {
            $ids = array();
            $skip = $this->opinionated();
            foreach ($this->registry()->all() as $id => $feature) {
                if (in_array($id, $skip, true)) {
                    continue;
                }
                $ids[] = $id;
            }
            return $ids;
        }
        return isset($preset['features']) ? $preset['features'] : array();
    }

    /* ------------------------------------------------------------- Render */

    public function render()
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        $registry = $this->registry();
        $presets  = $this->presets();
        $nonce    = wp_create_nonce(self::NONCE);
        $dashboard = admin_url('admin.php?page=wp-arzo');
        ?>
        <div class="wrap wpa-admin">
            <div class="wpa-admin__bar">
                <div>
                    <h1 class="wpa-admin__title"><?php echo wp_arzo_icon('sparkles', array('class' => 'wpa-icon')); ?> Welcome to WP Arzo</h1>
                    <p class="wpa-admin__subtitle">Pick a preset to switch on a curated set of features in one click — or skip and choose your own from the dashboard. Presets only <strong>add</strong> features; nothing you’ve set is turned off.</p>
                </div>
                <a class="wpa-btn wpa-btn--ghost" href="<?php echo esc_url($dashboard); ?>" id="wpa-wizard-skip" data-nonce="<?php echo esc_attr($nonce); ?>">
                    Skip — I’ll choose myself
                </a>
            </div>

            <div class="wpa-grid" id="wpa-preset-grid">
                <?php foreach ($presets as $key => $preset) :
                    $ids = $this->preset_feature_ids($preset);
                    $available = 0;
                    $locked = 0;
                    foreach ($ids as $fid) {
                        $feature = $registry->get($fid);
                        if (!$feature) {
                            continue;
                        }
                        if (apply_filters('wp_arzo_feature_is_available', true, $feature)) {
                            $available++;
                        } else {
                            $locked++;
                        }
                    }
                    ?>
                    <div class="wpa-feature-card" data-preset="<?php echo esc_attr($key); ?>">
                        <div class="wpa-feature-card__icon"><?php echo wp_arzo_icon($preset['icon'], array('class' => 'wpa-icon')); ?></div>
                        <div class="wpa-feature-card__body">
                            <div class="wpa-feature-card__head">
                                <h3 class="wpa-feature-card__title"><?php echo esc_html($preset['name']); ?></h3>
                                <span class="wpa-badge wpa-badge--info"><?php echo (int) $available; ?> features</span>
                                <?php if ($locked) : ?>
                                    <span class="wpa-badge wpa-badge--warning" title="Unlocks with WP Arzo Pro"><?php echo wp_arzo_icon('lock', array('class' => 'wpa-icon')); ?> <?php echo (int) $locked; ?> Pro</span>
                                <?php endif; ?>
                            </div>
                            <p class="wpa-feature-card__desc"><?php echo esc_html($preset['tagline']); ?></p>
                        </div>
                        <div class="wpa-feature-card__actions">
                            <button type="button" class="wpa-btn wpa-btn--primary wpa-btn--sm wpa-apply-preset"
                                data-preset="<?php echo esc_attr($key); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
                                <?php echo wp_arzo_icon('check', array('class' => 'wpa-icon wpa-icon--sm')); ?> Apply
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="wpa-wizard-result" class="wpa-card" style="margin-top:18px;display:none;"></div>
        </div>

        <script>
        (function () {
            var ajaxUrl = (window.ajaxurl || '<?php echo esc_js(admin_url('admin-ajax.php')); ?>');
            var dashboard = '<?php echo esc_js($dashboard); ?>';
            var result = document.getElementById('wpa-wizard-result');

            function busy(btn, on) { if (btn) { btn.disabled = on; btn.style.opacity = on ? '0.6' : ''; } }

            document.querySelectorAll('.wpa-apply-preset').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var preset = btn.dataset.preset;
                    busy(btn, true);
                    var body = new FormData();
                    body.append('action', 'wp_arzo_apply_preset');
                    body.append('nonce', btn.dataset.nonce || '');
                    body.append('preset', preset);
                    fetch(ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
                        .then(function (r) { return r.json(); })
                        .then(function (res) {
                            busy(btn, false);
                            if (res && res.success) {
                                if (result) {
                                    result.style.display = '';
                                    result.innerHTML = '<p style="margin:0;font-weight:600;">' +
                                        '✅ ' + (res.data.message || 'Preset applied.') + '</p>' +
                                        '<p style="margin:8px 0 0;">Redirecting to your dashboard…</p>';
                                }
                                setTimeout(function () { window.location.href = dashboard; }, 1100);
                            } else {
                                alert((res && res.data && res.data.message) || 'Could not apply preset.');
                            }
                        })
                        .catch(function () { busy(btn, false); alert('Request failed.'); });
                });
            });

            var skip = document.getElementById('wpa-wizard-skip');
            if (skip) {
                skip.addEventListener('click', function () {
                    // Mark the wizard done so it doesn't auto-open again; the link still navigates.
                    var body = new FormData();
                    body.append('action', 'wp_arzo_wizard_skip');
                    body.append('nonce', skip.dataset.nonce || '');
                    navigator.sendBeacon ? navigator.sendBeacon(ajaxUrl, body)
                        : fetch(ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin', keepalive: true });
                });
            }
        })();
        </script>
        <?php
    }

    /* -------------------------------------------------------------- AJAX */

    public function ajax_apply_preset()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        $key = isset($_POST['preset']) ? sanitize_key(wp_unslash($_POST['preset'])) : '';
        $presets = $this->presets();
        if (!isset($presets[$key])) {
            wp_send_json_error(array('message' => 'Unknown preset'), 404);
        }

        $registry = $this->registry();
        $ids = $this->preset_feature_ids($presets[$key]);
        $enabled = 0;
        $already = 0;
        $locked = 0;

        foreach ($ids as $fid) {
            $feature = $registry->get($fid);
            if (!$feature) {
                continue;
            }
            if (!apply_filters('wp_arzo_feature_is_available', true, $feature)) {
                $locked++;
                continue;
            }
            if ($registry->is_enabled($fid)) {
                $already++;
                continue;
            }
            $registry->set_enabled($fid, true);
            $enabled++;
        }

        update_option(self::OPT_DONE, 1, false);

        $msg = sprintf(
            '%s applied: %d feature%s enabled%s%s.',
            $presets[$key]['name'],
            $enabled,
            $enabled === 1 ? '' : 's',
            $already ? sprintf(' (%d already on)', $already) : '',
            $locked ? sprintf(', %d Pro feature%s skipped', $locked, $locked === 1 ? '' : 's') : ''
        );

        wp_send_json_success(array(
            'message' => $msg,
            'enabled' => $enabled,
            'already' => $already,
            'locked'  => $locked,
        ));
    }

    public function ajax_skip()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        update_option(self::OPT_DONE, 1, false);
        wp_send_json_success();
    }
}
