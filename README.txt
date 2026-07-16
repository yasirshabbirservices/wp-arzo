=== WP Arzo - Administration Suite ===
Contributors: yasirshabbir
Tags: maintenance, administration, analytics, smtp, security
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 6.162.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

An administration and site-enhancement dashboard for WordPress: analytics, email delivery, backups, security, and admin tools in one place.

== Description ==

WP Arzo is a dashboard of site-enhancement features you enable individually — a disabled feature
loads no code. It includes:

* A built-in, cookieless, first-party analytics engine — traffic, top pages, referrers, geo,
  devices and behaviour, recorded in your own database. No external service and no cookie banner
  are required.
* Multi-provider SMTP email delivery with fallback, local and scheduled backups, login and
  security hardening, media tools, performance tweaks, role/capability editing, and REST API key
  management.
* An optional MCP (Model Context Protocol) server (WP Arzo Pro) that lets AI assistants read and
  act on your site through permissioned, audited, confirm-gated tools.

Every feature is administrator-only. The interface is built for modern WordPress: accessible
(WCAG 2.2 AA) and token-themed.

**Advanced Tools console** — a standalone dark console of day-to-day admin tools: Site Info, Users,
Plugins, Themes, Debug, Site Modes, Extra Options, and Temporary Logins.

The console opens in its own tab via the WordPress admin menu and authenticates with your
existing WordPress session (administrators only).

== External services ==

This plugin can connect to external services that you choose to configure. No external service is
contacted unless you enable and set up the corresponding feature with your own account or API key.

**Email delivery providers.** The Email feature can send outgoing mail through a provider you
configure with your own account: SendGrid, Brevo (Sendinblue), Mailgun, Postmark, Amazon SES,
Gmail / Google Workspace, Outlook / Microsoft 365, Zoho Mail, Yahoo Mail, Fastmail, or any custom
SMTP server. When an email is sent, its contents (recipient, subject, body, headers) are transmitted
to the provider you selected, authenticated with the credentials you supplied, so it can deliver the
message. Nothing is sent unless you have configured and enabled that connection.
- SendGrid: https://sendgrid.com/policies/tos/ · https://www.twilio.com/en-us/legal/privacy
- Brevo: https://www.brevo.com/legal/termsofuse/ · https://www.brevo.com/legal/privacypolicy/
- Mailgun: https://www.mailgun.com/legal/terms/ · https://www.mailgun.com/legal/privacy-policy/
- Postmark: https://postmarkapp.com/eula · https://postmarkapp.com/privacy-policy
- Amazon SES: https://aws.amazon.com/service-terms/ · https://aws.amazon.com/privacy/

**Google Analytics 4 / Google Tag Manager / Google Ads.** These are optional tags. If you enable one
and enter your own Measurement ID, Container ID, or Conversion ID, the plugin loads Google's tagging
script (gtag.js / gtm.js) from googletagmanager.com on the front end, and visitor browsers then send
standard analytics/advertising events directly to Google under the ID you provided. Nothing is sent
unless you have entered an ID for that tag.
- Google: https://policies.google.com/terms · https://policies.google.com/privacy

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
