"""Test licensed/unlicensed archives in an isolated disposable repository copy.

No real license, production files, or deliverables are modified.
Run: python3 tests/package-license.py
"""
from pathlib import Path
from tempfile import TemporaryDirectory
from zipfile import ZipFile
import hashlib
import shutil
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
CACHE = ROOT / '.cache'
CACHE.mkdir(exist_ok=True)
EXPECTED = '3fac0395eb82fa65036a1e9aabcf0aac9c2bcedc'
LICENSE = 'RTL_License_c1595a7238d64f3a.php'
GUARD = 'includes/class-ashna-license-guard.php'

with TemporaryDirectory(prefix='ashna-packaging-', dir=CACHE) as temp:
    fixture = Path(temp) / 'repo'
    for name in ['scripts', 'scripts/templates', 'includes', 'assets', 'languages', 'templates']:
        (fixture / name).mkdir(parents=True, exist_ok=True)
    for name in ['scripts/package-deliverables.py', 'scripts/templates/ashna-no-license.php',
                 'ashna.php', 'index.php', 'uninstall.php', 'README.md', GUARD]:
        shutil.copy2(ROOT / name, fixture / name)
    dummy_license = b'<?php // fixture: not an RTL license\n'
    (fixture / LICENSE).write_bytes(dummy_license)
    script = fixture / 'scripts/package-deliverables.py'

    def package(name, *args):
        out = Path(temp) / name
        result = subprocess.run([sys.executable, str(script), '--output-dir', str(out), *args],
                                text=True, capture_output=True)
        return result, out

    result, out = package('wrong-hash')
    assert result.returncode != 0 and 'does not match the verifier hash' in result.stderr
    assert not (out / 'ashna.zip').exists(), 'Mismatched license must not be packaged'
    assert (fixture / LICENSE).read_bytes() == dummy_license
    print('PASS mismatched license rejected without changing its bytes')

    result, out = package('without-file', '--without-license-file')
    assert result.returncode == 0, result.stderr
    with ZipFile(out / 'ashna.zip') as bundle:
        assert bundle.testzip() is None
        assert 'ashna/' + LICENSE not in bundle.namelist()
        assert 'ashna/' + GUARD in bundle.namelist()
        assert EXPECTED in bundle.read('ashna/ashna.php').decode()
        assert EXPECTED in bundle.read('ashna/' + GUARD).decode()
        assert 'این بسته فایل لایسنس ندارد' in bundle.read('ashna/README-INSTALL-FA.txt').decode()
    print('PASS licensed package can be built without the license file')

    result, out = package('unlicensed', '--without-license')
    assert result.returncode == 0, result.stderr
    with ZipFile(out / 'ashna-no-license.zip') as bundle:
        assert 'ashna/' + LICENSE not in bundle.namelist()
        assert 'ashna/' + GUARD not in bundle.namelist()
        assert EXPECTED not in bundle.read('ashna/ashna.php').decode()
    print('PASS separate no-license build does not contain the guard')

    # Simulate a correct vendor file and an encoded guard only in this fixture.
    # Archiving must preserve both files byte-for-byte, including non-UTF-8 data.
    fixture_hash = hashlib.sha1(dummy_license).hexdigest()
    main = fixture / 'ashna.php'
    main.write_text(main.read_text().replace(EXPECTED, fixture_hash))
    encoded_guard = b'<?php // mock protected bytes\n\xff\x00\x81\n'
    (fixture / GUARD).write_bytes(encoded_guard)
    result, out = package('matching')
    assert result.returncode == 0, result.stderr
    with ZipFile(out / 'ashna.zip') as bundle:
        assert bundle.testzip() is None
        assert bundle.read('ashna/' + LICENSE) == dummy_license
        assert bundle.read('ashna/' + GUARD) == encoded_guard
    print('PASS matching license and protected guard are copied byte-for-byte')

    result, _ = package('invalid-flags', '--without-license', '--without-license-file')
    assert result.returncode != 0, 'Mutually exclusive editions must be rejected'
    print('PASS conflicting packaging flags rejected')
