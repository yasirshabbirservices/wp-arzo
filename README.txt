=== WP Arzo - Maintenance & Administration Suite ===
Contributors: Yasir Shabbir
Tags: maintenance, administration, tools, database, file manager
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.2
Stable tag: 6.18.0
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

= 6.18.0 =
* New: Media Cleanup tool (WP Arzo -> Media Cleanup) — batched scan with progress, finds
  attachments with no detectable references, filters + reclaimable-space summary, and
  confirmed batch delete (conservative usage detection; back up before deleting)

= 6.17.0 =
* New (free): Auto WebP Conversion — convert uploaded JPEG/PNG to WebP before they enter
  the media library (quality, resize, keep-original, and an optional per-upload confirm)

= 6.16.0 =
* Improved (free): Advanced SMTP — backup connection with auto-failover/retry, failure
  notifications, and a Send-test-email button
* Improved (free): Email Log — resend any email + sent/failed analytics counts

= 6.15.1 =
* Fixed (critical): Custom Login URL fatal on the login page (Undefined constant
  AUTOSAVE_INTERVAL) — wp-login.php is now loaded on wp_loaded, after WP constants exist

= 6.15.0 =
* New (free): Code Snippets Manager (PHP/CSS/JS/HTML) under WP Arzo -> Snippets, with a
  fatal-guard that auto-disables a snippet that errors (39 free features total)

= 6.14.0 =
* New (free): Site Verification (Google/Bing/Pinterest/Yandex/Baidu), Remove jQuery
  Migrate, Disable Front Dashicons (38 free features total)

= 6.13.1 =
* Fixed (critical): dashboard truncated / sidebar + login features missing — an infinite
  recursion in the Custom Login URL settings (present since 6.10.0). Update + clear OPcache.

= 6.13.0 =
* New (free): Crawl Optimizations, Custom Body Class, Disable Application Passwords,
  Clean Up Admin Bar, Enhance List Tables (35 free features total)

= 6.12.0 =
* New (free): Page & Post Duplication, Missed Schedule Fix, SVG Upload, Last Login Column,
  Header/Body/Footer Code, Custom CSS, Disable All Updates, Login/Logout Redirects
  (30 free features total)

= 6.11.0 =
* Added: off-site backup hook (wp_arzo_after_snapshot_created) so Pro can push snapshots
  to FTP/cloud after each local snapshot

= 6.10.0 =
* New (free): Custom Login URL — move wp-login.php to a secret slug
* New (free): Limit Login Attempts — lock out an IP after repeated failed logins
* Changed: dashboard now has a right sidebar (license/activation + promotions)

= 6.9.1 =
* Fixed: dashboard input fields showed a white background (now dark)
* Fixed: Custom Login Page now styles all login screens (login, lost password, reset,
  register), not just the sign-in form

= 6.9.0 =
* New (free): Custom Login Page — brand wp-login.php with your logo, colors and CSS

= 6.8.0 =
* New (free): SMTP Email Delivery (route wp_mail through your SMTP server)
* New (free): Email Log (sent/failed, recipient, subject) under WP Arzo → Email Log
* Local snapshots + custom login remain free; only cloud/remote backups are Pro

= 6.7.0 =
* Added: freemium gate (pro-tier features + Unlock CTA) — integration surface for the
  WP Arzo Pro add-on and Freemius licensing

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
