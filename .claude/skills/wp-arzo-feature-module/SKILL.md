---
name: wp-arzo-feature-module
description: How to add a feature to the WP Arzo dashboard via the feature registry (a self-contained module with a toggle, optional schema-driven settings, and hooks that boot only when enabled). Use when adding a site-enhancement feature, a dashboard toggle, or a Pro module.
---

# Adding a WP Arzo feature module

This is the **modern** way to add functionality (the dashboard toggle grid). For the
legacy standalone console tabs, see `wp-arzo-add-feature` instead.

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

## Rules

- `boot()` runs on `plugins_loaded` **only if the feature is enabled** — so a disabled
  feature costs nothing on the front end. Hook `init`/`admin_init`/etc. from inside it.
- Never do work at construct time; only in `boot()`.
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
