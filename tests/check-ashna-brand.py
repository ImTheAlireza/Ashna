"""Check active brand names and final packaged artifacts. Run from any directory."""
from pathlib import Path
from zipfile import ZipFile
import hashlib
import re
import json

ROOT = Path(__file__).resolve().parents[1]
OLD = re.compile(r'(?<![a-z])signa(?![a-z])|سیگنا', re.I)
ALLOWED = {
    'includes/core/class-ashna-storage.php',
    'includes/core/class-ashna-compatibility.php',
    'includes/services/class-ashna-frontend.php',  # Explicit legacy template fallback.
    'includes/integrations/elementor/class-ashna-elementor-legacy-widgets.php',
    'assets/js/compatibility.js',
    'tools/ashna-activation-diagnostics/ashna-activation-diagnostics.php',
    'tools/ashna-activation-diagnostics/uninstall.php',
    'docs/rebrand-audit-fa.md',
    'scripts/package-brand-assets.py',  # Checks that legacy archive paths are absent.
}
for p in ROOT.rglob('*'):
    relative = p.relative_to(ROOT)
    if not p.is_file() or set(relative.parts) & {'.git', '.cache', '.venv', 'node_modules', '__pycache__'}:
        continue
    assert not OLD.search(p.name), f'Old filename: {relative}'
    if relative.parts[0] == 'tests' or str(relative) in ALLOWED:
        continue
    try:
        text = p.read_text()
    except UnicodeError:
        continue
    assert not OLD.search(text), f'Old brand outside compatibility: {relative}'
    assert 'ashnature' not in text and 'ashnal' not in text, f'Protocol word corrupted: {relative}'

source = (ROOT / 'ashna.php').read_text()
assert "'4f106eb02a251f2a6951ca3f530596590081002d'" in source
assert 'RTL_License_c1595a7238d64f3a.php' in source
assert "true !== $rtlLicenseClass->{'isActive'}()" in source
assert 'assertion.response.signature' in (ROOT / 'assets/js/frontend.js').read_text()
assert 'signal: ac.signal' in (ROOT / 'assets/js/frontend.js').read_text()

for p in ROOT.rglob('*.zip'):
    if set(p.relative_to(ROOT).parts) & {'.git', '.cache', '.venv', 'node_modules'}:
        continue
    with ZipFile(p) as z:
        assert z.testzip() is None, p
        assert not any(OLD.search(name) for name in z.namelist()), f'Old archive path: {p}'
        assert not any('node_modules/' in name or '/.cache/' in name for name in z.namelist()), p
        for name in z.namelist():
            if Path(name).suffix not in {'.php', '.html', '.js', '.css', '.md', '.txt', '.json', '.svg'}:
                continue
            # License identifiers and explicitly documented legacy aliases are intentional.
            allowed = any(name.endswith('/' + a) or name == a for a in ALLOWED)
            if allowed or Path(name).name in {'ashna-activation-diagnostics.php', 'uninstall.php'}:
                continue
            assert not OLD.search(z.read(name).decode()), f'Old archived content: {p}: {name}'

for name in ['ashna-otp-with-logo.zip', 'ashna-otp-no-license.zip']:
    with ZipFile(ROOT / 'deliverables' / name) as z:
        assert not any('/assets/images/ashna/' in p for p in z.namelist()), 'Draft/design sources leaked into plugin'
        assert 'ashna-otp/assets/images/ashna-icon.png' in z.namelist()
        assert 'ashna-otp/includes/core/class-ashna-storage.php' in z.namelist()
        bootstrap = z.read('ashna-otp/ashna.php')
        if 'no-license' in name:
            assert bootstrap == (ROOT / 'scripts/templates/ashna-no-license.php').read_bytes()
            assert b'Requires PHP:      7.4' in z.read('ashna-otp/index.php')
        else:
            assert bootstrap == (ROOT / 'ashna.php').read_bytes()
            assert b'Requires PHP:      8.2' in z.read('ashna-otp/index.php')
assert (ROOT / 'ashna-otp.zip').read_bytes() == (ROOT / 'deliverables/ashna-otp-with-logo.zip').read_bytes()
for folder in ['deliverables', 'rightchin', 'product-images']:
    for item in json.loads((ROOT / folder / 'checksums.json').read_text()):
        p = ROOT / item['file'] if folder == 'product-images' else ROOT / folder / item['file']
        assert p.stat().st_size == item['bytes'] and hashlib.sha256(p.read_bytes()).hexdigest() == item['sha256'], f'Stale checksum: {p}'
print('PASS: source identities, protocol fields, verifier invariants, ZIP identities/contents and SHA256 manifests.')
