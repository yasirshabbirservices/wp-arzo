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

    /** @var bool Whether the current shell rendered its rail (dashboard only). */
    private $shell_has_rail = false;

    /** @var bool True while a page is being rendered inside the Settings hub (skip its own .wrap). */
    private $rendering_tab = false;

    const PAGE = 'wp-arzo';
    const PAGE_BACKUPS = 'wp-arzo-backups';
    const PAGE_EMAIL_LOG = 'wp-arzo-email-log';
    const PAGE_SNIPPETS = 'wp-arzo-snippets';
    const PAGE_MEDIA = 'wp-arzo-media';
    const PAGE_ACTIVITY = 'wp-arzo-activity';
    const PAGE_REST_AUTH = 'wp-arzo-rest-auth';
    const PAGE_ROLES = 'wp-arzo-roles';
    const PAGE_CONFIG = 'wp-arzo-config';
    const PAGE_LOGIN_SECURITY = 'wp-arzo-login-security';
    const PAGE_EMAIL = 'wp-arzo-email';
    const PAGE_ANALYTICS = 'wp-arzo-analytics';
    const PAGE_SETTINGS = 'wp-arzo-settings';
    const NONCE_TOGGLE = 'wp_arzo_toggle_feature';
    const NONCE_SETTINGS = 'wp_arzo_feature_settings';
    const NONCE_BACKUPS = 'wp_arzo_backups';
    const NONCE_EMAIL = 'wp_arzo_email_log';
    const NONCE_LICENSE = 'wp_arzo_license';
    const NONCE_SNIPPETS = 'wp_arzo_snippets';
    const NONCE_TEST_EMAIL = 'wp_arzo_test_email';
    const NONCE_MEDIA = 'wp_arzo_media';
    const NONCE_ACTIVITY = 'wp_arzo_activity';
    const NONCE_ANALYTICS = 'wp_arzo_analytics';
    const NONCE_REST = 'wp_arzo_rest_auth';
    const NONCE_ROLES = 'wp_arzo_roles';
    const NONCE_CONFIG = 'wp_arzo_config';
    const NONCE_LOGIN_SECURITY = 'wp_arzo_login_security';
    const NONCE_EMAIL_CONN = 'wp_arzo_email_conn';

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
        // Re-order the WP Arzo submenu deterministically, after add-ons (Pro registers at
        // admin_menu:22) have added theirs. The native add_submenu_page $position arg is
        // unreliable across differing hook priorities, so we sort the final array ourselves.
        add_action('admin_menu', array($this, 'order_submenu'), 999);
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
        // Command palette: feed WP Arzo destinations into WordPress's native Ctrl/⌘-K
        // palette (core/commands). Enqueued site-wide in admin (not just our pages) so the
        // shortcut jumps to any WP Arzo feature from anywhere in wp-admin.
        add_action('admin_enqueue_scripts', array($this, 'enqueue_command_palette'));
        add_action('admin_head', array($this, 'menu_icon_style'));
        add_filter('admin_body_class', array($this, 'admin_body_class'));
        add_action('wp_ajax_wp_arzo_set_theme', array($this, 'ajax_set_theme'));
        add_action('wp_ajax_wp_arzo_toggle_feature', array($this, 'ajax_toggle_feature'));
        add_action('wp_ajax_wp_arzo_toggle_group', array($this, 'ajax_toggle_group'));
        add_action('wp_ajax_wp_arzo_backup_create', array($this, 'ajax_backup_create'));
        add_action('wp_ajax_wp_arzo_backup_diff', array($this, 'ajax_backup_diff'));
        add_action('wp_ajax_wp_arzo_backup_restore', array($this, 'ajax_backup_restore'));
        add_action('wp_ajax_wp_arzo_backup_delete', array($this, 'ajax_backup_delete'));
        add_action('wp_ajax_wp_arzo_email_log_clear', array($this, 'ajax_email_log_clear'));
        // Import / Export (file downloads/uploads → admin-post.php).
        add_action('admin_post_wp_arzo_email_log_export', array($this, 'handle_email_log_export'));
        add_action('admin_post_wp_arzo_activity_export', array($this, 'handle_activity_export'));
        add_action('admin_post_wp_arzo_snippets_export', array($this, 'handle_snippets_export'));
        add_action('admin_post_wp_arzo_snippets_import', array($this, 'handle_snippets_import'));
        add_action('wp_ajax_wp_arzo_email_log_detail', array($this, 'ajax_email_log_detail'));
        add_action('wp_ajax_wp_arzo_activate_license', array($this, 'ajax_activate_license'));
        add_action('wp_ajax_wp_arzo_snippet_toggle', array($this, 'ajax_snippet_toggle'));
        add_action('wp_ajax_wp_arzo_snippet_delete', array($this, 'ajax_snippet_delete'));
        add_action('wp_ajax_wp_arzo_send_test_email', array($this, 'ajax_send_test_email'));
        add_action('wp_ajax_wp_arzo_email_resend', array($this, 'ajax_email_resend'));
        add_action('wp_ajax_wp_arzo_email_queue_retry', array($this, 'ajax_email_queue_retry'));
        add_action('wp_ajax_wp_arzo_email_queue_retry_all', array($this, 'ajax_email_queue_retry_all'));
        add_action('wp_ajax_wp_arzo_email_queue_delete', array($this, 'ajax_email_queue_delete'));
        add_action('wp_ajax_wp_arzo_email_queue_clear', array($this, 'ajax_email_queue_clear'));
        add_action('wp_ajax_wp_arzo_media_scan', array($this, 'ajax_media_scan'));
        add_action('wp_ajax_wp_arzo_media_delete', array($this, 'ajax_media_delete'));
        add_action('wp_ajax_wp_arzo_activity_clear', array($this, 'ajax_activity_clear'));
        add_action('wp_ajax_wp_arzo_activity_session_kill', array($this, 'ajax_session_kill'));
        add_action('wp_ajax_wp_arzo_analytics_query', array($this, 'ajax_analytics_query'));
        add_action('admin_post_wp_arzo_analytics_export', array($this, 'handle_analytics_export'));
        add_action('wp_ajax_wp_arzo_rest_key_create', array($this, 'ajax_rest_key_create'));
        add_action('wp_ajax_wp_arzo_rest_key_revoke', array($this, 'ajax_rest_key_revoke'));
        add_action('wp_ajax_wp_arzo_role_save_caps', array($this, 'ajax_role_save_caps'));
        add_action('wp_ajax_wp_arzo_role_add', array($this, 'ajax_role_add'));
        add_action('wp_ajax_wp_arzo_role_delete', array($this, 'ajax_role_delete'));
        add_action('wp_ajax_wp_arzo_config_export', array($this, 'ajax_config_export'));
        add_action('wp_ajax_wp_arzo_config_import', array($this, 'ajax_config_import'));
        add_action('wp_ajax_wp_arzo_conn_save', array($this, 'ajax_conn_save'));
        add_action('wp_ajax_wp_arzo_conn_delete', array($this, 'ajax_conn_delete'));
        add_action('wp_ajax_wp_arzo_conn_primary', array($this, 'ajax_conn_primary'));
        add_action('wp_ajax_wp_arzo_conn_reorder', array($this, 'ajax_conn_reorder'));
        add_action('wp_ajax_wp_arzo_conn_test', array($this, 'ajax_conn_test'));
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
        // Per-user theme (dark default). Server-rendered so there is no flash;
        // applied admin-wide so token-driven UI (e.g. palette icons) follows too.
        if (self::user_theme() === 'light') {
            $classes .= ' wpa-theme-light';
        }
        return $classes;
    }

    /** The current user's WP Arzo theme: 'dark' (default) or 'light'. */
    public static function user_theme()
    {
        $t = get_user_meta(get_current_user_id(), 'wp_arzo_theme', true);
        return $t === 'light' ? 'light' : 'dark';
    }

    /** AJAX: persist the user's theme choice. */
    public function ajax_set_theme()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_TOGGLE, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        $theme = (isset($_POST['theme']) && $_POST['theme'] === 'light') ? 'light' : 'dark';
        update_user_meta(get_current_user_id(), 'wp_arzo_theme', $theme);
        wp_send_json_success(array('theme' => $theme));
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
        // Explicit positions give a deliberate order and leave gaps (20–25, 40, 55) for the
        // Pro add-on's pages (Content Types, Custom Fields, Redirects, Cron) to slot in.
        if ($this->page_visible(self::PAGE_SNIPPETS)) {
            add_submenu_page(self::PAGE, 'Snippets', 'Snippets', 'manage_options', self::PAGE_SNIPPETS, array($this, 'render_snippets'), 30);
        }
        if ($this->page_visible(self::PAGE_MEDIA)) {
            add_submenu_page(self::PAGE, 'Media Cleanup', 'Media Cleanup', 'manage_options', self::PAGE_MEDIA, array($this, 'render_media_cleanup'), 35);
        }
        if ($this->page_visible(self::PAGE_ANALYTICS)) {
            add_submenu_page(self::PAGE, 'Analytics', 'Analytics', 'manage_options', self::PAGE_ANALYTICS, array($this, 'render_analytics'), 8);
        }
        if ($this->page_visible(self::PAGE_EMAIL)) {
            add_submenu_page(self::PAGE, 'Email', 'Email', 'manage_options', self::PAGE_EMAIL, array($this, 'render_email'), 44);
        }
        if ($this->page_visible(self::PAGE_BACKUPS)) {
            add_submenu_page(self::PAGE, 'Backups', 'Backups', 'manage_options', self::PAGE_BACKUPS, array($this, 'render_backups'), 50);
        }
        if ($this->page_visible(self::PAGE_ACTIVITY)) {
            add_submenu_page(self::PAGE, 'Activity Log', 'Activity Log', 'manage_options', self::PAGE_ACTIVITY, array($this, 'render_activity_log'), 60);
        }

        // One consolidated Settings hub — Login Security / Roles / REST API Auth / Import-Export
        // (free) plus Two-Factor / Notifications / AI-MCP (Pro, via the wp_arzo_settings_tabs
        // filter) live as TABS here instead of a menu each.
        add_submenu_page(self::PAGE, 'Settings', 'Settings', 'manage_options', self::PAGE_SETTINGS, array($this, 'render_settings_hub'), 80);

        // The standalone power-console (DB / Files / Emergency) opens in a new tab.
        if (function_exists('wp_arzo_redirect_page')) {
            add_submenu_page(self::PAGE, 'Advanced Tools', 'Advanced Tools', 'manage_options', 'wp-arzo-tool', 'wp_arzo_redirect_page', 95);
        }
    }

    /**
     * Deterministically order the WP Arzo submenu (free + Pro pages) by a slug→rank map.
     * Runs at admin_menu:999 so every add-on page is present before we sort. Unknown slugs
     * (future add-on pages) fall in the middle, before Import/Export & Advanced Tools.
     */
    public function order_submenu()
    {
        global $submenu;
        if (empty($submenu[self::PAGE]) || !is_array($submenu[self::PAGE])) {
            return;
        }
        // UX-driven order (single source of truth — Pro position args are overridden here):
        // Dashboard first, then the daily "monitor & operate" surfaces, then content
        // modeling, then security & access, then developer tools, with catch-all tools
        // (Import/Export, the Advanced Tools console) last. Unranked slugs land at 80.
        $rank = array(
            self::PAGE                => 0,  // Dashboard (hub)
            // Monitor & operate (checked most often)
            self::PAGE_ANALYTICS      => 8,  // Analytics — traffic at a glance
            self::PAGE_ACTIVITY       => 10, // Activity Log — "what's happening"
            self::PAGE_EMAIL          => 12,
            self::PAGE_BACKUPS        => 14,
            // Content & media
            'wp-arzo-content-types'   => 30, // Pro
            'wp-arzo-custom-fields'   => 32, // Pro
            self::PAGE_MEDIA          => 34,
            // Developer
            self::PAGE_SNIPPETS       => 70,
            'wp-arzo-redirects'       => 72, // Pro
            'wp-arzo-cron'            => 74, // Pro
            // Consolidated Settings hub (Security / Access / Integrations / Data as tabs) — near the bottom
            self::PAGE_SETTINGS       => 80,
            // Tools (bottom)
            'wp-arzo-tool'            => 95, // Advanced Tools console (opens standalone)
        );
        usort($submenu[self::PAGE], function ($a, $b) use ($rank) {
            // $item[2] is the menu slug.
            $ra = isset($rank[$a[2]]) ? $rank[$a[2]] : 80;
            $rb = isset($rank[$b[2]]) ? $rank[$b[2]] : 80;
            return $ra <=> $rb;
        });
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
            self::PAGE_ANALYTICS => array('analytics'),
            self::PAGE_BACKUPS   => array('auto_snapshots', 'scheduled_backups', 'backup_ftp', 'backup_gdrive', 'backup_pcloud'),
            self::PAGE_EMAIL     => array('smtp', 'email_log'),
            self::PAGE_SNIPPETS  => array('code_snippets'),
            self::PAGE_ACTIVITY  => array('activity_log', 'audit_log'),
            self::PAGE_MEDIA     => array('media_cleanup'),
            self::PAGE_REST_AUTH => array('rest_api_auth'),
            self::PAGE_ROLES     => array('role_manager'),
            self::PAGE_LOGIN_SECURITY => array('limit_login'),
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
            'connNonce'    => wp_create_nonce(self::NONCE_EMAIL_CONN),
            'adminEmail'   => get_option('admin_email'),
        ));

        // Syntax-highlighting code editor (CodeMirror) for the Snippets app. The PHP
        // mode bundle pulls in css/javascript/htmlmixed too, so every snippet type is
        // covered by one enqueue. Returns false if the user disabled it in their profile.
        if (isset($_GET['page']) && $_GET['page'] === self::PAGE_SNIPPETS && function_exists('wp_enqueue_code_editor')) {
            $cm = wp_enqueue_code_editor(array('type' => 'application/x-httpd-php'));
            if ($cm !== false) {
                wp_add_inline_script('wp-arzo-admin-js', 'window.wpArzoCM = ' . wp_json_encode($cm) . ';', 'before');
            }
        }
    }

    private function asset_url($rel)
    {
        return function_exists('wp_arzo_get_asset_url')
            ? wp_arzo_get_asset_url($rel)
            : WP_ARZO_PLUGIN_URL . $rel;
    }

    /* -------------------------------------------------- Command palette */

    /**
     * Enqueue the command-palette bridge on every admin screen for admins.
     *
     * Rather than build a competing Ctrl-K overlay, we register WP Arzo's pages,
     * settings tabs and console tools into WordPress's own command palette
     * (`core/commands`, the store behind the admin-bar ⌘K node since WP 6.3). The
     * script depends on `wp-commands`/`wp-data`, so WP guarantees the store is loaded.
     *
     * @param string $hook Current admin page hook (unused — palette is global).
     */
    public function enqueue_command_palette($hook)
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        // The commands store only exists on screens where the palette is available.
        // Since WP 6.3 `wp-commands` is a registered handle admin-wide; guard anyway so
        // older cores just skip the feature instead of erroring.
        if (!wp_script_is('wp-commands', 'registered')) {
            return;
        }
        $rel = 'assets/js/wp-arzo-command-palette.js';
        if (!file_exists(WP_ARZO_PLUGIN_DIR . $rel)) {
            return;
        }
        wp_enqueue_script(
            'wp-arzo-command-palette',
            $this->asset_url($rel),
            array('wp-commands', 'wp-data', 'wp-element', 'wp-dom-ready'),
            null,
            true
        );
        wp_localize_script('wp-arzo-command-palette', 'wpArzoCommands', array(
            'group'      => __('WP Arzo', 'wp-arzo'),
            'commands'   => $this->command_palette_items(),
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'themeNonce' => wp_create_nonce(self::NONCE_TOGGLE),
            'themeLabel' => __('WP Arzo: toggle light / dark theme', 'wp-arzo'),
        ));
        // Brand-theme our palette entries: design-tokens.css is pure :root variables
        // (safe admin-wide), so the icon rule below can use the accent token instead
        // of a hardcoded color. Never restyle core's own palette layout.
        wp_enqueue_style('wp-arzo-tokens', $this->asset_url('assets/css/design-tokens.css'), array(), null);
        wp_add_inline_style(
            'wp-arzo-tokens',
            '.commands-command-menu .wpa-cmd-icon{font-size:20px;width:20px;height:20px;color:var(--arzo-accent);}'
        );
    }

    /**
     * Build the WP Arzo command list for the palette: feature pages, Settings tabs
     * and (enabled) standalone console tools. Only destinations the current user can
     * actually reach right now are included, so the palette never dead-ends.
     *
     * @return array<int,array<string,mixed>> Each: id,label,icon(dashicon),url,newTab.
     */
    private function command_palette_items()
    {
        $base  = admin_url('admin.php?page=');
        $items = array();

        // --- Feature pages (verbs a user searches for) -------------------
        $pages = array(
            array('id' => 'dashboard', 'label' => __('Dashboard', 'wp-arzo'),     'icon' => 'screenoptions',     'slug' => self::PAGE,          'always' => true),
            array('id' => 'analytics', 'label' => __('Analytics', 'wp-arzo'),     'icon' => 'chart-bar',         'slug' => self::PAGE_ANALYTICS),
            array('id' => 'activity',  'label' => __('Activity Log', 'wp-arzo'),  'icon' => 'list-view',         'slug' => self::PAGE_ACTIVITY),
            array('id' => 'email',     'label' => __('Email', 'wp-arzo'),         'icon' => 'email',             'slug' => self::PAGE_EMAIL),
            array('id' => 'backups',   'label' => __('Backups', 'wp-arzo'),       'icon' => 'database-export',   'slug' => self::PAGE_BACKUPS),
            array('id' => 'media',     'label' => __('Media Cleanup', 'wp-arzo'), 'icon' => 'format-gallery',    'slug' => self::PAGE_MEDIA),
            array('id' => 'snippets',  'label' => __('Snippets', 'wp-arzo'),      'icon' => 'editor-code',       'slug' => self::PAGE_SNIPPETS),
            array('id' => 'settings',  'label' => __('Settings', 'wp-arzo'),      'icon' => 'admin-settings',    'slug' => self::PAGE_SETTINGS, 'always' => true),
        );
        foreach ($pages as $p) {
            if (empty($p['always']) && !$this->page_visible($p['slug'])) {
                continue;
            }
            $items[] = array(
                'id'    => 'page-' . $p['id'],
                'label' => $p['label'],
                'icon'  => $p['icon'],
                'url'   => $base . $p['slug'],
            );
        }

        // --- Activity Log → Sessions (deep link) -------------------------
        if ($this->page_visible(self::PAGE_ACTIVITY)) {
            $items[] = array(
                'id'    => 'activity-sessions',
                'label' => __('Live Sessions', 'wp-arzo'),
                'icon'  => 'admin-users',
                'url'   => add_query_arg('tab', 'sessions', $base . self::PAGE_ACTIVITY),
            );
        }

        // --- Setup wizard -----------------------------------------------
        $items[] = array(
            'id'    => 'setup-wizard',
            'label' => __('Setup Wizard', 'wp-arzo'),
            'icon'  => 'admin-generic',
            'url'   => $base . 'wp-arzo-setup',
        );

        // --- Settings hub tabs (deep links) ------------------------------
        $settings_base = $base . self::PAGE_SETTINGS;
        foreach ($this->settings_tabs() as $key => $tab) {
            $items[] = array(
                'id'    => 'settings-' . $key,
                'label' => sprintf(
                    /* translators: %s: settings sub-tab name. */
                    __('Settings: %s', 'wp-arzo'),
                    isset($tab['label']) ? $tab['label'] : $key
                ),
                'icon'  => 'admin-settings',
                'url'   => add_query_arg('tab', $key, $settings_base),
            );
        }

        // --- Standalone console tools (only the enabled ones) ------------
        $console = array(
            'info'          => array('label' => __('Site Info', 'wp-arzo'),        'icon' => 'info'),
            'users'         => array('label' => __('Users', 'wp-arzo'),            'icon' => 'admin-users'),
            'database'      => array('label' => __('Database', 'wp-arzo'),         'icon' => 'database'),
            'files'         => array('label' => __('File Manager', 'wp-arzo'),     'icon' => 'media-default'),
            'plugins'       => array('label' => __('Plugins', 'wp-arzo'),          'icon' => 'admin-plugins'),
            'themes'        => array('label' => __('Themes', 'wp-arzo'),           'icon' => 'admin-appearance'),
            'debug'         => array('label' => __('Debug', 'wp-arzo'),            'icon' => 'buddicons-replies'),
            'site_modes'    => array('label' => __('Site Modes', 'wp-arzo'),       'icon' => 'shield'),
            'extra_options' => array('label' => __('Extra Options', 'wp-arzo'),    'icon' => 'admin-tools'),
            'login'         => array('label' => __('Temporary Logins', 'wp-arzo'), 'icon' => 'admin-network'),
        );
        $console_available = function_exists('wp_arzo_console_tool_enabled');
        foreach ($console as $tab => $meta) {
            if ($tab !== 'info' && $console_available && !wp_arzo_console_tool_enabled($tab)) {
                continue;
            }
            $items[] = array(
                'id'     => 'console-' . $tab,
                'label'  => sprintf(
                    /* translators: %s: console tool name. */
                    __('Console: %s', 'wp-arzo'),
                    $meta['label']
                ),
                'icon'   => $meta['icon'],
                'url'    => admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=' . $tab),
                'newTab' => true,
            );
        }

        /**
         * Filter the WP Arzo command-palette entries (Pro adds its pages here).
         *
         * @param array               $items Command descriptors.
         * @param WP_Arzo_Admin        $admin The admin instance.
         */
        $items = apply_filters('wp_arzo_command_palette_items', $items, $this);

        return array_values(array_filter((array) $items));
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
                <button type="button" class="wpa-btn wpa-btn--ghost wpa-btn--icon" id="wpa-theme-toggle"
                    aria-pressed="<?php echo self::user_theme() === 'light' ? 'true' : 'false'; ?>"
                    aria-label="<?php esc_attr_e('Switch between light and dark theme', 'wp-arzo'); ?>"
                    title="<?php esc_attr_e('Light / dark theme', 'wp-arzo'); ?>">
                    <?php echo wp_arzo_icon('sun', array('class' => 'wpa-icon wpa-icon--sm wpa-theme-ico--sun')); ?>
                    <?php echo wp_arzo_icon('moon', array('class' => 'wpa-icon wpa-icon--sm wpa-theme-ico--moon')); ?>
                </button>
                <a class="wpa-brandbar__gh" href="https://github.com/yasirshabbirservices/wp-arzo" target="_blank" rel="noopener">
                    <?php echo wp_arzo_icon('github', array('class' => 'wpa-icon wpa-icon--sm')); ?> GitHub
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Open the page shell. On the dashboard a left rail renders the feature-category
     * filter; feature-owned pages (Backups, Roles, …) pass no categories and render
     * full-width (they're reached from the native WP-admin menu, not an in-page rail).
     * Always pair with render_shell_close().
     *
     * @param string $current    Active page key (retained for callers; nav is filter-only).
     * @param array  $categories Optional list of ['key','label','icon','count']; when empty
     *                           the rail is omitted and the content spans the full width.
     * @param int    $total      Total feature count (for the "All features" item).
     */
    private function render_shell_open($current, $categories = array(), $total = 0)
    {
        // Feature pages are "clean" — only the feature's own content, no brand header
        // and no shell/sidebar. The shell (with its category rail) renders ONLY on the
        // dashboard hub, which is the sole caller that passes categories.
        $this->shell_has_rail = !empty($categories);
        if (!$this->shell_has_rail) {
            return;
        }
        echo '<div class="wpa-shell">';
        $this->render_sidenav($current, $categories, $total);
        echo '<div class="wpa-shell__main">';
    }

    private function render_shell_close()
    {
        if (!empty($this->shell_has_rail)) {
            echo '</div></div>';
        }
    }

    /**
     * The left navigation rail: page links plus, on the dashboard, a category filter
     * that scopes the feature grid (wired up in wp-arzo-admin.js). Replaces the old
     * top-tab bar so the nav scales vertically as more pages/categories are added.
     */
    private function render_sidenav($current, $categories = array(), $total = 0)
    {
        echo '<aside class="wpa-sidenav" aria-label="WP Arzo navigation">';

        // The rail is purely a feature-grid filter now — page-owning features (Backups,
        // Roles, Content Types, …) live in the native WP-admin menu under "WP Arzo".
        if (!empty($categories)) {
            echo '<nav class="wpa-sidenav__group" aria-label="Browse features by group">';
            echo '<div class="wpa-sidenav__label">Browse</div>';
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
        <?php $wpa_pct = $total ? (int) round($enabled / $total * 100) : 0; ?>
        <div class="wpa-admin__bar wpa-fm-hero">
            <div class="wpa-fm-hero__info">
                <h1 class="wpa-admin__title"><?php echo wp_arzo_icon('tools', array('class' => 'wpa-icon')); ?> Feature Manager</h1>
                <p class="wpa-admin__subtitle">Enable only what you need — everything is off until you turn it on.</p>
                <div class="wpa-fm-progress" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo (int) $total; ?>" aria-valuenow="<?php echo (int) $enabled; ?>">
                    <div class="wpa-fm-progress__bar" style="width:<?php echo (int) $wpa_pct; ?>%"></div>
                </div>
                <div class="wpa-fm-progress__meta"><strong><?php echo (int) $enabled; ?></strong> of <?php echo (int) $total; ?> features active · <?php echo (int) $wpa_pct; ?>%</div>
            </div>
            <div class="wpa-fm-hero__actions">
                <a class="wpa-btn wpa-btn--primary" href="<?php echo esc_url(admin_url('admin.php?page=' . WP_Arzo_Setup_Wizard::PAGE)); ?>">
                    <?php echo wp_arzo_icon('sparkles', array('class' => 'wpa-icon wpa-icon--sm')); ?> Setup Wizard
                </a>
            </div>
        </div>

        <div class="wpa-layout">
            <div class="wpa-main">
                <div class="wpa-fm-searchbar" role="search">
                    <?php echo wp_arzo_icon('search', array('class' => 'wpa-icon')); ?>
                    <input type="search" id="wpa-feature-search" placeholder="Search all features…" aria-label="Search features">
                </div>
                <div id="wpa-feature-grid">
                    <?php foreach ($grouped as $group_key => $features) :
                        // Master toggle reflects the AVAILABLE (non-locked) features only.
                        $grp_avail = 0;
                        $grp_on = 0;
                        foreach ($features as $gf) {
                            if (apply_filters('wp_arzo_feature_is_available', true, $gf)) {
                                $grp_avail++;
                                if ($gf->is_enabled()) {
                                    $grp_on++;
                                }
                            }
                        }
                        $grp_all_on = ($grp_avail > 0 && $grp_on === $grp_avail);
                        ?>
                        <section class="wpa-group" id="group-<?php echo esc_attr($group_key); ?>" data-group="<?php echo esc_attr($group_key); ?>">
                            <div class="wpa-group__bar">
                                <h2 class="wpa-group__title">
                                    <?php echo wp_arzo_icon($registry->group_icon($group_key), array('class' => 'wpa-icon wpa-icon--sm')); ?>
                                    <?php echo esc_html($registry->group_label($group_key)); ?>
                                </h2>
                                <?php if ($grp_avail > 0) : ?>
                                    <label class="wpa-group__master" title="Enable or disable all features in this category">
                                        <span class="wpa-group__master-label">All</span>
                                        <span class="wpa-toggle">
                                            <input type="checkbox" class="wpa-toggle__input wpa-group-toggle" role="switch"
                                                data-group="<?php echo esc_attr($group_key); ?>"
                                                data-on="<?php echo (int) $grp_on; ?>" data-avail="<?php echo (int) $grp_avail; ?>"
                                                <?php checked($grp_all_on); ?>>
                                            <span class="wpa-toggle__track"><span class="wpa-toggle__thumb"></span></span>
                                        </span>
                                    </label>
                                <?php endif; ?>
                            </div>
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
        $promos = array();

        // The "WP Arzo Pro" upsell is pure promotion — hide it once the Pro add-on is
        // active (nothing left to promote; the License card already shows "Pro active").
        $pro_active = function_exists('wp_arzo_is_pro_active') && wp_arzo_is_pro_active();
        if (!$pro_active) {
            $promos[] = array(
                'title'      => 'WP Arzo Pro',
                'desc'       => 'Analytics & ad pixels, GSC/GTM, advanced SMTP + email logs, media manager, CPT/CCT builder, cloud backups, custom login & dashboard branding, and more.',
                'cta'        => 'Explore Pro',
                'url'        => 'https://yasirshabbir.com',
                'icon'       => 'sparkles',
                'badge'      => 'PRO',
                'badge_kind' => 'warning',
            );
        }

        $promos[] = array(
            'title'      => 'Need a custom build?',
            'desc'       => 'Yasir Shabbir builds bespoke WordPress plugins, integrations and performance work for agencies and businesses.',
            'cta'        => 'Get in touch',
            'url'        => 'https://yasirshabbir.com',
            'icon'       => 'bolt',
            'badge'      => 'SERVICE',
            'badge_kind' => 'neutral',
        );

        $promos = apply_filters('wp_arzo_promoted_products', $promos);

        if (empty($promos) || !is_array($promos)) {
            return;
        }
        ?>
        <section class="wpa-promos" aria-labelledby="wpa-promos-title">
            <div class="wpa-promos__head">
                <span class="wpa-promos__eyebrow"><?php echo wp_arzo_icon('sparkles', array('class' => 'wpa-icon wpa-icon--sm')); ?> Spotlight</span>
                <h2 id="wpa-promos-title" class="wpa-promos__title">More from Yasir Shabbir</h2>
                <p class="wpa-promos__sub">Hand-picked tools &amp; services — separate from this plugin.</p>
            </div>
            <div class="wpa-promo-grid">
                <?php foreach ($promos as $p) :
                    $title = isset($p['title']) ? $p['title'] : '';
                    $desc  = isset($p['desc']) ? $p['desc'] : '';
                    $cta   = isset($p['cta']) ? $p['cta'] : 'Learn more';
                    $url   = isset($p['url']) ? $p['url'] : '#';
                    $icon  = isset($p['icon']) ? $p['icon'] : 'bolt';
                    $badge = isset($p['badge']) ? $p['badge'] : '';
                    $bkind = isset($p['badge_kind']) ? $p['badge_kind'] : 'neutral';
                    // Whole card is one external link (single click target, keyboard focusable).
                    ?>
                    <a class="wpa-promo" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">
                        <span class="wpa-promo__icon"><?php echo wp_arzo_icon($icon, array('class' => 'wpa-icon')); ?></span>
                        <span class="wpa-promo__body">
                            <span class="wpa-promo__head">
                                <span class="wpa-promo__title"><?php echo esc_html($title); ?></span>
                                <?php if ($badge) : ?><span class="wpa-badge wpa-badge--<?php echo esc_attr($bkind); ?> wpa-promo__badge"><?php echo esc_html($badge); ?></span><?php endif; ?>
                            </span>
                            <span class="wpa-promo__desc"><?php echo esc_html($desc); ?></span>
                            <span class="wpa-promo__cta">
                                <?php echo esc_html($cta); ?>
                                <?php echo wp_arzo_icon('external', array('class' => 'wpa-icon wpa-icon--sm')); ?>
                                <span class="wpa-sr-only">(opens in a new tab)</span>
                            </span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }

    /**
     * Where a feature is configured after you enable it — its dedicated page or
     * Settings tab. Filterable (`wp_arzo_feature_manage_urls`) so Pro adds its own.
     * Returns '' if the feature has no dedicated destination (callers then fall back
     * to the schema-settings screen when the feature has settings).
     *
     * @return string
     */
    public function feature_manage_url($id)
    {
        $s = admin_url('admin.php?page=');
        $map = array(
            'smtp'              => $s . self::PAGE_EMAIL,
            'email_log'         => $s . self::PAGE_EMAIL . '&tab=logs',
            'code_snippets'     => $s . self::PAGE_SNIPPETS,
            'media_cleanup'     => $s . self::PAGE_MEDIA,
            'activity_log'      => $s . self::PAGE_ACTIVITY,
            'limit_login'       => $s . self::PAGE_SETTINGS . '&tab=login_security',
            'role_manager'      => $s . self::PAGE_SETTINGS . '&tab=roles',
            'rest_api_auth'     => $s . self::PAGE_SETTINGS . '&tab=rest_auth',
            'auto_snapshots'    => $s . self::PAGE_BACKUPS,
            'scheduled_backups' => $s . self::PAGE_BACKUPS,
        );
        $map = apply_filters('wp_arzo_feature_manage_urls', $map);
        return isset($map[$id]) ? $map[$id] : '';
    }

    /** Resolve the best "Configure" URL for a feature (dedicated page → schema settings → ''). */
    private function feature_config_url(WP_Arzo_Feature $feature)
    {
        $url = $this->feature_manage_url($feature->id());
        if ($url === '' && $feature->has_settings()) {
            $url = add_query_arg(array('page' => self::PAGE, 'view' => 'settings', 'feature' => $feature->id()), admin_url('admin.php'));
        }
        return $url;
    }

    private function render_feature_card(WP_Arzo_Feature $feature)
    {
        $id        = $feature->id();
        $enabled   = $feature->is_enabled();
        $is_pro    = $feature->tier() === 'pro';
        $manage    = $this->feature_config_url($feature);
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
                <?php if ($manage !== '') : ?>
                    <a class="wpa-btn wpa-btn--ghost wpa-btn--sm wpa-feature-card__settings<?php echo $enabled ? '' : ' is-hidden'; ?>"
                        href="<?php echo esc_url($manage); ?>"
                        aria-label="<?php echo esc_attr('Configure ' . $feature->title()); ?>">
                        <?php echo wp_arzo_icon('sliders', array('class' => 'wpa-icon wpa-icon--sm')); ?> Configure
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

        // Conditional visibility: show this field only when the controlling field(s) hold a
        // matching value. `show_if` is a single condition array('field'=>k,'value'=>v|[v,…])
        // or a list of such conditions (ALL must match). Toggled live in JS
        // (bindSettingsConditionals); a hidden field still submits, so toggling preserves it.
        $showif = '';
        if (!empty($field['show_if']) && is_array($field['show_if'])) {
            $conds = isset($field['show_if']['field']) ? array($field['show_if']) : $field['show_if'];
            $norm  = array();
            foreach ($conds as $c) {
                if (is_array($c) && !empty($c['field'])) {
                    $norm[] = array(
                        'field' => $c['field'],
                        'value' => array_map('strval', (array) (isset($c['value']) ? $c['value'] : array())),
                    );
                }
            }
            if ($norm) {
                $showif = ' data-wpa-showif="' . esc_attr(wp_json_encode($norm)) . '"';
            }
        }

        echo '<div class="wpa-field"' . $showif . '>';
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
            case 'multiselect':
                $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : array();
                $sel = is_array($value) ? array_map('strval', $value) : array();
                echo '<div class="wpa-checks">';
                foreach ($options as $oval => $olabel) {
                    $ck = in_array((string) $oval, $sel, true) ? ' checked' : '';
                    echo '<label class="wpa-check"><input type="checkbox" name="' . $name . '[]" value="' . esc_attr($oval) . '"' . $ck . '> ' . esc_html($olabel) . '</label>';
                }
                echo '</div>';
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
                case 'multiselect':
                    $options = isset($field['options']) && is_array($field['options']) ? array_map('strval', array_keys($field['options'])) : array();
                    $vals = is_array($raw) ? array_map('strval', $raw) : array();
                    $clean[$key] = array_values(array_intersect($vals, $options));
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
            'title'       => $feature->title(),
            'enabled'     => $enabled,
            'hasSettings' => $feature->has_settings(),
            'manageUrl'   => $this->feature_config_url($feature),
            'ownsPage'    => $this->feature_owns_page($id),
        ));
    }

    /** Enable or disable every available feature in a category at once. */
    public function ajax_toggle_group()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_TOGGLE, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        $group   = isset($_POST['group']) ? sanitize_key(wp_unslash($_POST['group'])) : '';
        $enabled = isset($_POST['enabled']) && ($_POST['enabled'] === '1' || $_POST['enabled'] === 'true');

        $grouped = $this->registry()->grouped();
        if (!isset($grouped[$group])) {
            wp_send_json_error(array('message' => 'Unknown category'), 404);
        }

        $changed = array();
        $owns_page = false;
        foreach ($grouped[$group] as $feature) {
            if (!apply_filters('wp_arzo_feature_is_available', true, $feature)) {
                continue; // never auto-toggle locked Pro features
            }
            $fid = $feature->id();
            if ($this->registry()->is_enabled($fid) !== $enabled) {
                $this->registry()->set_enabled($fid, $enabled);
                $changed[] = $fid;
                if ($this->feature_owns_page($fid)) {
                    $owns_page = true;
                }
            }
        }

        wp_send_json_success(array(
            'group'    => $group,
            'enabled'  => $enabled,
            'changed'  => $changed,
            'ownsPage' => $owns_page,
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

        // One Backups page, WPvivid-style: a "Local snapshots" tab plus any off-site
        // destinations (FTP / Google Drive / pCloud) that Pro registers via the filter —
        // each entry: ['label','icon','render'=>callable]. No separate menu per destination.
        $destinations = apply_filters('wp_arzo_backup_destinations', array());
        $tabs = array('local' => array('label' => 'Local snapshots', 'icon' => 'database'));
        foreach ($destinations as $id => $d) {
            $tabs[sanitize_key($id)] = array('label' => isset($d['label']) ? $d['label'] : $id, 'icon' => isset($d['icon']) ? $d['icon'] : 'cloud');
        }
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'local';
        if (!isset($tabs[$tab])) {
            $tab = 'local';
        }
        $base = admin_url('admin.php?page=' . self::PAGE_BACKUPS);

        echo '<div class="wrap wpa-admin">';
        if (count($tabs) > 1) {
            echo '<nav class="wpa-tabs" aria-label="Backups">';
            foreach ($tabs as $key => $t) {
                printf(
                    '<a class="wpa-tab%s" href="%s"%s>%s<span>%s</span></a>',
                    $key === $tab ? ' is-active' : '',
                    esc_url(add_query_arg('tab', $key, $base)),
                    $key === $tab ? ' aria-current="page"' : '',
                    wp_arzo_icon($t['icon'], array('class' => 'wpa-icon wpa-icon--sm')),
                    esc_html($t['label'])
                );
            }
            echo '</nav>';
        }

        if ($tab !== 'local' && isset($destinations[$tab]['render']) && is_callable($destinations[$tab]['render'])) {
            call_user_func($destinations[$tab]['render']);
        } else {
            $this->render_backups_local();
        }
        echo '</div>';
    }

    /** The "Local snapshots" tab body (create / list / restore / delete). */
    private function render_backups_local()
    {
        $manager   = WP_Arzo_Backup_Manager::instance();
        $snapshots = $manager->list_snapshots();
        $total     = size_format($manager->total_size());
        ?>
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
                    <button type="button" id="wpa-backup-compare" class="wpa-btn wpa-btn--ghost" <?php disabled(count($snapshots) < 2); ?>>
                        <?php echo wp_arzo_icon('list', array('class' => 'wpa-icon wpa-icon--sm')); ?> Compare
                    </button>
                </div>
            </div>

            <fieldset class="wpa-card" style="display:flex;gap:var(--arzo-space-3);flex-wrap:wrap;align-items:center;padding:var(--arzo-space-3) var(--arzo-space-4);margin-bottom:var(--arzo-space-4);">
                <legend class="screen-reader-text">Include files in the snapshot</legend>
                <span style="color:var(--arzo-text-secondary);font-weight:600;">Also include files:</span>
                <?php foreach (array('uploads' => 'Uploads', 'plugins' => 'Plugins', 'themes' => 'Themes', 'config' => 'wp-config + .htaccess') as $key => $label) : ?>
                    <label class="wpa-check"><input type="checkbox" class="wpa-backup-component" value="<?php echo esc_attr($key); ?>"> <?php echo esc_html($label); ?></label>
                <?php endforeach; ?>
                <span class="wpa-field__help" style="margin:0;">Files over 100&nbsp;MB are skipped (and counted). File snapshots enable the <strong>diff view</strong> and <strong>file restore</strong> — Restore offers database&nbsp;+&nbsp;files (config is never auto-restored).</span>
                <div class="wpa-progress wpa-progress--indeterminate" id="wpa-backup-progress" hidden aria-label="<?php esc_attr_e('Creating snapshot', 'wp-arzo'); ?>" style="flex:1 1 100%;">
                    <div class="wpa-progress__bar"></div>
                </div>
            </fieldset>

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

            <!-- Snapshot diff drawer -->
            <div class="wpa-drawer" id="wpa-diff-drawer" hidden>
                <div class="wpa-drawer__backdrop" data-close></div>
                <div class="wpa-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="wpa-diff-title">
                    <div class="wpa-drawer__head"><h2 id="wpa-diff-title">Compare snapshots</h2><button type="button" class="wpa-btn wpa-btn--ghost wpa-btn--icon" data-close aria-label="Close"><?php echo wp_arzo_icon('x', array('class' => 'wpa-icon')); ?></button></div>
                    <div class="wpa-drawer__body">
                        <div class="wpa-field">
                            <label class="wpa-field__label" for="wpa-diff-a">Base (older)</label>
                            <select class="wpa-select" data-wpa-select id="wpa-diff-a" style="width:100%;">
                                <?php foreach ($snapshots as $s) : ?>
                                    <option value="<?php echo esc_attr($s['id']); ?>"><?php echo esc_html($s['label'] . ' — ' . ($s['created_gmt'] ?? '')); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="wpa-field">
                            <label class="wpa-field__label" for="wpa-diff-b">Compare with (newer)</label>
                            <select class="wpa-select" data-wpa-select id="wpa-diff-b" style="width:100%;">
                                <?php foreach ($snapshots as $s) : ?>
                                    <option value="<?php echo esc_attr($s['id']); ?>"><?php echo esc_html($s['label'] . ' — ' . ($s['created_gmt'] ?? '')); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="margin-bottom:var(--arzo-space-4);"><button type="button" class="wpa-btn wpa-btn--primary" id="wpa-diff-run"><?php echo wp_arzo_icon('search', array('class' => 'wpa-icon wpa-icon--sm')); ?> Compare</button></div>
                        <div id="wpa-diff-result" aria-live="polite"></div>
                    </div>
                </div>
            </div>
        <?php
    }

    private function render_backup_row(array $s)
    {
        $scope_label = ($s['scope'] === 'full_db') ? 'Full DB' : 'Options';
        ?>
        <tr data-snapshot="<?php echo esc_attr($s['id']); ?>" data-components="<?php echo esc_attr(implode(',', array_diff((array) ($s['components'] ?? array()), array('config')))); ?>">
            <td>
                <strong><?php echo esc_html($s['label']); ?></strong>
                <div class="wpa-backup-meta"><?php echo (int) ($s['row_total'] ?? 0); ?> rows · <?php echo (int) ($s['table_count'] ?? 0); ?> table(s)<?php if (!empty($s['file_count'])) : ?> · <?php echo (int) $s['file_count']; ?> file(s)<?php endif; ?></div>
            </td>
            <td>
                <span class="wpa-badge wpa-badge--neutral"><?php echo esc_html($scope_label); ?></span>
                <?php foreach ((array) ($s['components'] ?? array()) as $component) : ?>
                    <span class="wpa-badge wpa-badge--accent"><?php echo esc_html($component); ?></span>
                <?php endforeach; ?>
                <?php if (!empty($s['files_skipped'])) : ?>
                    <span class="wpa-badge wpa-badge--warning" title="Files over 100 MB are not included"><?php echo (int) $s['files_skipped']; ?> skipped</span>
                <?php endif; ?>
            </td>
            <td><?php echo esc_html($s['trigger'] ?? 'manual'); ?></td>
            <td><?php echo esc_html(size_format((int) ($s['bytes'] ?? 0))); ?></td>
            <td><?php echo esc_html($s['created_gmt'] ?? ''); ?></td>
            <td class="wpa-backup-actions">
                <button type="button" class="wpa-btn wpa-btn--secondary wpa-btn--sm wpa-backup-restore" data-id="<?php echo esc_attr($s['id']); ?>">
                    <?php echo wp_arzo_icon('refresh', array('class' => 'wpa-icon wpa-icon--sm')); ?> Restore
                </button>
                <button type="button" class="wpa-btn wpa-btn--danger-soft wpa-btn--icon wpa-backup-delete" data-id="<?php echo esc_attr($s['id']); ?>" aria-label="Delete snapshot">
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
        $components = array();
        foreach ((array) ($_POST['components'] ?? array()) as $c) {
            $components[] = sanitize_key($c);
        }
        $result = WP_Arzo_Backup_Manager::instance()->create($scope, '', 'manual', $components);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 500);
        }
        wp_send_json_success(array('manifest' => $result));
    }

    public function ajax_backup_diff()
    {
        $this->verify_backup_request();
        $a = isset($_POST['a']) ? sanitize_text_field(wp_unslash($_POST['a'])) : '';
        $b = isset($_POST['b']) ? sanitize_text_field(wp_unslash($_POST['b'])) : '';
        if ($a === '' || $b === '' || $a === $b) {
            wp_send_json_error(array('message' => 'Pick two different snapshots.'));
        }
        $diff = WP_Arzo_Backup_Manager::instance()->diff($a, $b);
        if (is_wp_error($diff)) {
            wp_send_json_error(array('message' => $diff->get_error_message()));
        }
        wp_send_json_success($diff);
    }

    public function ajax_backup_restore()
    {
        $this->verify_backup_request();
        $id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';
        $include_files = !empty($_POST['include_files']);
        $manager = WP_Arzo_Backup_Manager::instance();
        $result  = $manager->restore($id, $include_files);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 500);
        }
        $msg = 'Snapshot restored.';
        $fr  = $manager->last_files_result;
        if (is_array($fr)) {
            if (!empty($fr['error'])) {
                $msg .= ' Files: ' . $fr['error'];
            } else {
                $msg .= sprintf(' Files: %d restored%s%s.',
                    (int) $fr['restored'],
                    !empty($fr['failed']) ? ', ' . (int) $fr['failed'] . ' failed' : '',
                    !empty($fr['config_skipped']) ? ', config skipped (never auto-restored)' : ''
                );
            }
        }
        wp_send_json_success(array('message' => $msg, 'files' => $fr));
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

    private function render_email_log_body()
    {
        $enabled = $this->registry()->is_enabled('email_log');
        $log = get_option('wp_arzo_email_log', array());
        if (!is_array($log)) {
            $log = array();
        }
        $failed_count = 0;
        $by_conn = array(); // connection label => ['sent'=>n,'failed'=>n]
        foreach ($log as $row) {
            $failed = (isset($row['status']) && $row['status'] === 'failed');
            if ($failed) {
                $failed_count++;
            }
            $label = (isset($row['connection']) && $row['connection'] !== '') ? (string) $row['connection'] : '—';
            if (!isset($by_conn[$label])) {
                $by_conn[$label] = array('sent' => 0, 'failed' => 0);
            }
            $by_conn[$label][$failed ? 'failed' : 'sent']++;
        }
        $total       = count($log);
        $sent_count  = $total - $failed_count;
        $pct_sent    = $total > 0 ? round(($sent_count / $total) * 100) : 0;
        $email_nonce = wp_create_nonce(self::NONCE_EMAIL);
        ?>
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
                    <div style="display:flex;gap:8px;">
                        <a class="wpa-btn wpa-btn--ghost" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wp_arzo_email_log_export'), self::NONCE_EMAIL)); ?>">
                            <?php echo wp_arzo_icon('download', array('class' => 'wpa-icon wpa-icon--sm')); ?> Export CSV
                        </a>
                        <button type="button" id="wpa-email-clear" class="wpa-btn wpa-btn--danger-soft" data-nonce="<?php echo esc_attr($email_nonce); ?>">
                            <?php echo wp_arzo_icon('trash', array('class' => 'wpa-icon wpa-icon--sm')); ?> Clear log
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($log)) : ?>
                <!-- Deliverability summary: success bar + per-connection breakdown -->
                <div class="wpa-card" style="margin-bottom:16px;">
                    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
                        <div style="flex:1 1 220px;min-width:200px;">
                            <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--arzo-text-muted);margin-bottom:6px;">
                                <span>Deliverability</span><span><strong style="color:var(--arzo-text-strong);"><?php echo (int) $pct_sent; ?>%</strong> delivered</span>
                            </div>
                            <div class="wpa-progress wpa-progress--lg" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int) $pct_sent; ?>" aria-label="<?php esc_attr_e('Deliverability', 'wp-arzo'); ?>">
                                <div class="wpa-progress__bar" style="width:100%;">
                                    <span class="wpa-progress__seg" style="width:<?php echo (int) $pct_sent; ?>%;background:var(--arzo-success);"></span>
                                    <span class="wpa-progress__seg" style="width:<?php echo (int) (100 - $pct_sent); ?>%;background:var(--arzo-error);"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($by_conn)) : ?>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:14px;">
                            <?php foreach ($by_conn as $label => $c) : ?>
                                <span class="wpa-badge wpa-badge--neutral" title="<?php echo esc_attr(sprintf('%d sent · %d failed', $c['sent'], $c['failed'])); ?>">
                                    <?php echo wp_arzo_icon('mail', array('class' => 'wpa-icon')); ?>
                                    <?php echo esc_html($label); ?>
                                    <strong style="margin-left:4px;color:var(--arzo-success);"><?php echo (int) $c['sent']; ?></strong>
                                    <?php if ($c['failed']) : ?><strong style="margin-left:2px;color:var(--arzo-error);">/ <?php echo (int) $c['failed']; ?></strong><?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Filter toolbar -->
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;align-items:center;">
                    <div style="flex:1 1 240px;min-width:200px;position:relative;">
                        <input type="search" id="wpa-emaillog-search" class="wpa-input" placeholder="Search recipient, subject or connection…" style="width:100%;" autocomplete="off" />
                    </div>
                    <select id="wpa-emaillog-status" class="wpa-input" data-wpa-select>
                        <option value="all">All statuses</option>
                        <option value="sent">Sent</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
            <?php endif; ?>

            <div class="wpa-card" style="padding:0;overflow:hidden;">
                <table class="wpa-backup-table" id="wpa-emaillog-table">
                    <thead>
                        <tr><th>Time (UTC)</th><th>To</th><th>Subject</th><th>Connection</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($log)) : ?>
                            <tr class="wpa-backup-empty"><td colspan="5">No emails logged yet.</td></tr>
                        <?php else : foreach ($log as $row) :
                            $status = isset($row['status']) ? $row['status'] : 'sent';
                            $failed = ($status === 'failed');
                            $id     = isset($row['id']) ? (string) $row['id'] : '';
                            $conn   = (isset($row['connection']) && $row['connection'] !== '') ? (string) $row['connection'] : '';
                            $needle = strtolower(($row['to'] ?? '') . ' ' . ($row['subject'] ?? '') . ' ' . $conn);
                            ?>
                            <tr class="wpa-email-row" data-id="<?php echo esc_attr($id); ?>" data-status="<?php echo esc_attr($failed ? 'failed' : 'sent'); ?>" data-filter="<?php echo esc_attr($needle); ?>" tabindex="0" style="cursor:pointer;">
                                <td><?php echo esc_html(gmdate('Y-m-d H:i:s', (int) ($row['time'] ?? 0))); ?></td>
                                <td><?php echo esc_html($row['to'] ?? ''); ?></td>
                                <td><?php echo esc_html($row['subject'] ?? ''); ?></td>
                                <td style="color:var(--arzo-text-muted);"><?php echo $conn !== '' ? esc_html($conn) : '—'; ?></td>
                                <td>
                                    <span class="wpa-badge <?php echo $failed ? 'wpa-badge--error' : 'wpa-badge--success'; ?>">
                                        <?php echo wp_arzo_icon($failed ? 'x' : 'check', array('class' => 'wpa-icon')); ?>
                                        <?php echo $failed ? 'Failed' : 'Sent'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
                <?php if (!empty($log)) : ?>
                    <div id="wpa-emaillog-empty" hidden style="padding:24px;text-align:center;color:var(--arzo-text-muted);">No emails match your filter.</div>
                <?php endif; ?>
            </div>

            <?php if (!empty($log)) : ?>
                <!-- Detail drawer (filled by JS from wp_arzo_email_log_detail) -->
                <div class="wpa-drawer" id="wpa-emaillog-drawer" hidden data-nonce="<?php echo esc_attr($email_nonce); ?>">
                    <div class="wpa-drawer__backdrop" data-maillog-close></div>
                    <div class="wpa-drawer__panel" role="dialog" aria-modal="true" aria-label="Email details">
                        <div class="wpa-drawer__head">
                            <h2 id="wpa-emaillog-drawer-title">Email details</h2>
                            <button type="button" class="wpa-btn wpa-btn--ghost wpa-btn--icon" data-maillog-close aria-label="Close"><?php echo wp_arzo_icon('x', array('class' => 'wpa-icon')); ?></button>
                        </div>
                        <div class="wpa-drawer__body" id="wpa-emaillog-detail"><!-- filled by JS --></div>
                        <div class="wpa-drawer__foot">
                            <button type="button" class="wpa-btn wpa-btn--ghost" data-maillog-close>Close</button>
                            <button type="button" class="wpa-btn wpa-btn--primary wpa-email-resend" id="wpa-emaillog-resend" data-id="" data-nonce="<?php echo esc_attr($email_nonce); ?>">
                                <?php echo wp_arzo_icon('refresh', array('class' => 'wpa-icon wpa-icon--sm')); ?> Resend
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
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

    /* ------------------------------------------- Import / Export handlers */

    /** Open a CSV download stream (capability-checked). @return resource */
    private function stream_csv($filename)
    {
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        return fopen('php://output', 'w');
    }

    /** Export the email log as CSV. */
    public function handle_email_log_export()
    {
        if (!current_user_can('manage_options') || !isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), self::NONCE_EMAIL)) {
            wp_die('Security check failed', '', array('response' => 403));
        }
        $log = get_option('wp_arzo_email_log', array());
        $out = $this->stream_csv('wp-arzo-email-log-' . gmdate('Ymd-His') . '.csv');
        fputcsv($out, array('Time (UTC)', 'To', 'Subject', 'Connection', 'Status', 'Error'));
        foreach ((array) $log as $r) {
            fputcsv($out, array(
                gmdate('Y-m-d H:i:s', (int) ($r['time'] ?? 0)),
                $r['to'] ?? '', $r['subject'] ?? '', $r['connection'] ?? '',
                $r['status'] ?? 'sent', $r['error'] ?? '',
            ));
        }
        fclose($out);
        exit;
    }

    /** Export the (free) activity log as CSV. */
    public function handle_activity_export()
    {
        if (!current_user_can('manage_options') || !isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), self::NONCE_ACTIVITY)) {
            wp_die('Security check failed', '', array('response' => 403));
        }
        $log = class_exists('WP_Arzo_Activity_Log') ? WP_Arzo_Activity_Log::instance()->get_log() : array();
        $out = $this->stream_csv('wp-arzo-activity-log-' . gmdate('Ymd-His') . '.csv');
        fputcsv($out, array('Time (UTC)', 'Severity', 'User', 'Action', 'Details', 'IP'));
        foreach ((array) $log as $r) {
            $a    = $r['a'] ?? '';
            $meta = class_exists('WP_Arzo_Activity_Log') ? WP_Arzo_Activity_Log::action_meta($a) : array($a);
            $sev  = class_exists('WP_Arzo_Activity_Log') ? WP_Arzo_Activity_Log::severity_meta($a)[0] : '';
            fputcsv($out, array(
                gmdate('Y-m-d H:i:s', (int) ($r['t'] ?? 0)),
                $sev, $r['ul'] ?? '', $meta[0], $r['o'] ?? '', $r['ip'] ?? '',
            ));
        }
        fclose($out);
        exit;
    }

    /** Export all snippets as a portable JSON file. */
    public function handle_snippets_export()
    {
        if (!current_user_can('manage_options') || !isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), self::NONCE_SNIPPETS)) {
            wp_die('Security check failed', '', array('response' => 403));
        }
        $snips  = class_exists('WP_Arzo_Snippets') ? WP_Arzo_Snippets::instance()->get_all() : array();
        $export = array();
        foreach ((array) $snips as $s) {
            $export[] = array(
                'title'       => $s['title'] ?? '',
                'description' => $s['description'] ?? '',
                'type'        => $s['type'] ?? 'php',
                'scope'       => $s['scope'] ?? 'everywhere',
                'priority'    => (int) ($s['priority'] ?? 10),
                'code'        => $s['code'] ?? '',
                'active'      => (int) ($s['active'] ?? 0),
            );
        }
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=wp-arzo-snippets-' . gmdate('Ymd-His') . '.json');
        echo wp_json_encode(array('plugin' => 'wp-arzo', 'type' => 'snippets', 'version' => WP_ARZO_VERSION, 'snippets' => $export), JSON_PRETTY_PRINT);
        exit;
    }

    /** Import snippets from an uploaded JSON file (added inactive for safety). */
    public function handle_snippets_import()
    {
        if (!current_user_can('manage_options') || !check_admin_referer('wp_arzo_snippets_import')) {
            wp_die('Security check failed', '', array('response' => 403));
        }
        $redirect = admin_url('admin.php?page=' . self::PAGE_SNIPPETS);
        if (empty($_FILES['snippets_file']['tmp_name']) || !is_uploaded_file($_FILES['snippets_file']['tmp_name'])) {
            wp_safe_redirect(add_query_arg('import', 'nofile', $redirect));
            exit;
        }
        $data    = json_decode((string) file_get_contents($_FILES['snippets_file']['tmp_name']), true);
        $manager = class_exists('WP_Arzo_Snippets') ? WP_Arzo_Snippets::instance() : null;
        if (!$manager || !is_array($data) || empty($data['snippets']) || !is_array($data['snippets'])) {
            wp_safe_redirect(add_query_arg('import', 'bad', $redirect));
            exit;
        }
        $count = 0;
        foreach ($data['snippets'] as $s) {
            if (!is_array($s) || !isset($s['code'])) {
                continue;
            }
            $manager->save(array(
                'id'          => '',
                'title'       => isset($s['title']) ? $s['title'] : 'Imported snippet',
                'description' => isset($s['description']) ? $s['description'] : '',
                'type'        => isset($s['type']) ? $s['type'] : 'php',
                'scope'       => isset($s['scope']) ? $s['scope'] : 'everywhere',
                'priority'    => isset($s['priority']) ? (int) $s['priority'] : 10,
                'code'        => $s['code'],
                'active'      => 0, // imported disabled — review before enabling
            ));
            $count++;
        }
        wp_safe_redirect(add_query_arg(array('import' => 'ok', 'n' => $count), $redirect));
        exit;
    }

    /** Return one logged email (for the Logs-tab detail drawer). */
    public function ajax_email_log_detail()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_EMAIL, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        $id  = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';
        $log = get_option('wp_arzo_email_log', array());
        $entry = null;
        foreach ((array) $log as $row) {
            if (isset($row['id']) && $row['id'] === $id) {
                $entry = $row;
                break;
            }
        }
        if (!$entry) {
            wp_send_json_error(array('message' => 'Log entry not found.'), 404);
        }
        $headers = isset($entry['headers']) ? $entry['headers'] : '';
        if (is_array($headers)) {
            $headers = implode("\n", $headers);
        }
        wp_send_json_success(array(
            'id'         => (string) ($entry['id'] ?? ''),
            'time'       => gmdate('Y-m-d H:i:s', (int) ($entry['time'] ?? 0)) . ' UTC',
            'to'         => (string) ($entry['to'] ?? ''),
            'subject'    => (string) ($entry['subject'] ?? ''),
            'status'     => (string) ($entry['status'] ?? 'sent'),
            'error'      => (string) ($entry['error'] ?? ''),
            'connection' => (string) ($entry['connection'] ?? ''),
            'headers'    => (string) $headers,
            'message'    => (string) ($entry['message'] ?? ''),
            'resendable' => isset($entry['message']) && $entry['to'] !== '',
        ));
    }

    /* ------------------------------------------------ Email Connections */

    private function connections()
    {
        return class_exists('WP_Arzo_Email_Connections') ? WP_Arzo_Email_Connections::instance() : null;
    }

    /**
     * The unified Email page — one menu, SureMail-style tabs (Connections / Logs /
     * Settings). Each tab body is rendered inline so all email tooling lives in one place.
     */
    public function render_email()
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        // Default-tab best practice: land on the most useful tab. Once at least one
        // connection exists, that's the Log (what's happening); with none, send the user
        // to Connections so they meet the setup wizard. Logs is listed first either way.
        $conn         = $this->connections();
        $has_conn     = $conn ? $conn->count() > 0 : false;
        $default_tab  = $has_conn ? 'logs' : 'connections';
        $tab          = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : $default_tab;
        $queue_pending = class_exists('WP_Arzo_Email_Queue') ? WP_Arzo_Email_Queue::instance()->pending_count() : 0;
        $tabs = array(
            'logs'        => array('label' => 'Logs', 'icon' => 'list'),
            'queue'       => array('label' => 'Queue', 'icon' => 'refresh', 'badge' => $queue_pending),
            'connections' => array('label' => 'Connections', 'icon' => 'mail'),
            'settings'    => array('label' => 'Settings', 'icon' => 'settings'),
        );
        if (!isset($tabs[$tab])) {
            $tab = $default_tab;
        }
        $base = admin_url('admin.php?page=' . self::PAGE_EMAIL);

        echo '<div class="wrap wpa-admin">';
        $this->render_shell_open('email');

        echo '<nav class="wpa-tabs" aria-label="Email tools">';
        foreach ($tabs as $key => $t) {
            $badge = (!empty($t['badge']))
                ? ' <span class="wpa-badge wpa-badge--accent" style="margin-left:2px;">' . (int) $t['badge'] . '</span>'
                : '';
            printf(
                '<a class="wpa-tab%s" href="%s"%s>%s<span>%s</span>%s</a>',
                $key === $tab ? ' is-active' : '',
                esc_url(add_query_arg('tab', $key, $base)),
                $key === $tab ? ' aria-current="page"' : '',
                wp_arzo_icon($t['icon'], array('class' => 'wpa-icon wpa-icon--sm')),
                esc_html($t['label']),
                $badge
            );
        }
        echo '</nav>';

        if ($tab === 'logs') {
            $this->render_email_log_body();
        } elseif ($tab === 'queue') {
            $this->render_email_queue_body();
        } elseif ($tab === 'settings') {
            $this->render_email_settings_body();
        } else {
            $this->render_connections_body();
        }

        $this->render_shell_close();
        echo '</div>';
    }

    private function render_email_queue_body()
    {
        $queue = class_exists('WP_Arzo_Email_Queue') ? WP_Arzo_Email_Queue::instance() : null;
        $items = $queue ? $queue->all() : array();
        $nonce = wp_create_nonce(self::NONCE_EMAIL);
        // Newest first.
        usort($items, function ($a, $b) {
            return strcmp((string) $b['created_gmt'], (string) $a['created_gmt']);
        });
        $pending = 0;
        $failed  = 0;
        foreach ($items as $i) {
            if ($i['status'] === 'pending') {
                $pending++;
            } else {
                $failed++;
            }
        }
        ?>
        <div class="wpa-admin__bar">
            <div>
                <h1 class="wpa-admin__title"><?php echo wp_arzo_icon('refresh', array('class' => 'wpa-icon')); ?> Retry Queue</h1>
                <p class="wpa-admin__subtitle">
                    <?php if (empty($items)) : ?>
                        Messages that every connection failed to send are queued here and re-tried automatically (5m → 15m → 1h → 6h, up to 4 tries).
                    <?php else : ?>
                        <strong><?php echo (int) $pending; ?></strong> pending · <strong><?php echo (int) $failed; ?></strong> gave up · auto-retries 5m → 15m → 1h → 6h.
                    <?php endif; ?>
                </p>
            </div>
            <?php if (!empty($items)) : ?>
                <div style="display:flex;gap:8px;align-items:center;">
                    <?php if ($pending > 0) : ?>
                        <button type="button" id="wpa-eq-retry-all" class="wpa-btn wpa-btn--primary" data-nonce="<?php echo esc_attr($nonce); ?>"><?php echo wp_arzo_icon('refresh', array('class' => 'wpa-icon wpa-icon--sm')); ?> Retry all now</button>
                    <?php endif; ?>
                    <button type="button" id="wpa-eq-clear" class="wpa-btn wpa-btn--danger-soft" data-nonce="<?php echo esc_attr($nonce); ?>"><?php echo wp_arzo_icon('trash', array('class' => 'wpa-icon wpa-icon--sm')); ?> Clear queue</button>
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($items)) : ?>
            <div class="wpa-card" style="text-align:center;padding:40px;">
                <?php echo wp_arzo_icon('check-circle', array('class' => 'wpa-icon wpa-icon--xl', 'style' => 'color:var(--arzo-accent)')); ?>
                <p style="margin:12px 0 0;color:var(--arzo-text-secondary);">The queue is empty — every email has gone out.</p>
            </div>
        <?php else : ?>
            <table class="wpa-table" id="wpa-eq-table" style="width:100%;">
                <thead><tr>
                    <th>To</th><th>Subject</th><th>Status</th><th>Tries</th><th>Next attempt (UTC)</th><th>Last error</th><th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($items as $it) :
                    $is_failed = ($it['status'] !== 'pending'); ?>
                    <tr data-id="<?php echo esc_attr($it['id']); ?>">
                        <td><?php echo esc_html($it['to']); ?></td>
                        <td><?php echo esc_html($it['subject'] !== '' ? $it['subject'] : '(no subject)'); ?></td>
                        <td><?php echo $is_failed
                            ? '<span class="wpa-badge wpa-badge--error">gave up</span>'
                            : '<span class="wpa-badge wpa-badge--warning">pending</span>'; ?></td>
                        <td><?php echo (int) $it['attempts']; ?> / <?php echo (int) WP_Arzo_Email_Queue::MAX_ATTEMPTS; ?></td>
                        <td><?php echo $is_failed ? '—' : esc_html($it['next_gmt']); ?></td>
                        <td style="max-width:280px;"><span style="color:var(--arzo-text-secondary);font-size:var(--arzo-fs-sm);"><?php echo esc_html($it['last_error']); ?></span></td>
                        <td class="wpa-backup-actions" style="white-space:nowrap;">
                            <button type="button" class="wpa-btn wpa-btn--ghost wpa-btn--sm wpa-eq-retry" data-id="<?php echo esc_attr($it['id']); ?>" data-nonce="<?php echo esc_attr($nonce); ?>"><?php echo wp_arzo_icon('refresh', array('class' => 'wpa-icon wpa-icon--sm')); ?> Retry</button>
                            <button type="button" class="wpa-btn wpa-btn--danger-soft wpa-btn--icon wpa-btn--sm wpa-eq-del" data-id="<?php echo esc_attr($it['id']); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" aria-label="Delete from queue"><?php echo wp_arzo_icon('trash', array('class' => 'wpa-icon wpa-icon--sm')); ?></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <script>
            (function () {
                var ajax = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
                function post(action, data) {
                    var body = new FormData();
                    body.append('action', action);
                    Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
                    return fetch(ajax, { method: 'POST', credentials: 'same-origin', body: body }).then(function (r) { return r.json(); });
                }
                function toast(m, t) { if (window.wpArzo && window.wpArzo.toast) { window.wpArzo.toast(m, t || 'success'); } }
                document.querySelectorAll('.wpa-eq-retry').forEach(function (b) {
                    b.addEventListener('click', function () {
                        b.disabled = true;
                        post('wp_arzo_email_queue_retry', { nonce: b.dataset.nonce, id: b.dataset.id }).then(function (res) {
                            if (res.success && res.data && res.data.delivered) { toast('Delivered ✓'); }
                            else { toast((res.data && res.data.error) ? ('Still failing: ' + res.data.error) : 'Retry failed', 'error'); }
                            location.reload();
                        });
                    });
                });
                document.querySelectorAll('.wpa-eq-del').forEach(function (b) {
                    b.addEventListener('click', function () {
                        post('wp_arzo_email_queue_delete', { nonce: b.dataset.nonce, id: b.dataset.id }).then(function () { location.reload(); });
                    });
                });
                var ra = document.getElementById('wpa-eq-retry-all');
                if (ra) { ra.addEventListener('click', function () { ra.disabled = true; post('wp_arzo_email_queue_retry_all', { nonce: ra.dataset.nonce }).then(function (res) { if (res.success) { toast('Retried: ' + res.data.ok + ' sent, ' + res.data.fail + ' still failing'); } location.reload(); }); }); }
                var cl = document.getElementById('wpa-eq-clear');
                if (cl) { cl.addEventListener('click', function () { if (!confirm('Clear the entire retry queue? Queued messages will be discarded.')) { return; } post('wp_arzo_email_queue_clear', { nonce: cl.dataset.nonce }).then(function () { location.reload(); }); }); }
            })();
        </script>
        <?php
    }

    public function ajax_email_queue_retry()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_EMAIL, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        $id  = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';
        $res = class_exists('WP_Arzo_Email_Queue') ? WP_Arzo_Email_Queue::instance()->retry($id) : array('ok' => false, 'error' => 'Queue unavailable');
        wp_send_json_success(array('delivered' => !empty($res['ok']), 'error' => isset($res['error']) ? $res['error'] : ''));
    }

    public function ajax_email_queue_retry_all()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_EMAIL, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        $res = class_exists('WP_Arzo_Email_Queue') ? WP_Arzo_Email_Queue::instance()->retry_all() : array('ok' => 0, 'fail' => 0);
        wp_send_json_success($res);
    }

    public function ajax_email_queue_delete()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_EMAIL, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        $id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';
        if (class_exists('WP_Arzo_Email_Queue')) {
            WP_Arzo_Email_Queue::instance()->delete($id);
        }
        wp_send_json_success();
    }

    public function ajax_email_queue_clear()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_EMAIL, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        if (class_exists('WP_Arzo_Email_Queue')) {
            WP_Arzo_Email_Queue::instance()->clear();
        }
        wp_send_json_success();
    }

    private function render_email_settings_body()
    {
        $feature = $this->registry()->get('smtp');
        if (!$feature) {
            echo '<p>Email delivery feature unavailable.</p>';
            return;
        }
        $saved  = $this->maybe_save_settings($feature);
        $schema = $feature->settings_schema();
        ?>
        <div class="wpa-admin__bar">
            <div>
                <h1 class="wpa-admin__title"><?php echo wp_arzo_icon('settings', array('class' => 'wpa-icon')); ?> Email settings</h1>
                <p class="wpa-admin__subtitle">Delivery is configured under <strong>Connections</strong>; these are the failure alerts.</p>
            </div>
        </div>
        <?php if ($saved) : ?>
            <div class="wpa-toast wpa-toast--success" style="position:static;margin-bottom:16px;display:inline-flex;"><?php echo wp_arzo_icon('check', array('class' => 'wpa-icon wpa-icon--sm')); ?> Settings saved.</div>
        <?php endif; ?>
        <form method="post" class="wpa-card">
            <?php wp_nonce_field(self::NONCE_SETTINGS, 'wp_arzo_settings_nonce'); ?>
            <?php foreach ($schema as $field) {
                $this->render_field($feature, $field);
            } ?>
            <div style="margin-top:20px;">
                <button type="submit" name="wp_arzo_save_settings" class="wpa-btn wpa-btn--primary"><?php echo wp_arzo_icon('check', array('class' => 'wpa-icon wpa-icon--sm')); ?> Save settings</button>
            </div>
        </form>
        <?php
    }

    private function render_connections_body()
    {
        $engine = $this->connections();
        if (!$engine) {
            echo '<p>Email engine unavailable.</p>';
            return;
        }
        $enabled     = $this->registry()->is_enabled('smtp');
        $providers   = WP_Arzo_Email_Connections::providers();
        $connections = $engine->all();

        // Provider schemas + existing connection values (secrets stripped) for the JS drawer.
        $schemas = array();
        foreach ($providers as $key => $def) {
            $schemas[$key] = array(
                'label'     => $def['label'],
                'transport' => $def['transport'],
                'fields'    => WP_Arzo_Email_Connections::fields_for($key),
            );
        }
        $conn_js = array();
        foreach ($connections as $i => $c) {
            foreach (array('password', 'api_key') as $secret) {
                if (isset($c[$secret]) && $c[$secret] !== '') {
                    $c[$secret] = '';
                    $c['_has_' . $secret] = true;
                }
            }
            $c['_primary'] = ($i === 0);
            $conn_js[] = $c;
        }
        ?>
            <div class="wpa-admin__bar">
                <div>
                    <h1 class="wpa-admin__title"><?php echo wp_arzo_icon('mail', array('class' => 'wpa-icon')); ?> Connections</h1>
                    <p class="wpa-admin__subtitle"><strong><?php echo count($connections); ?></strong> connection(s)<?php echo $enabled ? '' : ' · the “Email Delivery” feature is OFF, so nothing is routed (enable it on the dashboard)'; ?></p>
                </div>
                <button type="button" class="wpa-btn wpa-btn--primary" id="wpa-conn-add"><?php echo wp_arzo_icon('plus', array('class' => 'wpa-icon wpa-icon--sm')); ?> Add connection</button>
            </div>

            <?php if (empty($connections)) : ?>
                <?php $wiz_steps = array('Provider', 'Configure', 'Test', 'Done'); ?>
                <div class="wpa-card wpa-ewiz" id="wpa-email-wizard" data-admin-email="<?php echo esc_attr(get_option('admin_email')); ?>">
                    <div class="wpa-ewiz__steps">
                        <?php foreach ($wiz_steps as $i => $label) : ?>
                            <div class="wpa-ewiz__step<?php echo $i === 0 ? ' is-active' : ''; ?>" data-step-ind="<?php echo (int) $i; ?>">
                                <span class="wpa-ewiz__num"><?php echo (int) $i + 1; ?></span>
                                <span class="wpa-ewiz__step-label"><?php echo esc_html($label); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="wpa-ewiz__body">
                        <!-- Step 1: choose a provider -->
                        <section data-step="provider">
                            <div class="wpa-ewiz__center" style="margin-bottom:22px;">
                                <span class="wpa-ewiz__hero"><?php echo wp_arzo_icon('mail', array('class' => 'wpa-icon')); ?></span>
                                <h2 style="margin:0 0 6px;">Connect your first email provider</h2>
                                <p style="color:var(--arzo-text-muted);margin:0;">Route WordPress email through a reliable provider — Gmail, Outlook, Amazon SES, SendGrid and more. Pick one to get started; you can add fallbacks later.</p>
                            </div>
                            <div class="wpa-provider-grid" style="padding:0;">
                                <?php foreach ($providers as $key => $def) : ?>
                                    <button type="button" class="wpa-provider-card wpa-ewiz-provider" data-provider="<?php echo esc_attr($key); ?>">
                                        <span class="wpa-provider-card__icon"><?php echo wp_arzo_icon($def['icon'], array('class' => 'wpa-icon')); ?></span>
                                        <span class="wpa-provider-card__name"><?php echo esc_html($def['label']); ?></span>
                                        <?php if (!empty($def['badge'])) : ?><span class="wpa-badge wpa-badge--<?php echo $def['badge'] === 'Recommended' ? 'success' : 'neutral'; ?> wpa-provider-card__badge"><?php echo esc_html($def['badge']); ?></span><?php endif; ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <!-- Step 2: configure the chosen provider -->
                        <section data-step="configure" hidden>
                            <h2 style="margin:0 0 4px;" id="wpa-ewiz-conf-title">Configure</h2>
                            <p style="color:var(--arzo-text-muted);margin:0 0 20px;">Enter the connection details. Secrets are stored on your site only.</p>
                            <form class="wpa-ewiz__form" id="wpa-ewiz-form"><!-- fields injected by JS --></form>
                            <div class="wpa-ewiz__foot">
                                <button type="button" class="wpa-btn wpa-btn--ghost" data-ewiz-back>Back</button>
                                <button type="button" class="wpa-btn wpa-btn--primary" id="wpa-ewiz-save"><?php echo wp_arzo_icon('check', array('class' => 'wpa-icon wpa-icon--sm')); ?> Save &amp; continue</button>
                            </div>
                        </section>

                        <!-- Step 3: send a test -->
                        <section data-step="test" hidden>
                            <div class="wpa-ewiz__center">
                                <span class="wpa-ewiz__hero"><?php echo wp_arzo_icon('mail', array('class' => 'wpa-icon')); ?></span>
                                <h2 style="margin:0 0 6px;">Send a test email</h2>
                                <p style="color:var(--arzo-text-muted);margin:0 0 18px;">Confirm the connection works by sending a test message.</p>
                                <div class="wpa-field" style="text-align:left;max-width:360px;margin:0 auto;">
                                    <label class="wpa-field__label" for="wpa-ewiz-test-to">Send test to</label>
                                    <input class="wpa-input" type="email" id="wpa-ewiz-test-to" value="<?php echo esc_attr(get_option('admin_email')); ?>" style="width:100%;">
                                </div>
                                <p id="wpa-ewiz-test-msg" style="margin:14px 0 0;min-height:20px;"></p>
                            </div>
                            <div class="wpa-ewiz__foot">
                                <button type="button" class="wpa-btn wpa-btn--ghost" data-ewiz-skip-test>Skip</button>
                                <button type="button" class="wpa-btn wpa-btn--primary" id="wpa-ewiz-test-btn"><?php echo wp_arzo_icon('mail', array('class' => 'wpa-icon wpa-icon--sm')); ?> Send test</button>
                            </div>
                        </section>

                        <!-- Step 4: done -->
                        <section data-step="done" hidden>
                            <div class="wpa-ewiz__center">
                                <span class="wpa-ewiz__hero" style="background:var(--arzo-success-soft);color:var(--arzo-success);"><?php echo wp_arzo_icon('check', array('class' => 'wpa-icon')); ?></span>
                                <h2 style="margin:0 0 6px;">You're all set</h2>
                                <p style="color:var(--arzo-text-muted);margin:0 0 20px;">Your first connection is live and now sends your site's email. Add more providers to build an automatic fallback chain.</p>
                                <button type="button" class="wpa-btn wpa-btn--primary" id="wpa-ewiz-finish"><?php echo wp_arzo_icon('check', array('class' => 'wpa-icon wpa-icon--sm')); ?> View connections</button>
                            </div>
                        </section>
                    </div>
                </div>
            <?php else : ?>
                <div class="wpa-card" style="padding:0;overflow:hidden;">
                    <table class="wpa-backup-table">
                        <thead><tr><th></th><th>Connection</th><th>Provider</th><th>From</th><th>Priority</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($connections as $i => $c) :
                                $pdef = isset($providers[$c['provider']]) ? $providers[$c['provider']] : array('label' => $c['provider'], 'icon' => 'mail');
                                ?>
                                <tr data-conn="<?php echo esc_attr($c['id']); ?>">
                                    <td style="width:34px;color:var(--arzo-accent);"><?php echo wp_arzo_icon($pdef['icon'], array('class' => 'wpa-icon')); ?></td>
                                    <td><strong style="color:var(--arzo-text-strong);"><?php echo esc_html($c['title']); ?></strong></td>
                                    <td><?php echo esc_html($pdef['label']); ?></td>
                                    <td style="color:var(--arzo-text-muted);"><?php echo esc_html(!empty($c['from_email']) ? $c['from_email'] : '—'); ?></td>
                                    <td>
                                        <?php if ($i === 0) : ?>
                                            <span class="wpa-badge wpa-badge--success"><?php echo wp_arzo_icon('check', array('class' => 'wpa-icon')); ?> Primary</span>
                                        <?php else : ?>
                                            <span class="wpa-badge wpa-badge--neutral">Fallback <?php echo (int) $i; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="wpa-backup-actions" style="white-space:nowrap;">
                                        <button type="button" class="wpa-btn wpa-btn--ghost wpa-btn--sm wpa-conn-test" data-id="<?php echo esc_attr($c['id']); ?>"><?php echo wp_arzo_icon('mail', array('class' => 'wpa-icon wpa-icon--sm')); ?> Test</button>
                                        <?php if ($i !== 0) : ?>
                                            <button type="button" class="wpa-btn wpa-btn--ghost wpa-btn--sm wpa-conn-primary" data-id="<?php echo esc_attr($c['id']); ?>">Make primary</button>
                                        <?php endif; ?>
                                        <button type="button" class="wpa-btn wpa-btn--ghost wpa-btn--sm wpa-conn-edit" data-id="<?php echo esc_attr($c['id']); ?>"><?php echo wp_arzo_icon('edit', array('class' => 'wpa-icon wpa-icon--sm')); ?></button>
                                        <button type="button" class="wpa-btn wpa-btn--danger-soft wpa-btn--icon wpa-conn-delete" data-id="<?php echo esc_attr($c['id']); ?>" aria-label="Delete connection"><?php echo wp_arzo_icon('trash', array('class' => 'wpa-icon wpa-icon--sm')); ?></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="wpa-field__help" style="margin-top:12px;">Connections are tried top-to-bottom: the <strong>Primary</strong> sends your mail, and if it fails the next connection is tried automatically. “Make primary” reorders them.</p>
            <?php endif; ?>

            <!-- Provider picker (radio cards) -->
            <div class="wpa-modal" id="wpa-conn-picker" hidden>
                <div class="wpa-modal__backdrop" data-conn-close></div>
                <div class="wpa-modal__panel" role="dialog" aria-modal="true" aria-label="Choose an email provider">
                    <div class="wpa-modal__head">
                        <h2>Choose a provider</h2>
                        <button type="button" class="wpa-btn wpa-btn--ghost wpa-btn--icon" data-conn-close aria-label="Close"><?php echo wp_arzo_icon('x', array('class' => 'wpa-icon')); ?></button>
                    </div>
                    <div class="wpa-provider-grid">
                        <?php foreach ($providers as $key => $def) : ?>
                            <button type="button" class="wpa-provider-card" data-provider="<?php echo esc_attr($key); ?>">
                                <span class="wpa-provider-card__icon"><?php echo wp_arzo_icon($def['icon'], array('class' => 'wpa-icon')); ?></span>
                                <span class="wpa-provider-card__name"><?php echo esc_html($def['label']); ?></span>
                                <?php if (!empty($def['badge'])) : ?><span class="wpa-badge wpa-badge--<?php echo $def['badge'] === 'Recommended' ? 'success' : 'neutral'; ?> wpa-provider-card__badge"><?php echo esc_html($def['badge']); ?></span><?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Config drawer (form built by JS) -->
            <div class="wpa-drawer" id="wpa-conn-drawer" hidden>
                <div class="wpa-drawer__backdrop" data-conn-close></div>
                <div class="wpa-drawer__panel" role="dialog" aria-modal="true" aria-label="Configure connection">
                    <div class="wpa-drawer__head">
                        <h2 id="wpa-conn-drawer-title">Configure</h2>
                        <button type="button" class="wpa-btn wpa-btn--ghost wpa-btn--icon" data-conn-close aria-label="Close"><?php echo wp_arzo_icon('x', array('class' => 'wpa-icon')); ?></button>
                    </div>
                    <form class="wpa-drawer__body" id="wpa-conn-form"><!-- fields injected here --></form>
                    <div class="wpa-drawer__foot">
                        <button type="button" class="wpa-btn wpa-btn--ghost" data-conn-back>Back</button>
                        <button type="button" class="wpa-btn wpa-btn--primary" id="wpa-conn-save"><?php echo wp_arzo_icon('check', array('class' => 'wpa-icon wpa-icon--sm')); ?> Save connection</button>
                    </div>
                </div>
            </div>

            <script>window.wpArzoConn = <?php echo wp_json_encode(array('providers' => $schemas, 'connections' => $conn_js)); ?>;</script>
        <?php
    }

    private function verify_conn_request()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_EMAIL_CONN, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
    }

    public function ajax_conn_save()
    {
        $this->verify_conn_request();
        $engine = $this->connections();
        if (!$engine) {
            wp_send_json_error(array('message' => 'Engine unavailable'), 500);
        }
        $data = isset($_POST['fields']) && is_array($_POST['fields']) ? wp_unslash($_POST['fields']) : array();
        $data['provider'] = isset($_POST['provider']) ? sanitize_key(wp_unslash($_POST['provider'])) : '';
        $data['id']       = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';
        $res = $engine->save($data);
        if (is_wp_error($res)) {
            wp_send_json_error(array('message' => $res->get_error_message()));
        }
        wp_send_json_success(array('id' => $res));
    }

    public function ajax_conn_delete()
    {
        $this->verify_conn_request();
        $engine = $this->connections();
        $ok = $engine && $engine->delete(isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '');
        wp_send_json_success(array('deleted' => (bool) $ok));
    }

    public function ajax_conn_primary()
    {
        $this->verify_conn_request();
        $engine = $this->connections();
        $ok = $engine && $engine->set_primary(isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '');
        wp_send_json_success(array('ok' => (bool) $ok));
    }

    public function ajax_conn_reorder()
    {
        $this->verify_conn_request();
        $engine = $this->connections();
        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('sanitize_text_field', wp_unslash($_POST['ids'])) : array();
        $ok = $engine && $engine->reorder($ids);
        wp_send_json_success(array('ok' => (bool) $ok));
    }

    public function ajax_conn_test()
    {
        $this->verify_conn_request();
        $engine = $this->connections();
        if (!$engine) {
            wp_send_json_error(array('message' => 'Engine unavailable'), 500);
        }
        $res = $engine->test(
            isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '',
            isset($_POST['to']) ? sanitize_email(wp_unslash($_POST['to'])) : ''
        );
        if (is_wp_error($res)) {
            wp_send_json_error(array('message' => $res->get_error_message()));
        }
        wp_send_json_success(array('message' => 'Test email sent — check the inbox.'));
    }

    /* --------------------------------------------------------- Settings hub */

    /**
     * Tab registry for the consolidated Settings hub. Free tabs are added here (each
     * gated by its feature); Pro modules register theirs via `wp_arzo_settings_tabs`.
     * Each entry: ['label','icon','order','cb' => callable that renders the tab body].
     */
    private function settings_tabs()
    {
        $tabs = array();
        if ($this->page_visible(self::PAGE_LOGIN_SECURITY)) {
            $tabs['login_security'] = array('label' => 'Login Security', 'icon' => 'lock', 'order' => 20, 'cb' => array($this, 'render_login_security'));
        }
        if ($this->page_visible(self::PAGE_ROLES)) {
            $tabs['roles'] = array('label' => 'Roles', 'icon' => 'users', 'order' => 30, 'cb' => array($this, 'render_roles'));
        }
        if ($this->page_visible(self::PAGE_REST_AUTH)) {
            $tabs['rest_auth'] = array('label' => 'REST API Auth', 'icon' => 'key', 'order' => 32, 'cb' => array($this, 'render_rest_auth'));
        }
        $tabs['config_io'] = array('label' => 'Import / Export', 'icon' => 'download', 'order' => 90, 'cb' => array($this, 'render_config_io'));

        $tabs = apply_filters('wp_arzo_settings_tabs', $tabs);
        if (!is_array($tabs)) {
            $tabs = array();
        }
        uasort($tabs, function ($a, $b) {
            return (isset($a['order']) ? $a['order'] : 50) <=> (isset($b['order']) ? $b['order'] : 50);
        });
        return $tabs;
    }

    /** The Settings hub page — a tabbed shell that renders each folded page as a tab. */
    public function render_settings_hub()
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        $tabs = $this->settings_tabs();
        if (empty($tabs)) {
            echo '<div class="wrap wpa-admin"><p>No settings available.</p></div>';
            return;
        }
        $keys = array_keys($tabs);
        $tab  = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : $keys[0];
        if (!isset($tabs[$tab])) {
            $tab = $keys[0];
        }
        $base = admin_url('admin.php?page=' . self::PAGE_SETTINGS);

        echo '<div class="wrap wpa-admin">';
        echo '<nav class="wpa-tabs" role="tablist" aria-label="Settings">';
        foreach ($tabs as $key => $t) {
            printf(
                '<a class="wpa-tab%s" role="tab" aria-selected="%s" id="wpa-set-tab-%s" href="%s">%s<span>%s</span></a>',
                $key === $tab ? ' is-active' : '',
                $key === $tab ? 'true' : 'false',
                esc_attr($key),
                esc_url(add_query_arg('tab', $key, $base)),
                wp_arzo_icon(isset($t['icon']) ? $t['icon'] : 'settings', array('class' => 'wpa-icon wpa-icon--sm')),
                esc_html(isset($t['label']) ? $t['label'] : $key)
            );
        }
        echo '</nav>';

        echo '<div role="tabpanel" aria-labelledby="wpa-set-tab-' . esc_attr($tab) . '">';
        $this->rendering_tab = true;
        if (isset($tabs[$tab]['cb']) && is_callable($tabs[$tab]['cb'])) {
            call_user_func($tabs[$tab]['cb']);
        }
        $this->rendering_tab = false;
        echo '</div></div>';
    }

    /* --------------------------------------------------- Login Security */

    public function render_login_security()
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        $notice   = $this->maybe_handle_lockout_action();
        $enabled  = $this->registry()->is_enabled('limit_login');
        $lockouts = class_exists('WP_Arzo_Feature_Limit_Login') ? WP_Arzo_Feature_Limit_Login::active_lockouts() : array();
        $now      = time();
        ?>
        <?php if (!$this->rendering_tab) : ?><div class="wrap wpa-admin"><?php endif; ?>
            <?php $this->render_shell_open('login_security'); ?>
            <div class="wpa-admin__bar">
                <div>
                    <h1 class="wpa-admin__title"><?php echo wp_arzo_icon('shield', array('class' => 'wpa-icon')); ?> Login Security</h1>
                    <p class="wpa-admin__subtitle"><strong><?php echo count($lockouts); ?></strong> active lockout(s)<?php echo $enabled ? '' : ' · “Limit Login Attempts” is OFF (enable it on the dashboard)'; ?></p>
                </div>
                <?php if (!empty($lockouts)) : ?>
                    <form method="post" onsubmit="return confirm('Lift all active lockouts?');">
                        <?php wp_nonce_field(self::NONCE_LOGIN_SECURITY, 'wp_arzo_ls_nonce'); ?>
                        <input type="hidden" name="ls_action" value="clear_all">
                        <button type="submit" class="wpa-btn wpa-btn--ghost"><?php echo wp_arzo_icon('unlock', array('class' => 'wpa-icon wpa-icon--sm')); ?> Unlock all</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php if ($notice) : ?>
                <div class="wpa-toast wpa-toast--success" style="position:static;margin-bottom:16px;display:inline-flex;"><?php echo wp_arzo_icon('check', array('class' => 'wpa-icon wpa-icon--sm')); ?> <?php echo esc_html($notice); ?></div>
            <?php endif; ?>

            <div class="wpa-card" style="padding:0;overflow:hidden;">
                <table class="wpa-backup-table">
                    <thead><tr><th>IP address</th><th>Attempted user</th><th>Locked at (UTC)</th><th>Unlocks in</th><th></th></tr></thead>
                    <tbody>
                        <?php if (empty($lockouts)) : ?>
                            <tr class="wpa-backup-empty"><td colspan="5">No active lockouts.</td></tr>
                        <?php else : foreach ($lockouts as $l) :
                            $remaining = max(0, (int) $l['until'] - $now);
                            ?>
                            <tr>
                                <td><code><?php echo esc_html($l['ip']); ?></code></td>
                                <td><?php echo $l['user'] !== '' ? esc_html($l['user']) : '<span style="color:var(--arzo-text-muted)">—</span>'; ?></td>
                                <td><?php echo esc_html($l['time']); ?></td>
                                <td><?php echo esc_html($this->ls_human_duration($remaining)); ?></td>
                                <td class="wpa-backup-actions">
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field(self::NONCE_LOGIN_SECURITY, 'wp_arzo_ls_nonce'); ?>
                                        <input type="hidden" name="ls_action" value="unlock">
                                        <input type="hidden" name="hash" value="<?php echo esc_attr($l['hash']); ?>">
                                        <button type="submit" class="wpa-btn wpa-btn--ghost wpa-btn--sm"><?php echo wp_arzo_icon('unlock', array('class' => 'wpa-icon wpa-icon--sm')); ?> Unlock</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="wpa-field__help" style="margin-top:14px;">Set the attempt threshold, lockout duration, trusted-IP allowlist and lockout alerts in the <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE)); ?>">Limit Login Attempts settings</a> on the dashboard.</p>
            <?php $this->render_shell_close(); ?>
        <?php if (!$this->rendering_tab) : ?></div><?php endif; ?>
        <?php
    }

    /** Handle the unlock / unlock-all POST from the Login Security page. */
    private function maybe_handle_lockout_action()
    {
        if (!isset($_POST['ls_action'])) {
            return '';
        }
        if (!current_user_can('manage_options') || !isset($_POST['wp_arzo_ls_nonce']) ||
            !wp_verify_nonce(wp_unslash($_POST['wp_arzo_ls_nonce']), self::NONCE_LOGIN_SECURITY)) {
            wp_die('Security check failed.');
        }
        if (!class_exists('WP_Arzo_Feature_Limit_Login')) {
            return '';
        }
        $action = sanitize_key(wp_unslash($_POST['ls_action']));
        if ($action === 'unlock') {
            WP_Arzo_Feature_Limit_Login::unlock(isset($_POST['hash']) ? wp_unslash($_POST['hash']) : '');
            return 'Lockout lifted.';
        }
        if ($action === 'clear_all') {
            WP_Arzo_Feature_Limit_Login::clear_all_lockouts();
            return 'All lockouts lifted.';
        }
        return '';
    }

    /** Format a remaining-seconds count as a compact human string. */
    private function ls_human_duration($seconds)
    {
        $seconds = (int) $seconds;
        if ($seconds <= 0) {
            return 'expiring…';
        }
        $m = intdiv($seconds, 60);
        $s = $seconds % 60;
        if ($m >= 60) {
            $h = intdiv($m, 60);
            $m = $m % 60;
            return $h . 'h ' . $m . 'm';
        }
        return $m > 0 ? ($m . 'm ' . $s . 's') : ($s . 's');
    }

    /* ----------------------------------------------------- Activity Log */

    public function render_activity_log()
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'events';
        if (!in_array($tab, array('events', 'sessions'), true)) {
            $tab = 'events';
        }
        $base = admin_url('admin.php?page=' . self::PAGE_ACTIVITY);
        $active = class_exists('WP_Arzo_Activity_Sessions') ? WP_Arzo_Activity_Sessions::count_active() : 0;

        echo '<div class="wrap wpa-admin">';

        // Tabbed: Events (the audit trail — free capped view or the Pro DB-backed
        // Advanced Audit that upgrades it in place) + Sessions (live logged-in users).
        $tabs = array(
            'events'   => array('label' => 'Events', 'icon' => 'list'),
            'sessions' => array('label' => 'Sessions', 'icon' => 'users', 'badge' => $active),
        );
        echo '<nav class="wpa-tabs" role="tablist" aria-label="Activity Log">';
        foreach ($tabs as $key => $t) {
            $badge = isset($t['badge']) && $t['badge'] > 0
                ? ' <span class="wpa-badge wpa-badge--accent" style="margin-left:2px;">' . (int) $t['badge'] . '</span>'
                : '';
            printf(
                '<a class="wpa-tab%s" role="tab" aria-selected="%s" href="%s"%s>%s<span>%s</span>%s</a>',
                $key === $tab ? ' is-active' : '',
                $key === $tab ? 'true' : 'false',
                esc_url(add_query_arg('tab', $key, $base)),
                $key === $tab ? ' aria-current="page"' : '',
                wp_arzo_icon($t['icon'], array('class' => 'wpa-icon wpa-icon--sm')),
                esc_html($t['label']),
                $badge
            );
        }
        echo '</nav>';

        if ($tab === 'sessions') {
            $this->render_activity_sessions();
        } else {
            // The Pro Advanced Audit Log upgrades the Events view in place — durable DB
            // storage, advanced filters, CSV export, AJAX pagination. When it's not active,
            // the free capped-option log renders instead. (One page — no separate menu.)
            if (apply_filters('wp_arzo_activity_log_render', false) !== true) {
                $this->render_activity_log_basic();
            }
        }
        echo '</div>';
    }

    /** Activity Log → Sessions tab: live logged-in users + terminate (force logout). */
    private function render_activity_sessions()
    {
        $sessions = class_exists('WP_Arzo_Activity_Sessions') ? WP_Arzo_Activity_Sessions::all() : array();
        $users = array();
        $active = 0;
        foreach ($sessions as $s) {
            $users[$s['user_id']] = true;
            if (empty($s['expired'])) {
                $active++;
            }
        }
        $nonce = wp_create_nonce(self::NONCE_ACTIVITY);
        ?>
            <div class="wpa-admin__bar">
                <div>
                    <h1 class="wpa-admin__title"><?php echo wp_arzo_icon('users', array('class' => 'wpa-icon')); ?> Live Sessions</h1>
                    <p class="wpa-admin__subtitle">
                        <span class="wpa-badge wpa-badge--info"><?php echo (int) $active; ?> active</span>
                        across <?php echo (int) count($users); ?> user<?php echo count($users) === 1 ? '' : 's'; ?> · terminate a session to force that device to sign out
                    </p>
                </div>
            </div>

            <div class="wpa-card" style="padding:0;overflow:hidden;">
                <table class="wpa-backup-table" id="wpa-sessions-table">
                    <thead>
                        <tr><th>User</th><th>IP</th><th>Signed in (UTC)</th><th>Expires (UTC)</th><th>Client</th><th style="text-align:right;">Action</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sessions)) : ?>
                            <tr class="wpa-backup-empty"><td colspan="6">No active login sessions found.</td></tr>
                        <?php else : foreach ($sessions as $s) :
                            $client = $this->short_user_agent($s['ua']);
                            ?>
                            <tr data-user="<?php echo (int) $s['user_id']; ?>" data-verifier="<?php echo esc_attr($s['verifier']); ?>"<?php echo $s['expired'] ? ' style="opacity:.55;"' : ''; ?>>
                                <td>
                                    <?php if (!empty($s['edit'])) : ?><a href="<?php echo esc_url($s['edit']); ?>"><?php endif; ?>
                                    <strong><?php echo esc_html($s['user_login']); ?></strong>
                                    <?php if (!empty($s['edit'])) : ?></a><?php endif; ?>
                                    <?php if ($s['is_current']) : ?> <span class="wpa-badge wpa-badge--success">This device</span><?php endif; ?>
                                    <?php if ($s['expired']) : ?> <span class="wpa-badge wpa-badge--neutral">Expired</span><?php endif; ?>
                                    <?php if (!empty($s['roles'])) : ?><br><span style="color:var(--arzo-text-muted);font-size:.8rem;"><?php echo esc_html($s['roles']); ?></span><?php endif; ?>
                                </td>
                                <td><code><?php echo esc_html($s['ip']); ?></code></td>
                                <td style="white-space:nowrap;"><?php echo $s['login'] ? esc_html(gmdate('Y-m-d H:i', $s['login'])) : '—'; ?></td>
                                <td style="white-space:nowrap;"><?php echo $s['expiration'] ? esc_html(gmdate('Y-m-d H:i', $s['expiration'])) : '—'; ?></td>
                                <td><span title="<?php echo esc_attr($s['ua']); ?>"><?php echo esc_html($client); ?></span></td>
                                <td style="text-align:right;">
                                    <?php if ($s['is_current']) : ?>
                                        <button type="button" class="wpa-btn wpa-btn--icon" disabled aria-label="Your current session cannot be terminated" title="Your current session"><?php echo wp_arzo_icon('lock', array('class' => 'wpa-icon wpa-icon--sm')); ?></button>
                                    <?php else : ?>
                                        <button type="button" class="wpa-btn wpa-btn--icon wpa-btn--danger-soft wpa-session-kill" aria-label="Terminate this session" title="Terminate session"><?php echo wp_arzo_icon('x-circle', array('class' => 'wpa-icon wpa-icon--sm')); ?></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <script>
            (function () {
                var table = document.getElementById('wpa-sessions-table');
                if (!table) { return; }
                var nonce = <?php echo wp_json_encode($nonce); ?>;
                table.addEventListener('click', function (e) {
                    var btn = e.target.closest ? e.target.closest('.wpa-session-kill') : null;
                    if (!btn) { return; }
                    var tr = btn.closest('tr');
                    if (!tr || !window.confirm('Terminate this session? That device will be signed out immediately.')) { return; }
                    btn.disabled = true;
                    var body = new FormData();
                    body.append('action', 'wp_arzo_activity_session_kill');
                    body.append('nonce', nonce);
                    body.append('user_id', tr.dataset.user);
                    body.append('verifier', tr.dataset.verifier);
                    fetch(ajaxurl, { method: 'POST', body: body, credentials: 'same-origin' })
                        .then(function (r) { return r.json(); })
                        .then(function (res) {
                            if (res && res.success) {
                                tr.parentNode.removeChild(tr);
                                if (window.wpArzo && wpArzo.toast) { wpArzo.toast(res.data.message || 'Session terminated.', 'success'); }
                            } else {
                                btn.disabled = false;
                                if (window.wpArzo && wpArzo.toast) { wpArzo.toast((res && res.data && res.data.message) || 'Could not terminate.', 'error'); }
                            }
                        })
                        .catch(function () { btn.disabled = false; });
                });
            })();
            </script>
        <?php
    }

    /** Condense a raw User-Agent string to a short "Browser · OS" label. */
    private function short_user_agent($ua)
    {
        $ua = (string) $ua;
        if ($ua === '') {
            return '—';
        }
        $browser = 'Browser';
        foreach (array('Edg' => 'Edge', 'OPR' => 'Opera', 'Chrome' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari') as $needle => $name) {
            if (stripos($ua, $needle) !== false) {
                $browser = $name;
                break;
            }
        }
        $os = '';
        foreach (array('Windows' => 'Windows', 'Mac OS' => 'macOS', 'Android' => 'Android', 'iPhone' => 'iOS', 'iPad' => 'iPadOS', 'Linux' => 'Linux') as $needle => $name) {
            if (stripos($ua, $needle) !== false) {
                $os = $name;
                break;
            }
        }
        return $os ? $browser . ' · ' . $os : $browser;
    }

    /** The free (capped-option) Activity Log body. */
    private function render_activity_log_basic()
    {
        $enabled = $this->registry()->is_enabled('activity_log');
        $log = class_exists('WP_Arzo_Activity_Log') ? WP_Arzo_Activity_Log::instance()->get_log() : array();
        $stats = class_exists('WP_Arzo_Activity_Log') ? WP_Arzo_Activity_Log::instance()->stats()
            : array('total' => count($log), 'last24' => 0, 'failed24' => 0, 'high24' => 0);

        // Action types present, for the event filter dropdown (label-sorted).
        $present = array();
        foreach ($log as $row) {
            $a = isset($row['a']) ? $row['a'] : '';
            if ($a !== '') {
                $present[$a] = WP_Arzo_Activity_Log::action_meta($a)[0];
            }
        }
        asort($present);
        $severities = WP_Arzo_Activity_Log::severities();

        $nonce = wp_create_nonce(self::NONCE_ACTIVITY);

        // Summary tiles (state-aware: the whole log + the last 24h at a glance).
        $tiles = array(
            array('info',    'list',      $stats['total'],    'Total events'),
            array('info',    'clock',     $stats['last24'],   'Last 24 hours'),
            array($stats['high24'] ? 'warning' : 'neutral', 'shield', $stats['high24'], 'High/critical · 24h'),
            array($stats['failed24'] ? 'error' : 'neutral', 'lock',  $stats['failed24'], 'Failed sign-ins · 24h'),
        );
        ?>
            <div class="wpa-admin__bar">
                <div>
                    <h1 class="wpa-admin__title"><?php echo wp_arzo_icon('shield', array('class' => 'wpa-icon')); ?> Activity Log</h1>
                    <p class="wpa-admin__subtitle">
                        <span class="wpa-badge wpa-badge--info"><?php echo (int) $stats['total']; ?> recorded</span>
                        <?php echo $enabled ? '' : ' · logging is OFF (enable “Activity Log” on the dashboard)'; ?>
                    </p>
                </div>
                <?php if (!empty($log)) : ?>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <a class="wpa-btn wpa-btn--ghost" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wp_arzo_activity_export'), self::NONCE_ACTIVITY)); ?>">
                            <?php echo wp_arzo_icon('download', array('class' => 'wpa-icon wpa-icon--sm')); ?> Export CSV
                        </a>
                        <button type="button" id="wpa-activity-clear" class="wpa-btn wpa-btn--danger-soft" data-nonce="<?php echo esc_attr($nonce); ?>">
                            <?php echo wp_arzo_icon('trash', array('class' => 'wpa-icon wpa-icon--sm')); ?> Clear log
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="wpa-card" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:var(--arzo-space-4,16px);margin-bottom:var(--arzo-space-4,16px);">
                <?php foreach ($tiles as $t) : ?>
                    <div style="display:flex;align-items:center;gap:var(--arzo-space-3,12px);">
                        <span class="wpa-badge wpa-badge--<?php echo esc_attr($t[0]); ?>" style="width:2.25rem;height:2.25rem;padding:0;justify-content:center;border-radius:var(--arzo-radius,10px);flex:0 0 auto;">
                            <?php echo wp_arzo_icon($t[1], array('class' => 'wpa-icon')); ?>
                        </span>
                        <span style="display:flex;flex-direction:column;line-height:1.2;">
                            <strong style="font-size:1.4rem;"><?php echo (int) $t[2]; ?></strong>
                            <span style="color:var(--arzo-text-muted);font-size:.8rem;"><?php echo esc_html($t[3]); ?></span>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($log)) : ?>
            <div class="wpa-card" id="wpa-activity-filters" style="margin-bottom:var(--arzo-space-4,16px);display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:var(--arzo-space-3,12px);align-items:end;">
                <div class="wpa-field" style="margin:0;">
                    <label class="wpa-field__label" for="wpa-act-sev">Severity</label>
                    <select class="wpa-input" id="wpa-act-sev" data-wpa-select>
                        <option value="">All severities</option>
                        <?php foreach ($severities as $k => $s) : ?>
                            <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($s[0]); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="wpa-field" style="margin:0;">
                    <label class="wpa-field__label" for="wpa-act-action">Event</label>
                    <select class="wpa-input" id="wpa-act-action" data-wpa-select>
                        <option value="">All events</option>
                        <?php foreach ($present as $a => $label) : ?>
                            <option value="<?php echo esc_attr($a); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="wpa-field" style="margin:0;">
                    <label class="wpa-field__label" for="wpa-act-q">Search</label>
                    <input class="wpa-input" type="search" id="wpa-act-q" placeholder="detail, user or IP…" autocomplete="off">
                </div>
                <div style="display:flex;gap:var(--arzo-space-2,8px);">
                    <button type="button" id="wpa-act-reset" class="wpa-btn wpa-btn--ghost wpa-btn--sm" aria-label="Clear all filters" hidden><?php echo wp_arzo_icon('x', array('class' => 'wpa-icon wpa-icon--sm')); ?> Reset</button>
                </div>
            </div>
            <?php endif; ?>

            <div class="wpa-card" style="padding:0;overflow:hidden;">
                <table class="wpa-backup-table" id="wpa-activity-table">
                    <thead>
                        <tr><th>Time (UTC)</th><th>Severity</th><th>Event</th><th>Detail</th><th>User</th><th>IP</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($log)) : ?>
                            <tr class="wpa-backup-empty"><td colspan="6">No activity recorded yet.</td></tr>
                        <?php else : foreach ($log as $row) :
                            $a    = isset($row['a']) ? $row['a'] : '';
                            $meta = WP_Arzo_Activity_Log::action_meta($a);
                            $sev  = WP_Arzo_Activity_Log::action_severity($a);
                            $sm   = $severities[$sev];
                            $detail = $row['o'] ?? '';
                            $user   = $row['ul'] ?? '—';
                            $ip     = $row['ip'] ?? '';
                            $search = strtolower($detail . ' ' . $user . ' ' . $ip . ' ' . $meta[0]);
                            ?>
                            <tr data-action="<?php echo esc_attr($a); ?>" data-sev="<?php echo esc_attr($sev); ?>" data-search="<?php echo esc_attr($search); ?>">
                                <td style="white-space:nowrap;"><?php echo esc_html(gmdate('Y-m-d H:i:s', (int) ($row['t'] ?? 0))); ?></td>
                                <td><span class="wpa-badge wpa-badge--<?php echo esc_attr($sm[1]); ?>"><?php echo wp_arzo_icon($sm[2], array('class' => 'wpa-icon')); ?> <?php echo esc_html($sm[0]); ?></span></td>
                                <td>
                                    <span class="wpa-badge wpa-badge--<?php echo esc_attr($meta[1]); ?>">
                                        <?php echo wp_arzo_icon($meta[2], array('class' => 'wpa-icon')); ?>
                                        <?php echo esc_html($meta[0]); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $url = WP_Arzo_Activity_Log::object_edit_url($row['lt'] ?? '', $row['li'] ?? 0);
                                    if ($url) {
                                        printf(
                                            '<a href="%s" title="Open this item">%s %s</a>',
                                            esc_url($url),
                                            esc_html($detail),
                                            wp_arzo_icon('external', array('class' => 'wpa-icon wpa-icon--sm'))
                                        );
                                    } else {
                                        echo esc_html($detail);
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html($user); ?></td>
                                <td><code><?php echo esc_html($ip); ?></code></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        <tr id="wpa-activity-empty" class="wpa-backup-empty" hidden><td colspan="6">No events match your filter.</td></tr>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($log)) : ?>
            <script>
            (function () {
                var sev = document.getElementById('wpa-act-sev'),
                    act = document.getElementById('wpa-act-action'),
                    q = document.getElementById('wpa-act-q'),
                    reset = document.getElementById('wpa-act-reset'),
                    table = document.getElementById('wpa-activity-table'),
                    empty = document.getElementById('wpa-activity-empty');
                if (!table) { return; }
                var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-action]'));
                function apply() {
                    var s = sev ? sev.value : '', a = act ? act.value : '',
                        text = (q ? q.value : '').trim().toLowerCase(), shown = 0;
                    rows.forEach(function (tr) {
                        var ok = (!s || tr.dataset.sev === s) &&
                                 (!a || tr.dataset.action === a) &&
                                 (!text || tr.dataset.search.indexOf(text) !== -1);
                        tr.hidden = !ok;
                        if (ok) { shown++; }
                    });
                    if (empty) { empty.hidden = shown !== 0; }
                    if (reset) { reset.hidden = !(s || a || text); }
                }
                [sev, act].forEach(function (el) { if (el) { el.addEventListener('change', apply); } });
                if (q) { q.addEventListener('input', apply); }
                if (reset) {
                    reset.addEventListener('click', function () {
                        if (q) { q.value = ''; }
                        [sev, act].forEach(function (el) {
                            if (!el) { return; }
                            el.value = '';
                            if (window.wpArzo && wpArzo.setSelectValue) { wpArzo.setSelectValue(el, ''); }
                        });
                        apply();
                    });
                }
            })();
            </script>
            <?php endif; ?>
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

    /** Terminate one live login session (force logout of that device). */
    public function ajax_session_kill()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_ACTIVITY, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        if (!class_exists('WP_Arzo_Activity_Sessions')) {
            wp_send_json_error(array('message' => 'Sessions unavailable.'), 500);
        }
        $user_id  = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $verifier = isset($_POST['verifier']) ? preg_replace('/[^a-f0-9]/i', '', (string) wp_unslash($_POST['verifier'])) : '';
        if (!$user_id || $verifier === '') {
            wp_send_json_error(array('message' => 'Missing session reference.'), 400);
        }
        // Never let an admin cut off their own current session from under themselves.
        if (hash_equals(WP_Arzo_Activity_Sessions::current_verifier(), $verifier)) {
            wp_send_json_error(array('message' => 'You cannot terminate your own current session.'), 400);
        }
        if (!WP_Arzo_Activity_Sessions::terminate($user_id, $verifier)) {
            wp_send_json_error(array('message' => 'Session not found (it may have already ended).'), 404);
        }
        if (class_exists('WP_Arzo_Activity_Log')) {
            $u = get_userdata($user_id);
            WP_Arzo_Activity_Log::instance()->record('session_terminated', 'Terminated a session for ' . ($u ? $u->user_login : '#' . $user_id), 0, array('type' => 'user', 'id' => $user_id));
        }
        wp_send_json_success(array('message' => 'Session terminated.'));
    }

    /* -------------------------------------------------------- Analytics */

    /** Preset date ranges → [from_ts, to_ts]. */
    private function analytics_range($key)
    {
        $now = time();
        $end = $now;
        switch ($key) {
            case 'today':
                $start = strtotime(gmdate('Y-m-d 00:00:00'));
                break;
            case '7d':
                $start = $now - 7 * DAY_IN_SECONDS;
                break;
            case '90d':
                $start = $now - 90 * DAY_IN_SECONDS;
                break;
            case '30d':
            default:
                $start = $now - 30 * DAY_IN_SECONDS;
                break;
        }
        return array($start, $end);
    }

    private function fmt_duration($seconds)
    {
        $seconds = max(0, (int) $seconds);
        $m = floor($seconds / 60);
        $s = $seconds % 60;
        return sprintf('%d:%02d', $m, $s);
    }

    /** Self-hosted SVG area+line chart (CSP-safe, token-colored) for a daily series. */
    private function analytics_chart($series)
    {
        $n = count($series);
        if ($n === 0) {
            return '';
        }
        $w = 960;
        $h = 220;
        $padX = 8;
        $padY = 16;
        $max = 1;
        foreach ($series as $p) {
            if ($p['views'] > $max) {
                $max = $p['views'];
            }
        }
        $innerW = $w - $padX * 2;
        $innerH = $h - $padY * 2;
        $step = $n > 1 ? $innerW / ($n - 1) : 0;

        $pts = array();
        foreach ($series as $i => $p) {
            $x = $padX + $step * $i;
            $y = $padY + $innerH - ($p['views'] / $max) * $innerH;
            $pts[] = array(round($x, 1), round($y, 1), $p);
        }
        $line = '';
        foreach ($pts as $i => $pt) {
            $line .= ($i === 0 ? 'M' : 'L') . $pt[0] . ' ' . $pt[1] . ' ';
        }
        $area = $line . 'L' . $pts[$n - 1][0] . ' ' . ($padY + $innerH) . ' L' . $pts[0][0] . ' ' . ($padY + $innerH) . ' Z';

        $first = esc_html($series[0]['date']);
        $lastd = esc_html($series[$n - 1]['date']);
        $mid   = esc_html($series[intdiv($n - 1, 2)]['date']);

        $dots = '';
        foreach ($pts as $pt) {
            $dots .= '<circle cx="' . $pt[0] . '" cy="' . $pt[1] . '" r="2.5" fill="var(--arzo-accent)"><title>'
                . esc_html($pt[2]['date'] . ': ' . $pt[2]['views'] . ' views · ' . $pt[2]['visitors'] . ' visitors') . '</title></circle>';
        }

        return '<div class="wpa-card" style="padding:var(--arzo-space-4,16px);margin-bottom:var(--arzo-space-4,16px);overflow:hidden;">'
            . '<svg viewBox="0 0 ' . $w . ' ' . ($h + 22) . '" width="100%" role="img" aria-label="Pageviews over time" style="display:block;">'
            . '<defs><linearGradient id="wpaAnFill" x1="0" y1="0" x2="0" y2="1">'
            . '<stop offset="0%" stop-color="var(--arzo-accent)" stop-opacity="0.28"/>'
            . '<stop offset="100%" stop-color="var(--arzo-accent)" stop-opacity="0"/></linearGradient></defs>'
            . '<line x1="' . $padX . '" y1="' . ($padY + $innerH) . '" x2="' . ($w - $padX) . '" y2="' . ($padY + $innerH) . '" stroke="var(--arzo-border)" />'
            . '<path d="' . $area . '" fill="url(#wpaAnFill)" />'
            . '<path d="' . trim($line) . '" fill="none" stroke="var(--arzo-accent)" stroke-width="2" stroke-linejoin="round" />'
            . $dots
            . '<text x="' . $padX . '" y="' . ($h + 14) . '" fill="var(--arzo-text-muted)" font-size="12">' . $first . '</text>'
            . '<text x="' . ($w / 2) . '" y="' . ($h + 14) . '" fill="var(--arzo-text-muted)" font-size="12" text-anchor="middle">' . $mid . '</text>'
            . '<text x="' . ($w - $padX) . '" y="' . ($h + 14) . '" fill="var(--arzo-text-muted)" font-size="12" text-anchor="end">' . $lastd . '</text>'
            . '<text x="' . ($w - $padX) . '" y="' . ($padY - 4) . '" fill="var(--arzo-text-muted)" font-size="12" text-anchor="end">peak ' . (int) $max . '</text>'
            . '</svg></div>';
    }

    private function analytics_tabs()
    {
        $tabs = array(
            'overview'  => array('label' => 'Overview', 'icon' => 'chart'),
            'geo'       => array('label' => 'Geo', 'icon' => 'globe'),
            'devices'   => array('label' => 'Devices', 'icon' => 'grid'),
            'behaviour' => array('label' => 'Behaviour', 'icon' => 'exchange'),
            'google'    => array('label' => 'Google', 'icon' => 'bolt'),
        );
        /**
         * Filter the Analytics report tabs. Add-ons (WP Arzo Pro) append tabs here
         * (each: ['label'=>, 'icon'=>]) and render their body via `wp_arzo_analytics_render`.
         *
         * @param array $tabs tab-key => ['label','icon'].
         */
        return apply_filters('wp_arzo_analytics_tabs', $tabs);
    }

    /** Two regional-indicator letters → flag emoji, or '' if not a country code. */
    private function flag_emoji($cc)
    {
        $cc = strtoupper((string) $cc);
        if (strlen($cc) !== 2 || !ctype_alpha($cc) || !function_exists('mb_chr')) {
            return '';
        }
        return mb_chr(0x1F1E6 + ord($cc[0]) - 65, 'UTF-8') . mb_chr(0x1F1E6 + ord($cc[1]) - 65, 'UTF-8');
    }

    /** Render one breakdown card (label / views / visitors) — the P2 report primitive. */
    private function analytics_breakdown($title, $icon, $rows, $labelType = 'text')
    {
        ob_start();
        ?>
        <div class="wpa-card" style="padding:0;overflow:hidden;">
            <div style="padding:var(--arzo-space-3,12px) var(--arzo-space-4,16px);font-weight:600;"><?php echo wp_arzo_icon($icon, array('class' => 'wpa-icon wpa-icon--sm')); ?> <?php echo esc_html($title); ?></div>
            <table class="wpa-backup-table">
                <thead><tr><th><?php echo esc_html($labelType === 'country' ? 'Country' : 'Name'); ?></th><th style="text-align:right;">Views</th><th style="text-align:right;">Visitors</th></tr></thead>
                <tbody>
                    <?php if (empty($rows)) : ?>
                        <tr class="wpa-backup-empty"><td colspan="3">Nothing recorded in this range yet.</td></tr>
                    <?php else : foreach ($rows as $r) :
                        $label = (string) $r['label']; ?>
                        <tr>
                            <td>
                                <?php if ($labelType === 'country') {
                                    $flag = $this->flag_emoji($label);
                                    echo ($flag !== '' ? '<span aria-hidden="true">' . esc_html($flag) . '</span> ' : '') . esc_html($label);
                                } elseif ($labelType === 'path') {
                                    echo '<a href="' . esc_url(home_url($label)) . '" target="_blank" rel="noopener">' . esc_html($label) . '</a>';
                                } else {
                                    echo esc_html($label !== '' ? $label : '—');
                                } ?>
                            </td>
                            <td style="text-align:right;"><?php echo esc_html(number_format_i18n($r['views'])); ?></td>
                            <td style="text-align:right;color:var(--arzo-text-muted);"><?php echo esc_html(number_format_i18n($r['visitors'])); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    /** Dispatch the analytics body by report tab. */
    private function analytics_body($from, $to, $tab = 'overview')
    {
        if (!class_exists('WP_Arzo_Analytics')) {
            return '<div class="wpa-card">Analytics engine unavailable.</div>';
        }
        $engine = WP_Arzo_Analytics::instance();

        if ($tab === 'geo') {
            return '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:var(--arzo-space-4,16px);">'
                . $this->analytics_breakdown('Countries', 'globe', $engine->breakdown('country', $from, $to, 25), 'country')
                . '</div>';
        }
        if ($tab === 'devices') {
            return '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:var(--arzo-space-4,16px);">'
                . $this->analytics_breakdown('Device types', 'grid', $engine->breakdown('device', $from, $to, 10))
                . $this->analytics_breakdown('Browsers', 'grid', $engine->breakdown('browser', $from, $to, 15))
                . $this->analytics_breakdown('Operating systems', 'grid', $engine->breakdown('os', $from, $to, 15))
                . '</div>';
        }
        if ($tab === 'behaviour') {
            return '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:var(--arzo-space-4,16px);">'
                . $this->analytics_breakdown('Landing pages', 'download', $engine->landing($from, $to, 15), 'path')
                . $this->analytics_breakdown('Exit pages', 'upload', $engine->exiting($from, $to, 15), 'path')
                . $this->analytics_breakdown('404 not found', 'warning', $engine->not_found($from, $to, 15), 'path')
                . $this->analytics_breakdown('Search terms', 'search', $engine->searches($from, $to, 15))
                . '</div>';
        }
        if ($tab === 'google') {
            return $this->analytics_google();
        }
        // Add-on (Pro) tabs render their body here (Campaigns, Real-time, Events…).
        if ($tab !== 'overview') {
            $html = apply_filters('wp_arzo_analytics_render', '', $tab, $from, $to);
            if (is_string($html) && $html !== '') {
                return $html;
            }
        }
        return $this->analytics_overview($from, $to);
    }

    /** Google tab: manage the (free) GA4 / GTM / Google Ads tag insertion in one place. */
    private function analytics_google()
    {
        $items = array(
            'google_analytics_4' => array('icon' => 'chart', 'id_key' => 'measurement_id', 'id_label' => 'Measurement ID', 'blurb' => 'Send pageviews & events to Google Analytics 4 (gtag.js).'),
            'google_tag_manager' => array('icon' => 'code', 'id_key' => 'container_id', 'id_label' => 'Container ID', 'blurb' => 'Load a GTM container to manage all your marketing tags from Google.'),
            'google_ads'         => array('icon' => 'bolt', 'id_key' => 'conversion_id', 'id_label' => 'Conversion ID', 'blurb' => 'Google Ads remarketing & conversion tracking tag.'),
        );
        $registry = $this->registry();

        ob_start();
        ?>
        <div class="wpa-card" style="margin-bottom:var(--arzo-space-4,16px);display:flex;gap:var(--arzo-space-3,12px);align-items:flex-start;">
            <?php echo wp_arzo_icon('info', array('class' => 'wpa-icon', 'style' => 'flex:0 0 auto;color:var(--arzo-accent);')); ?>
            <p style="margin:0;color:var(--arzo-text-muted);">Insert Google’s tags without another plugin. These forward data to Google and work alongside — or instead of — the built-in cookieless engine. <em>GA4 report data inside this dashboard, Consent Mode, and server-side GTM arrive with WP Arzo Pro.</em></p>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:var(--arzo-space-4,16px);">
            <?php foreach ($items as $id => $meta) :
                $feature = $registry->get($id);
                if (!$feature) {
                    continue;
                }
                $enabled  = $registry->is_enabled($id);
                $value    = trim((string) $feature->get_setting($meta['id_key'], ''));
                $configured = $value !== '';
                $config_url = add_query_arg(array('page' => self::PAGE, 'view' => 'settings', 'feature' => $id), admin_url('admin.php'));
                ?>
                <div class="wpa-card">
                    <div style="display:flex;align-items:center;gap:var(--arzo-space-3,12px);margin-bottom:var(--arzo-space-3,12px);">
                        <span class="wpa-badge wpa-badge--info" style="width:2.25rem;height:2.25rem;padding:0;justify-content:center;border-radius:var(--arzo-radius,10px);flex:0 0 auto;"><?php echo wp_arzo_icon($meta['icon'], array('class' => 'wpa-icon')); ?></span>
                        <div>
                            <strong style="display:block;"><?php echo esc_html($feature->title()); ?></strong>
                            <span class="wpa-badge wpa-badge--<?php echo $enabled ? 'success' : 'neutral'; ?>"><?php echo $enabled ? 'Enabled' : 'Disabled'; ?></span>
                            <?php if ($enabled) : ?>
                                <span class="wpa-badge wpa-badge--<?php echo $configured ? 'success' : 'warning'; ?>"><?php echo $configured ? esc_html($meta['id_label'] . ' set') : 'Not configured'; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <p style="color:var(--arzo-text-muted);margin:0 0 var(--arzo-space-3,12px);"><?php echo esc_html($meta['blurb']); ?></p>
                    <?php if ($enabled && $configured) : ?>
                        <p style="margin:0 0 var(--arzo-space-3,12px);"><code><?php echo esc_html($value); ?></code></p>
                    <?php endif; ?>
                    <a class="wpa-btn wpa-btn--secondary wpa-btn--sm" href="<?php echo esc_url($config_url); ?>"><?php echo wp_arzo_icon('settings', array('class' => 'wpa-icon wpa-icon--sm')); ?> <?php echo $enabled ? 'Configure' : 'Enable & configure'; ?></a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /** Overview tab: KPIs + chart + top pages/referrers. */
    private function analytics_overview($from, $to)
    {
        $engine = WP_Arzo_Analytics::instance();
        $o      = $engine->overview($from, $to);
        $pages  = $engine->pages($from, $to, 10);
        $refs   = $engine->referrers($from, $to, 10);

        ob_start();

        if ((int) $o['views'] === 0) {
            ?>
            <div class="wpa-card" style="text-align:center;padding:var(--arzo-space-6,32px);">
                <?php echo wp_arzo_icon('chart', array('class' => 'wpa-icon', 'style' => 'width:2.5rem;height:2.5rem;color:var(--arzo-text-muted);')); ?>
                <h2 style="margin:.5rem 0;">No visits recorded in this range yet</h2>
                <p style="color:var(--arzo-text-muted);max-width:44ch;margin:0 auto;">Analytics records new visits as they happen — cookieless, in your own database. Logged-in admins aren’t counted by default (change that in the feature’s settings). Open your site in a private window to generate a test hit.</p>
            </div>
            <?php
            return ob_get_clean();
        }

        $tiles = array(
            array('Pageviews',       number_format_i18n($o['views']),        'chart'),
            array('Unique visitors', number_format_i18n($o['visitors']),     'users'),
            array('Sessions',        number_format_i18n($o['sessions']),     'refresh'),
            array('Bounce rate',     $o['bounce'] . '%',                     'exchange'),
            array('Avg. visit',      $this->fmt_duration($o['avg_dur']),     'clock'),
            array('Views / session', $o['per_sess'],                         'list'),
        );
        ?>
        <div class="wpa-card" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:var(--arzo-space-4,16px);margin-bottom:var(--arzo-space-4,16px);">
            <?php foreach ($tiles as $t) : ?>
                <div style="display:flex;align-items:center;gap:var(--arzo-space-3,12px);">
                    <span class="wpa-badge wpa-badge--info" style="width:2.25rem;height:2.25rem;padding:0;justify-content:center;border-radius:var(--arzo-radius,10px);flex:0 0 auto;">
                        <?php echo wp_arzo_icon($t[2], array('class' => 'wpa-icon')); ?>
                    </span>
                    <span style="display:flex;flex-direction:column;line-height:1.2;">
                        <strong style="font-size:1.4rem;"><?php echo esc_html($t[1]); ?></strong>
                        <span style="color:var(--arzo-text-muted);font-size:.8rem;"><?php echo esc_html($t[0]); ?></span>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php echo $this->analytics_chart($o['series']); ?>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:var(--arzo-space-4,16px);">
            <div class="wpa-card" style="padding:0;overflow:hidden;">
                <div style="padding:var(--arzo-space-3,12px) var(--arzo-space-4,16px);font-weight:600;"><?php echo wp_arzo_icon('file', array('class' => 'wpa-icon wpa-icon--sm')); ?> Top pages</div>
                <table class="wpa-backup-table">
                    <thead><tr><th>Page</th><th style="text-align:right;">Views</th><th style="text-align:right;">Visitors</th></tr></thead>
                    <tbody>
                        <?php if (empty($pages)) : ?>
                            <tr class="wpa-backup-empty"><td colspan="3">No pages yet.</td></tr>
                        <?php else : foreach ($pages as $p) : ?>
                            <tr>
                                <td><a href="<?php echo esc_url(home_url($p['path'])); ?>" target="_blank" rel="noopener" title="<?php echo esc_attr($p['title'] !== '' ? $p['title'] : $p['path']); ?>"><?php echo esc_html($p['path']); ?></a></td>
                                <td style="text-align:right;"><?php echo esc_html(number_format_i18n($p['views'])); ?></td>
                                <td style="text-align:right;color:var(--arzo-text-muted);"><?php echo esc_html(number_format_i18n($p['visitors'])); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="wpa-card" style="padding:0;overflow:hidden;">
                <div style="padding:var(--arzo-space-3,12px) var(--arzo-space-4,16px);font-weight:600;"><?php echo wp_arzo_icon('external', array('class' => 'wpa-icon wpa-icon--sm')); ?> Top referrers</div>
                <table class="wpa-backup-table">
                    <thead><tr><th>Source</th><th style="text-align:right;">Views</th><th style="text-align:right;">Visitors</th></tr></thead>
                    <tbody>
                        <?php if (empty($refs)) : ?>
                            <tr class="wpa-backup-empty"><td colspan="3">No external referrers yet — traffic so far is direct.</td></tr>
                        <?php else : foreach ($refs as $r) : ?>
                            <tr>
                                <td><?php echo esc_html($r['ref_host']); ?></td>
                                <td style="text-align:right;"><?php echo esc_html(number_format_i18n($r['views'])); ?></td>
                                <td style="text-align:right;color:var(--arzo-text-muted);"><?php echo esc_html(number_format_i18n($r['visitors'])); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_analytics()
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        $ranges = array('today' => 'Today', '7d' => '7 days', '30d' => '30 days', '90d' => '90 days');
        $range  = isset($_GET['range']) ? sanitize_key(wp_unslash($_GET['range'])) : '30d';
        if (!isset($ranges[$range])) {
            $range = '30d';
        }
        $tabs = $this->analytics_tabs();
        $tab  = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : 'overview';
        if (!isset($tabs[$tab])) {
            $tab = 'overview';
        }
        list($from, $to) = $this->analytics_range($range);
        $nonce = wp_create_nonce(self::NONCE_ANALYTICS);
        $base  = admin_url('admin.php?page=' . self::PAGE_ANALYTICS);
        $export_base = add_query_arg(array('action' => 'wp_arzo_analytics_export', '_wpnonce' => $nonce), admin_url('admin-post.php'));

        echo '<div class="wrap wpa-admin">';
        ?>
            <div class="wpa-admin__bar">
                <div>
                    <h1 class="wpa-admin__title"><?php echo wp_arzo_icon('chart', array('class' => 'wpa-icon')); ?> Analytics</h1>
                    <p class="wpa-admin__subtitle">Cookieless, first-party traffic — recorded in your own database, no external services.</p>
                </div>
                <div style="display:flex;gap:var(--arzo-space-3,12px);align-items:center;flex-wrap:wrap;">
                    <nav class="wpa-tabs" role="tablist" aria-label="Date range" id="wpa-an-ranges">
                        <?php foreach ($ranges as $key => $label) : ?>
                            <a class="wpa-tab<?php echo $key === $range ? ' is-active' : ''; ?>" role="tab" aria-selected="<?php echo $key === $range ? 'true' : 'false'; ?>" href="<?php echo esc_url(add_query_arg(array('view' => $tab, 'range' => $key), $base)); ?>" data-range="<?php echo esc_attr($key); ?>"><span><?php echo esc_html($label); ?></span></a>
                        <?php endforeach; ?>
                    </nav>
                    <a class="wpa-btn wpa-btn--ghost wpa-btn--sm" id="wpa-an-export" href="<?php echo esc_url(add_query_arg(array('view' => $tab, 'range' => $range), $export_base)); ?>" data-base="<?php echo esc_url($export_base); ?>"><?php echo wp_arzo_icon('download', array('class' => 'wpa-icon wpa-icon--sm')); ?> Export CSV</a>
                </div>
            </div>

            <nav class="wpa-tabs" role="tablist" aria-label="Reports" id="wpa-an-views" data-nonce="<?php echo esc_attr($nonce); ?>" style="margin-bottom:var(--arzo-space-4,16px);">
                <?php foreach ($tabs as $key => $t) : ?>
                    <a class="wpa-tab<?php echo $key === $tab ? ' is-active' : ''; ?>" role="tab" aria-selected="<?php echo $key === $tab ? 'true' : 'false'; ?>" href="<?php echo esc_url(add_query_arg(array('view' => $key, 'range' => $range), $base)); ?>" data-view="<?php echo esc_attr($key); ?>"><?php echo wp_arzo_icon($t['icon'], array('class' => 'wpa-icon wpa-icon--sm')); ?><span><?php echo esc_html($t['label']); ?></span></a>
                <?php endforeach; ?>
            </nav>

            <div id="wpa-an-body"><?php echo $this->analytics_body($from, $to, $tab); ?></div>

            <script>
            (function () {
                var ranges = document.getElementById('wpa-an-ranges'),
                    views = document.getElementById('wpa-an-views'),
                    body = document.getElementById('wpa-an-body'),
                    exportLink = document.getElementById('wpa-an-export');
                if (!views || !body) { return; }
                var nonce = views.dataset.nonce,
                    state = { range: <?php echo wp_json_encode($range); ?>, view: <?php echo wp_json_encode($tab); ?> },
                    baseUrl = <?php echo wp_json_encode($base); ?>;
                function activate(nav, attr, val) {
                    nav.querySelectorAll('.wpa-tab').forEach(function (t) {
                        var on = t.dataset[attr] === val;
                        t.classList.toggle('is-active', on);
                        t.setAttribute('aria-selected', on ? 'true' : 'false');
                    });
                }
                var refreshTimer = null;
                function setupRefresh() {
                    if (refreshTimer) { clearInterval(refreshTimer); refreshTimer = null; }
                    // A tab body can opt into live polling with [data-wpa-auto-refresh="<seconds>"].
                    var el = body.querySelector('[data-wpa-auto-refresh]');
                    if (el) {
                        var secs = parseInt(el.getAttribute('data-wpa-auto-refresh'), 10) || 15;
                        refreshTimer = setInterval(function () { load(true); }, Math.max(5, secs) * 1000);
                    }
                }
                function load(silent) {
                    if (!silent) { body.style.opacity = '.5'; }
                    var fd = new FormData();
                    fd.append('action', 'wp_arzo_analytics_query');
                    fd.append('nonce', nonce);
                    fd.append('range', state.range);
                    fd.append('view', state.view);
                    fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
                        .then(function (r) { return r.json(); })
                        .then(function (res) {
                            body.style.opacity = '';
                            if (res && res.success) { body.innerHTML = res.data.html; setupRefresh(); }
                        })
                        .catch(function () { body.style.opacity = ''; });
                    if (exportLink) {
                        exportLink.href = exportLink.dataset.base + '&view=' + encodeURIComponent(state.view) + '&range=' + encodeURIComponent(state.range);
                    }
                    if (!silent && window.history && history.replaceState) {
                        history.replaceState(null, '', baseUrl + '&view=' + state.view + '&range=' + state.range);
                    }
                }
                function bind(nav, attr, key) {
                    if (!nav) { return; }
                    nav.addEventListener('click', function (e) {
                        var tab = e.target.closest ? e.target.closest('.wpa-tab') : null;
                        if (!tab) { return; }
                        e.preventDefault();
                        state[key] = tab.dataset[attr];
                        activate(nav, attr, state[key]);
                        load();
                    });
                }
                bind(ranges, 'range', 'range');
                bind(views, 'view', 'view');
                setupRefresh(); // first paint may be a live tab (deep-linked)
            })();
            </script>
        <?php
        echo '</div>';
    }

    public function ajax_analytics_query()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE_ANALYTICS, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        $range = isset($_POST['range']) ? sanitize_key(wp_unslash($_POST['range'])) : '30d';
        $tab   = isset($_POST['view']) ? sanitize_key(wp_unslash($_POST['view'])) : 'overview';
        if (!isset($this->analytics_tabs()[$tab])) {
            $tab = 'overview';
        }
        list($from, $to) = $this->analytics_range($range);
        wp_send_json_success(array('html' => $this->analytics_body($from, $to, $tab)));
    }

    /** Stream the current report tab as CSV. */
    public function handle_analytics_export()
    {
        if (!current_user_can('manage_options') || !isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), self::NONCE_ANALYTICS)) {
            wp_die('Security check failed', '', array('response' => 403));
        }
        if (!class_exists('WP_Arzo_Analytics')) {
            wp_die('Analytics engine unavailable.');
        }
        $engine = WP_Arzo_Analytics::instance();
        $range  = isset($_GET['range']) ? sanitize_key(wp_unslash($_GET['range'])) : '30d';
        $tab    = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : 'overview';
        list($from, $to) = $this->analytics_range($range);

        // [report-label, rows] sets per tab.
        $sets = array();
        if ($tab === 'geo') {
            $sets['Country'] = $engine->breakdown('country', $from, $to, 500);
        } elseif ($tab === 'devices') {
            $sets['Device']  = $engine->breakdown('device', $from, $to, 100);
            $sets['Browser'] = $engine->breakdown('browser', $from, $to, 100);
            $sets['OS']      = $engine->breakdown('os', $from, $to, 100);
        } elseif ($tab === 'behaviour') {
            $sets['Landing']    = $engine->landing($from, $to, 500);
            $sets['Exit']       = $engine->exiting($from, $to, 500);
            $sets['404']        = $engine->not_found($from, $to, 500);
            $sets['Search']     = $engine->searches($from, $to, 500);
        } else {
            $sets['Page']     = array_map(function ($r) {
                return array('label' => $r['path'], 'views' => $r['views'], 'visitors' => $r['visitors']);
            }, $engine->pages($from, $to, 500));
            $sets['Referrer'] = array_map(function ($r) {
                return array('label' => $r['ref_host'], 'views' => $r['views'], 'visitors' => $r['visitors']);
            }, $engine->referrers($from, $to, 500));
        }

        $out = $this->stream_csv('wp-arzo-analytics-' . $tab . '-' . gmdate('Ymd-His') . '.csv');
        fputcsv($out, array('Report', 'Name', 'Views', 'Visitors'));
        foreach ($sets as $report => $rows) {
            foreach ((array) $rows as $r) {
                fputcsv($out, array($report, $r['label'], (int) $r['views'], (int) $r['visitors']));
            }
        }
        fclose($out);
        exit;
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
        $manager  = WP_Arzo_Snippets::instance();
        $saved    = $this->maybe_save_snippet();
        $snippets = $manager->get_all();
        $enabled  = $this->registry()->is_enabled('code_snippets');

        $id = isset($_GET['id']) ? sanitize_text_field(wp_unslash($_GET['id'])) : '';
        $current = $id !== '' ? $manager->get($id) : null;
        if (!$current) {
            $current = array('id' => '', 'title' => '', 'description' => '', 'type' => 'php', 'scope' => 'everywhere', 'priority' => 10, 'code' => '', 'active' => 0);
        }

        $types  = array('php' => 'PHP', 'css' => 'CSS', 'js' => 'JavaScript', 'html' => 'HTML');
        $scopes = array('everywhere' => 'Everywhere', 'admin' => 'Admin only', 'front' => 'Front-end only');
        $base   = admin_url('admin.php?page=' . self::PAGE_SNIPPETS);

        echo '<div class="wrap wpa-admin">';
        $this->render_shell_open('snippets');
        ?>
        <div class="wpa-admin__bar">
            <div>
                <h1 class="wpa-admin__title"><?php echo wp_arzo_icon('code', array('class' => 'wpa-icon')); ?> Code Snippets</h1>
                <p class="wpa-admin__subtitle"><strong><?php echo count($snippets); ?></strong> snippet(s)<?php echo $enabled ? '' : ' · the “Code Snippets” feature is OFF, so nothing runs (enable it on the dashboard)'; ?></p>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <button type="button" class="wpa-btn wpa-btn--ghost" id="wpa-snip-io-open" aria-haspopup="dialog"><?php echo wp_arzo_icon('exchange', array('class' => 'wpa-icon wpa-icon--sm')); ?> Import / Export</button>
                <a class="wpa-btn wpa-btn--primary" href="<?php echo esc_url($base); ?>"><?php echo wp_arzo_icon('plus', array('class' => 'wpa-icon wpa-icon--sm')); ?> New snippet</a>
            </div>
        </div>

        <?php // Import / Export — consolidated into one modal so the header stays clean. ?>
        <div class="wpa-modal" id="wpa-snip-io" hidden>
            <div class="wpa-modal__backdrop" data-snip-close></div>
            <div class="wpa-modal__panel" role="dialog" aria-modal="true" aria-labelledby="wpa-snip-io-title" style="max-width:520px;">
                <div class="wpa-modal__head">
                    <h2 id="wpa-snip-io-title"><?php echo wp_arzo_icon('code', array('class' => 'wpa-icon wpa-icon--sm')); ?> Import / Export snippets</h2>
                    <button type="button" class="wpa-btn wpa-btn--ghost wpa-btn--icon" data-snip-close aria-label="Close"><?php echo wp_arzo_icon('x', array('class' => 'wpa-icon wpa-icon--sm')); ?></button>
                </div>
                <div style="padding:var(--arzo-space-5);display:grid;gap:var(--arzo-space-5);">
                    <div>
                        <h3 style="margin:0 0 6px;font-size:var(--arzo-fs-md);color:var(--arzo-text-strong);">Export</h3>
                        <p style="margin:0 0 12px;font-size:var(--arzo-fs-sm);color:var(--arzo-text-secondary);">Download every snippet as a portable JSON file you can import elsewhere.</p>
                        <?php if (!empty($snippets)) : ?>
                            <a class="wpa-btn wpa-btn--secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wp_arzo_snippets_export'), self::NONCE_SNIPPETS)); ?>"><?php echo wp_arzo_icon('download', array('class' => 'wpa-icon wpa-icon--sm')); ?> Export <?php echo (int) count($snippets); ?> snippet(s)</a>
                        <?php else : ?>
                            <p style="margin:0;font-size:var(--arzo-fs-sm);color:var(--arzo-text-muted);">No snippets to export yet.</p>
                        <?php endif; ?>
                    </div>
                    <div style="border-top:1px solid var(--arzo-border);padding-top:var(--arzo-space-5);">
                        <h3 style="margin:0 0 6px;font-size:var(--arzo-fs-md);color:var(--arzo-text-strong);">Import</h3>
                        <p style="margin:0 0 12px;font-size:var(--arzo-fs-sm);color:var(--arzo-text-secondary);">Upload a WP Arzo snippets JSON. Imported snippets are added <strong>disabled</strong> — review, then enable them.</p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" style="display:grid;gap:12px;">
                            <input type="hidden" name="action" value="wp_arzo_snippets_import">
                            <?php wp_nonce_field('wp_arzo_snippets_import'); ?>
                            <input type="file" name="snippets_file" accept="application/json,.json" required class="wpa-input" style="padding:8px;">
                            <div><button type="submit" class="wpa-btn wpa-btn--primary"><?php echo wp_arzo_icon('upload', array('class' => 'wpa-icon wpa-icon--sm')); ?> Import snippets</button></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <script>
            (function () {
                var openBtn = document.getElementById('wpa-snip-io-open'),
                    modal = document.getElementById('wpa-snip-io');
                if (!openBtn || !modal) { return; }
                function show() { modal.hidden = false; }
                function hide() { modal.hidden = true; }
                openBtn.addEventListener('click', show);
                modal.addEventListener('click', function (e) { if (e.target.closest('[data-snip-close]')) { hide(); } });
                document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) { hide(); } });
            })();
        </script>
        <?php if ($saved) : ?>
            <div class="wpa-toast wpa-toast--success" style="position:static;margin-bottom:16px;display:inline-flex;"><?php echo wp_arzo_icon('check', array('class' => 'wpa-icon wpa-icon--sm')); ?> Snippet saved.</div>
        <?php endif; ?>
        <?php
        if (isset($_GET['import'])) {
            $imp = sanitize_key(wp_unslash($_GET['import']));
            if ($imp === 'ok') {
                $n = isset($_GET['n']) ? (int) $_GET['n'] : 0;
                echo '<div class="wpa-toast wpa-toast--success" style="position:static;margin-bottom:16px;display:inline-flex;">' . wp_arzo_icon('check', array('class' => 'wpa-icon wpa-icon--sm')) . ' Imported ' . $n . ' snippet(s) — added disabled; review and enable them.</div>';
            } else {
                echo '<div class="wpa-toast wpa-toast--error" style="position:static;margin-bottom:16px;display:inline-flex;">' . wp_arzo_icon('x', array('class' => 'wpa-icon wpa-icon--sm')) . ' ' . ($imp === 'nofile' ? 'No file selected.' : 'That file isn’t a valid WP Arzo snippets export.') . '</div>';
            }
        }
        ?>

        <div class="wpa-code-app">
            <!-- Editor -->
            <form method="post" class="wpa-code-app__editor" id="wpa-snippet-form">
                <?php wp_nonce_field(self::NONCE_SNIPPETS, 'wp_arzo_snippet_nonce'); ?>
                <input type="hidden" name="snippet_id" value="<?php echo esc_attr($current['id']); ?>">

                <div class="wpa-code-app__toolbar">
                    <input class="wpa-input wpa-code-app__title" type="text" name="snippet_title" placeholder="Snippet title…" value="<?php echo esc_attr($current['title']); ?>" required>
                    <label class="wpa-toggle">
                        <input type="checkbox" class="wpa-toggle__input" role="switch" name="snippet_active" value="1" <?php checked(!empty($current['active'])); ?>>
                        <span class="wpa-toggle__track"><span class="wpa-toggle__thumb"></span></span>
                        <span class="wpa-toggle__label">Active</span>
                    </label>
                    <button type="submit" name="wp_arzo_save_snippet" class="wpa-btn wpa-btn--primary"><?php echo wp_arzo_icon('check', array('class' => 'wpa-icon wpa-icon--sm')); ?> Save</button>
                </div>

                <div class="wpa-code-app__meta">
                    <div class="wpa-field">
                        <label class="wpa-field__label" for="snp-type">Type</label>
                        <select class="wpa-input" id="snp-type" name="snippet_type" data-wpa-select>
                            <?php foreach ($types as $v => $l) {
                                echo '<option value="' . esc_attr($v) . '"' . selected($current['type'], $v, false) . '>' . esc_html($l) . '</option>';
                            } ?>
                        </select>
                    </div>
                    <div class="wpa-field">
                        <label class="wpa-field__label" for="snp-scope">Run on</label>
                        <select class="wpa-input" id="snp-scope" name="snippet_scope" data-wpa-select>
                            <?php foreach ($scopes as $v => $l) {
                                echo '<option value="' . esc_attr($v) . '"' . selected($current['scope'], $v, false) . '>' . esc_html($l) . '</option>';
                            } ?>
                        </select>
                    </div>
                    <div class="wpa-field">
                        <label class="wpa-field__label" for="snp-priority">Priority</label>
                        <input class="wpa-input" type="number" id="snp-priority" name="snippet_priority" min="1" max="9999" value="<?php echo esc_attr(isset($current['priority']) ? (int) $current['priority'] : 10); ?>">
                    </div>
                    <div class="wpa-field wpa-code-app__desc">
                        <label class="wpa-field__label" for="snp-desc">Description</label>
                        <input class="wpa-input" type="text" id="snp-desc" name="snippet_description" value="<?php echo esc_attr(isset($current['description']) ? $current['description'] : ''); ?>" placeholder="Optional — what this snippet does">
                    </div>
                </div>

                <?php if (!empty($current['last_error'])) : ?>
                    <div class="wpa-toast wpa-toast--error" style="position:static;margin:0 0 10px;display:inline-flex;"><?php echo wp_arzo_icon('alert', array('class' => 'wpa-icon wpa-icon--sm')); ?> Auto-disabled after an error: <?php echo esc_html($current['last_error']); ?></div>
                <?php endif; ?>

                <div class="wpa-code-app__code" data-snippet-type="<?php echo esc_attr($current['type']); ?>">
                    <textarea id="snp-code" name="snippet_code" spellcheck="false"><?php echo esc_textarea($current['code']); ?></textarea>
                </div>
                <p class="wpa-field__help">PHP snippets run as code (the opening &lt;?php tag is optional). A PHP snippet that fatals is auto-disabled so it can’t break your site.</p>

                <?php
                $cond_schema  = WP_Arzo_Snippets::condition_schema();
                $cond_current = (isset($current['conditions']) && is_array($current['conditions'])) ? array_values($current['conditions']) : array();
                $cond_mode    = (isset($current['cond_mode']) && $current['cond_mode'] === 'any') ? 'any' : 'all';
                ?>
                <div class="wpa-cond" id="wpa-cond">
                    <div class="wpa-cond__head">
                        <div class="wpa-cond__title"><?php echo wp_arzo_icon('sliders', array('class' => 'wpa-icon wpa-icon--sm')); ?> Smart Conditional Logic</div>
                        <div class="wpa-cond__mode" role="radiogroup" aria-label="Match mode">
                            <span>Run when</span>
                            <span class="wpa-seg">
                                <input type="radio" name="snippet_cond_mode" id="wpa-cm-all" value="all" <?php checked($cond_mode, 'all'); ?>>
                                <label for="wpa-cm-all">all</label>
                                <input type="radio" name="snippet_cond_mode" id="wpa-cm-any" value="any" <?php checked($cond_mode, 'any'); ?>>
                                <label for="wpa-cm-any">any</label>
                            </span>
                            <span>of the rules match</span>
                        </div>
                    </div>
                    <div class="wpa-cond__rows" id="wpa-cond-rows"></div>
                    <button type="button" class="wpa-btn wpa-btn--ghost wpa-btn--sm" id="wpa-cond-add"><?php echo wp_arzo_icon('plus', array('class' => 'wpa-icon wpa-icon--sm')); ?> Add rule</button>
                    <p class="wpa-field__help" id="wpa-cond-empty" style="margin-top:8px;">No rules — this snippet runs everywhere (within its “Run on” scope).</p>
                    <input type="hidden" name="snippet_conditions" id="wpa-cond-json" value="">
                </div>
                <script>
                    (function () {
                        var SCHEMA = <?php echo wp_json_encode($cond_schema); ?>;
                        var INITIAL = <?php echo wp_json_encode($cond_current); ?>;
                        var RM = <?php echo wp_json_encode(wp_arzo_icon('trash', array('class' => 'wpa-icon wpa-icon--sm'))); ?>;
                        var TYPES = Object.keys(SCHEMA);
                        var rowsEl = document.getElementById('wpa-cond-rows'),
                            emptyEl = document.getElementById('wpa-cond-empty'),
                            addBtn = document.getElementById('wpa-cond-add'),
                            form = document.getElementById('wpa-snippet-form'),
                            jsonEl = document.getElementById('wpa-cond-json');
                        if (!rowsEl || !form) { return; }

                        function el(t, c) { var e = document.createElement(t); if (c) { e.className = c; } return e; }
                        function fill(sel, map, cur) {
                            sel.innerHTML = '';
                            Object.keys(map).forEach(function (k) {
                                var o = el('option'); o.value = k; o.textContent = map[k];
                                if (k === cur) { o.selected = true; }
                                sel.appendChild(o);
                            });
                        }
                        function opsFor(row) {
                            var type = row.querySelector('.wpa-cond__type').value;
                            fill(row.querySelector('.wpa-cond__op'), SCHEMA[type].ops, row._pre ? row._pre.op : '');
                        }
                        function toggleBetween(row) {
                            if (row.querySelector('.wpa-cond__type').value !== 'schedule') { return; }
                            var between = row.querySelector('.wpa-cond__op').value === 'between';
                            var sep = row.querySelector('.wpa-cond__sep'), d2 = row.querySelector('.wpa-cond__dt2');
                            if (sep) { sep.style.display = between ? '' : 'none'; }
                            if (d2) { d2.style.display = between ? '' : 'none'; }
                        }
                        function valueCell(row) {
                            var type = row.querySelector('.wpa-cond__type').value, spec = SCHEMA[type], pre = row._pre || {};
                            var cell = row.querySelector('.wpa-cond__val'); cell.innerHTML = '';
                            if (spec.values === null) {
                                var inp = el('input', 'wpa-input'); inp.type = 'text'; inp.placeholder = '/path or pattern'; inp.value = pre.value || ''; cell.appendChild(inp);
                            } else if (spec.values === 'datetime') {
                                var d1 = el('input', 'wpa-input wpa-cond__dt'); d1.type = 'datetime-local'; d1.value = pre.value || ''; cell.appendChild(d1);
                                var sep = el('span', 'wpa-cond__sep'); sep.textContent = 'and'; cell.appendChild(sep);
                                var d2 = el('input', 'wpa-input wpa-cond__dt2'); d2.type = 'datetime-local'; d2.value = pre.value2 || ''; cell.appendChild(d2);
                                toggleBetween(row);
                            } else {
                                var sel = el('select', 'wpa-input'); fill(sel, spec.values, pre.value); cell.appendChild(sel);
                            }
                        }
                        function makeRow(cond) {
                            cond = cond || {};
                            var type = SCHEMA[cond.type] ? cond.type : TYPES[0];
                            var row = el('div', 'wpa-cond__row'); row._pre = cond;
                            row.appendChild(el('span', 'wpa-cond__conn'));
                            var t = el('select', 'wpa-input wpa-cond__type'); var tmap = {}; TYPES.forEach(function (k) { tmap[k] = SCHEMA[k].label; }); fill(t, tmap, type); row.appendChild(t);
                            row.appendChild(el('select', 'wpa-input wpa-cond__op'));
                            row.appendChild(el('span', 'wpa-cond__val'));
                            var rm = el('button', 'wpa-btn wpa-btn--danger-soft wpa-btn--icon wpa-btn--sm'); rm.type = 'button'; rm.setAttribute('aria-label', 'Remove rule'); rm.setAttribute('title', 'Remove rule'); rm.innerHTML = RM; row.appendChild(rm);
                            opsFor(row); valueCell(row);
                            t.addEventListener('change', function () { row._pre = {}; opsFor(row); valueCell(row); });
                            row.querySelector('.wpa-cond__op').addEventListener('change', function () { toggleBetween(row); });
                            rm.addEventListener('click', function () { row.remove(); refresh(); });
                            return row;
                        }
                        function modeWord() { var a = document.getElementById('wpa-cm-any'); return (a && a.checked) ? 'or' : 'and'; }
                        function updateConnectives() {
                            var word = modeWord();
                            Array.prototype.forEach.call(rowsEl.children, function (row, i) {
                                var c = row.querySelector('.wpa-cond__conn');
                                if (c) { c.textContent = (i === 0) ? 'If' : word; }
                            });
                        }
                        function refresh() { emptyEl.style.display = rowsEl.children.length ? 'none' : ''; updateConnectives(); }
                        function addRow(cond) { rowsEl.appendChild(makeRow(cond)); refresh(); }

                        (INITIAL || []).forEach(addRow);
                        refresh();
                        addBtn.addEventListener('click', function () { addRow(); });
                        Array.prototype.forEach.call(document.querySelectorAll('input[name="snippet_cond_mode"]'), function (r) {
                            r.addEventListener('change', updateConnectives);
                        });

                        form.addEventListener('submit', function () {
                            var out = [];
                            Array.prototype.forEach.call(rowsEl.children, function (row) {
                                var type = row.querySelector('.wpa-cond__type').value,
                                    op = row.querySelector('.wpa-cond__op').value,
                                    spec = SCHEMA[type], c = { type: type, op: op };
                                if (spec.values === 'datetime') {
                                    c.value = (row.querySelector('.wpa-cond__dt') || {}).value || '';
                                    if (op === 'between') { c.value2 = (row.querySelector('.wpa-cond__dt2') || {}).value || ''; }
                                } else {
                                    var vc = row.querySelector('.wpa-cond__val select, .wpa-cond__val input');
                                    c.value = vc ? vc.value : '';
                                }
                                out.push(c);
                            });
                            jsonEl.value = JSON.stringify(out);
                        });
                    })();
                </script>
            </form>

            <!-- Snippet list -->
            <aside class="wpa-code-app__list">
                <div class="wpa-code-app__list-head">
                    <span>Snippets</span>
                    <a class="wpa-btn wpa-btn--ghost wpa-btn--icon" href="<?php echo esc_url($base); ?>" title="New snippet"><?php echo wp_arzo_icon('plus', array('class' => 'wpa-icon wpa-icon--sm')); ?></a>
                </div>
                <div class="wpa-code-app__list-body">
                    <?php if (empty($snippets)) : ?>
                        <p class="wpa-code-app__empty">No snippets yet. Write one and hit Save.</p>
                    <?php else : foreach ($snippets as $s) :
                        $is_current = ($s['id'] === $current['id'] && $current['id'] !== '');
                        ?>
                        <div class="wpa-code-item<?php echo $is_current ? ' is-active' : ''; ?>" data-snippet="<?php echo esc_attr($s['id']); ?>">
                            <a class="wpa-code-item__main" href="<?php echo esc_url(add_query_arg('id', $s['id'], $base)); ?>">
                                <span class="wpa-code-item__type wpa-badge wpa-badge--neutral"><?php echo esc_html(strtoupper($s['type'])); ?></span>
                                <span class="wpa-code-item__title"><?php echo esc_html($s['title'] !== '' ? $s['title'] : '[Untitled]'); ?><?php echo !empty($s['last_error']) ? ' ' . wp_arzo_icon('alert', array('class' => 'wpa-icon wpa-icon--xs', 'style' => 'color:var(--arzo-error)')) : ''; ?></span>
                            </a>
                            <label class="wpa-toggle wpa-toggle--sm" title="Active">
                                <input type="checkbox" class="wpa-toggle__input wpa-snippet-toggle" role="switch" data-id="<?php echo esc_attr($s['id']); ?>" <?php checked(!empty($s['active'])); ?>>
                                <span class="wpa-toggle__track"><span class="wpa-toggle__thumb"></span></span>
                            </label>
                            <button type="button" class="wpa-btn wpa-btn--danger-soft wpa-btn--icon wpa-snippet-delete" data-id="<?php echo esc_attr($s['id']); ?>" aria-label="Delete snippet"><?php echo wp_arzo_icon('trash', array('class' => 'wpa-icon wpa-icon--sm')); ?></button>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </aside>
        </div>
        <?php
        $this->render_shell_close();
        echo '</div>';
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
        // Conditional logic arrives as a JSON string from the builder; the engine
        // validates every rule against its schema in sanitize_conditions().
        $conditions = array();
        if (isset($_POST['snippet_conditions'])) {
            $decoded = json_decode(wp_unslash($_POST['snippet_conditions']), true);
            if (is_array($decoded)) {
                $conditions = $decoded;
            }
        }

        WP_Arzo_Snippets::instance()->save(array(
            'id'          => isset($_POST['snippet_id']) ? sanitize_text_field(wp_unslash($_POST['snippet_id'])) : '',
            'title'       => isset($_POST['snippet_title']) ? wp_unslash($_POST['snippet_title']) : '',
            'description' => isset($_POST['snippet_description']) ? wp_unslash($_POST['snippet_description']) : '',
            'type'      => isset($_POST['snippet_type']) ? sanitize_key($_POST['snippet_type']) : 'php',
            'scope'     => isset($_POST['snippet_scope']) ? sanitize_key($_POST['snippet_scope']) : 'everywhere',
            'priority'  => isset($_POST['snippet_priority']) ? (int) $_POST['snippet_priority'] : 10,
            'code'      => isset($_POST['snippet_code']) ? wp_unslash($_POST['snippet_code']) : '',
            'active'    => !empty($_POST['snippet_active']),
            'cond_mode' => isset($_POST['snippet_cond_mode']) ? sanitize_key($_POST['snippet_cond_mode']) : 'all',
            'conditions' => $conditions,
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
        <?php if (!$this->rendering_tab) : ?><div class="wrap wpa-admin"><?php endif; ?>
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
                                    <button type="button" class="wpa-btn wpa-btn--danger-soft wpa-btn--sm wpa-rest-revoke" data-id="<?php echo esc_attr($k['id']); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
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
        <?php if (!$this->rendering_tab) : ?></div><?php endif; ?>
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
        if (!$this->rendering_tab) {
            echo '<div class="wrap wpa-admin">';
        }
        $this->render_shell_open('roles');
        if ($slug !== '' && get_role($slug)) {
            $this->render_role_editor($slug);
        } else {
            $this->render_roles_list();
        }
        $this->render_shell_close();
        if (!$this->rendering_tab) {
            echo '</div>';
        }
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
        <div class="wpa-card" style="margin-bottom:16px;">
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

        <div class="wpa-card" style="padding:0;overflow:hidden;">
            <table class="wpa-backup-table">
                <thead><tr><th>Role</th><th>Slug</th><th>Users</th><th>Capabilities</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($roles as $r) :
                        $edit = admin_url('admin.php?page=' . self::PAGE_SETTINGS . '&tab=roles&role=' . $r['slug']); ?>
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
                                    <button type="button" class="wpa-btn wpa-btn--danger-soft wpa-btn--icon wpa-role-delete" data-slug="<?php echo esc_attr($r['slug']); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" aria-label="Delete role"><?php echo wp_arzo_icon('trash', array('class' => 'wpa-icon wpa-icon--sm')); ?></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
                <a class="wpa-btn wpa-btn--ghost wpa-btn--sm" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SETTINGS . '&tab=roles')); ?>"><?php echo wp_arzo_icon('chevron-right', array('class' => 'wpa-icon wpa-icon--sm')); ?> All roles</a>
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
        <?php if (!$this->rendering_tab) : ?><div class="wrap wpa-admin"><?php endif; ?>
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
        <?php if (!$this->rendering_tab) : ?></div><?php endif; ?>
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
                'Imported %d feature toggle(s), %d setting group(s), %d snippet(s), %d email connection(s).%s',
                $s['features'],
                $s['settings'],
                $s['snippets'],
                isset($s['connections']) ? $s['connections'] : 0,
                $s['snapshot'] ? ' A safety snapshot was taken.' : ''
            ),
        ));
    }
}
