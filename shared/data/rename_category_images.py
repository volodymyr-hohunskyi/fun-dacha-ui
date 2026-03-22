#!/usr/bin/env python3
"""
Rename product images in categories 401+ where image names use sequential numbers
(001, 002, ...) but should use product IDs from products_import.

Logic: p401001.jpg is image for 1st product in category 401 (p401840).
       p402001.jpg is image for 1st product in category 402 (p402948).
       p403001.jpg is image for 1st product in category 403 (p4031145).
Map by order: image p{cat}{seq:03d}.jpg -> products_sorted[seq-1]
"""

from pathlib import Path
from openpyxl import load_workbook
from collections import defaultdict

SHARED_PRODUCTS = Path(__file__).resolve().parent.parent / "images" / "products"
CATALOG_PRODUCTS = Path(__file__).resolve().parent.parent.parent / "image" / "catalog" / "products"
PRODUCTS_IMPORT = Path(__file__).resolve().parent / "products_import.xlsx"


def get_products_by_category():
    """Get products per category 401+ and 5xx, sorted by product_id."""
    wb = load_workbook(PRODUCTS_IMPORT, read_only=True)
    ws = wb["Products"]
    h = [c.value for c in ws[1]]
    model_idx = h.index("model")
    pid_idx = h.index("product_id")

    by_cat = defaultdict(list)
    for row in ws.iter_rows(min_row=2, values_only=True):
        m, pid = row[model_idx], row[pid_idx]
        if not m:
            continue
        m = str(m).strip()
        if m.startswith("p") and len(m) >= 7:
            try:
                cat = int(m[1:4])
                if 401 <= cat <= 599:
                    by_cat[cat].append((int(pid) if pid else 0, m))
            except (ValueError, TypeError):
                pass

    result = {}
    for cat, rows in by_cat.items():
        result[cat] = [r[1] for r in sorted(rows, key=lambda x: x[0])]
    return result


# Max numeric part for "sequential" images - product IDs often 800+, 1100+
# e.g. p402081=sequential, p402948=product ID. p405012=sequential, p4051303=product ID
MAX_SEQ_NUM = 500

# Categories already fixed (have correct product-ID images)
SKIP_CATEGORIES = {401, 402, 403, 405, 406, 407, 408, 500}


def get_sequential_images(dir_path: Path, cat: int, max_count: int | None = None):
    """Get p{cat}001.jpg, p{cat}002.jpg, ... in order (sequential only, exclude product IDs).
    Only include images with small num (<=MAX_SEQ_NUM) to avoid p402948 etc.
    If max_count set, return first max_count images."""
    prefix = f"p{cat}"
    images = []
    for f in sorted(dir_path.iterdir()):
        if not f.is_file():
            continue
        name = f.name
        if name.startswith(prefix) and name.endswith(".jpg") and "_" not in name:
            try:
                num = int(name[len(prefix) : -4])
                if 1 <= num <= MAX_SEQ_NUM:
                    images.append((num, name))
            except ValueError:
                pass
    sorted_images = [x[1] for x in sorted(images, key=lambda a: a[0])]
    if max_count is not None:
        return sorted_images[:max_count]
    return sorted_images


def main():
    import sys
    dry_run = "--dry-run" in sys.argv

    products_by_cat = get_products_by_category()
    dirs = [SHARED_PRODUCTS, CATALOG_PRODUCTS]

    total_renamed = 0
    renames = []

    for cat in sorted(products_by_cat.keys()):
        products = products_by_cat[cat]
        if not products:
            continue

        if cat in SKIP_CATEGORIES:
            continue
        cand = get_sequential_images(SHARED_PRODUCTS, cat, max_count=None)
        if not cand:
            continue

        for dir_path in dirs:
            images = get_sequential_images(dir_path, cat, max_count=len(products))
            n = min(len(products), len(images))
            for i in range(n):
                old_name = images[i]
                new_name = products[i] + ".jpg"
                old_path = dir_path / old_name
                new_path = dir_path / new_name
                if old_path.exists():
                    if old_name != new_name:
                        renames.append((str(old_path), str(new_path)))
                        total_renamed += 1

    # Deduplicate by (old_name, new_name) since we count per dir
    unique_ops = set()
    for old_p, new_p in renames:
        unique_ops.add((Path(old_p).name, Path(new_p).name))

    print(f"Total unique image mappings: {len(unique_ops)}")
    print(f"Total file renames (across both dirs): {total_renamed}")

    # Group by category
    by_cat_count = defaultdict(int)
    for old_name, new_name in unique_ops:
        try:
            cat = int(old_name[1:4])
            by_cat_count[cat] += 1
        except ValueError:
            pass
    print("\nPer category:")
    for cat in sorted(by_cat_count.keys()):
        print(f"  {cat}: {by_cat_count[cat]} images")

    if dry_run:
        print("\n[DRY RUN - no changes made]")
        return

    # Perform renames - use temp to avoid collisions
    done = 0
    for dir_path in dirs:
        for cat in sorted(products_by_cat.keys()):
            products = products_by_cat[cat]
            images = get_sequential_images(dir_path, cat, max_count=len(products))
            n = min(len(products), len(images))

            for i in range(n):
                old_name = images[i]
                new_name = products[i] + ".jpg"
                old_path = dir_path / old_name
                new_path = dir_path / new_name
                if old_path.exists() and old_name != new_name:
                    # Use temp name to avoid overwriting
                    temp = dir_path / f"_tmp_rename_{old_path.stem}{old_path.suffix}"
                    old_path.rename(temp)
                    if new_path.exists():
                        new_path.unlink()
                    temp.rename(new_path)
                    done += 1
                    if done <= 10:
                        print(f"  {old_name} -> {new_name}")

    print(f"\nRenamed {done} files in total.")


if __name__ == "__main__":
    main()
