#!/usr/bin/env python3
"""
Normalize ProductAttributes so attribute names align with tag-based attributes.

Reads shared/import_data/products_import.xlsx and rewrites the ProductAttributes
worksheet so that:
  - Each comma-separated value becomes its own row
  - `attribute` column matches the English label (e.g. 'Tall', 'Red')
  - Text columns remain translated on a per-value basis

Run after editing the workbook if attribute groups/values change:

    python3 shared/import_data/normalize_product_attributes.py
"""

from __future__ import annotations

from pathlib import Path
from typing import Dict, List

from openpyxl import load_workbook

WORKBOOK_PATH = Path(__file__).with_name("products_import.xlsx")
ATTR_WORKBOOK_PATH = Path(__file__).with_name("attributes_import.xlsx")
SHEET_NAME = "ProductAttributes"
TARGET_LANGUAGE = "uk-ua"  # must match store default language


def split_values(raw: str | None) -> List[str]:
    if not raw:
        return []
    parts = [part.strip() for part in str(raw).split(",")]
    return [part for part in parts if part]


def pad(values: List[str], target: int, fallback: str = "") -> List[str]:
    if not values:
        return [fallback] * target
    if len(values) >= target:
        return values[:target]
    return values + [fallback] * (target - len(values))


def load_localized_names() -> tuple[Dict[str, str], Dict[str, str]]:
    if not ATTR_WORKBOOK_PATH.exists():
        raise SystemExit(
            "Run build_attribute_workbook.py before normalizing product attributes."
        )

    wb = load_workbook(ATTR_WORKBOOK_PATH, data_only=True)
    group_ws = wb["AttributeGroups"]
    attr_ws = wb["Attributes"]

    group_map: Dict[str, str] = {}
    for row in group_ws.iter_rows(min_row=2, values_only=True):
        _, _, name_en, name_ua, name_ru = row
        if not name_en:
            continue
        if TARGET_LANGUAGE == "uk-ua":
            group_map[name_en] = name_ua or name_en
        elif TARGET_LANGUAGE == "ru-ru":
            group_map[name_en] = name_ru or name_en
        else:
            group_map[name_en] = name_en

    attr_map: Dict[str, str] = {}
    for row in attr_ws.iter_rows(min_row=2, values_only=True):
        _, _, _, name_en, name_ua, name_ru = row
        if not name_en:
            continue
        if TARGET_LANGUAGE == "uk-ua":
            attr_map[name_en] = name_ua or name_en
        elif TARGET_LANGUAGE == "ru-ru":
            attr_map[name_en] = name_ru or name_en
        else:
            attr_map[name_en] = name_en

    return group_map, attr_map


def main() -> None:
    if not WORKBOOK_PATH.exists():
        raise SystemExit(f"Workbook not found: {WORKBOOK_PATH}")

    group_names, attribute_names = load_localized_names()

    wb = load_workbook(WORKBOOK_PATH)
    if SHEET_NAME not in wb.sheetnames:
        raise SystemExit(f"Worksheet '{SHEET_NAME}' missing in workbook")

    ws = wb[SHEET_NAME]
    rows = list(ws.iter_rows(min_row=2, values_only=True))

    normalized_rows = []
    for product_id, group, attribute, text_en, text_ua, text_ru in rows:
        if product_id is None:
            continue
        group = (group or "").strip()
        if not group:
            continue
        en_tokens = split_values(text_en) or [(text_en or "").strip()]
        ua_tokens = split_values(text_ua)
        ru_tokens = split_values(text_ru)

        ua_tokens = pad(ua_tokens, len(en_tokens), (text_ua or "").strip())
        ru_tokens = pad(ru_tokens, len(en_tokens), (text_ru or "").strip())

        for idx, en_value in enumerate(en_tokens):
            if not en_value:
                continue
            localized_group = group_names.get(group, group)
            localized_attr = attribute_names.get(en_value, en_value)
            normalized_rows.append(
                (
                    product_id,
                    localized_group,
                    localized_attr,
                    en_value,
                    ua_tokens[idx],
                    ru_tokens[idx],
                )
            )

    # clear existing rows (except header)
    ws.delete_rows(2, ws.max_row)

    for row in normalized_rows:
        ws.append(row)

    wb.save(WORKBOOK_PATH)
    print(
        f"Normalized {len(rows)} source rows into "
        f"{len(normalized_rows)} attribute rows"
    )


if __name__ == "__main__":
    main()
