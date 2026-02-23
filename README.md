## WP Arzo – Maintenance & Administration Suite

WP Arzo is a maintenance and administration toolkit for WordPress. It provides a standalone, modern UI for common maintenance workflows (site info, database, users, files, plugins, themes, site modes, debug tools, and quick login helpers).

This repository is public and intended for production use on shared hosting, VPS, cloud, and enterprise WordPress setups.

### Development checklist for future updates

#### Assets

- **Always load CSS/JS via the asset helper**  
  Use `wp_arzo_get_asset_url( 'assets/css/wp-arzo.css' )` / `wp_arzo_get_asset_url( 'assets/js/wp-arzo.js' )` instead of hard‑coding URLs or query parameters.
- **Rely on `filemtime()` for cache‑busting**  
  The asset helper already uses `filemtime()` with a safe fallback to `WP_ARZO_VERSION`. Do not add manual `?v=...` query strings.
- **Use minified builds for production**  
  If you ship minified assets, name them `*.min.css` / `*.min.js`. The helper automatically prefers these when `WP_ARZO_DEBUG` is `false`.
- **Keep new assets under `assets/`**  
  Place styles under `assets/css/` and scripts under `assets/js/`, and reference them only via the asset helper.

#### PHP & features

- **Keep versions in sync**  
  Ensure the plugin header `Version:` and the `WP_ARZO_VERSION` constant always match on release.
- **Prefix all options/transients cleanly**  
  Use clear prefixes such as `maintenance_tool_...` or `wp_arzo_...` for any new options/transients so they are easy to track and clean up.
- **Register cleanup for new persistent data**  
  When you introduce new long‑lived options or transients, add them to the uninstall cleanup logic so they are removed when the plugin is uninstalled.
- **Avoid raw SQL for options/transients**  
  Use `get_option()`, `update_option()`, `delete_option()`, `set_transient()`, and `delete_transient()` so object caches (Redis/Memcached) stay consistent.

#### Debugging & safety

- **Use the Debug tab to inspect the active instance**  
  The Debug screen exposes:
  - The plugin file and directory actually loaded.
  - Header vs stored version (`WP_ARZO_VERSION` vs `wp_arzo_version` option).
  - Whether OPcache is enabled and whether the plugin file is cached.
- **Verify OPcache behavior after updates**  
  On plugin updates, confirm that the compiled timestamp in the Debug screen is newer than (or aligned with) the file `mtime`. If not, investigate host‑level OPcache configuration.
- **When adding new admin pages, enqueue safely**  
  For classic WP admin pages (non‑standalone), enqueue assets via `admin_enqueue_scripts` using unique handles and the asset helper, e.g.:
  - `wp_enqueue_style( 'wp-arzo-admin', wp_arzo_get_asset_url( 'assets/css/wp-arzo.css' ), array(), null );`
  - `wp_enqueue_script( 'wp-arzo-admin', wp_arzo_get_asset_url( 'assets/js/wp-arzo.js' ), array( 'jquery' ), null, true );`
- **Prefer capability checks on all entry points**  
  Guard new AJAX actions or tools with appropriate capability checks (e.g. `current_user_can( 'manage_options' )`) to match the rest of the plugin.

