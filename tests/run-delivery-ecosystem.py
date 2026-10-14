"""Run deterministic transport/adapter regressions, rejecting silent WASM PHP fatals."""
from pathlib import Path
import os
import subprocess
ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get('PHP_BINARY', 'php')
for name, count in [('delivery-integration.php', 10), ('ecosystem-integration.php', 4)]:
    result = subprocess.run([PHP, str(ROOT / 'tests' / name)], cwd=ROOT, capture_output=True, text=True)
    output = result.stdout + result.stderr
    print(output, end='')
    assert result.returncode == 0 and output.count('PASS ') == count and not any(marker in output for marker in ['Fatal error', 'Warning:', 'Deprecated:']), name
