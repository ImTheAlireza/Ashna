#!/bin/bash
# TEST ONLY: build a disposable WordPress (SQLite) with Ashna active. See tests/README.md.
# usage: make-site.sh <wp-version> <site-dir> <official|old> <php> [theme] [permalinks]
set -e
V=$1; SITE=$2; DRIVER=$3; PHPV=$4; THEME=${5:-}; PERMA=${6:-/%postname%/}
ROOT=$(cd "$(dirname "$0")/.." && pwd)
CORE=$ROOT/.cache/wpcore
PHPBIN=$ROOT/.cache/phpwasm/node_modules/.bin/php-wasm-cli
rm -rf "$SITE" && mkdir -p "$SITE"
cp -a "$CORE/$V/." "$SITE/"
rm -f "$SITE/wp-config-sample.php"
mkdir -p "$SITE/wp-content/database" "$SITE/wp-content/plugins"
if [ "$DRIVER" = official ]; then
  cp -a "$CORE/sqlite-plugin" "$SITE/wp-content/plugins/plugin-sqlite-database-integration"
  sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$SITE/wp-content/plugins/plugin-sqlite-database-integration#" \
      -e "s#{SQLITE_PLUGIN}#plugin-sqlite-database-integration/load.php#" \
      "$CORE/sqlite-plugin/db.copy" > "$SITE/wp-content/db.php"
else
  cp "$CORE/wp-sqlite-db/src/db.php" "$SITE/wp-content/db.php"
fi
MEM=""
[ "$DRIVER" = old ] && MEM="define( 'WP_MEMORY_LIMIT', '1536M' ); // the old wp-sqlite-db drop-in leaks ~47 KB per write query"
cat > "$SITE/wp-config.php" <<EOF
<?php
define( 'DB_NAME', 'wp' ); define( 'DB_USER', '' ); define( 'DB_PASSWORD', '' ); define( 'DB_HOST', '' );
define( 'DB_CHARSET', 'utf8mb4' ); define( 'DB_COLLATE', '' );
define( 'DB_DIR', __DIR__ . '/wp-content/database/' ); define( 'DB_FILE', '.ht.sqlite' );
define( 'AUTH_KEY', 'qa-1' ); define( 'SECURE_AUTH_KEY', 'qa-2' ); define( 'LOGGED_IN_KEY', 'qa-3' ); define( 'NONCE_KEY', 'qa-4' );
define( 'AUTH_SALT', 'qa-5' ); define( 'SECURE_AUTH_SALT', 'qa-6' ); define( 'LOGGED_IN_SALT', 'qa-7' ); define( 'NONCE_SALT', 'qa-8' );
\$table_prefix = 'wp_';
$MEM
define( 'WP_DEBUG', true ); define( 'WP_DEBUG_DISPLAY', true ); define( 'WP_DEBUG_LOG', false );
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
require_once ABSPATH . 'wp-settings.php';
EOF
# Ashna (no-license layout, same as tests/run-full-wp.py), header lowered for PHP 7.4 activation.
python3 - "$SITE" "$ROOT" <<'PY'
import shutil, sys, os, hashlib
from pathlib import Path
from zipfile import ZipFile
SITE = Path(sys.argv[1]); ROOT = Path(sys.argv[2])
src = open(ROOT / 'tests/run-full-wp.py').read()
start = src.index('def sync_plugin'); end = src.index('\ndef ', start + 5)
ns = {'shutil': shutil, 'Path': Path, 'ROOT': ROOT, 'SITE': SITE,
      'os': os, 'hashlib': hashlib, 'ZipFile': ZipFile}
exec(src[start:end], ns); ns['sync_plugin']()
PY
sed -i -E '0,/(Requires PHP:\s*)[0-9.]+/s//\17.4/' "$SITE/wp-content/plugins/ashna/ashna.php"
cat > "$SITE/_install.php" <<EOF
<?php
define( 'WP_INSTALLING', true );
\$_SERVER['HTTP_HOST'] = '127.0.0.1:9400'; \$_SERVER['REQUEST_URI'] = '/'; \$_SERVER['SERVER_NAME'] = '127.0.0.1'; \$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require __DIR__ . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
\$r = wp_install( 'Ashna QA $V', 'admin', 'admin@example.com', true, '', 'qa-password' );
update_option( 'siteurl', 'http://127.0.0.1:9400' ); update_option( 'home', 'http://127.0.0.1:9400' );
if ( '$THEME' ) { switch_theme( '$THEME' ); }
update_option( 'permalink_structure', '$PERMA' );
echo 'installed WP ', get_bloginfo( 'version' ), ' | PHP ', PHP_VERSION, ' | theme ', get_stylesheet(), "\n";
EOF
cat > "$SITE/_activate.php" <<'EOF'
<?php
$_SERVER['HTTP_HOST'] = '127.0.0.1:9400'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['SERVER_NAME'] = '127.0.0.1'; $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require __DIR__ . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$a = activate_plugin( 'ashna/ashna.php' );
global $wpdb;
echo 'activate: ', is_wp_error( $a ) ? $a->get_error_message() : 'ok', ' | logs table: ', $wpdb->get_var( "SELECT name FROM sqlite_master WHERE name = '{$wpdb->prefix}ashna_otp_logs'" ) ? 'yes' : 'MISSING', ' | schema ', get_option( 'ashna_otp_db_version' ), "\n";
EOF
cd "$SITE" && PHP=$PHPV $PHPBIN _install.php 2>&1 | grep -v "^\s*at " | tail -3
PHP=$PHPV $PHPBIN _activate.php 2>&1 | grep -v "^\s*at " | tail -3
rm -f _install.php _activate.php
