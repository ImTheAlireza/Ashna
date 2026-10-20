#!/usr/bin/env python3
"""برش‌های ۱۰۰۰ پیکسلیِ /tmp/slices را عمودی می‌چسباند و JPEG نهایی را می‌سازد.

    python3 stitch.py            # → ashna-infographic.jpg (کیفیت ۸۸)
    python3 stitch.py 84         # کیفیت دلخواه

چرا برش می‌زنیم؟ ارتفاع اینفوگرافیک از سقف ۱۶۳۸۴ پیکسل کروم برای یک اسکرین‌شات
بیشتر است؛ پس export.mjs صفحه را تکه‌تکه می‌گیرد و اینجا به هم می‌چسبد.
"""
import glob
import os
import sys

from PIL import Image

HERE = os.path.dirname(os.path.abspath(__file__))
SRC = "/tmp/slices"
OUT = os.path.join(HERE, "ashna-infographic.jpg")
QUALITY = int(sys.argv[1]) if len(sys.argv) > 1 else 88

files = sorted(glob.glob(os.path.join(SRC, "s*.png")))
if not files:
    raise SystemExit("برشی در /tmp/slices پیدا نشد؛ اول «node export.mjs» را اجرا کن.")

parts = [Image.open(f).convert("RGB") for f in files]
width = parts[0].width
height = sum(p.height for p in parts)
poster = Image.new("RGB", (width, height), "white")

y = 0
for part in parts:
    poster.paste(part, (0, y))
    y += part.height

# برش‌ها با deviceScaleFactor=2 گرفته می‌شوند؛ قبل از ذخیره به عرض دقیق ۱۰۰۰ برمی‌گردیم
if width != 1000:
    poster = poster.resize((1000, round(height * 1000 / width)), Image.LANCZOS)
    width, height = poster.size

poster.save(OUT, "JPEG", quality=QUALITY, optimize=True, progressive=True, subsampling=1)
size = os.path.getsize(OUT)
print(f"{width} × {height} پیکسل، {size / 1024:.0f} کیلوبایت (کیفیت {QUALITY})")
if size > 1_000_000:
    print("بالای یک مگابایت شد؛ با کیفیت کمتر اجرا کن: python3 stitch.py 82")
