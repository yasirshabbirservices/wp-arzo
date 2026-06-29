# WP Arzo — Design System

The WP Arzo console is a **dark-mode-first** admin UI with a neon-green accent. All
visual primitives live in [`assets/css/design-tokens.css`](../assets/css/design-tokens.css)
and are consumed through CSS custom properties. Never hard-code hex values in feature
markup — reference a token so the palette stays centralized.

## Brand

- **Mode:** Dark UI core (`#121212` canvas, `#1e1e1e` panels).
- **Accent:** Neon green `#16e791` (hover `#0ea66b`).
- **Voice:** Utilitarian, fast, "emergency console" — high contrast, no decoration that
  costs legibility.

## Color tokens

| Token | Value | Use |
|-------|-------|-----|
| `--arzo-bg-dark` | `#121212` | Page / app canvas |
| `--arzo-bg-panel` | `#1e1e1e` | Cards, panels, modals |
| `--arzo-bg-hover` | `#2a2a2a` | Hover / raised surfaces |
| `--arzo-border` | `#333333` | Dividers, card borders |
| `--arzo-text-primary` | `#e0e0e0` | Body text |
| `--arzo-text-secondary` | `#999999` | Secondary / meta text |
| `--arzo-text-muted` | `#666666` | Disabled / hints |
| `--arzo-accent` | `#16e791` | Primary actions, active nav, highlights |
| `--arzo-accent-hover` | `#0ea66b` | Accent hover state |
| `--arzo-text-on-accent` | `#121212` | Text/icons on an accent fill |
| `--arzo-error` | `#ff4d4f` | Errors, destructive actions |
| `--arzo-warning` | `#faad14` | Warnings |
| `--arzo-success` | `#16e791` | Success states |
| `--arzo-info` | `#1890ff` | Informational states |

### Contrast rules (WCAG 2.2 AA)

- Accent text **on dark** (`#16e791` on `#121212`) ≈ 13:1 → passes AAA. Good for links,
  labels, and active states.
- Accent text **on white** ≈ 1.5:1 → **fails**. Never put green text on a light fill.
- When filling a button/badge with `--arzo-accent`, the label must use
  `--arzo-text-on-accent` (`#121212`), not white.

## Components

| Token | Value | Use |
|-------|-------|-----|
| `--arzo-radius` | `4px` | Global corner radius (buttons, cards, inputs, badges) |
| `--arzo-shadow` | `0 8px 32px rgba(0,0,0,.5)` | Modal / lightbox elevation |

- **Buttons:** accent fill for primary, `--arzo-error` for destructive (Delete,
  Deactivate), `--arzo-bg-hover` for secondary. Always pair destructive buttons with a
  JS `confirm()`.
- **Badges:** small pill, `--arzo-radius`. `success` = accent, `secondary` = muted.
- **Nav:** horizontal tab bar; the active tab uses the `.active` class (accent text /
  underline). Active state is computed server-side in
  [`wp-arzo-header.php`](../includes/wp-arzo-header.php).
- **Lightbox / modal:** centered panel on `--arzo-bg-panel` with `--arzo-shadow`; close
  on backdrop click and on `Escape` (wired in `assets/js/wp-arzo.js`).
- **Spinner:** accent top-border on a `--arzo-bg-hover` ring (see the loader in
  `wp_arzo_redirect_page()`).

## Typography

- System UI stack for the console (`'Segoe UI', Tahoma, Geneva, Verdana, sans-serif`).
- The public maintenance page (`includes/maintenance-frontend.php`) uses **Lato** from
  Google Fonts and per-mode accent colors (orange = maintenance, green = coming-soon,
  red = payment-required); it is intentionally independent of the console palette.
- Icons: Font Awesome 6.4 (loaded from CDN).

## Conventions for new UI

1. Pull every color/radius/shadow from a `--arzo-*` token. If a needed token doesn't
   exist, add it to `design-tokens.css` rather than inlining a hex value.
2. Keep the dark canvas; don't introduce light backgrounds without re-checking contrast.
3. Match the existing button / badge / card classes in
   [`assets/css/wp-arzo.css`](../assets/css/wp-arzo.css) instead of inventing new ones.
4. Load assets via `wp_arzo_get_asset_url()` so caching and minification stay correct.
5. Escape all dynamic values rendered into markup (`esc_html`, `esc_attr`, `esc_url`).
