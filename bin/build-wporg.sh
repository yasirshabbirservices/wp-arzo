#!/usr/bin/env bash
#
# Build the WordPress.org distribution zip for WP Arzo.
#
# The plugin directory forbids a plugin name/slug that BEGINS with "WP", and
# forbids a self-updater on directory-hosted plugins. This script turns the
# self-distributed plugin (display name "WP Arzo - Administration Suite", with
# the GitHub self-updater) into a compliant .org build:
#
#   * renames "WP Arzo - Administration Suite" -> "Arzo Administration Suite"
#     (permanent slug: arzo-administration-suite)
#   * strips every path listed in .distignore — the self-updater
#     (includes/class-wp-arzo-updater.php), AI/dev docs, CI and VCS files
#   * emits a clean, forward-slash zip with the slug as the top-level folder
#
# The GitHub/self-hosted channel (release.yml) is unchanged and KEEPS the
# updater + the "WP Arzo" name. Only this build is for wordpress.org.
#
# Usage:
#   bin/build-wporg.sh          # build build/arzo-administration-suite.zip from HEAD
#   bin/build-wporg.sh <ref>    # build from a specific git ref/tag
#
set -euo pipefail

REF="${1:-HEAD}"
SLUG="arzo-administration-suite"
CANON_NAME="WP Arzo - Administration Suite"
ORG_NAME="Arzo Administration Suite"

ROOT="$(git rev-parse --show-toplevel)"
OUT="$ROOT/build"
STAGE="$OUT/$SLUG"

rm -rf "$OUT"
mkdir -p "$STAGE"

# 1) Export the tracked tree at REF (no .git, no untracked cruft).
git -C "$ROOT" archive "$REF" | tar -x -C "$STAGE"

# 2) Strip everything listed in .distignore (comments / blank lines ignored).
if [ -f "$ROOT/.distignore" ]; then
  while IFS= read -r raw || [ -n "$raw" ]; do
    entry="${raw%%#*}"                                   # drop trailing comments
    entry="$(printf '%s' "$entry" | sed 's/^[[:space:]]*//; s/[[:space:]]*$//')"
    [ -z "$entry" ] && continue
    rm -rf "$STAGE/$entry"
  done < "$ROOT/.distignore"
fi

# 3) Rename the plugin for wp.org compliance (main-file header + readme title).
sed -i "s/^ \* Plugin Name: ${CANON_NAME}\$/ * Plugin Name: ${ORG_NAME}/" "$STAGE/wp-arzo.php"
sed -i "1s/^=== ${CANON_NAME} ===\$/=== ${ORG_NAME} ===/" "$STAGE/README.txt"

# 4) Sanity: the forbidden pieces must be gone, the name must be transformed.
if [ -e "$STAGE/includes/class-wp-arzo-updater.php" ]; then
  echo "ERROR: updater still present in .org build" >&2; exit 1
fi
if ! grep -q "^ \* Plugin Name: ${ORG_NAME}\$" "$STAGE/wp-arzo.php"; then
  echo "ERROR: plugin name was not transformed (header still starts with WP?)" >&2; exit 1
fi

# 5) Zip with forward slashes, no extra attributes, slug as the root folder.
( cd "$OUT" && zip -rqX "$SLUG.zip" "$SLUG" )

echo "Built $OUT/$SLUG.zip (name: '$ORG_NAME', slug: '$SLUG')"
