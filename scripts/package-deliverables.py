"""Package the current plugin and the standalone HTML/CSS gallery website.
Run from any directory: python scripts/package-deliverables.py
"""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import hashlib
import json
import argparse
import re

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument("--plugin-only", action="store_true", help="Rebuild only the plugin ZIP, preserving the website archive.")
parser.add_argument("--without-license", action="store_true", help="Build a separate, unencoded license-free plugin ZIP; leave the licensed ZIP and website unchanged.")
args = parser.parse_args()

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'deliverables'
OUT.mkdir(exist_ok=True)
DESIGN = ROOT / 'product-images/gallery-design'

plugin_readme = '''نسخهٔ فعلی سیگنا با لوگوی جدید — ۲.۷.۷

این ZIP از کد فعلی مخزن ساخته شده و شامل لوگوی جدید در سربرگ مدیریت، منوی وردپرس، فرم ورود پیش‌فرض و پیش‌نمایش زنده است.
لوگوی سفارشی انتخاب‌شده توسط مدیر سایت همچنان اولویت دارد.

نصب: پیشخوان وردپرس ← افزونه‌ها ← افزودن ← بارگذاری افزونه.
قبل از جایگزینی نسخهٔ نصب‌شده، از سایت و دیتابیس نسخهٔ پشتیبان بگیرید.

مهم: هش بررسی فایل لایسنس به مقدار جدید ارسالی مالک محصول به‌روز شده است. فعال‌شدن امکانات همچنان به فایل معتبر RTL_License_c1595a7238d64f3a.php و تأیید isActive() نیاز دارد. فایل واقعی لایسنس در کد فعلی مخزن موجود نیست و این بسته آن را ایجاد نمی‌کند؛ فایل صادرشده توسط راست‌چین باید کنار signa.php قرار بگیرد.
وردپرس حداقل ۵.۸ و PHP حداقل ۷.۴ مطابق سربرگ افزونه لازم است.
بررسی مسیر لایسنس بعد از بارگذاری وردپرس انجام می‌شود. خطاهای قابل‌گرفتنِ فایل لایسنس، بدون فعال‌کردن امکانات، با کد تشخیصی در پیشخوان نمایش داده می‌شوند. هش و شرط isActive() === true همچنان اجباری هستند.
تست‌های خودکار با هوک واقعی وردپرس و سرویس‌های شبیه‌سازی‌شده انجام شده‌اند؛ تست با فایل واقعی لایسنس و وردپرس زندهٔ مشتری انجام نشده است.

فایل‌های تبلیغاتی، گالری، Git، کش‌ها و node_modules داخل بستهٔ افزونه قرار نگرفته‌اند.
'''
if args.without_license:
    plugin_readme = '''نسخهٔ بدون لایسنس و بدون کدگذاری سیگنا — ۲.۷.۷

این بستهٔ جداگانه از کد فعلی افزونه ساخته شده و بررسی لایسنس راست‌چین در فایل signa.php ندارد. به فایل RTL_License، ionCube یا SourceGuardian برای کد خود افزونه نیاز ندارد. لوگو و سایر امکانات بدون تغییر حفظ شده‌اند.
نسخهٔ لایسنس‌دار مخزن و ZIP قبلی تغییر نکرده‌اند.

ابتدا از سایت و دیتابیس بکاپ بگیرید؛ ترجیحاً روی سایت آزمایشی تست کنید.
نسخهٔ قبلی سیگنا را غیرفعال کنید، این ZIP را از بخش بارگذاری افزونه روی همان پوشهٔ signa-otp جایگزین و سپس فعال کنید. دو نسخه را هم‌زمان فعال نکنید.
برای حفظ تنظیمات، لازم نیست افزونه را از طریق دکمهٔ «حذف» وردپرس پاک کنید.
پس از استخراج دستی، فایل signa.php قدیمیِ کدگذاری‌شده را نگه ندارید؛ باید با فایل متنی همین بسته جایگزین شود.

حداقل وردپرس ۵.۸ و PHP ۷.۴ مطابق سربرگ افزونه.
این نسخه برای جداکردن احتمال خطای Loader/لایسنس از سایر خطاهای هاست مناسب است؛ رفع قطعی خطای گزارش‌شدهٔ سایت، بدون گزارش خطا قابل تأیید نیست.
'''
website_readme = '''# کد وب‌سایت گالری سیگنا

این بسته، صفحهٔ وب گالری ساخته‌شده در این گفتگو است، نه کد یک سایت دموی خارجی. همهٔ فونت‌ها، لوگوها، تصاویر و طرح‌های HTML/CSS داخل بسته‌اند و مسیرها نسبی هستند. به وردپرس یا CDN نیازی ندارد.

## استفاده

پوشهٔ signa-website را استخراج کنید و محتویاتش را روی هاست استاتیک قرار دهید.
صفحهٔ شروع: index.html
طرح‌های HTML قابل ویرایش: render.html?slide=1 تا render.html?slide=8
متن‌ها و طرح‌ها: slides.js
فونت، رنگ، ابعاد و چیدمان: styles.css
خروجی‌ها: gallery/*.webp
دانلود گروهی: signa-gallery-8-images.zip
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

    SIGNA_BUNDLED_LIBS=1 node export.mjs

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
    expected_hash = re.search(r"'([0-9a-f]{40})'\s*!==\s*\$rtlLicenseFileHash", (ROOT / 'signa.php').read_text())
    if not expected_hash or hashlib.sha1(license_file.read_bytes()).hexdigest() != expected_hash.group(1):
        raise SystemExit('License file does not match the verifier hash; refusing to package a mismatched license.')
    plugin_readme = plugin_readme.replace('فایل واقعی لایسنس در کد فعلی مخزن موجود نیست و این بسته آن را ایجاد نمی‌کند؛ فایل صادرشده توسط راست‌چین باید کنار signa.php قرار بگیرد.', 'فایل لایسنسِ موجود در مخزن با هش مورد انتظار بررسی شده و داخل بسته قرار گرفته است؛ تأیید فعال‌بودن آن همچنان در سایت مقصد انجام می‌شود.')

plugin = OUT / ('signa-otp-no-license.zip' if args.without_license else 'signa-otp-with-logo.zip')
with ZipFile(plugin, 'w', ZIP_DEFLATED) as bundle:
    for name in ['index.php', 'signa.php', 'uninstall.php', 'README.md']:
        if args.without_license and name == 'signa.php':
            bundle.write(ROOT / 'scripts/templates/signa-no-license.php', 'signa-otp/signa.php')
        elif args.without_license and name == 'index.php':
            index = (ROOT / name).read_text().replace('Signa - ورود', 'Signa (بدون لایسنس) - ورود', 1)
            add_bytes(bundle, 'signa-otp/index.php', index)
        else:
            bundle.write(ROOT / name, 'signa-otp/' + name)
    for folder in ['assets', 'includes', 'languages', 'templates']:
        for source in sorted((ROOT / folder).rglob('*')):
            if source.is_file():
                bundle.write(source, 'signa-otp/' + source.relative_to(ROOT).as_posix())
    if include_license:
        bundle.write(license_file, 'signa-otp/' + license_file.name)
    add_bytes(bundle, 'signa-otp/README-INSTALL-FA.txt', plugin_readme)

website = OUT / 'signa-website-source.zip'
if not args.plugin_only and not args.without_license:
    with ZipFile(website, 'w', ZIP_DEFLATED) as bundle:
        for name in ['index.html', 'render.html', 'styles.css', 'slides.js', 'package.json', 'package-lock.json']:
            text = (DESIGN / name).read_text()
            text = text.replace('../../assets/', 'assets/').replace('../gallery/', 'gallery/').replace('../signa-gallery-8-images.zip', 'signa-gallery-8-images.zip')
            add_bytes(bundle, 'signa-website/' + name, text)
        export = (DESIGN / 'export.mjs').read_text().replace("path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')", "path.dirname(fileURLToPath(import.meta.url))").replace('http://127.0.0.1:8000/product-images/gallery-design/', 'http://127.0.0.1:8000/')
        add_bytes(bundle, 'signa-website/export.mjs', export)
        pack = (DESIGN / 'package_images.py').read_text().replace('Path(__file__).resolve().parents[2]', 'Path(__file__).resolve().parent').replace("'product-images/gallery'", "'gallery'").replace("'product-images/signa-gallery-8-images.zip'", "'signa-gallery-8-images.zip'")
        add_bytes(bundle, 'signa-website/package_images.py', pack)
        add_bytes(bundle, 'signa-website/README-FA.md', website_readme)
        for source in sorted((DESIGN / 'logos').glob('*')):
            if source.is_file(): bundle.write(source, 'signa-website/logos/' + source.name)
        for weight in [400, 500, 700, 900]:
            name = f'assets/fonts/estedad/estedad-{weight}.woff2'
            bundle.write(ROOT / name, 'signa-website/' + name)
        for name in ['assets/fonts/estedad/LICENSE.txt', 'assets/images/signa-icon.png', 'assets/images/signa-logo.png']:
            bundle.write(ROOT / name, 'signa-website/' + name)
        report = json.loads((ROOT / 'product-images/gallery/manifest.json').read_text())
        assert len(report) == 8
        for entry in report:
            name = entry['file'] + '.webp'
            bundle.write(ROOT / 'product-images/gallery' / name, 'signa-website/gallery/' + name)
        bundle.write(ROOT / 'product-images/signa-gallery-8-images.zip', 'signa-website/signa-gallery-8-images.zip')

report = [archive_check(OUT / name, 'signa-otp') for name in ['signa-otp-with-logo.zip', 'signa-otp-no-license.zip'] if (OUT / name).exists()]
if website.exists():
    report.append(archive_check(website, 'signa-website'))
(OUT / 'checksums.json').write_text(json.dumps(report, indent=2) + '\n')
print(json.dumps(report, indent=2))
