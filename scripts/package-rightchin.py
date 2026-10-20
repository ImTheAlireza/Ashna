"""Update the Rightchin wrapper with the CURRENT installer and rendered Persian PDF.
Run scripts/render-help.mjs first. Does not rebuild or modify the installer/license.
"""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import hashlib
import json
import re

root = Path(__file__).resolve().parents[1]
installer = root / 'deliverables/signa-otp-with-logo.zip'
guide = root / 'deliverables/Signa-Help-FA.pdf'
wrapper = root / 'rightchin/Signa-Rightchin-Package.zip'
assert guide.read_bytes().startswith(b'%PDF-'), 'Render the guide PDF first'
with ZipFile(installer) as z:
    assert z.testzip() is None
    header = z.read('signa-otp/index.php').decode()
    assert re.search(r'Requires PHP:\s*8\.2\s', header)
    assert z.read('signa-otp/index.php') == (root / 'index.php').read_bytes(), 'Rebuild the current installer first'
    assert z.read('signa-otp/signa.php') == (root / 'signa.php').read_bytes()
    license_present = 'signa-otp/RTL_License_c1595a7238d64f3a.php' in z.namelist()
# The packaging README (not the customer PDF) records the missing-license status.
assert not license_present, 'Update the packaging license-presence notice before distributing a new licensed bundle'
installer_sha = hashlib.sha256(installer.read_bytes()).hexdigest()
readme = f'''بستهٔ سیگنا برای آماده‌سازی انتشار در راست‌چین — نسخهٔ ۲.۷.۷
ویرایش راهنما: ۱۳ اکتبر ۲۰۲۶

مهم: این بسته هنوز فایل واقعی لایسنس راست‌چین را ندارد و بستهٔ کامل آمادهٔ انتشار نیست.
مالک محصول باید فایل معتبر قبلی RTL_License_c1595a7238d64f3a.php را پیش از نصب/توزیع، کنار signa.php داخل ZIP افزونه قرار دهد. نسخهٔ مخصوص PHP 7.4 جایگزین نشده است.
هش SHA1 مورد انتظار همان 321791ab0231890e4569d17c52e64ed93d2eb42d است.

محتویات
- Plugin/signa-otp.zip: فایل نصب فعلی (همان deliverables/signa-otp-with-logo.zip)
- Signa-Help-FA.pdf: راهنمای فارسی ۳ صفحه‌ای نصب، تنظیمات، استفاده و به‌روزرسانی
- changelog-FA.txt: تاریخچه و توضیح تغییرات بسته

نیازمندی‌ها
- حداقل PHP 8.2 و وردپرس 5.8
- ionCube فعال در PHP وب و سازگار با PHP و فایل لایسنس
- افزونهٔ مدیریت هوشمند راست‌چین و تأیید لایسنس برای سایت اصلی
حداقل PHP به معنی تضمین سازگاری فایل رمزگذاری‌شده با همهٔ نسخه‌های جدیدتر نیست.

نصب: ZIP داخلی Plugin/signa-otp.zip را نصب کنید، نه ZIP بیرونی شامل راهنما.
هدر وردپرس در index.php و بررسی لایسنس در signa.php باقی مانده است.
پیش از تغییر PHP یا جایگزینی افزونه، از سایت و فایل لایسنس بکاپ بگیرید.

SHA256 فایل نصب فعلی (پیش از افزودن فایل لایسنس):
{installer_sha}
'''
wrapper.parent.mkdir(exist_ok=True)
with ZipFile(wrapper, 'w', ZIP_DEFLATED) as z:
    z.write(installer, 'Signa-Rightchin/Plugin/signa-otp.zip')
    z.write(guide, 'Signa-Rightchin/Signa-Help-FA.pdf')
    z.write(root / 'docs/changelog-FA.txt', 'Signa-Rightchin/changelog-FA.txt')
    z.writestr('Signa-Rightchin/README-FA.txt', readme)
with ZipFile(wrapper) as z:
    assert z.testzip() is None
    assert z.read('Signa-Rightchin/Plugin/signa-otp.zip') == installer.read_bytes()
    assert z.read('Signa-Rightchin/Signa-Help-FA.pdf') == guide.read_bytes()

def record(path, name):
    return {'file': name, 'bytes': path.stat().st_size, 'sha256': hashlib.sha256(path.read_bytes()).hexdigest()}

manifest = root / 'deliverables/checksums.json'
entries = json.loads(manifest.read_text())
entries = [entry for entry in entries if entry['file'] != guide.name]
entries.append(record(guide, guide.name))
manifest.write_text(json.dumps(entries, indent=2) + '\n')
(root / 'rightchin/checksums.json').write_text(json.dumps([
    record(wrapper, wrapper.name), record(guide, '../deliverables/' + guide.name),
    record(installer, '../deliverables/' + installer.name)], indent=2) + '\n')
print(json.dumps(record(wrapper, wrapper.name), indent=2))
print('PASS: wrapper contains byte-identical current installer and updated PDF; missing-license notice included.')
