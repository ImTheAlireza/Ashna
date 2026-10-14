"""Check the PHP floor with the real WordPress requirement-validation function.

Requires official WP 6.8.3 wp-admin/includes/plugin.php in .cache/license-tests/wp.
Metadata/UI/dependency helpers are stubs; no plugin, vendor license or DB is loaded.
Use PHP_BINARY and PHP for php-wasm-cli as in tests/README.md.
"""
from pathlib import Path
import os
import subprocess

root = Path(__file__).resolve().parents[1]
source = root / '.cache/license-tests/wp/wp-admin/includes/plugin.php'
if not source.exists():
    raise SystemExit('Cache the official WP admin plugin.php first; see tests/README.md')
text = source.read_text()
start = text.index('function validate_plugin_requirements( $plugin ) {')
end = text.index('\n}\n', start) + len('\n}\n')
(root / '.cache/license-tests/validate-requirements.php').write_text('<?php\n' + text[start:end])
result = subprocess.run([os.environ.get('PHP_BINARY', 'php'), str(root / 'tests/php-requirement.php')],
                        capture_output=True, text=True, cwd=root)
output = result.stdout + result.stderr
if result.returncode != 0 or 'PASS requirement / PHP ' not in output or 'Fatal error' in output:
    raise SystemExit(output)
print(result.stdout.strip())
