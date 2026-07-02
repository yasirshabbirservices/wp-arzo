# WP Arzo – Maintenance & Administration Suite

WP Arzo is an all‑in‑one **administration + site‑enhancement suite** for WordPress, built
around two surfaces:

1. A modern, native **Feature Manager dashboard** — a registry‑driven toggle grid of
   site‑enhancement features (performance, security, email/SMTP, marketing, content,
   media, backups, developer tools, branding) with schema‑driven settings, a **Setup
   Wizard** with one‑click presets, and a freemium **Pro** add‑on.
2. The original standalone, dark‑themed **Advanced Tools console** for hands‑on
   maintenance: site info, users, database, files, plugins, themes, site modes, debug
   tools, extra options (PHP limits), and quick‑login helpers.

This repository is the **free core** (public, GPL) and is intended for production use on
shared hosting, VPS, cloud, and enterprise WordPress setups. Everything is
**administrators‑only** and authenticates with your existing WordPress session. The paid
**WP Arzo Pro** add‑on lives in a separate private repo and registers extra modules into
the same dashboard.

- **Current version:** 6.95.0 (see [CHANGELOG.md](CHANGELOG.md))
- **Requires:** WordPress ≥ 5.0, PHP ≥ 7.2
- **Architecture & conventions for contributors / AI agents:** [CLAUDE.md](CLAUDE.md)
- **Design system:** [.claude/design.md](.claude/design.md)

## Feature Manager & Setup Wizard

The native **WP Arzo** admin menu is the modern surface: a grouped grid of ~45 free
feature modules you enable/disable with modern toggles, each with schema‑driven settings.
A **left sidebar** carries the page navigation (Dashboard, Email, Backups, Snippets, Media
Cleanup, Activity Log, Login Security, Advanced Tools) and a live **category filter** that
scopes the feature grid — so the dashboard scales as more features land. The **Email** page
is a single SureMail-style hub with **Connections / Logs / Settings** tabs.
A **Setup Wizard** (first run, and always under *WP Arzo → Setup Wizard*) applies curated
presets in one click — **Essentials, Velocity, Fortress, Creator, Growth, Command Center,
The Works** — which only *enable* features (never disable what you've set). Pro modules are
shown as locked **PRO** cards so you can see the full suite even on the free plan.

Highlights of the free core: a **multi-provider email connections manager** (SMTP + Gmail/
Outlook/SES/SendGrid/Brevo/Mailgun/Postmark… with primary + fallback) and email log, a
**syntax-highlighting Code Snippets editor**, Activity Log, local
backups + scheduled snapshots, auto WebP, SVG upload, custom login page, dozens of
performance/security/admin tweaks, per‑tool toggles for the console, Media Cleanup,
**REST API Authentication** (issuable API keys + Basic Auth), a **Role Manager**
(capability editor + custom roles), and **Config Import/Export** (move your WP Arzo setup
between sites as versioned JSON).
See [CHANGELOG.md](CHANGELOG.md) and [.claude/ROADMAP.md](.claude/ROADMAP.md) for the full,
current list. Pro adds marketing pixels, GA4/GTM/Ads, a CPT/CCT + Custom Fields builder,
Media Folders, Admin Branding, Redirects/404, Cron Manager, and
off‑site backups.

## Advanced Tools console

| Tab | What it does |
|-----|--------------|
| **Site Info** | WordPress / PHP / MySQL / server / disk usage at a glance |
| **Users** | Paginated user list; create, delete, and log in as any user |
| **Database** | Full database manager (AdminNeo, bundled) — browse/edit rows, SQL, export/import — WP-gated and auto-connected |
| **Files** | Full file manager (elFinder) rooted at the WordPress install |
| **Plugins** | Activate / deactivate via toggle; upload a plugin ZIP |
| **Themes** | Switch the active theme; upload a theme ZIP |
| **Debug** | Toggle `WP_DEBUG`/`WP_DEBUG_LOG`/`WP_DEBUG_DISPLAY`/`SCRIPT_DEBUG`/`SAVEQUERIES`; view the debug log |
| **Site Modes** | Maintenance / Coming Soon / Payment Required pages + emergency recovery script |
| **Extra Options** | Set PHP limits in `wp-config.php`, `.htaccess`, or `php.ini` |
| **Quick Login** | Create a temporary admin, or generate a one‑time emergency access link |

## How it works

1. The plugin registers a **WP Arzo** admin‑menu page that opens the console in a new tab
   at `admin-ajax.php?action=wp_arzo_standalone`.
2. `wp_arzo_handle_standalone()` enforces `current_user_can('manage_options')`, then
   includes [`includes/wp-arzo-modular.php`](includes/wp-arzo-modular.php).
3. `wp-arzo-modular.php` is the router: it short‑circuits AJAX/file operations, includes
   the shared header, and routes the current `tab` to a file in
   [`features/`](features/).
4. Front‑end interactivity lives in [`assets/js/wp-arzo.js`](assets/js/wp-arzo.js) plus
   inline scripts in each feature file; they call `admin-ajax.php` and exchange JSON.

```
wp-arzo.php                 → bootstrap, constants, lifecycle, admin menu, standalone gate
includes/
  wp-arzo-modular.php       → router + asset loading
  wp-arzo-header.php        → <head>/nav, login POST handling, file download, shared helpers
  maintenance-frontend.php  → public maintenance / coming‑soon / payment page
features/*.php              → one file per tab (site‑info, users, database, files, …)
assets/css/design-tokens.css→ color palette / design tokens
assets/js/wp-arzo.js        → shared front‑end behavior
wp-arzo-emergency/          → standalone recovery endpoint (/wp-arzo/emergency/)
```

See [CLAUDE.md](CLAUDE.md) for the full routing table and the front‑end ↔ back‑end JSON
contract.

## Security model

The console can edit files, run SQL, switch plugins/themes, create admins, and rewrite
server config — so it is gated to `manage_options` and every state‑changing action is
CSRF‑protected with a WordPress nonce. When adding or changing a feature:

- Re‑check capability at the handler, not just at the outer gate.
- Add a nonce to any state‑changing request (forms use `wp_nonce_field()`; AJAX uses the
  shared `wp_arzo_ajax` nonce verified with `check_ajax_referer()`).
- `wp_unslash()` + sanitize input early; escape output late.
- Confine file paths to the WordPress install; never write raw user input into config
  files or SQL.

## Development checklist for future updates

### Assets

- **Always load CSS/JS via the asset helper**  
  Use `wp_arzo_get_asset_url( 'assets/css/wp-arzo.css' )` / `wp_arzo_get_asset_url( 'assets/js/wp-arzo.js' )` instead of hard‑coding URLs or query parameters.
- **Rely on `filemtime()` for cache‑busting**  
  The asset helper already uses `filemtime()` with a safe fallback to `WP_ARZO_VERSION`. Do not add manual `?v=...` query strings.
- **Use minified builds for production**  
  If you ship minified assets, name them `*.min.css` / `*.min.js`. The helper automatically prefers these when `WP_ARZO_DEBUG` is `false`.
- **Keep new assets under `assets/`**  
  Place styles under `assets/css/` and scripts under `assets/js/`, and reference them only via the asset helper.

### PHP & features

- **Keep versions in sync**  
  Ensure the plugin header `Version:`, the `WP_ARZO_VERSION` constant, the version shown in `includes/wp-arzo-header.php`, and `README.txt`'s `Stable tag` always match on release — and add a `CHANGELOG.md` entry.
- **Prefix all options/transients cleanly**  
  Use clear prefixes such as `maintenance_tool_...` or `wp_arzo_...` for any new options/transients so they are easy to track and clean up.
- **Register cleanup for new persistent data**  
  When you introduce new long‑lived options or transients, add them to the uninstall cleanup logic so they are removed when the plugin is uninstalled.
- **Avoid raw SQL for options/transients**  
  Use `get_option()`, `update_option()`, `delete_option()`, `set_transient()`, and `delete_transient()` so object caches (Redis/Memcached) stay consistent.

### Debugging & safety

- **Use the Debug tab to inspect the active instance**  
  The Debug screen exposes:
  - The plugin file and directory actually loaded.
  - Header vs stored version (`WP_ARZO_VERSION` vs `wp_arzo_version` option).
  - Whether OPcache is enabled and whether the plugin file is cached.
- **Verify OPcache behavior after updates**  
  On plugin updates, confirm that the compiled timestamp in the Debug screen is newer than (or aligned with) the file `mtime`. If not, investigate host‑level OPcache configuration.
- **When adding new admin pages, enqueue safely**  
  For classic WP admin pages (non‑standalone), enqueue assets via `admin_enqueue_scripts` using unique handles and the asset helper, e.g.:
  - `wp_enqueue_style( 'wp-arzo-admin', wp_arzo_get_asset_url( 'assets/css/wp-arzo.css' ), array(), null );`
  - `wp_enqueue_script( 'wp-arzo-admin', wp_arzo_get_asset_url( 'assets/js/wp-arzo.js' ), array( 'jquery' ), null, true );`
- **Prefer capability + nonce checks on all entry points**  
  Guard new AJAX actions or tools with appropriate capability checks (e.g. `current_user_can( 'manage_options' )`) and a nonce, to match the rest of the plugin.

## License

Proprietary. See [LICENSE](LICENSE).
