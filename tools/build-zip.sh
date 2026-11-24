#!/usr/bin/env bash
#
# Builds the installable WordPress plugin ZIP for AgriXpert.
#
# Produces dist/agrixpert-<version>.zip with the correct internal layout
# for WordPress's "Upload Plugin" flow: a single top-level "agrixpert/"
# folder containing agrixpert.php at its root. Version is read straight
# out of the plugin header so the ZIP filename and the in-plugin version
# constant can never drift apart.
#
# Usage: tools/build-zip.sh   (run from the repo root)

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="$REPO_ROOT/agrixpert"
DIST_DIR="$REPO_ROOT/dist"

if [ ! -f "$PLUGIN_DIR/agrixpert.php" ]; then
	echo "error: $PLUGIN_DIR/agrixpert.php not found — run this from the repo root" >&2
	exit 1
fi

VERSION=$(grep -m1 "^ \* Version:" "$PLUGIN_DIR/agrixpert.php" | sed -E 's/^ \* Version:[[:space:]]*//')
if [ -z "$VERSION" ]; then
	echo "error: could not read Version from agrixpert.php header" >&2
	exit 1
fi

mkdir -p "$DIST_DIR"
ZIP_PATH="$DIST_DIR/agrixpert-${VERSION}.zip"
rm -f "$ZIP_PATH"

# Build from a clean staging copy so stray local files (.DS_Store, editor
# swap files, anything not meant to ship) never end up in the ZIP, without
# relying on zip's -x excludes matching every possible case.
STAGE_DIR=$(mktemp -d)
trap 'rm -rf "$STAGE_DIR"' EXIT

cp -R "$PLUGIN_DIR" "$STAGE_DIR/agrixpert"
find "$STAGE_DIR/agrixpert" \( -name ".DS_Store" -o -name "*.swp" -o -name "Thumbs.db" \) -delete

( cd "$STAGE_DIR" && zip -rq "$ZIP_PATH" agrixpert )

echo "Built: $ZIP_PATH"
echo "Version: $VERSION"
unzip -l "$ZIP_PATH" | tail -n +4 | head -n -2
