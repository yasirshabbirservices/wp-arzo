=== WP Arzo - Administration Suite ===
Contributors: yasirshabbir
Tags: maintenance, administration, analytics, smtp, security
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 6.162.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

One suite to run, secure, optimize, and rescue your WordPress site — replace 20+ single-purpose plugins.

== Description ==

**WP Arzo replaces a drawer full of single-purpose plugins with one cohesive, lightweight suite.**
Three reasons sites switch:

* **Replace 20+ plugins.** ~50 site-enhancement features in one dashboard — SMTP with fallback,
  cookieless analytics, backups, security hardening, login protection, media tools, performance
  tweaks, role/capability editing, REST API keys, and dozens more. Enable only what you need; disabled
  features load zero code.
* **Analytics without Site Kit.** A built-in, cookieless, first-party analytics engine — traffic,
  top pages, referrers, geo, devices and behaviour, recorded in your own database. No external
  service, no cookie banner, no MonsterInsights.
* **AI-agent ready.** WP Arzo Pro exposes a governed MCP (Model Context Protocol) server so AI
  assistants can safely read and act on your site — permissioned, audited, and confirm-gated.

Everything is administrator-only and built for modern WordPress: accessible (WCAG 2.2 AA),
token-themed, and modular so your site only ever loads what it uses.

**Advanced Tools console** — a standalone dark console of day-to-day admin tools: Site Info, Users,
Plugins, Themes, Debug, Site Modes, Extra Options, and Temporary Logins.

The console opens in its own tab via the WordPress admin menu and authenticates with your
existing WordPress session (administrators only).

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Click 'WP Arzo' in the admin menu to open the tool in a new tab

== Changelog ==

Full history: https://github.com/yasirshabbirservices/wp-arzo/blob/main/CHANGELOG.md

= 6.162.0 =
* Changed: the Advanced Tools console's **Files** and **Database** tabs no longer describe or
  advertise any file-management or database-query capability. Both tabs are, and always were,
  empty gates in the free plugin — WP Arzo Pro optionally supplies a renderer for them; without
  Pro they simply show a generic "available with Pro" notice. The free plugin ships no
  file-manager or SQL-runner code or library of any kind.

= 6.161.0 =
* Changed: **Code Snippets** and **Header / Body / Footer Code** are now Pro-only. These let an
  administrator run arbitrary PHP / inject arbitrary HTML+JS, so they are not part of the free
  WordPress.org build — they live in the WP Arzo Pro add-on. Everything else is unchanged, and the
  free plugin no longer executes any user-supplied code.

= 6.155.0 =
* Changed: **Enhance List Tables** now makes clear it applies to classic list tables (posts, pages,
  and custom post types). On WordPress 7.0+, the core Posts/Pages/Media screens use the new React
  **DataViews** grid, where the classic column hooks do not fire — the ID/thumbnail columns simply
  don't appear there (a graceful no-op, never a break); custom post types are unaffected. Pairs with
  the WP Arzo Pro v1.72.0 Admin Branding fix that recolors the whole modern admin/editor/DataViews.

= 6.154.0 =
* Improved: **Config Import/Export** now round-trips **every** feature's configuration, not just
  schema-based settings. Features that store config in their own option — Site Health, Cron Manager,
  Redirects, Content Types, Custom Fields, Menu Manager, Notifications — are now included via a new
  `wp_arzo_config_option_keys` allow-list filter (Pro registers its modules). Import only writes keys
  on the allow-list, so a config file can never inject an arbitrary option; secrets (backup/OAuth
  credentials, API keys, 2FA), logs, and analytics/content data are deliberately excluded.

= 6.153.0 =
* Added: a new **“MCP only”** scope for REST API keys. An MCP-scoped key authenticates *only* for the
  WP Arzo MCP endpoint and is rejected everywhere else on the REST API — so you can safely hand an AI
  agent a key that drives the MCP server without granting general REST write access. (Read-only and Full
  scopes are unchanged.)

= 6.152.1 =
* Changed: bumped "Tested up to" to WordPress 7.0 (verified end-to-end on a live 7.0 install — dashboard,
  analytics, Advanced Tools console, and the Pro MCP server + WP 6.9 Abilities API all working).

= 6.152.0 =
* Changed: accessibility, SEO & token polish — the custom select now has an accessible name
  (aria-label from its label), AJAX list pagers announce page/count changes via an aria-live region,
  the emergency recovery login field gained autocomplete, and the toggle knob uses design tokens.

= 6.151.0 =
* Changed: Roles (the capability editor) is now its own submenu (WP Arzo → Roles) instead of a
  Settings tab — it's a full workspace, not config-light. Old Settings→Roles links redirect. Also
  fixed submenu ordering for the Pro Site Health + Manage License pages, and routed the GA4/GTM/Ads
  "Configure" link to the Analytics Google tab.

= 6.150.0 =
* Changed: internal cleanup — removed dead license-activation code (an unused AJAX handler + JS that
  targeted markup no longer rendered), scrubbed stale "Freemius" comments (licensing is SureCart),
  and deactivation now clears all plugin cron events (analytics prune/rollup, temp-login GC) so none
  fire while the plugin is off. No user-facing behavior change.

= 6.149.0 =
* Changed: WordPress.org submission-readiness (pass 2) — the Advanced Tools console is now CDN-free.
  Removed the Font Awesome stylesheet and migrated ~39 console icons (Site Info, Users, Site Modes,
  Extra Options, Temporary Login, incl. JS-built buttons) to inline SVG. The free plugin now makes
  zero external asset requests on any surface.

= 6.148.0 =
* Changed: WordPress.org submission-readiness (pass 1). Declared GPL-3.0-or-later in the plugin header
  and readme (was "Proprietary"); retired the in-plugin GitHub self-updater from the .org build via a
  new .distignore + file_exists() guard; fixed the readme contributor slug, Tested-up-to, and tags.
* Changed: removed all CDN fonts/icons from public surfaces — the Maintenance/Coming-Soon/Payment page,
  the Emergency Recovery tool, and wp-arzo.css now use inline SVG icons + the system font stack (no more
  Font Awesome / Google Fonts requests, no visitor IPs sent to third parties).
* Security: the Emergency Recovery tool is now noindex (meta + X-Robots-Tag) with a locked-down CSP, and
  the ?debug switch that could enable display_errors on a public URL was removed.

= 6.147.0 =
* Changed: the dashboard License card now reflects WP Arzo Pro's update-gating model with three
  states — green "Pro active" (licensed), amber "Updates paused" (Pro features working, license
  inactive → activate for updates), and the "Free" upsell. Reads a new `wp_arzo_pro_license_active`
  signal from the Pro add-on.

For the full history (versions before 6.147.0), see:
https://github.com/yasirshabbirservices/wp-arzo/blob/main/CHANGELOG.md
