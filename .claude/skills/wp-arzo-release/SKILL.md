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

### Pro add-on (separate repo `wp-arzo-pro`, branch `main`)

When releasing the Pro add-on, bump **both**:

- `wp-arzo-pro.php` — header `* Version:` line
- `wp-arzo-pro.php` — `define('WP_ARZO_PRO_VERSION', 'X.Y.Z');`

…and update the Pro repo's own `CHANGELOG.md`. **Cross-repo invariant:** if you add or
rename a Pro module, also update `wp_arzo_pro_feature_catalog()` in the free core
(`includes/features-registry/class-feature-pro-placeholders.php`) so free users still see
it as a locked **PRO** card, plus the free core's `ROADMAP.md`.

## 2. Update docs

- Add a dated section to `CHANGELOG.md` (Fixed / Security / Added), newest first.
- Mirror the highlights into the `== Changelog ==` block of `README.txt`.
- Update `README.md` / `CLAUDE.md` / `.claude/ROADMAP.md` if architecture, routing,
  conventions, or the feature set changed. Refresh the **Snapshot** line in `ROADMAP.md`
  and the version/state in [`.claude/start-session.md`](../../start-session.md).
- New console tab/operation? Map it in `wp_arzo_console_tool_map()` /
  `wp_arzo_console_tool_for_request()`. New feature with a dedicated admin page? Add it to
  `page_features()` in `class-wp-arzo-admin.php` so the page gates behind its toggle.

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
