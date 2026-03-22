#!/usr/bin/env python3
"""
Map Johnny's category images to our OpenCart categories and replace where matched.

Two modes:
1) With our_categories.csv (from DB export): auto-matches by name
2) With category_mapping.csv: use manual mapping (our_category_id, johnnys_slug)

Usage:
    # Option A: Export from DB (php shared/data/export_our_categories.php)
    #   Then: python3 shared/data/map_category_images.py

    # Option B: Edit shared/data/category_mapping.csv - set johnnys_slug for each
    #   our_category_id. Run: python3 shared/data/map_category_images.py --use-mapping

    # Use --square to take images from johnnys_square/ (400x400) instead of johnnys/ (banner)
"""

from __future__ import annotations

import argparse
import csv
import re
import shutil
import unicodedata
from pathlib import Path

BASE_IMAGES = Path(__file__).resolve().parent.parent / "category_images"


def _johnnys_dir(square: bool) -> Path:
    return BASE_IMAGES / ("johnnys_square" if square else "johnnys")


JOHNNYS_DIR = BASE_IMAGES / "johnnys"
IMAGE_CATALOG = Path(__file__).resolve().parent.parent.parent / "image" / "catalog" / "categories"
DATA_DIR = Path(__file__).resolve().parent
DB_PREFIX = "oc_"

# Johnny's slug -> display name (for matching)
JOHNNYS_SLUG_TO_NAME = {
    "artichokes": "Artichokes",
    "asparagus": "Asparagus",
    "beans": "Beans",
    "beets": "Beets",
    "broccoli": "Broccoli",
    "brussels-sprouts": "Brussels Sprouts",
    "burdock": "Burdock",
    "cabbage": "Cabbage",
    "cardoon": "Cardoon",
    "carrots": "Carrots",
    "cauliflower": "Cauliflower",
    "celery-and-celeriac": "Celery and Celeriac",
    "chicory": "Chicory",
    "chinese-cabbage": "Chinese Cabbage",
    "collards": "Collards",
    "corn": "Corn",
    "cucumbers": "Cucumbers",
    "eggplant": "Eggplant",
    "fennel": "Fennel",
    "garlic": "Garlic",
    "gourds": "Gourds",
    "greens": "Greens",
    "horseradish": "Horseradish",
    "husk-cherry": "Husk Cherry",
    "kale": "Kale",
    "kalettes": "Kalettes",
    "kohlrabi": "Kohlrabi",
    "leeks": "Leeks",
    "lettuce": "Lettuce",
    "melons": "Melons",
    "microgreens": "Microgreens",
    "mushrooms": "Mushrooms",
    "okra": "Okra",
    "onions": "Onions",
    "parsnips": "Parsnips",
    "peas": "Peas",
    "peppers": "Peppers",
    "potatoes": "Potatoes",
    "pumpkins": "Pumpkins",
    "radishes": "Radishes",
    "rutabagas": "Rutabagas",
    "salsify": "Salsify",
    "scorzonera": "Scorzonera",
    "shallots": "Shallots",
    "shoots": "Shoots",
    "spinach": "Spinach",
    "sprouts": "Sprouts",
    "squash": "Squash",
    "sweet-potatoes": "Sweet Potatoes",
    "swiss-chard": "Swiss Chard",
    "tomatillos": "Tomatillos",
    "tomatoes": "Tomatoes",
    "turnips": "Turnips",
    "watermelons": "Watermelons",
    "zucchini": "Zucchini",
}


def normalize(s: str) -> str:
    """Normalize for matching: lowercase, strip, collapse spaces, remove accents."""
    s = unicodedata.normalize("NFD", s)
    s = "".join(c for c in s if unicodedata.category(c) != "Mn")
    s = re.sub(r"\s+", " ", s.lower().strip())
    return s


def load_our_categories(path: Path) -> list[dict]:
    """Load our categories from CSV (category_id, name, image)."""
    rows = []
    with open(path) as f:
        r = csv.DictReader(f)
        for row in r:
            rows.append(
                {
                    "category_id": row.get("category_id", "").strip(),
                    "name": row.get("name", "").strip(),
                    "image": row.get("image", "").strip(),
                }
            )
    return rows


def build_name_to_johnnys() -> dict[str, str]:
    """Map normalized name -> johnnys slug."""
    out = {}
    for slug, name in JOHNNYS_SLUG_TO_NAME.items():
        out[normalize(name)] = slug
    for slug in JOHNNYS_SLUG_TO_NAME:
        out[normalize(slug.replace("-", " "))] = slug
    return out


def get_our_category_ids_from_files() -> set[str]:
    """Extract category IDs from existing c{id}.jpg files."""
    ids = set()
    for f in IMAGE_CATALOG.glob("c*.jpg"):
        name = f.stem
        if name.startswith("c") and name[1:].isdigit():
            ids.add(name[1:])
    return ids


def load_mapping(path: Path) -> list[dict]:
    """Load category_mapping.csv (our_category_id, johnnys_slug)."""
    rows = []
    with open(path) as f:
        r = csv.DictReader(f)
        for row in r:
            rows.append({
                "category_id": row.get("our_category_id", "").strip(),
                "slug": row.get("johnnys_slug", "").strip(),
            })
    return rows


def run_with_mapping(mapping_path: Path, dry_run: bool, use_square: bool = False) -> None:
    """Use category_mapping.csv - explicit our_category_id -> johnnys_slug."""
    mapping = load_mapping(mapping_path)
    our_ids = get_our_category_ids_from_files()
    johnnys_dir = _johnnys_dir(use_square)
    matched = []
    mapped_ours = set()
    johnnys_used = set()

    for row in mapping:
        cid, slug = row["category_id"], row["slug"]
        if not cid or not slug:
            continue
        src = johnnys_dir / f"{slug}.jpg"
        if src.exists():
            matched.append({"category_id": cid, "name": f"(id={cid})", "slug": slug, "src": src})
            mapped_ours.add(cid)
            johnnys_used.add(slug)

    unmatched_ours = [{"category_id": i} for i in sorted(our_ids, key=int) if i not in mapped_ours]
    unmatched_johnnys = set(JOHNNYS_SLUG_TO_NAME.keys()) - johnnys_used
    _apply_and_report(matched, unmatched_ours, unmatched_johnnys, dry_run, johnnys_dir)


def _apply_and_report(
    matched: list, unmatched_ours: list, unmatched_johnnys: set, dry_run: bool, johnnys_dir: Path
) -> None:
    print("=== MATCHED (will replace) ===")
    IMAGE_CATALOG.mkdir(parents=True, exist_ok=True)
    sql_updates = []
    for m in matched:
        dest = IMAGE_CATALOG / f"c{m['category_id']}.jpg"
        rel_path = f"catalog/categories/c{m['category_id']}.jpg"
        print(f"  c{m['category_id']} <- {m['slug']}.jpg")
        if not dry_run:
            shutil.copy2(m["src"], dest)
        sql_updates.append(
            f"UPDATE `{DB_PREFIX}category` SET `image` = '{rel_path}' WHERE `category_id` = {m['category_id']};"
        )

    if sql_updates and not dry_run:
        sql_path = DATA_DIR / "category_image_updates.sql"
        sql_path.write_text("\n".join(sql_updates), encoding="utf-8")
        print(f"\nSQL saved to {sql_path}")

    print("\n=== OUR CATEGORIES NOT MAPPED (add johnnys_slug in category_mapping.csv) ===")
    for c in unmatched_ours:
        print(f"  {c.get('category_id', c.get('name', ''))}")

    print("\n=== JOHNNY'S IMAGES NOT USED ===")
    for slug in sorted(unmatched_johnnys):
        name = JOHNNYS_SLUG_TO_NAME.get(slug, slug)
        f = johnnys_dir / f"{slug}.jpg"
        status = " (has image)" if f.exists() else " (no image)"
        print(f"  {slug}: {name}{status}")

    print("\n--- Summary ---")
    print(f"Matched & replaced: {len(matched)}")
    print(f"Our categories needing mapping: {len(unmatched_ours)}")
    print(f"Johnny's images unused: {len(unmatched_johnnys)}")


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--our-csv", default=None, help="Path to our categories CSV")
    ap.add_argument("--use-mapping", action="store_true", help="Use category_mapping.csv")
    ap.add_argument("--dry-run", action="store_true", help="Do not copy files or write SQL")
    ap.add_argument("--square", action="store_true", help="Use square images from johnnys_square/")
    args = ap.parse_args()

    if args.use_mapping:
        mapping_path = DATA_DIR / "category_mapping.csv"
        if not mapping_path.exists():
            print(f"Not found: {mapping_path}")
            return
        run_with_mapping(mapping_path, args.dry_run, args.square)
        return

    our_csv = args.our_csv or str(DATA_DIR / "our_categories.csv")
    our_path = Path(our_csv)

    if not our_path.exists():
        print("Our categories file not found. Use --use-mapping with category_mapping.csv")
        print("Or run: php shared/data/export_our_categories.php")
        d = _johnnys_dir(args.square)
        if d.exists():
            print("\nAvailable Johnny's images:")
            for f in sorted(d.glob("*.jpg")):
                print(f"  - {f.name}")
        return

    johnnys_dir = _johnnys_dir(args.square)
    our_cats = load_our_categories(our_path)
    name_to_johnnys = build_name_to_johnnys()
    matched = []
    our_matched_ids = set()
    johnnys_matched = set()

    for cat in our_cats:
        cid = cat["category_id"]
        name = cat["name"]
        norm = normalize(name)
        slug = name_to_johnnys.get(norm)
        if not slug and " " in name:
            slug = name_to_johnnys.get(normalize(name.split()[0]))
        if slug:
            src = johnnys_dir / f"{slug}.jpg"
            if src.exists():
                matched.append({"category_id": cid, "name": name, "slug": slug, "src": src})
                our_matched_ids.add(cid)
                johnnys_matched.add(slug)

    unmatched_ours = [c for c in our_cats if c["category_id"] not in our_matched_ids]
    unmatched_johnnys = set(JOHNNYS_SLUG_TO_NAME.keys()) - johnnys_matched
    _apply_and_report(matched, unmatched_ours, unmatched_johnnys, args.dry_run, johnnys_dir)


if __name__ == "__main__":
    main()
