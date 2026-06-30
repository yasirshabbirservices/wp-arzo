# Changelog

All notable changes to **WP Arzo – Maintenance & Administration Suite** are documented
in this file. This project loosely follows [Keep a Changelog](https://keepachangelog.com/)
and [Semantic Versioning](https://semver.org/).

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
