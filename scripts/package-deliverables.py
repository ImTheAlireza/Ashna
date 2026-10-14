"""Package the current plugin and the standalone HTML/CSS gallery website.
Run from any directory: python scripts/package-deliverables.py
"""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import hashlib
import json
import argparse
import re
import shutil

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument("--plugin-only", action="store_true", help="Rebuild only the plugin ZIP, preserving the website archive.")
parser.add_argument("--without-license", action="store_true", help="Build a separate, unencoded license-free plugin ZIP; leave the licensed ZIP and website unchanged.")
args = parser.parse_args()

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'deliverables'
OUT.mkdir(exist_ok=True)
DESIGN = ROOT / 'product-images/gallery-design'

plugin_readme = '''نسخهٔ فعلی آشنا با لوگوی جدید — ۲.۷.۹

این ZIP از کد فعلی مخزن ساخته شده و شامل لوگوی جدید در سربرگ مدیریت، منوی وردپرس، فرم ورود پیش‌فرض و پیش‌نمایش زنده است.
لوگوی سفارشی انتخاب‌شده توسط مدیر سایت همچنان اولویت دارد.

نصب: پیشخوان وردپرس ← افزونه‌ها ← افزودن ← بارگذاری افزونه.
قبل از جایگزینی نسخهٔ نصب‌شده، از سایت و دیتابیس نسخهٔ پشتیبان بگیرید. نسخهٔ قدیمی را غیرفعال کنید، اما برای ارتقا از دکمهٔ «حذف» وردپرس استفاده نکنید؛ پوشهٔ کامل ashna-otp را نصب و سپس فعال کنید. دو نسخه را هم‌زمان فعال نکنید. فایل‌های دو پوشه را مخلوط نکنید؛ تنظیمات و داده‌ها از ذخیره‌سازی قبلی حفظ می‌شوند.

مهم: نسخهٔ قبلی لایسنس و هش 4f106eb02a251f2a6951ca3f530596590081002d حفظ شده است؛ فایل جایگزین مخصوص PHP 7.4 استفاده نمی‌شود. فعال‌شدن امکانات همچنان به فایل معتبر RTL_License_c1595a7238d64f3a.php و تأیید isActive() نیاز دارد. فایل واقعی لایسنس در کد فعلی مخزن موجود نیست و این بسته آن را ایجاد نمی‌کند؛ فایل صادرشده توسط راست‌چین باید کنار ashna.php قرار بگیرد.
وردپرس حداقل ۵.۸ و PHP حداقل ۸.۲ مطابق سربرگ افزونه لازم است؛ فعال‌سازی استاندارد وردپرس روی PHP پایین‌تر رد می‌شود. نصب این ZIP، PHP هاست را تغییر نمی‌دهد. ionCube Loader باید در PHP وب فعال و با همان PHP و فایل لایسنس سازگار باشد؛ سازگاری با همهٔ نسخه‌های جدیدتر PHP تضمین نشده است.
بررسی مسیر لایسنس بعد از بارگذاری وردپرس انجام می‌شود. خطاهای قابل‌گرفتنِ فایل لایسنس، بدون فعال‌کردن امکانات، با کد تشخیصی در پیشخوان نمایش داده می‌شوند. هش و شرط isActive() === true همچنان اجباری هستند.
تست‌های خودکار با هوک واقعی وردپرس و سرویس‌های شبیه‌سازی‌شده انجام شده‌اند؛ تست با فایل واقعی لایسنس و وردپرس زندهٔ مشتری انجام نشده است.

فایل‌های تبلیغاتی، گالری، Git، کش‌ها و node_modules داخل بستهٔ افزونه قرار نگرفته‌اند.
'''
if args.without_license:
    plugin_readme = '''نسخهٔ بدون لایسنس و بدون کدگذاری آشنا — ۲.۷.۹

این بستهٔ جداگانه از کد فعلی افزونه ساخته شده و بررسی لایسنس راست‌چین در فایل ashna.php ندارد. به فایل RTL_License، ionCube یا SourceGuardian برای کد خود افزونه نیاز ندارد. لوگو و سایر امکانات بدون تغییر حفظ شده‌اند.
نسخهٔ لایسنس‌دار مخزن و ZIP قبلی تغییر نکرده‌اند.

ابتدا از سایت و دیتابیس بکاپ بگیرید؛ ترجیحاً روی سایت آزمایشی تست کنید.
نسخهٔ قبلی را غیرفعال کنید؛ بدون حذف داده‌ها، پوشهٔ کامل ashna-otp را نصب و فعال کنید. اگر پوشهٔ قبلی نام دیگری دارد، فایل‌ها را با هم مخلوط نکنید. دو نسخه را هم‌زمان فعال نکنید. تنظیمات، کاربران، Passkeyها و لاگ‌ها از همان ذخیره‌سازی قبلی خوانده می‌شوند.
برای حفظ تنظیمات، لازم نیست افزونه را از طریق دکمهٔ «حذف» وردپرس پاک کنید.
پس از استخراج دستی، فایل ashna.php قدیمیِ کدگذاری‌شده را نگه ندارید؛ باید با فایل متنی همین بسته جایگزین شود.

حداقل وردپرس ۵.۸ و PHP ۷.۴ مطابق سربرگ افزونه.
این نسخه برای جداکردن احتمال خطای Loader/لایسنس از سایر خطاهای هاست مناسب است؛ رفع قطعی خطای گزارش‌شدهٔ سایت، بدون گزارش خطا قابل تأیید نیست.
'''
website_readme = '''# کد وب‌سایت گالری آشنا

این بسته، صفحهٔ وب گالری ساخته‌شده در این گفتگو است، نه کد یک سایت دموی خارجی. همهٔ فونت‌ها، لوگوها، تصاویر و طرح‌های HTML/CSS داخل بسته‌اند و مسیرها نسبی هستند. به وردپرس یا CDN نیازی ندارد.

## استفاده

پوشهٔ ashna-website را استخراج کنید و محتویاتش را روی هاست استاتیک قرار دهید.
صفحهٔ شروع: index.html
طرح‌های HTML قابل ویرایش: render.html?slide=1 تا render.html?slide=8
متن‌ها و طرح‌ها: slides.js
فونت، رنگ، ابعاد و چیدمان: styles.css
خروجی‌ها: gallery/*.webp
دانلود گروهی: ashna-gallery-8-images.zip
منابع نشان‌ها و توضیح آیکون IPPanel: logos/SOURCES.md

برای پیش‌نمایش محلی، از داخل همین پوشه اجرا کنید:

    python -m http.server 8000 --bind 0.0.0.0

سپس http://localhost:8000 را در همان دستگاه باز کنید.

این رابط‌ها تصویرسازی معرفی محصول هستند؛ ورود، ارسال پیامک و احراز هویت واقعی در این وب‌سایت استاتیک انجام نمی‌شود.
تصاویر ۲، ۳ و ۵ نسخهٔ بازطراحی‌شده هستند و ۵ سامانه‌های ارسال را معرفی می‌کند.
هر هشت تصویر ۱۶۰۰ × ۱۸۰۰ پیکسل و کمتر از یک مگابایت هستند.

## بازسازی اختیاری خروجی تصاویر

Node.js 22.17+ و Python 3.9+ توصیه می‌شود. سرور بالا را در ترمینال جداگانه فعال نگه دارید، سپس:

    npm ci
    node export.mjs
    python -m venv .venv
    .venv/bin/pip install Pillow
    .venv/bin/python package_images.py

در لینوکس حداقلی در صورت نبود کتابخانه‌های NSS/NSPR:

    ASHNA_BUNDLED_LIBS=1 node export.mjs

وابستگی‌ها و مرورگر از قبل داخل ZIP قرار نگرفته‌اند. این ابزارها فقط برای تولید مجدد خروجی‌ها لازم‌اند، نه برای نمایش صفحه.
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
include_license = not args.without_license and license_file.is_file()
if include_license:
    expected_hash = re.search(r"'([0-9a-f]{40})'\s*!==\s*\$rtlLicenseFileHash", (ROOT / 'ashna.php').read_text())
    if not expected_hash or hashlib.sha1(license_file.read_bytes()).hexdigest() != expected_hash.group(1):
        raise SystemExit('License file does not match the verifier hash; refusing to package a mismatched license.')
    plugin_readme = plugin_readme.replace('فایل واقعی لایسنس در کد فعلی مخزن موجود نیست و این بسته آن را ایجاد نمی‌کند؛ فایل صادرشده توسط راست‌چین باید کنار ashna.php قرار بگیرد.', 'فایل لایسنسِ موجود در مخزن با هش مورد انتظار بررسی شده و داخل بسته قرار گرفته است؛ تأیید فعال‌بودن آن همچنان در سایت مقصد انجام می‌شود.')

plugin = OUT / ('ashna-otp-no-license.zip' if args.without_license else 'ashna-otp-with-logo.zip')
with ZipFile(plugin, 'w', ZIP_DEFLATED) as bundle:
    for name in ['index.php', 'ashna.php', 'uninstall.php', 'README.md', 'rtl-description.md']:
        if args.without_license and name == 'ashna.php':
            bundle.write(ROOT / 'scripts/templates/ashna-no-license.php', 'ashna-otp/ashna.php')
        elif args.without_license and name == 'index.php':
            index = (ROOT / name).read_text().replace('Ashna - ورود', 'Ashna (بدون لایسنس) - ورود', 1)
            # The separate unencoded edition does not share the licensed file's PHP floor.
            index = re.sub(r'(Requires PHP:\s*)[0-9.]+', r'\g<1>7.4', index, count=1)
            add_bytes(bundle, 'ashna-otp/index.php', index)
        else:
            bundle.write(ROOT / name, 'ashna-otp/' + name)
    for folder in ['assets', 'includes', 'languages', 'templates']:
        for source in sorted((ROOT / folder).rglob('*')):
            if source.is_file() and not source.is_relative_to(ROOT / 'assets/images/ashna'):
                bundle.write(source, 'ashna-otp/' + source.relative_to(ROOT).as_posix())
    if include_license:
        bundle.write(license_file, 'ashna-otp/' + license_file.name)
    add_bytes(bundle, 'ashna-otp/README-INSTALL-FA.txt', plugin_readme)

if not args.without_license:
    shutil.copyfile(plugin, ROOT / 'ashna-otp.zip')

website = OUT / 'ashna-website-source.zip'
if not args.plugin_only and not args.without_license:
    with ZipFile(website, 'w', ZIP_DEFLATED) as bundle:
        for name in ['index.html', 'render.html', 'styles.css', 'slides.js', 'package.json', 'package-lock.json', 'cover.html']:
            text = (DESIGN / name).read_text()
            text = text.replace('../../assets/', 'assets/').replace('../gallery/', 'gallery/').replace('../ashna-gallery-8-images.zip', 'ashna-gallery-8-images.zip')
            add_bytes(bundle, 'ashna-website/' + name, text)
        export = (DESIGN / 'export.mjs').read_text().replace("path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')", "path.dirname(fileURLToPath(import.meta.url))").replace('http://127.0.0.1:8000/product-images/gallery-design/', 'http://127.0.0.1:8000/')
        add_bytes(bundle, 'ashna-website/export.mjs', export)
        pack = (DESIGN / 'package_images.py').read_text().replace('Path(__file__).resolve().parents[2]', 'Path(__file__).resolve().parent').replace("'product-images/gallery'", "'gallery'").replace("'product-images/ashna-gallery-8-images.zip'", "'ashna-gallery-8-images.zip'")
        add_bytes(bundle, 'ashna-website/package_images.py', pack)
        add_bytes(bundle, 'ashna-website/README-FA.md', website_readme)
        for source in sorted((DESIGN / 'logos').glob('*')):
            if source.is_file(): bundle.write(source, 'ashna-website/logos/' + source.name)
        for weight in [400, 500, 700, 900]:
            name = f'assets/fonts/estedad/estedad-{weight}.woff2'
            bundle.write(ROOT / name, 'ashna-website/' + name)
        for name in ['assets/fonts/estedad/LICENSE.txt', 'assets/images/ashna-icon.png', 'assets/images/ashna-logo.png']:
            bundle.write(ROOT / name, 'ashna-website/' + name)
        report = json.loads((ROOT / 'product-images/gallery/manifest.json').read_text())
        assert len(report) == 8
        for entry in report:
            name = entry['file'] + '.webp'
            bundle.write(ROOT / 'product-images/gallery' / name, 'ashna-website/gallery/' + name)
        bundle.write(ROOT / 'product-images/ashna-gallery-8-images.zip', 'ashna-website/ashna-gallery-8-images.zip')

report = [archive_check(OUT / name, 'ashna-otp') for name in ['ashna-otp-with-logo.zip', 'ashna-otp-no-license.zip'] if (OUT / name).exists()]
if website.exists():
    report.append(archive_check(website, 'ashna-website'))
diagnostics = OUT / 'ashna-activation-diagnostics.zip'
if diagnostics.exists():
    report.append(archive_check(diagnostics, 'ashna-activation-diagnostics'))
kit = OUT / 'ashna-logo-kit.zip'
if kit.exists():
    report.append(archive_check(kit, 'ashna-logo-kit'))
guide = OUT / 'Ashna-Help-FA.pdf'
if guide.exists():
    report.append({'file': guide.name, 'bytes': guide.stat().st_size,
                   'sha256': hashlib.sha256(guide.read_bytes()).hexdigest()})
(OUT / 'checksums.json').write_text(json.dumps(report, indent=2) + '\n')
print(json.dumps(report, indent=2))
