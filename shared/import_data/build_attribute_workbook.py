#!/usr/bin/env python3
"""
Generate an Export/Import ready workbook with attribute groups and attributes.

Usage:
    python3 shared/import_data/build_attribute_workbook.py

The script reads `shared/data/tags.csv` and produces
`shared/import_data/attributes_import.xlsx`.
"""

from __future__ import annotations

from pathlib import Path

from openpyxl import Workbook

try:
    from attribute_schema import ATTRIBUTE_DEFINITIONS, CHARACTERISTICS_GROUP
except ImportError:  # pragma: no cover - fallback for package execution
    from .attribute_schema import (  # type: ignore
        ATTRIBUTE_DEFINITIONS,
        CHARACTERISTICS_GROUP,
    )


def main() -> None:
    script_path = Path(__file__).resolve()
    output_path = script_path.with_name("attributes_import.xlsx")

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

    group_names = CHARACTERISTICS_GROUP["names"]
    ws_groups.append(
        [
            CHARACTERISTICS_GROUP["attribute_group_id"],
            CHARACTERISTICS_GROUP["sort_order"],
            group_names["en-gb"],
            group_names["uk-ua"],
            group_names["ru-ru"],
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

    attribute_id = 1

    for definition in ATTRIBUTE_DEFINITIONS:
        names = definition["names"]
        ws_attributes.append(
            [
                attribute_id,
                CHARACTERISTICS_GROUP["attribute_group_id"],
                definition["sort_order"],
                names["en-gb"],
                names["uk-ua"],
                names["ru-ru"],
            ]
        )
        attribute_id += 1

    workbook.save(output_path)
    print(
        f"Wrote {attribute_id - 1} attributes across 1 group to {output_path}"
    )


if __name__ == "__main__":
    main()
