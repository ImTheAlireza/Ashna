"""Convert actual Chromium screenshots into upload-ready WebP and ZIP files.
Requires Pillow (pip install Pillow). Does not resize or upscale screenshots.
"""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import json
from PIL import Image

ROOT = Path(__file__).resolve().parents[2]
raw = ROOT / '.cache/gallery-render'
output = ROOT / 'product-images/gallery'
output.mkdir(parents=True, exist_ok=True)
report = json.loads((raw / 'render-report.json').read_text())
assert len(report) == 8
for item in report:
    path = output / (item['file'] + '.webp')
    with Image.open(raw / (item['file'] + '.png')) as source:
        assert source.size == (1600, 1800), source.size
        image = source.convert('RGB')
        for quality in range(96, 59, -3):
            image.save(path, 'WEBP', quality=quality, method=6)
            if path.stat().st_size < 900_000:
                break
    with Image.open(path) as check:
        assert check.size == (1600, 1800) and check.format == 'WEBP'
    assert path.stat().st_size < 1_000_000
    item['bytes'] = path.stat().st_size
    print(path.name, '1600 × 1800', item['bytes'], 'bytes')
(output / 'manifest.json').write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
archive = ROOT / 'product-images/signa-gallery-8-images.zip'
with ZipFile(archive, 'w', compression=ZIP_DEFLATED) as bundle:
    for item in report:
        path = output / (item['file'] + '.webp')
        bundle.write(path, arcname=path.name)
with ZipFile(archive) as bundle:
    assert len(bundle.namelist()) == 8 and bundle.testzip() is None
print('Packaged:', archive)
