# WP Arzo — Product & Engineering Roadmap

> Status: proposed plan. Default architecture choices are marked **[default]** — any can
> be changed. We build **feature by feature**; each feature starts from a reference plugin
> the maintainer shares, and we ship a better/faster/more-accessible version.

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
- **SMTP delivery** (multi-provider) [F basic / P advanced]
- **Email logs + analytics** (sent/failed, open/click where possible, resend, search) [P]

### Security
- Limit login attempts [F], Disable XML-RPC [F], Obfuscate author slugs [F],
  Disable REST for guests [F], Disable file editor [F]
- **Custom login URL / hide wp-login** [F]
- 2FA, login firewall, activity/audit log, password policies [P]
- Password-protect site/pages [F basic / P advanced]

### Branding / White-label admin
- **Custom login page designer** (logo, bg, colors, layouts) [P]
- **Custom admin UI/UX** (color schemes, fonts, menu styling) [P]
- **Custom dashboard page** (replace all default boxes with branded widgets when active)
  [P]
- Login/Logout menu item, redirect after login/logout [F]

### Core controls
- Disable Gutenberg, comments, feeds, all updates, REST API [F]

### Developer tools
- **Code Snippets Manager** [F core / P advanced] — safe PHP/CSS/JS/HTML snippets with
  scope (admin/front/everywhere), run-location, conditional logic, error-guard
  (auto-disable a snippet that fatals), import/export, and a code editor (CodeMirror).
  Built on DataTable + Modal + custom Select components.

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

## 8. Open decisions (maintainer to confirm; defaults assumed)

- Tech stack (vanilla **[default]** vs React vs hybrid).
- Console fate (keep as Advanced Tools **[default]** vs fold in).
- Exact Free/Pro line (defaults in §4).
- Pro licensing mechanism (own server / Freemius / Lemon Squeezy / EDD).
- i18n/text-domain readiness + wp.org compliance timing.
- AI provider(s) for the AI layer (Anthropic / OpenAI / others) — default
  provider-agnostic with BYO key; pick the default + whether to offer a managed option.
