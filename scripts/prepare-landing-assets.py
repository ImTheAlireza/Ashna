"""Sync the standalone landing assets from the CURRENT offline settings render.
First run scripts/render-settings-infographic.mjs. Requires Pillow; no WP/API access.
Run with the same Python/Pillow environment used by the infographic builder.
"""
from pathlib import Path
import hashlib
import json
import shutil
from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
CACHE = ROOT / '.cache/settings-capture'
TARGET = ROOT / 'parsna-landing/assets'
report = json.loads((CACHE / 'capture-report.json').read_text())
sha = lambda p: hashlib.sha256(p.read_bytes()).hexdigest()
for name, expected in report['sources'].items():
    assert sha(ROOT / name) == expected, f'Stale capture source: {name}; render again'
original = ROOT / report['output']['file']
assert sha(original) == report['output']['sha256'], 'Stale infographic report; render again'
(TARGET / 'settings').mkdir(parents=True, exist_ok=True)
(TARGET / 'fonts').mkdir(parents=True, exist_ok=True)
for name in ['estedad-400.woff2', 'estedad-700.woff2', 'estedad-900.woff2', 'LICENSE.txt']:
    shutil.copyfile(ROOT / 'assets/fonts/estedad' / name, TARGET / 'fonts' / name)
shutil.copyfile(ROOT / 'assets/images/ashna-icon.svg', TARGET / 'ashna-icon.svg')
shutil.copyfile(original, TARGET / 'settings/ashna-settings-infographic.webp')
for capture in report['captures']:
    source = CACHE / (capture['name'] + '.png')
    target = TARGET / 'settings' / (capture['name'] + '.webp')
    with Image.open(source) as image:
        image.save(target, lossless=True, method=6)
        with Image.open(target) as encoded:
            assert image.convert('RGB').tobytes() == encoded.convert('RGB').tobytes()
    capture['file'] = target.name
    capture['sha256'] = sha(target)
report.pop('externalReference', None)
report['output']['file'] = original.name
# Source keys are relative to the plugin root; deployed metadata contains no local paths.
(TARGET / 'settings/provenance.json').write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
print('PASS: local font/license/icon and lossless settings captures synchronized; source SHA256 and pixel equality verified.')
