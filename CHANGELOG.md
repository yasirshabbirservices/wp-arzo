# Changelog

All notable changes to **WP Arzo – Maintenance & Administration Suite** are documented
in this file. This project loosely follows [Keep a Changelog](https://keepachangelog.com/)
and [Semantic Versioning](https://semver.org/).

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
