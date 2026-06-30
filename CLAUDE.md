# CLAUDE.md

Guidance for Claude Code (and human contributors) working in this repository.

> **Starting a new session?** Read [`.claude/start-session.md`](.claude/start-session.md)
> first — it's the fast brief on current versions, what's built, what's next, and the
> conventions below in condensed form.

## Working agreement (ALWAYS do this — do not skip)

> **Before adding anything new — check for duplicates first.** Inventory what already
> exists before building a feature, tool, option, or page: grep the feature registry
> (`includes/features-registry/`), `wp_arzo_bootstrap_features()`, the Pro catalog
> (`wp_arzo_pro_feature_catalog()`), and the console tool map. If a **near-neighbour**
> exists (e.g. "Restrict REST API" vs. "REST API Authentication"), don't duplicate it —
> either extend it or name/scope the new thing so the distinction is explicit in its
> title + description. When in doubt, present a short plan first.

> **Icons everywhere (modern UI/UX).** Every feature card, dashboard page, and **left
> sidebar nav item** must carry a real `wp_arzo_icon()` SVG — never emoji, dashicons, or
> a missing glyph. New features must implement `icon()`; new sidebar pages must set an
> `icon` in `page_tabs()` (`includes/admin/class-wp-arzo-admin.php`). Add a new SVG to
> `wp_arzo_icon_paths()` (`includes/wp-arzo-icons.php`) if no fitting key exists.

On **every** change set, without being asked:

1. **Keep docs in sync** — update [`.claude/ROADMAP.md`](.claude/ROADMAP.md) (move shipped
   items to "what's already built" + adjust "next up"), this `CLAUDE.md`, the relevant
   [`.claude/skills/`](.claude/skills/), `CHANGELOG.md`, `README.md`, and `README.txt`.
2. **Bump the version** in all places (`wp-arzo.php` header `Version:` + `WP_ARZO_VERSION`,
   the version shown in `includes/wp-arzo-header.php`, `README.txt` `Stable tag`,
   `README.md` "Current version"). PHP changes need a bump so OPcache invalidates; assets
   self-bust (see `wp_arzo_get_asset_version`).
3. **Commit, tag, and push** — `git commit`, `git tag -a vX.Y.Z`, then
   `git push origin wp-plugin` and `git push origin vX.Y.Z`. Create a GitHub **release**
   for the tag when `gh` is available (otherwise note it for the maintainer).
4. **Verify** — `php -l` every touched PHP file and `node --check` touched JS before
   committing. With no live WP here, harness new engine/gating logic in the scratchpad
   (stub the few WP functions, exercise the pure logic) — see `.claude/start-session.md` §6.
5. **Cross-repo invariants** — when you add/rename a **Pro** module, also update
   `wp_arzo_pro_feature_catalog()` (free core placeholder showcase) and the Pro repo's
   `CHANGELOG.md` + version (`WP_ARZO_PRO_VERSION`). When you add a **console tool or
   operation**, map it in `wp_arzo_console_tool_map()` / `wp_arzo_console_tool_for_request()`.

### CSS conventions

- Use design tokens (the `--arzo-*` variables) for every color/space/radius/shadow.
- Reach for **`clamp()`** for fluid type/spacing/sizing and **`calc()`** for derived
  values, and introduce a CSS **variable** when a value repeats or needs theming — wherever
  it makes sense, rather than hard-coded magic numbers.

## What this is

**WP Arzo – Maintenance & Administration Suite** is a single-file-bootstrapped WordPress
plugin that exposes a standalone, dark-themed admin console for site maintenance and
emergency administration: site info, users, database, file manager, plugins, themes,
debug tools, site modes (maintenance / coming-soon / payment-required), extra options
(PHP limits, etc.), and quick-login helpers.

It is **admin-only tooling**. Every entry point must assume it is operating with full
`manage_options` privileges and must be guarded accordingly.

- Main plugin file: [`wp-arzo.php`](wp-arzo.php)
- Text Domain: `wp-arzo`
- Version constant: `WP_ARZO_VERSION` (keep in sync with the plugin header `Version:`)
- Requires: WordPress >= 5.0, PHP >= 7.2

## How it loads (request flow)

The console does **not** render inside a normal admin screen. It runs as a standalone
HTML page served through `admin-ajax.php`:

1. `wp-arzo.php` registers an admin menu page (`wp_arzo_redirect_page`) that simply
   opens `admin-ajax.php?action=wp_arzo_standalone` in a new tab.
2. `wp_ajax_wp_arzo_standalone` → `wp_arzo_handle_standalone()` checks
   `current_user_can('manage_options')` and includes
   [`includes/wp-arzo-modular.php`](includes/wp-arzo-modular.php).
3. `wp-arzo-modular.php` is the **router**:
   - Early-exits for AJAX/file operations (downloads, `view_file`, `save_file`, plugin
     toggles, theme activation, DB pages, debug ops, site-mode ops) — these must run
     **before** any HTML is emitted.
   - Otherwise includes [`includes/wp-arzo-header.php`](includes/wp-arzo-header.php)
     (opens `<html>`, prints nav, handles login POSTs + the users-page JSON endpoint),
     then routes the current `tab` to a file in [`features/`](features/).
4. The page closes in `wp-arzo-modular.php`, which injects `wpArzoConfig` and loads
   [`assets/js/wp-arzo.js`](assets/js/wp-arzo.js).

### Routing keys

| `tab` value      | Feature file                  |
|------------------|-------------------------------|
| `info`           | `features/site-info.php`      |
| `users`          | `features/users.php`          |
| `database`       | `features/database.php`       |
| `files`          | `features/files.php`          |
| `plugins`        | `features/plugins.php`        |
| `themes`         | `features/themes.php`         |
| `debug`          | `features/debug.php`          |
| `site_modes` / `maintenance` | `features/site-modes.php` |
| `extra_options`  | `features/extra-options.php`  |
| `login`          | `features/login.php`          |

`tab=ajax` is a legacy alias: several handlers accept both `tab=<feature>` and
`tab=ajax` plus an `operation` param. When adding an operation, wire it in **both** the
router (`wp-arzo-modular.php`) and the feature file.

**Console tool toggles:** each console tab (except `info`) can be individually
enabled/disabled from the dashboard — registered as `WP_Arzo_Feature_Console_Tool`
instances (group `advanced_tools`) in
[`includes/features-registry/class-features-advanced-tools.php`](includes/features-registry/class-features-advanced-tools.php).
The router gates disabled tools at three points: the early AJAX/file/DB operation guard
(`wp_arzo_console_tool_for_request()` → JSON 403), the page-view route (notice instead of
the feature file), and the console nav (`wp-arzo-header.php`). **When you add a new console
tab or `operation`, also map it in `wp_arzo_console_tool_map()` /
`wp_arzo_console_tool_for_request()`** so the gating stays complete.

## Front-end ↔ back-end contract

`assets/js/wp-arzo.js` issues `fetch()` calls to `admin-ajax.php` with
`action=wp_arzo_standalone&tab=...&operation=...` and expects **JSON** back with a
`success` boolean. The single most common class of bug in this codebase is a **shape
mismatch**: the JS reads one set of keys (`user.ID`, `plugin.is_active`, …) while the
PHP returns differently named keys. When you touch either side, verify the exact field
names and types (string vs array) on both ends.

AJAX handlers must:
- `header('Content-Type: application/json');`
- `echo json_encode($response);`
- `exit;` (never fall through into HTML)

## Conventions

- **Assets:** never hard-code asset URLs. Use
  `wp_arzo_get_asset_url('assets/css/wp-arzo.css')` — it prefers `*.min.*` in
  production and appends a `filemtime()` cache-buster. Colors come from
  [`assets/css/design-tokens.css`](assets/css/design-tokens.css); see
  [`.claude/design.md`](.claude/design.md).
- **Options/transients:** prefix with `maintenance_tool_…` or `wp_arzo_…`. Use the
  Options/Transients API (not raw SQL) so object caches stay consistent. Register any
  new long-lived option in the uninstall cleanup in `wp-arzo.php`.
- **Versioning:** bump the header `Version:` **and** `WP_ARZO_VERSION` together, update
  the version string shown in `wp-arzo-header.php`, and add a `CHANGELOG.md` entry.

## Security baseline (do not regress)

Because this tool can edit files, run DB actions, switch themes/plugins, create admins,
and log users in, every state-changing entry point must:

1. Re-check capability: `if (!current_user_can('manage_options')) { … exit; }` — do not
   assume the outer `admin-ajax` gate is enough once code is reachable via multiple
   routes.
2. Use a **nonce** for state-changing requests (`wp_create_nonce` / `check_admin_referer`
   / `wp_verify_nonce`). Several legacy handlers are missing this — when you touch one,
   add it.
3. `wp_unslash()` + sanitize input early; escape output late (`esc_html`, `esc_attr`,
   `esc_url`).
4. For the file manager, **constrain paths to the WordPress install** (realpath +
   prefix check) before reading/writing/downloading. Never trust a raw path from the
   client.
5. Use `$wpdb->prepare()` for any SQL.

## Lifecycle

- Activation: `wp_arzo_activate` (rewrite rule for the emergency endpoint, store
  version, invalidate OPcache).
- Deactivation: `wp_arzo_deactivate` (disable maintenance mode, clear transients).
- Uninstall: `wp_arzo_uninstall` (remove all `maintenance_tool_*` + `wp_arzo_*` options,
  transients, and the generated `arzo-safe.php`). Keep this list complete.
- Emergency endpoint: `/wp-arzo/emergency/` rewrites to `wp-arzo-emergency/index.php`.

## When fixing or adding features

1. Trace the full path: JS `fetch` → router (`wp-arzo-modular.php`) → header
   (`wp-arzo-header.php`) → feature file. A handler that exists but isn't routed will
   silently 404/return HTML.
2. Confirm the JSON field names/types match on both sides.
3. Add capability + nonce checks to any new state-changing op.
4. Keep `README.md`, `README.txt` (`Stable tag`), and `CHANGELOG.md` in sync with the
   version bump.

## Two surfaces (important)

WP Arzo now has **two** distinct UIs — know which one you're touching:

1. **The native admin dashboard** (`WP Arzo` menu) — the **feature manager**. This is the
   modern surface and where new site-enhancement features go. It is powered by a
   **feature registry**:
   - `includes/class-wp-arzo-feature.php` — abstract `WP_Arzo_Feature` (id / title / group
     / tier / icon / settings_schema / boot).
   - `includes/class-wp-arzo-feature-registry.php` — registers modules, persists
     enable-state (`wp_arzo_features`) + settings (`wp_arzo_settings`), boots only enabled
     features, and fires `wp_arzo_before_feature_toggle` / `wp_arzo_feature_enabled` /
     `…_disabled` / `…_toggled`.
   - `includes/features-registry/*.php` — the feature modules. Add one here and register it
     in `wp_arzo_bootstrap_features()` (wp-arzo.php). See the
     [`wp-arzo-feature-module`](.claude/skills/wp-arzo-feature-module/SKILL.md) skill.
   - `includes/admin/class-wp-arzo-admin.php` — renders the dashboard (toggle grid),
     schema-driven settings, and the feature-owned pages — **Backups, Email Log, Snippets,
     Media Cleanup, Activity Log, REST API Auth, Roles, Import/Export** — plus their AJAX
     handlers (all capability + nonce gated). Navigation is a **left sidebar**
     (`render_shell_open()`/`render_sidenav()`, from `page_tabs()`); on the dashboard the
     sidebar also lists the feature categories as a grid filter. Every page-owning feature
     is registered in `page_features()` (gates the submenu + sidebar entry behind its
     toggle). Dashboard assets: `design-tokens.css` → `wp-arzo-components.css` →
     `wp-arzo-admin.css`, plus `wp-arzo-components.js` + `wp-arzo-admin.js`.
   - Add-on hooks: `wp_arzo_register_features` (Pro registers modules) and
     `wp_arzo_feature_is_available` (license gate). The gate defers to
     `wp_arzo_is_pro_active()` (filter `wp_arzo_pro_active`); the locked-feature CTA uses
     `wp_arzo_pro_upgrade_url()`. Licensing provider: **Freemius**.
   - **Pro showcase placeholders:** `includes/features-registry/class-feature-pro-placeholders.php`
     advertises the Pro catalog (locked **PRO** cards) when the add-on isn't installed.
     `wp_arzo_register_pro_placeholders()` runs **after** the `wp_arzo_register_features`
     action and only registers a placeholder for an id the real Pro module didn't register
     (no duplicates). **When you add or rename a Pro module, update `wp_arzo_pro_feature_catalog()`
     to match** (id/title/group/icon/description).
   - **WP Arzo Pro** is a separate **private** plugin/repo (`wp-arzo-pro`) — it boots after
     the core, flips `wp_arzo_pro_active` when licensed, and registers its premium modules
     via `wp_arzo_register_features`. Never put Pro code or Freemius secrets in this public
     repo.

2. **The standalone console** (`Advanced Tools` submenu → `admin-ajax.php`) — the legacy
   power-tools (Site Info, Users, Database, Files, Debug, Site Modes, Extra Options, Quick
   Login), documented in the routing section above. Still the right place for the heavy
   server-admin tools.

## Backups

`includes/class-wp-arzo-backup-manager.php` — streaming JSONL database snapshots
(`options` | `full_db`), restore (safety-snapshot-first, TRUNCATE + re-insert), delete,
retention. Stored in a web-protected `uploads/wp-arzo-backups/`. The **Automated
Snapshots** feature hooks `wp_arzo_before_feature_toggle` to snapshot before a toggle.

## Design system & components

One token source of truth: `assets/css/design-tokens.css` (canonical `--arzo-*` + legacy
aliases). Reusable `wpa-` components in `assets/css/wp-arzo-components.css` +
`assets/js/wp-arzo-components.js`. Icons via `wp_arzo_icon()`
(`includes/wp-arzo-icons.php`) — real SVGs, never emoji/default glyphs. See
[`.claude/design.md`](.claude/design.md). New UI uses toggles (not checkboxes),
`.wpa-select` (not native selects), and composes components rather than bespoke CSS.

See [`.claude/skills/`](.claude/skills/) and [`.claude/ROADMAP.md`](.claude/ROADMAP.md) for
playbooks and the product roadmap.
