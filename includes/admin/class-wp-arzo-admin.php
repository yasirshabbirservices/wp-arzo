<?php

/**
 * WP Arzo admin dashboard.
 *
 * Registers the native wp-admin "WP Arzo" menu, renders the feature-manager
 * toggle grid and per-feature settings screens, and handles the AJAX toggle.
 * All state changes are capability- + nonce-gated.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Admin
{
    /** @var WP_Arzo_Admin|null */
    private static $instance = null;

    const PAGE = 'wp-arzo';
    const PAGE_BACKUPS = 'wp-arzo-backups';
    const NONCE_TOGGLE = 'wp_arzo_toggle_feature';
    const NONCE_SETTINGS = 'wp_arzo_feature_settings';
    const NONCE_BACKUPS = 'wp_arzo_backups';

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init()
    {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
        add_action('admin_head', array($this, 'menu_icon_style'));
        add_filter('admin_body_class', array($this, 'admin_body_class'));
        add_action('wp_ajax_wp_arzo_toggle_feature', array($this, 'ajax_toggle_feature'));
        add_action('wp_ajax_wp_arzo_backup_create', array($this, 'ajax_backup_create'));
        add_action('wp_ajax_wp_arzo_backup_restore', array($this, 'ajax_backup_restore'));
        add_action('wp_ajax_wp_arzo_backup_delete', array($this, 'ajax_backup_delete'));
    }

    private function registry()
    {
        return WP_Arzo_Feature_Registry::instance();
    }

    /* --------------------------------------------------------------- Menu */

    /**
     * Constrain the custom image menu icon to 20×20. WordPress does not size a
     * URL-based menu icon, so a large logo PNG would otherwise render at full size
     * and overflow the sidebar into the page. Printed on every admin page (the menu
     * is global), so it can't be scoped to our screens only.
     */
    public function menu_icon_style()
    {
        echo '<style id="wp-arzo-menu-icon">'
            . '#adminmenu .toplevel_page_' . self::PAGE . ' .wp-menu-image img{'
            . 'width:20px;height:20px;padding:7px 0 0;object-fit:contain;opacity:.85}'
            . '#adminmenu .toplevel_page_' . self::PAGE . ':hover .wp-menu-image img,'
            . '#adminmenu .toplevel_page_' . self::PAGE . '.current .wp-menu-image img,'
            . '#adminmenu .toplevel_page_' . self::PAGE . '.wp-has-current-submenu .wp-menu-image img{opacity:1}'
            . '</style>';
    }

    public function admin_body_class($classes)
    {
        if ($this->is_our_page()) {
            $classes .= ' wp-arzo-screen';
        }
        return $classes;
    }

    private function menu_icon()
    {
        $logo = WP_ARZO_PLUGIN_DIR . 'assets/yasir-shabbir-white-logo.png';
        return file_exists($logo) ? WP_ARZO_PLUGIN_URL . 'assets/yasir-shabbir-white-logo.png' : 'dashicons-admin-tools';
    }

    public function add_menu()
    {
        add_menu_page(
            'WP Arzo',
            'WP Arzo',
            'manage_options',
            self::PAGE,
            array($this, 'render'),
            $this->menu_icon(),
            100
        );
        add_submenu_page(self::PAGE, 'Dashboard', 'Dashboard', 'manage_options', self::PAGE, array($this, 'render'));

        add_submenu_page(self::PAGE, 'Backups', 'Backups', 'manage_options', self::PAGE_BACKUPS, array($this, 'render_backups'));

        // The standalone power-console (DB / Files / Emergency) opens in a new tab.
        if (function_exists('wp_arzo_redirect_page')) {
            add_submenu_page(self::PAGE, 'Advanced Tools', 'Advanced Tools', 'manage_options', 'wp-arzo-tool', 'wp_arzo_redirect_page');
        }
    }

    private function is_our_page()
    {
        return isset($_GET['page']) && strpos($_GET['page'], 'wp-arzo') === 0 && $_GET['page'] !== 'wp-arzo-tool';
    }

    /* ------------------------------------------------------------ Assets */

    public function enqueue($hook)
    {
        if (!$this->is_our_page()) {
            return;
        }

        $styles = array(
            'wp-arzo-tokens'     => 'assets/css/design-tokens.css',
            'wp-arzo-components' => 'assets/css/wp-arzo-components.css',
            'wp-arzo-admin'      => 'assets/css/wp-arzo-admin.css',
        );
        foreach ($styles as $handle => $rel) {
            if (file_exists(WP_ARZO_PLUGIN_DIR . $rel)) {
                wp_enqueue_style($handle, $this->asset_url($rel), array(), null);
            }
        }

        $scripts = array(
            'wp-arzo-components-js' => 'assets/js/wp-arzo-components.js',
            'wp-arzo-admin-js'      => 'assets/js/wp-arzo-admin.js',
        );
        foreach ($scripts as $handle => $rel) {
            if (file_exists(WP_ARZO_PLUGIN_DIR . $rel)) {
                wp_enqueue_script($handle, $this->asset_url($rel), array(), null, true);
            }
        }

        wp_localize_script('wp-arzo-admin-js', 'wpArzoAdmin', array(
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce(self::NONCE_TOGGLE),
            'backupNonce' => wp_create_nonce(self::NONCE_BACKUPS),
        ));
    }

    private function asset_url($rel)
    {
        return function_exists('wp_arzo_get_asset_url')
            ? wp_arzo_get_asset_url($rel)
            : WP_ARZO_PLUGIN_URL . $rel;
    }

    /* ------------------------------------------------------------ Render */

    public function render()
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        $view = isset($_GET['view']) ? sanitize_key($_GET['view']) : 'dashboard';

        echo '<div class="wrap wpa-admin">';

        if ($view === 'settings' && isset($_GET['feature'])) {
            $feature = $this->registry()->get(sanitize_key($_GET['feature']));
            if ($feature) {
                $this->render_settings($feature);
                echo '</div>';
                return;
            }
        }

        $this->render_dashboard();
        echo '</div>';
    }

    private function render_brand_bar()
    {
        $logo = WP_ARZO_PLUGIN_URL . 'assets/yasir-shabbir-white-logo.png';
        $ver  = defined('WP_ARZO_VERSION') ? WP_ARZO_VERSION : '';
        ?>
        <div class="wpa-brandbar">
            <div class="wpa-brandbar__id">
                <img class="wpa-brandbar__logo" src="<?php echo esc_url($logo); ?>" alt="Yasir Shabbir">
                <div>
                    <div class="wpa-brandbar__name">WP Arzo</div>
                    <a class="wpa-brandbar__email" href="mailto:contact@yasirshabbir.com">by Yasir Shabbir</a>
                </div>
            </div>
            <div class="wpa-brandbar__meta">
                <span class="wpa-brandbar__ver">v<?php echo esc_html($ver); ?></span>
                <a class="wpa-brandbar__gh" href="https://github.com/yasirshabbirservices/wp-arzo" target="_blank" rel="noopener">
                    <?php echo wp_arzo_icon('external', array('class' => 'wpa-icon wpa-icon--sm')); ?> GitHub
                </a>
            </div>
        </div>
        <?php
    }

    private function render_dashboard()
    {
        $registry = $this->registry();
        $grouped  = $registry->grouped();
        $total    = count($registry->all());
        $enabled  = $registry->count_enabled();

        $this->render_brand_bar();
        ?>
        <div class="wpa-admin__bar">
            <div>
                <h1 class="wpa-admin__title"><?php echo wp_arzo_icon('tools', array('class' => 'wpa-icon')); ?> Feature Manager</h1>
                <p class="wpa-admin__subtitle">Enable only what you need. <strong><?php echo (int) $enabled; ?></strong> of <?php echo (int) $total; ?> active.</p>
            </div>
            <div class="wpa-admin__search">
                <?php echo wp_arzo_icon('search', array('class' => 'wpa-icon wpa-icon--sm')); ?>
                <input type="search" id="wpa-feature-search" placeholder="Search features…" aria-label="Search features">
            </div>
        </div>

        <div id="wpa-feature-grid">
            <?php foreach ($grouped as $group_key => $features) : ?>
                <section class="wpa-group" data-group="<?php echo esc_attr($group_key); ?>">
                    <h2 class="wpa-group__title">
                        <?php echo wp_arzo_icon($registry->group_icon($group_key), array('class' => 'wpa-icon wpa-icon--sm')); ?>
                        <?php echo esc_html($registry->group_label($group_key)); ?>
                    </h2>
                    <div class="wpa-grid">
                        <?php foreach ($features as $feature) {
                            $this->render_feature_card($feature);
                        } ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>

        <p class="wpa-admin__empty" id="wpa-no-results" hidden>No features match your search.</p>
        <?php
        $this->render_promos();
    }

    /**
     * Cross-promotion area. Filterable so products are easy to add/remove.
     */
    private function render_promos()
    {
        $promos = apply_filters('wp_arzo_promoted_products', array(
            array(
                'title' => 'WP Arzo Pro',
                'desc'  => 'Analytics & ad pixels, GSC/GTM, advanced SMTP + email logs, media manager, CPT/CCT builder, cloud backups, custom login & dashboard branding, and more.',
                'cta'   => 'Coming soon',
                'url'   => 'https://yasirshabbir.com',
                'icon'  => 'sparkles',
                'badge' => 'PRO',
            ),
            array(
                'title' => 'Need a custom build?',
                'desc'  => 'Yasir Shabbir builds bespoke WordPress plugins, integrations and performance work for agencies and businesses.',
                'cta'   => 'Get in touch',
                'url'   => 'https://yasirshabbir.com',
                'icon'  => 'bolt',
                'badge' => '',
            ),
        ));

        if (empty($promos) || !is_array($promos)) {
            return;
        }
        ?>
        <section class="wpa-promos" aria-label="More products">
            <h2 class="wpa-group__title"><?php echo wp_arzo_icon('sparkles', array('class' => 'wpa-icon wpa-icon--sm')); ?> More from Yasir Shabbir</h2>
            <div class="wpa-promo-grid">
                <?php foreach ($promos as $p) :
                    $title = isset($p['title']) ? $p['title'] : '';
                    $desc  = isset($p['desc']) ? $p['desc'] : '';
                    $cta   = isset($p['cta']) ? $p['cta'] : 'Learn more';
                    $url   = isset($p['url']) ? $p['url'] : '#';
                    $icon  = isset($p['icon']) ? $p['icon'] : 'bolt';
                    $badge = isset($p['badge']) ? $p['badge'] : '';
                    ?>
                    <div class="wpa-promo">
                        <div class="wpa-promo__icon"><?php echo wp_arzo_icon($icon, array('class' => 'wpa-icon')); ?></div>
                        <div class="wpa-promo__body">
                            <div class="wpa-promo__head">
                                <h3 class="wpa-promo__title"><?php echo esc_html($title); ?></h3>
                                <?php if ($badge) : ?><span class="wpa-badge wpa-badge--warning"><?php echo esc_html($badge); ?></span><?php endif; ?>
                            </div>
                            <p class="wpa-promo__desc"><?php echo esc_html($desc); ?></p>
                            <a class="wpa-btn wpa-btn--secondary wpa-btn--sm" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener">
                                <?php echo esc_html($cta); ?> <?php echo wp_arzo_icon('chevron-right', array('class' => 'wpa-icon wpa-icon--sm')); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }

    private function render_feature_card(WP_Arzo_Feature $feature)
    {
        $id        = $feature->id();
        $enabled   = $feature->is_enabled();
        $is_pro    = $feature->tier() === 'pro';
        /** Pro addon can hook this to lock features behind a license. */
        $available = apply_filters('wp_arzo_feature_is_available', true, $feature);
        $search    = strtolower($feature->title() . ' ' . $feature->description());
        ?>
        <div class="wpa-feature-card<?php echo $enabled ? ' is-on' : ''; ?>"
            data-feature-card="<?php echo esc_attr($id); ?>"
            data-search="<?php echo esc_attr($search); ?>">
            <div class="wpa-feature-card__icon"><?php echo wp_arzo_icon($feature->icon(), array('class' => 'wpa-icon')); ?></div>
            <div class="wpa-feature-card__body">
                <div class="wpa-feature-card__head">
                    <h3 class="wpa-feature-card__title"><?php echo esc_html($feature->title()); ?></h3>
                    <?php if ($is_pro) : ?>
                        <span class="wpa-badge wpa-badge--warning">PRO</span>
                    <?php endif; ?>
                </div>
                <?php if ($feature->description()) : ?>
                    <p class="wpa-feature-card__desc"><?php echo esc_html($feature->description()); ?></p>
                <?php endif; ?>
            </div>
            <div class="wpa-feature-card__actions">
                <?php if ($feature->has_settings()) : ?>
                    <a class="wpa-btn wpa-btn--ghost wpa-btn--icon wpa-feature-card__settings<?php echo $enabled ? '' : ' is-hidden'; ?>"
                        href="<?php echo esc_url(add_query_arg(array('page' => self::PAGE, 'view' => 'settings', 'feature' => $id), admin_url('admin.php'))); ?>"
                        aria-label="<?php echo esc_attr($feature->title() . ' settings'); ?>">
                        <?php echo wp_arzo_icon('settings', array('class' => 'wpa-icon wpa-icon--sm')); ?>
                    </a>
                <?php endif; ?>
                <?php if ($available) : ?>
                    <label class="wpa-toggle" title="<?php echo esc_attr($enabled ? 'Disable' : 'Enable'); ?>">
                        <input type="checkbox" class="wpa-toggle__input wpa-feature-toggle" role="switch"
                            aria-label="<?php echo esc_attr($feature->title()); ?>"
                            data-feature="<?php echo esc_attr($id); ?>" <?php checked($enabled); ?>>
                        <span class="wpa-toggle__track"><span class="wpa-toggle__thumb"></span></span>
                    </label>
                <?php else : ?>
                    <a class="wpa-btn wpa-btn--primary wpa-btn--sm" href="#"><?php echo wp_arzo_icon('lock', array('class' => 'wpa-icon wpa-icon--sm')); ?> Unlock</a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /* ---------------------------------------------------------- Settings */

    private function render_settings(WP_Arzo_Feature $feature)
    {
        $saved = $this->maybe_save_settings($feature);
        $schema = $feature->settings_schema();
        ?>
        <div class="wpa-admin__header">
            <div>
                <a class="wpa-btn wpa-btn--ghost wpa-btn--sm" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE)); ?>">
                    <?php echo wp_arzo_icon('chevron-right', array('class' => 'wpa-icon wpa-icon--sm')); ?> Back to dashboard
                </a>
                <h1 class="wpa-admin__title" style="margin-top:10px;">
                    <?php echo wp_arzo_icon($feature->icon(), array('class' => 'wpa-icon')); ?>
                    <?php echo esc_html($feature->title()); ?> settings
                </h1>
            </div>
        </div>

        <?php if ($saved) : ?>
            <div class="wpa-toast wpa-toast--success" style="position:static;margin-bottom:16px;display:inline-flex;">
                <?php echo wp_arzo_icon('check', array('class' => 'wpa-icon wpa-icon--sm')); ?> Settings saved.
            </div>
        <?php endif; ?>

        <form method="post" class="wpa-card" style="max-width:640px;">
            <?php wp_nonce_field(self::NONCE_SETTINGS, 'wp_arzo_settings_nonce'); ?>
            <?php
            foreach ($schema as $field) {
                $this->render_field($feature, $field);
            }
            ?>
            <div style="margin-top:20px;">
                <button type="submit" name="wp_arzo_save_settings" class="wpa-btn wpa-btn--primary">
                    <?php echo wp_arzo_icon('check', array('class' => 'wpa-icon wpa-icon--sm')); ?> Save settings
                </button>
            </div>
        </form>
        <?php
    }

    private function render_field(WP_Arzo_Feature $feature, array $field)
    {
        if (empty($field['key']) || empty($field['type'])) {
            return;
        }
        $key   = $field['key'];
        $type  = $field['type'];
        $label = isset($field['label']) ? $field['label'] : $key;
        $help  = isset($field['help']) ? $field['help'] : '';
        $value = $feature->get_setting($key);
        $name  = 'wpa_field_' . esc_attr($key);
        $fid   = 'wpa-field-' . esc_attr($key);

        echo '<div class="wpa-field">';
        if ($type !== 'toggle') {
            echo '<label class="wpa-field__label" for="' . $fid . '">' . esc_html($label) . '</label>';
        }

        switch ($type) {
            case 'textarea':
                echo '<textarea class="wpa-input" id="' . $fid . '" name="' . $name . '" rows="5">' . esc_textarea((string) $value) . '</textarea>';
                break;
            case 'number':
                echo '<input class="wpa-input" type="number" id="' . $fid . '" name="' . $name . '" value="' . esc_attr((string) $value) . '">';
                break;
            case 'select':
                $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : array();
                echo '<select class="wpa-input" id="' . $fid . '" name="' . $name . '" data-wpa-select>';
                foreach ($options as $oval => $olabel) {
                    echo '<option value="' . esc_attr($oval) . '" ' . selected((string) $value, (string) $oval, false) . '>' . esc_html($olabel) . '</option>';
                }
                echo '</select>';
                break;
            case 'toggle':
                echo '<label class="wpa-toggle"><input type="checkbox" class="wpa-toggle__input" role="switch" id="' . $fid . '" name="' . $name . '" value="1" ' . checked((bool) $value, true, false) . '>';
                echo '<span class="wpa-toggle__track"><span class="wpa-toggle__thumb"></span></span>';
                echo '<span class="wpa-toggle__label">' . esc_html($label) . '</span></label>';
                break;
            case 'text':
            default:
                echo '<input class="wpa-input" type="text" id="' . $fid . '" name="' . $name . '" value="' . esc_attr((string) $value) . '">';
                break;
        }

        if ($help) {
            echo '<p class="wpa-field__help">' . esc_html($help) . '</p>';
        }
        echo '</div>';
    }

    private function maybe_save_settings(WP_Arzo_Feature $feature)
    {
        if (!isset($_POST['wp_arzo_save_settings'])) {
            return false;
        }
        if (!current_user_can('manage_options') ||
            !isset($_POST['wp_arzo_settings_nonce']) ||
            !wp_verify_nonce(wp_unslash($_POST['wp_arzo_settings_nonce']), self::NONCE_SETTINGS)) {
            wp_die('Security check failed.');
        }

        $clean = array();
        foreach ($feature->settings_schema() as $field) {
            if (empty($field['key']) || empty($field['type'])) {
                continue;
            }
            $key = $field['key'];
            $raw = isset($_POST['wpa_field_' . $key]) ? wp_unslash($_POST['wpa_field_' . $key]) : null;

            switch ($field['type']) {
                case 'toggle':
                    $clean[$key] = ($raw === '1' || $raw === 1) ? 1 : 0;
                    break;
                case 'number':
                    $clean[$key] = is_numeric($raw) ? $raw + 0 : 0;
                    break;
                case 'textarea':
                    $clean[$key] = sanitize_textarea_field((string) $raw);
                    break;
                case 'select':
                    $options = isset($field['options']) && is_array($field['options']) ? array_keys($field['options']) : array();
                    $clean[$key] = in_array($raw, $options, true) ? $raw : (isset($field['default']) ? $field['default'] : '');
                    break;
                default:
                    $clean[$key] = sanitize_text_field((string) $raw);
                    break;
            }
        }

        $this->registry()->save_settings($feature->id(), $clean);
        return true;
    }

    /* -------------------------------------------------------------- AJAX */

    public function ajax_toggle_feature()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_TOGGLE, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }

        $id = isset($_POST['feature']) ? sanitize_key(wp_unslash($_POST['feature'])) : '';
        $enabled = isset($_POST['enabled']) && ($_POST['enabled'] === '1' || $_POST['enabled'] === 'true');

        $feature = $this->registry()->get($id);
        if (!$feature) {
            wp_send_json_error(array('message' => 'Unknown feature'), 404);
        }

        $available = apply_filters('wp_arzo_feature_is_available', true, $feature);
        if (!$available) {
            wp_send_json_error(array('message' => 'This feature requires WP Arzo Pro.'), 402);
        }

        $this->registry()->set_enabled($id, $enabled);

        wp_send_json_success(array(
            'feature'     => $id,
            'enabled'     => $enabled,
            'hasSettings' => $feature->has_settings(),
        ));
    }

    /* ----------------------------------------------------------- Backups */

    public function render_backups()
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        if (!class_exists('WP_Arzo_Backup_Manager')) {
            echo '<div class="wrap wpa-admin"><p>Backup engine unavailable.</p></div>';
            return;
        }

        $manager   = WP_Arzo_Backup_Manager::instance();
        $snapshots = $manager->list_snapshots();
        $total     = size_format($manager->total_size());
        ?>
        <div class="wrap wpa-admin">
            <?php $this->render_brand_bar(); ?>
            <div class="wpa-admin__bar">
                <div>
                    <h1 class="wpa-admin__title"><?php echo wp_arzo_icon('database', array('class' => 'wpa-icon')); ?> Backups</h1>
                    <p class="wpa-admin__subtitle"><strong><?php echo count($snapshots); ?></strong> snapshot(s) · <?php echo esc_html($total); ?> on disk · database snapshots (v1)</p>
                </div>
                <div class="wpa-backup-create">
                    <select id="wpa-backup-scope" class="wpa-input" data-wpa-select aria-label="Snapshot scope">
                        <option value="options">Options table only</option>
                        <option value="full_db">Full database</option>
                    </select>
                    <button type="button" id="wpa-backup-create" class="wpa-btn wpa-btn--primary"
                        data-nonce="<?php echo esc_attr(wp_create_nonce(self::NONCE_BACKUPS)); ?>">
                        <?php echo wp_arzo_icon('plus', array('class' => 'wpa-icon wpa-icon--sm')); ?> Create snapshot
                    </button>
                </div>
            </div>

            <div class="wpa-card" style="padding:0;overflow:hidden;">
                <table class="wpa-backup-table" id="wpa-backup-table">
                    <thead>
                        <tr>
                            <th>Snapshot</th>
                            <th>Scope</th>
                            <th>Trigger</th>
                            <th>Size</th>
                            <th>Created (UTC)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($snapshots)) : ?>
                            <tr class="wpa-backup-empty"><td colspan="6">No snapshots yet. Create one above, or enable “Automated Snapshots” on the dashboard.</td></tr>
                        <?php else : foreach ($snapshots as $s) {
                            $this->render_backup_row($s);
                        } endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    private function render_backup_row(array $s)
    {
        $scope_label = ($s['scope'] === 'full_db') ? 'Full DB' : 'Options';
        ?>
        <tr data-snapshot="<?php echo esc_attr($s['id']); ?>">
            <td>
                <strong><?php echo esc_html($s['label']); ?></strong>
                <div class="wpa-backup-meta"><?php echo (int) ($s['row_total'] ?? 0); ?> rows · <?php echo (int) ($s['table_count'] ?? 0); ?> table(s)</div>
            </td>
            <td><span class="wpa-badge wpa-badge--neutral"><?php echo esc_html($scope_label); ?></span></td>
            <td><?php echo esc_html($s['trigger'] ?? 'manual'); ?></td>
            <td><?php echo esc_html(size_format((int) ($s['bytes'] ?? 0))); ?></td>
            <td><?php echo esc_html($s['created_gmt'] ?? ''); ?></td>
            <td class="wpa-backup-actions">
                <button type="button" class="wpa-btn wpa-btn--secondary wpa-btn--sm wpa-backup-restore" data-id="<?php echo esc_attr($s['id']); ?>">
                    <?php echo wp_arzo_icon('refresh', array('class' => 'wpa-icon wpa-icon--sm')); ?> Restore
                </button>
                <button type="button" class="wpa-btn wpa-btn--ghost wpa-btn--icon wpa-backup-delete" data-id="<?php echo esc_attr($s['id']); ?>" aria-label="Delete snapshot">
                    <?php echo wp_arzo_icon('trash', array('class' => 'wpa-icon wpa-icon--sm')); ?>
                </button>
            </td>
        </tr>
        <?php
    }

    private function verify_backup_request()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_BACKUPS, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        if (!class_exists('WP_Arzo_Backup_Manager')) {
            wp_send_json_error(array('message' => 'Backup engine unavailable'), 500);
        }
    }

    public function ajax_backup_create()
    {
        $this->verify_backup_request();
        $scope = (isset($_POST['scope']) && $_POST['scope'] === 'full_db') ? 'full_db' : 'options';
        $result = WP_Arzo_Backup_Manager::instance()->create($scope, '', 'manual');
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 500);
        }
        wp_send_json_success(array('manifest' => $result));
    }

    public function ajax_backup_restore()
    {
        $this->verify_backup_request();
        $id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';
        $result = WP_Arzo_Backup_Manager::instance()->restore($id);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 500);
        }
        wp_send_json_success(array('message' => 'Snapshot restored.'));
    }

    public function ajax_backup_delete()
    {
        $this->verify_backup_request();
        $id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';
        $ok = WP_Arzo_Backup_Manager::instance()->delete($id);
        if (!$ok) {
            wp_send_json_error(array('message' => 'Could not delete snapshot.'), 500);
        }
        wp_send_json_success(array('message' => 'Snapshot deleted.'));
    }
}
