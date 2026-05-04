#!/usr/bin/env python3
"""
Regenerate all Fun Dacha brand assets with a tomato icon
instead of the old basket icon.
"""

import math
import os
import subprocess
import tempfile
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

BASE = Path(__file__).resolve().parent
LOGO_DIR = BASE / "logo"
ART_DIR = BASE / "art"
SPICKER_DIR = BASE / "spicker"
QR_DIR = BASE / "qr"
BANNER_DIR = BASE / "banner"

# Brand colours
GREEN = "#207D43"
GREEN_DARK = "#1a6a38"
GREEN_DARKER = "#155530"
BLACK = "#2d2d2d"
WHITE = "#ffffff"
CREAM = "#FFF8F0"
GREY = "#888888"
BROWN = "#8b5e3c"

# Tomato colours
TOMATO_RED = "#e53935"
TOMATO_DARK = "#c62828"
TOMATO_HIGHLIGHT = "#ef5350"
LEAF_GREEN = "#388e3c"
LEAF_LIGHT = "#4caf50"
STEM_GREEN = "#2e7d32"


def find_font(bold=True, italic=False):
    """Find the best available bold sans-serif font."""
    candidates = [
        "/System/Library/Fonts/Helvetica.ttc",
        "/System/Library/Fonts/HelveticaNeue.ttc",
        "/System/Library/Fonts/Supplemental/Arial Bold.ttf",
    ]
    for c in candidates:
        if os.path.exists(c):
            return c
    return None


FONT_PATH = find_font()


def font(size, bold=True):
    """Get a font at the given size."""
    if FONT_PATH:
        # index=1 is Bold in Helvetica.ttc
        try:
            return ImageFont.truetype(FONT_PATH, size, index=1 if bold else 0)
        except Exception:
            return ImageFont.truetype(FONT_PATH, size)
    return ImageFont.load_default()


def font_italic(size):
    """Get an italic font."""
    if FONT_PATH:
        try:
            return ImageFont.truetype(FONT_PATH, size, index=2)
        except Exception:
            return font(size, bold=False)
    return ImageFont.load_default()


def draw_tomato(draw, cx, cy, radius, shadow=True):
    """Draw a tomato icon centred at (cx, cy) with given radius."""
    r = radius

    if shadow:
        draw.ellipse(
            [cx - r * 0.85, cy + r * 0.7, cx + r * 0.85, cy + r * 1.1],
            fill="#00000020",
        )

    # Main tomato body
    body_top = cy - r * 0.5
    body_bottom = cy + r * 0.75
    body_left = cx - r
    body_right = cx + r
    draw.ellipse([body_left, body_top, body_right, body_bottom], fill=TOMATO_RED)

    # Darker bottom half for depth
    draw.ellipse(
        [cx - r * 0.85, cy + r * 0.05, cx + r * 0.85, body_bottom + r * 0.02],
        fill=TOMATO_DARK,
    )
    # Main body again (overlapping to create gradient effect)
    draw.ellipse(
        [body_left + r * 0.05, body_top, body_right - r * 0.05, body_bottom - r * 0.1],
        fill=TOMATO_RED,
    )

    # Highlight
    draw.ellipse(
        [cx - r * 0.55, body_top + r * 0.05, cx + r * 0.1, cy + r * 0.15],
        fill=TOMATO_HIGHLIGHT,
    )

    # Stem
    stem_w = r * 0.12
    draw.rectangle(
        [cx - stem_w, body_top - r * 0.35, cx + stem_w, body_top + r * 0.15],
        fill=STEM_GREEN,
    )

    # Leaves (calyx) — a star of small leaves around the stem
    leaf_r = r * 0.35
    leaf_cy = body_top + r * 0.05
    for angle_deg in [-50, -15, 15, 50]:
        angle = math.radians(angle_deg)
        lx = cx + math.sin(angle) * leaf_r
        ly = leaf_cy - abs(math.cos(angle)) * leaf_r * 0.6
        points = [
            (cx, leaf_cy),
            (lx - r * 0.08, ly - r * 0.05),
            (lx, ly - r * 0.12),
            (lx + r * 0.08, ly - r * 0.05),
        ]
        draw.polygon(points, fill=LEAF_GREEN)

    # Centre leaf dot
    draw.ellipse(
        [cx - r * 0.1, leaf_cy - r * 0.1, cx + r * 0.1, leaf_cy + r * 0.1],
        fill=LEAF_LIGHT,
    )


def draw_tomato_small(draw, cx, cy, radius):
    """Draw a simpler tomato for small sizes."""
    r = radius

    # Body
    draw.ellipse(
        [cx - r, cy - r * 0.5, cx + r, cy + r * 0.75],
        fill=TOMATO_RED,
    )

    # Stem
    stem_w = max(1, r * 0.12)
    draw.rectangle(
        [cx - stem_w, cy - r * 0.85, cx + stem_w, cy - r * 0.35],
        fill=STEM_GREEN,
    )

    # Leaf
    draw.polygon(
        [
            (cx, cy - r * 0.5),
            (cx + r * 0.4, cy - r * 0.9),
            (cx + r * 0.15, cy - r * 0.45),
        ],
        fill=LEAF_GREEN,
    )
    draw.polygon(
        [
            (cx, cy - r * 0.5),
            (cx - r * 0.35, cy - r * 0.85),
            (cx - r * 0.1, cy - r * 0.45),
        ],
        fill=LEAF_GREEN,
    )


# ═══════════════════════════════════════════
# Logo generators
# ═══════════════════════════════════════════

def make_logo_horizontal(bg_color, fun_color, dacha_color, tagline_color, filename, width=1500, height=400):
    """Generate horizontal logo: FUN [tomato] DACHA + tagline."""
    img = Image.new("RGBA", (width, height), bg_color)
    draw = ImageDraw.Draw(img)

    title_size = int(height * 0.32)
    tag_size = int(height * 0.11)
    f_title = font(title_size)
    f_tag = font_italic(tag_size)

    fun_text = "FUN"
    dacha_text = "DACHA"

    fun_bb = draw.textbbox((0, 0), fun_text, font=f_title)
    dacha_bb = draw.textbbox((0, 0), dacha_text, font=f_title)
    fun_w = fun_bb[2] - fun_bb[0]
    dacha_w = dacha_bb[2] - dacha_bb[0]
    text_h = fun_bb[3] - fun_bb[1]

    icon_r = int(height * 0.1)
    icon_space = int(height * 0.08)
    total_w = fun_w + icon_space + icon_r * 2 + icon_space + dacha_w

    start_x = (width - total_w) // 2
    text_y = int(height * 0.2)

    # FUN
    draw.text((start_x, text_y), fun_text, fill=fun_color, font=f_title)

    # Tomato
    icon_cx = start_x + fun_w + icon_space + icon_r
    icon_cy = text_y + text_h // 2 + int(height * 0.02)
    draw_tomato(draw, icon_cx, icon_cy, icon_r, shadow=False)

    # DACHA
    dacha_x = icon_cx + icon_r + icon_space
    draw.text((dacha_x, text_y), dacha_text, fill=dacha_color, font=f_title)

    # Tagline
    tagline = "Насіння. Добрива. Захист."
    tag_bb = draw.textbbox((0, 0), tagline, font=f_tag)
    tag_w = tag_bb[2] - tag_bb[0]
    tag_x = (width - tag_w) // 2
    tag_y = text_y + text_h + int(height * 0.1)
    draw.text((tag_x, tag_y), tagline, fill=tagline_color, font=f_tag)

    img.save(str(filename))
    print(f"  {filename.name}")


def make_logo_stacked(bg_color, fun_color, dacha_color, tagline_color, filename, size=900):
    """Generate stacked logo: tomato on top, FUN, DACHA, tagline."""
    img = Image.new("RGBA", (size, size), bg_color)
    draw = ImageDraw.Draw(img)

    title_size = int(size * 0.14)
    tag_size = int(size * 0.055)
    f_title = font(title_size)
    f_tag = font_italic(tag_size)

    # Tomato at top
    icon_r = int(size * 0.08)
    icon_cy = int(size * 0.25)
    draw_tomato(draw, size // 2, icon_cy, icon_r, shadow=False)

    # FUN
    fun_bb = draw.textbbox((0, 0), "FUN", font=f_title)
    fun_w = fun_bb[2] - fun_bb[0]
    fun_y = int(size * 0.35)
    draw.text(((size - fun_w) // 2, fun_y), "FUN", fill=fun_color, font=f_title)

    # DACHA
    dacha_bb = draw.textbbox((0, 0), "DACHA", font=f_title)
    dacha_w = dacha_bb[2] - dacha_bb[0]
    dacha_y = fun_y + int(size * 0.14)
    draw.text(((size - dacha_w) // 2, dacha_y), "DACHA", fill=dacha_color, font=f_title)

    # Tagline
    tagline = "Насіння. Добрива. Захист."
    tag_bb = draw.textbbox((0, 0), tagline, font=f_tag)
    tag_w = tag_bb[2] - tag_bb[0]
    tag_y = dacha_y + int(size * 0.22)
    draw.text(((size - tag_w) // 2, tag_y), tagline, fill=tagline_color, font=f_tag)

    img.save(str(filename))
    print(f"  {filename.name}")


def make_monogram(filename, size=750):
    """Generate monogram: green circles with tomato sprout."""
    img = Image.new("RGBA", (size, int(size * 0.45)), "#00000000")
    draw = ImageDraw.Draw(img)

    h = int(size * 0.45)
    sizes_data = [
        (int(size * 0.19), int(h * 0.5), int(size * 0.17)),
        (int(size * 0.52), int(h * 0.5), int(size * 0.11)),
        (int(size * 0.74), int(h * 0.5), int(size * 0.075)),
    ]

    for cx, cy, r in sizes_data:
        # Green circle
        draw.ellipse([cx - r, cy - r, cx + r, cy + r], fill=GREEN)
        # Inner ring
        inner_r = int(r * 0.88)
        draw.ellipse([cx - inner_r, cy - inner_r, cx + inner_r, cy + inner_r], fill=GREEN_DARK)

        # Tomato-styled sprout — stem + two leaves
        stem_h = int(r * 0.35)
        stem_w = max(2, int(r * 0.06))
        stem_top = cy - stem_h
        draw.rectangle([cx - stem_w, stem_top, cx + stem_w, cy], fill=WHITE)

        # Two leaves
        leaf_len = int(r * 0.25)
        lw = max(2, int(r * 0.04))
        # Left leaf
        for i in range(leaf_len):
            t = i / leaf_len
            lx = cx - int(t * leaf_len * 0.8)
            ly = stem_top + int(r * 0.15) - int(t * leaf_len * 0.5)
            draw.ellipse([lx - lw, ly - lw, lx + lw, ly + lw], fill=WHITE)
        # Right leaf
        for i in range(leaf_len):
            t = i / leaf_len
            rx = cx + int(t * leaf_len * 0.8)
            ry = stem_top + int(r * 0.05) - int(t * leaf_len * 0.6)
            draw.ellipse([rx - lw, ry - lw, rx + lw, ry + lw], fill=WHITE)

        # "DACHA" text only on the largest circle
        if r == sizes_data[0][2]:
            f_text = font(int(r * 0.28))
            bb = draw.textbbox((0, 0), "DACHA", font=f_text)
            tw = bb[2] - bb[0]
            draw.text(
                ((cx - tw // 2), cy + int(r * 0.15)),
                "DACHA",
                fill=WHITE,
                font=f_text,
            )

    img.save(str(filename))
    print(f"  {filename.name}")


# ═══════════════════════════════════════════
# Art / packaging generators
# ═══════════════════════════════════════════

def draw_seedling_pattern(draw, width, height, color, alpha=30):
    """Draw subtle seedling pattern on a background."""
    spacing = 80
    for y in range(0, height, spacing):
        offset = (spacing // 2) if (y // spacing) % 2 else 0
        for x in range(offset, width, spacing):
            sw = 2
            sh = 18
            # Stem
            draw.line([(x, y + sh), (x, y)], fill=color, width=sw)
            # Left leaf
            draw.line([(x, y + 6), (x - 6, y)], fill=color, width=sw)
            # Right leaf
            draw.line([(x, y + 3), (x + 6, y - 4)], fill=color, width=sw)


def make_pack_box_art(filename):
    """Green box art card with tomato in circle."""
    w, h = 940, 640
    img = Image.new("RGBA", (w, h), "#00000000")
    draw = ImageDraw.Draw(img)

    # Rounded rect background
    r = 30
    draw.rounded_rectangle([0, 0, w - 1, h - 1], radius=r, fill=GREEN)

    # Seedling pattern
    draw_seedling_pattern(draw, w, h, GREEN_DARK)

    # Circle with tomato
    circle_r = 100
    cx, cy_circle = w // 2, int(h * 0.35)
    draw.ellipse(
        [cx - circle_r, cy_circle - circle_r, cx + circle_r, cy_circle + circle_r],
        outline="#ffffff40",
        width=2,
    )
    draw_tomato(draw, cx, cy_circle, 40, shadow=False)

    # FUN DACHA text
    f_fun = font(48)
    f_dacha = font(48)
    fun_bb = draw.textbbox((0, 0), "FUN", font=f_fun)
    dacha_bb = draw.textbbox((0, 0), "DACHA", font=f_dacha)
    fun_w = fun_bb[2] - fun_bb[0]
    dacha_w = dacha_bb[2] - dacha_bb[0]
    gap = 20
    total = fun_w + gap + dacha_w
    text_y = int(h * 0.62)
    draw.text(((w - total) // 2, text_y), "FUN", fill=GREEN_DARK, font=f_fun)
    draw.text(((w - total) // 2 + fun_w + gap, text_y), "DACHA", fill=WHITE, font=f_dacha)

    # Tagline
    f_tag = font(18, bold=False)
    tagline = "НАСІННЯ  ·  ДОБРИВА  ·  ЗАХИСТ"
    tag_bb = draw.textbbox((0, 0), tagline, font=f_tag)
    tag_w = tag_bb[2] - tag_bb[0]
    draw.text(((w - tag_w) // 2, int(h * 0.78)), tagline, fill="#ffffffbb", font=f_tag)

    img.save(str(filename))
    print(f"  {filename.name}")


def make_pack_brand_tape(filename):
    """Brand tape: repeating FUN [tomato] DACHA pattern."""
    w, h = 1100, 120
    img = Image.new("RGBA", (w, h), GREEN)
    draw = ImageDraw.Draw(img)

    f_text = font(36)
    segment_w = 380
    tomato_r = 14

    for sx in range(-100, w + 100, segment_w):
        # FUN
        draw.text((sx, (h - 36) // 2 - 2), "FUN", fill=WHITE, font=f_text)
        fun_bb = draw.textbbox((0, 0), "FUN", font=f_text)
        fun_w = fun_bb[2] - fun_bb[0]

        # Tomato
        tx = sx + fun_w + 18
        draw_tomato_small(draw, tx + tomato_r, h // 2 + 2, tomato_r)

        # DACHA
        dx = tx + tomato_r * 2 + 18
        draw.text((dx, (h - 36) // 2 - 2), "DACHA", fill=WHITE, font=f_text)

        # Dot separator
        dot_x = dx + 180
        draw.ellipse([dot_x, h // 2 - 3, dot_x + 6, h // 2 + 3], fill="#ffffffbb")

    img.save(str(filename))
    print(f"  {filename.name}")


def make_pack_hang_tag(filename):
    """Hang tag with tomato, FUN DACHA, tagline."""
    w, h = 380, 560
    img = Image.new("RGBA", (w, h), "#00000000")
    draw = ImageDraw.Draw(img)

    # Tag shape with border
    r = 15
    draw.rounded_rectangle([10, 10, w - 10, h - 10], radius=r, fill=WHITE, outline="#dddddd", width=1)

    # Hole at top
    hole_r = 12
    draw.ellipse(
        [w // 2 - hole_r, 30, w // 2 + hole_r, 30 + hole_r * 2],
        fill="#eeeeee",
        outline="#cccccc",
        width=1,
    )

    # Tomato
    draw_tomato(draw, w // 2, int(h * 0.28), 35, shadow=False)

    # FUN
    f_title = font(38)
    fun_bb = draw.textbbox((0, 0), "FUN", font=f_title)
    fun_w = fun_bb[2] - fun_bb[0]
    draw.text(((w - fun_w) // 2, int(h * 0.42)), "FUN", fill=GREEN, font=f_title)

    # DACHA
    dacha_bb = draw.textbbox((0, 0), "DACHA", font=f_title)
    dacha_w = dacha_bb[2] - dacha_bb[0]
    draw.text(((w - dacha_w) // 2, int(h * 0.51)), "DACHA", fill=BLACK, font=f_title)

    # Divider
    div_y = int(h * 0.64)
    draw.line([(w // 2 - 30, div_y), (w // 2 + 30, div_y)], fill=GREEN, width=2)

    # Tagline lines
    f_tag = font(16, bold=False)
    for i, line in enumerate(["Насіння", "Добрива", "Захист"]):
        bb = draw.textbbox((0, 0), line, font=f_tag)
        lw = bb[2] - bb[0]
        draw.text(((w - lw) // 2, div_y + 20 + i * 28), line, fill=GREY, font=f_tag)

    # URL
    f_url = font(13, bold=False)
    url = "fun-dacha.com.ua"
    url_bb = draw.textbbox((0, 0), url, font=f_url)
    url_w = url_bb[2] - url_bb[0]
    draw.text(((w - url_w) // 2, int(h * 0.88)), url, fill="#aaaaaa", font=f_url)

    img.save(str(filename))
    print(f"  {filename.name}")


def make_pack_rect_label(filename):
    """Rectangular label with logo."""
    w, h = 850, 380
    img = Image.new("RGBA", (w, h), "#00000000")
    draw = ImageDraw.Draw(img)

    # Outer border
    draw.rounded_rectangle([0, 0, w - 1, h - 1], radius=12, outline=GREEN, width=4)
    # Inner cream fill
    draw.rounded_rectangle([8, 8, w - 9, h - 9], radius=8, fill=CREAM)

    # Inner border box for logo
    box_m = 30
    box_h = int(h * 0.38)
    draw.rounded_rectangle(
        [box_m, box_m, w - box_m, box_m + box_h],
        radius=6,
        fill=CREAM,
        outline=GREEN,
        width=2,
    )

    # FUN [tomato] DACHA inside box
    f_title = font(42)
    fun_bb = draw.textbbox((0, 0), "FUN", font=f_title)
    dacha_bb = draw.textbbox((0, 0), "DACHA", font=f_title)
    fun_w = fun_bb[2] - fun_bb[0]
    dacha_w = dacha_bb[2] - dacha_bb[0]
    text_h_val = fun_bb[3] - fun_bb[1]
    icon_r = 18
    gap = 14
    total = fun_w + gap + icon_r * 2 + gap + dacha_w
    sx = (w - total) // 2
    ty = box_m + (box_h - text_h_val) // 2 - 5

    draw.text((sx, ty), "FUN", fill=GREEN, font=f_title)
    draw_tomato_small(draw, sx + fun_w + gap + icon_r, ty + text_h_val // 2 + 4, icon_r)
    draw.text((sx + fun_w + gap + icon_r * 2 + gap, ty), "DACHA", fill=BLACK, font=f_title)

    # Tagline below box
    f_tag = font_italic(20)
    tagline = "Насіння · Добрива · Захист"
    tag_bb = draw.textbbox((0, 0), tagline, font=f_tag)
    tag_w = tag_bb[2] - tag_bb[0]
    draw.text(((w - tag_w) // 2, box_m + box_h + 30), tagline, fill=BROWN, font=f_tag)

    # URL
    f_url = font(14, bold=False)
    url = "fun-dacha.com.ua"
    url_bb = draw.textbbox((0, 0), url, font=f_url)
    url_w = url_bb[2] - url_bb[0]
    draw.text(((w - url_w) // 2, int(h * 0.82)), url, fill=GREY, font=f_url)

    img.save(str(filename))
    print(f"  {filename.name}")


def make_pack_thank_you(filename):
    """Thank you card."""
    w, h = 900, 750
    img = Image.new("RGBA", (w, h), "#00000000")
    draw = ImageDraw.Draw(img)

    # Soft card background
    draw.rounded_rectangle([20, 20, w - 20, h - 20], radius=24, fill=CREAM, outline="#e0d8d0", width=2)

    # Decorative green blobs
    draw.ellipse([w - 120, h - 140, w + 20, h + 20], fill="#207d4318")
    draw.ellipse([-30, h - 180, 80, h - 80], fill="#207d4312")

    # Tomato
    draw_tomato(draw, w // 2, int(h * 0.13), 30, shadow=False)

    # Main heading
    f_heading = font(52)
    lines = ["ДЯКУЄМО ЗА", "ЗАМОВЛЕННЯ!"]
    for i, line in enumerate(lines):
        bb = draw.textbbox((0, 0), line, font=f_heading)
        lw = bb[2] - bb[0]
        draw.text(((w - lw) // 2, int(h * 0.22) + i * 65), line, fill=GREEN, font=f_heading)

    # Divider
    div_y = int(h * 0.5)
    draw.line([(w // 2 - 40, div_y), (w // 2 + 40, div_y)], fill="#ccbbaa", width=2)

    # Body text
    f_body = font_italic(24)
    body_lines = ["Бажаємо гарного врожаю!", "Ваш магазин Fun Dacha"]
    for i, line in enumerate(body_lines):
        bb = draw.textbbox((0, 0), line, font=f_body)
        lw = bb[2] - bb[0]
        draw.text(((w - lw) // 2, int(h * 0.56) + i * 42), line, fill="#666666", font=f_body)

    # URL
    f_url = font(16, bold=False)
    url = "fun-dacha.com.ua"
    url_bb = draw.textbbox((0, 0), url, font=f_url)
    url_w = url_bb[2] - url_bb[0]
    draw.text(((w - url_w) // 2, int(h * 0.78)), url, fill="#aaaaaa", font=f_url)

    img.save(str(filename))
    print(f"  {filename.name}")


# ═══════════════════════════════════════════
# Seal / sticker
# ═══════════════════════════════════════════

def make_brand_seal(filename):
    """Round green brand seal with tomato."""
    size = 720
    img = Image.new("RGBA", (size, size), "#00000000")
    draw = ImageDraw.Draw(img)
    cx, cy = size // 2, size // 2
    R = size // 2 - 20

    # Outer circle
    draw.ellipse([cx - R, cy - R, cx + R, cy + R], fill=GREEN)
    # Inner ring
    inner_r = int(R * 0.92)
    draw.ellipse(
        [cx - inner_r, cy - inner_r, cx + inner_r, cy + inner_r],
        fill=GREEN,
        outline=GREEN_DARK,
        width=3,
    )
    # Dashed inner ring (simplified — dotted)
    dash_r = int(R * 0.85)
    for angle in range(0, 360, 5):
        rad = math.radians(angle)
        dx = cx + int(math.cos(rad) * dash_r)
        dy = cy + int(math.sin(rad) * dash_r)
        if angle % 10 < 5:
            draw.ellipse([dx - 1, dy - 1, dx + 1, dy + 1], fill="#ffffff50")

    # Tomato
    draw_tomato(draw, cx, int(cy * 0.7), 35, shadow=False)

    # FUN DACHA text
    f_text = font(38)
    text = "FUN DACHA"
    bb = draw.textbbox((0, 0), text, font=f_text)
    tw = bb[2] - bb[0]
    draw.text(((size - tw) // 2, int(size * 0.53)), text, fill=WHITE, font=f_text)

    # Tagline
    f_tag = font(14, bold=False)
    tagline = "НАСІННЯ  ·  ДОБРИВА  ·  ЗАХИСТ"
    tag_bb = draw.textbbox((0, 0), tagline, font=f_tag)
    tag_w = tag_bb[2] - tag_bb[0]
    draw.text(((size - tag_w) // 2, int(size * 0.65)), tagline, fill="#ffffffcc", font=f_tag)

    img.save(str(filename))
    print(f"  {filename.name}")


# ═══════════════════════════════════════════
# QR banners (logo portion only — preserve QR codes)
# ═══════════════════════════════════════════

def make_banner_hero_wide(filename):
    """Wide hero banner with logo and QR."""
    w, h = 1200, 150
    img = Image.new("RGBA", (w, h), "#00000000")
    draw = ImageDraw.Draw(img)

    # Green gradient background
    draw.rounded_rectangle([0, 0, w - 1, h - 1], radius=12, fill=GREEN)
    # Slightly darker right side
    draw.rectangle([w // 2, 0, w, h], fill=GREEN_DARK)
    # Re-draw the main green part with rounding
    draw.rounded_rectangle([0, 0, int(w * 0.7), h - 1], radius=12, fill=GREEN)

    # FUN [tomato] DACHA
    f_title = font(30)
    fun_bb = draw.textbbox((0, 0), "FUN", font=f_title)
    dacha_bb = draw.textbbox((0, 0), "DACHA", font=f_title)
    fun_w = fun_bb[2] - fun_bb[0]
    dacha_w = dacha_bb[2] - dacha_bb[0]
    icon_r = 12
    gap = 8
    total = fun_w + gap + icon_r * 2 + gap + dacha_w
    sx = int(w * 0.3)
    ty = 18

    draw.text((sx, ty), "FUN", fill=WHITE, font=f_title)
    draw_tomato_small(draw, sx + fun_w + gap + icon_r, ty + 18, icon_r)
    draw.text((sx + fun_w + gap + icon_r * 2 + gap, ty), "DACHA", fill=WHITE, font=f_title)

    # Subtitle
    f_sub = font(12, bold=False)
    draw.text((sx, ty + 38), "Насіння · Добрива · Захист рослин", fill="#ffffffcc", font=f_sub)

    # CTA
    f_cta = font(18)
    cta_lines = ["Замовляй онлайн — доставка по", "Україні!"]
    for i, line in enumerate(cta_lines):
        draw.text((sx, ty + 60 + i * 24), line, fill=WHITE, font=f_cta)

    # QR placeholder area
    qr_size = 100
    qr_x = w - qr_size - 30
    qr_y = (h - qr_size) // 2
    draw.rounded_rectangle([qr_x - 5, qr_y - 5, qr_x + qr_size + 5, qr_y + qr_size + 5], radius=6, fill=WHITE)
    # QR pattern placeholder
    f_qr = font(10, bold=False)
    draw.text((qr_x + 20, qr_y + 40), "QR CODE", fill=GREEN, font=f_qr)

    # URL
    f_url = font(11, bold=False)
    draw.text((qr_x, qr_y + qr_size + 8), "fun-dacha.com.ua", fill="#ffffffcc", font=f_url)

    img.save(str(filename))
    print(f"  {filename.name}")


def make_banner_dark_promo(filename):
    """Dark promo banner."""
    w, h = 1200, 150
    img = Image.new("RGBA", (w, h), "#00000000")
    draw = ImageDraw.Draw(img)

    # Dark background with green left accent
    draw.rectangle([0, 0, w, h], fill=BLACK)
    draw.rectangle([0, 0, 6, h], fill=GREEN)

    # FUN [tomato] DACHA
    f_title = font(28)
    fun_bb = draw.textbbox((0, 0), "FUN", font=f_title)
    dacha_bb = draw.textbbox((0, 0), "DACHA", font=f_title)
    fun_w = fun_bb[2] - fun_bb[0]
    icon_r = 11
    gap = 8
    sx = int(w * 0.3)
    ty = 16

    draw.text((sx, ty), "FUN", fill=GREEN, font=f_title)
    draw_tomato_small(draw, sx + fun_w + gap + icon_r, ty + 16, icon_r)
    draw.text((sx + fun_w + gap + icon_r * 2 + gap, ty), "DACHA", fill=WHITE, font=f_title)

    # Main text
    f_main = font(36)
    draw.text((sx, ty + 38), "БЕЗКОШТОВНА ДОСТАВКА", fill=WHITE, font=f_main)

    # Subtitle
    f_sub = font_italic(14)
    draw.text((sx, ty + 82), "При замовленні від 2000 ₴ · Нова Пошта", fill="#ffffffaa", font=f_sub)

    # QR area
    qr_size = 100
    qr_x = w - qr_size - 30
    qr_y = (h - qr_size) // 2
    draw.rounded_rectangle([qr_x - 5, qr_y - 5, qr_x + qr_size + 5, qr_y + qr_size + 5], radius=6, fill=WHITE)
    f_qr = font(10, bold=False)
    draw.text((qr_x + 20, qr_y + 40), "QR CODE", fill=GREEN, font=f_qr)
    f_url = font(10, bold=False)
    draw.text((qr_x, qr_y + qr_size + 8), "fun-dacha.com.ua", fill=GREEN, font=f_url)

    img.save(str(filename))
    print(f"  {filename.name}")


def make_banner_square_insta(filename):
    """Instagram square banner."""
    size = 1080
    img = Image.new("RGBA", (size, size), CREAM)
    draw = ImageDraw.Draw(img)

    # Top/bottom decorative lines
    draw.rectangle([0, 0, size, 8], fill=BROWN)
    draw.rectangle([0, size - 8, size, size], fill=BROWN)

    # FUN [tomato] DACHA large
    f_title = font(72)
    fun_bb = draw.textbbox((0, 0), "FUN", font=f_title)
    dacha_bb = draw.textbbox((0, 0), "DACHA", font=f_title)
    fun_w = fun_bb[2] - fun_bb[0]
    dacha_w = dacha_bb[2] - dacha_bb[0]
    icon_r = 28
    gap = 18
    total = fun_w + gap + icon_r * 2 + gap + dacha_w
    sx = (size - total) // 2
    ty = int(size * 0.08)

    draw.text((sx, ty), "FUN", fill=GREEN, font=f_title)
    draw_tomato(draw, sx + fun_w + gap + icon_r, ty + 42, icon_r, shadow=False)
    draw.text((sx + fun_w + gap + icon_r * 2 + gap, ty), "DACHA", fill=BLACK, font=f_title)

    # Divider
    div_y = ty + 95
    draw.line([(size // 2 - 50, div_y), (size // 2 + 50, div_y)], fill=GREEN, width=3)

    # Main heading
    f_heading = font(52)
    heading = "ВЕСНЯНИЙ РОЗПРОДАЖ"
    hbb = draw.textbbox((0, 0), heading, font=f_heading)
    hw = hbb[2] - hbb[0]
    draw.text(((size - hw) // 2, int(size * 0.22)), heading, fill=GREEN_DARK, font=f_heading)

    # Sub
    f_sub = font_italic(28)
    sub = "Знижки до -15% на все насіння"
    sbb = draw.textbbox((0, 0), sub, font=f_sub)
    sw = sbb[2] - sbb[0]
    draw.text(((size - sw) // 2, int(size * 0.32)), sub, fill=BLACK, font=f_sub)

    # QR placeholder
    qr_size = 240
    qr_x = int(size * 0.1)
    qr_y = int(size * 0.48)
    draw.rounded_rectangle(
        [qr_x, qr_y, qr_x + qr_size, qr_y + qr_size],
        radius=12,
        fill=WHITE,
        outline=GREEN,
        width=3,
    )
    f_qr = font(16, bold=False)
    draw.text((qr_x + qr_size // 2 - 30, qr_y + qr_size // 2 - 8), "QR CODE", fill=GREEN, font=f_qr)

    # CTA text
    f_cta = font(28)
    cta_x = qr_x + qr_size + 50
    draw.text((cta_x, qr_y + 40), "Скануй та замовляй", fill=GREEN_DARK, font=f_cta)
    f_url = font(24)
    draw.text((cta_x, qr_y + 80), "fun-dacha.com.ua", fill=BLACK, font=f_url)

    # Bottom tagline
    f_tag = font_italic(20)
    tagline = "Насіння · Добрива · Захист"
    tbb = draw.textbbox((0, 0), tagline, font=f_tag)
    tw = tbb[2] - tbb[0]
    draw.text(((size - tw) // 2, int(size * 0.88)), tagline, fill=GREY, font=f_tag)

    img.save(str(filename))
    print(f"  {filename.name}")


def make_banner_story_vertical(filename):
    """Vertical story banner."""
    w, h = 540, 960
    img = Image.new("RGBA", (w, h), "#00000000")
    draw = ImageDraw.Draw(img)

    # Green gradient bg
    draw.rounded_rectangle([0, 0, w - 1, h - 1], radius=20, fill=GREEN)
    # Darker bottom area
    draw.rectangle([0, int(h * 0.6), w, h], fill=GREEN_DARK)
    draw.rounded_rectangle([0, 0, w - 1, int(h * 0.65)], radius=20, fill=GREEN)

    # FUN [tomato] DACHA
    f_title = font(28)
    fun_bb = draw.textbbox((0, 0), "FUN", font=f_title)
    fun_w = fun_bb[2] - fun_bb[0]
    icon_r = 10
    gap = 6
    total_logo = fun_w + gap + icon_r * 2 + gap + 100
    sx = (w - total_logo) // 2 + 10
    ty = 30

    draw.text((sx, ty), "FUN", fill=WHITE, font=f_title)
    draw_tomato_small(draw, sx + fun_w + gap + icon_r, ty + 16, icon_r)
    draw.text((sx + fun_w + gap + icon_r * 2 + gap, ty), "DACHA", fill=WHITE, font=f_title)

    # Subtitle
    f_sub = font(12, bold=False)
    sub = "Інтернет-магазин"
    sbb = draw.textbbox((0, 0), sub, font=f_sub)
    sw = sbb[2] - sbb[0]
    draw.text(((w - sw) // 2, ty + 38), sub, fill="#ffffffcc", font=f_sub)

    # Main heading
    f_heading = font(48)
    lines = ["СЕЗОН", "ВІДКРИТО!"]
    for i, line in enumerate(lines):
        lbb = draw.textbbox((0, 0), line, font=f_heading)
        lw = lbb[2] - lbb[0]
        draw.text(((w - lw) // 2, int(h * 0.18) + i * 58), line, fill=WHITE, font=f_heading)

    # Body
    f_body = font_italic(18)
    body = "Свіже насіння вже у продажу"
    bbb = draw.textbbox((0, 0), body, font=f_body)
    bw = bbb[2] - bbb[0]
    draw.text(((w - bw) // 2, int(h * 0.38)), body, fill="#ffffffdd", font=f_body)

    # QR placeholder
    qr_size = 200
    qr_x = (w - qr_size) // 2
    qr_y = int(h * 0.48)
    draw.rounded_rectangle([qr_x, qr_y, qr_x + qr_size, qr_y + qr_size], radius=10, fill=WHITE)
    f_qr = font(14, bold=False)
    draw.text((qr_x + qr_size // 2 - 28, qr_y + qr_size // 2 - 7), "QR CODE", fill=GREEN, font=f_qr)

    # Footer
    f_url = font(16)
    url = "fun-dacha.com.ua"
    ubb = draw.textbbox((0, 0), url, font=f_url)
    uw = ubb[2] - ubb[0]
    draw.text(((w - uw) // 2, int(h * 0.82)), url, fill=WHITE, font=f_url)

    f_tag = font(11, bold=False)
    tag = "Насіння · Добрива · Захист"
    tbb = draw.textbbox((0, 0), tag, font=f_tag)
    tw = tbb[2] - tbb[0]
    draw.text(((w - tw) // 2, int(h * 0.87)), tag, fill="#ffffffaa", font=f_tag)

    img.save(str(filename))
    print(f"  {filename.name}")


# ═══════════════════════════════════════════
# SVG seal update
# ═══════════════════════════════════════════

def make_seal_svg(filename, dark=False):
    """Update SVG seal with tomato icon instead of sprout."""
    tomato_svg = """<g transform="translate(120, 68)" class="seal-icon">
        <!-- Tomato body -->
        <ellipse cx="0" cy="8" rx="16" ry="13" fill="#e53935"/>
        <ellipse cx="0" cy="11" rx="14" ry="10" fill="#c62828"/>
        <ellipse cx="0" cy="7" rx="15" ry="12" fill="#e53935"/>
        <!-- Highlight -->
        <ellipse cx="-5" cy="3" rx="6" ry="4" fill="#ef5350" opacity=".6"/>
        <!-- Stem -->
        <rect x="-1.5" y="-8" width="3" height="7" rx="1.5" fill="#2e7d32"/>
        <!-- Leaves -->
        <path d="M0,-4 C-6,-10 -12,-11 -14,-9 C-10,-8 -5,-5 0,-4 Z" fill="#388e3c"/>
        <path d="M0,-5 C6,-11 12,-12 14,-10 C10,-9 5,-6 0,-5 Z" fill="#4caf50"/>
        <!-- Shadow -->
        <ellipse cx="0" cy="22" rx="12" ry="3" fill="#1a6a38" opacity=".4"/>
      </g>"""

    bg_fill = "#1a1a1a" if dark else "#207D43"
    text_fill = "#fff"

    svg = f"""<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240" width="200">
      <circle cx="120" cy="120" r="116" fill="{bg_fill}"/>
      <circle cx="120" cy="120" r="110" stroke="#1a6a38" stroke-width="2" fill="none"/>
      <circle cx="120" cy="120" r="102" fill="none" stroke="#fff" stroke-width="1.2" stroke-dasharray="4 3" opacity=".5"/>
      {tomato_svg}
      <text x="120" y="130" text-anchor="middle" fill="{text_fill}" font-family="Montserrat, sans-serif" font-weight="900" font-size="18" letter-spacing="2">FUN DACHA</text>
      <line x1="40" y1="142" x2="200" y2="142" stroke="#fff" stroke-width="1" opacity=".3"/>
      <text x="120" y="162" text-anchor="middle" fill="#FFF8F0" font-family="Open Sans, sans-serif" font-weight="600" font-size="9" letter-spacing="1.5" opacity=".85">НАСІННЯ • ДОБРИВА • ЗАХИСТ</text>
      <text x="120" y="180" text-anchor="middle" fill="#FFF8F0" font-family="Montserrat, sans-serif" font-weight="700" font-size="10" letter-spacing=".3" opacity=".7">fun-dacha.com.ua</text>
    </svg>"""

    with open(str(filename), "w", encoding="utf-8") as f:
        f.write(svg)
    print(f"  {filename.name}")


# ═══════════════════════════════════════════
# Main
# ═══════════════════════════════════════════

def main():
    print("Generating brand assets with tomato icon...\n")

    print("=== Logos ===")
    make_logo_horizontal(WHITE, GREEN, BLACK, GREY, LOGO_DIR / "fun-dacha-logo.png")
    make_logo_horizontal(WHITE, GREEN, BLACK, GREY, LOGO_DIR / "fun-dacha-logo-compact.png", width=900, height=300)
    make_logo_horizontal(BLACK, GREEN, WHITE, GREY, LOGO_DIR / "fun-dacha-logo-dark.png")
    make_logo_horizontal(CREAM, GREEN, BLACK, BROWN, LOGO_DIR / "fun-dacha-logo-cream.png")
    make_logo_stacked(WHITE, GREEN, BLACK, GREY, LOGO_DIR / "fun-dacha-logo-stacked.png")
    make_logo_stacked(BLACK, GREEN, WHITE, GREY, LOGO_DIR / "fun-dacha-logo-stacked-dark.png")
    make_monogram(LOGO_DIR / "fun-dacha-monogram.png")

    print("\n=== Art / Packaging ===")
    make_pack_box_art(ART_DIR / "pack-box-art.png")
    make_pack_brand_tape(ART_DIR / "pack-brand-tape.png")
    make_pack_hang_tag(ART_DIR / "pack-hang-tag.png")
    make_pack_rect_label(ART_DIR / "pack-rect-label.png")
    make_pack_thank_you(ART_DIR / "pack-thank-you.png")

    print("\n=== Seal / Sticker ===")
    make_brand_seal(SPICKER_DIR / "pack-brand-seal.png")
    make_seal_svg(SPICKER_DIR / "fun-dacha-seal.svg", dark=False)
    make_seal_svg(SPICKER_DIR / "fun-dacha-seal-dark.svg", dark=True)

    print("\n=== QR Banners ===")
    make_banner_hero_wide(QR_DIR / "banner-hero-wide.png")
    make_banner_dark_promo(QR_DIR / "banner-dark-promo.png")
    make_banner_square_insta(QR_DIR / "banner-square-insta.png")
    make_banner_story_vertical(QR_DIR / "banner-story-vertical.png")

    print("\nDone! All brand assets regenerated with tomato icon.")


if __name__ == "__main__":
    main()
