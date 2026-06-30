# Changelog

All notable changes to **WP Arzo – Maintenance & Administration Suite** are documented
in this file. This project loosely follows [Keep a Changelog](https://keepachangelog.com/)
and [Semantic Versioning](https://semver.org/).

## [6.18.0] — 2026-06-30

### Added — Media Cleanup tool
- **WP Arzo → Media Cleanup** — scan the media library (batched, with a **progress bar**)
  to find attachments with **no detectable references**. Usage is checked conservatively
  (featured image, site logo/icon, post content incl. `wp-image-<id>`, and post meta /
  ACF / page-builder storage), biased toward "in use" so live files aren't flagged.
- **Filters** (possibly-unused only, type, filename search) + a **reclaimable-space**
  summary, thumbnails, select-all, and **batch delete** (explicit selection + confirm;
  removes all image sizes). Clear warning to back up first — detection can't see theme
  options / hard-coded CSS / external caches.

## [6.17.0] — 2026-06-30

### Added (free) — Auto WebP Conversion (40 free features total)
- **Auto WebP Conversion** — converts uploaded JPEG/PNG to **WebP on `wp_handle_upload`**
  (before the attachment is created, so the WebP becomes the library item and all
  thumbnail sizes are WebP). GD `imagewebp()` with an Imagick fallback; degrades safely
  when neither supports WebP.
- Settings: quality, convert JPEG / PNG (alpha preserved), **max-width resize**, **keep
  original**, and **“ask before converting on each upload”** — a per-upload confirmation
  wired into the media uploader (`wp.Uploader` → multipart param the server honors).

## [6.16.0] — 2026-06-30

### Changed (free) — Advanced SMTP + Email Log (SureMail-style)
- **SMTP → Advanced SMTP:** added a **backup connection (failover)** — if the primary
  connection fails, the email is automatically **retried via the backup** — plus
  **failure notifications** (email an address when a message can't be delivered) and a
  **"Send test email"** button right in the settings.
- **Email Log:** now stores the message/headers so failed (or any) emails can be
  **resent** with one click, and the page shows **sent / failed analytics** counts.
- New settings field type `test_email`; new AJAX: send-test-email, resend.

## [6.15.1] — 2026-06-30

### Fixed (critical)
- **Custom Login URL caused a fatal on the login page** (`Undefined constant
  "AUTOSAVE_INTERVAL"`). The feature `require`d `wp-login.php` during `plugins_loaded`, but
  WordPress defines its functionality constants *after* that hook, so `wp-login.php`'s
  script localization fataled. The secret-slug request is now detected on `plugins_loaded`
  but `wp-login.php` is loaded on **`wp_loaded`** (after all constants are defined). Direct
  `wp-login.php` blocking is unchanged.

## [6.15.0] — 2026-06-30

### Added (free) — Code Snippets Manager (39 free features total)
- **Code Snippets** — manage PHP / CSS / JS / HTML snippets under **WP Arzo → Snippets**:
  per-snippet type, scope (everywhere / admin / front), active toggle, edit/delete.
- **Fatal-guard:** a PHP snippet that errors (caught Throwable, or an uncatchable fatal via
  a shutdown backstop) is **auto-disabled with the error recorded** — a bad snippet can't
  permanently break the site. The "Code Snippets" feature toggle is a global kill switch.
- Snippet storage (`wp_arzo_snippets`) is removed on uninstall.

## [6.14.0] — 2026-06-30

### Added (free) — 3 new feature modules (38 free features total)
- **Site Verification** — Google / Bing / Pinterest / Yandex / Baidu verification meta tags.
- **Remove jQuery Migrate** — drop jquery-migrate.js on the front end.
- **Disable Front Dashicons** — skip the Dashicons stylesheet for logged-out visitors.

## [6.13.1] — 2026-06-30

### Fixed (critical)
- **Dashboard truncated / sidebar missing / login features not showing.** `Custom Login
  URL`'s `settings_schema()` called `get_setting()`, which resolves defaults *through*
  `settings_schema()` — an **infinite recursion** that exhausted memory and fatally aborted
  the page the moment that card rendered (cutting off later features and the whole sidebar).
  Present since 6.10.0. Fixed by reading the saved value directly, **and** by adding a
  re-entrancy guard to `WP_Arzo_Feature::get_setting()` so no feature can ever trigger this
  again. The dashboard now also renders each card / the sidebar inside a guard so a single
  feature error can never truncate the page.

## [6.13.0] — 2026-06-30

### Added (free) — 5 new feature modules (35 free features total)
- **Crawl Optimizations** — remove generator / RSD / WLW-manifest / shortlink / REST &
  oEmbed link tags from `<head>`.
- **Custom Body Class** — add custom classes to the front-end `<body>`.
- **Disable Application Passwords** — turn off Application Passwords site-wide.
- **Clean Up Admin Bar** — remove the WP logo / comments / updates / “New” toolbar nodes
  (toggleable).
- **Enhance List Tables** — add an ID column and a featured-image thumbnail to posts/pages.

## [6.12.0] — 2026-06-30

### Added (free) — 8 new feature modules (30 free features total)
- **Page & Post Duplication** — one-click "Duplicate" row action (copies content, taxonomies
  and meta to a draft).
- **Missed Schedule Fix** — auto-publishes scheduled posts WordPress missed (throttled).
- **SVG Upload** — admins can upload SVGs, with basic on-upload sanitization.
- **Last Login Column** — records last login and shows it in the Users list.
- **Header / Body / Footer Code** — inject custom code into `<head>`, after `<body>`, or
  before `</body>` (new raw `code` settings field type).
- **Custom CSS** — front-end and/or admin CSS.
- **Disable All Updates** — stop core/plugin/theme update checks, notices and auto-updates.
- **Login / Logout Redirects** — send users to a custom URL after login (scopeable) or logout.

## [6.11.0] — 2026-06-30

### Added
- **Off-site backup hook** — the backup engine now fires
  `do_action('wp_arzo_after_snapshot_created', $id, $manifest, $dir)` after each snapshot,
  so Pro/cloud destinations can push the snapshot off-site. (First destination —
  **FTP** — ships in WP Arzo Pro 1.2.0; cloud/S3/Drive follow.)

## [6.10.0] — 2026-06-30

### Added (free)
- **Custom Login URL** — move `wp-login.php` to a secret slug; login links (emails,
  logout, password reset, register) are rewritten to it, and direct hits on the default
  endpoint are bounced home.
- **Limit Login Attempts** — lock out an IP after N failed logins for a configurable
  window (transient-based, auto-expiring), with success clearing the counter.

### Changed
- **Dashboard layout** is now two-column: the feature grid on the left and a sticky
  **sidebar** on the right holding a **License / activation** card and the cross-promotion
  area (previously full-width at the bottom). Collapses to a single column on narrow
  screens.
- Settings renderer used by the sidebar license box delegates real activation to
  `wp_arzo_activate_license_result` (Pro/Freemius); until connected it reports that
  licensing isn't available yet.

## [6.9.1] — 2026-06-30

### Fixed
- **Dashboard form fields rendered with a white background** — wp-admin's
  `input[type="text"]` styles out-specified the single-class `.wpa-input`. Added a scoped,
  higher-specificity override so text/number/textarea fields are dark on WP Arzo screens.
- **Custom Login Page now brands the entire login flow** — sign-in, **lost password**,
  **reset password**, and **register**. The CSS targeted only `#loginform`; it now uses
  `.login form` so every wp-login.php view is styled consistently.

## [6.9.0] — 2026-06-30

### Added (free)
- **Custom Login Page** — brand `wp-login.php` with a custom logo, page/form/text/accent
  colors and optional extra CSS; the logo links back to the site.
- Settings renderer gained a `color` field type (native color picker, `sanitize_hex_color`).

## [6.8.0] — 2026-06-30

### Added (free)
- **SMTP Email Delivery** — route `wp_mail()` through your SMTP server (host/port/
  encryption/auth, optional force-from name/email) via `phpmailer_init`.
- **Email Log** — logs outgoing email (recipient, subject, sent/failed + error),
  newest-first and capped, with a **WP Arzo → Email Log** page (status badges, clear-log).
- Settings renderer gained `password` and `email` field types. Passwords are never
  re-rendered, and a blank submit keeps the saved secret.
- **Tiering confirmed:** SMTP, Email Log, **local** snapshots, and custom login are all
  **free**; only **cloud/remote** backup destinations are Pro.

## [6.7.0] — 2026-06-30

### Added
- **Freemium gate** in the free core: `wp_arzo_is_pro_active()` (filter
  `wp_arzo_pro_active`) drives `wp_arzo_feature_is_available`, so pro-tier features lock
  until Pro is active; the dashboard shows an **Unlock** CTA pointing at
  `wp_arzo_pro_upgrade_url()`. This is the integration surface for the **WP Arzo Pro**
  add-on (separate private repo) and Freemius licensing.

## [6.6.4] — 2026-06-30

### Changed
- **Fluid, variable-driven CSS:** headings now use `clamp()` (`--arzo-fs-lg/xl/2xl`), and
  the dashboard uses new `--arzo-container` / `--arzo-card-min` variables plus
  `clamp()`/`calc()` for margins, grid columns, gaps, and the search field — replacing
  hard-coded magic numbers so the UI scales smoothly across viewports.

### Docs
- `CLAUDE.md`: added a **Working agreement** (always update roadmap/docs/skills + version +
  commit/tag/release/push) and CSS conventions (`clamp`/`calc`/variables).

## [6.6.3] — 2026-06-30

### Changed
- **UI consistency:** the admin dashboard, Backups, and feature-settings screens now share
  a console-style **tab navigation** (Dashboard / Backups / Advanced Tools) and the same
  branded header + chrome, so the native dashboard matches the standalone console's look
  and feel.

## [6.6.2] — 2026-06-30

### Fixed
- **Admin-menu icon** rendered at full size (a giant logo overflowing the sidebar into the
  page). WordPress doesn't size a URL-based menu icon, so the logo PNG is now constrained
  to 20×20 (with hover/active opacity) via a small global admin style.

## [6.6.1] — 2026-06-30

### Fixed
- **Asset cache-busting** no longer depends on bumping the plugin version. The buster now
  derives from the file's `filemtime` **+ `filesize`**, falls back to a short **content
  hash** when `filemtime` is unavailable (locked-down / LiteSpeed hosts previously fell
  back to the static plugin version, so CSS/JS edits didn't take effect until a manual
  bump), and busts on **every request** when `WP_DEBUG` is on (instant updates while
  developing).

## [6.6] — 2026-06-30

WP Arzo becomes a **feature suite**: a native wp-admin dashboard + a feature registry, 16
free features, a database backup engine, and a full design-system / UI overhaul — on top of
the v6.5 security and bug fixes.

### UI & branding
- Dashboard restyled to a **full-dark, branded** screen — no white wp-admin chrome on WP
  Arzo pages. Branded header bar (YS logo + version + GitHub) mirroring the console, and the
  **plugin logo is used as the admin-menu icon**.
- **Compact toggle-box** feature cards (Debug-settings style): label + 2-line-clamped
  description on the left, modern toggle on the right; smaller boxes, better readability.
- Fixed a duplicated toggle label (the SR-only label was rendering visibly on the dashboard).
- Added a **filterable cross-promotion area** (`wp_arzo_promoted_products`) on the dashboard
  to surface WP Arzo Pro and other products.

### Added — backup engine v1 (DB snapshots)
- **Backup Manager** (`includes/class-wp-arzo-backup-manager.php`): low-memory, streaming
  **database snapshots** (JSONL, gzip when available) at two scopes — `options` (fast) or
  `full_db`. Create / list / **restore** (takes a safety snapshot first; structure-
  preserving TRUNCATE + re-insert) / delete, with automatic **retention** pruning.
  Snapshots live in a web-protected folder under `uploads/wp-arzo-backups/`; snapshot ids
  are validated against path traversal. Verified end-to-end (create → restore round-trip,
  special-character integrity) via a fake-`$wpdb` harness.
- **Automated Snapshots feature** (`auto_snapshots`): when enabled, takes a DB snapshot
  **before any feature is toggled** (wired to `wp_arzo_before_feature_toggle`), with
  scope + retention settings.
- **Backups admin page** (WP Arzo → Backups): create a snapshot (scope select), and a
  table of snapshots with **Restore** / **Delete** — AJAX, capability + nonce gated, built
  from the component library.

### Roadmap
- Locked **Freemius** as the licensing/checkout provider; documented the SDK integration
  plan (gate Pro via `wp_arzo_feature_is_available` → Freemius license state).

### Added — feature dashboard & registry

The backbone of the suite: a native wp-admin feature manager that everything plugs into.

### Added
- **Feature registry** (`includes/class-wp-arzo-feature.php` + `class-wp-arzo-feature-registry.php`):
  every feature is a self-contained module declaring `id / title / group / tier / icon /
  settings_schema / boot()`. The registry persists enabled-state (`wp_arzo_features`) and
  per-feature settings (`wp_arzo_settings`), boots only enabled features, and fires
  `wp_arzo_before_feature_toggle` / `wp_arzo_feature_enabled` / `…_disabled` /
  `…_toggled` — the integration point for the upcoming auto-snapshot/backup system.
- **Native admin dashboard** (`includes/admin/class-wp-arzo-admin.php` + `wp-arzo-admin.css`
  /`.js`): a grouped, searchable **feature toggle grid** built from the component library
  (modern toggles, icons, cards, badges), with live AJAX enable/disable (capability +
  nonce), Pro chips, and a schema-driven per-feature **settings** screen. Menu restructured
  to a top-level **WP Arzo** dashboard with the standalone power-console moved under
  **Advanced Tools**.
- **Feature modules (16 free, registry-driven):**
  - Utilities/Admin: Hide Admin Bar (scope), Disable Dashboard Widgets, Disable Emojis,
    Disable Self Pingbacks.
  - Core: Disable Comments, Disable Gutenberg, Disable RSS Feeds, Disable Embeds.
  - Security: Disable XML-RPC, Restrict REST API, Disable Theme/Plugin Editor, Block User
    Enumeration.
  - Content/Dev: Revisions Control (max setting), Heartbeat Control (behavior + frequency).
  - Marketing/SEO: Manage robots.txt, Manage ads.txt (both with content settings).
- Add-on hook `wp_arzo_register_features` so the future Pro plugin registers its modules
  into the same registry, and `wp_arzo_feature_is_available` to gate Pro features by
  license.

### Changed
- `wp_arzo_features` and `wp_arzo_settings` options are removed on uninstall.

### Added — design-system foundation

Groundwork for the larger feature-suite roadmap ([.claude/ROADMAP.md](.claude/ROADMAP.md)).
No feature behavior changes; this is the design/UI foundation everything else builds on.

### Added
- **Single design-token source of truth** (`assets/css/design-tokens.css`): canonical
  `--arzo-*` tokens with full color/spacing/radius/typography/elevation/motion/z-index
  scales, legacy aliases for back-compat, and reduced-motion handling. Now loaded in the
  console (it was previously never loaded — the `--arzo-*` tokens `design.md` documented
  resolved to nothing).
- **Component library** (`assets/css/wp-arzo-components.css` + `assets/js/wp-arzo-components.js`):
  reusable `wpa-` primitives — Button, modern Toggle, accessible custom Select
  (progressive-enhanced from a native `<select>`), Badge/Status, Card, Field, Toast.
- **Icon system** (`includes/wp-arzo-icons.php`): `wp_arzo_icon()` inline-SVG registry
  (currentColor, 24×24 stroke) — real icons for states/actions, no emoji/default glyphs.
- `.claude/ROADMAP.md` — product & engineering roadmap (feature-registry architecture,
  freemium split, component plan, AI/MCP/snippets modules, phasing).

### Changed
- Modernized the global toggle (`.switch`/`.slider`) — pill track, soft knob shadow,
  accent glow, keyboard `:focus-visible` ring; all existing toggles upgraded in place.
- Added a global accessibility layer: consistent `:focus-visible` ring on all interactive
  elements, `prefers-reduced-motion` support, and a `.wpa-sr-only` utility.
- Console now loads tokens → components → base CSS in the correct order; removed the
  duplicate `:root` palette from `wp-arzo.css`.
- Status badges aligned to the component system (pill, semantic soft fills, status dot).
- Native `<select>` fields (Extra Options target, Create-User role) upgraded to the
  accessible custom select (`data-wpa-select`).

### Emergency tool (`wp-arzo-emergency/`)
- **Security:** added IP-based brute-force throttling on the recovery login (lockout
  after repeated failures) and `session_regenerate_id()` on success; tightened the
  Content-Security-Policy (removed `unsafe-eval`, scoped `script-src`/`style-src`, added
  `frame-ancestors 'none'`, `base-uri`, `form-action`, `Referrer-Policy`).
- **Consistency:** reconciled the version (now 2.3 across header + constant); aligned its
  inline tokens with the design system and fixed an undefined `--radius-global` (its radii
  were collapsing to 0). Documented the intentional `md5()` password trick (WP re-hashes on
  first login).
- Throttle state file is cleaned up on plugin uninstall.

### Roadmap
- Added the **Backup, restore & versioning** system (Git-style snapshots, auto-snapshot on
  feature toggle / risky actions, cloud remotes: Google Drive / Dropbox / pCloud / FTP-SFTP
  / S3 / Git, encryption + retention), a **repository / freemium / licensing strategy**
  (free core public+GPL, Pro in a private repo), and more candidate modules (activity log,
  cron manager, redirects/404, notifications, safe mode, multisite, white-label, …).

## [6.5] — 2026-06-30

A maintenance release focused on fixing broken features and closing security holes
across the administration console. All changes are admin-only tooling fixes; no data
migrations are required.

### Fixed (functional)

- **Quick Login → "Direct Admin Access" link was completely broken (404).** The
  generated URL pointed at `…/login.php?maintenance_access=…`, a path that does not
  exist, and the token handler only ran *after* the capability gate (so a logged-out
  admin could never use it). The link now targets the standalone endpoint and is
  handled in `wp_arzo_handle_standalone()` **before** the gate, so it works as an
  emergency re-entry link. The token is now a strong, single-use, 1-hour value **bound
  to the admin who generated it** (previously it logged you in as an arbitrary "first
  administrator").
- **Public maintenance page could render blank with PHP warnings.** An unknown/legacy
  `maintenance_tool_active_mode` value caused "array offset on null" warnings (PHP 8)
  injected into the page. Unknown modes now short-circuit cleanly.
- **Admin detection on the maintenance page** used `current_user_can('administrator')`
  (a role name where a capability is expected); switched to `manage_options`.
- **Database tab fatal on PHP 8.** A `0`/blank `per_page` produced a
  `DivisionByZeroError`. Pagination now clamps `page`/`per_page` to a minimum of 1.
- **Database queries containing quotes were corrupted** by WordPress's added slashes;
  the query is now passed through `wp_unslash()`. Table names in the row-count query are
  backticked so reserved-word tables don't error.
- **Users table treated the current admin as a different user** (strict `===` between an
  int and a possibly-string ID), showing Login/Delete buttons against your own account.
  Comparison is now type-safe.
- **Site Info disk row** could emit a PHP TypeError/`NAN` when a size was `0`
  (`log(0)` / float array index) or when total disk space was `0` (division by zero).
  Both are now guarded.
- **Theme "activate immediately" on upload** could switch the site to a non-existent
  stylesheet. The theme cache is now refreshed and the stylesheet validated before
  switching (both on upload and on the activate action).
- **Extra Options → wp-config.php target** silently discarded execution-time / upload /
  post-size values (those can't be set via PHP constants) while reporting success. The
  message now states that only the memory limit is written there.
- **Quick-login activity write to `debug.log`** no longer emits a PHP warning on
  read-only hosts (writability is checked first).
- Removed dead `view_file` / `edit_file` / `save_file` routes that advertised endpoints
  the elFinder-based file manager does not implement.

### Security

- **Arbitrary file download / path traversal (critical).** The `?download=` handler
  streamed *any* server-readable file (e.g. `wp-config.php`, private keys, `/etc/passwd`).
  Downloads are now confined to the WordPress install root via `realpath()` and re-check
  `manage_options`.
- **wp-config.php code injection via Debug settings (critical).** Debug values were
  written verbatim into `wp-config.php` as PHP. Values are now strictly limited to the
  literal `true`/`false`, and the form is nonce-protected.
- **Config-file injection via Extra Options (critical).** PHP-limit values were written
  raw into `wp-config.php` / `.htaccess` / `php.ini` (a newline could inject directives
  such as `auto_prepend_file`). Values are now validated as size/integer literals, the
  target file is allow-listed, and the form is nonce-protected.
- **CSRF protection added** to state-changing operations that previously relied only on
  the page-level capability gate:
  - User create / delete / impersonate (Quick Login as user)
  - Temporary-admin creation and the Direct Admin Access link
  - SQL query execution
  - Plugin activate/deactivate and theme activation (AJAX)
  - Debug log clear/log and debug-setting writes
  - Site mode activate/deactivate, option auto-save, and emergency-script
    generate/delete
- **User creation role** is now allow-listed (no arbitrary/empty role strings).
- **File manager dotfile exposure.** The elFinder connector referenced an undefined
  `access` callback, so the intended hiding of dot-files (`.env`, `.git`, `.htpasswd`)
  silently did nothing. A real `wp_arzo_elfinder_access` callback now denies them.
- Custom maintenance CSS is passed through `wp_strip_all_tags()` to prevent a
  `</style><script>` breakout.

### Added

- `CLAUDE.md` — architecture / routing / conventions / security baseline for
  contributors and AI agents.
- `.claude/` — committed agent docs: `design.md` (design system) and task-specific
  skills under `.claude/skills/`.
- This `CHANGELOG.md`.

### Notes / known follow-ups

- The Database tab is, by design, an unrestricted SQL console; it is now CSRF-protected
  but still executes whatever an authenticated admin submits. Treat with care.
- The emergency endpoint (`/wp-arzo/emergency/`) and `arzo-safe.php` recovery flow are
  unchanged in behavior aside from the new CSRF protection on generation/deletion.

## [6.4] and earlier

Earlier history was not tracked in a changelog. Highlights from commit history:

- 6.4 — Centralized cache-safe asset loading (`wp_arzo_get_asset_url`), plugin debug
  info, OPcache invalidation on activation/version change; global border-radius and
  various UI refinements.
- 6.0–6.3 — Modular feature architecture (`features/*.php` routed by
  `wp-arzo-modular.php`); elFinder-based file manager; site modes redesign.
- 5.1 — Converted the standalone tool into a WordPress plugin with admin-menu
  integration and WordPress-native authentication (removed the legacy ACCESS_KEY).
