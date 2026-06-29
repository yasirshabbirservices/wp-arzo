---
name: wp-arzo-security
description: Security checklist and patterns for WP Arzo — capability checks, CSRF nonces, input sanitization, output escaping, path confinement, and safe config/SQL writes. Use when adding or reviewing any state-changing code in this plugin.
---

# WP Arzo Security Checklist

This console can edit files, run SQL, switch plugins/themes, create admins, and rewrite
server config. The page-level `manage_options` gate is necessary but **not sufficient** —
the dominant residual risk is CSRF against a logged-in admin, plus injection into files
and SQL. Apply all of the following to any state-changing code.

## Nonces (CSRF)

There are two established nonce families in this codebase — reuse them:

- **AJAX (fetch/JSON):** action `wp_arzo_ajax`, field `nonce`.
  - Emit: `wpArzoConfig.nonce` (set in `wp-arzo-modular.php`) or inline
    `wp_create_nonce('wp_arzo_ajax')`.
  - Verify: `check_ajax_referer('wp_arzo_ajax', 'nonce', false)`.
- **Form POSTs:** a per-feature action, e.g. `wp_arzo_login_action`,
  `wp_arzo_user_action`, `wp_arzo_db_query`, `wp_arzo_debug_settings`,
  `wp_arzo_php_limits`.
  - Emit: `wp_nonce_field('<action>', '<field>')`.
  - Verify: `wp_verify_nonce(wp_unslash($_POST['<field>']), '<action>')`.

Always pair the nonce check with `current_user_can('manage_options')` (or a more specific
cap like `activate_plugins` / `switch_themes`).

## Input / output

- `wp_unslash()` then sanitize **early**: `sanitize_text_field`, `sanitize_user`,
  `sanitize_email`, `wp_kses_post` (for HTML message fields), `intval`/`absint`.
- Escape **late** on output: `esc_html`, `esc_attr`, `esc_url`, `esc_js` (for values
  echoed into inline JS).
- Allow-list constrained values (roles, target config files, mode names) — never write
  arbitrary strings into privileged sinks.

## Dangerous sinks in this plugin (handle with care)

- **File download** (`includes/wp-arzo-header.php`): confine to `ABSPATH` with
  `realpath()` + prefix check. Never stream a path straight from `$_GET`.
- **File manager** (`features/files.php`): elFinder is rooted at `ABSPATH`; the
  `wp_arzo_elfinder_access` callback hides/denies dot-files. Keep it wired.
- **SQL console** (`features/database.php`): `wp_unslash` the query; backtick identifiers
  in helper queries. It intentionally runs arbitrary SQL — keep it nonce-gated.
- **wp-config.php writes** (`features/debug.php`): only ever write the literal
  `true`/`false`. The value is emitted as PHP — raw POST data = code injection.
- **Config/.htaccess/php.ini writes** (`features/extra-options.php`): validate values as
  size (`^-?\d+[KMG]?$`) or integer literals before writing; a newline can inject
  directives (`auto_prepend_file` → RCE). Allow-list the target file.
- **Auth changes** (temp admin, impersonate, emergency link): nonce-gate; bind emergency
  tokens to the creating user and make them single-use + time-limited.

## Review prompt

When reviewing a change, ask: *Is this state-changing? If so — does it re-check
capability, verify a nonce, sanitize input, escape output, and constrain any path / SQL /
config value to an allow-list?* If any answer is no, it's not done.
