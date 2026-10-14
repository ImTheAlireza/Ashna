"""Build the product package with ONLY installer ZIP + PDF; keep extra material outside.
Run render-help.mjs and both installer packagers first. No license is fabricated.
"""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import hashlib
import json
import re

root = Path(__file__).resolve().parents[1]
installer = root / 'deliverables/ashna-otp-with-logo.zip'
guide = root / 'deliverables/Ashna-Help-FA.pdf'
wrapper = root / 'rightchin/Ashna-Rightchin-Package.zip'
assert guide.read_bytes().startswith(b'%PDF-'), 'Render the guide PDF first'
with ZipFile(installer) as z:
    assert z.testzip() is None
    assert re.search(r'Requires PHP:\s*8\.2\s', z.read('ashna-otp/index.php').decode())
    for name in ['index.php', 'ashna.php', 'README.md', 'rtl-description.md']:
        assert z.read('ashna-otp/' + name) == (root / name).read_bytes(), 'Rebuild installer: ' + name
    license_present = 'ashna-otp/RTL_License_c1595a7238d64f3a.php' in z.namelist()
wrapper.parent.mkdir(exist_ok=True)
with ZipFile(wrapper, 'w', ZIP_DEFLATED) as z:
    z.write(installer, 'Ashna-Rightchin/Plugin/ashna-otp.zip')
    z.write(guide, 'Ashna-Rightchin/Ashna-Help-FA.pdf')
with ZipFile(wrapper) as z:
    assert z.testzip() is None
    assert set(z.namelist()) == {'Ashna-Rightchin/Plugin/ashna-otp.zip', 'Ashna-Rightchin/Ashna-Help-FA.pdf'}
    assert z.read('Ashna-Rightchin/Plugin/ashna-otp.zip') == installer.read_bytes()
    assert z.read('Ashna-Rightchin/Ashna-Help-FA.pdf') == guide.read_bytes()

def record(path, name):
    return {'file': name, 'bytes': path.stat().st_size, 'sha256': hashlib.sha256(path.read_bytes()).hexdigest()}
manifest = root / 'deliverables/checksums.json'
entries = [entry for entry in json.loads(manifest.read_text()) if entry['file'] != guide.name]
entries.append(record(guide, guide.name))
manifest.write_text(json.dumps(entries, indent=2) + '\n')
(root / 'rightchin/checksums.json').write_text(json.dumps([
    record(wrapper, wrapper.name), record(guide, '../deliverables/' + guide.name),
    record(installer, '../deliverables/' + installer.name)], indent=2) + '\n')
print(json.dumps(record(wrapper, wrapper.name), indent=2))
print('PASS: product package contains EXACTLY installer + PDF; both byte-identical to standalone files.')
print('LICENSE STATUS: ' + ('file present; live validation still required' if license_present else 'MISSING; preparation package is NOT ready for official distribution. See submission checklist.'))
