#!/usr/bin/env python3
"""
Normalize flat ProductAttributes column into OpenCart-ready worksheets.

Reads products_import.xlsx with a flat ProductAttributes column like:
    Колір: Жовтий, Оранжевий
    Ріст: Високорослий, Розлогий

Produces:
  - ProductAttributes worksheet (one row per product+attribute, comma-joined text for display)
  - ProductFilters worksheet (one row per product+filter value for multi-select filtering)
  - FilterGroups and Filters worksheets (if --include-taxonomy)

Usage:
    python3 shared/data/normalize_flat_attributes.py
    python3 shared/data/normalize_flat_attributes.py --input /path/to/products_import.xlsx --output /path/to/output.xlsx
"""

from __future__ import annotations

import argparse
import re
from pathlib import Path
from typing import Dict, List, Tuple

from openpyxl import Workbook, load_workbook

try:
    from attribute_schema import (
        ATTRIBUTE_DEFINITIONS,
        ATTRIBUTE_LOOKUP,
        CHARACTERISTICS_GROUP,
        FILTER_VALUE_TAXONOMY,
        SUPPORTED_LANGUAGES,
        normalize_filter_value,
    )
except ImportError:
    from .attribute_schema import (
        ATTRIBUTE_DEFINITIONS,
        ATTRIBUTE_LOOKUP,
        CHARACTERISTICS_GROUP,
        FILTER_VALUE_TAXONOMY,
        SUPPORTED_LANGUAGES,
        normalize_filter_value,
    )


DEFAULT_INPUT = Path(__file__).resolve().with_name("products_import.xlsx")
DEFAULT_OUTPUT = Path(__file__).resolve().with_name("products_import_normalized.xlsx")

# Column names to look for in source sheet (first matching wins)
PRODUCT_ID_COLUMNS = ("product_id", "ProductID", "productid", "ID")
PRODUCT_ATTRIBUTES_COLUMNS = ("ProductAttributes", "product_attributes", "productattributes")

# Default sheet containing ProductID + ProductAttributes
DEFAULT_SOURCE_SHEET = "Products"
TARGET_LANGUAGE = "uk-ua"
ATTRIBUTE_GROUP_NAME = CHARACTERISTICS_GROUP["names"][TARGET_LANGUAGE]


def split_values(raw: str | None) -> List[str]:
    """Split by comma, trim, drop empties."""
    if not raw:
        return []
    parts = [p.strip() for p in str(raw).split(",")]
    return [p for p in parts if p]


def parse_attributes_cell(cell_value: str | None) -> List[Tuple[str, List[str]]]:
    """
    Parse flat attribute string into (group_name, [values]) pairs.

    Format: "GroupName: value1, value2" or multiple lines separated by newline.
    Example: "Колір: Жовтий, Оранжевий\\nРіст: Високорослий, Розлогий"
    """
    if not cell_value:
        return []
    text = str(cell_value).strip()
    if not text:
        return []

    result: List[Tuple[str, List[str]]] = []
    lines = [ln.strip() for ln in text.split("\n") if ln.strip()]

    for line in lines:
        if ":" not in line:
            continue
        part = line.split(":", 1)
        group_raw = (part[0] or "").strip()
        values_raw = (part[1] or "").strip()
        if not group_raw:
            continue

        # Resolve group name via schema (uk-ua)
        defn = ATTRIBUTE_LOOKUP.get(group_raw.lower())
        if defn:
            group_name = defn["names"].get(TARGET_LANGUAGE, defn["names"]["uk-ua"])
        else:
            group_name = group_raw

        values = split_values(values_raw)
        if values:
            result.append((group_name, values))

    return result


def find_column_index(row_values: tuple, candidates: Tuple[str, ...]) -> int:
    """Return 1-based column index of first matching header, or 0."""
    for i, val in enumerate(row_values, start=1):
        v = str(val or "").strip()
        for c in candidates:
            if v and v.lower() == c.lower():
                return i
    return 0


def load_flat_attributes(
    wb_path: Path,
    sheet_name: str = DEFAULT_SOURCE_SHEET,
) -> List[Tuple[int, List[Tuple[str, List[str]]]]]:
    """
    Load (product_id, parsed_attributes) from workbook.
    """
    wb = load_workbook(wb_path, read_only=True, data_only=True)
    if sheet_name not in wb.sheetnames:
        raise SystemExit(f"Sheet '{sheet_name}' not found. Available: {wb.sheetnames}")

    ws = wb[sheet_name]
    rows = list(ws.iter_rows(min_row=1, values_only=True))
    wb.close()

    if not rows:
        raise SystemExit(f"Sheet '{sheet_name}' is empty")

    header = rows[0]
    pid_col = find_column_index(header, PRODUCT_ID_COLUMNS)
    attr_col = find_column_index(header, PRODUCT_ATTRIBUTES_COLUMNS)

    if not pid_col:
        raise SystemExit(
            f"Product ID column not found. Expected one of: {PRODUCT_ID_COLUMNS}"
        )
    if not attr_col:
        raise SystemExit(
            f"ProductAttributes column not found. Expected one of: {PRODUCT_ATTRIBUTES_COLUMNS}"
        )

    out: List[Tuple[int, List[Tuple[str, List[str]]]]] = []
    for row in rows[1:]:
        pid_val = row[pid_col - 1] if pid_col <= len(row) else None
        attr_val = row[attr_col - 1] if attr_col <= len(row) else None

        try:
            pid = int(pid_val) if pid_val is not None else None
        except (TypeError, ValueError):
            pid = None

        if pid is None:
            continue

        parsed = parse_attributes_cell(attr_val)
        if parsed:
            out.append((pid, parsed))

    return out


def build_normalized_output(
    data: List[Tuple[int, List[Tuple[str, List[str]]]]],
) -> Tuple[
    List[Tuple[int, str, str]],
    List[Tuple[int, str, str]],
    Dict[str, List[str]],
]:
    """
    Returns:
      - pa_rows: (product_id, attribute_name, text) for ProductAttributes
      - pf_rows: (product_id, filter_group, filter) for ProductFilters
      - taxonomy_seen: {group: [values]} for discovered values
    """
    pa_rows: List[Tuple[int, str, str]] = []
    pf_rows: List[Tuple[int, str, str]] = []
    taxonomy_seen: Dict[str, List[str]] = {}

    for product_id, groups in data:
        for group_name, values in groups:
            values_clean = [v.strip() for v in values if v.strip()]
            if not values_clean:
                continue

            # ProductAttributes: one row per product+attribute, text = comma-joined
            text_display = ", ".join(dict.fromkeys(values_clean))
            pa_rows.append((product_id, group_name, text_display))

            # ProductFilters: one row per product per value
            canon_values = FILTER_VALUE_TAXONOMY.get(group_name)
            for v in values_clean:
                if canon_values:
                    nv = normalize_filter_value(group_name, v)
                    if nv:
                        pf_rows.append((product_id, group_name, nv))
                        taxonomy_seen.setdefault(group_name, [])
                        if nv not in taxonomy_seen[group_name]:
                            taxonomy_seen[group_name].append(nv)
                else:
                    pf_rows.append((product_id, group_name, v))
                    taxonomy_seen.setdefault(group_name, [])
                    if v not in taxonomy_seen[group_name]:
                        taxonomy_seen[group_name].append(v)

    return pa_rows, pf_rows, taxonomy_seen


def write_opencart_workbook(
    pa_rows: List[Tuple[int, str, str]],
    pf_rows: List[Tuple[int, str, str]],
    taxonomy_seen: Dict[str, List[str]],
    output_path: Path,
    include_taxonomy: bool = True,
) -> None:
    """Write OpenCart-compatible Excel with ProductAttributes, ProductFilters, optionally FilterGroups/Filters."""
    wb = Workbook()
    wb.remove(wb.active)

    # ProductAttributes: product_id | attribute_group | attribute | text(en-gb) | text(uk-ua) | text(ru-ru)
    ws_pa = wb.create_sheet("ProductAttributes", 0)
    ws_pa.append(
        [
            "product_id",
            "attribute_group",
            "attribute",
            "text(en-gb)",
            "text(uk-ua)",
            "text(ru-ru)",
        ]
    )
    for product_id, attribute_name, text in pa_rows:
        ws_pa.append(
            (
                product_id,
                ATTRIBUTE_GROUP_NAME,
                attribute_name,
                text,
                text,
                text,
            )
        )

    # NormalizedAttributes: ProductID | AttributeGroup | AttributeValue (Step 1 canonical format)
    ws_na = wb.create_sheet("NormalizedAttributes", 1)
    ws_na.append(["ProductID", "AttributeGroup", "AttributeValue"])
    for product_id, filter_group, filter_name in pf_rows:
        ws_na.append((product_id, filter_group, filter_name))

    # ProductFilters: product_id | filter_group | filter (OpenCart import format)
    ws_pf = wb.create_sheet("ProductFilters", 2)
    ws_pf.append(["product_id", "filter_group", "filter"])
    for product_id, filter_group, filter_name in pf_rows:
        ws_pf.append((product_id, filter_group, filter_name))

    if include_taxonomy:
        # Merge canonical taxonomy with discovered values (discovered extend canonical)
        all_values: Dict[str, List[str]] = {}
        for group, canon_vals in FILTER_VALUE_TAXONOMY.items():
            all_values[group] = list(canon_vals)
        for group, discovered in taxonomy_seen.items():
            for v in discovered:
                if v not in all_values.setdefault(group, []):
                    all_values[group].append(v)

        group_to_id: Dict[str, int] = {}
        group_sort: Dict[str, int] = {}
        for idx, defn in enumerate(ATTRIBUTE_DEFINITIONS):
            name_ua = defn["names"].get("uk-ua", defn["names"]["en-gb"])
            if name_ua in all_values:
                gid = len(group_to_id) + 1
                group_to_id[name_ua] = gid
                group_sort[name_ua] = defn.get("sort_order", idx)
        for name_ua in taxonomy_seen:
            if name_ua not in group_to_id:
                gid = len(group_to_id) + 1
                group_to_id[name_ua] = gid
                group_sort[name_ua] = 99

        if group_to_id:
            ws_fg = wb.create_sheet("FilterGroups", 3)
            ws_fg.append(
                [
                    "filter_group_id",
                    "sort_order",
                    "name(en-gb)",
                    "name(uk-ua)",
                    "name(ru-ru)",
                ]
            )
            ws_f = wb.create_sheet("Filters", 4)
            ws_f.append(
                [
                    "filter_id",
                    "filter_group_id",
                    "sort_order",
                    "name(en-gb)",
                    "name(uk-ua)",
                    "name(ru-ru)",
                ]
            )
            for name_ua, sort_order in sorted(
                group_sort.items(), key=lambda x: (x[1], x[0])
            ):
                gid = group_to_id[name_ua]
                defn = next(
                    (
                        d
                        for d in ATTRIBUTE_DEFINITIONS
                        if d["names"].get("uk-ua") == name_ua
                    ),
                    None,
                )
                en = defn["names"]["en-gb"] if defn else name_ua
                ru = defn["names"].get("ru-ru", name_ua) if defn else name_ua
                ws_fg.append((gid, sort_order, en, name_ua, ru))

            filter_id = 1
            for name_ua in sorted(
                group_to_id.keys(), key=lambda x: (group_sort.get(x, 99), x)
            ):
                gid = group_to_id[name_ua]
                for sort_idx, val in enumerate(
                    all_values.get(name_ua, []), start=1
                ):
                    ws_f.append((filter_id, gid, sort_idx, val, val, val))
                    filter_id += 1

    wb.save(output_path)


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Normalize flat ProductAttributes into OpenCart worksheets"
    )
    parser.add_argument(
        "--input",
        "-i",
        type=Path,
        default=DEFAULT_INPUT,
        help="Input Excel file with ProductID and ProductAttributes columns",
    )
    parser.add_argument(
        "--output",
        "-o",
        type=Path,
        default=DEFAULT_OUTPUT,
        help="Output Excel file with normalized ProductAttributes and ProductFilters",
    )
    parser.add_argument(
        "--sheet",
        "-s",
        default=DEFAULT_SOURCE_SHEET,
        help="Source sheet name containing ProductID and ProductAttributes",
    )
    parser.add_argument(
        "--no-taxonomy",
        action="store_true",
        help="Skip generating FilterGroups and Filters worksheets",
    )
    args = parser.parse_args()

    if not args.input.exists():
        raise SystemExit(f"Input file not found: {args.input}")

    data = load_flat_attributes(args.input, args.sheet)
    pa_rows, pf_rows, taxonomy_seen = build_normalized_output(data)

    write_opencart_workbook(
        pa_rows,
        pf_rows,
        taxonomy_seen,
        args.output,
        include_taxonomy=not args.no_taxonomy,
    )

    print(f"Read {len(data)} products with attributes")
    print(f"ProductAttributes rows: {len(pa_rows)}")
    print(f"ProductFilters rows: {len(pf_rows)}")
    print(f"Filter groups discovered: {list(taxonomy_seen.keys())}")
    print(f"Output: {args.output}")


if __name__ == "__main__":
    main()
