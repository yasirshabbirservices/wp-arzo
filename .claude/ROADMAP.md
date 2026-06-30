# WP Arzo — Product & Engineering Roadmap

> Status: proposed plan. Default architecture choices are marked **[default]** — any can
> be changed. We build **feature by feature**; each feature starts from a reference plugin
> the maintainer shares, and we ship a better/faster/more-accessible version.

## 0. Progress log — what's already built

Legend in the catalog (§4): **[✅]** shipped · **[F]** planned free · **[P]** planned Pro.

### Shipped
- **v6.5** — security hardening + broken-feature fixes (CSRF nonces, path-traversal &
  config-injection fixes, emergency-tool hardening incl. login throttle + CSP).
- **v6.6 — Phase 0 (design system foundation):** single-source design tokens, reusable
  `wpa-` component library (button, modern toggle, custom select, badge/status, card,
  field, toast), SVG icon system (`wp_arzo_icon`), global a11y layer.
- **v6.6 — Phase 1 (dashboard + registry):** `WP_Arzo_Feature` base + registry
  (enable-state, settings, lifecycle hooks), native full-dark **Feature Manager** dashboard
  (toggle grid, search, schema-driven settings), branded header, logo menu icon,
  cross-promotion area. Standalone console moved under **Advanced Tools**.
- **v6.6 — Phase 2 (16 free feature modules):**
  - Core: Disable Comments, Disable Gutenberg, Disable RSS Feeds, Disable Embeds.
  - Security: Disable XML-RPC, Restrict REST API, Disable Theme/Plugin Editor, Block User
    Enumeration.
  - Utilities: Hide Admin Bar, Disable Dashboard Widgets, Disable Emojis, Disable Self
    Pingbacks.
  - Content/Dev: Revisions Control, Heartbeat Control.
  - Marketing/SEO: Manage robots.txt, Manage ads.txt.
- **v6.6 — Backup engine v1:** streaming DB snapshots (options/full_db), restore (safety-
  snapshot-first), delete, retention, **Automated Snapshots** before any feature toggle;
  Backups admin page.
- **v6.6.1** — reliable asset cache-busting (no manual version bump needed for CSS/JS).

- **v6.7.0** — freemium gate in the free core: `wp_arzo_pro_active` /
  `wp_arzo_is_pro_active()` / `wp_arzo_pro_upgrade_url()`; pro-tier features lock with an
  "Unlock" CTA until Pro is active.
- **WP Arzo Pro** (private repo `wp-arzo-pro`, published):
  - v1.0.0 — add-on scaffold + Freemius-ready init (inert until keys) + Meta Pixel.
  - v1.1.0 — **marketing pixel suite**: Meta Pixel, TikTok Pixel, LinkedIn Insight,
    Pinterest Tag, Google Analytics 4 (gtag), Google Tag Manager (all Pro, group
    "Marketing & Tracking"; IDs sanitized; `<noscript>` fallbacks).
- **v6.8.0** — **Email (free):** SMTP delivery (phpmailer_init config + force-from) and
  Email Log (capped option store, sent/failed, WP Arzo → Email Log page with clear).
  Added `password`/`email` settings-field types (passwords never re-rendered; blank keeps
  the saved secret). Tiering confirmed: SMTP + email log + local snapshots + custom login
  = **Free**; only cloud/remote backup destinations = Pro.

### Not yet built (next up)
- Wire **Freemius** for real (add SDK + plugin IDs/keys in the Pro repo's
  `includes/freemius-init.php`) so the license actually enforces `wp_arzo_pro_active`.
- Backup v2 (bounded file snapshots + scheduled/cron) → cloud remotes (FTP/S3 →
  Drive/Dropbox/pCloud) + encryption.
- Marketing (pixels/GA4/GTM/GSC), Email (SMTP + logs), Security suite (2FA, audit log,
  limit login, custom login URL), Branding (login/dashboard/admin), Builders (CPT/CCT,
  media manager), Code Snippets, AI/MCP layer, Ops/monitoring modules.

## 1. Vision

Turn WP Arzo from an admin maintenance console into an **all-in-one WordPress
administration + site-enhancement suite** with a clean feature-manager dashboard — a
direct competitor to [WP ASE / wpase.com](https://www.wpase.com/) and Admin Site
Enhancements, but with stronger UI/UX, a real component system, and a paid Pro addon.

Principles (non-negotiable):

- **Security first** — capability + nonce + sanitize/escape on every state change (see
  [`.claude/skills/wp-arzo-security`](skills/wp-arzo-security/SKILL.md)).
- **Accessibility** — WCAG 2.2 AA: keyboard operable, focus-visible, ARIA roles/labels,
  `prefers-reduced-motion`, real labels on every control.
- **Modern, branded UI/UX** — one design system ([`design.md`](design.md)), custom
  toggles/selects/everything (never default browser controls), real SVG icons.
- **Reusable components** — build once, reuse everywhere (pagination, tables, modals…).
- **Performance** — features are opt-in; disabled features load no code on the front end.
- **Freemium** — free core + Pro addon; Pro is just more registered modules + a license
  gate.

## 2. Architecture

### 2.1 Tech approach **[default: vanilla PHP + CSS + web-components]**

- PHP renders pages and server components; a small **vanilla-JS component layer**
  (custom elements / web components) powers rich widgets (data tables, custom selects,
  modals, toasts). No React, no build step required. (Optional `wp-scripts` later only if
  a feature truly needs it.)
- All UI composes shared components; **no bespoke inline styles** in feature files.

### 2.2 Admin surface **[default: dashboard + toggle grid; console as Advanced Tools]**

- New native **WP Arzo** wp-admin menu with:
  - **Dashboard** — feature-manager: a grouped toggle grid (ASE-style) to enable/disable
    every feature; search/filter; per-feature "Settings" link + status chip.
  - **Per-feature settings pages** — only shown for enabled features.
  - **Advanced Tools** — the existing standalone power-console (Site Info, Users,
    Database, Files, Debug, Site Modes, Extra Options, Quick Login), restyled onto the
    shared components and reachable in its own tab as today.
- The standalone console + emergency endpoint remain (they must work even when wp-admin
  is broken).

### 2.3 Feature-registry pattern (the core of the suite)

Every feature is a self-contained module:

```
class WP_Arzo_Feature_Disable_Comments extends WP_Arzo_Feature {
    public function id(): string        { return 'disable_comments'; }
    public function group(): string     { return 'utilities'; }
    public function tier(): string      { return 'free'; }          // free | pro
    public function title(): string     { return 'Disable Comments'; }
    public function icon(): string      { return 'comment-slash'; } // icon registry key
    public function settings_schema(): array { /* fields */ }
    public function boot(): void        { /* add_action/add_filter — only when enabled */ }
}
```

- A central **registry** discovers modules, renders the toggle grid from their metadata,
  persists enabled-state in one option (`wp_arzo_features`), persists settings under
  `wp_arzo_settings[<id>]`, and only calls `boot()` for **enabled** features.
- The **Pro addon** is a separate plugin that registers additional modules into the same
  registry and unlocks Pro tiers behind a license check. Free shows Pro features as
  locked upsell chips.
- Benefits: consistent UX, trivial enable/disable, clean free/pro split, testable units.

### 2.4 Data & conventions

- Options: `wp_arzo_features` (enabled map), `wp_arzo_settings` (per-feature settings),
  plus existing `maintenance_tool_*`. Register all in uninstall cleanup.
- Reuse the established nonce families (`wp_arzo_ajax` for fetch; per-form actions).
- Settings API or a thin schema-driven renderer that outputs our components from
  `settings_schema()` (one renderer → consistent, accessible forms everywhere).

## 3. Design system & component library

Fix the foundation first (see §6, Phase 0). Then build, in priority order:

| Component | Notes |
|-----------|-------|
| **Design tokens** | ONE source of truth. Reconcile `design-tokens.css` (`--arzo-*`) with the palette `wp-arzo.css` actually uses; load it everywhere (console, dashboard, emergency, maintenance page). Add spacing/typography/elevation/z-index scales. |
| **Icon system** | Curated inline-SVG set (sprite or PHP helper) — real icons for every status/action. No emoji, no default browser glyphs. Map semantic names → SVG. |
| **Toggle** | **Modern** accessible switch (`role="switch"`, `aria-checked`, keyboard, focus-visible, smooth motion, semantic on/off color + optional state icon). NOT the flat ASE style — pill track, soft shadow, accent glow when on. Replaces all checkboxes where on/off. |
| **Custom Select** | Branded listbox (`role="listbox"`/`option`, type-ahead, keyboard) replacing native `<select>`. |
| **Pagination** | Single component reused by Users/DB/Plugins/Themes/Media/Logs. |
| **DataTable** | Sortable/filterable table shell with states (loading/empty/error) + row actions. |
| **Card / Panel** | Section container with header/title/icon/actions. |
| **Modal / Lightbox** | Focus-trap, ESC, backdrop close, ARIA dialog. |
| **Tabs** | Accessible tablist for settings sections. |
| **Toast/Notice** | Success/error/info/warning with icons; ARIA live region. |
| **Badge / Status** | State chips (Active/Inactive/Pending/Error…) with semantic color + icon. |
| **Field** | Label + control + help + error wrapper used by the schema renderer. |
| **Empty/Loading/Error states** | Standard illustrations + copy for every async surface. |

All components: keyboard operable, `:focus-visible` rings, `prefers-reduced-motion`,
AA contrast, RTL-friendly, branded.

## 4. Feature catalog

Grouped like ASE plus the requested additions. **[F]** = Free, **[P]** = Pro
(default split; adjustable).

### Utilities / Admin tweaks (mostly Free)
- Page & Post duplication [F]
- Media replacement [F]
- SVG upload (sanitized) [F]
- External permalinks [F]
- Missed-schedule fix [F]
- Enhance list tables (extra columns, IDs, thumbs) [F]
- Hide admin notices [F]
- Clean up / customize admin bar [F]
- Admin menu organizer (reorder/hide) [F→P advanced]
- Disable dashboard widgets [F]
- Hide admin bar (by role) [F]
- Last-login column + login activity [F]
- `<head>/<body>/<footer>` custom code injection [F]
- Custom body class [F]
- Custom admin & frontend CSS [F]
- Maintenance mode (existing, restyled) [F]

### Content & modeling
- **Custom Post Types** builder (JetEngine-style UX) [P]
- **Custom Content Types / custom fields (meta boxes)** [P]
- Custom taxonomies [P]
- Revisions control, Heartbeat control, image upload control [F]

### Media management
- **Advanced media manager (HappyFiles-style folders/virtual taxonomy, drag-drop,
  bulk, filters)** [P] — built on the DataTable/Tree + Modal components.
- Media replacement [F], SVG upload [F], image optimization hooks [P]

### Marketing / Analytics / Tracking (mostly Pro)
- Google Analytics (GA4) + **Google Search Console** verify [P]
- **Google Tag Manager** container [P]
- **Meta (Facebook) Pixel** [P], **TikTok Pixel** [P], **LinkedIn Insight** [P],
  Pinterest/X/Snap/Google Ads — pluggable **pixel framework** (one engine, many
  providers; consent-mode aware) [P]
- Manage `ads.txt` / `robots.txt` [F]
- Crawl optimizations / SEO sitemap toggles [F]

### Email
- **SMTP delivery** (multi-provider) [✅ Free]
- **Email log** (sent/failed, recipient/subject, errors) [✅ Free]; advanced analytics
  (open/click, resend, search/export) [P]

### Security
- Limit login attempts [✅ Free], Disable XML-RPC [✅], Block user enumeration [✅],
  Disable REST for guests [✅], Disable file editor [✅]
- **Custom login URL / hide wp-login** [✅ Free]
- 2FA, login firewall, activity/audit log, password policies [P]
- Password-protect site/pages [F basic / P advanced]

### Branding / White-label admin
- **Custom login page** (logo, bg/form/text/accent colors, custom CSS) [✅ Free]; custom
  login URL [F, next]; full white-label designer + advanced layouts [P]
- **Custom admin UI/UX** (color schemes, fonts, menu styling) [P]
- **Custom dashboard page** (replace all default boxes with branded widgets when active)
  [P]
- Login/Logout menu item, redirect after login/logout [F]

### Ops & monitoring (additional suggestions)
- **Activity log / audit trail** — who did what (logins, content, settings, WP Arzo
  actions), filterable, exportable. [F basic / P advanced + retention]
- **Site Health integration** + WP Arzo health checks (writability, cron, OPcache, SSL,
  PHP/DB versions). [F]
- **Cron / scheduled-tasks manager** — view/run/pause WP-Cron events; switch to real
  cron. [F view / P manage]
- **Transient & cache manager**, **autoloaded-options cleanup**, **database optimizer**
  (overhead, orphaned meta, revisions/spam cleanup). [F basic / P scheduled]
- **Redirect manager + 404 monitor + broken-link checker**. [P]
- **Maintenance scheduler** — schedule maintenance-mode windows. [P]
- **Notifications** — email / Slack / Discord / webhook on events (backup, login lockout,
  errors, update available). [P]
- **Import/Export settings** (and per-feature config) + config presets/blueprints. [F]
- **Safe mode** — boot WP Arzo with all features off (URL/constant) to recover from a bad
  toggle, independent of the emergency tool. [F]
- **Multisite support**, **role-based access** to WP Arzo itself, **WP-CLI commands**, and
  a **REST surface** (shared with the MCP layer). [P]
- **White-label / agency mode** — rebrand the plugin, hide from non-privileged users. [P]
- **GDPR/privacy tools** — export/erase helpers, consent-mode wiring for the pixels. [P]

### Core controls
- Disable Gutenberg, comments, feeds, all updates, REST API [F]

### Developer tools
- **Code Snippets Manager** [F core / P advanced] — safe PHP/CSS/JS/HTML snippets with
  scope (admin/front/everywhere), run-location, conditional logic, error-guard
  (auto-disable a snippet that fatals), import/export, and a code editor (CodeMirror).
  Built on DataTable + Modal + custom Select components.

### Backup, restore & versioning (flagship)
A first-class, **Git/GitHub-style** snapshot system — the headline differentiator.

- **Snapshots (commits):** point-in-time backups of any/all of: database (selected
  tables or full), `wp-content` (uploads/plugins/themes/mu-plugins), and config
  (`wp-config.php`, `.htaccess`). Each snapshot has a message, author, timestamp, parent,
  and a content hash — a lightweight history you can browse like commits. [F basic / P full]
- **Incremental & deduplicated:** store only changed files between snapshots (content-
  addressed, like Git objects) so history is cheap; full + incremental modes. [P]
- **One-click restore (checkout):** restore a whole snapshot or **cherry-pick** (just the
  DB, just one plugin folder, a single file). Always creates a safety snapshot before
  restoring (so restore is itself undoable). [✅ Free for local snapshots; partial/any-point P]
- **Local storage is always Free.** Only **remote/cloud destinations** (Drive/Dropbox/
  pCloud/FTP/S3/Git) are Pro.
- **Diff view:** see what changed between two snapshots (DB row counts/option diffs,
  added/removed/modified files) before restoring. [P]
- **Automated snapshot triggers** (opt-in, per the maintainer's request):
  - **Before enabling/disabling any feature** (auto-snapshot if the toggle is on). [F]
  - Before risky actions: file edits, SQL execution, plugin/theme activate/switch,
    config writes, updates. [F/P]
  - **Scheduled** (hourly/daily/weekly/monthly) via WP-Cron or a real system cron. [P]
- **Storage destinations (remotes):** Local (default), **Google Drive**, **Dropbox**,
  **pCloud**, **FTP/SFTP**, **Amazon S3 / S3-compatible** (Wasabi, Backblaze B2,
  DigitalOcean Spaces), and Git remote (push snapshots to a real Git repo). Multiple
  remotes at once; "push" a snapshot to a remote like `git push`. [Local F / cloud P]
- **Reliability & safety:** client-side encryption (AES-GCM) for off-site copies,
  integrity verification (hash check) on backup and restore, retention/rotation policy,
  chunked/resumable transfers for large sites, low-memory streaming, WP-CLI parity,
  email/webhook notification on success/failure, and a restore **dry-run**. [P]
- **Migration/clone:** export a snapshot as a portable archive to spin the site up
  elsewhere (search-replace URLs on restore). [P]

Engineering notes: snapshots are content-addressed objects + a manifest (JSON) per
snapshot; the "auto-snapshot before toggle" hooks into the feature-registry
enable/disable lifecycle; destinations are pluggable **remote drivers** (one interface,
many providers) mirroring the pixel-framework pattern; OAuth tokens for Drive/Dropbox/
pCloud stored encrypted. Never block the request — large backups run via background
queue/cron with progress + cancel.

### AI (where genuinely valuable)
- **AI MCP Server & Command Center** [P] — exposes WP Arzo capabilities as an MCP server
  (and a natural-language "Command Center" inside the dashboard) so the maintainer (or an
  external AI client) can drive admin tasks via tools: query site status, toggle features,
  manage users/plugins/themes, search/replace, generate snippets, etc. Every tool is
  capability- + nonce-gated and runs through the same registry/permission layer as the UI
  — never a bypass. Read-only tools by default; writes require explicit confirmation.
- **AI assists embedded in features** (opt-in, bring-your-own API key; provider-agnostic):
  - Snippets: "describe what you want" → generated, scoped, sandboxed snippet draft.
  - Code injection / CSS: explain/secure/optimize a block.
  - SEO/marketing: draft `robots.txt`/meta, suggest pixel/event setup.
  - Email logs: summarize delivery failures and suggest fixes.
  - Debug log: explain errors and likely causes/fixes.
  - CPT/CCT: scaffold a post type + fields from a prompt.
  AI is always **suggestion-first** (human approves before any write), keys stored
  encrypted, and fully optional/disable-able.

## 5. Emergency tool — improvement workstream

`wp-arzo-emergency/index.php` (standalone recovery, bcrypt login, session CSRF). Tasks:

- **Consistency:** reconcile version (`2.0` header vs `2.2` const vs plugin-written hash);
  centralize via a single constant; show it in one place.
- **Security review:** audit the `md5()` usage (line ~440), tighten the CSP (drop
  `unsafe-eval`; scope `unsafe-inline` or move to nonces/hashes), rate-limit login,
  ensure no error/credential leakage, confirm `arzo-safe.php` is non-guessable + denied
  by web server, add brute-force backoff.
- **UX/design:** adopt the shared token set + components (currently duplicates CSS);
  consistent nav, status chips, icons; responsive + accessible.
- **Robustness:** safer wp-config parsing, clearer DB-repair flows, dry-run/confirm on
  destructive actions, better empty/error states.
- **Generation flow:** keep the new CSRF-protected generate/delete (v6.5); surface the
  recovery URL + password with copy buttons and a regenerate action.

## 6. Phasing / milestones

**Phase 0 — Foundation (do first).**
1. Unify design tokens; load them everywhere; update `design.md` to match reality.
2. Build the component library (§3) + icon registry.
3. Plugin-wide consistency pass: replace ad-hoc styles/checkboxes/native selects with
   components; fix any remaining JSON shape mismatches; a11y baseline.
4. Restyle the emergency tool onto the system + security/consistency fixes (§5).

**Phase 1 — Dashboard & registry.**
- Native WP Arzo dashboard, feature-registry, toggle grid, schema-driven settings
  renderer, enable/disable persistence, Advanced-Tools home for the console.

**Phase 2 — Free feature modules.**
- Port/build the Free utilities & core-control features as registry modules (batch by
  group), each using shared components.

**Phase 3 — Pro addon scaffold + first Pro modules.**
- Separate Pro plugin, license gate, upsell UI; ship first Pro modules (e.g. pixel
  framework + GA4/GTM, SMTP advanced + email logs).

**Phase 4 — Heavy builders + developer tools.**
- Media manager (HappyFiles-style), CPT/CCT builder (JetEngine-style), custom
  login/dashboard/admin branding, advanced security, Code Snippets Manager.

**Phase 4.2 — Backup, restore & versioning.**
- Local snapshot engine (content-addressed objects + manifest) → restore/diff →
  auto-snapshot triggers (feature toggles + risky actions) → scheduled backups → remote
  drivers (Drive/Dropbox/pCloud/FTP-SFTP/S3/Git) → encryption + retention + notifications.
- Slot the local engine + "snapshot before feature toggle" early (it protects every other
  feature); cloud remotes and scheduling follow as Pro.

**Phase 4.5 — AI layer.**
- AI MCP Server + Command Center (registry-backed, capability/nonce-gated tools), then
  the embedded per-feature AI assists. Provider-agnostic, BYO key, suggestion-first.

**Phase 5 — Polish & launch.**
- Perf pass, full a11y audit, docs, i18n, packaging for wp.org (free) + Pro distribution.

## 7. Per-feature workflow (every feature)

1. Maintainer shares the reference plugin/competitor.
2. Define the registry module (id, group, tier, icon, `settings_schema`, `boot`).
3. Build UI from shared components only; wire AJAX with the standard nonce.
4. Security + a11y review against the skills checklists.
5. `php -l` + `node --check`; manual verify on a live site.
6. Update `CHANGELOG.md` + bump version per `wp-arzo-release`.

## 8. Repository, licensing & distribution strategy

This repo is **currently public** (it was planned as free). Going freemium changes what
should live where. Recommended model:

- **Free core stays public + GPLv2+.** WordPress is GPL; a plugin distributed on wp.org
  (and most PHP that calls WP APIs) must be GPL-compatible. Keeping the free core public
  and GPL is the norm (ASE, Yoast, etc. all do this) and is good for trust/SEO/adoption.
  Anyone *can* fork it — that's fine; your moat is the Pro features, updates, support,
  and brand, not code obscurity.
- **Pro addon = a SEPARATE, PRIVATE repo.** Ship it as its own plugin that registers into
  the free core's feature-registry. Distribute it as a licensed download (not on wp.org).
  Note GPL still technically allows redistribution of Pro code, but the **license key /
  update server / support** is what you actually sell — same as every major Pro plugin.
- **Do NOT commit Pro code, license-server secrets, OAuth client secrets, or signing keys
  to the public repo.** Cloud-storage OAuth apps (Drive/Dropbox/pCloud) and the licensing
  server live outside this repo.
- **Security posture for a public repo:** assume an attacker reads all the code. That's
  already our model (capability + nonce + validation on every action). Keep secrets in
  config/env, never in source. The emergency tool's password hash lives in the generated
  `arzo-safe.php` (git-ignored), not in the repo — keep it that way.
- **Branching:** keep `main` as the stable free core; `wp-plugin` (current) as the active
  dev branch; tag releases. Pro lives in its own repo with its own tags.
- **Licensing: Freemius (chosen).** Use [Freemius](https://freemius.com/) for checkout,
  licensing, subscriptions, EU VAT/MoR, secure auto-updates, and the upgrade/upsell UI.
  Integration plan:
  - Add the Freemius WordPress SDK (`vendor/freemius/start.php`) to the **free core** and
    init via `wp_arzo_fs()` with the plugin's Freemius **Plugin ID / public key / secret**
    (secret kept out of the public repo / in CI only).
  - Configure it as a plugin **with a paid add-on/plan** (free core on wp.org + Pro plan).
  - Gate Pro modules through the existing `wp_arzo_feature_is_available` filter →
    `wp_arzo_fs()->can_use_premium_code()` / `is_paying()`; show locked cards + an
    `fs()->get_upgrade_url()` CTA for free users.
  - Use Freemius for licensing **state**; features still self-register through our
    registry, so the Pro add-on is just additional modules unlocked by license.
- **Trademark/name:** lock the plugin slug + name early; check wp.org guidelines (see the
  `wp-plugin-directory-guidelines` skill) before submitting the free core.

Action items when we get there: create the private Pro repo + a thin licensing/update
client in the free core (feature-gate hooks only, no secrets), and move any
cloud/AI/license credentials into a config the public repo never sees.

## 9. Open decisions (maintainer to confirm; defaults assumed)

- Tech stack (vanilla **[default]** vs React vs hybrid).
- Console fate (keep as Advanced Tools **[default]** vs fold in).
- Exact Free/Pro line (defaults in §4).
- Pro licensing mechanism (own server / Freemius / Lemon Squeezy / EDD).
- i18n/text-domain readiness + wp.org compliance timing.
- AI provider(s) for the AI layer (Anthropic / OpenAI / others) — default
  provider-agnostic with BYO key; pick the default + whether to offer a managed option.
