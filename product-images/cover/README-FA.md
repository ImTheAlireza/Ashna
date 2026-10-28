# کاور محصول آشنا

کاور اصلی افقی **۲۱۰۰×۱۰۴۰ پیکسل** است و می‌تواند در ابعاد نمایشی ۱۰۵۰×۵۲۰ نیز استفاده شود. فایل نهایی `ashna-cover.jpg` است. نسخهٔ مربع موبایل (`mobile.html` و `ashna-cover-mobile.jpg`، با ابعاد ۱۲۰۰×۱۲۰۰) طرح جداگانه‌ای دارد و در بازطراحی کاور اصلی تغییر نکرده است.

## ساختار کاور اصلی

| بخش | محتوا |
|---|---|
| بالا و سمت راست | نشان «محصول ایرانی»، لوگوی رسمی آشنا و معرفی کوتاه افزونه |
| پایین متن | نام انگلیسی و چهار ویژگی کوتاه |
| سمت چپ | وکتور دست‌ساز سپر، حساب کاربری و کد تأیید |
| پایین راست | نام پارسنا و نشانی محصول |

پس‌زمینهٔ سرمه‌ای و نور آبی، نوشته‌ها را با کنتراست بالا نگه می‌دارند. لوگو از فایل رسمی `../infographic/img/ashna-logo-white.svg` استفاده می‌کند و وکتور قابل ویرایش در `img/login-shield.svg` قرار دارد. فونت‌ها از `../../assets/fonts/` خوانده می‌شوند. ویژگی‌ها را کوتاه نگه دارید تا در اندازهٔ نمایشی هم خوانا بمانند.

## فایل‌ها

| فایل | کاربرد |
|---|---|
| `index.html` | منبع قابل ویرایش کاور افقی |
| `img/login-shield.svg` | وکتور مستقل ورود امن |
| `ashna-cover.jpg` | تصویر نهایی کاور اصلی، ۲۱۰۰×۱۰۴۰ |
| `render.mjs` | تولید PNG در مقیاس دلخواه |
| `check.mjs` | بررسی ابعاد، فونت، تصاویر و سرریز متن |
| `shot.mjs` | میان‌بر برای پیش‌نمایش PNG در مقیاس ۱ |
| `mobile.html`, `ashna-cover-mobile.jpg` | منبع و خروجی طرح جداگانهٔ موبایل |
| `mobile.mjs` | اسکریپت قدیمی رندر موبایل؛ نیازمند سرور محلی و مسیرهای محیط قبلی است |

## بازسازی خروجی کاور اصلی

اسکریپت‌ها بدون وب‌سرور و از هر مسیر اجرا می‌شوند. به Node.js و `playwright-core` و `@sparticuz/chromium` نیاز دارند؛ برای مثال، از ریشهٔ مخزن:

```sh
npm install --prefix .cache/browser --no-package-lock --no-save @sparticuz/chromium@141.0.0 playwright-core@1.57.0

# بررسی طرح؛ فهرست issues باید خالی باشد.
node product-images/cover/check.mjs

# خروجی PNG در اندازهٔ اصلی؛ مقیاس ۲ برای نسخهٔ بزرگ‌تر است.
node product-images/cover/render.mjs 1 /tmp/cover-1x.png
node product-images/cover/render.mjs 2 /tmp/cover-2x.png

# تبدیل به JPEG نهایی (با Pillow)
python3 -c "from PIL import Image; Image.open('/tmp/cover-1x.png').convert('RGB').save('product-images/cover/ashna-cover.jpg', quality=94, optimize=True, progressive=True, subsampling=0)"
```

بستهٔ Chromium به کتابخانه‌های سیستمی لینوکس نیاز دارد. در محیط‌هایی که این کتابخانه‌ها در مسیر پیش‌فرض نیستند، مسیر آن‌ها را با `LD_LIBRARY_PATH` تنظیم کنید؛ برای استفاده از یک Chromium دیگر نیز می‌توان `CHROMIUM_PATH` را مشخص کرد.
