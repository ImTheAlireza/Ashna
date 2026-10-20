#!/usr/bin/env python3
"""Render docs/demo-report-FA.md into a self-contained RTL HTML report.

Usage:
    pip install markdown
    python scripts/render-demo-report.py
"""

from __future__ import annotations

import base64
import pathlib
import re

import markdown

ROOT = pathlib.Path(__file__).resolve().parents[1]
MD_PATH = ROOT / "docs" / "demo-report-FA.md"
OUT_PATH = ROOT / "docs" / "demo-report-FA.html"

VAZIR = ROOT / "assets" / "fonts" / "vazirmatn" / "Vazirmatn-Variable.woff2"
ESTEDAD = ROOT / "assets" / "fonts" / "estedad" / "estedad-900.woff2"
ICON = ROOT / "assets" / "images" / "ashna-icon.svg"


def data_uri(path: pathlib.Path) -> str:
    payload = base64.b64encode(path.read_bytes()).decode("ascii")
    return f"data:font/woff2;base64,{payload}"


def inline_svg(path: pathlib.Path, css_class: str) -> str:
    svg = path.read_text(encoding="utf-8")
    svg = re.sub(r"<svg\b", f'<svg class="{css_class}"', svg, count=1)
    return svg


source = MD_PATH.read_text(encoding="utf-8")
md = markdown.Markdown(
    extensions=["tables", "fenced_code", "toc", "sane_lists", "attr_list"],
    extension_configs={"toc": {"permalink": False, "toc_depth": "2-2"}},
)
body = md.convert(source)
toc_html = md.toc
body = re.sub(r"(<table>.*?</table>)", r'<div class="table-wrap">\1</div>', body, flags=re.S)
body = body.replace('<pre><code class="language-text">', '<pre class="wireframe"><code>')

# Markdown turns bare quotes and dashes fine; keep external links safe.
body = body.replace('<a href="http', '<a target="_blank" rel="noopener" href="http')

template = """<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>دموی آشنا — گزارش پژوهش و طرح اجرا</title>
<meta name="description" content="گزارش بررسی دموهای موفق راست‌چین و طرح اجرای دموی محصول آشنا">
<style>
  @font-face {{
    font-family: "VazirmatnEmbed";
    src: url("{vazir}") format("woff2");
    font-weight: 100 900;
    font-display: swap;
  }}
  @font-face {{
    font-family: "EstedadEmbed";
    src: url("{estedad}") format("woff2");
    font-weight: 900;
    font-display: swap;
  }}
  :root {{
    --blue: #2563eb;
    --navy: #0b1120;
    --surface: #ffffff;
    --muted: #64748b;
    --line: #e2e8f0;
    --soft: #f8fafc;
    --softblue: #eff6ff;
    --text: #0f172a;
    --radius: 18px;
  }}
  * {{ box-sizing: border-box; }}
  html {{ scroll-behavior: smooth; }}
  body {{
    margin: 0;
    background: #f1f5f9;
    color: var(--text);
    font-family: "VazirmatnEmbed", Vazirmatn, Tahoma, "Segoe UI", sans-serif;
    font-size: 16px;
    line-height: 2;
    -webkit-font-smoothing: antialiased;
  }}
  a {{ color: var(--blue); text-decoration: none; border-bottom: 1px solid rgba(37,99,235,.25); }}
  a:hover {{ border-bottom-color: var(--blue); }}
  .page {{
    max-width: 1040px;
    margin: 0 auto;
    padding: 28px 20px 80px;
  }}
  .cover {{
    background:
      radial-gradient(circle at 85% 15%, rgba(59,130,246,.28), transparent 42%),
      radial-gradient(circle at 10% 90%, rgba(37,99,235,.18), transparent 40%),
      var(--navy);
    color: #f8fafc;
    border-radius: 28px;
    padding: 42px 44px 36px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 24px 60px -24px rgba(15, 23, 42, .55);
  }}
  .cover::after {{
    content: "";
    position: absolute;
    inset: 0;
    background-image: linear-gradient(rgba(255,255,255,.035) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,.035) 1px, transparent 1px);
    background-size: 34px 34px;
    pointer-events: none;
    mask-image: linear-gradient(to bottom, #000, transparent 85%);
  }}
  .cover-top {{ display: flex; align-items: center; gap: 14px; position: relative; z-index: 1; }}
  .cover-top .brand-icon {{
    width: 50px;
    height: 50px;
    flex: 0 0 auto;
    background: #fff;
    border-radius: 14px;
    padding: 7px;
    box-shadow: 0 8px 24px -8px rgba(37, 99, 235, .55);
  }}
  .brand-name {{ font-weight: 900; font-size: 22px; letter-spacing: -.2px; }}
  .brand-sub {{ color: #93c5fd; font-size: 12.5px; margin-top: 2px; }}
  .cover h1 {{
    font-family: "EstedadEmbed", "VazirmatnEmbed", Tahoma, sans-serif;
    font-weight: 900;
    font-size: clamp(30px, 5vw, 46px);
    line-height: 1.55;
    margin: 36px 0 12px;
    position: relative;
    z-index: 1;
  }}
  .cover p.lead {{
    margin: 0;
    color: #cbd5e1;
    font-size: 16.5px;
    max-width: 760px;
    position: relative;
    z-index: 1;
  }}
  .meta {{
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 24px;
    position: relative;
    z-index: 1;
  }}
  .meta span {{
    background: rgba(148, 163, 184, .12);
    border: 1px solid rgba(148, 163, 184, .25);
    color: #e2e8f0;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 12.5px;
  }}
  .notice {{
    background: #fffbeb;
    border: 1px solid #fde68a;
    color: #78350f;
    border-radius: 14px;
    padding: 12px 18px;
    margin: 22px 0 0;
    font-size: 13.5px;
  }}
  .toc {{
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: var(--radius);
    padding: 22px 26px;
    margin: 26px 0 34px;
    box-shadow: 0 4px 24px -12px rgba(15,23,42,.12);
  }}
  .toc h2 {{ margin: 0 0 10px; font-size: 15px; color: var(--muted); font-weight: 700; }}
  .toc ul {{ list-style: none; padding: 0; margin: 0; columns: 2; column-gap: 36px; }}
  .toc li {{ margin: 2px 0; break-inside: avoid; }}
  .toc a {{ border: 0; font-size: 14px; color: #1e3a8a; }}
  .toc a:hover {{ color: var(--blue); }}
  main {{
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 26px;
    padding: 12px clamp(20px, 4vw, 52px) 44px;
    box-shadow: 0 8px 40px -24px rgba(15,23,42,.18);
  }}
  main > h1 {{ display: none; }}
  h2 {{
    font-family: "EstedadEmbed", "VazirmatnEmbed", Tahoma, sans-serif;
    font-size: clamp(21px, 2.6vw, 27px);
    font-weight: 900;
    line-height: 1.7;
    margin: 52px 0 16px;
    padding-top: 18px;
    border-top: 1px solid var(--line);
    color: #0b1120;
  }}
  h2:first-of-type {{ border-top: 0; margin-top: 20px; padding-top: 0; }}
  h3 {{ font-size: 18.5px; font-weight: 800; margin: 30px 0 10px; color: #1e293b; }}
  p {{ margin: 12px 0; }}
  strong {{ font-weight: 800; color: #0b1120; }}
  blockquote {{
    margin: 18px 0;
    padding: 16px 22px;
    background: var(--softblue);
    border-right: 4px solid var(--blue);
    border-radius: 12px;
    color: #1e3a8a;
  }}
  blockquote p {{ margin: 4px 0; }}
  blockquote strong {{ color: #1e40af; }}
  hr {{ border: 0; border-top: 1px solid var(--line); margin: 34px 0; }}
  ul, ol {{ padding-right: 22px; margin: 12px 0; }}
  @counter-style fa-num {{
    system: numeric;
    symbols: "۰" "۱" "۲" "۳" "۴" "۵" "۶" "۷" "۸" "۹";
  }}
  ol {{ list-style-type: fa-num; }}
  li {{ margin: 5px 0; }}
  .table-wrap {{ overflow-x: auto; margin: 16px 0; border-radius: 14px; border: 1px solid var(--line); }}
  table {{ border-collapse: collapse; width: 100%; font-size: 14px; line-height: 1.9; min-width: 560px; }}
  th, td {{ padding: 11px 14px; text-align: right; vertical-align: top; border-bottom: 1px solid var(--line); }}
  th {{ background: #f1f5f9; color: #334155; font-weight: 800; white-space: nowrap; }}
  tbody tr:nth-child(even) {{
    background: #fbfdff;
  }}
  tbody tr:hover {{ background: var(--softblue); }}
  tbody tr:last-child td {{ border-bottom: 0; }}
  code {{
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    direction: ltr;
    unicode-bidi: embed;
    background: #f1f5f9;
    padding: 2px 7px;
    border-radius: 6px;
    font-size: 12.5px;
    color: #0f172a;
  }}
  pre {{
    direction: ltr;
    text-align: left;
    background: #0b1120;
    color: #cbd5e1;
    padding: 20px 22px;
    border-radius: 14px;
    overflow-x: auto;
    line-height: 1.75;
    font-size: 12.5px;
    margin: 18px 0;
  }}
  pre code {{ background: none; color: inherit; padding: 0; font-size: inherit; }}
  pre.wireframe {{ direction: rtl; text-align: right; }}
  pre.wireframe code {{ direction: rtl; unicode-bidi: plaintext; }}
  input[type="checkbox"] {{ width: 15px; height: 15px; accent-color: var(--blue); }}
  main li input[type="checkbox"] {{ margin-left: 6px; }}
  .footer {{
    text-align: center;
    color: var(--muted);
    font-size: 12.5px;
    margin-top: 26px;
    line-height: 2;
  }}
  .footer .dot {{ color: #cbd5e1; margin: 0 8px; }}
  @media (max-width: 760px) {{
    .page {{ padding: 14px 10px 50px; }}
    .cover {{ padding: 28px 22px 26px; border-radius: 20px; }}
    .cover h1 {{ margin-top: 26px; }}
    main {{ padding: 6px 16px 30px; border-radius: 20px; }}
    .toc ul {{ columns: 1; }}
    body {{ font-size: 15px; }}
    table {{ font-size: 13px; }}
  }}
  @media print {{
    body {{ background: #fff; }}
    .page {{ max-width: none; padding: 0; }}
    .cover {{ box-shadow: none; }}
    main {{ border: 0; box-shadow: none; }}
    .toc {{ break-after: page; }}
    h2 {{ break-after: avoid; }}
    table, pre, blockquote {{ break-inside: avoid; }}
  }}
</style>
</head>
<body>
<div class="page">
  <header class="cover">
    <div class="cover-top">
      {icon}
      <div>
        <div class="brand-name">آشنا</div>
        <div class="brand-sub">ورود و ثبت‌نام یکپارچهٔ وردپرس · محصولی از پارسنا</div>
      </div>
    </div>
    <h1>دموی محصول؛ از «صفحهٔ نمایش»<br>به «اثبات ۹۰ ثانیه‌ای»</h1>
    <p class="lead">گزارش بررسی دموهای موفق راست‌چین، ممیزی وضعیت فعلی آشنا، و طرح کامل لندینگ و محیط دموی اختصاصی — بدون قالب آماده.</p>
    <div class="meta">
      <span>۲۸ مهر ۱۴۰۵</span>
      <span>۶ نمونهٔ بررسی‌شده</span>
      <span>۷ روز کاری تا اجرا</span>
      <span>نسخهٔ محصول ۳.۱.۳</span>
    </div>
  </header>

  <p class="notice">این سند یک گزارش پژوهشی و پیشنهاد اجرایی است. قیمت‌ها و آمار فروش، مربوط به تاریخ بررسی صفحات راست‌چین است و ممکن است تغییر کرده باشد. متن‌های نمونه، پیش‌نویس‌اند و باید با لحن نهایی تیم پارسنا بازنویسی شوند.</p>

  <nav class="toc">
    <h2>در این گزارش</h2>
    {toc}
  </nav>

  <main>
    {body}
  </main>

  <div class="footer">
    آشنا — OTP Login for WordPress <span class="dot">•</span> گزارش دمو <span class="dot">•</span> پارسنا
    <br>
    ساخته‌شده برای تیم پارسنا؛ منابع و لینک‌ها در انتهای سند آمده است.
  </div>
</div>
</body>
</html>
"""

toc_items = re.sub(r'^<div class="toc">\s*|<\/div>\s*$', "", toc_html, flags=re.S)

html = template.format(
    vazir=data_uri(VAZIR),
    estedad=data_uri(ESTEDAD),
    icon=inline_svg(ICON, "brand-icon"),
    toc=toc_items,
    body=body,
)

OUT_PATH.write_text(html, encoding="utf-8")
print(f"Wrote {OUT_PATH} ({OUT_PATH.stat().st_size:,} bytes)")
