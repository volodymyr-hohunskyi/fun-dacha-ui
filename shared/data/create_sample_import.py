#!/usr/bin/env python3
"""
Create a sample products_import_sample.xlsx with flat ProductAttributes for testing normalization.

Does NOT overwrite products_import.xlsx (which may contain real Products, Categories, etc.).

Usage:
    python3 shared/data/create_sample_import.py
"""

from __future__ import annotations

from pathlib import Path

from openpyxl import Workbook

OUTPUT_PATH = Path(__file__).resolve().with_name("products_import_sample.xlsx")

SAMPLE_DATA = [
    (101, "Колір: Жовтий, Оранжевий"),
    (102, "Колір: Червоний, Жовтий\nРіст: Високорослий, Розлогий"),
    (103, "Колір: Рожевий, Малиновий\nВирощування: Відкритий ґрунт, Потребує підв'язки"),
    (104, "Ріст: Детермінантний, Індетермінантний\nВирощування: Теплиця"),
    (105, "Колір: Жовтий\nРіст: Високорослий\nСтиглість: Рання, Середня"),
]


def main() -> None:
    wb = Workbook()
    ws = wb.active
    ws.title = "Products"
    ws.append(["product_id", "name", "model", "ProductAttributes"])
    for pid, attrs in SAMPLE_DATA:
        ws.append([pid, f"Product {pid}", f"MOD-{pid}", attrs])
    wb.save(OUTPUT_PATH)
    print(f"Created sample file: {OUTPUT_PATH} (use --input {OUTPUT_PATH} for normalize_flat_attributes.py)")


if __name__ == "__main__":
    main()
