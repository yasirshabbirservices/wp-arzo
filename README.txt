=== WP Arzo - Maintenance & Administration Suite ===
Contributors: yasirshabbir
Tags: maintenance, administration, analytics, smtp, security
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.2
Stable tag: 6.148.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

One suite to run, secure, optimize, and rescue your WordPress site — replace 20+ single-purpose plugins.

== Description ==

**WP Arzo replaces a drawer full of single-purpose plugins with one cohesive, lightweight suite.**
Three reasons sites switch:

* **Replace 20+ plugins.** ~50 site-enhancement features in one dashboard — SMTP with fallback,
  code snippets, backups, security hardening, login protection, media tools, performance tweaks,
  role/capability editing, REST API keys, and dozens more. Enable only what you need; disabled
  features load zero code.
* **Analytics without Site Kit.** A built-in, cookieless, first-party analytics engine — traffic,
  top pages, referrers, geo, devices and behaviour, recorded in your own database. No external
  service, no cookie banner, no MonsterInsights.
* **AI-agent ready.** WP Arzo Pro exposes a governed MCP (Model Context Protocol) server so AI
  assistants can safely read and act on your site — permissioned, audited, and confirm-gated.

Everything is administrator-only and built for modern WordPress: accessible (WCAG 2.2 AA),
token-themed, and modular so your site only ever loads what it uses.

**Advanced Tools console** — a standalone dark console of hands-on power tools: Site Info, Users,
Debug, Site Modes, Extra Options, Temporary Logins, plus (with WP Arzo Pro) a full **File Manager**
and **Database manager**. **Emergency Recovery** — a self-contained script that works even when
WordPress is fully down.

The console opens in its own tab via the WordPress admin menu and authenticates with your
existing WordPress session (administrators only).

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Click 'WP Arzo' in the admin menu to open the tool in a new tab

== Changelog ==

See CHANGELOG.md for the full history.

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

= 6.146.0 =
* Changed: the console **File Manager (elFinder)** and **Database manager (AdminNeo)** are now
  WP Arzo Pro power-tools. Moving these large third-party libraries out of the free core makes the
  free plugin dramatically lighter (~19 MB smaller) and shrinks its security surface. When Pro is
  active the tools work exactly as before; without Pro, those console tabs show an unlock prompt.
* Changed: refreshed the plugin description to lead with what matters — replace 20+ plugins,
  built-in cookieless analytics, and AI-agent (MCP) readiness.

= 6.145.0 =
* Changed: the **Email Log** and **Activity Log** now paginate + filter via **server-side AJAX**
  (`admin-ajax`, in-place table swap — the project's required list pattern), replacing the interim
  client-side pager from 6.144.0. The DOM only ever holds one page (25/page); search + status/
  severity/event filters query the server and reset to page 1; row-click drawer uses event
  delegation so it survives page swaps. New reusable `wpArzo.ajaxList()` controller (seeds page
  state from `data-paged`/`data-pages`, guards against out-of-order responses). Verified on a live
  site.
* Fixed (Pro 1.65.3): Cron Manager “Events” Next/Prev pager was a no-op — now seeds its page count
  from the server so navigation works on the first click.

= 6.144.0 =
* Added: reusable client-side table pager (`wpArzo.tablePager`) — a filter-aware pager for
  lists that render every row server-side. The **Email Log** and **Activity Log** now paginate
  (25 per page) instead of one long scroll; the pager self-hides when everything fits on one
  page and works together with the existing search/status filters.

= 6.143.1 =
* Fixed: `<button>`-based `.wpa-tab` tabs (e.g. the Pro Site Health page's Checks / Trends /
  Settings) rendered with the browser's native light-gray button chrome on inactive tabs —
  the component now strips button appearance/background/border so button and link tabs look
  identical.

= 6.143.0 =
* Changed: the Analytics page's report tabs moved from a horizontal row to a new reusable
  `.wpa-vnav` left-sidebar rail — much better with many tabs (Pro adds Campaigns, Real-time,
  Events, Journeys, eCommerce…). Few-tab pages keep the horizontal pills.

= 6.142.0 =
* Added: `menu` and `eye-off` icons; the Pro catalog now advertises the new **Admin Menu
  Manager** (drag-drop reorder / hide / rename wp-admin menus, saved per role).

= 6.141.2 =
* Fixed: the "Analytics — last 7 days" dashboard widget showed dark KPI numbers on dark tiles
  (it used the dark admin-dashboard tokens on the light wp-admin dashboard) — now readable.

= 6.141.1 =
* Changed: Pro “Admin Branding & Dashboard” card copy now advertises the full-page Custom
  Dashboard (render any Bricks/Elementor/Divi/WordPress page or template as the dashboard).

= 6.141.0 =
* Security: hardening across the Emergency Recovery tool (recovery password is now set only
  from inside WordPress), the Advanced Tools file manager (CSRF nonce on the file connector),
  and Config Import (imported code snippets are always imported disabled). Updating recommended.

= 6.140.2 =
* Changed: the dashboard "License" card now reflects Pro status and links to the Pro "Manage
  License" page (removed an old inline license field that predated the current activation flow).

= 6.140.1 =
* Changed: the "Get Pro" upsell link now points to https://wparzo.com/ (filterable).

= 6.140.0 =
* Changed: On-demand feature loading — the registry loads a feature's PHP class only when it's
  enabled (or its page opens), instead of parsing every module file on every request. A front-end
  request now loads only the features you actually use. Behavior-preserving; also applied to Pro.

= 6.139.0 =
* New (free): REST API Auth — per-key read-only scope + per-key auto-expiry (companion features)
* New (free): Email Log Stats tab (per-connection volume + deliverability trend over 7/30/60 days)
* New (free): Role capability editor grouped by category with a live filter
* New (free): live AJAX debug-log console + read-only wp-config/.htaccess viewer (secrets masked)
* Changed: Advanced Tools launcher page fully dark + tokenized (was on a white background)
* Pro (v1.53.0-v1.60.0): Site Health trends/uptime/status-page/digest; Cron bulk actions + CSV +
  Snippet jobs + cron-tick fatal capture; Email open/click tracking; Notifications severity floor +
  quiet hours. See CHANGELOG.md.

= 6.104.0 =
* New (free): Email retry queue — messages that every connection fails to send are queued and
  auto-retried with backoff (5m/15m/1h/6h, up to 4 tries); new Queue tab with retry/delete/clear
* New (free): 4 more SMTP providers — SMTP2GO, SparkPost, MailerSend, Elastic Email (16 total)

= 6.103.1 =
* Changed: conditional-logic builder UI polish — segmented all/any toggle, rule chips with live
  IF/AND/OR connectives, branded chevron selects, right-aligned soft-red delete

= 6.103.0 =
* New (free): Code Snippets — Smart Conditional Logic. Gate a snippet to run only where you want
  (match all/any of: user login, user role, post type, page type, URL path, device, date/time
  schedule). No rules = runs everywhere. (Enrichment #3.)

= 6.102.3 =
* Changed: the console Files tab (elFinder file manager) is now fully dark — darkened the light
  list-view column-header bar + dialog/tooltip/input gaps, and softened selection to accent-soft

= 6.102.2 =
* Changed: full semantic-color audit of both plugins (colors already matched intent); fixed the
  Unlock/Unlock-all lockout buttons to use a proper unlock icon instead of a misleading trash glyph

= 6.102.1 =
* Added: pause/play icons in the registry so the Cron Manager pause/resume action uses real SVGs
  (no more emoji glyphs)

= 6.102.0 =
* Added: semantic action colors (color = intent) — destructive/delete/clear buttons now read red
  across the dashboard, console and Emergency tool; new subtle `.wpa-btn--danger-soft` row style
* Changed: delete-row / Clear log / Revoke buttons turned red; more console buttons gained icons

= 6.101.1 =
* Fixed: the `hidden` attribute now correctly hides wpa- buttons/tabs/badges (a class-set display
  was overriding it) — e.g. the Advanced Audit "Reset" button hides when no filter is active

= 6.101.0 =
* Fixed: Emergency tool Plugins & Themes tabs were always empty (WP_CONTENT_DIR resolved one
  level too deep) — both lists populate again
* Changed: Emergency tool gains a full inline-SVG icon set across its nav + every button, a
  rebuilt Create Administrator form, and consistent login/setup styling
* Changed: Temporary Logins Role/Expires selects use the branded dropdown; Snippets Import/Export
  moved into a clean modal; more console buttons gained icons; global checkbox glyph re-centered
* Added: documented a repo-wide "Surface Consistency Bar" (trigger phrase "arzo consistency pass")

= 6.100.0 =
* Changed: Emergency recovery page redesigned to match the dashboard — segmented pill tabs,
  dark sunken inputs, info-card System Status, elevated panels
* Changed: Site Modes -> Emergency Mode card rebuilt from a cramped single row into a clean
  stacked card (header + toggle, description, actions row with the note below)
* Changed: Console inputs/panels aligned to the dashboard's darker surfaces; Site Info status
  badges + progress track and the error banner moved onto semantic tokens
* Fixed: public maintenance page had two self-referential (invalid) CSS variables that broke
  the card background and message color — now render correctly

= 6.65.0 =
* Changed (free): Snippets rebuilt into an editor app — a CodeMirror syntax-highlighted
  editor (mode per type) + meta panel + right-hand snippet list with inline toggles; adds an
  optional per-snippet Description (erropix Advanced Scripts-inspired)

= 6.64.0 =
* New (free): Email connections manager (WP Arzo -> Email) — a provider-card picker
  (Custom SMTP, Gmail, Outlook, Zoho, Yahoo, Fastmail, Amazon SES, Mailjet, SendGrid, Brevo,
  Mailgun, Postmark) opening a config drawer, with multiple named connections, a primary +
  ordered fallback, and a per-connection test send (SureMail-inspired). Legacy SMTP settings
  auto-migrate

= 6.20.0 =
* Improved: Custom Login Page now fully styles the form (inputs, labels, links, button,
  remember-me checkbox, password toggle, messages) across all login screens; adds input
  background, button text color, and rounded-corners options

= 6.19.1 =
* Fixed (critical): Custom Login URL caused PHP warnings on the login page (wp-login.php
  is now loaded natively + gated by a secret key, ASE-style)
* Changed: WebP per-upload confirmation now reliable (incl. bulk uploads); added optional
  video -> WebM via ffmpeg

= 6.19.0 =
* New (free): Scheduled Backups — automatic database snapshots on a daily/weekly/monthly
  WP-Cron schedule with scope + retention

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
