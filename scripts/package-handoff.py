"""Collect the three submission files + marketing/demo/source/checklist in one ZIP.
This is a handoff archive, NOT the product ZIP to install/upload to WordPress.
Run other packagers first; no credentials, license files or caches are invented.
"""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from io import BytesIO
import hashlib
import json
import subprocess

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'deliverables/Ashna-All-Files.zip'
installer = ROOT / 'deliverables/ashna-otp-with-logo.zip'
guide = ROOT / 'deliverables/Ashna-Help-FA.pdf'
product = ROOT / 'rightchin/Ashna-Rightchin-Package.zip'
with ZipFile(product) as z:
    assert z.testzip() is None and len(z.namelist()) == 2
    assert z.read('Ashna-Rightchin/Plugin/ashna-otp.zip') == installer.read_bytes()
    assert z.read('Ashna-Rightchin/Ashna-Help-FA.pdf') == guide.read_bytes()
with ZipFile(installer) as z:
    assert z.read('ashna-otp/README.md') == (ROOT / 'README.md').read_bytes()
    license_present = 'ashna-otp/RTL_License_c1595a7238d64f3a.php' in z.namelist()
with ZipFile(ROOT / 'parsna-landing/parsna-landing.zip') as z:
    assert z.testzip() is None
    for name in z.namelist():
        assert z.read(name) == (ROOT / 'parsna-landing' / name).read_bytes(), 'Stale landing: ' + name

files = {
    '00-START-HERE-checklist.md': ROOT / 'docs/submission-checklist.md',
    '01-submit/01-product-package.zip': product,
    '01-submit/02-updater-ashna-otp.zip': installer,
    '01-submit/03-Ashna-Help-FA.pdf': guide,
    '02-marketing/rtl-description.md': ROOT / 'rtl-description.md',
    '02-marketing/ashna-settings-infographic.webp': ROOT / 'product-images/ashna-settings-infographic.webp',
    # Keep the single canonical Markdown's relative image URL resolvable.
    '02-marketing/product-images/ashna-settings-infographic.webp': ROOT / 'product-images/ashna-settings-infographic.webp',
    '02-marketing/cover/ashna-main-branded.jpg': ROOT / 'product-images/ashna-main-branded.jpg',
    '02-marketing/cover/ashna-thumbnail.png': ROOT / 'product-images/ashna-thumbnail.png',
    '02-marketing/ashna-gallery-8-images.zip': ROOT / 'product-images/ashna-gallery-8-images.zip',
    '02-marketing/changelog-FA.txt': ROOT / 'docs/changelog-FA.txt',
    '03-demo-and-design/ashna-landing.zip': ROOT / 'parsna-landing/parsna-landing.zip',
    '03-demo-and-design/ashna-logo-kit.zip': ROOT / 'deliverables/ashna-logo-kit.zip',
    '03-demo-and-design/ashna-gallery-html-source.zip': ROOT / 'product-images/ashna-gallery-html-source.zip',
    '03-demo-and-design/ashna-gallery-revised-2-3-5.zip': ROOT / 'product-images/ashna-gallery-revised-2-3-5.zip',
    '03-demo-and-design/ashna-gallery-website-source.zip': ROOT / 'deliverables/ashna-website-source.zip',
    '04-test-tools-NOT-FOR-SUBMISSION/ashna-otp-no-license.zip': ROOT / 'deliverables/ashna-otp-no-license.zip',
    '04-test-tools-NOT-FOR-SUBMISSION/ashna-activation-diagnostics.zip': ROOT / 'deliverables/ashna-activation-diagnostics.zip',
}
for name in ['ashna-logo.svg', 'ashna-icon.svg', 'ashna-logo.png', 'ashna-icon.png']:
    files['03-demo-and-design/logos/' + name] = ROOT / 'assets/images' / name

# Only version-controlled source. Stage new source/checklist before running this tool.
# Exclude generated archives/PDFs and hash manifests to avoid recursive/stale hashes.
tracked = subprocess.check_output(['git', 'ls-files', '-z'], cwd=ROOT).decode().split('\0')
source_bytes = BytesIO()
with ZipFile(source_bytes, 'w', ZIP_DEFLATED) as z:
    for name in sorted(filter(None, tracked)):
        source = ROOT / name
        if not source.is_file() or source.suffix in {'.zip', '.pdf'} or source.name == 'checksums.json':
            continue
        assert not set(Path(name).parts) & {'.git', '.cache', '.venv', 'node_modules'}
        assert not source.name.startswith('.env')
        z.write(source, 'ashna-source/' + name)

records = []
def add(bundle, name, data):
    bundle.writestr(name, data)
    records.append({'file': name, 'bytes': len(data), 'sha256': hashlib.sha256(data).hexdigest()})
with ZipFile(OUT, 'w', ZIP_DEFLATED) as z:
    for name, source in sorted(files.items()):
        add(z, name, source.read_bytes())
    add(z, '05-source/ashna-source.zip', source_bytes.getvalue())
    manifest = {'version': '2.7.9', 'license_file_present': license_present,
                'readiness': 'Preparation handoff; open requirements in checklist. NOT marketplace approval.',
                'files': records}
    z.writestr('checksums.json', json.dumps(manifest, ensure_ascii=False, indent=2) + '\n')
with ZipFile(OUT) as z:
    assert z.testzip() is None
    assert len([name for name in z.namelist() if name.startswith('01-submit/')]) == 3
    for record in records:
        data = z.read(record['file'])
        assert len(data) == record['bytes'] and hashlib.sha256(data).hexdigest() == record['sha256']
    for name in z.namelist():
        if name.endswith('.zip'):
            with ZipFile(BytesIO(z.read(name))) as nested:
                assert nested.testzip() is None, name
record = {'file': OUT.name, 'bytes': OUT.stat().st_size,
          'sha256': hashlib.sha256(OUT.read_bytes()).hexdigest(), 'files': len(records) + 1}
manifest_path = ROOT / 'deliverables/checksums.json'
entries = [entry for entry in json.loads(manifest_path.read_text()) if entry['file'] != OUT.name]
entries.append(record)
manifest_path.write_text(json.dumps(entries, indent=2) + '\n')
print(json.dumps(record, indent=2))
print('PASS: exactly three submission files, product/updater/PDF byte identity, source snapshot and all nested ZIP CRC/SHA256 checks.')
print('License file present:', license_present, '; unresolved requirements remain in checklist.')
