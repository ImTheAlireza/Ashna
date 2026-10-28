"""Package the current Ashna plugin ZIPs.
Run from any directory: python scripts/package-deliverables.py
"""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import hashlib
import json
import argparse
import re

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument("--plugin-only", action="store_true", help="Accepted for backward compatibility; only plugin ZIPs are built now.")
variants = parser.add_mutually_exclusive_group()
variants.add_argument("--without-license", action="store_true", help="Build a separate, unencoded license-free plugin ZIP.")
variants.add_argument("--without-license-file", action="store_true", help="Build the licensed plugin without bundling its vendor license file.")
parser.add_argument("--output-dir", type=Path, help="Optional directory for the generated ZIP and manifest.")
args = parser.parse_args()

ROOT = Path(__file__).resolve().parents[1]
OUT = args.output_dir.resolve() if args.output_dir else ROOT / 'deliverables'
OUT.mkdir(parents=True, exist_ok=True)

LICENSE_MISSING_NOTE = 'این بسته فایل لایسنس ندارد؛ فایل اصلی صادرشده توسط راست‌چین باید بدون تغییر کنار ashna.php قرار بگیرد.'
LICENSE_INCLUDED_NOTE = 'فایل لایسنسِ موجود در مخزن با هش مورد انتظار بررسی شده و داخل بسته قرار گرفته است؛ تأیید فعال‌بودن آن همچنان در سایت مقصد انجام می‌شود.'

plugin_readme = '''آشنا | ورود و ثبت‌نام با کد یکبارمصرف — نسخهٔ ۳.۱.۴
محصولی از پارسنا — https://parsena.ir/ashna

این ZIP از کد فعلی مخزن ساخته شده است. لوگوی آشنا در سربرگ مدیریت، منوی وردپرس، فرم ورود پیش‌فرض و پیش‌نمایش زنده استفاده شده و لوگوی سفارشی انتخاب‌شده توسط مدیر سایت همچنان اولویت دارد.

ورود با گوگل و تلگرام (OIDC + PKCE) در همین نسخه اضافه شده است؛ تب «ورود با گوگل و تلگرام» برای هر سرویس آدرس بازگشتی جداگانه، راهنمای مرحله‌به‌مرحلهٔ کنسول و دکمهٔ «بررسی اتصال» دارد. آدرس هر سرویس را از همان کارت کپی و در کنسول گوگل (Authorized redirect URIs) یا BotFather (Web Login → Redirect URI) ثبت کنید. اگر پیش‌تر نسخهٔ ۳.۱.۰ یا ۳.۱.۱ را با آدرس مشترک ‎/ashna-sso/callback/‎ تنظیم کرده‌اید، آدرس تازهٔ هر سرویس را اضافه کنید؛ آدرس قدیمی برای بازگشت همچنان پذیرفته می‌شود.

نصب: پیشخوان وردپرس ← افزونه‌ها ← افزودن ← بارگذاری افزونه.
قبل از نصب یا جایگزینی، از سایت و دیتابیس نسخهٔ پشتیبان بگیرید.

مهم: فعال‌شدن امکانات به فایل معتبر RTL_License_c1595a7238d64f3a.php با هش 3fac0395eb82fa65036a1e9aabcf0aac9c2bcedc و تأیید isActive() نیاز دارد. {license_note}
وردپرس حداقل ۵.۸ و PHP حداقل ۸.۲ مطابق سربرگ افزونه لازم است؛ فعال‌سازی استاندارد وردپرس روی PHP پایین‌تر رد می‌شود. نصب این ZIP، PHP هاست را تغییر نمی‌دهد. اگر فایل لایسنس یا فایل محافظت‌شده ionCube باشد، Loader باید در PHP وب فعال و سازگار با همان فایل و PHP باشد؛ سازگاری با همهٔ نسخه‌های جدیدتر PHP تضمین نشده است.
بررسی مسیر لایسنس بعد از بارگذاری وردپرس انجام می‌شود. خطاهای قابل‌گرفتنِ فایل لایسنس، بدون فعال‌کردن امکانات، با کد تشخیصی در پیشخوان نمایش داده می‌شوند. هش و شرط isActive() === true همچنان اجباری هستند. اگر فایل لایسنس ionCube باشد و Loader روی PHP وب فعال نباشد، کد license_loader_missing نمایش داده می‌شود و فایل لایسنس اجرا نمی‌شود؛ این حالت را با افزونهٔ عیب‌یابی آشنا قبل از تماس با پشتیبانی بررسی کنید.

فایل‌های تبلیغاتی، Git، تست‌ها، کش‌ها و node_modules داخل بستهٔ افزونه قرار نگرفته‌اند.
'''
if args.without_license:
    plugin_readme = '''نسخهٔ بدون لایسنس و بدون کدگذاری آشنا — ۳.۱.۴
محصولی از پارسنا — https://parsena.ir/ashna

این بستهٔ جداگانه فقط برای تست و عیب‌یابی است و بررسی لایسنس راست‌چین در فایل ashna.php ندارد. به فایل RTL_License، ionCube یا SourceGuardian برای کد خود افزونه نیاز ندارد. نسخهٔ قابل ارسال به راست‌چین، بستهٔ لایسنس‌دار ashna.zip است.

ابتدا از سایت و دیتابیس بکاپ بگیرید؛ ترجیحاً روی سایت آزمایشی تست کنید.
اگر نسخهٔ لایسنس‌دار آشنا نصب است، آن را غیرفعال کنید و این ZIP را از بخش بارگذاری افزونه روی همان پوشهٔ ashna جایگزین و سپس فعال کنید. دو نسخه را هم‌زمان فعال نکنید.
برای حفظ تنظیمات، لازم نیست افزونه را از طریق دکمهٔ «حذف» وردپرس پاک کنید.
پس از استخراج دستی، فایل ashna.php کدگذاری‌شدهٔ قبلی را نگه ندارید؛ باید با فایل متنی همین بسته جایگزین شود.

حداقل وردپرس ۵.۸ و PHP ۷.۴ مطابق سربرگ همین بسته.
این نسخه برای جداکردن احتمال خطای Loader/لایسنس از سایر خطاهای هاست مناسب است؛ رفع قطعی خطای گزارش‌شدهٔ سایت، بدون گزارش خطا قابل تأیید نیست.
'''
def add_bytes(bundle, name, content):
    bundle.writestr(name, content.encode('utf-8'))

def archive_check(path, prefix):
    with ZipFile(path) as bundle:
        assert bundle.testzip() is None
        names = bundle.namelist()
        assert len(names) == len(set(names))
        for name in names:
            assert name.startswith(prefix + '/')
            assert not set(Path(name).parts) & {'.git', '.cache', '.venv', '__pycache__', 'node_modules'}
    return {'file': path.name, 'bytes': path.stat().st_size,
            'sha256': hashlib.sha256(path.read_bytes()).hexdigest(), 'files': len(names)}

license_file = ROOT / 'RTL_License_c1595a7238d64f3a.php'
include_license = not args.without_license and not args.without_license_file and license_file.is_file()
if include_license:
    expected_hash = re.search(r"'([0-9a-f]{40})'\s*!==\s*\$rtlLicenseFileHash", (ROOT / 'ashna.php').read_text())
    if not expected_hash or hashlib.sha1(license_file.read_bytes()).hexdigest() != expected_hash.group(1):
        raise SystemExit('License file does not match the verifier hash; refusing to package a mismatched license.')

if not args.without_license:
    plugin_readme = plugin_readme.format(license_note=LICENSE_INCLUDED_NOTE if include_license else LICENSE_MISSING_NOTE)

SLUG = 'ashna'
plugin = OUT / (SLUG + '-no-license.zip' if args.without_license else SLUG + '.zip')
with ZipFile(plugin, 'w', ZIP_DEFLATED) as bundle:
    for name in ['ashna.php', 'index.php', 'uninstall.php', 'README.md']:
        if args.without_license and name == 'ashna.php':
            # Header and bootstrap live in the same unencoded file for this edition.
            bundle.write(ROOT / 'scripts/templates/ashna-no-license.php', SLUG + '/ashna.php')
        else:
            bundle.write(ROOT / name, SLUG + '/' + name)
    for folder in ['assets', 'includes', 'languages', 'templates']:
        for source in sorted((ROOT / folder).rglob('*')):
            if source.is_file():
                # The diagnostic no-license build does not need the licensed gate.
                if args.without_license and source == ROOT / 'includes/class-ashna-license-guard.php':
                    continue
                bundle.write(source, SLUG + '/' + source.relative_to(ROOT).as_posix())
    if include_license:
        bundle.write(license_file, SLUG + '/' + license_file.name)
    add_bytes(bundle, SLUG + '/README-INSTALL-FA.txt', plugin_readme)

# Acceptance check: nothing from the previous product name may ship in the plugin.
LEGACY_NAME = re.compile(r'(?<![A-Za-z])sign[aA](?![a-z])|(?<![A-Z])Signa(?![a-z])|SIGNA(?![A-Z])|سیگنا')
with ZipFile(plugin) as bundle:
    for name in bundle.namelist():
        content = bundle.read(name)
        hits = [name] if LEGACY_NAME.search(name) else []
        if not name.endswith(('.png', '.woff2')) and LEGACY_NAME.search(content.decode('utf-8', errors='ignore')):
            hits.append(name + ' (content)')
        if hits:
            raise SystemExit('Legacy product name found in plugin package: ' + ', '.join(hits))

report = [archive_check(OUT / name, SLUG) for name in [SLUG + '.zip', SLUG + '-no-license.zip'] if (OUT / name).exists()]
diagnostics = OUT / 'ashna-activation-diagnostics.zip'
if diagnostics.exists():
    report.append(archive_check(diagnostics, 'ashna-activation-diagnostics'))
for guide in [OUT / 'help.pdf']:
    if guide.exists():
        report.append({'file': guide.name, 'bytes': guide.stat().st_size,
                       'sha256': hashlib.sha256(guide.read_bytes()).hexdigest()})
# checksums.json is shared with scripts/package-landing.py: keep the entries this
# script does not produce, as long as the file is still on disk.
checksums = OUT / 'checksums.json'
produced = {entry['file'] for entry in report}
if checksums.exists():
    for entry in json.loads(checksums.read_text()):
        if entry['file'] not in produced and (OUT / entry['file']).exists():
            report.append(entry)
checksums.write_text(json.dumps(report, indent=2) + '\n')
print(json.dumps(report, indent=2))
