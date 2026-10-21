#!/bin/bash
# TEST ONLY — prove the refactor changed nothing a visitor can see.
#
# Runs tests/load/golden.php (a fixed script of sign-in situations, printed with the random parts
# replaced by placeholders) twice against the *shipped* plugin build and once against the working
# tree, then diffs the three transcripts. The first two runs must be identical (the transcript is
# deterministic); the third must be identical to them.
#
# The baseline is whatever `git archive <ref>` gives. Run it before committing the change (HEAD is
# then still the baseline), or name the baseline yourself as the third argument — any ref works
# (a tag, a branch, a commit), e.g. the commit this one is built on.
#
# usage: bash tests/load/golden-compare.sh [<site-dir>] [<php-version>] [<baseline-ref>]
# exit code 0 = the working tree answers exactly like the baseline build.
set -u

SITE="${1:-.cache/matrix/wp64}"
PHPV="${2:-8.1}"
BASELINE="${3:-HEAD}"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

PHP_BINARY="$ROOT/.cache/phpwasm/node_modules/.bin/php-wasm-cli"
OUT="$ROOT/.cache/load/golden"
mkdir -p "$OUT"

SLUG="$(basename "$SITE")"
BOOT="$SITE/_golden.php"
PLUGIN="$SITE/wp-content/plugins/ashna"
HEAD_SRC="$ROOT/.cache/load/golden-head-src"

# The shipped build of the current commit, laid out like the no-license package.
rm -rf "$HEAD_SRC"
mkdir -p "$HEAD_SRC"
git -C "$ROOT" archive "$BASELINE" | tar -x -C "$HEAD_SRC"

install_build() {
	local from="$1"
	rm -rf "$PLUGIN"
	mkdir -p "$PLUGIN"
	cp -r "$from/assets" "$from/includes" "$from/languages" "$from/templates" "$PLUGIN"/
	cp "$from/uninstall.php" "$from/README.md" "$from/index.php" "$PLUGIN"/
	cp "$ROOT/scripts/templates/ashna-no-license.php" "$PLUGIN/ashna.php"
}

cat > "$BOOT" <<'PHP'
<?php
define( 'WP_USE_THEMES', false );
$_SERVER['HTTP_HOST']   = '127.0.0.1:9400';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '203.0.113.50';
require __DIR__ . '/wp-load.php';
require '@REPO@/tests/load/golden.php';
PHP

sed -i "s|@REPO@|$ROOT|" "$BOOT"

run() {
	( cd "$SITE" && PHP="$PHPV" "$PHP_BINARY" _golden.php 2>&1 | grep -v '^\s*at ' ) > "$1"
}

echo "== $SLUG: baseline build ($BASELINE)"
install_build "$HEAD_SRC"
run "$OUT/$SLUG-before-1.txt"
run "$OUT/$SLUG-before-2.txt"

echo "== $SLUG: working tree"
python "$ROOT/tests/load/sync-site.py" "$SITE" > /dev/null
run "$OUT/$SLUG-after.txt"

status=0
steps="$(grep -c '^== ' "$OUT/$SLUG-before-1.txt" || true)"
if [ "${steps:-0}" -lt 20 ]; then
	echo "   TRANSCRIPT EMPTY/TOO SHORT ($steps steps) — the sandbox site did not run it"
	exit 1
fi

if diff -q "$OUT/$SLUG-before-1.txt" "$OUT/$SLUG-before-2.txt" > /dev/null; then
	echo "   deterministic: two runs of the baseline build are identical"
else
	echo "   UNSTABLE TRANSCRIPT: two runs of the same build differ"
	status=1
fi

if diff -u "$OUT/$SLUG-before-1.txt" "$OUT/$SLUG-after.txt" > "$OUT/$SLUG.diff"; then
	echo "   GOLDEN MATCH: $steps answers byte-identical before/after"
else
	echo "   GOLDEN DIFFERS — see $OUT/$SLUG.diff"
	head -40 "$OUT/$SLUG.diff"
	status=1
fi

exit "$status"
