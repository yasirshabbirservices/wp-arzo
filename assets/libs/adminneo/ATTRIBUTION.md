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

**When updating AdminNeo, re-download the same compiled build and re-apply this one-line
guard** (place it immediately after the `use …;` import block, before `abstract class Plugin`).
`adminneo-config.php` is generated at runtime by `loader.php` from the site's wp-config DB
credentials and is not committed.
