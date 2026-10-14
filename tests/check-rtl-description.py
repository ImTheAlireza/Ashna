"""Structural copy checks only: not human authorship or RTL marketplace approval."""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
raw = (ROOT / 'rtl-description.md').read_text()
assert 'هوش مصنوعی' in raw and 'بازنویسی انسانی' in raw, 'Keep the draft/authorship warning explicit'
assert '۹۶۰' in raw and 'کمتر از ۱ مگابایت' in raw, 'Keep the pending infographic specification'
visible = re.sub(r'<!--.*?-->', '', raw, flags=re.S).strip()
parts = re.split(r'^## (.+)\n', visible, flags=re.M)
assert len(parts) == 9
assert parts[1::2] == ['معرفی محصول', 'ویژگی‌های اصلی', 'اینفوگرافیک', 'فهرست کامل ویژگی‌ها']
intro = [line.strip() for line in parts[2].splitlines() if line.strip()]
assert len(intro) == 2 and all(not line.startswith(('-', '#')) for line in intro), 'Introduction must have two explicit lines'
main = [line.strip() for line in parts[4].splitlines() if line.strip()]
assert len(main) == 5 and all(line.startswith('- ') for line in main), 'Exactly five single-line main features'
assert not parts[6].strip(), 'Do not insert a fake or unreviewed infographic into the pending slot'
features = [line.strip() for line in parts[8].splitlines() if line.strip()]
assert len(features) >= 30 and all(line.startswith('- ') for line in features), 'Full features must be a flat, title-only list'
assert not any('**' in line or ':' in line or 'http' in line for line in features), 'No feature paragraphs or external claims'
assert 'MemberPress' not in visible, 'Unverified MemberPress bridge is not a sale-ready feature'
for claim in ['تضمین امنیت', 'سازگار با همه', 'سریع‌ترین', 'بهترین افزونه', 'Enterprise']:
    assert claim not in visible, f'Unsupported promotional claim: {claim}'
assert len(re.findall(r'^# ', visible, flags=re.M)) == 1
for name in ['Dokan', 'Paid Memberships Pro', 'Ultimate Member']:
    assert name in visible
print('PASS: two intro lines, five main features, pending 960px/<1MB human-designed infographic and flat feature titles; no acceptance/authorship certification.')
