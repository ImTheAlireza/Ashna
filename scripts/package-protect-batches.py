"""Build the RTL protection batches.

RTL accepts one PHP file or a ZIP of 2-5 PHP files (1 MB per file, 5 MB total
after extraction, 6 MB per ZIP), rejects include/require paths without an
absolute base and refuses the entry file that holds the license loader.
index.php is therefore never packed; ashna.php is uploaded on its own.
Run: python3 scripts/package-protect-batches.py
"""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import json

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'deliverables/protect'
OUT.mkdir(parents=True, exist_ok=True)

MAX_FILES = 5
MAX_FILE_BYTES = 1024 * 1024
MAX_TOTAL_BYTES = 5 * 1024 * 1024
MAX_ZIP_BYTES = 6 * 1024 * 1024

SKIP_NAMES = {'index.php'}
SKIP_PATHS = {
    'includes/class-ashna-autoloader.php',
}

files = []
for folder in ['includes', 'templates']:
    for path in sorted((ROOT / folder).rglob('*.php')):
        relative = path.relative_to(ROOT).as_posix()
        if path.name in SKIP_NAMES or relative in SKIP_PATHS:
            continue
        if path.stat().st_size > MAX_FILE_BYTES:
            raise SystemExit(f'{relative} is larger than the 1 MB limit')
        files.append(relative)

chunks = []
index = 0
while index < len(files):
    remaining = len(files) - index
    size = remaining if remaining <= MAX_FILES else MAX_FILES
    if remaining > MAX_FILES and remaining - size == 1:
        size -= 1
    chunks.append(files[index:index + size])
    index += size

for old in OUT.glob('ashna-protect-*.zip'):
    old.unlink()

manifest = []
for number, chunk in enumerate(chunks, start=1):
    if not 2 <= len(chunk) <= MAX_FILES:
        raise SystemExit(f'batch {number} has {len(chunk)} files; RTL needs 2-5')
    total = sum((ROOT / name).stat().st_size for name in chunk)
    if total > MAX_TOTAL_BYTES:
        raise SystemExit(f'batch {number} exceeds the 5 MB extraction limit')
    target = OUT / f'ashna-protect-{number:02d}.zip'
    with ZipFile(target, 'w', ZIP_DEFLATED) as archive:
        for name in chunk:
            archive.write(ROOT / name, name)
    if target.stat().st_size > MAX_ZIP_BYTES:
        raise SystemExit(f'{target.name} exceeds the 6 MB ZIP limit')
    with ZipFile(target) as archive:
        assert archive.testzip() is None
        assert archive.namelist() == chunk
    manifest.append({'batch': target.name, 'files': chunk,
                     'bytes': target.stat().st_size, 'total': total})

lines = [
    'بسته‌های ارسال به راست‌چین برای محافظت',
    '',
    'قواعد راست‌چین:',
    '- هر ZIP شامل ۲ تا ۵ فایل PHP؛ هر فایل حداکثر ۱ مگابایت؛ مجموع پس از استخراج حداکثر ۵ مگابایت.',
    '- فایل اصلی افزونه (index.php) که Loader لایسنس داخل آن است ارسال نمی‌شود.',
    '- فایل محافظت‌شده را باز، ویرایش یا دوباره ذخیره نکنید؛ برای هر تغییر، نسخهٔ اصلی را ویرایش و دوباره ارسال کنید.',
    '- پس از دریافت فایل‌ها، هر فایل را در همان مسیر نسبی خودش جایگزین کنید.',
    '',
    'ارسال تک‌فایلی (بدون ZIP):',
    '- ashna.php — کد بررسی لایسنس؛ پس از دریافت، کنار index.php قرار می‌گیرد.',
    '',
    f'تعداد فایل‌های داخل بسته‌ها: {len(files)} فایل در {len(chunks)} بسته',
    '',
]
for entry in manifest:
    lines.append(f'{entry["batch"]}  ({len(entry["files"])} فایل، {entry["bytes"]} بایت)')
    for name in entry['files']:
        lines.append(f'    {name}')
    lines.append('')

(OUT / 'MANIFEST-FA.txt').write_text('\n'.join(lines), encoding='utf-8')
(OUT / 'manifest.json').write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
print(json.dumps({'files': len(files), 'batches': len(chunks)}, ensure_ascii=False))
for entry in manifest:
    print(entry['batch'], len(entry['files']))
