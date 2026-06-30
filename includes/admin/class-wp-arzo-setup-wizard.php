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
    const PAGE_LEADS = 'wp-arzo-leads';
    const NONCE = 'wp_arzo_wizard';
    const OPT_DONE = 'wp_arzo_wizard_done';
    const OPT_LEADS = 'wp_arzo_leads';

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
        add_filter('admin_body_class', array($this, 'body_class'));
        add_action('wp_ajax_wp_arzo_apply_preset', array($this, 'ajax_apply_preset'));
        add_action('wp_ajax_wp_arzo_wizard_skip', array($this, 'ajax_skip'));
        add_action('wp_ajax_wp_arzo_wizard_lead', array($this, 'ajax_lead'));
    }

    /** Turn the wizard page into a full-screen takeover (hides wp-admin chrome via CSS). */
    public function body_class($classes)
    {
        if (isset($_GET['page']) && $_GET['page'] === self::PAGE) {
            $classes .= ' wp-arzo-wizard';
        }
        return $classes;
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
        add_submenu_page(
            'wp-arzo',
            'Leads',
            'Leads',
            'manage_options',
            self::PAGE_LEADS,
            array($this, 'render_leads')
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
                    'disable_app_passwords', 'disable_rest_api_guests', 'rest_api_auth',
                    'two_factor', 'limit_login', 'custom_login_url', 'activity_log',
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
                    'role_manager', 'rest_api_auth',
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
        $registry  = $this->registry();
        $presets   = $this->presets();
        $grouped   = $registry->grouped();
        $nonce     = wp_create_nonce(self::NONCE);
        $dashboard = admin_url('admin.php?page=wp-arzo');
        $logo      = WP_ARZO_PLUGIN_URL . 'assets/wp-arzo-icon.svg';
        $privacy   = function_exists('get_privacy_policy_url') ? get_privacy_policy_url() : '';
        ?>
        <div class="wpa-wiz" id="wpa-wiz" data-nonce="<?php echo esc_attr($nonce); ?>" data-dashboard="<?php echo esc_url($dashboard); ?>">
            <header class="wpa-wiz__head">
                <div class="wpa-wiz__brand">
                    <img class="wpa-wiz__logo" src="<?php echo esc_url($logo); ?>" alt="WP Arzo">
                    <span>WP Arzo Setup</span>
                </div>
                <a class="wpa-wiz__exit" href="<?php echo esc_url($dashboard); ?>" id="wpa-wiz-exit">Skip setup <?php echo wp_arzo_icon('x', array('class' => 'wpa-icon wpa-icon--sm')); ?></a>
            </header>
            <div class="wpa-wiz__progress"><div class="wpa-wiz__bar" id="wpa-wiz-bar"></div></div>

            <main class="wpa-wiz__body">
                <!-- Step 1: Welcome -->
                <section class="wpa-wiz__step is-active" data-step="1">
                    <div class="wpa-wiz__hero">
                        <div class="wpa-wiz__hero-icon"><?php echo wp_arzo_icon('sparkles', array('class' => 'wpa-icon')); ?></div>
                        <h1>Welcome to WP Arzo</h1>
                        <p>Let’s get your site set up in under a minute. Apply a curated preset, or hand-pick exactly what you want — nothing is switched on without your say-so.</p>
                        <div class="wpa-wiz__cta">
                            <button type="button" class="wpa-btn wpa-btn--primary wpa-btn--lg" data-goto="2"><?php echo wp_arzo_icon('bolt', array('class' => 'wpa-icon wpa-icon--sm')); ?> Get started</button>
                            <button type="button" class="wpa-btn wpa-btn--ghost" data-goto="3">I’ll configure manually</button>
                        </div>
                    </div>
                </section>

                <!-- Step 2: Presets -->
                <section class="wpa-wiz__step" data-step="2">
                    <div class="wpa-wiz__stephead">
                        <h2>Choose a starting point</h2>
                        <p>Presets only <strong>add</strong> features — they never turn off anything you’ve set. Fine-tune everything afterwards.</p>
                    </div>
                    <div class="wpa-wiz__presets">
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
                            <div class="wpa-wiz__preset" data-preset="<?php echo esc_attr($key); ?>">
                                <div class="wpa-wiz__preset-icon"><?php echo wp_arzo_icon($preset['icon'], array('class' => 'wpa-icon')); ?></div>
                                <div class="wpa-wiz__preset-body">
                                    <div class="wpa-wiz__preset-head">
                                        <h3><?php echo esc_html($preset['name']); ?></h3>
                                        <span class="wpa-badge wpa-badge--info"><?php echo (int) $available; ?> features</span>
                                        <?php if ($locked) : ?><span class="wpa-badge wpa-badge--warning"><?php echo wp_arzo_icon('lock', array('class' => 'wpa-icon')); ?> <?php echo (int) $locked; ?> Pro</span><?php endif; ?>
                                    </div>
                                    <p><?php echo esc_html($preset['tagline']); ?></p>
                                </div>
                                <button type="button" class="wpa-btn wpa-btn--primary wpa-btn--sm wpa-wiz-apply" data-preset="<?php echo esc_attr($key); ?>"><?php echo wp_arzo_icon('check', array('class' => 'wpa-icon wpa-icon--sm')); ?> Apply</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="wpa-wiz__nav">
                        <button type="button" class="wpa-btn wpa-btn--ghost" data-goto="1">Back</button>
                        <div class="wpa-wiz__nav-right">
                            <button type="button" class="wpa-btn wpa-btn--secondary" data-goto="3">Configure manually</button>
                            <button type="button" class="wpa-btn wpa-btn--ghost" data-goto="4">Skip</button>
                        </div>
                    </div>
                </section>

                <!-- Step 3: Manual -->
                <section class="wpa-wiz__step" data-step="3">
                    <div class="wpa-wiz__stephead">
                        <h2>Turn on what you need</h2>
                        <p>Flip any switch — changes save instantly, and you can change all of this later from the dashboard.</p>
                    </div>
                    <div class="wpa-wiz__features">
                        <?php foreach ($grouped as $gk => $features) : ?>
                            <section class="wpa-wiz__group">
                                <h3><?php echo wp_arzo_icon($registry->group_icon($gk), array('class' => 'wpa-icon wpa-icon--sm')); ?> <?php echo esc_html($registry->group_label($gk)); ?></h3>
                                <div class="wpa-wiz__grid">
                                    <?php foreach ($features as $feature) :
                                        $fid = $feature->id();
                                        $en  = $feature->is_enabled();
                                        $avail = apply_filters('wp_arzo_feature_is_available', true, $feature);
                                        ?>
                                        <div class="wpa-wiz__feature<?php echo $en ? ' is-on' : ''; ?>" data-feature-card="<?php echo esc_attr($fid); ?>">
                                            <div class="wpa-wiz__feature-icon"><?php echo wp_arzo_icon($feature->icon(), array('class' => 'wpa-icon wpa-icon--sm')); ?></div>
                                            <div class="wpa-wiz__feature-body">
                                                <strong><?php echo esc_html($feature->title()); ?><?php echo $feature->tier() === 'pro' ? ' <span class="wpa-badge wpa-badge--warning">PRO</span>' : ''; ?></strong>
                                                <span><?php echo esc_html($feature->description()); ?></span>
                                            </div>
                                            <?php if ($avail) : ?>
                                                <label class="wpa-toggle">
                                                    <input type="checkbox" class="wpa-toggle__input wpa-feature-toggle" role="switch" data-feature="<?php echo esc_attr($fid); ?>" <?php checked($en); ?>>
                                                    <span class="wpa-toggle__track"><span class="wpa-toggle__thumb"></span></span>
                                                </label>
                                            <?php else : ?>
                                                <span class="wpa-badge wpa-badge--neutral"><?php echo wp_arzo_icon('lock', array('class' => 'wpa-icon')); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                    <div class="wpa-wiz__nav">
                        <button type="button" class="wpa-btn wpa-btn--ghost" data-goto="2">Back</button>
                        <button type="button" class="wpa-btn wpa-btn--primary" data-goto="4">Continue <?php echo wp_arzo_icon('chevron-right', array('class' => 'wpa-icon wpa-icon--sm')); ?></button>
                    </div>
                </section>

                <!-- Step 4: Lead capture -->
                <section class="wpa-wiz__step" data-step="4">
                    <div class="wpa-wiz__stephead">
                        <h2>Want tips, updates &amp; offers?</h2>
                        <p>Optional — leave your details and we’ll send occasional WP Arzo news and exclusive offers. No spam, unsubscribe anytime.</p>
                    </div>
                    <form class="wpa-wiz__form wpa-card" id="wpa-wiz-lead" onsubmit="return false;">
                        <div class="wpa-wiz__form-grid">
                            <div class="wpa-field"><label class="wpa-field__label" for="wpa-lead-name">Your name</label><input class="wpa-input" type="text" id="wpa-lead-name"></div>
                            <div class="wpa-field"><label class="wpa-field__label" for="wpa-lead-email">Email</label><input class="wpa-input" type="email" id="wpa-lead-email" placeholder="you@example.com"></div>
                            <div class="wpa-field"><label class="wpa-field__label" for="wpa-lead-website">Website</label><input class="wpa-input" type="text" id="wpa-lead-website" value="<?php echo esc_attr(home_url('/')); ?>"></div>
                            <div class="wpa-field"><label class="wpa-field__label" for="wpa-lead-needs">What do you need help with?</label><input class="wpa-input" type="text" id="wpa-lead-needs" placeholder="e.g. speed, security, a custom build"></div>
                        </div>
                        <label class="wpa-wiz__consent">
                            <input type="checkbox" id="wpa-lead-consent">
                            <span>I agree to be contacted about WP Arzo and accept the <?php if ($privacy) : ?><a href="<?php echo esc_url($privacy); ?>" target="_blank" rel="noopener">privacy policy</a><?php else : ?>privacy policy<?php endif; ?> &amp; terms. You can unsubscribe at any time.</span>
                        </label>
                        <p class="wpa-field__help" id="wpa-lead-msg"></p>
                    </form>
                    <div class="wpa-wiz__nav">
                        <button type="button" class="wpa-btn wpa-btn--ghost" data-goto="2">Back</button>
                        <div class="wpa-wiz__nav-right">
                            <button type="button" class="wpa-btn wpa-btn--secondary" data-goto="5">Skip</button>
                            <button type="button" class="wpa-btn wpa-btn--primary" id="wpa-lead-submit"><?php echo wp_arzo_icon('check', array('class' => 'wpa-icon wpa-icon--sm')); ?> Submit &amp; finish</button>
                        </div>
                    </div>
                </section>

                <!-- Step 5: Finish -->
                <section class="wpa-wiz__step" data-step="5">
                    <div class="wpa-wiz__hero">
                        <div class="wpa-wiz__hero-icon wpa-wiz__hero-icon--ok"><?php echo wp_arzo_icon('check-circle', array('class' => 'wpa-icon')); ?></div>
                        <h1>You’re all set!</h1>
                        <p>WP Arzo is ready. Head to your dashboard to fine-tune features, run backups, manage logins and more.</p>
                        <div class="wpa-wiz__cta">
                            <a class="wpa-btn wpa-btn--primary wpa-btn--lg" href="<?php echo esc_url($dashboard); ?>" id="wpa-wiz-finish"><?php echo wp_arzo_icon('settings', array('class' => 'wpa-icon wpa-icon--sm')); ?> Go to dashboard</a>
                        </div>
                    </div>
                </section>
            </main>
        </div>

        <script>
        (function () {
            var wiz = document.getElementById('wpa-wiz');
            if (!wiz) return;
            var nonce = wiz.dataset.nonce, dashboard = wiz.dataset.dashboard;
            var ajaxUrl = (window.wpArzoAdmin && wpArzoAdmin.ajaxUrl) || window.ajaxurl || '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
            var steps = wiz.querySelectorAll('.wpa-wiz__step');
            var bar = document.getElementById('wpa-wiz-bar');
            var total = steps.length;

            function goTo(n) {
                n = Math.max(1, Math.min(total, n));
                steps.forEach(function (s) { s.classList.toggle('is-active', String(s.dataset.step) === String(n)); });
                if (bar) bar.style.width = Math.round((n / total) * 100) + '%';
                try { sessionStorage.setItem('wpArzoWizStep', n); } catch (e) {}
                window.scrollTo(0, 0);
            }
            var saved = 1;
            try { saved = parseInt(sessionStorage.getItem('wpArzoWizStep') || '1', 10) || 1; } catch (e) {}
            goTo(saved);

            wiz.addEventListener('click', function (e) {
                var t = e.target.closest('[data-goto]');
                if (t) { goTo(parseInt(t.getAttribute('data-goto'), 10)); }
            });

            function post(action, data) {
                var body = new FormData();
                body.append('action', action);
                body.append('nonce', nonce);
                Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
                return fetch(ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { return r.json(); });
            }

            wiz.querySelectorAll('.wpa-wiz-apply').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    btn.disabled = true;
                    post('wp_arzo_apply_preset', { preset: btn.dataset.preset }).then(function (res) {
                        btn.disabled = false;
                        if (res && res.success) { goTo(4); }
                        else { alert((res && res.data && res.data.message) || 'Could not apply preset.'); }
                    }).catch(function () { btn.disabled = false; alert('Request failed.'); });
                });
            });

            var submit = document.getElementById('wpa-lead-submit');
            if (submit) submit.addEventListener('click', function () {
                var consent = document.getElementById('wpa-lead-consent');
                var msg = document.getElementById('wpa-lead-msg');
                if (!consent || !consent.checked) { if (msg) msg.textContent = 'Please tick the consent box to continue, or use Skip.'; return; }
                submit.disabled = true;
                post('wp_arzo_wizard_lead', {
                    name: (document.getElementById('wpa-lead-name') || {}).value || '',
                    email: (document.getElementById('wpa-lead-email') || {}).value || '',
                    website: (document.getElementById('wpa-lead-website') || {}).value || '',
                    needs: (document.getElementById('wpa-lead-needs') || {}).value || '',
                    consent: '1'
                }).then(function (res) {
                    submit.disabled = false;
                    if (res && res.success) { goTo(5); }
                    else { if (msg) msg.textContent = (res && res.data && res.data.message) || 'Could not save.'; }
                }).catch(function () { submit.disabled = false; if (msg) msg.textContent = 'Request failed.'; });
            });

            function markDone() {
                try { sessionStorage.removeItem('wpArzoWizStep'); } catch (e) {}
                var body = new FormData();
                body.append('action', 'wp_arzo_wizard_skip');
                body.append('nonce', nonce);
                if (navigator.sendBeacon) { navigator.sendBeacon(ajaxUrl, body); }
                else { fetch(ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin', keepalive: true }); }
            }
            var exit = document.getElementById('wpa-wiz-exit');
            if (exit) exit.addEventListener('click', markDone);
            var finish = document.getElementById('wpa-wiz-finish');
            if (finish) finish.addEventListener('click', markDone);
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

    /* -------------------------------------------------------------- Leads */

    /** Save a wizard lead (opt-in only) and notify the developer. */
    public function ajax_lead()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        if (empty($_POST['consent'])) {
            wp_send_json_error(array('message' => 'Please accept the privacy terms to continue (or Skip).'), 400);
        }
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        if ($email !== '' && !is_email($email)) {
            wp_send_json_error(array('message' => 'Please enter a valid email address.'), 400);
        }
        $lead = array(
            'name'        => isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '',
            'email'       => $email,
            'website'     => isset($_POST['website']) ? esc_url_raw(wp_unslash($_POST['website'])) : '',
            'needs'       => isset($_POST['needs']) ? sanitize_text_field(wp_unslash($_POST['needs'])) : '',
            'site'        => home_url('/'),
            'admin_email' => get_option('admin_email'),
            'time_gmt'    => gmdate('Y-m-d H:i:s'),
            'ip'          => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
        );
        $this->record_lead($lead);
        update_option(self::OPT_DONE, 1, false);
        wp_send_json_success(array('message' => 'Thanks — you’re on the list!'));
    }

    /** Persist the lead locally (capped) and email a copy to the developer. */
    private function record_lead(array $lead)
    {
        $leads = get_option(self::OPT_LEADS, array());
        if (!is_array($leads)) {
            $leads = array();
        }
        $leads[] = $lead;
        if (count($leads) > 500) {
            $leads = array_slice($leads, -500);
        }
        update_option(self::OPT_LEADS, $leads, false);

        /** Where wizard leads are emailed. Site owners can override; defaults to the developer. */
        $to = apply_filters('wp_arzo_lead_email', 'leads@yasirshabbir.com');
        if ($to && is_email($to)) {
            $subject = 'New WP Arzo lead — ' . ($lead['name'] !== '' ? $lead['name'] : $lead['site']);
            $body = "A WP Arzo user opted in to be contacted.\n\n"
                . "Name:        {$lead['name']}\n"
                . "Email:       {$lead['email']}\n"
                . "Website:     {$lead['website']}\n"
                . "Needs:       {$lead['needs']}\n"
                . "Site:        {$lead['site']}\n"
                . "Admin email: {$lead['admin_email']}\n"
                . "Time (UTC):  {$lead['time_gmt']}\n"
                . "IP:          {$lead['ip']}\n";
            wp_mail($to, $subject, $body);
        }
    }

    /** Admin view listing captured leads. */
    public function render_leads()
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        $leads = get_option(self::OPT_LEADS, array());
        if (!is_array($leads)) {
            $leads = array();
        }
        $leads = array_reverse($leads);
        ?>
        <div class="wrap wpa-admin">
            <div class="wpa-admin__bar">
                <div>
                    <h1 class="wpa-admin__title"><?php echo wp_arzo_icon('users', array('class' => 'wpa-icon')); ?> Leads</h1>
                    <p class="wpa-admin__subtitle"><strong><?php echo count($leads); ?></strong> opt-in contact(s) collected by the Setup Wizard.</p>
                </div>
            </div>
            <div class="wpa-card" style="padding:0;overflow:hidden;">
                <table class="wpa-backup-table">
                    <thead><tr><th>Name</th><th>Email</th><th>Website</th><th>Needs</th><th>When (UTC)</th></tr></thead>
                    <tbody>
                        <?php if (empty($leads)) : ?>
                            <tr class="wpa-backup-empty"><td colspan="5">No leads yet. They’re captured (with consent) on the final step of the Setup Wizard.</td></tr>
                        <?php else : foreach ($leads as $l) : ?>
                            <tr>
                                <td><strong><?php echo esc_html(isset($l['name']) ? $l['name'] : ''); ?></strong></td>
                                <td><?php echo esc_html(isset($l['email']) ? $l['email'] : ''); ?></td>
                                <td><?php echo esc_html(isset($l['website']) ? $l['website'] : ''); ?></td>
                                <td><?php echo esc_html(isset($l['needs']) ? $l['needs'] : ''); ?></td>
                                <td><?php echo esc_html(isset($l['time_gmt']) ? $l['time_gmt'] : ''); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}
