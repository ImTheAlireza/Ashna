"""Prepare the RTL-Theme protection uploads for Ashna.

Builds everything the seller has to send to «سرویس محافظت فایل» in the
RTL-Theme dashboard, in the sizes that service accepts (2–5 PHP files per ZIP,
6 MB per ZIP, 1 MB per file):

    deliverables/protection/MANIFEST.json   what to upload, in order
    deliverables/protection/README-FA.txt   step-by-step instructions
    deliverables/protection/batch-NN.zip    the product code, five files each

`ashna-core.php` is listed first as a single-file upload; the class files of
includes/ follow in batches. Silence stubs (index.php) are skipped — the
service rejects empty files — and the plain-text loader files are never listed.

Run: python3 scripts/prepare-protection-batches.py
"""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED, ZIP_STORED
import hashlib
import json
import shutil
import sys

sys.path.insert(0, str(Path(__file__).resolve().parent))
import protection_rules as rules  # noqa: E402

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'deliverables' / 'protection'
MAX_FILES_PER_ZIP = 5
MAX_ZIP_BYTES = 6 * 1024 * 1024

ENTRY = ROOT / 'ashna-core.php'
# Never submitted: WordPress reads the header from ashna.php, and the Loader
# file must stay readable (both are explicit RTL-Theme rules).
NEVER_SUBMITTED = [ROOT / 'ashna.php', ROOT / 'ashna-license.php',
                   ROOT / 'scripts/templates/ashna-no-license.php']


def relative(path):
    return path.relative_to(ROOT).as_posix()


def main():
    problems = []
    for path in NEVER_SUBMITTED:
        if 'Plugin Name:' not in path.read_text() and 'RTL_LICENSE_CLASS' not in path.read_text():
            problems.append(f'{relative(path)}: expected to stay a readable loader/main file')

    entry_problems = rules.check_file(ENTRY)
    problems += entry_problems

    candidates = []
    for path in sorted((ROOT / 'includes').rglob('*.php')):
        if rules.is_silence_stub(path):
            continue
        problems += rules.check_file(path)
        candidates.append(path)

    if problems:
        print('FAIL — these files cannot be submitted as they are:')
        for problem in problems:
            print(' - ' + problem)
        return 1

    if OUT.exists():
        shutil.rmtree(OUT)
    OUT.mkdir(parents=True)

    manifest = {
        'entry_upload': {'file': relative(ENTRY), 'bytes': ENTRY.stat().st_size,
                         'sha256': hashlib.sha256(ENTRY.read_bytes()).hexdigest(),
                         'note': 'single-file upload; do not zip it'},
        'batches': [],
        'never_submitted': [relative(path) for path in NEVER_SUBMITTED],
        'limits': {'files_per_zip': MAX_FILES_PER_ZIP, 'zip_bytes': MAX_ZIP_BYTES,
                   'file_bytes': rules.MAX_BYTES},
    }

    batch_number = 0
    for start in range(0, len(candidates), MAX_FILES_PER_ZIP):
        files = candidates[start:start + MAX_FILES_PER_ZIP]
        total = sum(path.stat().st_size for path in files)
        assert total <= MAX_ZIP_BYTES, 'batch exceeds the 6 MB ZIP limit'
        batch_number += 1
        name = f'batch-{batch_number:02d}.zip'
        with ZipFile(OUT / name, 'w', ZIP_DEFLATED) as bundle:
            for path in files:
                bundle.write(path, relative(path))
        manifest['batches'].append({
            'file': name,
            'bytes': (OUT / name).stat().st_size,
            'sha256': hashlib.sha256((OUT / name).read_bytes()).hexdigest(),
            'files': [{'path': relative(path), 'bytes': path.stat().st_size}
                      for path in files],
        })

    readme = (ROOT / 'scripts' / 'templates' / 'protection-README-FA.txt').read_text()
    readme = readme.format(batches=len(manifest['batches']),
                           files=len(candidates) + 1,
                           entry=relative(ENTRY))
    (OUT / 'README-FA.txt').write_text(readme)
    (OUT / 'MANIFEST.json').write_text(json.dumps(manifest, indent=2, ensure_ascii=False) + '\n')

    print(f'PASS {len(candidates)} includes files + 1 entry file in {batch_number} batches '
          f'({MAX_FILES_PER_ZIP} per ZIP, all within the 1 MB / 6 MB limits)')
    print(f'  entry upload : {relative(ENTRY)} ({ENTRY.stat().st_size} bytes)')
    for batch in manifest['batches']:
        print(f'  {batch["file"]}: {batch["bytes"]} bytes, ' + ', '.join(f['path'] for f in batch['files']))
    print(f'  manifest     : {relative(OUT / "MANIFEST.json")}')
    print(f'  instructions : {relative(OUT / "README-FA.txt")}')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
