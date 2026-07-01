<?php

/**
 * Free features: Advanced Tools (standalone console) toggles.
 *
 * Each power-tool in the standalone console (Users, Database, File Manager,
 * Plugins, Themes, Debug, Site Modes, Extra Options, Quick Login) is exposed as
 * a dashboard toggle so it can be individually enabled/disabled — useful for
 * locking down dangerous tools (file/DB access) on production.
 *
 * The toggles live in the normal feature registry (group "advanced_tools"), so
 * they persist in `wp_arzo_features` like every other feature. The console reads
 * that state via wp_arzo_console_tool_enabled() to gate both its nav and its
 * AJAX/file/DB operations. "Site Info" is intentionally NOT toggleable — it is
 * the console's always-available home.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * A toggle-only registry feature for one console tool. It has no front-end
 * behaviour; enabling/disabling it simply gates the matching console tab.
 */
class WP_Arzo_Feature_Console_Tool extends WP_Arzo_Feature
{
    private $meta;

    public function __construct(array $meta)
    {
        $this->meta = $meta;
    }

    public function id()
    {
        return $this->meta['id'];
    }
    public function title()
    {
        return $this->meta['title'];
    }
    public function description()
    {
        return isset($this->meta['description']) ? $this->meta['description'] : '';
    }
    public function group()
    {
        return 'advanced_tools';
    }
    public function icon()
    {
        return isset($this->meta['icon']) ? $this->meta['icon'] : 'tools';
    }
    public function tier()
    {
        return 'free';
    }
    public function default_enabled()
    {
        // Existing tools default ON so behaviour is unchanged until a user opts out.
        return true;
    }
    public function settings_schema()
    {
        return array();
    }
    public function boot()
    {
        // Inert: the console reads enabled-state directly via the helpers below.
    }
}

/**
 * Map a console tab to its toggle feature id. "info" (Site Info) is omitted on
 * purpose — it is always available as the console home.
 *
 * @return array<string,string>
 */
function wp_arzo_console_tool_map()
{
    return array(
        'users'         => 'tool_users',
        'database'      => 'tool_database',
        'files'         => 'tool_files',
        'plugins'       => 'tool_plugins',
        'themes'        => 'tool_themes',
        'debug'         => 'tool_debug',
        'site_modes'    => 'tool_site_modes',
        'maintenance'   => 'tool_site_modes', // legacy alias
        'extra_options' => 'tool_extra_options',
        'login'         => 'tool_login',
    );
}

/**
 * The display catalog used to register the toggles (tab => label/desc/icon).
 *
 * @return array<string,array>
 */
function wp_arzo_console_tool_catalog()
{
    return array(
        'users'         => array('id' => 'tool_users', 'title' => 'Users (Console)', 'icon' => 'users', 'description' => 'Advanced Tools → Users: browse, search, and manage all site users.'),
        'database'      => array('id' => 'tool_database', 'title' => 'Database (Console)', 'icon' => 'database', 'description' => 'Advanced Tools → Database: a full database manager (AdminNeo) — browse, edit, export/import, run SQL. Disable to lock down direct DB access.'),
        'files'         => array('id' => 'tool_files', 'title' => 'File Manager (Console)', 'icon' => 'folder', 'description' => 'Advanced Tools → Files: the elFinder file manager. Disable to block file browsing/editing/downloads.'),
        'plugins'       => array('id' => 'tool_plugins', 'title' => 'Plugins (Console)', 'icon' => 'plugin', 'description' => 'Advanced Tools → Plugins: activate/deactivate plugins from the console.'),
        'themes'        => array('id' => 'tool_themes', 'title' => 'Themes (Console)', 'icon' => 'theme', 'description' => 'Advanced Tools → Themes: switch the active theme from the console.'),
        'debug'         => array('id' => 'tool_debug', 'title' => 'Debug Tools (Console)', 'icon' => 'bug', 'description' => 'Advanced Tools → Debug: toggle WP_DEBUG and view/clear the debug log.'),
        'site_modes'    => array('id' => 'tool_site_modes', 'title' => 'Site Modes (Console)', 'icon' => 'lock', 'description' => 'Advanced Tools → Site Modes: maintenance / coming-soon / payment-required and the emergency script.'),
        'extra_options' => array('id' => 'tool_extra_options', 'title' => 'Extra Options (Console)', 'icon' => 'settings', 'description' => 'Advanced Tools → Extra Options: PHP limits and other server tweaks.'),
        'login'         => array('id' => 'tool_login', 'title' => 'Temporary Logins (Console)', 'icon' => 'key', 'description' => 'Advanced Tools → Quick Login: create passwordless, expiring, revocable login links.'),
    );
}

/**
 * Whether a console tab is currently enabled. Reads `wp_arzo_features` directly
 * (default ON) so it is reliable inside the standalone console regardless of
 * registry boot order. Unknown tabs and "info" are always allowed.
 *
 * @param string $tab
 * @return bool
 */
function wp_arzo_console_tool_enabled($tab)
{
    $map = wp_arzo_console_tool_map();
    if (!isset($map[$tab])) {
        return true; // Site Info + anything not gated.
    }
    $features = get_option('wp_arzo_features', array());
    $id = $map[$tab];
    if (is_array($features) && array_key_exists($id, $features)) {
        return (bool) $features[$id];
    }
    return true; // default ON for existing tools.
}

/**
 * Resolve the console tool that owns a request (tab or AJAX operation), so the
 * router can gate operations issued under the legacy `tab=ajax` alias.
 *
 * @return string tool tab key, or '' when not gated here.
 */
function wp_arzo_console_tool_for_request($tab, $op)
{
    $tab = (string) $tab;
    $op  = (string) $op;

    $direct = wp_arzo_console_tool_map();
    if ($tab !== 'ajax' && isset($direct[$tab])) {
        return ($tab === 'maintenance') ? 'site_modes' : $tab;
    }

    $op_map = array(
        'elfinder_connector'        => 'files',
        'get_db_tables_page'        => 'database',
        'get_plugins_page'          => 'plugins',
        'toggle_plugin'             => 'plugins',
        'get_themes_page'           => 'themes',
        'activate_theme'            => 'themes',
        'get_users_page'            => 'users',
        'clear_debug_log'           => 'debug',
        'log_debug_change'          => 'debug',
        'update_maintenance_option' => 'site_modes',
        'activate_mode'             => 'site_modes',
        'deactivate_mode'           => 'site_modes',
        'tl_create'                 => 'login',
        'tl_delete'                 => 'login',
        'tl_toggle'                 => 'login',
        'tl_invite'                 => 'login',
    );
    return isset($op_map[$op]) ? $op_map[$op] : '';
}

/**
 * Register every console-tool toggle into the registry.
 *
 * @param WP_Arzo_Feature_Registry $registry
 */
function wp_arzo_register_console_tools($registry)
{
    foreach (wp_arzo_console_tool_catalog() as $meta) {
        $registry->register(new WP_Arzo_Feature_Console_Tool($meta));
    }
}
