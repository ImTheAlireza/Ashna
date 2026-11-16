"""Package the product images for the RTL-Theme listing.

Builds deliverables/ashna-product-images.zip with the three images the seller
uploads, plus JPG copies of the gallery (the panel takes JPG/PNG; the sources
are WebP):

    ashna-cover.jpg            2100x1040  کاور دسکتاپ
    ashna-cover-mobile.jpg     1200x1200  کاور موبایل
    ashna-thumbnail-320.png    320x320    آیکون محصول (PNG)
    ashna-thumbnail-320.jpg    320x320    همان آیکون (JPG)
    gallery/0N-*.jpg           1600x1800  گالری، آمادهٔ آپلود
    gallery-webp/0N-*.webp     نسخهٔ اصلی سبک‌تر برای صفحهٔ سایت
    README-FA.txt              اندازه‌ها و کاربرد هر فایل

Run: python3 scripts/package-product-images.py
"""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from PIL import Image
import hashlib
import json
import tempfile

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'deliverables'
IMAGES = ROOT / 'product-images'
TARGET = OUT / 'ashna-product-images.zip'

SOURCES = {
    'ashna-cover.jpg': IMAGES / 'cover' / 'ashna-cover.jpg',
    'ashna-cover-mobile.jpg': IMAGES / 'cover' / 'ashna-cover-mobile.jpg',
    'ashna-thumbnail-320.png': IMAGES / 'ashna-icon-320.png',
    'ashna-thumbnail-320.jpg': IMAGES / 'ashna-icon-320.jpg',
}

README = """تصاویر محصول آشنا — بستهٔ آمادهٔ آپلود در راست‌چین
==================================================

کاورها
------
  ashna-cover.jpg          ۲۱۰۰×۱۰۴۰   کاور اصلی فهرست محصول (دسکتاپ)
  ashna-cover-mobile.jpg   ۱۲۰۰×۱۲۰۰   کاور موبایل / شبکه‌های اجتماعی

بندانگشتی (آیکون محصول)
------------------------
  ashna-thumbnail-320.png  ۳۲۰×۳۲۰، RGB، بدون شفافیت
  ashna-thumbnail-320.jpg  همان تصویر با حجم کمتر (۲۲ کیلوبایت)

گالری — ۸ تصویر ۱۶۰۰×۱۸۰۰
--------------------------
  gallery/01-otp.jpg          ورود با کد پیامکی، بدون رمز عبور
  gallery/02-gateways.jpg     شش درگاه پیامکی ایرانی
  gallery/03-limits.jpg       ضد اسپم و بمبر با سقف دقیقه‌ای
  gallery/04-failover.jpg     زنجیرهٔ نجات و تعویض خودکار درگاه
  gallery/05-sso.jpg          ورود با گوگل، تلگرام و Passkey
  gallery/06-appearance.jpg   شخصی‌سازی ظاهر فرم
  gallery/07-panel.jpg        پنل مدیریت نه‌تبی
  gallery/08-form.jpg         فرم ورود روی سایت

  gallery-webp/             همان هشت تصویر با فرمت WebP (حجم کمتر، برای صفحهٔ
                            محصول در سایت خودتان؛ اگر پنل راست‌چین WebP قبول
                            نکرد از نسخهٔ JPG استفاده کنید)

نکته‌ها
-------
* همهٔ تصاویر RGB و بدون شفافیت‌اند (شرط پذیرش تصاویر در پنل).
* کاورها و گالری از اسکرین‌شات واقعی محصول ساخته شده‌اند؛ هیچ تصویر استوک یا
  تولیدشده با هوش مصنوعی در بسته نیست.
* آیکون در ۹۶ پیکسل هم خوانا مانده (بررسی‌شده) و حاشیهٔ امن ۱۰٪ دارد.
"""


def main():
    missing = [str(path) for path in SOURCES.values() if not path.is_file()]
    gallery = sorted((IMAGES / 'gallery-v2').glob('*.webp'))
    if not gallery:
        missing.append(str(IMAGES / 'gallery-v2' / '*.webp'))
    if missing:
        raise SystemExit('missing image sources: ' + ', '.join(missing))

    manifest = []
    OUT.mkdir(exist_ok=True)
    with tempfile.TemporaryDirectory() as tmp, ZipFile(TARGET, 'w', ZIP_DEFLATED) as bundle:
        for name, path in SOURCES.items():
            image = Image.open(path)
            assert image.mode == 'RGB', f'{name}: expected RGB, got {image.mode}'
            bundle.write(path, name)
            manifest.append({'file': name, 'size': list(image.size),
                             'bytes': path.stat().st_size})

        for path in gallery:
            image = Image.open(path)
            assert image.mode == 'RGB', f'{path.name}: expected RGB'
            bundle.write(path, 'gallery-webp/' + path.name)
            jpg = Path(tmp) / (path.stem + '.jpg')
            image.save(jpg, 'JPEG', quality=92, optimize=True, progressive=True, subsampling=1)
            bundle.write(jpg, 'gallery/' + path.stem + '.jpg')
            manifest.append({'file': 'gallery/' + path.stem + '.jpg',
                             'size': list(image.size), 'bytes': jpg.stat().st_size})

        bundle.writestr('README-FA.txt', README.encode('utf-8'))

    summary = {'file': TARGET.name, 'bytes': TARGET.stat().st_size,
               'sha256': hashlib.sha256(TARGET.read_bytes()).hexdigest(),
               'images': manifest}
    print(json.dumps(summary, indent=2, ensure_ascii=False))


if __name__ == '__main__':
    main()
