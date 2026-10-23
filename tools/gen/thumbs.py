#!/usr/bin/env python3
"""Generate the 700px gallery thumbnails and record them in gallery-tiles.json.

The gallery grid used to serve the full-size photo in every tile, so opening
the gallery pulled ~15 MB. Each tile now loads a 700px WebP (with a JPEG
fallback) while the lightbox keeps using the original file behind the <a>.

Idempotent: an existing thumbnail is left alone, so re-running is cheap.

Usage:  python3 tools/gen/thumbs.py [--force]
"""
import argparse
import json
import os
import sys

from PIL import Image

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.abspath(os.path.join(HERE, "..", ".."))
PREVIEW = os.path.join(ROOT, "preview")

EDGE = 700
WEBP_Q = 80
JPEG_Q = 82

# build.py reads this one and copies it to the preview; the admin edits the
# preview copy at runtime.
TILES_FILES = [
    os.path.join(HERE, "gallery-tiles.json"),
    os.path.join(PREVIEW, "gallery-tiles.json"),
]


def thumb_paths(src):
    stem, _ = os.path.splitext(src)
    return stem + "-thumb.webp", stem + "-thumb.jpg"


def build_one(src, force=False):
    """Create the two thumbnails for src. Returns (webp, jpg, w, h)."""
    webp, jpg = thumb_paths(src)
    if force or not (os.path.isfile(webp) and os.path.isfile(jpg)):
        im = Image.open(src).convert("RGB")
        im.thumbnail((EDGE, EDGE), Image.LANCZOS)
        im.save(webp, "WEBP", quality=WEBP_Q, method=6)
        im.save(jpg, "JPEG", quality=JPEG_Q, optimize=True, progressive=True)
    # Read the real size back rather than assuming EDGE: thumbnail() preserves
    # the aspect ratio, so portrait photos come out 467x700. build.py needs the
    # true numbers for the width/height attributes.
    with Image.open(webp) as im:
        w, h = im.size
    return webp, jpg, w, h


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--force", action="store_true",
                    help="rebuild thumbnails even if they already exist")
    args = ap.parse_args()

    source = TILES_FILES[0]
    if not os.path.isfile(source):
        sys.exit("missing %s" % source)
    tiles = json.load(open(source, encoding="utf-8"))

    os.chdir(PREVIEW)
    made = missing = 0
    total_webp = 0
    for t in tiles:
        src = t["src"]
        if not os.path.isfile(src):
            print("  ! missing original, skipped: %s" % src)
            missing += 1
            continue
        webp, jpg, w, h = build_one(src, force=args.force)
        t["thumb"] = webp
        t["thumb_jpg"] = jpg
        t["thumb_w"] = w
        t["thumb_h"] = h
        total_webp += os.path.getsize(webp)
        made += 1

    payload = json.dumps(tiles, ensure_ascii=False, indent=2)
    for path in TILES_FILES:
        with open(path, "w", encoding="utf-8") as fh:
            fh.write(payload)

    print("  thumbnails ready: %d   originals missing: %d" % (made, missing))
    print("  grid payload: %.2f MB WebP" % (total_webp / 1024 / 1024))


if __name__ == "__main__":
    main()
