"""Run the fast-failover engine tests (QC 3) with real WordPress hooks and WP_Error.

PHP_BINARY may point to php-wasm-cli; PHP selects the WASM PHP version.
Requires wp-includes/plugin.php, class-wp-hook.php and class-wp-error.php in
ASHNA_TEST_WP_PATH, which defaults to .cache/license-tests/wp (see tests/README.md).
Gateways are simulated; "hang" scenarios really wait, so the run takes ~35 seconds.
"""
from pathlib import Path
import os
import subprocess

ROOT = Path(__file__).resolve().parents[1]
WP = Path(os.environ.get('ASHNA_TEST_WP_PATH', ROOT / '.cache/license-tests/wp')).resolve()
PHP = os.environ.get('PHP_BINARY', 'php')
for file in ['plugin.php', 'class-wp-hook.php', 'class-wp-error.php']:
    if not (WP / 'wp-includes' / file).is_file():
        raise SystemExit('WordPress files missing (' + file + '); see tests/README.md')

result = subprocess.run([PHP, str(ROOT / 'tests/failover.php'), str(ROOT), str(WP)], capture_output=True, text=True, cwd=ROOT)
output = (result.stdout + result.stderr).strip()
print(output)
# Some WASM CLI versions exit 0 even after a PHP fatal error: require the final marker.
if result.returncode != 0 or not output.splitlines() or not output.splitlines()[-1].endswith('failover scenarios'):
    raise SystemExit('FAIL failover tests')
