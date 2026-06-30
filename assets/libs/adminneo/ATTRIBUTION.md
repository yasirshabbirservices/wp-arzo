# AdminNeo (bundled)

`adminneo.php` is **AdminNeo** — a powerful single-file database manager — vendored here
to power WP Arzo's built-in Database tool.

- Project: https://www.adminneo.org/
- Source: https://github.com/adminneo-org/adminneo
- Version: 5.5.0 (compiled build: driver `mysql`, language `en`)
- Authors: Peter Knut; Jakub Vrána
- License: **dual-licensed Apache-2.0 OR GPL-2.0** — WP Arzo uses it under **GPL-2.0**,
  matching WordPress.

## Local modification

A single guard line was injected near the top of `adminneo.php` so it cannot run unless
WP Arzo's WP-gated `loader.php` has authenticated an administrator:

```php
if (!\defined("WP_ARZO_ADMINNEO_OK")) { \http_response_code(403); exit("Forbidden"); }
```

A second change makes embedding work: AdminNeo's `header("X-Frame-Options: DENY")` is changed
to `header("X-Frame-Options: SAMEORIGIN")` so the WP-gated, same-origin console iframe can
display it. (Zero-click connect is done from the parent console page — `features/database.php`
auto-submits AdminNeo's own login form, which falls back to the configured wp-config DB creds.)

**When updating AdminNeo, re-download the same compiled build and re-apply BOTH local changes**
(the `WP_ARZO_ADMINNEO_OK` guard after the `use …;` block, and `X-Frame-Options: SAMEORIGIN`).
`adminneo-config.php` is generated at runtime by `loader.php` from the site's wp-config DB
credentials and is not committed.
