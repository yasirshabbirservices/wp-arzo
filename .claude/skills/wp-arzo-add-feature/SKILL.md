---
name: wp-arzo-add-feature
description: Step-by-step playbook for adding a new tab or AJAX operation to the WP Arzo console without breaking routing or the JSON contract. Use when implementing a new feature, tab, or AJAX endpoint.
---

# Adding a feature or AJAX operation to WP Arzo

Read `wp-arzo-architecture` first. Then follow these steps exactly — most breakage comes
from registering a handler in only one of the required places.

## Add a new tab

1. **Create the feature file** `features/<name>.php`. Start with the standard guard:
   ```php
   <?php
   if (!defined('ABSPATH')) { exit; }
   ```
   Put page rendering inside a function and call it at the bottom (mirror existing files).
2. **Register the route** in `includes/wp-arzo-modular.php` `$feature_files`:
   ```php
   '<name>' => '<name>.php',
   ```
3. **Add a nav link** in `includes/wp-arzo-header.php` (the `.nav` block), using
   `admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=<name>')` and the active-class
   check `($action === '<name>')`.

## Add an AJAX operation

1. **Handle it inside the feature file** at the top, before any HTML:
   ```php
   if (isset($_GET['operation']) && $_GET['operation'] === 'my_op') {
       header('Content-Type: application/json');
       if (!current_user_can('manage_options') || !check_ajax_referer('wp_arzo_ajax', 'nonce', false)) {
           echo json_encode(['success' => false, 'message' => 'Security check failed']);
           exit;
       }
       // ... do work, then:
       echo json_encode(['success' => true, /* ... */]);
       exit;
   }
   ```
2. **Route the operation** in `includes/wp-arzo-modular.php` so the feature file is
   included for that op (add `my_op` to the relevant `in_array([...])` for the tab). A
   GET-only/read pagination op that the feature file already handles when its tab is
   routed may not need a new router entry — verify by tracing the include path.
3. **Send the request from JS** with the nonce:
   - In an inline feature `<script>`, append
     `formData.append('nonce', '<?php echo esc_js(wp_create_nonce('wp_arzo_ajax')); ?>');`
     (or `&nonce=<?php echo esc_js(wp_create_nonce('wp_arzo_ajax')); ?>` in the URL).
   - In `assets/js/wp-arzo.js`, use `wpArzoConfig.nonce`.
4. **Match the JSON shape** the JS reads exactly (key names + types).

## Verify before finishing

- `php -l features/<name>.php` (and any other touched PHP) — zero errors.
- `node --check assets/js/wp-arzo.js` if you changed the shared JS.
- Confirm the nonce action string matches between `wp_create_nonce` / `wp_nonce_field`
  and the corresponding `check_ajax_referer` / `wp_verify_nonce`.
- Read-only ops don't need a nonce; **every state-changing op does**.

## Don't

- Don't add a handler without routing it (silent fallthrough to HTML).
- Don't emit any output before an AJAX handler's `header('Content-Type: application/json')`.
- Don't trust `$_GET`/`$_POST` directly — `wp_unslash()` + sanitize, escape on output.
