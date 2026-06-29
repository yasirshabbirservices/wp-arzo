---
name: wp-arzo-release
description: Release checklist for WP Arzo — bump the version in every place it appears, update the changelog and readmes, and run the lint/verification steps. Use when cutting a new version or preparing a release.
---

# WP Arzo Release Checklist

## 1. Bump the version everywhere (must all match)

The version string lives in several places. Update all of them to the new `X.Y`:

- `wp-arzo.php` — the header `* Version:` line
- `wp-arzo.php` — `define('WP_ARZO_VERSION', 'X.Y');`
- `includes/wp-arzo-header.php` — the `vX.Y` shown next to the GitHub link
- `README.txt` — `Stable tag: X.Y` (and bump `Tested up to:` if validated)
- `README.md` — the "Current version" line

Quick scan for stragglers (ignore the Font Awesome `6.4.0` CDN string and elFinder libs):

```bash
grep -rn "<old-version>" wp-arzo.php includes README.md README.txt
```

## 2. Update docs

- Add a dated section to `CHANGELOG.md` (Fixed / Security / Added), newest first.
- Mirror the highlights into the `== Changelog ==` block of `README.txt`.
- Update `README.md` / `CLAUDE.md` if architecture, routing, or conventions changed.

## 3. Verify

```bash
# PHP syntax — must be clean on the supported floor (PHP 7.2+) and current PHP:
for f in wp-arzo.php includes/*.php features/*.php; do php -l "$f"; done

# JS syntax:
node --check assets/js/wp-arzo.js
```

- Confirm every nonce action string matches between its creator
  (`wp_create_nonce`/`wp_nonce_field`) and verifier
  (`check_ajax_referer`/`wp_verify_nonce`). See `wp-arzo-security`.
- If new options/transients were added, confirm they're in the uninstall cleanup in
  `wp-arzo.php`.

## 4. Package

- `.gitignore` excludes archives (`*.zip`, etc.) and local-only Claude state
  (`.claude/settings.local.json`) but **keeps** committed `.claude/` docs/skills.
- Exclude dev-only files from the distributed ZIP if you build one; ship the plugin
  folder as activated from `/wp-content/plugins/`.

## Notes

- Activation runs `flush_rewrite_rules()` and OPcache invalidation; a version change is
  also picked up on `plugins_loaded` via `wp_arzo_maybe_upgrade_plugin()`. After release,
  the Debug tab should show the stored version matching the header version.
