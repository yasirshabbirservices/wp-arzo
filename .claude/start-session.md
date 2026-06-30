# WP Arzo — Start-of-Session Brief

> Read this first when starting a new session. It's the fast path to "where things are".
> For depth, follow the links: [`CLAUDE.md`](../CLAUDE.md) (architecture + working
> agreement), [`ROADMAP.md`](ROADMAP.md) (product plan + progress log),
> [`design.md`](design.md) (design system), [`skills/`](skills/) (playbooks).

## 1. What this is

**WP Arzo** is a freemium all-in-one WordPress **administration + site-enhancement suite**
(a better-UX competitor to ASE / wpase.com). Two surfaces:

1. **Feature Manager dashboard** (native `WP Arzo` admin menu) — the modern surface. A
   registry-driven toggle grid + schema-driven settings. New features go here.
2. **Advanced Tools console** (standalone page via `admin-ajax.php`) — the legacy
   power-tools (Site Info, Users, Database, Files, Plugins, Themes, Debug, Site Modes,
   Extra Options, Quick Login).

## 2. Repos & versions (keep both in sync)

| Repo | Path | Branch | Visibility | Current |
|------|------|--------|------------|---------|
| **Free core** | `D:\Github\wp-arzo` | `wp-plugin` | public | **v6.47.0** |
| **Pro add-on** | `D:\Github\wp-arzo-pro` | `main` | private | **v1.10.1** |

- Pro registers its modules into the free core's registry via the `wp_arzo_register_features`
  action and is gated by `wp_arzo_pro_active`. **Never** put Pro code / Freemius secrets in
  the public repo.
- Pro **refuses to activate** without the free core (activation guard + runtime
  self-deactivate). Free core **advertises** the Pro catalog as locked placeholder cards.

## 3. Current state (what's built)

**Free core (6.29.0):** ~48 feature modules across Utilities, Core controls, Content,
Media, Marketing/SEO, Email (SMTP + log), Security, Branding (custom login), Developer
(Code Snippets), Backup (local snapshots + scheduled), Ops (Activity Log), plus:
- **REST API Authentication** (`rest_api_auth`) — issuable/revocable API keys + Basic Auth
  via `determine_current_user` (complements, not duplicates, "Restrict REST API"). Page +
  keys table in `class-wp-arzo-admin.php`; engine in `class-feature-rest-api-auth.php`.
- **Role Manager** (`role_manager`) — roles/caps editor + add/clone/delete via the Roles API.
- **Config Import/Export** (`config_io`) — versioned JSON of features+settings+snippets,
  safety-snapshot before import.
- **Left sidebar navigation** (`render_shell_open()`/`render_sidenav()` in
  `class-wp-arzo-admin.php`) — page tabs went vertical, collapsible (icon-rail) + scrollable;
  the dashboard sidebar also has a **category filter** that scopes the feature grid.
- **Console parity (v6.29–6.33):** branded scrollbar plugin-wide; the console uses the same
  `.wpa-brandbar` header (GitHub icon); hardened **emergency recovery**; **Temporary Login
  links** replaced Quick Login; **Database = bundled AdminNeo** (WP-gated `loader.php`, guard
  in `adminneo.php` — re-apply on update; see `assets/libs/adminneo/ATTRIBUTION.md`).
- **Setup Wizard + 7 presets** (Essentials / Velocity / Fortress / Creator / Growth /
  Command Center / The Works) — `includes/admin/class-wp-arzo-setup-wizard.php`.
- **Pro showcase placeholders** — `includes/features-registry/class-feature-pro-placeholders.php`.
- **Advanced Tools per-tool toggles** + page gating —
  `includes/features-registry/class-features-advanced-tools.php`.
- Feature **admin pages gate** behind their toggle (Email Log / Snippets / Activity Log /
  Backups / Media Cleanup).

**Pro add-on (1.10.1) — 18 modules:** Meta/TikTok/LinkedIn/Pinterest/Snapchat/X/Bing pixels,
GA4 / GTM / Google Ads, **Content Types (CPT/CCT)**, **Custom Fields (meta boxes)**,
**Media Folders**, **Admin Branding & Custom Dashboard**, **Redirects & 404 Monitor**,
**Cron Manager**, **API Email Providers**, **Off-site Backups: FTP**.

## 4. What's next (priority order)

1. **Advanced Audit Log (Pro)** — extend the free Activity Log with a real DB table,
   retention windows, CSV export, advanced filters.
2. **2FA (Pro)** — TOTP + recovery codes. ⚠️ Modifies the login flow; build strictly
   opt-in with a recovery escape. **Held while the maintainer is live-testing** — confirm
   before shipping.
3. **S3-compatible remote backup + remote restore (Pro)** (WPvivid ref).
4. **Notifications (Pro)** — Slack / Discord / webhook on events.
5. **AI / MCP layer** — MCP server + Command Center (registry-backed, capability/nonce
   gated, suggestion-first).
6. **Freemius wiring (LAST step)** — needs the maintainer's Pro **Plugin ID + Public Key**;
   fill `wp-arzo-pro/includes/freemius-init.php`. Keep the secret out of any repo.

## 5. Working agreement (do every change set — from CLAUDE.md)

1. **Keep docs in sync:** `ROADMAP.md`, `CLAUDE.md`, relevant `skills/`, `CHANGELOG.md`,
   `README.md`, `README.txt` (and the Pro repo's `CHANGELOG.md`).
2. **Bump the version** in all places (free core: `wp-arzo.php` header + `WP_ARZO_VERSION`,
   `includes/wp-arzo-header.php`, `README.txt` `Stable tag`, `README.md`; Pro: header +
   `WP_ARZO_PRO_VERSION`). See the [`wp-arzo-release`](skills/wp-arzo-release/SKILL.md) skill.
3. **Commit, tag `vX.Y.Z`, push** the branch + tag. (Pro pushes to `main`.)
4. **Verify:** `php -l` every touched PHP, `node --check` touched JS.
5. **When adding a Pro module:** also add it to `wp_arzo_pro_feature_catalog()` in the free
   core (placeholder showcase) so it appears for free users.
6. **When adding a console tool/operation:** map it in `wp_arzo_console_tool_map()` /
   `wp_arzo_console_tool_for_request()` so the per-tool gating stays complete.
7. **No duplicates — check first.** Grep the registry / bootstrap / Pro catalog before
   adding anything; differentiate near-neighbours explicitly (see CLAUDE.md).
8. **Icons everywhere** — every feature card, page header, and **sidebar nav item** needs
   a real `wp_arzo_icon()` SVG (new features implement `icon()`; new pages set an `icon` in
   `page_tabs()`).

## 6. Verification pattern (use it)

There's no live WP in this environment. The reliable check is a **PHP harness in the
scratchpad** that stubs the few WP functions a class needs and exercises its pure logic
(storage, parsing, gating, sanitizing). This caught real bugs this project (recursion
fatal, regex-source mangling, undefined keys) before shipping. Always harness new
engine/gating logic, then `php -l`. For render-level fatals, a render harness that
includes the file with WP stubs works too.

## 7. Gotchas / environment

- **Platform:** Windows + PowerShell primary; Bash tool available. `cd` can trigger a
  permission prompt — prefer absolute paths.
- **`gh` is NOT installed** — tags are pushed but GitHub **releases** must be created
  manually in the web UI (or install `gh`). Tags are current through `v6.26.0` (core) and
  `v1.10.1` (Pro).
- **LF→CRLF git warnings are benign** (Windows checkout) — ignore.
- No `.min.*` assets exist; assets self-bust via `wp_arzo_get_asset_version()`.
- Design tokens load order: `design-tokens.css` → `wp-arzo-components.css` → page CSS.
  Use `--arzo-*` tokens, `clamp()`/`calc()`/CSS vars, real SVG icons via `wp_arzo_icon()`
  (never emoji/native glyphs), modern toggles + `.wpa-select` (never native controls).
- **Branding:** `assets/wp-arzo-glyph.svg` (menu + emergency, transparent dark-bg) and
  `assets/wp-arzo-icon.svg` (brand bars). The old YS PNG was removed.

## 8. Security baseline (never regress)

Every state-changing entry point: re-check `current_user_can('manage_options')`, verify a
**nonce**, `wp_unslash()` + sanitize early, escape late, `$wpdb->prepare()` for SQL, and
constrain file-manager paths to the install (realpath + prefix). AJAX handlers send JSON
and `exit`. See the [`wp-arzo-security`](skills/wp-arzo-security/SKILL.md) skill.
