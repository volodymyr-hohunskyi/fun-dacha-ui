#!/usr/bin/env python3
"""
Download category images from Johnny's Selected Seeds vegetables page.
Each category page has a banner image (catbanner) - we fetch and save them.

Usage:
    python3 shared/data/download_johnnys_category_images.py

Output:
    shared/category_images/johnnys/ - downloaded images named by slug (beans.jpg, tomatoes.jpg, etc.)
"""

from __future__ import annotations

import re
import urllib.request
from pathlib import Path

# All 55 vegetable category slugs from Johnny's (from main vegetables page)
JOHNNYS_SLUGS = [
    "artichokes",
    "asparagus",
    "beans",
    "beets",
    "broccoli",
    "brussels-sprouts",
    "burdock",
    "cabbage",
    "cardoon",
    "carrots",
    "cauliflower",
    "celery-and-celeriac",
    "chicory",
    "chinese-cabbage",
    "collards",
    "corn",
    "cucumbers",
    "eggplant",
    "fennel",
    "garlic",
    "gourds",
    "greens",
    "horseradish",
    "husk-cherry",
    "kale",
    "kalettes",
    "kohlrabi",
    "leeks",
    "lettuce",
    "melons",
    "microgreens",
    "mushrooms",
    "okra",
    "onions",
    "parsnips",
    "peas",
    "peppers",
    "potatoes",
    "pumpkins",
    "radishes",
    "rutabagas",
    "salsify",
    "scorzonera",
    "shallots",
    "shoots",
    "spinach",
    "sprouts",
    "squash",
    "sweet-potatoes",
    "swiss-chard",
    "tomatillos",
    "tomatoes",
    "turnips",
    "watermelons",
    "zucchini",
]

OUTPUT_DIR = Path(__file__).resolve().parent.parent / "category_images" / "johnnys"
BASE_URL = "https://www.johnnyseeds.com/vegetables/"
IMG_PATTERN = re.compile(
    r'src="(https://www\.johnnyseeds\.com/dw/image[^"]*images/catbanner/(?:vegetables/)?[^"?]+\.jpg)'
)


def fetch_image_url(slug: str) -> str | None:
    """Fetch category page HTML and extract catbanner image URL."""
    url = BASE_URL + slug + "/"
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
        with urllib.request.urlopen(req, timeout=15) as resp:
            html = resp.read().decode("utf-8", errors="replace")
    except Exception as e:
        print(f"  Error fetching {url}: {e}")
        return None

    m = IMG_PATTERN.search(html)
    if m:
        return m.group(1).split("?")[0]  # strip query params for cleaner URL
    return None


def download_image(url: str, dest: Path) -> bool:
    """Download image from URL to dest path."""
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
        with urllib.request.urlopen(req, timeout=30) as resp:
            data = resp.read()
        dest.parent.mkdir(parents=True, exist_ok=True)
        dest.write_bytes(data)
        return True
    except Exception as e:
        print(f"  Download error: {e}")
        return False


def main() -> None:
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    print(f"Downloading {len(JOHNNYS_SLUGS)} category images to {OUTPUT_DIR}")

    results = {}
    for i, slug in enumerate(JOHNNYS_SLUGS, 1):
        # File name: slugs like "brussels-sprouts" -> "brussels-sprouts.jpg"
        safe_name = slug.replace("/", "-")
        dest = OUTPUT_DIR / f"{safe_name}.jpg"

        print(f"[{i}/{len(JOHNNYS_SLUGS)}] {slug}...", end=" ")
        url = fetch_image_url(slug)
        if url:
            if download_image(url, dest):
                print(f"OK -> {dest.name}")
                results[slug] = str(dest)
            else:
                print("DOWNLOAD FAILED")
                results[slug] = None
        else:
            print("NO IMAGE FOUND")
            results[slug] = None

    # Summary
    ok = sum(1 for v in results.values() if v)
    print(f"\nDone: {ok}/{len(JOHNNYS_SLUGS)} images downloaded")
    return results


if __name__ == "__main__":
    main()
