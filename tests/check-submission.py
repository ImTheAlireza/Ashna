"""Validate the three-file delivery and all-files handoff; not market approval.
Stdlib only. render-help.mjs separately validates browser layout/font/image quality.
"""
from pathlib import Path
from zipfile import ZipFile
from io import BytesIO
import hashlib
import json
import re
from html.parser import HTMLParser

ROOT = Path(__file__).resolve().parents[1]
installer = ROOT / 'deliverables/ashna-otp-with-logo.zip'
guide = ROOT / 'deliverables/Ashna-Help-FA.pdf'
product = ROOT / 'rightchin/Ashna-Rightchin-Package.zip'
handoff = ROOT / 'deliverables/Ashna-All-Files.zip'
assert guide.read_bytes().startswith(b'%PDF-')
assert len(re.findall(rb'/Type\s*/Page\b', guide.read_bytes())) == 8, 'Expected 8 PDF pages'
with ZipFile(product) as z:
    assert z.testzip() is None
    assert set(z.namelist()) == {'Ashna-Rightchin/Plugin/ashna-otp.zip', 'Ashna-Rightchin/Ashna-Help-FA.pdf'}
    assert z.read('Ashna-Rightchin/Plugin/ashna-otp.zip') == installer.read_bytes()
    assert z.read('Ashna-Rightchin/Ashna-Help-FA.pdf') == guide.read_bytes()
with ZipFile(installer) as z:
    assert z.testzip() is None
    assert z.read('ashna-otp/index.php') == (ROOT / 'index.php').read_bytes()
    assert z.read('ashna-otp/ashna.php') == (ROOT / 'ashna.php').read_bytes()
    assert z.read('ashna-otp/README.md') == (ROOT / 'README.md').read_bytes()
    license_present = 'ashna-otp/RTL_License_c1595a7238d64f3a.php' in z.namelist()
assert (ROOT / 'ashna-otp.zip').read_bytes() == installer.read_bytes()

class GuideParser(HTMLParser):
    def __init__(self):
        super().__init__(); self.figures = 0; self.capture_images = []
    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'figure': self.figures += 1
        if tag == 'img' and '/settings/' in attrs.get('src', ''):
            self.capture_images.append(attrs)
parser = GuideParser(); html = (ROOT / 'docs/guide-fa.html').read_text(); parser.feed(html)
assert parser.figures == 5 and len(parser.capture_images) == 5
assert html.index('مراحل نصب') < html.index('اتصال سرویس ارسال کد')
for image in parser.capture_images:
    file = (ROOT / 'docs' / image['src']).resolve()
    assert file.is_file() and image.get('alt')
    data = file.read_bytes()
    assert data[:4] == b'RIFF' and data[8:12] == b'WEBP'

with ZipFile(handoff) as z:
    assert z.testzip() is None
    assert set(n for n in z.namelist() if n.startswith('01-submit/')) == {
        '01-submit/01-product-package.zip', '01-submit/02-updater-ashna-otp.zip', '01-submit/03-Ashna-Help-FA.pdf'}
    for name, file in [('01-submit/01-product-package.zip', product),
                       ('01-submit/02-updater-ashna-otp.zip', installer),
                       ('01-submit/03-Ashna-Help-FA.pdf', guide),
                       ('00-START-HERE-checklist.md', ROOT / 'docs/submission-checklist.md'),
                       ('02-marketing/rtl-description.md', ROOT / 'rtl-description.md'),
                       ('03-demo-and-design/ashna-landing.zip', ROOT / 'parsna-landing/parsna-landing.zip')]:
        assert z.read(name) == file.read_bytes(), name
    for name in ['02-marketing/ashna-settings-infographic.webp', '02-marketing/product-images/ashna-settings-infographic.webp']:
        assert z.read(name) == (ROOT / 'product-images/ashna-settings-infographic.webp').read_bytes()
    manifest = json.loads(z.read('checksums.json'))
    assert manifest['license_file_present'] == license_present
    assert set(r['file'] for r in manifest['files']) == set(z.namelist()) - {'checksums.json'}
    for record in manifest['files']:
        data = z.read(record['file'])
        assert len(data) == record['bytes'] and hashlib.sha256(data).hexdigest() == record['sha256']
    for name in z.namelist():
        if name.endswith('.zip'):
            with ZipFile(BytesIO(z.read(name))) as nested: assert nested.testzip() is None, name
    with ZipFile(BytesIO(z.read('05-source/ashna-source.zip'))) as source:
        assert not any(set(Path(name).parts) & {'.git', '.cache', '.venv', 'node_modules'} for name in source.namelist())
        for name in ['docs/submission-checklist.md', 'scripts/package-handoff.py', 'tests/check-submission.py', 'docs/guide-fa.html']:
            assert source.read('ashna-source/' + name) == (ROOT / name).read_bytes(), name
for item in json.loads((ROOT / 'deliverables/checksums.json').read_text()):
    file = ROOT / 'deliverables' / item['file']
    assert item['bytes'] == file.stat().st_size and item['sha256'] == hashlib.sha256(file.read_bytes()).hexdigest()
print('PASS: exact 3 submission files, 2-entry product package, byte-identical installer/PDF, 8-page illustrated guide, marketing links, source snapshot and nested CRC/SHA256 manifests.')
print('LICENSE STATUS:', 'present; real validation required' if license_present else 'MISSING; official distribution remains blocked.')
