"""Diagnostic-tool regression tests; real WP hooks, mocked storage/auth.

Use the hook setup from tests/README.md, PHP_BINARY for php-wasm-cli,
and PHP=7.4/8.2/8.3 to select the WASM version. No real license is loaded.
"""
import os
from pathlib import Path
import subprocess

root = Path(__file__).resolve().parents[1]
php = os.environ.get('PHP_BINARY', 'php')
scenarios = ['unauthorized', 'bad_nonce', 'array_nonce', 'wrong_target', 'bulk',
             'ordinary_page', 'success', 'scrape', 'silent_exit', 'fatal',
             'inactive', 'redaction', 'page']
for scenario in scenarios:
    result = subprocess.run([php, str(root / 'tests/activation-diagnostics.php'), scenario],
                            capture_output=True, text=True, cwd=root)
    output = result.stdout + result.stderr
    # WASM can return exit code zero on PHP Fatal, so assert the shutdown marker.
    if f'PASS {scenario}' not in output or (scenario != 'fatal' and ('Fatal error' in output or result.returncode != 0)):
        raise SystemExit(f'FAIL {scenario}\n{output}')
    print(f'PASS {scenario}')
print(f'PASS all {len(scenarios)} diagnostic scenarios')

result = subprocess.run([php, str(root / 'tests/ioncube-diagnostics.php')],
                        capture_output=True, text=True, cwd=root)
output = result.stdout + result.stderr
if result.returncode != 0 or 'PASS all 14 ionCube message fixtures' not in output or 'Fatal error' in output:
    raise SystemExit(f'FAIL ionCube parser\n{output}')
print('PASS all 14 ionCube message fixtures')

private_scenarios = ['default', 'expired', 'other_user', 'bad_nonce', 'scrape',
                     'not_fatal', 'not_ioncube', 'capture', 'long', 'first_only',
                     'arm', 'arm_no_consent', 'arm_bad_nonce', 'page_privacy', 'uninstall']
for scenario in private_scenarios:
    result = subprocess.run([php, str(root / 'tests/diagnostics-private-message.php'), scenario],
                            capture_output=True, text=True, cwd=root)
    output = result.stdout + result.stderr
    if result.returncode != 0 or f'PASS private {scenario}' not in output or 'Fatal error' in output:
        raise SystemExit(f'FAIL private {scenario}\n{output}')
    print(f'PASS private {scenario}')
