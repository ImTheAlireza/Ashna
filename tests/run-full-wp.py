"""Run Ashna test files inside a disposable full WordPress (php-wasm + SQLite works).

Usage:  PHP_BINARY=... python tests/run-full-wp.py [attacks] [session] [security]
                                                   [integrations] [scenarios]

No arguments runs every shipped suite (attacks, session, security, sso, integrations,
scenarios). ASHNA_TEST_WP_SITE points at a throw-away WordPress install with Ashna
active (default: .cache/wp-cli-site). The current source tree is copied into its
wp-content/plugins/ashna first, laid out like the no-license build.
Each tests/<name>.php must end by printing "... in <name> scenarios".
"""
from pathlib import Path
import os
import shutil
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
SITE = Path(os.environ.get('ASHNA_TEST_WP_SITE', ROOT / '.cache/wp-cli-site')).resolve()
PHP = os.environ.get('PHP_BINARY', 'php')

if not (SITE / 'wp-load.php').is_file():
    raise SystemExit('No disposable WordPress at ' + str(SITE) + ' (set ASHNA_TEST_WP_SITE).')


def sync_plugin():
    dest = SITE / 'wp-content/plugins/ashna'
    if dest.exists():
        shutil.rmtree(dest)
    dest.mkdir(parents=True)
    for folder in ['assets', 'includes', 'languages', 'templates']:
        shutil.copytree(ROOT / folder, dest / folder)
    for name in ['uninstall.php', 'README.md', 'index.php']:
        shutil.copy2(ROOT / name, dest / name)
    shutil.copy2(ROOT / 'scripts/templates/ashna-no-license.php', dest / 'ashna.php')


def run(name):
    boot = SITE / ('_ashna_test_' + name + '.php')
    boot.write_text(
        "<?php\n"
        "ob_start(); // keep headers unsent so wp_send_json() reports HTTP status codes\n"
        "define( 'WP_USE_THEMES', false );\n"
        "$_SERVER['HTTP_HOST'] = '127.0.0.1:9400';\n"
        "$_SERVER['REQUEST_URI'] = '/';\n"
        "$_SERVER['SERVER_NAME'] = '127.0.0.1';\n"
        "$_SERVER['REMOTE_ADDR'] = '203.0.113.7';\n"
        "require __DIR__ . '/wp-load.php';\n"
        "echo 'Full WordPress ', get_bloginfo( 'version' ), ' | PHP ', PHP_VERSION, ' | Ashna ', ASHNA_OTP_VERSION, \"\\n\";\n"
        "require " + repr(str(ROOT / 'tests' / (name + '.php'))) + ";\n"
    )
    try:
        result = subprocess.run([PHP, boot.name], capture_output=True, text=True, cwd=SITE)
    finally:
        boot.unlink()
    output = (result.stdout + result.stderr).strip()
    print(output)
    last = output.splitlines()[-1] if output else ''
    # Some WASM CLI versions exit 0 even after a PHP fatal error: require the final marker.
    if result.returncode != 0 or not last.endswith(name + ' scenarios') or ' 0 failed' not in last:
        return False
    return True


sync_plugin()
ALL_SUITES = ['attacks', 'session', 'security', 'sso', 'integrations', 'scenarios']
names = sys.argv[1:] or ALL_SUITES
failed = [n for n in names if not run(n)]
if failed:
    raise SystemExit('FAIL ' + ', '.join(failed))
