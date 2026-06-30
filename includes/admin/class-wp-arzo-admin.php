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
    const PAGE_EMAIL_LOG = 'wp-arzo-email-log';
    const PAGE_SNIPPETS = 'wp-arzo-snippets';
    const PAGE_MEDIA = 'wp-arzo-media';
    const PAGE_ACTIVITY = 'wp-arzo-activity';
    const PAGE_REST_AUTH = 'wp-arzo-rest-auth';
    const PAGE_ROLES = 'wp-arzo-roles';
    const PAGE_CONFIG = 'wp-arzo-config';
    const NONCE_TOGGLE = 'wp_arzo_toggle_feature';
    const NONCE_SETTINGS = 'wp_arzo_feature_settings';
    const NONCE_BACKUPS = 'wp_arzo_backups';
    const NONCE_EMAIL = 'wp_arzo_email_log';
    const NONCE_LICENSE = 'wp_arzo_license';
    const NONCE_SNIPPETS = 'wp_arzo_snippets';
    const NONCE_TEST_EMAIL = 'wp_arzo_test_email';
    const NONCE_MEDIA = 'wp_arzo_media';
    const NONCE_ACTIVITY = 'wp_arzo_activity';
    const NONCE_REST = 'wp_arzo_rest_auth';
    const NONCE_ROLES = 'wp_arzo_roles';
    const NONCE_CONFIG = 'wp_arzo_config';

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
        add_action('wp_ajax_wp_arzo_email_log_clear', array($this, 'ajax_email_log_clear'));
        add_action('wp_ajax_wp_arzo_activate_license', array($this, 'ajax_activate_license'));
        add_action('wp_ajax_wp_arzo_snippet_toggle', array($this, 'ajax_snippet_toggle'));
        add_action('wp_ajax_wp_arzo_snippet_delete', array($this, 'ajax_snippet_delete'));
        add_action('wp_ajax_wp_arzo_send_test_email', array($this, 'ajax_send_test_email'));
        add_action('wp_ajax_wp_arzo_email_resend', array($this, 'ajax_email_resend'));
        add_action('wp_ajax_wp_arzo_media_scan', array($this, 'ajax_media_scan'));
        add_action('wp_ajax_wp_arzo_media_delete', array($this, 'ajax_media_delete'));
        add_action('wp_ajax_wp_arzo_activity_clear', array($this, 'ajax_activity_clear'));
        add_action('wp_ajax_wp_arzo_rest_key_create', array($this, 'ajax_rest_key_create'));
        add_action('wp_ajax_wp_arzo_rest_key_revoke', array($this, 'ajax_rest_key_revoke'));
        add_action('wp_ajax_wp_arzo_role_save_caps', array($this, 'ajax_role_save_caps'));
        add_action('wp_ajax_wp_arzo_role_add', array($this, 'ajax_role_add'));
        add_action('wp_ajax_wp_arzo_role_delete', array($this, 'ajax_role_delete'));
        add_action('wp_ajax_wp_arzo_config_export', array($this, 'ajax_config_export'));
        add_action('wp_ajax_wp_arzo_config_import', array($this, 'ajax_config_import'));
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
        $logo = WP_ARZO_PLUGIN_DIR . 'assets/wp-arzo-glyph.svg';
        return file_exists($logo) ? WP_ARZO_PLUGIN_URL . 'assets/wp-arzo-glyph.svg' : 'dashicons-admin-tools';
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

        // Feature pages appear only while their feature is enabled (see page_visible()).
        if ($this->page_visible(self::PAGE_BACKUPS)) {
            add_submenu_page(self::PAGE, 'Backups', 'Backups', 'manage_options', self::PAGE_BACKUPS, array($this, 'render_backups'));
        }
        if ($this->page_visible(self::PAGE_EMAIL_LOG)) {
            add_submenu_page(self::PAGE, 'Email Log', 'Email Log', 'manage_options', self::PAGE_EMAIL_LOG, array($this, 'render_email_log'));
        }
        if ($this->page_visible(self::PAGE_SNIPPETS)) {
            add_submenu_page(self::PAGE, 'Snippets', 'Snippets', 'manage_options', self::PAGE_SNIPPETS, array($this, 'render_snippets'));
        }
        if ($this->page_visible(self::PAGE_MEDIA)) {
            add_submenu_page(self::PAGE, 'Media Cleanup', 'Media Cleanup', 'manage_options', self::PAGE_MEDIA, array($this, 'render_media_cleanup'));
        }
        if ($this->page_visible(self::PAGE_ACTIVITY)) {
            add_submenu_page(self::PAGE, 'Activity Log', 'Activity Log', 'manage_options', self::PAGE_ACTIVITY, array($this, 'render_activity_log'));
        }
        if ($this->page_visible(self::PAGE_REST_AUTH)) {
            add_submenu_page(self::PAGE, 'REST API Auth', 'REST API Auth', 'manage_options', self::PAGE_REST_AUTH, array($this, 'render_rest_auth'));
        }
        if ($this->page_visible(self::PAGE_ROLES)) {
            add_submenu_page(self::PAGE, 'Roles', 'Roles', 'manage_options', self::PAGE_ROLES, array($this, 'render_roles'));
        }
        if ($this->page_visible(self::PAGE_CONFIG)) {
            add_submenu_page(self::PAGE, 'Import / Export', 'Import / Export', 'manage_options', self::PAGE_CONFIG, array($this, 'render_config_io'));
        }

        // The standalone power-console (DB / Files / Emergency) opens in a new tab.
        if (function_exists('wp_arzo_redirect_page')) {
            add_submenu_page(self::PAGE, 'Advanced Tools', 'Advanced Tools', 'manage_options', 'wp-arzo-tool', 'wp_arzo_redirect_page');
        }
    }

    /**
     * Map a feature-owned admin page to the feature id(s) that unlock it. A page
     * is shown when ANY of its features is enabled. Pages not listed here (e.g.
     * Media Cleanup — an on-demand tool with no toggle) are always available.
     *
     * @return array<string,array<int,string>>
     */
    private function page_features()
    {
        return array(
            self::PAGE_BACKUPS   => array('auto_snapshots', 'scheduled_backups'),
            self::PAGE_EMAIL_LOG => array('email_log'),
            self::PAGE_SNIPPETS  => array('code_snippets'),
            self::PAGE_ACTIVITY  => array('activity_log'),
            self::PAGE_MEDIA     => array('media_cleanup'),
            self::PAGE_REST_AUTH => array('rest_api_auth'),
            self::PAGE_ROLES     => array('role_manager'),
            // PAGE_CONFIG is intentionally NOT mapped — Config Import/Export is always available.
        );
    }

    /**
     * Whether a feature-owned page should be registered/linked right now.
     * Unmapped pages are always visible.
     */
    private function page_visible($page)
    {
        $map = $this->page_features();
        if (!isset($map[$page])) {
            return true;
        }
        foreach ($map[$page] as $feature_id) {
            if ($this->registry()->is_enabled($feature_id)) {
                return true;
            }
        }
        return false;
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
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce(self::NONCE_TOGGLE),
            'backupNonce'  => wp_create_nonce(self::NONCE_BACKUPS),
            'licenseNonce' => wp_create_nonce(self::NONCE_LICENSE),
            'snippetNonce' => wp_create_nonce(self::NONCE_SNIPPETS),
            'mediaNonce'   => wp_create_nonce(self::NONCE_MEDIA),
            'restNonce'    => wp_create_nonce(self::NONCE_REST),
            'rolesNonce'   => wp_create_nonce(self::NONCE_ROLES),
            'configNonce'  => wp_create_nonce(self::NONCE_CONFIG),
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
        $logo = WP_ARZO_PLUGIN_URL . 'assets/wp-arzo-icon.svg';
        $ver  = defined('WP_ARZO_VERSION') ? WP_ARZO_VERSION : '';
        ?>
        <div class="wpa-brandbar">
            <div class="wpa-brandbar__id">
                <img class="wpa-brandbar__logo" src="<?php echo esc_url($logo); ?>" alt="WP Arzo">
                <div>
                    <div class="wpa-brandbar__name">WP Arzo</div>
                    <a class="wpa-brandbar__email" href="https://yasirshabbir.com" target="_blank" rel="noopener">by Yasir Shabbir</a>
                </div>
            </div>
            <div class="wpa-brandbar__meta">
                <span class="wpa-brandbar__ver">v<?php echo esc_html($ver); ?></span>
                <a class="wpa-brandbar__gh" href="https://github.com/yasirshabbirservices/wp-arzo" target="_blank" rel="noopener">
                    <?php echo wp_arzo_icon('github', array('class' => 'wpa-icon wpa-icon--sm')); ?> GitHub
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Page-level navigation items, shared by every WP Arzo admin screen. Tabs whose
     * feature page is currently gated off are dropped (except the active one, so the
     * current screen always has a highlighted entry).
     *
     * @return array<string,array>
     */
    private function page_tabs($current)
    {
        $tabs = array(
            'dashboard' => array('label' => 'Dashboard', 'icon' => 'settings', 'url' => admin_url('admin.php?page=' . self::PAGE)),
            'backups'   => array('label' => 'Backups', 'icon' => 'database', 'url' => admin_url('admin.php?page=' . self::PAGE_BACKUPS), 'page' => self::PAGE_BACKUPS),
            'email'     => array('label' => 'Email Log', 'icon' => 'mail', 'url' => admin_url('admin.php?page=' . self::PAGE_EMAIL_LOG), 'page' => self::PAGE_EMAIL_LOG),
            'snippets'  => array('label' => 'Snippets', 'icon' => 'code', 'url' => admin_url('admin.php?page=' . self::PAGE_SNIPPETS), 'page' => self::PAGE_SNIPPETS),
            'media'     => array('label' => 'Media Cleanup', 'icon' => 'image', 'url' => admin_url('admin.php?page=' . self::PAGE_MEDIA), 'page' => self::PAGE_MEDIA),
            'activity'  => array('label' => 'Activity Log', 'icon' => 'shield', 'url' => admin_url('admin.php?page=' . self::PAGE_ACTIVITY), 'page' => self::PAGE_ACTIVITY),
            'rest_auth' => array('label' => 'REST API Auth', 'icon' => 'key', 'url' => admin_url('admin.php?page=' . self::PAGE_REST_AUTH), 'page' => self::PAGE_REST_AUTH),
            'roles'     => array('label' => 'Roles', 'icon' => 'users', 'url' => admin_url('admin.php?page=' . self::PAGE_ROLES), 'page' => self::PAGE_ROLES),
            'config'    => array('label' => 'Import / Export', 'icon' => 'sliders', 'url' => admin_url('admin.php?page=' . self::PAGE_CONFIG), 'page' => self::PAGE_CONFIG),
            'tools'     => array('label' => 'Advanced Tools', 'icon' => 'tools', 'url' => admin_url('admin.php?page=wp-arzo-tool'), 'blank' => true),
        );
        foreach ($tabs as $key => $tab) {
            if (!empty($tab['page']) && $key !== $current && !$this->page_visible($tab['page'])) {
                unset($tabs[$key]);
            }
        }
        return $tabs;
    }

    /**
     * Open the page shell: a vertical left sidebar (page nav + optional in-page
     * "Categories" filter for the dashboard) followed by the main content column.
     * Always pair with render_shell_close().
     *
     * @param string $current    Active page key for the nav highlight.
     * @param array  $categories Optional list of ['key','label','icon','count'] to render
     *                           the dashboard category filter; empty hides that block.
     * @param int    $total      Total feature count (for the "All features" item).
     */
    private function render_shell_open($current, $categories = array(), $total = 0)
    {
        echo '<div class="wpa-shell">';
        $this->render_sidenav($current, $categories, $total);
        echo '<div class="wpa-shell__main">';
    }

    private function render_shell_close()
    {
        echo '</div></div>';
    }

    /**
     * The left navigation rail: page links plus, on the dashboard, a category filter
     * that scopes the feature grid (wired up in wp-arzo-admin.js). Replaces the old
     * top-tab bar so the nav scales vertically as more pages/categories are added.
     */
    private function render_sidenav($current, $categories = array(), $total = 0)
    {
        $tabs = $this->page_tabs($current);
        echo '<aside class="wpa-sidenav" aria-label="WP Arzo navigation">';

        // Collapse toggle (icon-only rail ⇄ full). State persisted in localStorage by JS.
        echo '<div class="wpa-sidenav__bar">'
            . '<button type="button" class="wpa-sidenav__collapse" id="wpa-rail-toggle" aria-label="Collapse navigation" aria-pressed="false" title="Collapse / expand">'
            . wp_arzo_icon('chevron-right', array('class' => 'wpa-icon wpa-icon--sm'))
            . '</button></div>';

        echo '<nav class="wpa-sidenav__group" aria-label="Pages">';
        echo '<div class="wpa-sidenav__label">Pages</div>';
        foreach ($tabs as $key => $tab) {
            $active = ($key === $current) ? ' is-active' : '';
            $target = !empty($tab['blank']) ? ' target="_blank" rel="noopener"' : '';
            echo '<a class="wpa-sidenav__item' . $active . '" href="' . esc_url($tab['url']) . '"' . $target . ' title="' . esc_attr($tab['label']) . '">'
                . wp_arzo_icon($tab['icon'], array('class' => 'wpa-icon wpa-icon--sm'))
                . '<span class="wpa-sidenav__text">' . esc_html($tab['label']) . '</span>'
                . (!empty($tab['blank']) ? wp_arzo_icon('external', array('class' => 'wpa-icon wpa-icon--xs wpa-sidenav__ext')) : '')
                . '</a>';
        }
        echo '</nav>';

        if (!empty($categories)) {
            echo '<nav class="wpa-sidenav__group" aria-label="Feature categories">';
            echo '<div class="wpa-sidenav__label">Categories</div>';
            echo '<a class="wpa-sidenav__item wpa-cat-filter is-active" href="#wpa-feature-grid" data-group-filter="*" title="All features">'
                . wp_arzo_icon('grid', array('class' => 'wpa-icon wpa-icon--sm'))
                . '<span class="wpa-sidenav__text">All features</span>'
                . '<span class="wpa-sidenav__count">' . (int) $total . '</span></a>';
            foreach ($categories as $c) {
                echo '<a class="wpa-sidenav__item wpa-cat-filter" href="#group-' . esc_attr($c['key']) . '" data-group-filter="' . esc_attr($c['key']) . '" title="' . esc_attr($c['label']) . '">'
                    . wp_arzo_icon($c['icon'], array('class' => 'wpa-icon wpa-icon--sm'))
                    . '<span class="wpa-sidenav__text">' . esc_html($c['label']) . '</span>'
                    . '<span class="wpa-sidenav__count">' . (int) $c['count'] . '</span></a>';
            }
            echo '</nav>';
        }

        echo '</aside>';
    }

    private function render_dashboard()
    {
        $registry = $this->registry();
        $grouped  = $registry->grouped();
        $total    = count($registry->all());
        $enabled  = $registry->count_enabled();

        // Mirror the feature groups into the sidebar as in-page category filters.
        $categories = array();
        foreach ($grouped as $group_key => $features) {
            $categories[] = array(
                'key'   => $group_key,
                'label' => $registry->group_label($group_key),
                'icon'  => $registry->group_icon($group_key),
                'count' => count($features),
            );
        }

        $this->render_brand_bar();
        $this->render_shell_open('dashboard', $categories, $total);
        ?>
        <div class="wpa-admin__bar">
            <div>
                <h1 class="wpa-admin__title"><?php echo wp_arzo_icon('tools', array('class' => 'wpa-icon')); ?> Feature Manager</h1>
                <p class="wpa-admin__subtitle">Enable only what you need. <strong><?php echo (int) $enabled; ?></strong> of <?php echo (int) $total; ?> active.</p>
            </div>
            <div style="display:flex;gap:10px;align-items:center;">
                <a class="wpa-btn wpa-btn--ghost wpa-btn--sm" href="<?php echo esc_url(admin_url('admin.php?page=' . WP_Arzo_Setup_Wizard::PAGE)); ?>">
                    <?php echo wp_arzo_icon('sparkles', array('class' => 'wpa-icon wpa-icon--sm')); ?> Setup Wizard
                </a>
                <div class="wpa-admin__search">
                    <?php echo wp_arzo_icon('search', array('class' => 'wpa-icon wpa-icon--sm')); ?>
                    <input type="search" id="wpa-feature-search" placeholder="Search features…" aria-label="Search features">
                </div>
            </div>
        </div>

        <div class="wpa-layout">
            <div class="wpa-main">
                <div id="wpa-feature-grid">
                    <?php foreach ($grouped as $group_key => $features) : ?>
                        <section class="wpa-group" id="group-<?php echo esc_attr($group_key); ?>" data-group="<?php echo esc_attr($group_key); ?>">
                            <h2 class="wpa-group__title">
                                <?php echo wp_arzo_icon($registry->group_icon($group_key), array('class' => 'wpa-icon wpa-icon--sm')); ?>
                                <?php echo esc_html($registry->group_label($group_key)); ?>
                            </h2>
                            <div class="wpa-grid">
                                <?php foreach ($features as $feature) {
                                    // Defense in depth: never let one feature's render error
                                    // truncate the grid (and the sidebar that follows it).
                                    try {
                                        $this->render_feature_card($feature);
                                    } catch (\Throwable $e) {
                                        if (defined('WP_DEBUG') && WP_DEBUG) {
                                            echo '<!-- WP Arzo: card render failed for ' . esc_html($feature->id()) . ': ' . esc_html($e->getMessage()) . ' -->';
                                        }
                                    }
                                } ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
                <p class="wpa-admin__empty" id="wpa-no-results" hidden>No features match your search.</p>
            </div>

            <aside class="wpa-aside">
                <?php
                try {
                    $this->render_license_box();
                    $this->render_promos();
                } catch (\Throwable $e) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        echo '<!-- WP Arzo: sidebar render failed: ' . esc_html($e->getMessage()) . ' -->';
                    }
                }
                ?>
            </aside>
        </div>
        <?php
        $this->render_shell_close();
    }

    /**
     * License / activation card (sidebar). Real activation is delegated to the Pro
     * add-on / Freemius via the `wp_arzo_activate_license_result` filter.
     */
    private function render_license_box()
    {
        $active = function_exists('wp_arzo_is_pro_active') && wp_arzo_is_pro_active();
        $upgrade = function_exists('wp_arzo_pro_upgrade_url') ? wp_arzo_pro_upgrade_url() : '#';
        ?>
        <div class="wpa-aside-card wpa-license<?php echo $active ? ' is-active' : ''; ?>">
            <div class="wpa-aside-card__head">
                <?php echo wp_arzo_icon($active ? 'check-circle' : 'lock', array('class' => 'wpa-icon')); ?>
                <h3 class="wpa-aside-card__title">License</h3>
                <span class="wpa-badge <?php echo $active ? 'wpa-badge--success' : 'wpa-badge--neutral'; ?>"><?php echo $active ? 'Pro active' : 'Free'; ?></span>
            </div>
            <?php if ($active) : ?>
                <p class="wpa-aside-card__text">WP Arzo Pro is active. All premium features are unlocked.</p>
            <?php else : ?>
                <p class="wpa-aside-card__text">Enter your WP Arzo Pro license key to unlock premium features.</p>
                <input type="text" id="wpa-license-key" class="wpa-input" placeholder="License key" autocomplete="off">
                <div class="wpa-license__actions">
                    <button type="button" id="wpa-license-activate" class="wpa-btn wpa-btn--primary wpa-btn--sm"
                        data-nonce="<?php echo esc_attr(wp_create_nonce(self::NONCE_LICENSE)); ?>">
                        <?php echo wp_arzo_icon('check', array('class' => 'wpa-icon wpa-icon--sm')); ?> Activate
                    </button>
                    <a class="wpa-btn wpa-btn--ghost wpa-btn--sm" href="<?php echo esc_url($upgrade); ?>" target="_blank" rel="noopener">Get Pro</a>
                </div>
                <p class="wpa-aside-card__note" id="wpa-license-msg" hidden></p>
            <?php endif; ?>
        </div>
        <?php
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
                <?php else :
                    $upgrade = function_exists('wp_arzo_pro_upgrade_url') ? wp_arzo_pro_upgrade_url() : '#';
                    ?>
                    <a class="wpa-btn wpa-btn--primary wpa-btn--sm" href="<?php echo esc_url($upgrade); ?>" target="_blank" rel="noopener"><?php echo wp_arzo_icon('lock', array('class' => 'wpa-icon wpa-icon--sm')); ?> Unlock</a>
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
        $this->render_brand_bar();
        $this->render_shell_open('dashboard');
        ?>
        <div class="wpa-admin__bar">
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

        <form method="post" class="wpa-card">
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
        $this->render_shell_close();
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
            case 'code':
                echo '<textarea class="wpa-input wpa-code" id="' . $fid . '" name="' . $name . '" rows="8" spellcheck="false" autocomplete="off" style="font-family:var(--arzo-font-mono);font-size:13px;line-height:1.5;">' . esc_textarea((string) $value) . '</textarea>';
                break;
            case 'test_email':
                echo '<div style="display:flex;gap:8px;align-items:center;">';
                echo '<input class="wpa-input" type="email" id="wpa-test-email" placeholder="recipient@example.com" style="flex:1;">';
                echo '<button type="button" class="wpa-btn wpa-btn--secondary" id="wpa-test-email-btn" data-nonce="' . esc_attr(wp_create_nonce(self::NONCE_TEST_EMAIL)) . '">' . wp_arzo_icon('mail', array('class' => 'wpa-icon wpa-icon--sm')) . ' Send test</button>';
                echo '</div>';
                echo '<p class="wpa-field__help" id="wpa-test-email-msg">Sends a test message using the current (saved) settings.</p>';
                break;
            case 'number':
                echo '<input class="wpa-input" type="number" id="' . $fid . '" name="' . $name . '" value="' . esc_attr((string) $value) . '">';
                break;
            case 'email':
                echo '<input class="wpa-input" type="email" id="' . $fid . '" name="' . $name . '" value="' . esc_attr((string) $value) . '">';
                break;
            case 'color':
                $cval = ($value !== '' && $value !== null) ? $value : (isset($field['default']) ? $field['default'] : '#16e791');
                echo '<input type="color" style="width:64px;height:40px;padding:4px;background:var(--arzo-bg-input);border:1px solid var(--arzo-border-strong);border-radius:var(--arzo-radius-sm);" id="' . $fid . '" name="' . $name . '" value="' . esc_attr((string) $cval) . '">';
                break;
            case 'password':
                // Never echo the stored secret; blank submit keeps the saved value.
                $has = ($value !== '' && $value !== null);
                echo '<input class="wpa-input" type="password" autocomplete="new-password" id="' . $fid . '" name="' . $name . '" value="" placeholder="' . ($has ? '••••••••  (leave blank to keep current)' : '') . '">';
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
            if (empty($field['key']) || empty($field['type']) || $field['type'] === 'test_email') {
                continue; // test_email is a UI action, not a stored setting
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
                case 'email':
                    $clean[$key] = sanitize_email((string) $raw);
                    break;
                case 'color':
                    $color = sanitize_hex_color((string) $raw);
                    $clean[$key] = $color ? $color : (isset($field['default']) ? $field['default'] : '');
                    break;
                case 'password':
                    // Blank submit keeps the existing secret (we never render it).
                    $clean[$key] = ($raw === '' || $raw === null) ? (string) $feature->get_setting($key, '') : (string) $raw;
                    break;
                case 'textarea':
                    $clean[$key] = sanitize_textarea_field((string) $raw);
                    break;
                case 'code':
                    // Raw on purpose: admin-entered code/CSS/scripts, kept exactly as
                    // typed (the page is manage_options-gated, like WP's code editors).
                    $clean[$key] = is_string($raw) ? $raw : '';
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
            'ownsPage'    => $this->feature_owns_page($id),
        ));
    }

    /** Whether a feature id has a dedicated, visibility-gated admin page. */
    private function feature_owns_page($id)
    {
        foreach ($this->page_features() as $features) {
            if (in_array($id, $features, true)) {
                return true;
            }
        }
        return false;
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
            <?php $this->render_shell_open('backups'); ?>
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
            <?php $this->render_shell_close(); ?>
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

    /* --------------------------------------------------------- Email Log */

    public function render_email_log()
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        $enabled = $this->registry()->is_enabled('email_log');
        $log = get_option('wp_arzo_email_log', array());
        if (!is_array($log)) {
            $log = array();
        }
        $failed_count = 0;
        foreach ($log as $row) {
            if (isset($row['status']) && $row['status'] === 'failed') {
                $failed_count++;
            }
        }
        $sent_count = count($log) - $failed_count;
        $email_nonce = wp_create_nonce(self::NONCE_EMAIL);
        ?>
        <div class="wrap wpa-admin">
            <?php $this->render_brand_bar(); ?>
            <?php $this->render_shell_open('email'); ?>
            <div class="wpa-admin__bar">
                <div>
                    <h1 class="wpa-admin__title"><?php echo wp_arzo_icon('mail', array('class' => 'wpa-icon')); ?> Email Log</h1>
                    <p class="wpa-admin__subtitle">
                        <span class="wpa-badge wpa-badge--success"><?php echo wp_arzo_icon('check', array('class' => 'wpa-icon')); ?> <?php echo (int) $sent_count; ?> sent</span>
                        <span class="wpa-badge wpa-badge--error" style="margin-left:6px;"><?php echo wp_arzo_icon('x', array('class' => 'wpa-icon')); ?> <?php echo (int) $failed_count; ?> failed</span>
                        <?php echo $enabled ? '' : ' · logging is OFF (enable “Email Log” on the dashboard)'; ?>
                    </p>
                </div>
                <?php if (!empty($log)) : ?>
                    <button type="button" id="wpa-email-clear" class="wpa-btn wpa-btn--ghost" data-nonce="<?php echo esc_attr($email_nonce); ?>">
                        <?php echo wp_arzo_icon('trash', array('class' => 'wpa-icon wpa-icon--sm')); ?> Clear log
                    </button>
                <?php endif; ?>
            </div>

            <div class="wpa-card" style="padding:0;overflow:hidden;">
                <table class="wpa-backup-table">
                    <thead>
                        <tr><th>Time (UTC)</th><th>To</th><th>Subject</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($log)) : ?>
                            <tr class="wpa-backup-empty"><td colspan="5">No emails logged yet.</td></tr>
                        <?php else : foreach ($log as $row) :
                            $status = isset($row['status']) ? $row['status'] : 'sent';
                            $failed = ($status === 'failed');
                            ?>
                            <tr>
                                <td><?php echo esc_html(gmdate('Y-m-d H:i:s', (int) ($row['time'] ?? 0))); ?></td>
                                <td><?php echo esc_html($row['to'] ?? ''); ?></td>
                                <td>
                                    <?php echo esc_html($row['subject'] ?? ''); ?>
                                    <?php if ($failed && !empty($row['error'])) : ?>
                                        <div class="wpa-backup-meta" style="color:var(--arzo-error)"><?php echo esc_html($row['error']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="wpa-badge <?php echo $failed ? 'wpa-badge--error' : 'wpa-badge--success'; ?>">
                                        <?php echo wp_arzo_icon($failed ? 'x' : 'check', array('class' => 'wpa-icon')); ?>
                                        <?php echo $failed ? 'Failed' : 'Sent'; ?>
                                    </span>
                                </td>
                                <td class="wpa-backup-actions">
                                    <?php if (!empty($row['id']) && isset($row['message'])) : ?>
                                        <button type="button" class="wpa-btn wpa-btn--ghost wpa-btn--sm wpa-email-resend" data-id="<?php echo esc_attr($row['id']); ?>" data-nonce="<?php echo esc_attr($email_nonce); ?>">
                                            <?php echo wp_arzo_icon('refresh', array('class' => 'wpa-icon wpa-icon--sm')); ?> Resend
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <?php $this->render_shell_close(); ?>
        </div>
        <?php
    }

    public function ajax_email_log_clear()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_EMAIL, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        update_option('wp_arzo_email_log', array(), false);
        wp_send_json_success(array('message' => 'Email log cleared.'));
    }

    /* ----------------------------------------------------- Activity Log */

    public function render_activity_log()
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        $enabled = $this->registry()->is_enabled('activity_log');
        $log = class_exists('WP_Arzo_Activity_Log') ? WP_Arzo_Activity_Log::instance()->get_log() : array();
        $filter = isset($_GET['action_filter']) ? sanitize_key(wp_unslash($_GET['action_filter'])) : '';

        // Collect the action types present, for the filter dropdown.
        $present = array();
        foreach ($log as $row) {
            $a = isset($row['a']) ? $row['a'] : '';
            if ($a !== '') {
                $present[$a] = true;
            }
        }
        $rows = $filter ? array_values(array_filter($log, function ($r) use ($filter) {
            return isset($r['a']) && $r['a'] === $filter;
        })) : $log;

        $nonce = wp_create_nonce(self::NONCE_ACTIVITY);
        $base  = admin_url('admin.php?page=' . self::PAGE_ACTIVITY);
        ?>
        <div class="wrap wpa-admin">
            <?php $this->render_brand_bar(); ?>
            <?php $this->render_shell_open('activity'); ?>
            <div class="wpa-admin__bar">
                <div>
                    <h1 class="wpa-admin__title"><?php echo wp_arzo_icon('shield', array('class' => 'wpa-icon')); ?> Activity Log</h1>
                    <p class="wpa-admin__subtitle">
                        <span class="wpa-badge wpa-badge--info"><?php echo (int) count($log); ?> recorded</span>
                        <?php echo $enabled ? '' : ' · logging is OFF (enable “Activity Log” on the dashboard)'; ?>
                    </p>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <?php if (!empty($present)) : ?>
                        <form method="get" style="margin:0;">
                            <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_ACTIVITY); ?>">
                            <select name="action_filter" class="wpa-input" onchange="this.form.submit()" style="min-width:170px;">
                                <option value="">All events</option>
                                <?php foreach (array_keys($present) as $a) :
                                    $meta = WP_Arzo_Activity_Log::action_meta($a); ?>
                                    <option value="<?php echo esc_attr($a); ?>" <?php selected($filter, $a); ?>><?php echo esc_html($meta[0]); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    <?php endif; ?>
                    <?php if (!empty($log)) : ?>
                        <button type="button" id="wpa-activity-clear" class="wpa-btn wpa-btn--ghost" data-nonce="<?php echo esc_attr($nonce); ?>">
                            <?php echo wp_arzo_icon('trash', array('class' => 'wpa-icon wpa-icon--sm')); ?> Clear log
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="wpa-card" style="padding:0;overflow:hidden;">
                <table class="wpa-backup-table">
                    <thead>
                        <tr><th>Time (UTC)</th><th>Event</th><th>Detail</th><th>User</th><th>IP</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)) : ?>
                            <tr class="wpa-backup-empty"><td colspan="5"><?php echo $filter ? 'No events of this type.' : 'No activity recorded yet.'; ?></td></tr>
                        <?php else : foreach ($rows as $row) :
                            $meta = WP_Arzo_Activity_Log::action_meta(isset($row['a']) ? $row['a'] : '');
                            ?>
                            <tr>
                                <td><?php echo esc_html(gmdate('Y-m-d H:i:s', (int) ($row['t'] ?? 0))); ?></td>
                                <td>
                                    <span class="wpa-badge wpa-badge--<?php echo esc_attr($meta[1]); ?>">
                                        <?php echo wp_arzo_icon($meta[2], array('class' => 'wpa-icon')); ?>
                                        <?php echo esc_html($meta[0]); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($row['o'] ?? ''); ?></td>
                                <td><?php echo esc_html($row['ul'] ?? '—'); ?></td>
                                <td><code><?php echo esc_html($row['ip'] ?? ''); ?></code></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <?php $this->render_shell_close(); ?>
        </div>
        <?php
    }

    public function ajax_activity_clear()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_ACTIVITY, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        if (class_exists('WP_Arzo_Activity_Log')) {
            WP_Arzo_Activity_Log::instance()->clear();
        } else {
            update_option('wp_arzo_activity_log', array(), false);
        }
        wp_send_json_success(array('message' => 'Activity log cleared.'));
    }

    public function ajax_send_test_email()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_TEST_EMAIL, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        $to = isset($_POST['to']) ? sanitize_email(wp_unslash($_POST['to'])) : '';
        if (!is_email($to)) {
            wp_send_json_error(array('message' => 'Enter a valid email address.'), 400);
        }
        $subject = 'WP Arzo test email — ' . get_bloginfo('name');
        $body    = "This is a test email from WP Arzo.\n\nIf you received it, your email delivery settings are working.\n\nSite: " . home_url('/') . "\nTime: " . gmdate('Y-m-d H:i:s') . " UTC";

        $sent = wp_mail($to, $subject, $body);
        if ($sent) {
            wp_send_json_success(array('message' => 'Test email sent to ' . $to . '.'));
        }
        wp_send_json_error(array('message' => 'wp_mail() reported a failure. Check your SMTP settings / Email Log.'), 500);
    }

    public function ajax_email_resend()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_EMAIL, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        $id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';
        $log = get_option('wp_arzo_email_log', array());
        $entry = null;
        foreach ((array) $log as $row) {
            if (isset($row['id']) && $row['id'] === $id) {
                $entry = $row;
                break;
            }
        }
        if (!$entry || empty($entry['to'])) {
            wp_send_json_error(array('message' => 'Original email not found (or too old).'), 404);
        }
        $sent = wp_mail(
            $entry['to'],
            isset($entry['subject']) ? $entry['subject'] : '',
            isset($entry['message']) ? $entry['message'] : '',
            isset($entry['headers']) ? $entry['headers'] : ''
        );
        if ($sent) {
            wp_send_json_success(array('message' => 'Email resent to ' . $entry['to'] . '.'));
        }
        wp_send_json_error(array('message' => 'Resend failed — check your SMTP settings.'), 500);
    }

    /* -------------------------------------------------------- Snippets */

    public function render_snippets()
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        $view = isset($_GET['view']) ? sanitize_key($_GET['view']) : 'list';
        echo '<div class="wrap wpa-admin">';
        $this->render_brand_bar();
        $this->render_shell_open('snippets');
        if ($view === 'edit') {
            $this->render_snippet_edit(isset($_GET['id']) ? sanitize_text_field(wp_unslash($_GET['id'])) : '');
        } else {
            $this->render_snippet_list();
        }
        $this->render_shell_close();
        echo '</div>';
    }

    private function render_snippet_list()
    {
        $manager  = WP_Arzo_Snippets::instance();
        $snippets = $manager->get_all();
        $enabled  = $this->registry()->is_enabled('code_snippets');
        $new_url  = admin_url('admin.php?page=' . self::PAGE_SNIPPETS . '&view=edit');
        ?>
        <div class="wpa-admin__bar">
            <div>
                <h1 class="wpa-admin__title"><?php echo wp_arzo_icon('code', array('class' => 'wpa-icon')); ?> Code Snippets</h1>
                <p class="wpa-admin__subtitle"><strong><?php echo count($snippets); ?></strong> snippet(s)<?php echo $enabled ? '' : ' · the “Code Snippets” feature is OFF, so nothing runs (enable it on the dashboard)'; ?></p>
            </div>
            <a class="wpa-btn wpa-btn--primary" href="<?php echo esc_url($new_url); ?>"><?php echo wp_arzo_icon('plus', array('class' => 'wpa-icon wpa-icon--sm')); ?> New snippet</a>
        </div>

        <div class="wpa-card" style="padding:0;overflow:hidden;">
            <table class="wpa-backup-table">
                <thead><tr><th>Title</th><th>Type</th><th>Scope</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    <?php if (empty($snippets)) : ?>
                        <tr class="wpa-backup-empty"><td colspan="5">No snippets yet. Click “New snippet” to add one.</td></tr>
                    <?php else : foreach ($snippets as $s) :
                        $edit = admin_url('admin.php?page=' . self::PAGE_SNIPPETS . '&view=edit&id=' . $s['id']);
                        $err  = !empty($s['last_error']);
                        ?>
                        <tr data-snippet="<?php echo esc_attr($s['id']); ?>">
                            <td>
                                <a href="<?php echo esc_url($edit); ?>" style="color:var(--arzo-text-strong);font-weight:600;text-decoration:none;"><?php echo esc_html($s['title']); ?></a>
                                <?php if ($err) : ?><div class="wpa-backup-meta" style="color:var(--arzo-error)"><?php echo wp_arzo_icon('alert', array('class' => 'wpa-icon wpa-icon--xs')); ?> auto-disabled: <?php echo esc_html($s['last_error']); ?></div><?php endif; ?>
                            </td>
                            <td><span class="wpa-badge wpa-badge--neutral"><?php echo esc_html(strtoupper($s['type'])); ?></span></td>
                            <td><?php echo esc_html($s['scope']); ?></td>
                            <td>
                                <label class="wpa-toggle">
                                    <input type="checkbox" class="wpa-toggle__input wpa-snippet-toggle" role="switch" data-id="<?php echo esc_attr($s['id']); ?>" <?php checked(!empty($s['active'])); ?>>
                                    <span class="wpa-toggle__track"><span class="wpa-toggle__thumb"></span></span>
                                </label>
                            </td>
                            <td class="wpa-backup-actions">
                                <a class="wpa-btn wpa-btn--ghost wpa-btn--sm" href="<?php echo esc_url($edit); ?>"><?php echo wp_arzo_icon('edit', array('class' => 'wpa-icon wpa-icon--sm')); ?> Edit</a>
                                <button type="button" class="wpa-btn wpa-btn--ghost wpa-btn--icon wpa-snippet-delete" data-id="<?php echo esc_attr($s['id']); ?>" aria-label="Delete snippet"><?php echo wp_arzo_icon('trash', array('class' => 'wpa-icon wpa-icon--sm')); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_snippet_edit($id)
    {
        $manager = WP_Arzo_Snippets::instance();
        $saved   = $this->maybe_save_snippet();
        $snippet = $id !== '' ? $manager->get($id) : null;
        if (!$snippet) {
            $snippet = array('id' => '', 'title' => '', 'type' => 'php', 'scope' => 'everywhere', 'code' => '', 'active' => 0);
        }
        $types  = array('php' => 'PHP', 'css' => 'CSS', 'js' => 'JavaScript', 'html' => 'HTML');
        $scopes = array('everywhere' => 'Everywhere', 'admin' => 'Admin only', 'front' => 'Front-end only');
        ?>
        <div class="wpa-admin__bar">
            <div>
                <a class="wpa-btn wpa-btn--ghost wpa-btn--sm" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SNIPPETS)); ?>"><?php echo wp_arzo_icon('chevron-right', array('class' => 'wpa-icon wpa-icon--sm')); ?> All snippets</a>
                <h1 class="wpa-admin__title" style="margin-top:10px;"><?php echo wp_arzo_icon('code', array('class' => 'wpa-icon')); ?> <?php echo $snippet['id'] ? 'Edit snippet' : 'New snippet'; ?></h1>
            </div>
        </div>
        <?php if ($saved) : ?>
            <div class="wpa-toast wpa-toast--success" style="position:static;margin-bottom:16px;display:inline-flex;"><?php echo wp_arzo_icon('check', array('class' => 'wpa-icon wpa-icon--sm')); ?> Snippet saved.</div>
        <?php endif; ?>

        <form method="post" class="wpa-card">
            <?php wp_nonce_field(self::NONCE_SNIPPETS, 'wp_arzo_snippet_nonce'); ?>
            <input type="hidden" name="snippet_id" value="<?php echo esc_attr($snippet['id']); ?>">
            <div class="wpa-field">
                <label class="wpa-field__label" for="snp-title">Title</label>
                <input class="wpa-input" type="text" id="snp-title" name="snippet_title" value="<?php echo esc_attr($snippet['title']); ?>" required>
            </div>
            <div style="display:flex;gap:16px;flex-wrap:wrap;">
                <div class="wpa-field" style="flex:1;min-width:180px;">
                    <label class="wpa-field__label" for="snp-type">Type</label>
                    <select class="wpa-input" id="snp-type" name="snippet_type" data-wpa-select>
                        <?php foreach ($types as $v => $l) {
                            echo '<option value="' . esc_attr($v) . '"' . selected($snippet['type'], $v, false) . '>' . esc_html($l) . '</option>';
                        } ?>
                    </select>
                </div>
                <div class="wpa-field" style="flex:1;min-width:180px;">
                    <label class="wpa-field__label" for="snp-scope">Run on</label>
                    <select class="wpa-input" id="snp-scope" name="snippet_scope" data-wpa-select>
                        <?php foreach ($scopes as $v => $l) {
                            echo '<option value="' . esc_attr($v) . '"' . selected($snippet['scope'], $v, false) . '>' . esc_html($l) . '</option>';
                        } ?>
                    </select>
                </div>
            </div>
            <div class="wpa-field">
                <label class="wpa-field__label" for="snp-code">Code</label>
                <textarea class="wpa-input wpa-code" id="snp-code" name="snippet_code" rows="14" spellcheck="false" style="font-family:var(--arzo-font-mono);font-size:13px;line-height:1.5;"><?php echo esc_textarea($snippet['code']); ?></textarea>
                <p class="wpa-field__help">PHP snippets run as code (omit or include the opening &lt;?php tag). A PHP snippet that errors is auto-disabled.</p>
            </div>
            <label class="wpa-toggle" style="margin-bottom:16px;">
                <input type="checkbox" class="wpa-toggle__input" role="switch" name="snippet_active" value="1" <?php checked(!empty($snippet['active'])); ?>>
                <span class="wpa-toggle__track"><span class="wpa-toggle__thumb"></span></span>
                <span class="wpa-toggle__label">Active</span>
            </label>
            <div><button type="submit" name="wp_arzo_save_snippet" class="wpa-btn wpa-btn--primary"><?php echo wp_arzo_icon('check', array('class' => 'wpa-icon wpa-icon--sm')); ?> Save snippet</button></div>
        </form>
        <?php
    }

    private function maybe_save_snippet()
    {
        if (!isset($_POST['wp_arzo_save_snippet'])) {
            return false;
        }
        if (!current_user_can('manage_options') || !isset($_POST['wp_arzo_snippet_nonce']) ||
            !wp_verify_nonce(wp_unslash($_POST['wp_arzo_snippet_nonce']), self::NONCE_SNIPPETS)) {
            wp_die('Security check failed.');
        }
        WP_Arzo_Snippets::instance()->save(array(
            'id'     => isset($_POST['snippet_id']) ? sanitize_text_field(wp_unslash($_POST['snippet_id'])) : '',
            'title'  => isset($_POST['snippet_title']) ? wp_unslash($_POST['snippet_title']) : '',
            'type'   => isset($_POST['snippet_type']) ? sanitize_key($_POST['snippet_type']) : 'php',
            'scope'  => isset($_POST['snippet_scope']) ? sanitize_key($_POST['snippet_scope']) : 'everywhere',
            'code'   => isset($_POST['snippet_code']) ? wp_unslash($_POST['snippet_code']) : '',
            'active' => !empty($_POST['snippet_active']),
        ));
        return true;
    }

    private function verify_snippet_request()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_SNIPPETS, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
    }

    public function ajax_snippet_toggle()
    {
        $this->verify_snippet_request();
        $id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';
        $active = isset($_POST['active']) && ($_POST['active'] === '1' || $_POST['active'] === 'true');
        WP_Arzo_Snippets::instance()->set_active($id, $active);
        wp_send_json_success(array('id' => $id, 'active' => $active));
    }

    public function ajax_snippet_delete()
    {
        $this->verify_snippet_request();
        $id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';
        WP_Arzo_Snippets::instance()->delete($id);
        wp_send_json_success(array('message' => 'Snippet deleted.'));
    }

    /* ---------------------------------------------------- Media Cleanup */

    public function render_media_cleanup()
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        $total = class_exists('WP_Arzo_Media_Cleanup') ? WP_Arzo_Media_Cleanup::instance()->count_attachments() : 0;
        ?>
        <div class="wrap wpa-admin">
            <?php $this->render_brand_bar(); ?>
            <?php $this->render_shell_open('media'); ?>
            <div class="wpa-admin__bar">
                <div>
                    <h1 class="wpa-admin__title"><?php echo wp_arzo_icon('image', array('class' => 'wpa-icon')); ?> Media Cleanup</h1>
                    <p class="wpa-admin__subtitle"><strong><?php echo (int) $total; ?></strong> attachment(s). Scan to find files with no detectable references.</p>
                </div>
                <button type="button" id="wpa-media-scan" class="wpa-btn wpa-btn--primary" data-total="<?php echo (int) $total; ?>" data-nonce="<?php echo esc_attr(wp_create_nonce(self::NONCE_MEDIA)); ?>">
                    <?php echo wp_arzo_icon('search', array('class' => 'wpa-icon wpa-icon--sm')); ?> Scan media
                </button>
            </div>

            <div class="wpa-card" id="wpa-media-intro">
                <p class="wpa-aside-card__text" style="margin:0;">
                    “Possibly unused” means no reference was found in post content, featured images, post meta
                    (ACF / page builders), or the site logo/icon. Detection can’t see theme options, hard-coded
                    CSS, or external caches, so <strong>review each item</strong> before deleting — and take a backup
                    first (WP Arzo → Backups). Deletion is permanent and removes all image sizes.
                </p>
            </div>

            <div class="wpa-media-progress" id="wpa-media-progress" hidden>
                <div class="wpa-progress"><div class="wpa-progress__bar" id="wpa-media-bar"></div></div>
                <div class="wpa-progress__label" id="wpa-media-progress-label">Scanning…</div>
            </div>

            <div id="wpa-media-results" hidden>
                <div class="wpa-admin__bar" style="margin-top:16px;">
                    <div class="wpa-media-filters">
                        <label class="wpa-toggle"><input type="checkbox" class="wpa-toggle__input" id="wpa-media-unused-only" role="switch" checked><span class="wpa-toggle__track"><span class="wpa-toggle__thumb"></span></span><span class="wpa-toggle__label">Possibly-unused only</span></label>
                        <select id="wpa-media-type" class="wpa-input" data-wpa-select style="width:160px;">
                            <option value="">All types</option>
                            <option value="image">Images</option>
                            <option value="video">Video</option>
                            <option value="audio">Audio</option>
                            <option value="application">Documents</option>
                        </select>
                        <input type="search" id="wpa-media-search" class="wpa-input" placeholder="Search filename…" style="width:180px;">
                    </div>
                    <div class="wpa-media-summary" id="wpa-media-summary"></div>
                </div>
                <div class="wpa-card" style="padding:0;overflow:hidden;">
                    <table class="wpa-backup-table" id="wpa-media-table">
                        <thead><tr>
                            <th style="width:34px;"><input type="checkbox" id="wpa-media-all"></th>
                            <th>File</th><th>Type</th><th>Size</th><th>Date</th><th>Status</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div style="margin-top:14px;display:flex;gap:10px;align-items:center;">
                    <button type="button" id="wpa-media-delete" class="wpa-btn wpa-btn--danger" data-nonce="<?php echo esc_attr(wp_create_nonce(self::NONCE_MEDIA)); ?>" disabled>
                        <?php echo wp_arzo_icon('trash', array('class' => 'wpa-icon wpa-icon--sm')); ?> Delete selected
                    </button>
                    <span class="wpa-backup-meta" id="wpa-media-selcount"></span>
                </div>
            </div>
            <?php $this->render_shell_close(); ?>
        </div>
        <?php
    }

    private function verify_media_request()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_MEDIA, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        if (!$this->registry()->is_enabled('media_cleanup')) {
            wp_send_json_error(array('message' => 'Media Cleanup is disabled. Enable it on the WP Arzo dashboard.'), 403);
        }
        if (!class_exists('WP_Arzo_Media_Cleanup')) {
            wp_send_json_error(array('message' => 'Media cleanup unavailable'), 500);
        }
    }

    public function ajax_media_scan()
    {
        $this->verify_media_request();
        $offset = isset($_POST['offset']) ? max(0, (int) $_POST['offset']) : 0;
        $limit  = 20;
        $rows = WP_Arzo_Media_Cleanup::instance()->scan_batch($offset, $limit);
        wp_send_json_success(array('items' => $rows, 'count' => count($rows)));
    }

    public function ajax_media_delete()
    {
        $this->verify_media_request();
        $ids = isset($_POST['ids']) ? (array) $_POST['ids'] : array();
        $ids = array_map('intval', $ids);
        $deleted = WP_Arzo_Media_Cleanup::instance()->delete($ids);
        wp_send_json_success(array('deleted' => $deleted));
    }

    /* -------------------------------------------------------- License */

    public function ajax_activate_license()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_LICENSE, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        $key = isset($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : '';

        /**
         * The Pro add-on / Freemius hooks this to perform real activation and returns
         * ['success'=>bool, 'message'=>string]. Until then, activation is unavailable.
         */
        $result = apply_filters('wp_arzo_activate_license_result', null, $key);

        if (is_array($result) && isset($result['success'])) {
            if (!empty($result['success'])) {
                wp_send_json_success(array('message' => isset($result['message']) ? $result['message'] : 'License activated.'));
            }
            wp_send_json_error(array('message' => isset($result['message']) ? $result['message'] : 'Activation failed.'), 400);
        }

        wp_send_json_error(array('message' => 'Licensing is not connected on this site yet. Install WP Arzo Pro to activate.'), 501);
    }

    /* ----------------------------------------------------- REST API Auth */

    public function render_rest_auth()
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        $enabled = $this->registry()->is_enabled('rest_api_auth');
        $keys    = class_exists('WP_Arzo_Feature_REST_API_Auth') ? WP_Arzo_Feature_REST_API_Auth::all_keys() : array();
        $users   = get_users(array('fields' => array('ID', 'display_name', 'user_login'), 'number' => 300, 'orderby' => 'display_name'));
        $nonce   = wp_create_nonce(self::NONCE_REST);
        $example = home_url('/wp-json/wp/v2/posts');
        ?>
        <div class="wrap wpa-admin">
            <?php $this->render_brand_bar(); ?>
            <?php $this->render_shell_open('rest_auth'); ?>
            <div class="wpa-admin__bar">
                <div>
                    <h1 class="wpa-admin__title"><?php echo wp_arzo_icon('key', array('class' => 'wpa-icon')); ?> REST API Authentication</h1>
                    <p class="wpa-admin__subtitle">
                        <span class="wpa-badge wpa-badge--info"><?php echo count($keys); ?> key(s)</span>
                        <?php echo $enabled ? '' : ' · the “REST API Authentication” feature is OFF (enable it on the dashboard) — keys won’t authenticate'; ?>
                    </p>
                </div>
            </div>

            <div class="wpa-card" style="margin-bottom:16px;">
                <h2 class="wpa-group__title" style="margin-top:0;"><?php echo wp_arzo_icon('plus', array('class' => 'wpa-icon wpa-icon--sm')); ?> Generate a key</h2>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
                    <div class="wpa-field" style="flex:1;min-width:200px;margin:0;">
                        <label class="wpa-field__label" for="wpa-rest-label">Label</label>
                        <input class="wpa-input" type="text" id="wpa-rest-label" placeholder="e.g. Mobile app">
                    </div>
                    <div class="wpa-field" style="flex:1;min-width:200px;margin:0;">
                        <label class="wpa-field__label" for="wpa-rest-user">Authenticate as</label>
                        <select class="wpa-input" id="wpa-rest-user" data-wpa-select>
                            <?php foreach ($users as $u) {
                                echo '<option value="' . (int) $u->ID . '">' . esc_html($u->display_name . ' (' . $u->user_login . ')') . '</option>';
                            } ?>
                        </select>
                    </div>
                    <button type="button" id="wpa-rest-create" class="wpa-btn wpa-btn--primary" data-nonce="<?php echo esc_attr($nonce); ?>">
                        <?php echo wp_arzo_icon('key', array('class' => 'wpa-icon wpa-icon--sm')); ?> Generate key
                    </button>
                </div>
                <div id="wpa-rest-reveal" class="wpa-toast wpa-toast--success" hidden style="position:static;margin-top:14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <?php echo wp_arzo_icon('check', array('class' => 'wpa-icon wpa-icon--sm')); ?>
                    <span>Copy this key now — it won’t be shown again:</span>
                    <code id="wpa-rest-newkey" style="user-select:all;font-weight:700;"></code>
                </div>
                <p class="wpa-field__help" style="margin-top:10px;">A key grants the chosen user’s privileges to anyone holding it. Prefer a least-privilege user, and always use HTTPS.</p>
            </div>

            <div class="wpa-card" style="padding:0;overflow:hidden;">
                <table class="wpa-backup-table" id="wpa-rest-table">
                    <thead><tr><th>Label</th><th>Key</th><th>Authenticates as</th><th>Created (UTC)</th><th>Last used (UTC)</th><th></th></tr></thead>
                    <tbody>
                        <?php if (empty($keys)) : ?>
                            <tr class="wpa-backup-empty"><td colspan="6">No keys yet. Generate one above.</td></tr>
                        <?php else : foreach ($keys as $k) :
                            $ku = get_userdata((int) $k['user_id']); ?>
                            <tr data-key="<?php echo esc_attr($k['id']); ?>">
                                <td><strong><?php echo esc_html($k['label']); ?></strong></td>
                                <td><code>arzo_<?php echo esc_html($k['prefix']); ?>…</code></td>
                                <td><?php echo $ku ? esc_html($ku->display_name) : '<span class="wpa-badge wpa-badge--error">missing user</span>'; ?></td>
                                <td><?php echo esc_html($k['created_gmt']); ?></td>
                                <td><?php echo esc_html(!empty($k['last_used_gmt']) ? $k['last_used_gmt'] : '—'); ?></td>
                                <td class="wpa-backup-actions">
                                    <button type="button" class="wpa-btn wpa-btn--ghost wpa-btn--sm wpa-rest-revoke" data-id="<?php echo esc_attr($k['id']); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
                                        <?php echo wp_arzo_icon('trash', array('class' => 'wpa-icon wpa-icon--sm')); ?> Revoke
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="wpa-card" style="margin-top:16px;">
                <h2 class="wpa-group__title" style="margin-top:0;"><?php echo wp_arzo_icon('code', array('class' => 'wpa-icon wpa-icon--sm')); ?> How to call</h2>
                <p class="wpa-aside-card__text">Send the key with any REST request using one of these (HTTPS recommended):</p>
                <pre style="margin:0;padding:14px;background:var(--arzo-bg-input);border:1px solid var(--arzo-border-strong);border-radius:var(--arzo-radius-sm);overflow:auto;font-family:var(--arzo-font-mono);font-size:12.5px;line-height:1.6;color:var(--arzo-text-primary);"><code>curl -H "Authorization: Bearer arzo_…" <?php echo esc_html($example); ?>

curl -H "X-API-Key: arzo_…" <?php echo esc_html($example); ?>

curl -u "any:arzo_…" <?php echo esc_html($example); ?></code></pre>
            </div>
            <?php $this->render_shell_close(); ?>
        </div>
        <?php
    }

    public function ajax_rest_key_create()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_REST, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        if (!$this->registry()->is_enabled('rest_api_auth')) {
            wp_send_json_error(array('message' => 'Enable “REST API Authentication” on the dashboard first.'), 403);
        }
        $label   = isset($_POST['label']) ? sanitize_text_field(wp_unslash($_POST['label'])) : '';
        $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $res = WP_Arzo_Feature_REST_API_Auth::create_key($label, $user_id);
        if (is_wp_error($res)) {
            wp_send_json_error(array('message' => $res->get_error_message()), 400);
        }
        $ku = get_userdata((int) $res['user_id']);
        wp_send_json_success(array(
            'plain' => $res['plain'],
            'id'    => $res['id'],
            'label' => $res['label'],
            'prefix' => $res['prefix'],
            'user'  => $ku ? $ku->display_name : '',
            'created' => $res['created_gmt'],
        ));
    }

    public function ajax_rest_key_revoke()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_REST, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        $id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';
        if (!WP_Arzo_Feature_REST_API_Auth::revoke_key($id)) {
            wp_send_json_error(array('message' => 'Key not found.'), 404);
        }
        wp_send_json_success(array('message' => 'Key revoked.'));
    }

    /* ----------------------------------------------------- Role Manager */

    public function render_roles()
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        $slug = isset($_GET['role']) ? sanitize_key(wp_unslash($_GET['role'])) : '';
        echo '<div class="wrap wpa-admin">';
        $this->render_brand_bar();
        $this->render_shell_open('roles');
        if ($slug !== '' && get_role($slug)) {
            $this->render_role_editor($slug);
        } else {
            $this->render_roles_list();
        }
        $this->render_shell_close();
        echo '</div>';
    }

    private function render_roles_list()
    {
        $enabled = $this->registry()->is_enabled('role_manager');
        $roles   = WP_Arzo_Feature_Role_Manager::roles_overview();
        $nonce   = wp_create_nonce(self::NONCE_ROLES);
        ?>
        <div class="wpa-admin__bar">
            <div>
                <h1 class="wpa-admin__title"><?php echo wp_arzo_icon('users', array('class' => 'wpa-icon')); ?> Role Manager</h1>
                <p class="wpa-admin__subtitle"><strong><?php echo count($roles); ?></strong> role(s)<?php echo $enabled ? '' : ' · the “Role Manager” feature is OFF (enable it on the dashboard)'; ?></p>
            </div>
        </div>
        <div class="wpa-card" style="padding:0;overflow:hidden;margin-bottom:16px;">
            <table class="wpa-backup-table">
                <thead><tr><th>Role</th><th>Slug</th><th>Users</th><th>Capabilities</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($roles as $r) :
                        $edit = admin_url('admin.php?page=' . self::PAGE_ROLES . '&role=' . $r['slug']); ?>
                        <tr data-role="<?php echo esc_attr($r['slug']); ?>">
                            <td><strong><?php echo esc_html($r['name']); ?></strong>
                                <?php echo $r['is_builtin'] ? '<span class="wpa-badge wpa-badge--neutral">built-in</span>' : '<span class="wpa-badge wpa-badge--info">custom</span>'; ?>
                            </td>
                            <td><code><?php echo esc_html($r['slug']); ?></code></td>
                            <td><?php echo (int) $r['users']; ?></td>
                            <td><?php echo (int) $r['caps']; ?></td>
                            <td class="wpa-backup-actions">
                                <a class="wpa-btn wpa-btn--ghost wpa-btn--sm" href="<?php echo esc_url($edit); ?>"><?php echo wp_arzo_icon('edit', array('class' => 'wpa-icon wpa-icon--sm')); ?> Edit caps</a>
                                <?php if (!$r['is_builtin']) : ?>
                                    <button type="button" class="wpa-btn wpa-btn--ghost wpa-btn--icon wpa-role-delete" data-slug="<?php echo esc_attr($r['slug']); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" aria-label="Delete role"><?php echo wp_arzo_icon('trash', array('class' => 'wpa-icon wpa-icon--sm')); ?></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="wpa-card">
            <h2 class="wpa-group__title" style="margin-top:0;"><?php echo wp_arzo_icon('plus', array('class' => 'wpa-icon wpa-icon--sm')); ?> Add a role</h2>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
                <div class="wpa-field" style="flex:1;min-width:160px;margin:0;">
                    <label class="wpa-field__label" for="wpa-role-name">Display name</label>
                    <input class="wpa-input" type="text" id="wpa-role-name" placeholder="e.g. Shop Manager">
                </div>
                <div class="wpa-field" style="flex:1;min-width:160px;margin:0;">
                    <label class="wpa-field__label" for="wpa-role-slug">Slug</label>
                    <input class="wpa-input" type="text" id="wpa-role-slug" placeholder="shop_manager">
                </div>
                <div class="wpa-field" style="flex:1;min-width:160px;margin:0;">
                    <label class="wpa-field__label" for="wpa-role-clone">Clone caps from</label>
                    <select class="wpa-input" id="wpa-role-clone" data-wpa-select>
                        <option value="">None (empty)</option>
                        <?php foreach ($roles as $r) {
                            echo '<option value="' . esc_attr($r['slug']) . '">' . esc_html($r['name']) . '</option>';
                        } ?>
                    </select>
                </div>
                <button type="button" id="wpa-role-add" class="wpa-btn wpa-btn--primary" data-nonce="<?php echo esc_attr($nonce); ?>"><?php echo wp_arzo_icon('plus', array('class' => 'wpa-icon wpa-icon--sm')); ?> Add role</button>
            </div>
        </div>
        <?php
    }

    private function render_role_editor($slug)
    {
        $role  = get_role($slug);
        $all   = wp_roles();
        $name  = isset($all->roles[$slug]['name']) ? $all->roles[$slug]['name'] : $slug;
        $caps  = WP_Arzo_Feature_Role_Manager::all_capabilities();
        $has   = ($role && is_array($role->capabilities)) ? $role->capabilities : array();
        $nonce = wp_create_nonce(self::NONCE_ROLES);
        $is_admin_role = ($slug === 'administrator');
        ?>
        <div class="wpa-admin__bar">
            <div>
                <a class="wpa-btn wpa-btn--ghost wpa-btn--sm" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_ROLES)); ?>"><?php echo wp_arzo_icon('chevron-right', array('class' => 'wpa-icon wpa-icon--sm')); ?> All roles</a>
                <h1 class="wpa-admin__title" style="margin-top:10px;"><?php echo wp_arzo_icon('users', array('class' => 'wpa-icon')); ?> <?php echo esc_html($name); ?> <code style="font-size:13px;"><?php echo esc_html($slug); ?></code></h1>
            </div>
            <button type="button" id="wpa-role-save" class="wpa-btn wpa-btn--primary" data-slug="<?php echo esc_attr($slug); ?>" data-nonce="<?php echo esc_attr($nonce); ?>"><?php echo wp_arzo_icon('check', array('class' => 'wpa-icon wpa-icon--sm')); ?> Save capabilities</button>
        </div>
        <?php if ($is_admin_role) : ?>
            <div class="wpa-card" style="margin-bottom:16px;"><p class="wpa-aside-card__text" style="margin:0;"><?php echo wp_arzo_icon('shield', array('class' => 'wpa-icon wpa-icon--sm')); ?> This is the administrator role — <code>manage_options</code> is locked on to prevent lockout.</p></div>
        <?php endif; ?>
        <div class="wpa-card">
            <div class="wpa-caps-grid">
                <?php foreach ($caps as $cap) :
                    $on   = !empty($has[$cap]);
                    $lock = ($is_admin_role && $cap === 'manage_options'); ?>
                    <label class="wpa-toggle wpa-cap">
                        <input type="checkbox" class="wpa-toggle__input wpa-cap-input" role="switch" value="<?php echo esc_attr($cap); ?>" <?php checked($on); ?> <?php disabled($lock); ?>>
                        <span class="wpa-toggle__track"><span class="wpa-toggle__thumb"></span></span>
                        <span class="wpa-toggle__label"><?php echo esc_html($cap); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    private function verify_role_request()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_ROLES, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        if (!$this->registry()->is_enabled('role_manager')) {
            wp_send_json_error(array('message' => 'Enable “Role Manager” on the dashboard first.'), 403);
        }
    }

    public function ajax_role_save_caps()
    {
        $this->verify_role_request();
        $slug = isset($_POST['role']) ? sanitize_key(wp_unslash($_POST['role'])) : '';
        $role = get_role($slug);
        if (!$role) {
            wp_send_json_error(array('message' => 'Unknown role.'), 404);
        }
        $granted = isset($_POST['caps']) ? (array) wp_unslash($_POST['caps']) : array();
        $granted = array_fill_keys(array_map('sanitize_key', $granted), true);
        if ($slug === 'administrator') {
            $granted['manage_options'] = true; // lockout guard
        }
        foreach (WP_Arzo_Feature_Role_Manager::all_capabilities() as $cap) {
            if (!empty($granted[$cap])) {
                $role->add_cap($cap);
            } else {
                $role->remove_cap($cap);
            }
        }
        wp_send_json_success(array('message' => 'Capabilities saved.', 'count' => count($granted)));
    }

    public function ajax_role_add()
    {
        $this->verify_role_request();
        $name  = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $slug  = isset($_POST['slug']) ? sanitize_key(wp_unslash($_POST['slug'])) : '';
        $clone = isset($_POST['clone']) ? sanitize_key(wp_unslash($_POST['clone'])) : '';
        if ($name === '' || $slug === '') {
            wp_send_json_error(array('message' => 'Name and slug are both required.'), 400);
        }
        if (get_role($slug)) {
            wp_send_json_error(array('message' => 'A role with that slug already exists.'), 409);
        }
        $caps = array();
        if ($clone !== '' && ($src = get_role($clone)) && is_array($src->capabilities)) {
            $caps = $src->capabilities;
        }
        add_role($slug, $name, $caps);
        wp_send_json_success(array('message' => 'Role added.', 'slug' => $slug));
    }

    public function ajax_role_delete()
    {
        $this->verify_role_request();
        $slug = isset($_POST['slug']) ? sanitize_key(wp_unslash($_POST['slug'])) : '';
        if (WP_Arzo_Feature_Role_Manager::is_builtin($slug)) {
            wp_send_json_error(array('message' => 'Built-in roles cannot be deleted.'), 400);
        }
        if (!get_role($slug)) {
            wp_send_json_error(array('message' => 'Unknown role.'), 404);
        }
        remove_role($slug);
        wp_send_json_success(array('message' => 'Role deleted.'));
    }

    /* ------------------------------------------------- Config Import/Export */

    public function render_config_io()
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        $nonce = wp_create_nonce(self::NONCE_CONFIG);
        ?>
        <div class="wrap wpa-admin">
            <?php $this->render_brand_bar(); ?>
            <?php $this->render_shell_open('config'); ?>
            <div class="wpa-admin__bar">
                <div>
                    <h1 class="wpa-admin__title"><?php echo wp_arzo_icon('sliders', array('class' => 'wpa-icon')); ?> Config Import / Export</h1>
                    <p class="wpa-admin__subtitle">Move your WP Arzo setup between sites.</p>
                </div>
            </div>
            <div class="wpa-layout">
                <div class="wpa-main">
                    <div class="wpa-card" style="margin-bottom:16px;">
                        <h2 class="wpa-group__title" style="margin-top:0;"><?php echo wp_arzo_icon('download', array('class' => 'wpa-icon wpa-icon--sm')); ?> Export</h2>
                        <p class="wpa-aside-card__text">Download a JSON file with your feature toggles, feature settings, and code snippets.</p>
                        <button type="button" id="wpa-config-export" class="wpa-btn wpa-btn--primary" data-nonce="<?php echo esc_attr($nonce); ?>"><?php echo wp_arzo_icon('download', array('class' => 'wpa-icon wpa-icon--sm')); ?> Download config</button>
                    </div>
                    <div class="wpa-card">
                        <h2 class="wpa-group__title" style="margin-top:0;"><?php echo wp_arzo_icon('upload', array('class' => 'wpa-icon wpa-icon--sm')); ?> Import</h2>
                        <p class="wpa-aside-card__text">Importing <strong>overwrites</strong> your current feature toggles and settings, and merges snippets. A safety snapshot of the options table is taken first.</p>
                        <input type="file" id="wpa-config-file" accept="application/json,.json" class="wpa-input" style="margin-bottom:10px;">
                        <button type="button" id="wpa-config-import" class="wpa-btn wpa-btn--danger" data-nonce="<?php echo esc_attr($nonce); ?>"><?php echo wp_arzo_icon('upload', array('class' => 'wpa-icon wpa-icon--sm')); ?> Import config</button>
                        <p class="wpa-field__help" id="wpa-config-msg"></p>
                    </div>
                </div>
                <aside class="wpa-aside">
                    <div class="wpa-aside-card">
                        <div class="wpa-aside-card__head"><?php echo wp_arzo_icon('info', array('class' => 'wpa-icon')); ?><h3 class="wpa-aside-card__title">What’s included</h3></div>
                        <p class="wpa-aside-card__text">The feature on/off map, per-feature settings, and code snippets. It does <strong>not</strong> include other plugins’ options or your content/database — use Backups for those.</p>
                    </div>
                </aside>
            </div>
            <?php $this->render_shell_close(); ?>
        </div>
        <?php
    }

    public function ajax_config_export()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_CONFIG, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        wp_send_json_success(array(
            'filename' => WP_Arzo_Feature_Config_IO::export_filename(),
            'json'     => wp_json_encode(WP_Arzo_Feature_Config_IO::export_payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ));
    }

    public function ajax_config_import()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_CONFIG, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        $raw  = isset($_POST['data']) ? wp_unslash($_POST['data']) : '';
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            wp_send_json_error(array('message' => 'Could not read that file as JSON.'), 400);
        }
        $clean = WP_Arzo_Feature_Config_IO::validate($data);
        if (is_wp_error($clean)) {
            wp_send_json_error(array('message' => $clean->get_error_message()), 400);
        }
        $s = WP_Arzo_Feature_Config_IO::apply($clean);
        wp_send_json_success(array(
            'message' => sprintf(
                'Imported %d feature toggle(s), %d setting group(s), %d snippet(s).%s',
                $s['features'],
                $s['settings'],
                $s['snippets'],
                $s['snapshot'] ? ' A safety snapshot was taken.' : ''
            ),
        ));
    }
}
