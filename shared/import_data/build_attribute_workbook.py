#!/usr/bin/env python3
"""
Generate an Export/Import ready workbook with attribute groups and attributes.

Usage:
    python3 shared/import_data/build_attribute_workbook.py

The script reads `shared/data/tags.csv` and produces
`shared/import_data/attributes_import.xlsx`.
"""

from __future__ import annotations

import csv
from collections import OrderedDict, defaultdict
from pathlib import Path
from typing import Dict

from openpyxl import Workbook

LANGUAGE_HEADERS = ("en-gb", "uk-ua", "ru-ru")

GROUP_TRANSLATIONS: Dict[str, Dict[str, str]] = {
    "Color": {"uk-ua": "Колір", "ru-ru": "Цвет"},
    "Cultivation": {"uk-ua": "Вирощування", "ru-ru": "Выращивание"},
    "Growth": {"uk-ua": "Ріст", "ru-ru": "Рост"},
    "Maturity": {"uk-ua": "Стиглість", "ru-ru": "Спелость"},
    "Resistance": {"uk-ua": "Стійкість", "ru-ru": "Устойчивость"},
    "Shape": {"uk-ua": "Форма", "ru-ru": "Форма"},
    "Texture": {"uk-ua": "Текстура", "ru-ru": "Текстура"},
    "Traits": {"uk-ua": "Особливості", "ru-ru": "Особенности"},
    "Usage": {"uk-ua": "Призначення", "ru-ru": "Назначение"},
    "Yield": {"uk-ua": "Врожайність", "ru-ru": "Урожайность"},
}


def slug_to_label(value: str) -> str:
    """Convert a slug-ish key to a readable English label."""
    cleaned = value.replace("_", " ").replace("-", " ").strip()
    if not cleaned:
        return value
    parts = cleaned.split()
    title_cased = []
    for part in parts:
        if part.isupper() or part.isdigit():
            title_cased.append(part)
        else:
            title_cased.append(part.capitalize())
    return " ".join(title_cased)


def main() -> None:
    script_path = Path(__file__).resolve()
    shared_dir = script_path.parents[1]
    tags_csv = shared_dir / "data" / "tags.csv"
    output_path = script_path.with_name("attributes_import.xlsx")

    if not tags_csv.exists():
        raise SystemExit(f"Missing source csv: {tags_csv}")

    groups: OrderedDict[str, None] = OrderedDict()
    attribute_rows = []
    seen_keys = set()

    with tags_csv.open(encoding="utf-8", newline="") as handle:
        reader = csv.DictReader(handle)
        for row in reader:
            group = (row.get("group") or "").strip()
            key = (row.get("key") or "").strip()
            if not group or not key:
                continue
            dedupe_key = (group.lower(), key.lower())
            if dedupe_key in seen_keys:
                continue
            seen_keys.add(dedupe_key)
            groups.setdefault(group, None)
            attribute_rows.append(
                {
                    "group": group,
                    "key": key,
                    "ua": (row.get("ua") or "").strip(),
                    "ru": (row.get("ru") or "").strip(),
                }
            )

    if not attribute_rows:
        raise SystemExit("No attribute rows were derived from tags.csv")

    group_ids = {group: idx for idx, group in enumerate(groups.keys(), start=1)}

    workbook = Workbook()
    ws_groups = workbook.active
    ws_groups.title = "AttributeGroups"
    ws_groups.append(
        [
            "attribute_group_id",
            "sort_order",
            "name(en-gb)",
            "name(uk-ua)",
            "name(ru-ru)",
        ]
    )

    for group, group_id in group_ids.items():
        translations = GROUP_TRANSLATIONS.get(group, {})
        ws_groups.append(
            [
                group_id,
                group_id - 1,
                group,
                translations.get("uk-ua", group),
                translations.get("ru-ru", group),
            ]
        )

    ws_attributes = workbook.create_sheet("Attributes")
    ws_attributes.append(
        [
            "attribute_id",
            "attribute_group_id",
            "sort_order",
            "name(en-gb)",
            "name(uk-ua)",
            "name(ru-ru)",
        ]
    )

    per_group_sort = defaultdict(int)
    attribute_id = 1

    for row in attribute_rows:
        group = row["group"]
        group_id = group_ids[group]
        sort_order = per_group_sort[group]
        per_group_sort[group] += 1

        name_en = slug_to_label(row["key"])
        name_ua = row["ua"] or name_en
        name_ru = row["ru"] or name_en

        ws_attributes.append(
            [
                attribute_id,
                group_id,
                sort_order,
                name_en,
                name_ua,
                name_ru,
            ]
        )
        attribute_id += 1

    workbook.save(output_path)
    print(
        f"Wrote {attribute_id - 1} attributes across "
        f"{len(group_ids)} groups to {output_path}"
    )


if __name__ == "__main__":
    main()
