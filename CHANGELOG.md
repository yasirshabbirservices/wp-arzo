# Changelog

All notable changes to **WP Arzo – Maintenance & Administration Suite** are documented
in this file. This project loosely follows [Keep a Changelog](https://keepachangelog.com/)
and [Semantic Versioning](https://semver.org/).

## [6.120.0] — 2026-07-03

### Added — Configure a feature in place (dashboard drawer + finder) (Settings UX, Phase 1)

Configuring a specific feature used to mean hunting for its card in the ~50-card grid, then
bouncing to a separate settings page. Now:

- **In-place Configure drawer.** For a feature with settings but no dedicated page, “Configure”
  opens its full settings form in a slide-in drawer **right on the dashboard** — load + save go
  through AJAX (no page reload); enhanced selects, conditional (`show_if`) fields, focus
  management and Escape/backdrop close all work. The old full-page settings route still works
  when JS is off (the Configure link’s href) and via Ctrl-K. *(Progressive disclosure +
  co-location — best-practice: keep the control next to the thing it configures.)*
- **“Configurable only” filter chip** next to the feature search — instantly narrows the grid to
  just the features that have settings.
- **Ctrl-K “Configure: ‹feature›”** entries for every enabled, configurable feature, so you can
  jump straight to one from the command palette.

New AJAX endpoints `wp_arzo_feature_form` / `wp_arzo_feature_save` (capability + nonce gated);
the field sanitizer was refactored into a shared `sanitize_feature_settings()` used by both the
page form and the drawer. No new top-level surface — the dashboard stays the one place to enable
*and* now configure.

## [6.119.0] — 2026-07-03

### Changed — Analytics page header (UX)

- The Analytics header now shows the **resolved date window** for the active range (e.g.
  “Showing Jun 3 – Jul 3, 2026”, site-timezone) under the range control, so it’s always clear
  exactly what period the report covers — it updates live when you switch Today / 7 / 30 / 90.
- The date-range control is now visually distinct from the report-tab row (grouped with a clock
  caption), reducing the two-identical-tab-rows ambiguity. Tokens only; no new JS dependency.
- Added `analytics_range_label()`; the AJAX report endpoint returns the window label alongside
  the body.

## [6.118.0] — 2026-07-03

### Added — Analytics: visitor Journeys engine (Phase 4)

- The built-in Analytics engine (`WP_Arzo_Analytics`) gained visitor-**journey** queries — one
  row per anonymous cookieless 30-minute session, with its landing/exit page, source, page
  count, duration, device and country: `journeys()` / `journeys_count()`, plus `journey_steps()`
  which merges a session's pageviews, tracked events and orders into one chronological timeline.
- **No schema change** — journeys are reconstructed entirely from the existing `session` hash on
  the hits / events / orders tables (no new table, no per-visitor identity, still cookieless).
- Pure helpers `merge_steps()` (chronological step merge + tie-break) and `human_duration()`
  are harnessed (17 checks). Added a `route` icon glyph.
- Surfaced by **WP Arzo Pro (Analytics Pro)** as a new **Journeys** report tab (expand a visit to
  replay its path — pages → events → order — with zero extra JS via native `<details>`).

## [6.117.0] — 2026-07-03

### Added — Google tags: extension points for Consent Mode + server-side GTM (Phase 4)

- The free GA4 / GTM / Google Ads tags now load from a **filterable host** via the new
  `wp_arzo_google_tag_host` filter (bare host, sanitized), and the GA4 `config` params are
  filterable via `wp_arzo_ga4_config_params`. This lets **WP Arzo Pro (Advanced Google Tags)**
  route tags through a **server-side GTM** container and add a GA4 `transport_url` — with no
  behavioural change when Pro is absent (defaults to `www.googletagmanager.com`).
- Added the **Advanced Google Tags** (`google_tags_pro`) locked-PRO showcase card (Consent
  Mode v2 + server-side GTM).

## [6.116.0] — 2026-07-03

### Changed — Pro catalog: advertise Content Analytics (Phase 4)

- Added the **Content Analytics** (`analytics_content`) locked-PRO showcase card
  (author / post-type / category reports with traffic-share %). The feature itself is
  entirely in WP Arzo Pro (it post-processes the free engine's top-pages data at report
  time — no free schema change).

## [6.115.0] — 2026-07-03

### Added — Analytics pillar: eCommerce revenue store (Phase 4, free half)

- The built-in engine gained an **orders** store: a new `{prefix}wp_arzo_analytics_orders`
  table (**DB v4**, `dbDelta`, `UNIQUE(order_id)`) plus `record_order()` — which attributes
  each order to the shopper's **first-party first touch** (utm / referrer / landing page, via
  `first_touch()` against the hits table) — and the reporting queries `ecommerce_totals()`
  (orders / revenue / AOV / conversion %), `revenue_by_source()`, `converting_landing()`, and
  `revenue_series()`. The recording + queries live in the free engine; **WP Arzo Pro
  (eCommerce Analytics)** hooks WooCommerce and gates the report tab. Generic by design so
  EDD / SureCart / FluentCart can hook the same `record_order()` later.
- `INSERT IGNORE` keeps recording idempotent (safe if an order hook fires more than once);
  `sanitize_currency()` normalizes the currency code. Orders are **not** pruned (revenue
  history is kept); the table is dropped on uninstall. New `cart` icon.

## [6.114.0] — 2026-07-03

### Added — reusable `button` settings field (enables Pro scheduled email reports)

- The schema-driven settings renderer gained a generic **`button`** field type: a nonce'd
  link to an `admin-post.php` action the owning feature registers (fields: `action`,
  `button` label, `button_icon`). Skipped by the settings save loop (it's a UI action, not a
  stored value). Used by **WP Arzo Pro → Analytics Email Reports** for its “Send a test report
  now” button, and reusable by any future feature that needs an inline action button.

## [6.113.0] — 2026-07-03

### Added — Analytics pillar: event-tracking engine (Phase 3b, free half)

- The built-in analytics engine gained an **interaction-events** store: a new
  `{prefix}wp_arzo_analytics_events` table (**DB v3**, created by `dbDelta`) plus
  `record_event()` and the `events()` / `events_by_type()` / `events_total()` reporting
  queries. The queries live in the free engine; **WP Arzo Pro (Analytics Pro)** gates the
  tracking config + Events report tab (same pattern as Campaigns / Real-time).
- The collector `wp-arzo/v1/hit` now accepts event payloads (`{k:'e', e:type, n:name, …}`)
  alongside page hits — same cookieless privacy gate (no cookies, no PII at rest, DNT honored).
- The beacon (`assets/js/wp-arzo-analytics.js`) gained **dormant, opt-in delegated listeners**
  for outbound-link / download / mailto / tel / form / custom (`data-wpa-track="…"`) events.
  With no server rules it attaches nothing (zero overhead); Pro supplies the rules via the new
  **`wp_arzo_analytics_event_rules`** filter.
- CSV export handles the **Events** tab; retention prune and uninstall now cover the events
  table; new `cursor` icon.

## [6.112.0] — 2026-07-03

### Added — Analytics pillar: extensibility hooks for Pro report tabs (Phase 3a enabler)

- The **Analytics page is now extensible**: add-ons append report tabs via the new
  **`wp_arzo_analytics_tabs`** filter and render their bodies via **`wp_arzo_analytics_render`**
  ($html, $tab, $from, $to). WP Arzo Pro uses these to add **Campaigns** and **Real-time** tabs.
- The engine gained reusable queries: `campaigns()` (UTM performance) and
  `realtime_active()` / `realtime_recent()` / `realtime_series()` (live view).
- The report JS gained generic **live auto-refresh**: a tab body with
  `data-wpa-auto-refresh="<seconds>"` is polled in place (used by the Real-time tab).

## [6.111.0] — 2026-07-03

### Changed — Analytics pillar, Phase 2b: Google tags now FREE + admin-bar peek

- **Google Analytics 4, Google Tag Manager, and Google Ads tag insertion are now FREE features**
  (moved from Pro; grouped under **Analytics**) — Site-Kit parity, so you don't need another plugin
  just to add Google's tags. Each gained an **“Exclude signed-in admins”** option (keeps your own
  visits out of the data), and GA4 keeps IP anonymization.
- **Google tab** on the Analytics page — manage GA4 / GTM / Ads in one place (enabled state +
  configured ID + Configure link), with a note that Pro adds GA4 report data in-dashboard, Consent
  Mode, and server-side GTM.
- **Admin-bar peek** — a “N today” pageviews node (with today's unique visitors) linking to Analytics.
- Removed the now-obsolete Pro GA4/GTM/Ads placeholders from the locked-feature catalog.

## [6.110.0] — 2026-07-03

### Added — Analytics pillar, Phase 2: more reports + CSV + surfacing

Builds on Phase 1 — the Analytics dashboard is now **tabbed** (Overview / Geo / Devices / Behaviour)
and the data is surfaced across wp-admin.

- **Geo** — a **Countries** report (with flag emoji), from a `country` column populated header-first
  (`CF-IPCountry` / host geo headers; a bundled DB comes in a later phase).
- **Devices** — **Device type**, **Browser**, and **Operating system** breakdowns (parsed from the
  User-Agent server-side).
- **Behaviour** — **Landing** and **Exit** pages (per-session first/last hit), **404s** (new `is_404`
  flag from the beacon), and on-site **Search terms** (new `search` column).
- **CSV export** of the current tab + range (nonce-gated `admin-post` stream).
- **Dashboard widget** — an "Analytics — last 7 days" wp-admin widget (KPIs + top pages + deep link).
- **Per-post Views column** — a Views count on every public post-type list table.
- Schema **DB v2** (adds `is_404` + `search`; dbDelta migration). New engine queries harnessed for the
  SQL-safe dimension whitelist (42 checks total). Reports use a shared breakdown renderer.
- Next: **P2b** — move the GA4/GTM/Ads tag insertion from Pro into a free **Google** tab (enriched);
  then Pro Phases 3–4.

## [6.109.0] — 2026-07-03

### Added — Analytics pillar, Phase 1: built-in cookieless analytics (NEW)

First slice of the **Analytics initiative** ([`.claude/analytics-plan.md`](.claude/analytics-plan.md)) —
a built-in, first-party, **cookieless** website-analytics engine so you don't need Site Kit,
Independent Analytics, or MonsterInsights.

- **Tracking** — a tiny front-end beacon (`assets/js/wp-arzo-analytics.js`, ~1 KB, `sendBeacon`)
  posts each page-hit to a REST collector (`wp-arzo/v1/hit`) that records it in a custom table
  (`{prefix}wp_arzo_analytics_hits`). **No cookies, no external services, no personal data at rest** —
  the IP is used only to derive a **daily-rotating salted visitor hash**, then discarded. Sessions are
  a 30-minute stateless bucket. Honors Do-Not-Track.
- **Reports** — a new **WP Arzo → Analytics** dashboard: KPI cards (pageviews, unique visitors,
  sessions, bounce rate, avg. visit, views/session), a **self-hosted SVG traffic chart** (CSP-safe,
  token-themed), and **Top pages** + **Top referrers** tables, with **Today / 7 / 30 / 90-day** range
  switching (AJAX, no reload).
- **Hygiene** — bot/crawler filtering, logged-in **admin exclusion** (default on), extra role + IP
  exclusion lists, DNT respect, and **daily retention pruning** (schema-driven settings). Self-referrals
  and direct traffic are handled correctly; UTM params are captured.
- **Plumbing** — new `analytics` feature (group **Analytics**, new `chart`/`globe` icons), engine
  `WP_Arzo_Analytics` (pure helpers harnessed — 35 checks), command-palette entry, submenu near the top.
  Table dropped + cron cleared on uninstall.
- Next: **Phase 2** (Geo/Devices/Behaviour reports, CSV export, dashboard widget, per-post views, and
  the Google GA4/GTM tag tab moving to free); then Pro Phases 3–4.

## [6.108.0] — 2026-07-03

### Added — Activity Log: retention by severity (enrichment #8)

- **Keep important events longer.** A new toggle (on by default) makes the log **severity-aware
  when trimming**: the newest `Entries to keep` are always retained, *and* older **Critical/High**
  events are protected beyond that limit (up to the 1000-entry ceiling) — only routine Low/Medium
  events drop at the cap. Turn it off for a strict newest-N log. Trim logic in
  `WP_Arzo_Activity_Log::trim()`; severity map refactored to a shared `severity_groups()` +
  `important_actions()`.
- **Pro parity.** The Advanced Audit Log's daily prune is now **two-tier**: routine events prune
  past *Keep routine events for (days)*, while *Keep High &amp; Critical for (days)* (0 = forever,
  never shorter than the routine window) retains security-relevant history far longer.
- 13-check harness.

## [6.107.0] — 2026-07-03

### Added — Activity Log: per-event object deep-links (enrichment #7)

- **Clickable events.** A log entry about a post, page, CPT, media item, user, or comment is now a
  **link straight to that object's edit screen** (with an external-link icon) — no more hunting for
  the item the entry refers to. Applies to publish/update/trash, media uploads, user create/role/
  profile changes, comment moderation, and session terminations (→ the user).
- **How it works.** `record()` gained an optional object reference `{type,id}` stored as `lt`/`li`
  on the entry; `WP_Arzo_Activity_Log::object_edit_url()` resolves it to `get_edit_post_link` /
  `get_edit_user_link` / `get_edit_comment_link` at display time. Only **non-destructive** events
  link (a permanently-deleted object has nothing to open); entries logged before this release simply
  render as plain text (fully backward compatible).
- **Pro parity.** The Advanced Audit Log table gains the same deep-links via two new columns
  (`link_type`, `link_id`; DB v2 migration) mirrored from the free entry.
- 11-check harness.

## [6.106.0] — 2026-07-03

### Added — Activity Log: live user sessions + terminate (enrichment #6)

- **Sessions tab.** The Activity Log page is now tabbed (**Events** / **Sessions**). The new
  Sessions view lists every live WordPress login **site-wide** — user (+ role, edit link), IP,
  signed-in time, expiry, and a condensed **Client** (browser · OS) — with a live "N active"
  count badge on the tab. Reads core's `session_tokens` user-meta (no new storage).
- **Terminate a session (force logout).** Each row has an icon-only **Terminate** action (danger
  red) that signs that device out immediately via a capability + nonce-gated AJAX endpoint. Your
  **own current session is protected** (shown as *This device*, terminate disabled, and the server
  refuses to cut it by verifier match). Expired sessions are dimmed but still clearable.
- **Interop.** A terminate records a **High**-severity `session_terminated` event → the Activity
  Log, the Pro **Audit Log**, and the Pro **Notifications** *security* group. A **Live Sessions**
  deep-link is registered in the command palette.
- Session parsing/termination logic harnessed (15 checks). No new option (uses core session meta).

## [6.105.0] — 2026-07-03

### Added — Activity Log: severity grading, live summary & brute-force alerts (enrichment #5)

- **Severity grading.** Every event is now graded **Critical / High / Medium / Low** (derived from
  the action, so it applies retroactively to already-logged entries) and shown as a coloured
  badge in a new **Severity** column. A severity **filter** joins the event-type filter and
  search. Model lives in the engine (`severities()` / `action_severity()` / `severity_meta()`), so
  the Pro Audit Log reuses it (new Severity column + CSV field there too).
- **Live summary strip.** The Activity Log page opens with four at-a-glance tiles — **Total
  events**, **Last 24 hours**, **High/critical · 24h**, **Failed sign-ins · 24h** (state-aware
  colours) — from a new `stats()` helper.
- **Brute-force burst alert.** When many sign-ins fail in a short rolling window (defaults:
  **10 within 15 min**, both configurable), a **Critical `security_alert`** is recorded and fans
  out through `wp_arzo_activity_recorded` → the Pro **Notifications** *security* group and the Pro
  **Audit Log**. A per-window cooldown prevents alert spam. New settings: *Brute-force alert*
  toggle + threshold + window.
- **Instant client-side filtering.** The severity / event / search controls now filter the table
  **in place** (no page reload) for the free capped log; CSV export gains a **Severity** column.
- Engine logic harnessed (19 checks); `wp_arzo_activity_failwin` window state removed on uninstall.

## [6.104.0] — 2026-07-03

### Added — Email suite: retry queue + 4 more providers (enrichment #4)

- **Email retry queue.** When every configured connection fails to send a message, it's now
  **queued and re-tried automatically** with exponential backoff (5m → 15m → 1h → 6h, up to 4
  tries) via a 5-minute WP-Cron worker — so transient SMTP/API failures (timeouts, rate limits,
  brief outages) no longer lose the email. New **Queue** tab on the Email page (with a pending
  count badge) shows queued/gave-up messages with **Retry now**, **Retry all**, **Delete**, and
  **Clear**. Engine `includes/class-wp-arzo-email-queue.php` (`WP_Arzo_Email_Queue`); the send
  path was refactored to share `walk_connections()` + a `retry_deliver()` re-attempt method.
  Pure queue logic harnessed (22 checks). Cron cleared on deactivate; option removed on uninstall.
- **4 new SMTP providers** in the connection picker: **SMTP2GO · SparkPost · MailerSend ·
  Elastic Email** (16 providers total).
- Research: SureMail / FluentSMTP (queue + provider coverage).

## [6.103.1] — 2026-07-03

### Changed

- **Conditional-logic builder UI polish**: the match-mode is now a **segmented all/any pill**;
  each rule is a bordered **chip** with a leading **IF / AND / OR** connective that updates live
  with the mode; the selects are branded (custom chevron, token hover/focus) and the delete is a
  right-aligned soft-red icon — the whole thing now reads like a sentence.

## [6.103.0] — 2026-07-03

### Added — Code Snippets: Smart Conditional Logic (enrichment #3)

- A snippet can now be **gated by rules** so it only runs where you want (WPCode-style, but free).
  A **"Smart Conditional Logic"** builder in the editor lets you require **all** or **any** of a set
  of rules: **User login** (in/out) · **User role** · **Post type** · **Page type** (front/blog/
  singular/page/archive/search/404) · **URL path** (is / is not / contains / starts with / regex) ·
  **Device** (desktop/mobile) · **Date & time** schedule (on/after · before · between). No rules =
  runs everywhere (fully backward-compatible).
- Engine: `WP_Arzo_Snippets::condition_schema()` drives both the builder and server-side
  validation (they can't drift); `passes_conditions()` is evaluated at the safe moment —
  CSS/JS/HTML at output (query ready), PHP at boot, deferring to `wp` only when a page/post-type
  rule needs the main query. Pure logic harnessed (31 checks).
- Research: erropix Advanced Scripts `ConditionManager` + WPCode Smart Conditional Logic.

## [6.102.3] — 2026-07-03

### Changed — Files (elFinder) dark skin

- The console **Files** tab (elFinder file manager) is now fully dark. The last light spot — the
  list-view **column-header bar** (Name / Permissions / Modified / Size / Kind), a jQuery-UI /
  material-theme leak — is darkened (scoped to `#elfinder` to beat the theme's specificity, gradient
  cleared) to the dashboard's muted-header style. Also closed other latent light gaps (dialog
  titlebars, tooltips, `ui-widget` bases, inline rename/filter + search inputs → sunken dark, path
  links → accent), tokenized the remaining hardcoded hex, and **softened selection** (navbar + file
  rows) from a loud filled-green to the dashboard's **accent-soft + ring** active-tab look.

## [6.102.2] — 2026-07-03

### Changed

- **Full semantic-color audit of both plugins** (via the new `/color-audit` command) — result:
  every control's color already matched its intent (Pro: zero mismatches). One fix on the free
  side: the **Unlock / Unlock all** lockout buttons wore a misleading `trash` glyph (they *unblock*
  users, they don't delete) — new **`unlock`** icon added and applied.
- **Command registry** added under `.claude/commands/` (gitignored): `/enrich-feature`,
  `/consistency-pass`, `/color-audit`, `/handoff-docs` — reusable trigger phrases documented so any
  session recognizes them (see CLAUDE.md).

## [6.102.1] — 2026-07-03

### Added

- **`pause` + `play` icons** in the icon registry (filled glyphs) — closes the last emoji hold-out:
  the Pro **Cron Manager** pause/resume row action now uses real SVG icons instead of ⏸/▶.

## [6.102.0] — 2026-07-03

### Added — Semantic action colors (color = intent) + continued icon sweep

- New standing rule in the **Surface Consistency Bar** (CLAUDE.md #7): a control's **color
  matches what it does** — destructive/data-losing = red, primary = accent, neutral (incl.
  filter reset) = ghost/secondary, cautionary = amber. Applies across both plugins.
- New **`.wpa-btn--danger-soft`** component: a red-glyph button (transparent/bordered) that
  **fills red on hover** — the modern pattern for row-level & secondary destructive actions
  (delete a row, clear a log, revoke a key) without a wall of filled red buttons.
- **Free dashboard**: delete-snapshot / delete-connection / delete-snippet / delete-role
  row buttons, and **Clear log** (Email + Activity) and **Revoke** (REST key) now read **red**
  (`--danger-soft`) instead of neutral ghost.
- **Emergency tool**: destructive **Clear all transients** now red; the reversible repair
  actions stay neutral.
- **Console**: Debug "Update Settings" gains an icon (continuing the console icon sweep).

## [6.101.1] — 2026-07-03

### Fixed

- **`hidden` now hides `wpa-` components.** A class-set `display` (e.g. `.wpa-btn { display:
  inline-flex }`) overrides the UA `[hidden] { display:none }`, so any button/tab/badge toggled
  via the `hidden` attribute stayed visible. Added `.wpa-btn[hidden], .wpa-tab[hidden],
  .wpa-badge[hidden] { display:none !important }`. This makes the Pro **Advanced Audit** filter
  **Reset** button (v1.41.0) actually hide when no filter is active, and fixes the pattern globally.

## [6.101.0] — 2026-07-03

### Added — Surface Consistency Bar (standing directive + trigger phrase)

- Documented a repo-wide **Surface Consistency Bar** in CLAUDE.md with the reusable trigger
  phrase **"arzo consistency pass"** — one line the maintainer can drop in any session to bring
  any surface (console, emergency tool, public pages, Free/Pro feature page) to exact dashboard
  parity: same dark tokens (inputs sink to `--arzo-bg-input`, never the lighter gray), same
  `wpa-` components (segmented `.wpa-tabs`, `.wpa-btn`, `.wpa-select`, `.wpa-modal`/`.wpa-drawer`),
  real icons on every control (**icon-first / icon-only where sensible**), full interaction
  states + WCAG 2.2 AA, sensible ordering, and conditional/self-cleaning controls.

### Fixed

- **Emergency tool: Plugins & Themes tabs were always empty.** `WP_CONTENT_DIR` was defined one
  level too deep (`…/wp-content/wp-content`), so the plugin/theme/upload paths never resolved.
  Now points at the real `wp-content`; both lists populate again.

### Changed — Emergency tool + console consistency + icons

- **Emergency recovery tool** (`wp-arzo-emergency/index.php`): ships its own inline-SVG icon set
  (`arzo_em_icon()` — the CSP blocks the FA CDN) and uses it across the **nav tabs and every
  button** (Bulk Deactivate, Install, Create Admin, Reset, Update URLs, repair actions, login).
  The **Create Administrator** form is rebuilt into a clean responsive grid with helper text;
  repair actions become secondary buttons; the login/setup button gains an icon; broken FA
  pagination chevrons replaced with real SVGs; `.btn` gains inline-flex + `.btn-secondary`.
- **Temporary Logins**: the Role / Expires **native selects become the branded `.wpa-select`**
  listbox (via `data-wpa-select`; the console loads `wp-arzo-components.js`), and the Generate
  button gains an icon.
- **Snippets header**: the inline "Choose File" clutter is gone — **Import & Export now live in a
  `.wpa-modal`** opened from a single "Import / Export" button (new `exchange` icon), leaving a
  clean two-button header.
- **Extra Options**: Update Limits / Reset to Defaults buttons gain icons (representative of the
  ongoing console icon sweep).
- **Global checkbox glyph** re-centered (optical nudge) across every WP Arzo surface.

## [6.100.0] — 2026-07-02

### Changed — Emergency page redesign + console/public surface consistency

- **Emergency recovery page (`wp-arzo-emergency/index.php`) now mirrors the dashboard.**
  Its self-contained CSS was upgraded: the boxy top tabs become the dashboard's **segmented
  pill tabs** (accent-soft active state + focus ring), inputs **sink to the dark
  `--background-input` surface** (matching the feature-manager, not the lighter gray), the
  System Status readout is rebuilt as **info cards** (Environment / WordPress) like Site Info,
  table headers adopt the muted-uppercase-on-elevated style, and upload/create panels lift
  onto `--background-elev` with a border. New self-contained tokens
  (`--background-input`, `--background-elev`, `--accent-soft`, `--accent-ring`, `--radius-lg`).
- **Site Modes → Emergency Mode card no longer cramped.** Rebuilt from a single squeezed row
  into a clean stacked card: header row (icon + title + ACTIVE badge on the left, toggle on
  the right), full-width description, then an actions row (Copy Link / Copy Direct Link /
  Reset Password — now `nowrap`, no 3-line wrapping) with the explanatory note below,
  revealed only when configured. JS updated to toggle the badge/body and rebuild the buttons.
- **Console "light" surfaces aligned to the dashboard.** Console inputs move off the lighter
  `--background-light` (#2a2a2a) onto the dashboard's sunken `--arzo-bg-input` (#151515);
  the Plugins/Themes/Temporary-Logins upload & create panels lift onto `--arzo-bg-elev` with
  a border; Site Info status badges + progress track and the console error banner move onto
  semantic tokens; focus rings unified.
- **Public maintenance page bug fixed.** Two **self-referential (invalid) custom properties**
  (`--arzo-bg-hover` / `--arzo-text-primary` defined as themselves) were silently breaking the
  card background and message color — replaced with real values; the card now renders as a
  proper `--arzo-bg-panel` panel with a bordered `--arzo-bg-elev` contact box.

## [6.99.0] — 2026-07-02

### Changed — Console + public pages: complete token consistency (Phase A2 finale)

- **Every console tab is now 100% token-driven** — a property-aware sweep converted all
  remaining hardcoded colors in Site Modes, Debug, Extra Options, Users, Plugins, Themes,
  Database and Temporary Logins (text, surfaces, borders, semantic states, even the
  debug-log severity colors and JS hover handlers) to `--arzo-*` tokens, so readability
  and contrast match the dashboard everywhere, in both themes.
- **The PUBLIC pages match the dashboard too**: the maintenance / coming-soon /
  payment-required page now uses the dashboard's dark palette (embedded token block —
  the page stays self-contained) with per-mode accents aligned to the semantic tokens
  (warning / accent / error); the **emergency recovery page**'s palette block was aligned
  with the current tokens (danger/success values) and its stray hexes swept onto its vars.
- Zero raw palette values remain in any console tab, the console CSS, the public
  maintenance pages, or the emergency tool (embedded token definitions excepted — those
  pages must work standalone).

## [6.98.0] — 2026-07-02

### Changed — Console Phase A2: full visual consistency with the dashboard

- **Tables**: console table headers move from accent-on-gray to the dashboard's muted
  uppercase style on an elevated surface.
- **Buttons**: the console `.btn` adopts the dashboard button language — token radius,
  icon-friendly inline-flex alignment, accent-hover, pressed push, and a focus-visible
  ring; pagination buttons gain focus rings too.
- **Site Modes**: the mode cards' activate buttons, icons and notices move off hardcoded
  hexes onto semantic tokens (warning/success/error, AA-checked text-on-fill); success
  notices use token soft tints. The last accent-hover hexes in the console CSS are
  tokenized. **Zero hardcoded palette values remain in the console styles.**

## [6.97.1] — 2026-07-02

### Fixed

- Site Info still showed green-gradient card headers — its page-level `<style>` block was
  overriding the shared CSS. De-gradiented and tokenized (headers are elevated panels with
  accent icons; the disk-usage fill is a flat accent bar). Zero gradients remain plugin-wide.

## [6.97.0] — 2026-07-02

### Changed — Advanced Tools console: Phase A modernization (consistency + accessibility)

- **The console nav is now the same modern segmented tablist as the dashboard**
  (`.wpa-tabs` with icons on every tool tab, hover/pressed/focus-visible/selected states,
  `aria-current="page"`), replacing the legacy boxy folder tabs and their hardcoded hover
  colors. Wrapped in a proper `<nav>` landmark.
- **Accessibility retrofit**: a skip-to-content link (visible on keyboard focus), a
  `<main>` landmark around the tool content, and a real **`<h1>` on every tab** (the File
  Manager gets a screen-reader h1 + labelled region). The PHP-limits form controls are
  now properly label-associated (`for`/`id` on all five fields).
- **Visual consistency**: the solid green gradient card headers (Site Info cards,
  lightbox, quick-login) are now subtle elevated panels with accent icons, matching the
  dashboard's card language; the console progress fill and Extra Options form styles moved
  off hardcoded hex onto design tokens.

## [6.96.0] — 2026-07-02

### Added — Progress component system + alignment polish

- **One progress language for the whole plugin** (`wp-arzo-components.css`): linear
  `.wpa-progress` (sm/lg sizes, semantic colors), **stacked segments** for ratio meters,
  an **indeterminate shimmer** for unknown-duration operations (degrades to a static
  translucent bar under reduced motion), and a **conic-gradient ring dial** (`.wpa-ring`)
  for compact metrics. All token-driven, all with `role="progressbar"` + ARIA values.
- Applied first: the Email **deliverability meter** moved off its hardcoded-rgba inline
  styles onto the stacked component; **Backups shows a live shimmer bar** while a snapshot
  (especially with files) is being created; Media Cleanup's scan bar now rides the shared
  component. More surfaces roll out per the enrichment plan.
- **Checkbox tick optically centered** — the check glyph is bottom-heavy; it's now a
  border-drawn check with a 1px optical lift, verified at zoom in the browser. Alignment
  best practices (optical centering, browser-verified) are recorded in the design system.

## [6.95.1] — 2026-07-02

### Fixed

- Backups: the "Also include files" help text still said file restore was "coming" —
  it shipped in 6.94.0; the copy now points at Restore's database + files option.

## [6.95.0] — 2026-07-02

### Fixed — Overlay readability: drawers above the admin bar, token headings, real checkboxes

- **Drawers and modals no longer hide behind the WP admin bar** — the overlay z-index
  tokens now sit above wp-admin's bar (`--arzo-z-modal: 100000`, toast above that).
  Applies to every overlay: the Backups compare drawer, Email connection drawer, Cron
  event/URL-job drawers, provider picker modal, toasts.
- **Readable text in every overlay**: wp-admin's near-black heading/text colors leaked
  into anything we hadn't explicitly colored (drawer titles, section headings) —
  unreadable on dark panels. `.wpa-admin` headings and the modal/drawer panels now force
  design-token colors in both themes.
- **Token-styled checkboxes and radios everywhere**: native checkboxes rendered as
  near-invisible dark squares. All checkboxes/radios on WP Arzo surfaces (pages, modals,
  drawers) are now custom-drawn from tokens — accent check/dot, visible border, focus
  ring, smooth transitions (the on/off switches keep their own component styling).

## [6.94.0] — 2026-07-02

### Added — Automatic file restore (completes the file-snapshot loop)

- Restoring a snapshot that contains file components now offers to **restore the files
  too**: uploads / plugins / themes are extracted back over the live site. Safety-first
  and explicit:
  - A **safety snapshot with the same file components** is taken before anything is
    overwritten — so a file restore is itself undoable.
  - **Non-destructive**: existing files are overwritten from the archive, files added
    since the snapshot are left in place.
  - **Config is never auto-restored** — a wrong `wp-config.php` bricks a site; those
    entries are counted and reported, apply them manually from the snapshot ZIP.
  - **Zip-slip safe**: every archive entry passes a strict path mapper (component
    allowlist, no `..`/`.`/absolute/drive-letter segments — 13-check harness) plus a
    realpath containment check; files are written beside the target and swapped, never
    half-written in place.
- The restore result reports exact counts (restored / failed / config skipped) in the
  toast and the AJAX payload.

## [6.93.0] — 2026-07-02

### Added — File snapshots + snapshot diff view (Backups enrichment, Tier 1)

- **Bounded file snapshots**: the create bar on WP Arzo → Backups gains "Also include files"
  chips — **Uploads / Plugins / Themes / wp-config + .htaccess**. Chosen components are
  zipped into the snapshot (streaming, memory-safe; the zip flushes every 500 entries) with
  a per-file hash manifest. Bounds are explicit, never silent: files over 100 MB and
  symlinks are skipped **and counted** (a "N skipped" badge shows on the row); our own
  backup folder is always excluded. Off-site destinations (Pro) upload the files along with
  the database automatically.
- **Diff view — compare any two snapshots** (the Git-style differentiator): a **Compare**
  button opens a drawer where you pick a base and a target; the report shows **options
  added/removed/changed** (name-level, hash-compared), **per-table row-count deltas** (plus
  added/removed tables), and **file changes** per component — with exact counts and capped
  sample lists. Powered by a cheap `db-summary.json` (row counts + option-value hashes,
  captured while the dump streams) and the file manifest — comparing is instant, no dump
  parsing. Snapshots made before this version report "diff unavailable" gracefully.
- Restore stays database-first; the files ZIP is stored inside the snapshot (download/export
  includes it) — **automatic file restore is the next Backups enrichment**.
- Diff + manifest logic harnessed (16 checks).

## [6.92.0] — 2026-07-02

### Added — Light / dark theme toggle (per-user) + accessibility

- **Light theme** for the whole WP Arzo dashboard: a sun/moon toggle in the brand bar (and a
  "toggle light / dark theme" command in the Ctrl/⌘-K palette) flips instantly and persists
  per user (`wp_arzo_theme` user meta, server-rendered body class — no flash). Every light
  value was re-derived for **WCAG AA on light surfaces**: the neon accent (fails on white)
  becomes a deep green, warning/error/info darken for text use, soft tints/ shadows/ code-
  syntax colors all re-tuned. `color-scheme` hints keep native controls consistent.
- New `sun` / `moon` icons; smooth icon rotation on hover (token-driven, reduced-motion
  aware) per the new **motion directive** (basic smooth animations wherever they make
  sense — documented in the design system).

## [6.91.0] — 2026-07-02

### Changed — Modern tabs + design-token/state sweep

- **`.wpa-tabs` rebuilt as a modern segmented tablist** — pill tabs on a raised bordered
  track with the full interaction-state set: hover (surface tint), pressed (subtle push),
  **focus-visible** (accent ring), selected (accent-soft pill + inset ring + scale-in
  entrance animation, reduced-motion aware), and disabled. Applies instantly to every
  tabbed page: Email, Backups, Settings hub, and the Pro Cron Manager. Active tab links now
  also carry `aria-current="page"`.
- **Command palette entries themed** — WP Arzo commands in the Ctrl/⌘-K palette now show
  their icon in the brand accent, driven by `var(--arzo-accent)` (design-tokens.css is
  enqueued admin-wide alongside the palette bridge; it's pure `:root` variables). The
  icon's inline styles moved to CSS (`.wpa-cmd-icon`).
- **Zero-hardcoded-values sweep**: new tokens `--arzo-white` and a `--arzo-code-*` syntax
  palette; the CodeMirror snippet-editor theme, toggle thumb, and danger-button text now
  reference tokens instead of raw hex. New `.wpa-badge--accent` modifier.

## [6.90.0] — 2026-07-02

### Changed — Advanced Cron Manager placeholder (Pro 1.38.0)

- The locked **Cron Manager** showcase card is now the **Advanced Cron Manager**, matching the
  rebuilt Pro module: create/edit/run/pause/delete WP-Cron events, scheduled **URL jobs**
  (webhooks, cache warmers), **custom intervals**, a **timed run log**, and cron **health
  diagnostics** with a spawn test. No free-core behavior changes — catalog copy only.

## [6.89.0] — 2026-07-02

### Changed — Site Health placeholder now advertises uptime / external monitoring (Pro 1.37.0)

- The locked **Site Health Monitor** showcase card now describes the Pro monitor's new uptime
  tooling shipped in **WP Arzo Pro v1.37.0**: an outbound **heartbeat ping** (Healthchecks.io-style
  dead-man switch, `/fail` on critical), a **token-guarded public status endpoint** for external
  uptime monitors (HTTP 200 while OK/warning, 503 when critical), and **response-time tracking**
  on the loopback check with a configurable slow-response threshold. No free-core behavior
  changes — catalog copy only.

## [6.88.0] — 2026-07-02

### Added — Support for the Site Health Monitor (Pro)

- New `heartbeat` icon in the icon set and a locked **Site Health Monitor** placeholder card in
  the Pro showcase. The monitor itself (disk / SSL / updates / PHP / cron / reachability checks
  with alerts to your Notifications channels) ships in **WP Arzo Pro v1.36.0** — this release is
  the free-core support (icon + catalog entry) it hooks into.

## [6.87.0] — 2026-07-02

### Added — Remote restore foundation (import a snapshot ZIP back into the site)

- The backup manager can now **import a snapshot ZIP and restore it** in one step
  (`import_and_restore()` / `import_zip()`). This is the local half of **remote restore**:
  the Pro off-site destinations (Google Drive / pCloud / FTP) download a backup and hand the
  archive here, so a backup that only exists in the cloud can be brought back and restored.
- The archive becomes a normal **local** snapshot again (you get it back on-disk too) and is
  restored through the existing safety-first path (a pre-restore safety snapshot is taken).
- **Zip-slip-safe:** only our known files (`manifest.json`, `data.jsonl[.gz]`) are extracted,
  by basename — embedded/relative paths in the archive are never honored. The snapshot id is
  validated before anything is written. Verified end-to-end against a real gzip snapshot.

## [6.86.0] — 2026-07-02

### Added — Command palette (Ctrl/⌘-K) jumps to any WP Arzo destination

- WP Arzo now feeds its pages, Settings tabs and console tools into **WordPress's own command
  palette** — the one already behind the admin-bar **Ctrl/⌘-K** node (`core/commands`, WP 6.3+).
  Hit the shortcut from **anywhere in wp-admin** and jump straight to Email, Backups, Activity
  Log, Snippets, Media Cleanup, Settings (and each Settings tab: Login Security, Roles, REST API
  Auth, Two-Factor, Notifications, AI-MCP…), the Setup Wizard, or any enabled standalone console
  tool (Site Info, Users, Database, Files, Debug, Site Modes, Extra Options, Temporary Logins).
- Deliberately **not** a competing overlay — we register into the palette the user already knows,
  so WP Arzo shows up alongside core commands with its own dashicons. Entries are gated: only
  destinations reachable right now appear (disabled features/console tools are omitted).
- Extensible via the `wp_arzo_command_palette_items` filter — the Pro add-on registers its own
  pages (Content Types, Custom Fields, Redirects, Cron), each shown only while enabled.
- New asset `assets/js/wp-arzo-command-palette.js` (deps: `wp-commands`, `wp-data`, `wp-element`,
  `wp-dom-ready`); enqueued admin-wide for `manage_options` users.

## [6.85.0] — 2026-07-02

### Added — "Configure →" discoverability after enabling a feature

- Turning a feature on now tells you **where to set it up**. Every configurable feature card
  shows a clear **Configure** link (to its page or Settings tab) while enabled, and enabling one
  pops an actionable toast — "{Feature} enabled — set it up next · **Configure →**" — that deep-
  links to the right destination. Previously only schema-settings features had a (cryptic gear)
  affordance, so page/tab-configured features (2FA, Email, Backups, Roles, Notifications, …) gave
  no hint where to go.
- Destinations come from a filterable `wp_arzo_feature_manage_urls` map (Pro adds its own), and
  the toast component now supports an optional action link.

## [6.84.0] — 2026-07-02

### Changed — one Settings hub, fewer menus, prominent search

- Seven config pages are consolidated into a single **Settings** submenu with tabs:
  **Login Security · Roles · REST API Auth · Import/Export** (free) plus **Two-Factor ·
  Notifications · AI/MCP** (Pro, via the new `wp_arzo_settings_tabs` filter). The WP Arzo menu
  drops from ~16 items to ~10 — the rest keep their own menu only when they're a distinct
  destination (Dashboard, Activity Log, Email, Backups, Content Types, Custom Fields, Media
  Cleanup, Snippets, Advanced Tools).
- The **feature search** moved out of the header into a prominent full-width bar above the grid,
  and the **Setup Wizard** button is aligned cleanly in the header.
- Menu order stays grouped by use (monitor → content → developer → Settings → tools).

## [6.83.0] — 2026-07-02

### Added — advertise the AI / MCP Server (Pro)

- The locked Pro catalog now lists **AI / MCP Server** (Ops) — expose the site to AI agents
  (Claude, etc.) through a Model Context Protocol endpoint, authenticated with your REST API
  keys, with read tools plus confirm-gated, admin-enabled write tools.

## [6.82.0] — 2026-07-02

### Changed — menu order by use, Setup Wizard menu removed

- The WP Arzo submenu is reordered by how often each page is actually used: **Dashboard**,
  then monitor &amp; operate (**Activity Log, Email, Backups, Notifications**), then content
  (**Content Types, Custom Fields, Media Cleanup**), then security &amp; access (**Login
  Security, Two-Factor, Roles, REST API Auth**), then developer (**Snippets, Redirects,
  Cron**), with **Import / Export** and the **Advanced Tools** console last. Ordering is
  centralized in one rank map (Pro pages included), so it stays deterministic.
- The **Setup Wizard** no longer occupies a permanent menu slot — it's a one-time flow already
  reachable from the Dashboard's "Setup Wizard" button (and the first-run redirect). The page
  is still routable at the same URL; only the redundant menu entry is gone.

## [6.81.0] — 2026-07-02

### Added — advertise Notifications (Pro)

- The locked Pro catalog now lists **Notifications** (Ops) — push site events (security,
  backups, email failures, system changes) to Slack, Discord, **n8n (cloud or self-hosted)**,
  or any generic webhook, with per-channel event selection.

## [6.80.0] — 2026-07-02

### Added — targeted Import / Export

- **Snippets** — export all snippets to a portable JSON file and import them back (imported
  snippets are added **disabled** so you can review before enabling). Buttons on the Snippets page.
- **Email Log** — **Export CSV** (time, to, subject, connection, status, error).
- **Activity Log** — **Export CSV** (time, user, action, details, IP) on the free log view.
- **Config Import/Export** now also carries your **Email connections** (the multi-provider store),
  so a config export/import moves your SMTP/API providers with everything else.

## [6.79.0] — 2026-07-02

### Changed — one Activity Log page (Pro upgrades it in place)

- The **Activity Log** page is now the single audit surface. When the Pro **Advanced Audit
  Log** is active it upgrades this page in place (durable DB storage, advanced filters, CSV
  export, AJAX pagination) via the new `wp_arzo_activity_log_render` filter — no more a
  separate "Audit Log" menu item. Without Pro, the free capped-option log renders as before.
- The Activity Log page also unlocks when the Pro audit feature is enabled.

## [6.78.0] — 2026-07-02

### Changed — one Backups page with tabs (WPvivid-style)

- The **Backups** page is now tabbed: **Local snapshots** plus any off-site destinations
  (FTP / Google Drive / pCloud) that Pro registers via the new `wp_arzo_backup_destinations`
  filter — all managed in one place instead of a separate admin menu per destination.
- The Backups page now also unlocks when any Pro backup destination is enabled (not only the
  local snapshot features).

## [6.77.0] — 2026-07-02

### Changed — Two-Factor Authentication is now Pro-only (de-duplicated)

- Removed the free Two-Factor Authentication feature (and its bundled QR generator) — 2FA now
  lives solely in **WP Arzo Pro**, where it gains a scannable QR code at enrollment. This
  resolves a duplicate: 2FA was registered by both the free core and the new Pro module. The
  free core keeps advertising it as a locked **Pro** catalog card.

## [6.76.0] — 2026-07-02

### Changed — Email UX: sensible default tab + Brevo recommended

- The **Email** page now lists **Logs** first and lands there by default once you have a
  connection (with none, it defaults to **Connections** so you meet the setup wizard) — instead
  of always opening on Connections. This "most-useful-tab-first / state-aware default" is now a
  documented convention for every multi-tab page.
- **Brevo** is now the **Recommended** provider (green badge) and appears at the top of the
  provider picker + onboarding wizard — a reliable free-tier API sender that avoids SMTP
  port/auth headaches.
- Documented two structural conventions in CLAUDE.md: **consolidate menus** (group related
  config into one tabbed parent page rather than a menu per feature) and **keep the docs
  learning** (record new patterns/gotchas/dedup checks every change set).

## [6.75.0] — 2026-07-02

### Added — advertise Two-Factor Authentication (Pro)

- The locked Pro catalog now lists **Two-Factor Authentication** (Security) — authenticator-app
  TOTP second factor with recovery codes, strictly opt-in per user.

## [6.74.0] — 2026-07-02

### Added — advertise pCloud off-site backups (Pro)

- The locked Pro catalog now lists **Off-site Backups: pCloud** (Backup), matching the new Pro
  module (OAuth connect + automatic snapshot upload + retention + AJAX remote-file manager).

## [6.73.0] — 2026-07-02

### Added — advertise Google Drive off-site backups (Pro)

- The locked Pro catalog now lists **Off-site Backups: Google Drive** (Ops/Backup), matching
  the new Pro module (OAuth connect + automatic snapshot upload + retention + a remote-file
  manager).
- **Convention:** list/table pages that paginate, search, or filter should now use
  **AJAX (fetch → JSON, update in place)** rather than `?paged=` GET reloads — documented in
  CLAUDE.md and the feature-module skill.

## [6.72.1] — 2026-07-02

### Fixed — Activity Log noise

- Skip internal post types in status-transition logging (e.g. `wp_global_styles`,
  `wp_template*`) and internal taxonomies in term logging (e.g. `wp_theme`, `nav_menu`,
  `post_format`), so block-editor/theme plumbing no longer shows up as "Published" or
  "Term created" entries.

## [6.72.0] — 2026-07-02

### Changed — Activity Log now tracks all site activity (not just plugin activity)

- The Activity Log captures a much broader event set: **password resets & profile updates**;
  **post updates & permanent deletes** (in addition to publish/trash), **media uploads/deletes**,
  **category/tag create & delete**; **comments** (posted, approved/unapproved, spam, trashed,
  deleted); **plugin/theme deletes** and **install/update of plugins, themes & WordPress core**
  (via `upgrader_process_complete`); and **settings changes** to a curated set of important
  options, **menu edits**, and **site exports**. Noisy sources are guarded (autosaves/revisions
  skipped; only whitelisted options; status-transition edits not double-logged).
- Each event type has its own badge tone + icon. The Pro **Advanced Audit Log** mirrors all of
  these into its DB table automatically (via `wp_arzo_activity_recorded`), so its filters/export
  cover the full activity set with no extra code.
- New settings toggles let you scope what's logged: **authentication, users, content, comments,
  plugins & themes, settings & site changes** (the settings schema now renders correctly, too).

## [6.71.0] — 2026-07-02

### Changed — Clean feature pages (no brand header or sidebar)

- Every feature-owned admin page (Email, Snippets, Backups, Media Cleanup, Activity Log,
  Login Security, REST API Auth, Roles, Import/Export, and per-feature Settings) now renders
  **only its own content** — the WP Arzo brand header and the left sidebar are gone from these
  pages, matching the Audit Log / Redirects layout. Navigate between pages via WordPress's own
  submenu. Only the main **Dashboard** hub keeps the brand header + the category-filter rail.
- `render_shell_open()`/`render_shell_close()` now no-op unless passed categories (dashboard
  only), so feature pages are a plain `.wrap.wpa-admin`. This is now a documented convention
  for all new feature pages.

## [6.70.0] — 2026-07-02

### Added — `wp_arzo_activity_recorded` extension hook

- The Activity Log engine now fires `do_action('wp_arzo_activity_recorded', $entry)` after
  every recorded event. This is the extension point the new Pro **Advanced Audit Log** uses
  to mirror events into a durable, searchable database table (retention, filters, CSV export).
- Advertised the Pro **Advanced Audit Log** in the locked Pro catalog (Ops & Monitoring).

## [6.69.0] — 2026-07-02

### Added — Email onboarding wizard (first-run)

- When the **Email → Connections** tab has no connections yet, it now shows a guided
  **first-run wizard** instead of a bare empty state: a 4-step flow — **Provider → Configure
  → Test → Done** — with a progress stepper, all in the wpa- design system (SureMail
  onboarding reference).
- Pick a provider from the card grid, fill the schema-driven form inline, **Save & continue**
  (creates the connection), then **send a test** to the admin address (with skip), and finish
  to the connections list. Reuses the existing provider registry, field builder and
  `wp_arzo_conn_save` / `wp_arzo_conn_test` AJAX — no new endpoints.
- Completes the SureMail-style Email rebuild (Phases 1–4): connections manager (v6.64) →
  unified tabbed hub (v6.66) → N-step fallback engine (v6.67) → Logs upgrade (v6.68) →
  onboarding wizard (v6.69).

## [6.68.0] — 2026-07-02

### Added — Email Logs upgrade (detail drawer, filters, deliverability)

- The **Logs** tab gets a proper inspector: a **deliverability bar** (delivered %),
  a **per-connection breakdown** (which connection sent/failed how many — surfacing the
  connection each message was delivered by, recorded since v6.67), and a new **Connection**
  column in the table.
- **Search + status filter** toolbar — filter the log live by recipient / subject /
  connection and by Sent / Failed, with an empty-state when nothing matches.
- **Row-click detail drawer** (reuses `.wpa-drawer`) showing the full email — status, time,
  To, Subject, Connection, error, headers and body — with **Resend** moved into the drawer.
  Details load on demand via a new `wp_arzo_email_log_detail` AJAX endpoint (capability +
  nonce gated), so the page stays light. Rows are keyboard-accessible (Enter/Space, Esc).

## [6.67.0] — 2026-07-02

### Changed — Email: real N-step fallback engine across mixed SMTP + API

- The live send path is rebuilt into a single `pre_wp_mail` **orchestrator** that owns the
  whole delivery: it parses the message once (From/Cc/Bcc/Reply-To/Content-Type/charset/
  boundary/custom headers/attachments, faithfully to WP core) and then walks the **full
  ordered connection chain** — primary → every fallback — trying each until one delivers.
- **Unified SMTP + API retry.** Previously an API failure (via `pre_wp_mail`) and an SMTP
  failure (via `wp_mail_failed`) were separate single-step paths, so a mixed chain like
  `[SendGrid, SMTP, SMTP]` could not walk past the first fallback. Now a message failing on
  any transport transparently retries on the next connection regardless of its transport.
- **Correct return value.** Because the chain is driven entirely from `pre_wp_mail` (never
  the lossy `wp_mail_failed` retry), `wp_mail()` now returns the true delivery result — a
  message that a fallback rescued reports success to the caller.
- **Records which connection delivered.** The engine remembers the delivering connection and
  fires `wp_arzo_email_delivered`; the **Email Log** stamps each entry with that connection.
- **Attachments** are routed to SMTP connections (API providers, which can't carry them, are
  skipped); if every connection is API-only for an attachment message, delivery defers to
  WordPress's native transport rather than being dropped.
- Per-connection **Test email** now runs through the same deliver path, so a passing test
  genuinely exercises the connection the live chain would use.

## [6.66.0] — 2026-07-01

### Changed — Email: one unified hub with tabs (SureMail-style)
- The separate **Email** and **Email Log** submenus are merged into a **single Email page**
  with in-page tabs: **Connections** (the provider-card manager), **Logs** (the email log +
  resend/clear), and **Settings** (failure-alert). One menu item, all email tooling in one
  place — matching SureMail’s layout, in the WP Arzo design system. New reusable `.wpa-tabs`
  component + `list` icon.

## [6.65.0] — 2026-07-01

### Changed — Snippets: Advanced-Scripts-style editor app
- The **Snippets** page is rebuilt into a full editor app: a **CodeMirror** syntax-highlighted
  code editor (dark theme, line numbers, mode switches automatically with the snippet Type —
  PHP/CSS/JS/HTML) as the centrepiece, a compact meta panel (Title, **Description** (new),
  Type, Run on, Priority, Active), and a **right-hand snippet list** with inline active
  toggles and click-to-edit — all on one screen. Reference: erropix Advanced Scripts.
- Snippets gain an optional **Description** field. The editor uses WordPress’s bundled
  CodeMirror (`wp_enqueue_code_editor`), so it respects the user’s “disable syntax
  highlighting” profile setting and adds no third-party editor.

## [6.64.0] — 2026-07-01

### Added — Email: multi-connection provider manager (SureMail-style UX)
- A new **WP Arzo → Email** page replaces the old single SMTP form with a **provider-card
  picker** (Custom SMTP, Gmail, Outlook, Zoho, Yahoo, Fastmail, Amazon SES, Mailjet,
  SendGrid, Brevo, Mailgun, Postmark) → a **slide-in config drawer** with each provider’s
  own fields (SMTP presets auto-applied; SES host built from region).
- **Multiple named connections** with a **Primary + ordered fallbacks**: the primary sends
  your mail and the next connection is tried automatically if it fails. Make-primary,
  edit, delete, and a **per-connection Test send** (independent of `wp_mail`).
- Legacy single-SMTP settings are **auto-migrated** into a connection on upgrade, so mail
  keeps flowing without reconfiguration. New reusable `.wpa-modal` / `.wpa-drawer` /
  provider-card components.
- The **Email Delivery** feature (was “Advanced SMTP & Email API”) now owns just the on/off
  switch + the all-connections-failed alert email; delivery config lives on the Email page.

## [6.63.0] — 2026-07-01

### Changed — Pro catalog
- Note the new **Custom Dashboard presets** in the Admin Branding placeholder description
  (the real Pro module ships five ready-made welcome-panel templates).

## [6.62.0] — 2026-07-01

### Added — SMTP provider presets (auto-fill)
- The Advanced SMTP method gains a **Provider preset** selector — pick Gmail, Microsoft 365,
  Yahoo, Zoho, iCloud, Fastmail, Amazon SES, SendGrid, Mailgun, Brevo, Postmark or Mailjet and
  the **host, port and encryption auto-fill** instantly (choose “Custom” to type them yourself).
  Powered by a new reusable `wpArzo.setSelectValue()` helper that also refreshes the custom
  select UI.

## [6.61.0] — 2026-07-01

### Added — Login Security page: active-lockouts dashboard
- New **WP Arzo → Login Security** admin page (shown while “Limit Login Attempts” is enabled)
  lists every **currently locked-out IP** — with the attempted username, when it was locked,
  and the time remaining — and lets you **Unlock** any one or **Unlock all** with a click.
  Backed by a self-healing lockout registry (the lock transient stays the source of truth).

## [6.60.0] — 2026-07-01

### Added — Limit Login Attempts: trusted-IP allowlist + lockout alerts
- **Trusted IP allowlist**: list your own IPs or CIDR ranges (e.g. `203.0.113.0/24`) that are
  never counted or locked out — so a strict lockout policy can’t lock *you* out. Full IPv4 &
  IPv6 CIDR matching.
- **Lockout alert email** (opt-in): get an email — to a recipient you choose (defaults to the
  admin) — whenever an IP is locked out, with the IP, attempted username, duration and time.

## [6.59.0] — 2026-07-01

### Added — Snippets: load-order priority
- Each snippet now has a **Priority** (1–9999, default 10). PHP snippets run in ascending
  priority order; CSS/JS/HTML snippets use it as their `wp_head`/`wp_footer`/`admin_*` hook
  priority — so you can control which snippet loads first when order matters. Shown as a new
  column in the Snippets list and editable in the snippet form. Existing snippets default to 10.

## [6.58.0] — 2026-07-01

### Added — Temporary Logins: branded email invites + last-IP tracking
- **Email invite**: the console's Temporary Logins tab now sends a proper branded `wp_mail`
  invitation (site name, role, one-tap login link, expiry, optional personal note) instead of
  just opening a `mailto:` draft. Capability + nonce gated; refuses to email a disabled,
  expired or exhausted link.
- **Last-IP column**: each successful sign-in now records the client IP, shown alongside the
  login count and last-login time so you can see where a link was used from.

## [6.57.0] — 2026-07-01

### Changed — Pro catalog
- Advertise the new Pro **Text Replacement (White-label)** module (locked PRO card for free
  users) and tidy the Admin Branding placeholder description (toolbar/CSS moved to free).

## [6.56.0] — 2026-07-01

### Changed — Clean Up Admin Bar is now the single, complete toolbar-cleanup owner
- Expanded **Clean Up Admin Bar** from 4 to **9 toggles**: WordPress logo, **site name / Visit
  Site**, comments, updates, “New”, **Customize**, **search**, the **Help tab**, and the
  **“Howdy,” greeting** — studied against White Label CMS + Branda and made more complete than
  both. Verified live.
- This consolidates all toolbar/greeting cleanup here (free), so nothing is duplicated across
  features — the Pro Admin Branding feature drops its overlapping toolbar controls (see Pro
  v1.18.0). Toolbar cleanup + “Howdy” removal are now **free**.

## [6.55.0] — 2026-07-01

### Added — Two-Factor: role-enforcement policy (+ `multiselect` field type)
- 2FA now has a **settings screen** with **"Require 2FA for these roles"** (a multi-select of
  roles). Users in an enforced role who haven't enrolled are held on their profile (where 2FA
  is set up) until they do — the profile and WP Arzo pages stay reachable, and
  `WP_ARZO_2FA_DISABLE` / the emergency tool remain the lockout escape. Verified: enforce →
  redirect → set up or revert.
- New reusable **`multiselect`** settings field type (renders `.wpa-check` chips, saves an
  array) in the core settings renderer — also used by the Pro Admin Branding controls.

## [6.54.0] — 2026-07-01

### Changed — Email delivery unified into one FREE feature
- **Advanced SMTP and API Email Providers are now a single free feature** ("Advanced SMTP &
  Email API"). A **Delivery method** selector chooses SMTP server (with the existing backup
  connection + failover + failure alerts) or a **provider API** (SendGrid / Brevo / Mailgun);
  `show_if` reveals only the relevant fields. The provider-API sending engine moved from the
  Pro add-on into the free core — **API email is now free** and the Pro `email_api` module is
  removed. (Re-select your provider + key under *WP Arzo → the SMTP feature settings* if you
  had the old Pro API-email configured.)

## [6.53.0] — 2026-07-01

### Added — Two-Factor: QR-code enrollment
- 2FA enrollment now shows a **scannable QR code** for the authenticator secret (with the
  manual key as a fallback), instead of only a raw `otpauth://` string. The QR is generated
  **offline** by a bundled generator — **no external service** is contacted (privacy).
- New `includes/lib/class-wp-arzo-qr.php` — a small wrapper (`WP_Arzo_QR::data_uri()` /
  `html_table()`) around the MIT-licensed "QRCode for PHP" library by Kazuhiko Arase
  (bundled, class-prefixed; GD PNG with an HTML-table fallback when GD is unavailable).
- First step of the 2FA overhaul; a dedicated settings screen + role-enforcement policy
  follow.

## [6.52.0] — 2026-07-01

### Added — `.wpa-collapse` accordion component
- New reusable **collapse/accordion** component (`.wpa-collapse` + `wpArzo.initCollapses`)
  for progressive-disclosure sections in long forms (click a header to expand/collapse,
  chevron rotates, keyboard + `aria-expanded`). Used by the Pro Content Types builder to
  hide "Advanced options" behind a collapse.

## [6.51.0] — 2026-07-01

### Fixed
- **Advanced Tools console: unreadable active tab.** The broad `.container a` accent-link
  override was hijacking the nav tabs — the active tab rendered green text on its green
  fill (invisible) and inactive tabs were all green. Tabs now own their colors (active =
  dark text on accent; inactive = muted, accent on hover).

### Changed — Emergency Recovery page matches the dashboard
- The Emergency Recovery header is now the **exact same brand bar** as the dashboard/console
  (logo + "WP Arzo" + "by Yasir Shabbir" + version + GitHub) — **removed** the "Recovery
  Mode" badge and the header Logout button.
- The emergency version now **reads from the main plugin header** (no more hard-coded `2.4`),
  so it always matches the installed version even when WP is down.

## [6.50.0] — 2026-07-01

### Added — Conditional settings fields (`show_if`)
- Settings fields can now **reveal based on another field's value** — a schema field may
  declare `'show_if' => array('field' => 'x', 'value' => 'y')` (or a list of conditions, ALL
  of which must match). Toggled live in JS; hidden fields still submit, so switching back
  preserves values. Applied to **SMTP** (username/password appear only with auth; the whole
  backup section only when the backup connection is enabled; backup credentials need both
  backup + backup-auth; the failure address only when notifications are on) and to the Pro
  **API Email Providers** (Mailgun domain/region show only for Mailgun, etc.).

### Changed
- **Removed the dashboard sidebar collapse/expand toggle** — the rail is a compact
  category filter and didn't need it.
- **Emergency Recovery page** now matches the dashboard/console **brand bar** (logo + "WP
  Arzo" + a "Recovery Mode" pill + version + GitHub + Logout); dropped the redundant `<h1>`
  and fixed the GitHub URL.

## [6.49.0] — 2026-07-01

### Added — `.wpa-check` checkbox chips (component)
- New reusable **`.wpa-checks` / `.wpa-check`** component: a row of selectable pill chips for
  multi-checkbox fields (e.g. CPT "Supports", "Attach to post types"), replacing ad-hoc
  inline-styled checkbox lists. Active chips light with the brand accent; strictly tokens.
  Used by the Pro Content Types / Custom Fields builders for design-system consistency.

## [6.48.0] — 2026-07-01

### Changed
- **Deterministic WP Arzo admin-menu order.** Added `order_submenu()` (admin_menu:999) that
  sorts the whole WP Arzo submenu — free **and** Pro pages — by a fixed slug→rank map, since
  the native `add_submenu_page` `$position` argument is unreliable when submenus register at
  different hook priorities (the Pro add-on registers later). Final order: Dashboard →
  Content Types → Custom Fields → Snippets → Media Cleanup → Redirects → Email Log → Backups →
  Cron → Activity Log → REST API Auth → Roles → Import/Export → Advanced Tools → Setup Wizard.

## [6.47.0] — 2026-07-01

### Changed — Dashboard nav simplified; pages are WP-admin menus
- **Removed the "Pages" group from the dashboard left rail.** The rail is now purely a
  **feature-category filter** for the grid; its label is renamed **"Pages/Categories" →
  "Browse"**. Page-owning features (Backups, Roles, Content Types, …) are reached from the
  **native WP-admin menu** under *WP Arzo*, not a duplicated in-page nav.
- Feature pages (Backups, Email Log, Roles, …) now render **full-width** (no empty rail) —
  the rail only appears on the Dashboard. Added `.wpa-shell--full`.
- **Ordered the WP Arzo admin submenus deliberately** via explicit `add_submenu_page`
  positions, leaving gaps for the Pro add-on's pages to interleave logically.
- Retired the `wp_arzo_admin_page_tabs` filter (no longer needed now that the rail doesn't
  list pages).

## [6.46.0] — 2026-07-01

### Fixed — Pro feature production-readiness (audit follow-up)
- **`media_folders` ID collision resolved.** The free core no longer registers a Media
  Folders module (it was an incomplete stub with no folder-creation UI, and it collided
  with the Pro module under the same id). **Media Folders is now Pro-only** — the complete
  nested folder manager ships in WP Arzo Pro; the free tier advertises it as a locked **PRO**
  card via the Pro catalog. (Removed `class-feature-media-folders.php` + its registration.)
- **Pro pages now appear in the dashboard sidebar.** Added a `wp_arzo_admin_page_tabs` filter
  so add-ons (WP Arzo Pro) can surface their page-owning features (Content Types, Custom
  Fields, Media Folders, Redirects, Cron) in the WP Arzo left nav — previously they were only
  reachable via the native wp-admin submenu and easy to miss ("Pro looks like it does
  nothing"). Each injected tab self-gates on its feature id (shown only while enabled).

## [6.45.0] — 2026-07-01

### Changed — "More from Yasir Shabbir" promo cards redesigned
- The cross-promotion area is now **visually set apart from the plugin's own UI** so it reads
  as a curated recommendation, not a feature: a **"Spotlight" eyebrow** + subtitle ("separate
  from this plugin"), an **accent-tinted card surface with an accent rail**, soft elevation,
  and a **hover lift / accent glow**.
- Each card is now a **single full-area link** (whole-card click target, keyboard focusable
  with a brand focus ring) instead of a small button, and carries an **explicit external-link
  affordance** — an "open in new tab" icon plus a screen-reader-only "(opens in a new tab)".
- Cards carry a **category badge** (PRO / SERVICE) for quick scanning. All strictly design
  tokens (no hard-coded colors; dark text on the accent fill).

## [6.44.0] — 2026-07-01

### Changed — Advanced Tools console header + Pro promo
- Removed the redundant **"WP Arzo - Administration Suite"** `<h1>` from the standalone
  console — the brand bar already identifies the page.
- **Even vertical rhythm**: the gap between the brand bar and the tab strip now matches the
  gap between the tabs and the page content (one shared `--arzo-space-5` cadence).
- The dashboard **"WP Arzo Pro" cross-promo card is hidden when the Pro add-on is active**
  (nothing left to upsell; the License card already shows "Pro active"). The generic
  "Need a custom build?" promo still shows.

## [6.43.0] — 2026-07-01

### Added — Automatic updates from GitHub Releases
- The free plugin now **updates itself from GitHub Releases** — no wordpress.org needed. When
  a newer release exists, WP Arzo shows a normal plugin update (update badge, one-click
  update, the per-plugin **auto-update** toggle, and a View-details modal). Engine:
  `includes/class-wp-arzo-updater.php` (cached GitHub API check; renames the extracted folder
  to the install slug so updates replace in place).
- New CI workflow `.github/workflows/release.yml`: every pushed `vX.Y.Z` tag **builds a clean
  `wp-arzo.zip`** and **publishes the GitHub Release** (notes auto-extracted from this
  changelog), which the updater serves as the update package.

## [6.42.0] — 2026-07-01

### Changed — Setup Wizard polish + brand-token consistency
- New **welcome / splash** first step: WP Arzo logo, a one-line value prop, three highlight
  cards (Faster / Safer / In control), and a developer credit linking yasirshabbir.com.
- Wizard **inputs and checkboxes are now branded** (dark fields + accent checkboxes) — they
  no longer fall back to wp-admin's white controls (the wizard lives outside `.wpa-admin`).
- On the manual-features step, **Back / Continue moved to the top**, and the inner toggle
  **scrollbar was removed** (the page uses the normal browser scroll).
- **Dark text on accent/primary backgrounds everywhere** — fixed remaining white-on-green
  spots in the console (pagination + select hover). Native checkboxes/radios across the whole
  plugin now use `accent-color: var(--arzo-accent)`.
- **Removed the “Leads” admin page.** Wizard opt-ins are still collected — stored and emailed
  to the developer (`wp_arzo_lead_email`) — but there's no Leads screen in the site's admin.

## [6.41.0] — 2026-07-01

### Added — Content & media features (free)
- **Content Order** (`content_order`): **drag-and-drop ordering** of posts, pages, and any
  custom post type right in the admin list (writes `menu_order`); enabled types are then
  ordered by `menu_order` in the admin list **and** on the front end. Per-post-type toggles.
  Verified live (jQuery-UI sortable wired on the Pages list).
- **Image SEO** (`image_seo`): auto-fill an empty image **Alt text** from the filename, and
  **convert underscores to hyphens** in uploaded filenames (two independent toggles).
- **Disable Archives** (`disable_archives`): turn off **category / tag / author / date**
  archive pages on the front end (each an independent toggle → 404).

## [6.40.0] — 2026-07-01

### Fixed — Database manager auto-connects (no more login prompt)
- The bundled **AdminNeo** database tool now opens **already connected** — no login screen.
  AdminNeo blocked our iframe with `X-Frame-Options: DENY` (now relaxed to `SAMEORIGIN` in the
  vendored file) and its strict CSP blocked injected scripts, so the **parent console page**
  (same-origin, WP-gated) auto-submits AdminNeo's own login form, which falls back to the
  site's wp-config DB credentials. Verified live. ("Open full screen" still shows AdminNeo's
  one-click prefilled login since there's no parent frame there.)

### Changed — Dashboard polish
- **Feature Manager** header redesigned with an **activation progress bar** (X of Y active · %).
- **License** card: the **Activate** and **Get Pro** buttons now fill the card 50 / 50.
- **Role Manager**: the **Add a role** box moved above the roles table for quicker access.

## [6.39.0] — 2026-07-01

### Added — Two-Factor Authentication (free, opt-in)
- New **Two-Factor Authentication** feature (`two_factor`, Security): **TOTP** (Google
  Authenticator / Authy / 1Password / etc.) plus single-use **recovery codes**, enrolled
  **per user** from the profile screen. The TOTP engine is hand-rolled (no library) and
  verified against the **RFC 6238** test vectors.
- The login flow is challenged only for users who opt in. **Two lockout escapes:** recovery
  codes, and a `WP_ARZO_2FA_DISABLE` constant (in `wp-config.php`) — plus the WP Arzo
  emergency tool can clear a user's 2FA meta. Added to the **Fortress** preset.

> ⚠️ 2FA changes the login flow. The feature is **off by default** and the challenge code
> can't be exercised in this dev environment — **enrol a test user and verify login +
> recovery on staging before enabling it on a production site.**

## [6.38.0] — 2026-07-01

### Added — Media tools (now free)
- **Media Folders** (`media_folders`) is now a **free** feature (was a Pro placeholder):
  organise the library into nestable folders via a private taxonomy, with a **folder filter**
  on the media list view, a **per-file folder selector**, and a **bulk “Move to folder”**
  action. Purely additive — it only tags attachments, never moves or deletes files.
- **Prevent Duplicate Uploads** (`media_replace`): when you upload a file that already exists
  (match by **name**, or **name + size**), the upload is skipped with a message pointing to the
  existing item — so you don't pile up duplicates. It modifies/deletes **nothing** (the safe
  way to "replace instead of duplicate"); to swap an item's contents, edit it in the library.

> Note: Media Folders moved from Pro to free — the Pro repo's catalog/version should be
> updated to match. Both media features touch the media library; verify on a real WP install.

## [6.37.0] — 2026-07-01

### Changed — Advanced Tools console brand consistency
- Re-toned the console to match the dashboard's design system: the legacy “rainbow” action
  buttons (purple download, green view, Bootstrap info/success/etc.) now use **brand tokens**
  — subtle secondary buttons, accent primary/success, brand amber/error — with no
  hard-coded hover colors. Links read as the brand accent and focus uses the brand ring (no
  default/browser blue). (Header + scrollbar were already unified in 6.30 / 6.29.)

## [6.36.0] — 2026-07-01

### Added — Bulk enable/disable per category
- Each category on the Feature Manager dashboard now has an **“All” master toggle** that
  enables or disables every *available* feature in that group in one click (locked Pro
  features are skipped). Especially handy for **Advanced Tools** — flip the whole console on
  or off at once. Child toggles + cards update live; page-owning features trigger a refresh.

## [6.35.0] — 2026-07-01

### Changed — Full-screen, multi-step Setup Wizard
- The Setup Wizard is now a modern **full-screen, multi-step** experience (it hides the
  wp-admin chrome and shows a progress bar): **Welcome → Choose a preset** (or **Configure
  manually** = every feature grouped with live toggles) **→ Stay in touch → Finish**.
- New optional **lead capture** step with a **privacy/terms opt-in** (links your site's
  privacy policy). With consent, the contact is stored locally and emailed to the developer
  (`apply_filters('wp_arzo_lead_email', …)`, default `leads@yasirshabbir.com`). A **Leads**
  admin page (WP Arzo → Leads) lists captured opt-ins; the option is removed on uninstall.
- Everything uses strict brand tokens; presets remain additive (never disable what you set).

## [6.34.0] — 2026-07-01

### Fixed
- **WP 6.9+ notice** “Function WP_Scripts::add was called incorrectly … wp-auth-check …
  heartbeat”. The Heartbeat Control feature deregistered `heartbeat` but left core's
  `wp-auth-check` (which depends on it) dangling; it now also drops `wp-auth-check` and
  stops core from enqueuing it, at enqueue time.

### Changed — Strict brand colors + consistent widths
- Removed wp-admin **blue** leaking into the UI: links now read as the brand accent, the
  brand focus ring replaces wp-admin's blue `a:focus` glow (including the sidebar), the info
  token is now a brand teal (no more blue “N features” badges), and the console's blue
  `.btn-edit` uses brand tokens.
- **Card/form widths are consistent** — settings & snippet forms fill the content column
  instead of being arbitrarily narrower than the header. Documented the rule (and the
  no-blue rule) in `CLAUDE.md` + `design.md`.
- **Branding**: the “by Yasir Shabbir” line (dashboard, console, emergency tool) now links to
  https://yasirshabbir.com instead of an email address.
- **Config Import / Export** is now a permanent tool — always available in the sidebar, no
  dashboard toggle.

## [6.33.0] — 2026-07-01

### Added — Full database manager (AdminNeo)
- The Advanced Tools **Database** tool is now a complete database manager powered by
  **AdminNeo** (bundled, GPL-2.0) — browse and edit rows, run SQL, export/import, manage
  indexes/foreign keys, and more — replacing the lightweight table viewer (kept as a
  fallback if the library is removed).
- **Security:** AdminNeo can never be reached unauthenticated. Its file is guarded (refuses
  to run unless WP Arzo's WP-gated `loader.php` unlocks it), and `loader.php` boots WordPress
  and requires `manage_options` on **every** request before auto-connecting with the site's
  own wp-config DB credentials. The `Database` console toggle still gates the whole tool.
  Apache `.htaccess` denies direct access as defense-in-depth; the connection config is
  generated at runtime and git-ignored, and removed on uninstall.
- Note: admins already had arbitrary-SQL access in this tool, so AdminNeo is a far better UI,
  not a new privilege.

## [6.32.0] — 2026-07-01

### Changed — Temporary Login links (replaces the old Quick Login)
- The Advanced Tools **Quick Login** tab is now a proper **Temporary Login links** manager
  (engine: `includes/class-wp-arzo-temp-login.php`). Create passwordless, **expiring,
  revocable** links that sign someone in as a chosen role — for support, clients, or
  developers — with no password sharing.
- Each link is a real WordPress user marked with `wp_arzo_tl_*` usermeta (64-byte CSPRNG
  token, role, absolute expiry, redirect, usage count, optional max-uses, status). Sign-in
  runs site-wide on `init`; expiry is enforced on click **and** on every page load.
- Safer than typical temp-login plugins: a **capability guard** stops you minting a link
  more privileged than your own account, **max-use limits are enforced**, and a **daily
  cron** deletes expired accounts (reassigning their content to the creator). Temp users
  can't manage users/profiles or reset passwords. Uninstall removes all temp users + the cron.
- **Removed** the old "Create Temporary Admin" / "Direct Admin Access" helpers and the
  redundant "Current Login Status" card (the Users tab already covers that).

## [6.31.0] — 2026-07-01

### Improved — Emergency Recovery reliability (works when WordPress is down)
- The standalone recovery tool (`wp-arzo-emergency/index.php`) now finds **wp-config.php**
  more reliably — it checks the WordPress root *and* the "split config" location one
  directory above the webroot, and only accepts a file that actually defines `DB_NAME`.
- The DB connection now parses `DB_HOST` into **host / TCP port / Unix socket**
  (`host:3306`, `host:/path.sock`) and honours `DB_CHARSET` — so it connects on hosts the
  old naive parser couldn't.
- The most sensitive writes (create admin, reset password, update URLs) now use **prepared
  statements**.
- New one-click **Repair & Recovery** actions: switch to a default `Twenty*` theme, restore
  the default `.htaccess` (backing up the current one), and clear all transients — alongside
  the existing safe-mode (deactivate all plugins but WP Arzo).
- **Discoverability**: the Site Modes emergency card now also offers a **Direct Link** (the
  recovery file's real URL) that keeps working even when WordPress can't load its rewrites —
  bookmark it for a true WSOD.

## [6.30.0] — 2026-07-01

### Changed — Consistent header across both surfaces
- The **Advanced Tools console** now uses the **same brand header** as the dashboard
  (`.wpa-brandbar`, promoted to the shared component stylesheet) — logo, "WP Arzo / by Yasir
  Shabbir", version, and a GitHub link — instead of the old bespoke `.developer-info` bar.
- Added a proper **GitHub mark icon** to `wp_arzo_icon()` and used it in both headers.
- The console header version now reads `WP_ARZO_VERSION` (no longer hard-coded).

## [6.29.0] — 2026-07-01

### Changed — Collapsible sidebar + branded scrollbars
- The dashboard's **left sidebar can now collapse** to an icon-only rail (toggle at the top;
  state persists in `localStorage`, tooltips show labels when collapsed) and **scrolls within
  itself** instead of stretching the page — so it stays usable as the nav grows.
- A **branded, dark scrollbar** now applies across the whole plugin — the dashboard, the
  standalone Advanced Tools console, and the emergency recovery tool — all from `--arzo-*`
  tokens. Scoped to WP Arzo surfaces so the rest of wp-admin keeps its native scrollbars.

## [6.28.0] — 2026-07-01

### Added — REST API Authentication
- New **REST API Authentication** feature (`rest_api_auth`, Security). Issue and revoke
  **API keys** that let external apps authenticate to the WP REST API as a chosen user —
  sent as `Authorization: Bearer …`, an `X-API-Key:` header, or the password of an HTTP
  Basic credential. Keys are stored **hashed** (only an 8-char lookup prefix in clear) and
  the full key is shown **once** at creation, just like Application Passwords.
- This is the **complement** of the existing "Restrict REST API" toggle (which only blocks
  anonymous access) — one keeps strangers out, the other lets trusted clients in.
- Per-feature settings: accept keys via header / via Basic / require HTTPS (all on by
  default). Dedicated **REST API Auth** page with a key table + "How to call" examples.

### Added — Role Manager
- New **Role Manager** feature (`role_manager`, Core controls). View every role (user count,
  capability count), **edit a role's capabilities** from a searchable toggle grid, and
  **add / clone / delete** custom roles — all through WordPress's Roles API (persisted to
  `wp_user_roles`). Built-in roles can't be deleted and the administrator role can't lose
  `manage_options` (lockout guard).

### Added — Config Import / Export
- New **Config Import / Export** feature (`config_io`, Core controls). **Export** your WP
  Arzo setup — feature toggles, feature settings, and code snippets — to a versioned JSON
  file, and **import** it on another site. Import takes a **safety snapshot** of the options
  table first (when Backups is available) and merges snippets by id. It never touches other
  plugins' options or your content.

### Conventions
- Documented two standing rules in `CLAUDE.md`, `design.md`, the feature-module skill, and
  the session brief: **check for duplicates before adding anything**, and **use a real icon
  on every feature card, page header, and sidebar nav item** (never emoji/none).

## [6.27.0] — 2026-07-01

### Changed — Dashboard navigation moved to a left sidebar
- The Feature Manager's top **tab bar** is now a **vertical left sidebar** that runs down
  every WP Arzo admin screen (Dashboard, Backups, Email Log, Snippets, Media Cleanup,
  Activity Log, Advanced Tools). Top tabs ran out of room as the suite grew; a sidebar
  scales vertically and reads cleanly with many entries.
- On the **dashboard**, the sidebar adds a **Categories** section mirroring the feature
  groups (Utilities, Core, Content, Media, …), each with a count. Clicking a category
  **filters the feature grid** to that group and scrolls it into view; "All features"
  clears the filter. The category filter and the existing search **compose** — a card
  shows only when it matches both.
- The sidebar is sticky on wide screens and **collapses to a horizontal band** above the
  content on narrow admin viewports (≤ 960px). Built entirely from `--arzo-*` design
  tokens; a new `grid` icon was added to `wp_arzo_icon()`.

## [6.26.0] — 2026-06-30

### Added — Setup Wizard & feature presets
- A new **Setup Wizard** (shown once on first activation, always reachable from
  **WP Arzo → Setup Wizard** and a button on the dashboard) lets you switch on a curated
  bundle of features in one click. Seven named presets:
  **Essentials** (smart starter), **Velocity** (performance), **Fortress** (security),
  **Creator** (publishing), **Growth** (marketing/analytics), **Command Center** (admin/dev
  power tools), and **The Works** (everything sensible).
- Presets are **additive** — they only *enable* their features, never disable what you've
  set. Any feature that isn't available (a Pro module without a license) is skipped and
  reported ("N Pro features skipped"). "The Works" enables every available feature except
  the opinionated "disable native WordPress behaviour" toggles, so the catch-all stays
  safe. Each card shows how many features it turns on (and any Pro count). Capability +
  nonce gated.

## [6.25.0] — 2026-06-30

### Added — Media Cleanup is now a toggle
- **Media Cleanup** is now a regular dashboard feature (group **Media**) with its own
  on/off toggle — completing "every feature has a toggle". Its admin page **and** its
  scan/delete AJAX endpoints are gated on the toggle (disabled → page hidden + a 403 on the
  scan/delete calls). It defaults to **off** (opt-in), in line with the dashboard's "enable
  only what you need" model — enable it to reveal the **WP Arzo → Media Cleanup** page.

### Changed — New plugin branding
- Replaced the old `yasir-shabbir-white-logo.png` with the new **WP Arzo** SVG marks:
  `assets/wp-arzo-glyph.svg` (transparent, for dark backgrounds) is used for the **admin
  menu icon** and the **emergency recovery tool**; `assets/wp-arzo-icon.svg` (rounded app
  badge) is used in the **dashboard brand bar** and the **standalone console header**. The
  old PNG was removed.

## [6.24.0] — 2026-06-30

### Added — Per-tool toggles for the Advanced Tools console
- Every tool in the standalone **Advanced Tools** console (Users, Database, File Manager,
  Plugins, Themes, Debug, Site Modes, Extra Options, Quick Login) is now an individual
  **dashboard toggle** under a new **Advanced Tools (Console)** group — so you can lock down
  dangerous tools (e.g. File Manager / Database) on production. *Site Info* stays always on
  as the console home.
- Disabling a tool **hides it from the console nav, shows a "Tool disabled" notice on its
  page, and — importantly — blocks its AJAX/file/DB operations** (the router gates the
  operation by tool, including the legacy `tab=ajax` calls and file downloads). Tools
  default to **enabled**, so existing behaviour is unchanged until you opt out. State lives
  in `wp_arzo_features` alongside every other feature toggle.

## [6.23.0] — 2026-06-30

### Changed — Feature pages appear only when the feature is on
- A feature's dedicated admin page (and its nav tab) is now **hidden until that feature's
  toggle is enabled**. **Email Log** needs *Email Log*, **Snippets** needs *Code Snippets*,
  **Activity Log** needs *Activity Log*, and **Backups** needs *Automated Snapshots* or
  *Scheduled Backups*. Enabling a feature that owns a page now reloads the dashboard so the
  menu/tabs update immediately (the toggle response signals `ownsPage`).
- Pro feature pages were already gated this way — they register inside the module's
  `boot()`, which only runs while the feature is enabled. *Media Cleanup* stays always
  available: it's an on-demand maintenance tool with no enable toggle.

## [6.22.0] — 2026-06-30

### Added — Pro feature showcase in the dashboard
- The Feature Manager now **advertises the full Pro catalog** even when the WP Arzo Pro
  add-on isn't installed: every premium module (18 of them — pixels, GA4/GTM/Ads, CPT/CCT
  builder, Custom Fields, Media Folders, Admin Branding, Redirects, Cron Manager, API
  email, FTP backups) appears as a card with a **PRO** badge and a locked **Unlock** CTA,
  in its proper group. Users can see exactly what the paid tier offers at a glance.
- These are inert **placeholders**: they carry no behaviour, can't be toggled (the
  freemium gate blocks it), and are **automatically suppressed** for any module the real
  Pro plugin already registers — so there's never a duplicate card. When Pro is active and
  licensed, the genuine, fully-functional modules take their place.

## [6.21.0] — 2026-06-30

### Added — Activity Log (free)
- A new **Activity Log** feature records an audit trail of key site events: logins
  (success / failed / logout), user created/deleted and role changes, content
  published/trashed, plugin activate/deactivate, theme switches, and WP Arzo feature
  toggles. Entries are stored in a capped option (no custom table) — newest first, with
  actor, IP, and a configurable retention (20–1000 entries).
- New **WP Arzo → Activity Log** page with an event-type filter, per-event status badges
  + icons, and a one-click **Clear log** (capability + nonce gated). Per-category toggles
  (auth / users / content / plugins) in the feature's settings let you log only what you
  need; disabled features add no hooks. Foundation for the Pro advanced audit log.

## [6.20.0] — 2026-06-30

### Changed — Custom Login Page UI/UX overhaul
- The Custom Login Page now fully styles the login form, not just colors: the **card,
  logo, labels, inputs (with accent focus ring), password show/hide button, remember-me
  checkbox, submit button, links, and message/error boxes** — across every login screen
  (sign-in, lost password, reset, register).
- New options: **input background**, **button text** color, and a **rounded corners**
  toggle. Additional CSS is now a raw `code` field (no longer tag-stripped on save).

## [6.19.1] — 2026-06-30

### Fixed (critical)
- **Custom Login URL produced PHP warnings on the login page** (`Undefined variable
  $user_login / $error in wp-login.php`). It loaded `wp-login.php` via `require` inside a
  method, leaving wp-login's internal variables undefined. Rewritten (ASE-style): wp-login.php
  now loads **natively** and is gated by a secret query key — the pretty `/slug` redirects to
  it, all login links carry the key, and direct `wp-login.php` access is bounced away. No more
  require, no warnings.

### Changed
- **Auto WebP Conversion → Auto WebP / WebM Conversion.** The per-upload confirmation is now
  reliable (polls for `wp.Uploader`, so it works in the media modal, block editor, and **bulk**
  uploads). Added optional **video → WebM** conversion via ffmpeg (size-capped, degrades safely
  when ffmpeg/exec is unavailable; best for short clips).

## [6.19.0] — 2026-06-30

### Added (free) — Scheduled Backups (41 free features total)
- **Scheduled Backups** — automatic database snapshots on a WP-Cron schedule
  (**daily / weekly / monthly**) with scope (options / full DB) and retention. Registers
  `weekly`/`monthly` cron schedules, keeps the event in sync with the chosen frequency,
  and clears the cron when the feature is disabled / the plugin is deactivated.

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
