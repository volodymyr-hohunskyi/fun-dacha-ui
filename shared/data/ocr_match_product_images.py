#!/usr/bin/env python3
"""
OCR-based product image matching for 4xx categories.
- Reads text from each image using EasyOCR
- Finds product with matching/similar name (configurable, default 75%)
- Renames image to product model (e.g. p401840.jpg)
- Updates products_import.xlsx image_name

Requires: pip install easyocr pillow rapidfuzz unidecode openpyxl

Options:
  --dry-run       Preview matches without renaming
  --limit N       Process only first N images (for testing)
  --min-similarity N  Match threshold 0-100 (default 75, OCR is noisy)

Run: python3 shared/data/ocr_match_product_images.py --dry-run
     python3 shared/data/ocr_match_product_images.py --limit 10
"""

from pathlib import Path
from unidecode import unidecode
from rapidfuzz import fuzz

CATALOG = Path(__file__).resolve().parent.parent.parent / "image" / "catalog" / "products"
PRODUCTS_IMPORT = Path(__file__).resolve().parent / "products_import.xlsx"


def normalize(s: str) -> str:
    """Normalize for comparison: unidecode, lowercase, collapse spaces."""
    if not s:
        return ""
    t = unidecode(s).lower().strip()
    return " ".join(t.split())


def load_4xx_products():
    """Load products in 4xx categories with model and name."""
    from openpyxl import load_workbook

    wb = load_workbook(PRODUCTS_IMPORT, read_only=True)
    ws = wb["Products"]
    h = [c.value for c in ws[1]]
    model_idx = h.index("model")
    pid_idx = h.index("product_id")
    name_col = "name(en-gb)"
    if name_col not in h:
        name_col = next((c for c in h if c and "name" in str(c).lower()), None)
    name_idx = h.index(name_col) if name_col else model_idx

    products = []
    for row in ws.iter_rows(min_row=2, values_only=True):
        m, name, pid = row[model_idx], row[name_idx], row[pid_idx]
        if not m:
            continue
        m = str(m).strip()
        if m.startswith("p40") and len(m) >= 6:
            try:
                cat = int(m[1:4])
                if 401 <= cat <= 499:
                    name = str(name or "").strip()
                    products.append(
                        {"model": m, "name": name, "name_norm": normalize(name), "product_id": pid}
                    )
            except (ValueError, TypeError):
                pass
    return products


def ocr_image(reader, path: Path) -> str:
    """Extract text from image, return normalized combined string."""
    try:
        result = reader.readtext(str(path))
        # Take high-confidence texts, combine
        texts = [r[1] for r in result if r[2] > 0.2]
        combined = " ".join(texts)
        return normalize(combined)
    except Exception as e:
        return f"__error:{e}"


def best_match(ocr_text: str, products: list, min_sim: int, img_category: int | None = None) -> dict | None:
    """Find product with best name match. If img_category set, only match same category (e.g. 401)."""
    if not ocr_text or ocr_text.startswith("__error"):
        return None
    best = None
    best_score = 0
    for p in products:
        if img_category is not None:
            try:
                p_cat = int(p["model"][1:4])
                if p_cat != img_category:
                    continue
            except (ValueError, TypeError):
                continue
        name_norm = p["name_norm"]
        if not name_norm:
            continue
        s1 = fuzz.ratio(ocr_text, name_norm)
        s2 = fuzz.partial_ratio(ocr_text, name_norm)
        s3 = fuzz.token_set_ratio(ocr_text, name_norm)
        s4 = fuzz.partial_token_set_ratio(ocr_text, name_norm)
        score = max(s1, s2, s3, s4)
        if score > best_score:
            best_score = score
            best = p
    if best_score >= min_sim:
        return {**best, "score": best_score}
    return None


def main():
    import sys
    import warnings
    warnings.filterwarnings("ignore", message=".*pin_memory.*")

    dry_run = "--dry-run" in sys.argv
    limit = None
    min_sim = 90  # Higher default - OCR matches were too loose
    args = sys.argv[1:]
    for i, a in enumerate(args):
        if a == "--limit" and i + 1 < len(args):
            limit = int(args[i + 1])
        elif a == "--min-similarity" and i + 1 < len(args):
            min_sim = int(args[i + 1])

    print("Loading 4xx products...")
    products = load_4xx_products()
    print(f"  {len(products)} products")

    print("Loading EasyOCR (may take a moment)...")
    import easyocr
    reader = easyocr.Reader(["en", "uk"], gpu=False)

    images = sorted(
        f
        for f in CATALOG.iterdir()
        if f.is_file() and f.suffix.lower() == ".jpg" and f.name.startswith("p40")
    )
    if limit:
        images = images[:limit]
        print(f"Processing {len(images)} 4xx images (--limit {limit})...")
    else:
        print(f"Processing {len(images)} 4xx images...")

    matched = []
    no_match = []
    already_correct = []

    for img_path in images:
        current_name = img_path.stem  # e.g. p401840
        try:
            img_cat = int(current_name[1:4])
        except (ValueError, TypeError):
            img_cat = None
        ocr_text = ocr_image(reader, img_path)
        hit = best_match(ocr_text, products, min_sim, img_category=img_cat)

        if hit:
            target_model = hit["model"]
            if current_name == target_model:
                already_correct.append((img_path.name, ocr_text[:60]))
            else:
                matched.append(
                    {
                        "from": img_path.name,
                        "to": target_model + ".jpg",
                        "product": hit["name"],
                        "score": hit["score"],
                        "ocr": ocr_text[:80],
                    }
                )
        else:
            no_match.append((img_path.name, ocr_text[:80]))

    print("\n=== ALREADY CORRECT (name matches product) ===")
    print(f"  {len(already_correct)} images")
    for name, ocr in already_correct[:5]:
        print(f"    {name} (ocr: {ocr!r}...)")

    print("\n=== MATCHED (will rename) ===")
    for m in matched[:15]:
        print(f"  {m['from']} -> {m['to']} ({m['score']}%)")
        print(f"    Product: {m['product']}")
        print(f"    OCR: {m['ocr'][:60]}...")
    if len(matched) > 15:
        print(f"  ... and {len(matched) - 15} more")
    print(f"  Total: {len(matched)}")

    print(f"\n=== NO MATCH (>={min_sim}%) ===")
    for name, ocr in no_match[:10]:
        print(f"  {name}: {ocr[:60]!r}...")
    if len(no_match) > 10:
        print(f"  ... and {len(no_match) - 10} more")
    print(f"  Total: {len(no_match)}")

    # Safeguard: reject if multiple images would map to same target (overwrites)
    targets = {}
    for m in matched:
        t = m["to"]
        if t not in targets:
            targets[t] = []
        targets[t].append(m["from"])
    conflicts = {t: srcs for t, srcs in targets.items() if len(srcs) > 1}
    if conflicts:
        print("\n=== CONFLICTS (skipping - multiple images -> same product) ===")
        for t, srcs in list(conflicts.items())[:10]:
            print(f"  {t}: {srcs}")
        if len(conflicts) > 10:
            print(f"  ... and {len(conflicts) - 10} more")
        matched = [m for m in matched if targets.get(m["to"], [m["from"]]) == [m["from"]]]
        print(f"  After removing conflicts: {len(matched)} safe renames")

    if dry_run or not matched:
        print("\n[DRY RUN or no matches - no changes made]")
        return

    # Rename files and update xlsx
    from openpyxl import load_workbook

    wb = load_workbook(PRODUCTS_IMPORT)
    ws = wb["Products"]
    h = [c.value for c in ws[1]]
    model_idx = h.index("model")
    img_idx = h.index("image_name")

    for m in matched:
        old_path = CATALOG / m["from"]
        new_path = CATALOG / m["to"]
        if old_path.exists():
            if new_path.exists() and new_path != old_path:
                new_path.unlink()
            old_path.rename(new_path)
            print(f"  Renamed: {m['from']} -> {m['to']}")

        # Update xlsx: set image_name for this product
        for row_idx in range(2, ws.max_row + 1):
            if ws.cell(row_idx, model_idx + 1).value == m["to"][:-4]:
                ws.cell(row_idx, img_idx + 1).value = f"catalog/products/{m['to']}"
                break

    wb.save(PRODUCTS_IMPORT)
    print(f"\nUpdated {len(matched)} images and products_import.xlsx")


if __name__ == "__main__":
    main()
