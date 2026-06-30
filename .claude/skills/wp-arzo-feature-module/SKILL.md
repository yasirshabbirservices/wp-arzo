---
name: wp-arzo-feature-module
description: How to add a feature to the WP Arzo dashboard via the feature registry (a self-contained module with a toggle, optional schema-driven settings, and hooks that boot only when enabled). Use when adding a site-enhancement feature, a dashboard toggle, or a Pro module.
---

# Adding a WP Arzo feature module

This is the **modern** way to add functionality (the dashboard toggle grid). For the
legacy standalone console tabs, see `wp-arzo-add-feature` instead.

## 0. Check for duplicates FIRST (don't skip)

Before writing a new module, confirm nothing already covers it:
- Grep `includes/features-registry/` and `wp_arzo_bootstrap_features()` (wp-arzo.php) for
  the behaviour and likely `id()`s.
- Check `wp_arzo_pro_feature_catalog()` (Pro placeholders) so you don't reinvent a planned
  Pro module.
- Watch for **near-neighbours**: e.g. `disable_rest_api_guests` ("Restrict REST API",
  *blocks* anonymous) vs. `rest_api_auth` ("REST API Authentication", *issues keys*). If one
  exists, extend it — or make the new title + description spell out the distinction.

## 1. Create the module

Add a file under `includes/features-registry/` (one class, or group related ones in a
`class-features-<group>.php` file). Extend `WP_Arzo_Feature`:

```php
class WP_Arzo_Feature_My_Thing extends WP_Arzo_Feature {
    public function id()          { return 'my_thing'; }              // unique, snake_case
    public function title()       { return 'My Thing'; }
    public function description() { return 'One-line, shown on the card.'; }
    public function group()       { return 'utilities'; }            // see registry groups
    public function tier()        { return 'free'; }                 // 'free' | 'pro'
    public function icon()        { return 'bolt'; }                 // wp_arzo_icon() key
    public function settings_schema() {                              // optional
        return array(
            array('key' => 'mode', 'type' => 'select', 'label' => 'Mode',
                  'default' => 'a', 'options' => array('a' => 'A', 'b' => 'B')),
        );
    }
    public function boot() {                                          // ONLY runs when enabled
        add_filter('some_hook', function () {
            $mode = $this->get_setting('mode', 'a');                 // reads saved settings
            // ...
        });
    }
}
```

Groups: `utilities, content, media, marketing, email, security, branding, developer,
backup, ai, core, ops`. Field types: `text, number, textarea, select, toggle`.

## 2. Register it

In `wp_arzo_bootstrap_features()` (wp-arzo.php):
```php
$registry->register(new WP_Arzo_Feature_My_Thing());
```
(Pro add-ons register via the `wp_arzo_register_features` action instead of editing core.)

**Also wire these when they apply:**
- **Pro module** → add it to `wp_arzo_pro_feature_catalog()`
  (`includes/features-registry/class-feature-pro-placeholders.php`) so the free core
  advertises it as a locked PRO card; and to the Pro repo's registration list + changelog.
- **Dedicated admin page** (its own submenu) → add a `PAGE_*` const, an `add_submenu_page`
  (gated by `page_visible()`), the page→feature mapping in `page_features()`, a
  `render_*()` that wraps content in `render_shell_open(<key>)` / `render_shell_close()`,
  and a **`page_tabs()` entry with an `icon`** so it shows in the left sidebar — all in
  `class-wp-arzo-admin.php`. (See `rest_api_auth` / `role_manager` / `config_io` for a
  template.)
- **Worth featuring in a preset?** → add the id to the relevant preset in
  `class-wp-arzo-setup-wizard.php` (`presets()`); opinionated "disable WP behaviour"
  toggles also belong in that file's `opinionated()` denylist so "The Works" skips them.

## Rules

- `boot()` runs on `plugins_loaded` **only if the feature is enabled** — so a disabled
  feature costs nothing on the front end. Hook `init`/`admin_init`/etc. from inside it.
- Never do work at construct time; only in `boot()`.
- Always implement `icon()` with a real `wp_arzo_icon()` key (add an SVG to
  `wp_arzo_icon_paths()` if none fits) — every card and nav item must have an icon.
- Read settings with `$this->get_setting('key', $default)` (falls back to the schema
  default). The dashboard renders + sanitizes settings from `settings_schema()` — no custom
  settings UI needed for standard field types.
- State changes you add must be capability- + nonce-checked (see `wp-arzo-security`). The
  toggle itself is already gated.
- Pro features: set `tier() => 'pro'`; the dashboard shows a PRO chip and the
  `wp_arzo_feature_is_available` filter (Freemius) gates them.
- Destructive features should be safe by default and reversible; the Automated Snapshots
  feature already snapshots the DB before any toggle.

## Verify

- `php -l` the new file(s); confirm the class registers (unique `id()`).
- Open **WP Arzo → Dashboard**: the card appears in its group, toggles persist (AJAX +
  toast), and any settings gear saves.
- Enabling/disabling actually applies/reverts the behavior on the front end.
