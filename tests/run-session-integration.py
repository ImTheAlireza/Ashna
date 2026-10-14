"""Run isolated integration with real WP/WC session APIs; run prepare-session-tests.py first."""
from pathlib import Path
import os
import subprocess
ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get('PHP_BINARY', 'php')
result = subprocess.run([PHP, str(ROOT / 'tests/session-integration.php')], cwd=ROOT, capture_output=True, text=True)
output = result.stdout + result.stderr
print(output, end='')
assert result.returncode == 0 and 'Fatal error' not in output and 'Warning:' not in output and output.count('PASS ') == 7, 'Session integration failed'

for arguments, marker in [
    ([], 'PASS actual OTP/password/Passkey AJAX handlers'),
    (['-d', 'disable_functions=openssl_verify'], 'PASS Passkey endpoint fails closed'),
]:
    command = [PHP, *arguments, str(ROOT / 'tests/auth-endpoints.php')]
    if arguments:
        command.append('no-openssl')
    result = subprocess.run(command, cwd=ROOT, capture_output=True, text=True)
    output = result.stdout + result.stderr
    print(output, end='')
    assert result.returncode == 0 and marker in output and 'Fatal error' not in output and 'Warning:' not in output, 'Authentication endpoint integration failed'
