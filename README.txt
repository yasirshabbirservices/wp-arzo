=== WP Arzo - Maintenance & Administration Suite ===
Contributors: Yasir Shabbir
Tags: maintenance, administration, tools, database, file manager
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.2
Stable tag: 6.6.4
License: Proprietary

Ultimate WordPress Maintenance & Administration Suite.

== Description ==

WP Arzo provides a comprehensive, standalone admin console for managing your WordPress site:

* Site Information - WordPress, PHP, MySQL, server, and disk information
* User Management - Create, delete, and log in as any user
* Database Access - Browse tables and run SQL queries
* File Manager - Browse, edit, upload, and download files (powered by elFinder)
* Plugin Manager - Activate / deactivate and upload plugins
* Theme Manager - Switch and upload themes
* Debug Tools - Toggle WP_DEBUG family settings and view the debug log
* Site Modes - Maintenance / Coming Soon / Payment Required, plus emergency recovery
* Extra Options - Configure PHP limits via wp-config.php / .htaccess / php.ini
* Quick Login - Create a temporary admin or generate a one-time emergency access link

The console opens in its own tab via the WordPress admin menu and authenticates with your
existing WordPress session (administrators only).

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Click 'WP Arzo' in the admin menu to open the tool in a new tab

== Changelog ==

See CHANGELOG.md for the full history.

= 6.6.4 =
* Changed: fluid, variable-driven CSS (clamp/calc + container/card variables) so the
  dashboard scales smoothly across screen sizes

= 6.6.3 =
* Changed: consistent console-style tab navigation + branding across the dashboard,
  Backups, and feature-settings screens

= 6.6.2 =
* Fixed: admin-menu icon rendered oversized (logo overflowing the sidebar); now
  constrained to a proper 20x20 icon

= 6.6.1 =
* Fixed: CSS/JS changes now take effect without bumping the plugin version (robust
  cache-busting via filemtime + filesize, content-hash fallback, and per-request busting
  when WP_DEBUG is on)

= 6.6 =
* New: native WP Arzo dashboard (Feature Manager) with a modern toggle grid + 16 free
  features (disable comments/gutenberg/feeds/embeds/xmlrpc/emojis, restrict REST API,
  block user enumeration, heartbeat & revisions control, robots.txt/ads.txt managers, …)
* New: database Backup engine with one-click restore + automatic snapshot before any
  feature toggle (WP Arzo → Backups)
* New: design-system foundation (unified tokens, reusable components, SVG icon system),
  full-dark branded admin UI, cross-promotion area
* Licensing for the upcoming Pro add-on will use Freemius

= 6.5 =
* Fixed the broken Quick Login "Direct Admin Access" link (404) and bound the one-time
  token to the generating admin
* Fixed PHP 8 fatals/notices in the Database, Site Info, and public Maintenance pages
* Fixed current-user detection in the Users table and theme auto-activation on upload
* Security: confined file downloads to the WP root (path-traversal fix), blocked
  wp-config.php / config-file code injection in Debug and Extra Options, added CSRF
  nonces to user/plugin/theme/database/debug/site-mode actions, hid dot-files in the
  file manager, and allow-listed new-user roles

= 5.1 =
* Converted standalone tool into WordPress plugin
* Added WordPress admin menu integration
* Removed ACCESS_KEY authentication (uses WordPress auth)
* Opens in new tab with clean interface
* Fixed all AJAX operations for plugin context
