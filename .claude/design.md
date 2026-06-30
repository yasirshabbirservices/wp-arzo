# WP Arzo — Design System

A **dark-mode-first** admin UI with a neon-green accent. The single source of truth is
[`assets/css/design-tokens.css`](../assets/css/design-tokens.css) — load it **before**
any other stylesheet. Never hard-code a hex/size; reference a token. The reusable UI
primitives live in [`assets/css/wp-arzo-components.css`](../assets/css/wp-arzo-components.css)
(+ behavior in [`assets/js/wp-arzo-components.js`](../assets/js/wp-arzo-components.js)) and
icons in [`includes/wp-arzo-icons.php`](../includes/wp-arzo-icons.php).

> Token layers: use the canonical **`--arzo-*`** tokens in all new code. Legacy aliases
> (`--accent-color`, `--radius-global`, `--success-color`, …) are mapped in
> `design-tokens.css` for back-compat only — don't add new legacy names.

## Brand

- **Canvas** `#121212` (`--arzo-bg-dark`), **panels** `#1e1e1e` (`--arzo-bg-panel`).
- **Accent** neon green `#16e791` (`--arzo-accent`), hover `#0ea66b`.
- **Voice:** utilitarian, fast, "control room" — high contrast, no decoration that costs
  legibility.

## Color tokens

| Token | Use |
|-------|-----|
| `--arzo-bg-dark` / `--arzo-bg-panel` / `--arzo-bg-elev` / `--arzo-bg-hover` / `--arzo-bg-input` | Surfaces (canvas → raised → input) |
| `--arzo-border` / `--arzo-border-strong` | Dividers, control borders |
| `--arzo-text-strong` / `--arzo-text-primary` / `--arzo-text-secondary` / `--arzo-text-muted` | Text hierarchy |
| `--arzo-accent` / `--arzo-accent-hover` / `--arzo-accent-soft` / `--arzo-accent-ring` | Primary actions, active state, soft fills, focus ring |
| `--arzo-text-on-accent` | Text/icons on an accent fill (`#121212`) |
| `--arzo-success` / `--arzo-warning` / `--arzo-error` / `--arzo-info` / `--arzo-neutral` (+ `*-soft`) | Semantic states and their soft chip backgrounds |

### Contrast rules (WCAG 2.2 AA)

- Accent **on dark** ≈ 13:1 (AAA) — good for links, labels, active states.
- Accent **on white** ≈ 1.5:1 (**fails**) — never put accent text on a light fill.
- On an accent fill, labels use `--arzo-text-on-accent`, not white.

## Scales

- **Radius:** `--arzo-radius-sm` 4px · `--arzo-radius` 8px · `--arzo-radius-lg` 14px ·
  `--arzo-radius-pill`.
- **Spacing (4px base):** `--arzo-space-1` … `--arzo-space-10`.
- **Type:** family `--arzo-font` (Lato), mono `--arzo-font-mono`. Small sizes fixed
  (`--arzo-fs-xs` 11 … `--arzo-fs-md` 14); headings are **fluid** via `clamp()`
  (`--arzo-fs-lg`, `--arzo-fs-xl`, `--arzo-fs-2xl`) so they scale with the viewport.
- **Layout:** `--arzo-container` (fluid max-width) and `--arzo-card-min` (fluid grid
  column min) — prefer these + `clamp()`/`calc()` over magic numbers.
- **Elevation:** `--arzo-shadow-sm` / `--arzo-shadow` / `--arzo-shadow-lg`.
- **Motion:** `--arzo-transition-fast|''|slow` (auto-zeroed under
  `prefers-reduced-motion`).
- **Focus:** `--arzo-focus-ring` (3px accent ring) — applied to every interactive element
  via `:focus-visible`.
- **Z-index:** `--arzo-z-dropdown|sticky|modal|toast`.

## Components (`wpa-` namespace)

Compose these; do **not** write bespoke inline styles in features.

| Component | Class / API |
|-----------|-------------|
| Icon | `wp_arzo_icon('name', ['class'=>'wpa-icon', 'aria-label'=>'…'])` → inline SVG |
| Button | `.wpa-btn` + `--primary` / `--secondary` / `--ghost` / `--danger` / `--sm` / `--lg` / `--icon` / `--block` |
| Toggle | `.wpa-toggle` (modern switch; `role="switch"`); legacy `.switch`/`.slider` markup is also modernized |
| Select | `.wpa-select`; add `data-wpa-select` to a native `<select>` → JS upgrades it to an accessible listbox |
| Badge/Status | `.wpa-badge` + `--success` / `--error` / `--warning` / `--info` / `--neutral` (icon slot) |
| Card | `.wpa-card` + `__header` / `__title` / `__icon` / `__actions` |
| Field | `.wpa-field` + `__label` / `__help` / `__error`, `.wpa-input` |
| Toast | `wpArzo.toast(message, 'success'|'error'|'info')` (ARIA live region) |

All components: keyboard-operable, `:focus-visible` rings, AA contrast, RTL-friendly,
reduced-motion aware, branded.

## Icons

Use [`wp_arzo_icon()`](../includes/wp-arzo-icons.php) — a registry of 24×24 stroke SVGs
that inherit `currentColor`. **No emoji, no default browser glyphs.** Add new icons to
the registry rather than inlining one-offs. Decorative by default; pass `aria-label` when
the icon conveys meaning.

**Icons everywhere — including navigation.** Every feature card, dashboard page heading,
and **left sidebar nav item** carries an icon. A new feature module must implement
`icon()`; a new sidebar page must set an `icon` in `page_tabs()`
(`includes/admin/class-wp-arzo-admin.php`). Never ship a nav item, card, or page header
without one — add a fitting SVG to the registry if none exists.

## Conventions for new UI

1. Reference a `--arzo-*` token for every color/space/radius/shadow; add a token if one is
   missing rather than inlining a value.
2. Use toggles (not checkboxes) for on/off; use `.wpa-select` (not native `<select>`) for
   choices; use `wp_arzo_icon()` for every status/action glyph.
3. Compose `wpa-` components; match existing patterns instead of new bespoke CSS.
4. Keep the dark canvas; re-check contrast before any light surface.
5. Load assets via `wp_arzo_get_asset_url()`; escape all dynamic output.

## The public maintenance page

[`includes/maintenance-frontend.php`](../includes/maintenance-frontend.php) is a
standalone public page with its own per-mode accent (orange = maintenance, green =
coming-soon, red = payment) and Lato type. It is intentionally independent of the console
palette (migrating it onto the tokens is a Phase 0 follow-up).
