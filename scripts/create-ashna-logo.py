"""Build the FINAL blue shield/door logo and outlined Ashna wordmark.
Run: .venv/bin/pip install resvg-py Pillow fonttools brotli uharfbuzz
     .venv/bin/python scripts/create-ashna-logo.py
The approved source icon is never overwritten by this script.
"""
from pathlib import Path
from io import BytesIO
from zipfile import ZipFile, ZIP_DEFLATED
import shutil
import xml.etree.ElementTree as ET
import resvg_py
import uharfbuzz as hb
from fontTools.ttLib import TTFont
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from PIL import Image
ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'assets/images/ashna'
OUT.mkdir(parents=True, exist_ok=True)
BLUE = '#2563EB'
NAVY = '#172443'
approved = ET.parse(OUT / 'ashna-secure-login.svg').getroot()
paths = [p.attrib['d'] for p in approved.iter('{http://www.w3.org/2000/svg}path')]
assert len(paths) == 2

def mark(color=BLUE):
    return '<svg width="512" height="512" viewBox="280 280 464 464">' + ''.join(f'<path fill="{color}" d="{d}"/>' for d in paths) + '</svg>'

font = TTFont(ROOT / 'assets/fonts/estedad/estedad-900.woff2')
font.flavor = None
stream = BytesIO()
font.save(stream)
face = hb.Face(stream.getvalue())
hfont = hb.Font(face)
hfont.scale = (face.upem, face.upem)
hb.ot_font_set_funcs(hfont)
buf = hb.Buffer()
buf.add_str('آشنا')
buf.direction = 'rtl'
buf.script = 'arab'
buf.language = 'fa'
hb.shape(hfont, buf)
glyphs = font.getGlyphSet()
order = font.getGlyphOrder()
width = sum(p.x_advance for p in buf.glyph_positions)

def wordmark(x, baseline, desired_width, color=NAVY):
    scale = desired_width / width
    paths = []
    cursor = 0
    for info, pos in zip(buf.glyph_infos, buf.glyph_positions):
        pen = SVGPathPen(glyphs)
        transform = TransformPen(pen, (scale, 0, 0, -scale,
                                     x + (cursor + pos.x_offset) * scale,
                                     baseline - pos.y_offset * scale))
        glyphs[order[info.codepoint]].draw(transform)
        paths.append(f'<path d="{pen.getCommands()}"/>')
        cursor += pos.x_advance
    return f'<g fill="{color}">' + ''.join(paths) + '</g>'


def svg(w, h, content, label):
    return f'<svg xmlns="http://www.w3.org/2000/svg" width="{w}" height="{h}" viewBox="0 0 {w} {h}" role="img" aria-label="{label}">{content}</svg>'

assets = {
    'ashna-icon.svg': svg(512, 512, mark(), 'آشنا — سپر و در نیمه‌باز'),
    'ashna-icon-mono.svg': svg(512, 512, mark(NAVY), 'نشان تک‌رنگ آشنا'),
    'ashna-logo.svg': svg(820, 240, wordmark(42, 217, 420) + '<g transform="translate(540 0) scale(.46875)">' + mark() + '</g>', 'آشنا — ورود آسان، هویت مطمئن'),
    'ashna-logo-white.svg': svg(820, 240, wordmark(42, 217, 420, '#FFFFFF') + '<g transform="translate(540 0) scale(.46875)">' + mark('#FFFFFF') + '</g>', 'آشنا — نسخه سفید'),
    'ashna-logo-preview.svg': svg(1600, 1000, '<rect width="1600" height="1000" fill="#FFFFFF"/>' + '<g transform="translate(544 95)">' + mark() + '</g>' + wordmark(615, 810, 370), 'هویت نهایی آشنا'),
}
for name, content in assets.items():
    path = OUT / name
    path.write_text(content, encoding='utf-8')
    path.with_suffix('.png').write_bytes(resvg_py.svg_to_bytes(svg_string=content))
(OUT / 'ashna-secure-login.png').write_bytes(resvg_py.svg_to_bytes(svg_path=str(OUT / 'ashna-secure-login.svg'), width=1024, height=1024))
for name in ['ashna-icon.svg','ashna-icon.png','ashna-logo.svg','ashna-logo.png','ashna-logo-white.svg','ashna-logo-white.png']:
    shutil.copyfile(OUT / name, ROOT / 'assets/images' / name)
menu = svg(512,512,mark('#FFFFFF'),'آشنا')
(ROOT / 'assets/images/ashna-menu-icon.png').write_bytes(resvg_py.svg_to_bytes(svg_string=menu, width=40, height=40))
for folder in ['parsna-landing', 'parsna-landing/assets']:
    shutil.copyfile(OUT / 'ashna-icon.png', ROOT / folder / 'ashna-logo.png')
for name in ['ashna-logo.png', 'ashna-logo-transparent.png']:
    shutil.copyfile(OUT / 'ashna-logo.png', ROOT / 'product-images' / name)
(OUT / 'README-FA.md').write_text("""# هویت نهایی آشنا / Ashna

نماد تأییدشده: سپر آبی با در نیمه‌باز؛ بدون فلش.
رنگ اصلی: `#2563EB`؛ رنگ نوشته: `#172443`.

- `ashna-secure-login.svg`: نماد اصلی تأییدشده؛ فقط مسیرهای وکتور واقعی.
- `ashna-secure-login.png`: خروجی شفاف ۱۰۲۴ × ۱۰۲۴.
- `ashna-icon.svg/png`: نماد مستقل.
- `ashna-logo.svg/png`: لوگوی افقی با نام فارسی به صورت وکتور.
- `ashna-logo-white.svg/png`: نسخه سفید برای زمینه تیره.
- `ashna-icon-mono.svg/png`: نسخه تک‌رنگ.
- `ashna-logo-preview.svg/png`: نمایش هویت روی زمینه سفید؛ تنها نسخه با بک‌گراند.

سایر فایل‌ها، بیرون و داخل نماد، شفاف هستند.
نام فارسی با Estedad 900 تحت مجوز SIL OFL به مسیر وکتور تبدیل شده است.
طرح‌های آزمایشی و لوگوی حرف «آ» داخل این بسته نیستند.
""", encoding='utf-8')
with ZipFile(ROOT / 'deliverables/ashna-logo-kit.zip', 'w', ZIP_DEFLATED) as bundle:
    for name in [*assets, *(Path(n).with_suffix('.png').name for n in assets), 'ashna-secure-login.svg','ashna-secure-login.png','README-FA.md']:
        bundle.write(OUT / name, 'ashna-logo-kit/' + name)
    bundle.write(ROOT / 'assets/fonts/estedad/LICENSE.txt', 'ashna-logo-kit/Estedad-OFL.txt')
for name in assets:
    if 'preview' in name: continue
    with Image.open((OUT / name).with_suffix('.png')) as im:
        assert im.mode=='RGBA' and im.getpixel((0,0))[3]==0
print('PASS: final shield/door logo, outlined wordmark, transparent assets and logo kit rebuilt.')
