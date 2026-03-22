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

    # Option C: Auto-generate mapping from products_import.xlsx Categories (name match):
    #   python3 shared/data/map_category_images.py --from-products
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
    """Extract category IDs from c{id}.jpg or c{id}_banner.jpg."""
    ids = set()
    for f in IMAGE_CATALOG.glob("c*.jpg"):
        name = f.stem
        if name.startswith("c"):
            rest = name[1:]
            if rest.endswith("_banner"):
                rest = rest[:-7]
            if rest.isdigit():
                ids.add(rest)
    return ids


def _infer_parent_id(cid: str, all_ids: set[str]) -> str | None:
    """Infer parent category ID when products_import not available."""
    n = int(cid)
    for div in (10, 100):
        parent = (n // div) * div
        if parent != n and str(parent) in all_ids:
            return str(parent)
    return None


def load_categories_from_products_import(path: Path) -> tuple[dict[str, int], dict[str, str]]:
    """Load category_id->parent_id and category_id->name from products_import.xlsx Categories sheet.
    Returns (parent_map: cid->parent_id, name_map: cid->name_en)."""
    try:
        from openpyxl import load_workbook
    except ImportError:
        return {}, {}

    if not path.exists():
        return {}, {}

    wb = load_workbook(path, read_only=True)
    if "Categories" not in wb.sheetnames:
        return {}, {}

    ws = wb["Categories"]
    headers = [c.value for c in ws[1]]
    cid_idx = headers.index("category_id") if "category_id" in headers else 0
    pid_idx = headers.index("parent_id") if "parent_id" in headers else 1
    name_idx = next((i for i, h in enumerate(headers) if h and "name" in str(h).lower() and "en" in str(h)), 2)

    parent_map: dict[str, int] = {}
    name_map: dict[str, str] = {}
    for row in ws.iter_rows(min_row=2, values_only=True):
        cid = row[cid_idx]
        pid = row[pid_idx] if pid_idx < len(row) else 0
        name = row[name_idx] if name_idx < len(row) and row[name_idx] else ""
        if cid is not None and str(cid).strip():
            cid_s = str(int(cid)) if isinstance(cid, (int, float)) else str(cid).strip()
            parent_map[cid_s] = int(pid) if pid is not None else 0
            if name:
                name_map[cid_s] = str(name).strip()
    return parent_map, name_map


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


# Name mappings from products_import (transliterated Ukrainian) to Johnny's slugs
NAME_TO_SLUG_EXTRA = {
    "tomaty": "tomatoes",
    "ohirky": "cucumbers",
    "kapusta": "cabbage",
    "kapusta tsvitna": "cauliflower",
    "redys": "radishes",
    "perets": "peppers",
    "baklazhany": "eggplant",
    "tsybulya": "onions",
    "morkva": "carrots",
    "buryak": "beets",
    "kavuny": "watermelons",
    "dyni": "melons",
    "harbuzy": "pumpkins",
    "kabachky": "squash",
    "patysony": "squash",
    "zeleni kultury": "greens",
    "pryanosmakovi kultury": "greens",
    "bobovi kultury": "beans",
    "kvasolya": "beans",
    "kukurudza": "corn",
    "kvity": "greens",
    "dobryva ta zakhyst": "greens",
    "suputni tovary": "greens",
}


def run_from_products_import(dry_run: bool) -> None:
    """Load categories from products_import.xlsx, auto-match names, apply parent fallback."""
    products_path = DATA_DIR / "products_import.xlsx"
    parent_from_excel, name_from_excel = load_categories_from_products_import(products_path)
    if not parent_from_excel:
        print("No Categories in products_import.xlsx or file not found")
        return

    our_ids = set(parent_from_excel.keys())
    name_to_johnnys = build_name_to_johnnys()
    for k, v in NAME_TO_SLUG_EXTRA.items():
        name_to_johnnys[normalize(k)] = v

    # Match by name
    explicit: dict[str, str] = {}
    for cid, name in name_from_excel.items():
        norm = normalize(name)
        slug = name_to_johnnys.get(norm)
        if not slug and " " in name:
            slug = name_to_johnnys.get(normalize(name.split()[0]))
        if slug:
            explicit[cid] = slug

    parent_map = {k: str(v) for k, v in parent_from_excel.items() if v != 0}

    def get_parent(cid: str) -> str | None:
        if cid in parent_map:
            p = parent_map[cid]
            return p if p in our_ids else None
        return _infer_parent_id(cid, our_ids)

    resolved = dict(explicit)
    for _ in range(10):
        changed = False
        for cid in our_ids:
            if cid in resolved:
                continue
            parent = get_parent(cid)
            if parent and parent in resolved:
                resolved[cid] = resolved[parent]
                changed = True
        if not changed:
            break

    matched = []
    for c in sorted(resolved.keys(), key=int):
        s = resolved[c]
        src = "explicit" if c in explicit else "parent"
        matched.append({"category_id": c, "slug": s, "source": src})
    sq_dir = _johnnys_dir(True)
    bn_dir = _johnnys_dir(False)
    unmatched_ours = [{"category_id": i} for i in our_ids if i not in resolved]
    unmatched_johnnys = set(JOHNNYS_SLUG_TO_NAME.keys()) - set(resolved.values())
    _apply_and_report(matched, unmatched_ours, unmatched_johnnys, dry_run, sq_dir, bn_dir)


def run_with_mapping(mapping_path: Path, dry_run: bool) -> None:
    """Use category_mapping.csv. Banner->c{id}_banner.jpg, square->c{id}.jpg.
    Child without mapping inherits parent's image.
    Uses products_import.xlsx for real parent_id when available."""
    mapping = load_mapping(mapping_path)
    our_ids = get_our_category_ids_from_files()
    products_path = DATA_DIR / "products_import.xlsx"
    parent_from_excel, _ = load_categories_from_products_import(products_path)
    parent_map = {k: str(v) for k, v in parent_from_excel.items() if v != 0}

    def get_parent(cid: str) -> str | None:
        if cid in parent_map:
            p = parent_map[cid]
            return p if p in our_ids else None
        return _infer_parent_id(cid, our_ids)

    # Build cid -> slug from explicit mapping
    explicit: dict[str, str] = {}
    for row in mapping:
        cid, slug = row["category_id"], row["slug"]
        if cid and slug:
            explicit[cid] = slug

    # Resolve slug: explicit first, then parent fallback (multiple passes)
    resolved: dict[str, str] = dict(explicit)
    for _ in range(10):
        changed = False
        for cid in our_ids:
            if cid in resolved:
                continue
            parent = get_parent(cid)
            if parent and parent in resolved:
                resolved[cid] = resolved[parent]
                changed = True
        if not changed:
            break

    # Build matched with source (explicit vs parent)
    matched = []
    for c in sorted(resolved.keys(), key=int):
        s = resolved[c]
        src = "explicit" if c in explicit else "parent"
        matched.append({"category_id": c, "slug": s, "source": src})
    johnnys_square_dir = _johnnys_dir(True)
    johnnys_banner_dir = _johnnys_dir(False)
    unmatched_ours = [{"category_id": i} for i in our_ids if i not in resolved]
    johnnys_used = set(resolved.values())
    unmatched_johnnys = set(JOHNNYS_SLUG_TO_NAME.keys()) - johnnys_used
    _apply_and_report(matched, unmatched_ours, unmatched_johnnys, dry_run, johnnys_square_dir, johnnys_banner_dir)


def _apply_and_report(
    matched: list,
    unmatched_ours: list,
    unmatched_johnnys: set,
    dry_run: bool,
    johnnys_square_dir: Path,
    johnnys_banner_dir: Path,
) -> None:
    """Copy both square (c{id}.jpg) and banner (c{id}_banner.jpg). SQL uses square as main."""
    print("=== MATCHED (will replace) ===")
    IMAGE_CATALOG.mkdir(parents=True, exist_ok=True)
    sql_updates = []
    for m in matched:
        cid, slug = m["category_id"], m["slug"]
        src_square = johnnys_square_dir / f"{slug}.jpg"
        src_banner = johnnys_banner_dir / f"{slug}.jpg"
        dest_square = IMAGE_CATALOG / f"c{cid}.jpg"
        dest_banner = IMAGE_CATALOG / f"c{cid}_banner.jpg"
        rel_square = f"catalog/categories/c{cid}.jpg"
        src_note = f" ({m.get('source', '')})" if m.get("source") == "parent" else ""
        print(f"  c{cid}.jpg + c{cid}_banner.jpg <- {slug}{src_note}")
        if not dry_run:
            if src_square.exists():
                shutil.copy2(src_square, dest_square)
            if src_banner.exists():
                shutil.copy2(src_banner, dest_banner)
        sql_updates.append(
            f"UPDATE `{DB_PREFIX}category` SET `image` = '{rel_square}' WHERE `category_id` = {cid};"
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
        f = johnnys_square_dir / f"{slug}.jpg"
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
    ap.add_argument("--from-products", action="store_true", help="Use products_import.xlsx for categories + mapping")
    ap.add_argument("--dry-run", action="store_true", help="Do not copy files or write SQL")
    args = ap.parse_args()

    if args.from_products:
        run_from_products_import(args.dry_run)
        return

    if args.use_mapping:
        mapping_path = DATA_DIR / "category_mapping.csv"
        if not mapping_path.exists():
            print(f"Not found: {mapping_path}")
            return
        run_with_mapping(mapping_path, args.dry_run)
        return

    our_csv = args.our_csv or str(DATA_DIR / "our_categories.csv")
    our_path = Path(our_csv)

    if not our_path.exists():
        print("Our categories file not found. Use --use-mapping with category_mapping.csv")
        print("Or run: php shared/data/export_our_categories.php")
        d = _johnnys_dir(True)
        if d.exists():
            print("\nAvailable Johnny's images:")
            for f in sorted(d.glob("*.jpg")):
                print(f"  - {f.name}")
        return

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
            sq = _johnnys_dir(True) / f"{slug}.jpg"
            bn = _johnnys_dir(False) / f"{slug}.jpg"
            if sq.exists() or bn.exists():
                matched.append({"category_id": cid, "slug": slug})
                our_matched_ids.add(cid)
                johnnys_matched.add(slug)

    unmatched_ours = [c for c in our_cats if c["category_id"] not in our_matched_ids]
    unmatched_johnnys = set(JOHNNYS_SLUG_TO_NAME.keys()) - johnnys_matched
    sq_dir = _johnnys_dir(True)
    bn_dir = _johnnys_dir(False)
    _apply_and_report(matched, unmatched_ours, unmatched_johnnys, args.dry_run, sq_dir, bn_dir)


if __name__ == "__main__":
    main()
