"""Exercise the exact distributed PHP after a loader/guard bypass, with fake RTL files.

Requires ASHNA_TEST_PACKAGE to point at the unencoded licensed ZIP. It never
modifies the real vendor license, source files, or published package.
"""
from pathlib import Path
from tempfile import TemporaryDirectory
from zipfile import ZipFile
import hashlib
import os
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PACKAGE = Path(os.environ.get('ASHNA_TEST_PACKAGE', ROOT / 'deliverables/ashna.zip')).resolve()
PHP = os.environ.get('PHP_BINARY', 'php')
EXPECTED_HASH = '3fac0395eb82fa65036a1e9aabcf0aac9c2bcedc'
LICENSE = 'RTL_License_c1595a7238d64f3a'
files = [
    'includes/class-ashna-protected-core.php',
    'includes/integrations/elementor/class-ashna-elementor-login-widget.php',
    'includes/integrations/elementor/class-ashna-elementor-modal-button-widget.php',
]
fixtures = {
    'active': 'public function isActive() { ++$GLOBALS["license_calls"]; return true; }',
    'inactive': 'public function isActive() { ++$GLOBALS["license_calls"]; return false; }',
    'truthy': 'public function isActive() { ++$GLOBALS["license_calls"]; return 1; }',
    'method_error': 'public function isActive() { ++$GLOBALS["license_calls"]; throw new RuntimeException("fake"); }',
    'missing_class': 'public function isActive() { ++$GLOBALS["license_calls"]; return true; }',
    'missing_method': 'public function other() { return true; }',
    'wrong_hash': 'public function isActive() { ++$GLOBALS["license_calls"]; return true; }',
    'wrong_origin': 'public function isActive() { ++$GLOBALS["license_calls"]; return true; }',
}
(ROOT / '.cache').mkdir(exist_ok=True)
for scenario, body in fixtures.items():
    for mode in ('autoload', 'direct'):
        with TemporaryDirectory(prefix='protected-boundary-', dir=ROOT / '.cache') as tmp:
            with ZipFile(PACKAGE) as archive:
                archive.extractall(tmp)
            plugin = Path(tmp) / 'ashna'
            license_content = ('<?php class ' + LICENSE + ' { ' + body + ' }\n').encode()
            (plugin / (LICENSE + '.php')).write_bytes(license_content)
            if scenario == 'wrong_origin':
                (plugin / 'other-license.php').write_bytes(license_content + b'// different file contents\n')
            fake_hash = hashlib.sha1(license_content).hexdigest()
            for path in files:
                target = plugin / path
                text = target.read_text()
                assert text.count(EXPECTED_HASH) == 1
                if scenario != 'wrong_hash':
                    target.write_text(text.replace(EXPECTED_HASH, fake_hash))
            (plugin / 'templates/login-form.php').write_text('<?php echo "plugin:" . $args["context"];')
            theme = Path(tmp) / 'theme/ashna/login-form.php'
            theme.parent.mkdir(parents=True)
            theme.write_text('<?php echo "theme:" . $args["context"];')
            result = subprocess.run([PHP, str(ROOT / 'tests/protected-boundaries.php'), str(plugin), scenario, mode],
                                    capture_output=True, text=True, cwd=ROOT)
            output = result.stdout + result.stderr
            assert result.returncode == 0 and ('PASS ' + scenario + ' ' + mode + ' protected boundaries') in output and 'Fatal error' not in output, output
            print(result.stdout.strip(), flush=True)
print(f'PASS {len(fixtures) * 2} autoload/direct-boundary scenarios; fake guard/flags cannot bypass protected files.')
