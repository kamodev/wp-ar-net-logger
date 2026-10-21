#!/usr/bin/env bash
# Build an installable plugin zip: dist/ar-net-logger-<version>.zip
# The zip contains a single "ar-net-logger/" folder with the runtime files only.
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
slug="ar-net-logger"
version="$(sed -n 's/^ \* Version: *//p' "$root/$slug.php" | tr -d '[:space:]')"

if [ -z "$version" ]; then
	echo "Could not read the plugin version from $slug.php" >&2
	exit 1
fi

build="$(mktemp -d)"
trap 'rm -rf "$build"' EXIT

mkdir -p "$build/$slug" "$root/dist"
cp -R "$root/$slug.php" "$root/uninstall.php" "$root/readme.txt" "$root/includes" "$root/assets" "$root/languages" "$build/$slug/"

zip_file="$root/dist/$slug-$version.zip"
rm -f "$zip_file"
(cd "$build" && zip -rqX "$zip_file" "$slug")

echo "Built $zip_file"
