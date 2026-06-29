---
name: wp-arzo-architecture
description: Explains how the WP Arzo console boots, routes requests, and exchanges JSON between the front-end and PHP. Use when navigating the codebase, tracing a request, or figuring out where a tab/operation is handled.
---

# WP Arzo Architecture

WP Arzo is a standalone admin console served through `admin-ajax.php`, not a normal admin
screen. Understand this flow before changing anything.

## Boot & gate

1. `wp-arzo.php` registers the **WP Arzo** menu page (`wp_arzo_redirect_page`) which opens
   `admin-ajax.php?action=wp_arzo_standalone` in a new tab.
2. `wp_ajax_wp_arzo_standalone` → `wp_arzo_handle_standalone()`:
   - First handles the one-time `?maintenance_access=<token>` emergency login **before**
     the capability gate (a logged-out admin must be able to use the link).
   - Then enforces `current_user_can('manage_options')`.
   - Includes `includes/wp-arzo-modular.php`.

## Router — `includes/wp-arzo-modular.php`

- Reads the current tab: `$_GET['tab'] ?? $_GET['action'] ?? 'info'`.
- **Early-exits** for AJAX/raw responses (file download, `elfinder_connector`, DB pages,
  plugin/theme toggles, debug ops, site-mode ops) — these run before any HTML.
- Includes `includes/wp-arzo-header.php` (opens `<html>`, prints nav, handles login POSTs
  and the users-page JSON endpoint, defines shared file helpers + the download handler).
- Routes `tab` to a file in `features/` via the `$feature_files` map.
- Emits `wpArzoConfig` (incl. the `wp_arzo_ajax` nonce) and loads `assets/js/wp-arzo.js`.

### Tab → feature map

`info`→site-info, `users`→users, `database`→database, `files`→files, `plugins`→plugins,
`themes`→themes, `debug`→debug, `site_modes`/`maintenance`→site-modes,
`extra_options`→extra-options, `login`→login.

`tab=ajax` is a legacy alias accepted alongside `tab=<feature>` by several handlers.

## Request shapes

- **Page render:** `…&tab=<feature>` → feature file echoes HTML into the console layout.
- **AJAX op:** `…&tab=<feature>&operation=<op>` (or `tab=ajax`) → handler sets
  `Content-Type: application/json`, echoes `json_encode([...])`, and `exit`s.

A handler that exists in a feature file but isn't routed (in `wp-arzo-modular.php` or
`wp-arzo-header.php`) will never run — the router will fall through to HTML instead.

## Front-end ↔ back-end contract

`assets/js/wp-arzo.js` and the inline `<script>` blocks in each feature file `fetch()`
JSON and read `data.success`. The #1 bug class here is a **field-name/type mismatch**
between the JS reader and the PHP response (e.g. `id` vs `ID`, a roles *string* vs
*array*). When editing either side, verify exact key names and types on both ends.

## Lifecycle (in `wp-arzo.php`)

- Activation: rewrite rule for `/wp-arzo/emergency/`, store version, invalidate OPcache.
- Deactivation: disable maintenance mode, clear `maintenance_access_*` transients.
- Uninstall: delete all `maintenance_tool_*` + `wp_arzo_*` options/transients and
  `arzo-safe.php`.
- `plugins_loaded` → `wp_arzo_maybe_upgrade_plugin()` re-invalidates OPcache on version
  change.
