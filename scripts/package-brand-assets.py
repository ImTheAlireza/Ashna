"""Package Ashna gallery sources, revised subset and the Parsna product landing.
Run after rendering the gallery/covers and building plugin/website deliverables.
"""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import hashlib
import json

ROOT = Path(__file__).resolve().parents[1]
PRODUCT = ROOT / 'product-images'
DESIGN = PRODUCT / 'gallery-design'

with ZipFile(PRODUCT / 'ashna-gallery-html-source.zip', 'w', ZIP_DEFLATED) as bundle:
    for folder in [DESIGN, PRODUCT / 'gallery', ROOT / 'assets/fonts/estedad']:
        for source in sorted(folder.rglob('*')):
            if source.is_file() and not set(source.parts) & {'node_modules', '__pycache__'}:
                bundle.write(source, source.relative_to(ROOT).as_posix())
    for name in ['ashna-icon.png', 'ashna-icon.svg', 'ashna-logo.png', 'ashna-logo.svg']:
        source = ROOT / 'assets/images' / name
        bundle.write(source, source.relative_to(ROOT).as_posix())
    bundle.write(PRODUCT / 'ashna-gallery-8-images.zip', 'product-images/ashna-gallery-8-images.zip')

with ZipFile(PRODUCT / 'ashna-gallery-revised-2-3-5.zip', 'w', ZIP_DEFLATED) as bundle:
    for name in ['02-passkey.webp', '03-channels.webp', '05-gateways.webp']:
        bundle.write(PRODUCT / 'gallery' / name, name)

landing = ROOT / 'parsna-landing'
with ZipFile(landing / 'parsna-landing.zip', 'w', ZIP_DEFLATED) as bundle:
    for name in ['.htaccess', 'index.html', 'css/style.css', 'js/main.js', 'assets/ashna-logo.png', 'ashna-logo.png', 'serve.py']:
        bundle.write(landing / name, name)

files = sorted(p for p in PRODUCT.iterdir() if p.is_file() and p.suffix in {'.zip', '.jpg', '.png'})
for path in [*files, landing / 'parsna-landing.zip']:
    if path.suffix == '.zip':
        with ZipFile(path) as bundle:
            assert bundle.testzip() is None
            assert not any('signa' in name.lower() for name in bundle.namelist())
records = [{'file': p.relative_to(ROOT).as_posix(), 'bytes': p.stat().st_size,
            'sha256': hashlib.sha256(p.read_bytes()).hexdigest()} for p in [*files, landing / 'parsna-landing.zip']]
(PRODUCT / 'checksums.json').write_text(json.dumps(records, indent=2) + '\n')
print('PASS: gallery source, three-image subset and product landing ZIPs rebuilt; checksums updated.')
