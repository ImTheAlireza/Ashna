#!/usr/bin/env python3
"""Join 2× Chromium slices into the finished JPEG.

    python3 product-images/infographic/stitch.py 90
    python3 product-images/infographic/stitch.py 90 --width 2000 --output /tmp/ashna-2x.jpg

The slices are kept in the system temporary directory, not in the repository.
"""
import argparse
import os
from pathlib import Path
import tempfile

from PIL import Image

HERE = Path(__file__).resolve().parent
DEFAULT_SLICES = Path(os.environ.get("ASHNA_SLICES_DIR", Path(tempfile.gettempdir()) / "ashna-infographic-slices"))

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument("quality", nargs="?", type=int, default=88, help="JPEG quality (1–95)")
parser.add_argument("--width", type=int, default=1000, help="output width in pixels (default: 1000)")
parser.add_argument("--output", type=Path, default=HERE / "ashna-infographic.jpg")
parser.add_argument("--slices", type=Path, default=DEFAULT_SLICES)
args = parser.parse_args()
if not 1 <= args.quality <= 95 or args.width < 1:
    parser.error("quality must be 1–95 and width must be positive")

files = sorted(args.slices.glob("s[0-9][0-9][0-9].png"))
if not files:
    raise SystemExit(f"No slices found in {args.slices}; run export.mjs first.")

parts = []
for filename in files:
    with Image.open(filename) as part:
        parts.append(part.convert("RGB"))
width = parts[0].width
if any(part.width != width for part in parts):
    raise SystemExit("Slice widths differ; run export.mjs again.")
height = sum(part.height for part in parts)
poster = Image.new("RGB", (width, height), "white")
y = 0
for part in parts:
    poster.paste(part, (0, y))
    y += part.height
    part.close()

if width != args.width:
    poster = poster.resize((args.width, round(height * args.width / width)), Image.Resampling.LANCZOS)
    width, height = poster.size

args.output.parent.mkdir(parents=True, exist_ok=True)
poster.save(args.output, "JPEG", quality=args.quality, optimize=True, progressive=True, subsampling=1)
size = args.output.stat().st_size
print(f"{args.output}: {width} × {height} pixels, {size / 1024:.0f} KiB (quality {args.quality})")
if args.width == 1000 and size > 1_000_000:
    print("Over 1 MB; try a lower quality, e.g. stitch.py 84.")
