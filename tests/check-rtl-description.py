"""Structural copy checks only: not human authorship or RTL marketplace approval."""
from pathlib import Path
import re
import struct

ROOT = Path(__file__).resolve().parents[1]
raw = (ROOT / 'rtl-description.md').read_text()
assert 'هوش مصنوعی' in raw and 'بازنویسی انسانی' in raw, 'Keep the draft/authorship warning explicit'
assert '۱۰۰۰' in raw and 'کمتر از ۱ مگابایت' in raw, 'Use the updated reviewer dimensions'
assert 'نه اسکرین‌شات نصب زنده' in raw, 'Preserve offline-capture provenance'
visible = re.sub(r'<!--.*?-->', '', raw, flags=re.S).strip()
parts = re.split(r'^## (.+)\n', visible, flags=re.M)
assert len(parts) == 9
assert parts[1::2] == ['معرفی محصول', 'ویژگی‌های اصلی', 'اینفوگرافیک', 'فهرست کامل ویژگی‌ها']
intro = [line.strip() for line in parts[2].splitlines() if line.strip()]
assert len(intro) == 2 and all(not line.startswith(('-', '#')) for line in intro), 'Introduction must have two explicit lines'
main = [line.strip() for line in parts[4].splitlines() if line.strip()]
assert len(main) == 5 and all(line.startswith('- ') for line in main), 'Exactly five single-line main features'
images = re.findall(r'!\[([^\]]+)\]\(([^)]+)\)', parts[6])
assert len(images) == 1, 'One real settings infographic, not a fake URL or several images'
asset = ROOT / images[0][1]
assert asset.is_file() and asset.stat().st_size < 1_000_000
blob = asset.read_bytes()
assert blob[:4] == b'RIFF' and blob[8:12] == b'WEBP'
assert struct.unpack_from('<I', blob, 4)[0] + 8 == len(blob), 'Truncated WebP'
# VP8L lossless dimensions, without making Pillow a dependency of this structural test.
position = 12
size = None
while position + 8 <= len(blob):
    kind = blob[position:position+4]
    length = struct.unpack_from('<I', blob, position+4)[0]
    payload = blob[position+8:position+8+length]
    if kind == b'VP8L':
        assert payload[0] == 0x2f
        bits = struct.unpack_from('<I', payload, 1)[0]
        size = ((bits & 0x3fff) + 1, ((bits >> 14) & 0x3fff) + 1)
    assert kind != b'VP8 ', 'Infographic should be lossless, not lossy WebP'
    position += 8 + length + (length % 2)
assert size and size[0] == 1000 and size[1] > 1000, size
features = [line.strip() for line in parts[8].splitlines() if line.strip()]
assert len(features) >= 30 and all(line.startswith('- ') for line in features), 'Full features must be a flat, title-only list'
assert not any('**' in line or ':' in line or 'http' in line for line in features), 'No feature paragraphs or external claims'
assert 'MemberPress' not in visible, 'Unverified MemberPress bridge is not a sale-ready feature'
for claim in ['تضمین امنیت', 'سازگار با همه', 'سریع‌ترین', 'بهترین افزونه', 'Enterprise']:
    assert claim not in visible, f'Unsupported promotional claim: {claim}'
assert len(re.findall(r'^# ', visible, flags=re.M)) == 1
for name in ['Dokan', 'Paid Memberships Pro', 'Ultimate Member']:
    assert name in visible
print(f'PASS: two intro lines, five main features, real lossless {size[0]}x{size[1]} infographic ({len(blob)} bytes), provenance and flat feature titles; no marketplace approval claimed.')
