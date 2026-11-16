"""Swap RTL-Theme's protected files into the plugin ZIP.

Takes the files that came back from «سرویس محافظت فایل», checks each one really
is an ionCube build, and writes a new ZIP with them in place:

    deliverables/ashna.zip  ->  deliverables/ashna-protected.zip

Run: python3 scripts/apply-protected-files.py --from <folder-with-returned-files>

Returned files may keep their relative paths (ashna-core.php,
includes/core/class-ashna-helper.php) or sit flat in one folder; a unique
basename is matched back to its path. Sources in the repository are never
touched.
"""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import argparse
import hashlib
import json
import sys

sys.path.insert(0, str(Path(__file__).resolve().parent))
import protection_rules as rules  # noqa: E402

ROUND = 'ashna/'

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--from', dest='source', required=True,
                    help='folder holding the files returned by RTL-Theme')
parser.add_argument('--zip', default=None, help='source plugin ZIP (default: deliverables/ashna.zip)')
parser.add_argument('--out', default=None, help='output ZIP (default: deliverables/ashna-protected.zip)')
args = parser.parse_args()

ROOT = Path(__file__).resolve().parents[1]
source_zip = Path(args.zip) if args.zip else ROOT / 'deliverables' / 'ashna.zip'
output_zip = Path(args.out) if args.out else ROOT / 'deliverables' / 'ashna-protected.zip'
incoming = Path(args.source)

if not source_zip.is_file():
    raise SystemExit(f'missing {source_zip}; run scripts/package-deliverables.py first')
if not incoming.is_dir():
    raise SystemExit(f'not a folder: {incoming}')

with ZipFile(source_zip) as bundle:
    entries = [(name, bundle.read(name)) for name in bundle.namelist()]

manifest_path = ROOT / 'deliverables' / 'protection' / 'MANIFEST.json'
expected = set()
if manifest_path.is_file():
    manifest = json.loads(manifest_path.read_text())
    expected.add(ROUND + manifest['entry_upload']['file'])
    for batch in manifest['batches']:
        expected.update(ROUND + item['path'] for item in batch['files'])

# Map returned files onto ZIP entry names, by relative path first, then basename.
by_path = {name: index for index, (name, _) in enumerate(entries)}
by_basename = {}
for name, _ in entries:
    by_basename.setdefault(name.rsplit('/', 1)[-1], []).append(name)

replacements = {}
problems = []
for path in sorted(p for p in incoming.rglob('*') if p.is_file()):
    if path.suffix != '.php':
        continue
    if not rules.is_ioncube(path):
        problems.append(f'{path.name}: not an ionCube file (starts with {path.read_bytes()[:16]!r})')
        continue
    relative = path.relative_to(incoming).as_posix()
    target = ROUND + relative if (ROUND + relative) in by_path else None
    if target is None:
        matches = by_basename.get(path.name, [])
        if len(matches) == 1:
            target = matches[0]
        elif not matches:
            problems.append(f'{path.name}: no matching file inside {source_zip.name}')
            continue
        else:
            problems.append(f'{path.name}: ambiguous ({len(matches)} matches)')
            continue
    if target in replacements:
        problems.append(f'{target}: supplied twice')
        continue
    replacements[target] = path.read_bytes()

if problems:
    print('FAIL')
    for problem in problems:
        print(' - ' + problem)
    raise SystemExit(1)

missing = sorted(expected - set(replacements)) if expected else []
if not replacements:
    raise SystemExit('no protected files found; nothing to place')

with ZipFile(output_zip, 'w', ZIP_DEFLATED) as bundle:
    for name, data in entries:
        bundle.writestr(name, replacements.get(name, data))

print(f'placed {len(replacements)} protected files into {output_zip.relative_to(ROOT)}')
print(f'  sha256 {hashlib.sha256(output_zip.read_bytes()).hexdigest()}')
if missing:
    print(f'  still plain text ({len(missing)}):')
    for name in missing:
        print('   - ' + name)
else:
    print('  every submitted file is now protected')
print('  not submitted, stays readable: ashna/ashna.php, ashna/ashna-license.php, '
      'ashna/RTL_License_c1595a7238d64f3a.php')
