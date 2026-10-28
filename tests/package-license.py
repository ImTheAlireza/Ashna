"""Test licensed/unlicensed packages and exact protected-file assembly in an isolated copy.

No real license, production files, or deliverables are modified.
Run: python3 tests/package-license.py
"""
from pathlib import Path
from tempfile import TemporaryDirectory
from zipfile import ZipFile
import hashlib
import re
import shutil
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
CACHE = ROOT / '.cache'
CACHE.mkdir(exist_ok=True)
EXPECTED = '3fac0395eb82fa65036a1e9aabcf0aac9c2bcedc'
LICENSE = 'RTL_License_c1595a7238d64f3a.php'
GUARD = 'includes/class-ashna-license-guard.php'
CORE = 'includes/class-ashna-protected-core.php'
PROTECT = [GUARD, CORE,
    'includes/integrations/elementor/class-ashna-elementor-login-widget.php',
    'includes/integrations/elementor/class-ashna-elementor-modal-button-widget.php']

with TemporaryDirectory(prefix='ashna-packaging-', dir=CACHE) as temp:
    fixture = Path(temp) / 'repo'
    fixture.mkdir()
    (fixture / 'docs').mkdir()
    shutil.copy2(ROOT / 'docs/license-protection-fa.md', fixture / 'docs/license-protection-fa.md')
    shutil.copytree(ROOT / 'includes', fixture / 'includes')
    shutil.copytree(ROOT / 'scripts/templates', fixture / 'scripts/templates')
    for name in ['assets', 'languages', 'templates']:
        (fixture / name).mkdir()
    for name in ['scripts/package-deliverables.py', 'scripts/license_protection.py',
                 'ashna.php', 'index.php', 'uninstall.php', 'README.md']:
        shutil.copy2(ROOT / name, fixture / name)
    dummy_license = b'<?php // fixture: not an RTL license\n'
    (fixture / LICENSE).write_bytes(dummy_license)
    script = fixture / 'scripts/package-deliverables.py'

    def package(name, *args):
        out = Path(temp) / name
        result = subprocess.run([sys.executable, str(script), '--output-dir', str(out), *map(str, args)],
                                text=True, capture_output=True)
        return result, out

    result, out = package('wrong-hash')
    assert result.returncode != 0 and 'does not match the verifier hash' in result.stderr
    assert not (out / 'ashna.zip').exists()
    assert (fixture / LICENSE).read_bytes() == dummy_license
    print('PASS mismatched license rejected without changing its bytes')

    result, out = package('without-file', '--without-license-file')
    assert result.returncode == 0, result.stderr
    plugin_zip, protect_zip = out / 'ashna.zip', out / 'ashna-protection.zip'
    with ZipFile(plugin_zip) as plugin, ZipFile(protect_zip) as protect:
        assert plugin.testzip() is None and protect.testzip() is None
        assert 'ashna/' + LICENSE not in plugin.namelist()
        assert EXPECTED in plugin.read('ashna/ashna.php').decode()
        assert 'این بسته فایل لایسنس ندارد' in plugin.read('ashna/README-INSTALL-FA.txt').decode()
        assert set(protect.namelist()) == set(PROTECT) and len(protect.namelist()) == 4
        assert protect_zip.stat().st_size < 6_000_000 and sum(i.file_size for i in protect.infolist()) < 5_000_000
        for path in PROTECT:
            content = protect.read(path)
            assert content == plugin.read('ashna/' + path), path
            assert 0 < len(content) < 1_000_000
            assert not re.search(rb'<\?(?!php\b)', content, re.IGNORECASE)
            assert b'__halt_compiler' not in content
        guard = protect.read(GUARD).decode()
        assert EXPECTED in guard and "require_once __DIR__ . '/class-ashna-autoloader.php';" in guard
        core = protect.read(CORE).decode()
        assert core.count(EXPECTED) == 1 and "class Ashna_Auth" in core and "class Ashna_Passkey" in core
        assert "class Ashna_SSO" in core and "class Ashna_Session" in core
        assert "include plugin_dir_path( $theme_template ) . basename( $theme_template );" in core
        includes = re.findall(r'^\s*(?:include|require)(?:_once)?\s+([^;]+);', core, re.MULTILINE)
        assert includes and all(re.search(r'\b(?:ABSPATH|__DIR__|plugin_dir_path)\b', value) for value in includes), includes
        # The runtime must not be duplicated in plaintext class files in the distribution.
        for path in ['includes/class-ashna-plugin.php', 'includes/services/class-ashna-auth.php',
                     'includes/services/class-ashna-sso.php', 'includes/core/class-ashna-passkey.php',
                     'includes/core/class-ashna-session.php', 'includes/gateways/class-ashna-gateway-manager.php',
                     'includes/admin/class-ashna-admin.php']:
            assert 'ashna/' + path not in plugin.namelist(), path
    print('PASS protection ZIP has exactly four matching, path-safe, size-compliant PHP inputs')

    result, out_no = package('unlicensed', '--without-license')
    assert result.returncode == 0, result.stderr
    with ZipFile(out_no / 'ashna-no-license.zip') as bundle:
        assert 'ashna/' + LICENSE not in bundle.namelist()
        assert 'ashna/' + GUARD not in bundle.namelist()
        assert 'ashna/' + CORE not in bundle.namelist()
        assert EXPECTED not in bundle.read('ashna/ashna.php').decode()
        assert bundle.read('ashna/includes/services/class-ashna-auth.php') == (ROOT / 'includes/services/class-ashna-auth.php').read_bytes()
    print('PASS separate no-license build retains its original layout and behavior')

    # The assembler must preserve every output byte, without attempting to parse or
    # validate the vendor's encoding. These are mock binary files, not real encoding.
    encoded_dir = Path(temp) / 'encoded'
    encoded = {}
    for index, path in enumerate(PROTECT):
        target = encoded_dir / path
        target.parent.mkdir(parents=True, exist_ok=True)
        encoded[path] = b'<?php // mock protected ' + str(index).encode() + b'\n\xff\x00\x81\n'
        target.write_bytes(encoded[path])
    result, assembled = package('assembled', '--without-license-file', '--protected-dir', encoded_dir, '--protection-input', protect_zip)
    assert result.returncode == 0, result.stderr
    with ZipFile(assembled / 'ashna.zip') as final, ZipFile(assembled / 'ashna-protection.zip') as raw, ZipFile(protect_zip) as original:
        for path in PROTECT:
            assert final.read('ashna/' + path) == encoded[path]
            assert raw.read(path) == original.read(path), 'Never re-encode protected output'
    print('PASS all four mock-encoded files are assembled byte-for-byte, while raw inputs stay separate')

    missing = encoded_dir / PROTECT[1]
    missing.unlink()
    result, _ = package('missing-encoded', '--without-license-file', '--protected-dir', encoded_dir, '--protection-input', protect_zip)
    assert result.returncode != 0 and 'Missing encoded file' in result.stderr
    missing.write_bytes(encoded[PROTECT[1]])
    (encoded_dir / PROTECT[0]).write_bytes((fixture / PROTECT[0]).read_bytes())
    result, _ = package('raw-instead', '--without-license-file', '--protected-dir', encoded_dir, '--protection-input', protect_zip)
    assert result.returncode != 0 and 'identical to the raw input' in result.stderr
    (encoded_dir / PROTECT[0]).write_bytes(encoded[PROTECT[0]])
    original_auth = (fixture / 'includes/services/class-ashna-auth.php').read_bytes()
    (fixture / 'includes/services/class-ashna-auth.php').write_bytes(original_auth + b'\n// changed source\n')
    result, _ = package('stale-input', '--without-license-file', '--protected-dir', encoded_dir, '--protection-input', protect_zip)
    assert result.returncode != 0 and 'Source changed since' in result.stderr
    (fixture / 'includes/services/class-ashna-auth.php').write_bytes(original_auth)
    print('PASS missing, raw, or stale protection inputs are rejected')

    original_guard = (fixture / GUARD).read_bytes()
    (fixture / GUARD).write_bytes(b'<?php //ICB0 81:0 82:503b ?>')
    result, _ = package('re-encode', '--without-license-file')
    assert result.returncode != 0 and 'already ionCube encoded' in result.stderr
    (fixture / GUARD).write_bytes(original_guard)
    (fixture / 'includes/services/class-ashna-auth.php').write_bytes(original_auth + b'\ninclude $unrooted_file;\n')
    result, _ = package('unrooted-include', '--without-license-file')
    assert result.returncode != 0 and 'needs a supported base path' in result.stderr
    (fixture / 'includes/services/class-ashna-auth.php').write_bytes(original_auth)
    print('PASS re-encoding and unrooted local includes are rejected')

    fixture_hash = hashlib.sha1(dummy_license).hexdigest()
    main = fixture / 'ashna.php'
    main.write_text(main.read_text().replace(EXPECTED, fixture_hash))
    result, matching = package('matching')
    assert result.returncode == 0, result.stderr
    with ZipFile(matching / 'ashna.zip') as bundle:
        assert bundle.read('ashna/' + LICENSE) == dummy_license
    print('PASS matching fixture license is copied byte-for-byte')

    result, _ = package('invalid-flags', '--without-license', '--without-license-file')
    assert result.returncode != 0
    result, _ = package('invalid-assembly', '--without-license-file', '--protected-dir', encoded_dir)
    assert result.returncode != 0 and 'must be supplied together' in result.stderr
    print('PASS conflicting or incomplete packaging flags rejected')
