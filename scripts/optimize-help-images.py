#!/usr/bin/env python3
"""./.venv/bin/python scripts/optimize-help-images.py

از تصاویر اصلی راهنما (webp) نسخهٔ سبک JPEG برای PDF می‌سازد.

کروم هنگام چاپ PDF، تصاویر webp را بی‌اتلاف (Flate) در فایل می‌نویسد و حجم PDF
سه برابر می‌شود؛ تصویر JPEG را همان‌طور (DCT) کپی می‌کند. پس هر تصویر با عرض
حداکثر ۱۱۰۰ پیکسل و کیفیت ۷۶ به JPEG تبدیل می‌شود.
"""
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "docs" / "help" / "img"
MAX_WIDTH = 1100
QUALITY = 76

total_src = total_dst = 0
for src in sorted(SRC.glob("*.webp")):
    im = Image.open(src).convert("RGB")
    if im.width > MAX_WIDTH:
        im = im.resize((MAX_WIDTH, round(im.height * MAX_WIDTH / im.width)), Image.LANCZOS)
    dst = src.with_suffix(".jpg")
    im.save(dst, "JPEG", quality=QUALITY, optimize=True, progressive=False, subsampling=2)
    total_src += src.stat().st_size
    total_dst += dst.stat().st_size

print(f"{len(list(SRC.glob('*.jpg')))} jpg · {total_dst / 1024:.0f} KB (webp: {total_src / 1024:.0f} KB)")
