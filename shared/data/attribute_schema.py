#!/usr/bin/env python3
"""Shared attribute schema for attribute workbook generation and normalization."""

from __future__ import annotations

SUPPORTED_LANGUAGES = ("en-gb", "uk-ua", "ru-ru")

CHARACTERISTICS_GROUP = {
    "attribute_group_id": 1,
    "sort_order": 0,
    "names": {
        "en-gb": "Characteristics",
        "uk-ua": "Характеристики",
        "ru-ru": "Характеристики",
    },
}

ATTRIBUTE_DEFINITIONS = [
    {
        "key": "Color",
        "sort_order": 0,
        "names": {"en-gb": "Color", "uk-ua": "Колір", "ru-ru": "Цвет"},
    },
    {
        "key": "Cultivation",
        "sort_order": 1,
        "names": {"en-gb": "Cultivation", "uk-ua": "Вирощування", "ru-ru": "Выращивание"},
    },
    {
        "key": "Growth",
        "sort_order": 2,
        "names": {"en-gb": "Growth", "uk-ua": "Ріст", "ru-ru": "Рост"},
    },
    {
        "key": "Maturity",
        "sort_order": 3,
        "names": {"en-gb": "Maturity", "uk-ua": "Стиглість", "ru-ru": "Спелость"},
    },
    {
        "key": "Resistance",
        "sort_order": 4,
        "names": {"en-gb": "Resistance", "uk-ua": "Стійкість", "ru-ru": "Устойчивость"},
    },
    {
        "key": "Shape",
        "sort_order": 5,
        "names": {"en-gb": "Shape", "uk-ua": "Форма", "ru-ru": "Форма"},
    },
    {
        "key": "Texture",
        "sort_order": 6,
        "names": {"en-gb": "Texture", "uk-ua": "Текстура", "ru-ru": "Текстура"},
    },
    {
        "key": "Traits",
        "sort_order": 7,
        "names": {"en-gb": "Traits", "uk-ua": "Особливості", "ru-ru": "Особенности"},
    },
    {
        "key": "Usage",
        "sort_order": 8,
        "names": {"en-gb": "Usage", "uk-ua": "Призначення", "ru-ru": "Назначение"},
    },
    {
        "key": "Yield",
        "sort_order": 9,
        "names": {"en-gb": "Yield", "uk-ua": "Врожайність", "ru-ru": "Урожайность"},
    },
]


def build_attribute_lookup():
    """Return dict keyed by lowercase localized name mapping to definition."""
    lookup = {}
    for definition in ATTRIBUTE_DEFINITIONS:
        for name in definition["names"].values():
            lookup[name.lower()] = definition
        lookup[definition["key"].lower()] = definition
    return lookup


ATTRIBUTE_LOOKUP = build_attribute_lookup()


# --- Filter value taxonomies for seed e-commerce (uk-ua primary) ---
# Each group maps to canonical filter values. Used for FilterGroups/Filters import
# and for normalizing raw attribute text into consistent filter assignments.
FILTER_VALUE_TAXONOMY = {
    "Колір": [
        "Жовтий",
        "Оранжевий",
        "Рожевий",
        "Малиновий",
        "Червоний",
        "Коричневий",
        "Чорний",
        "Білий",
        "Зелений",
        "Фіолетовий",
        "Різнокольоровий",
    ],
    "Вирощування": [
        "Відкритий ґрунт",
        "Теплиця",
        "Потребує підв'язки",
        "Підв'язка не потрібна",
        "Контейнер",
        "Балкон",
    ],
    "Ріст": [
        "Високорослий",
        "Розлогий",
        "Детермінантний",
        "Індетермінантний",
        "Низькорослий",
        "Середньорослий",
    ],
    "Стиглість": [
        "Рання",
        "Середньорання",
        "Середня",
        "Середньопізня",
        "Пізня",
    ],
    "Стійкість": [
        "До холоду",
        "До посухи",
        "До хвороб",
        "До шкідників",
    ],
    "Форма": [
        "Кругла",
        "Овальна",
        "Витягнута",
        "Серцеподібна",
        "Плоска",
    ],
    "Текстура": [
        "Гладка",
        "Ребриста",
        "М'ясиста",
    ],
    "Особливості": [
        "Ароматна",
        "Солодка",
        "Кисла",
        "Хрустка",
    ],
    "Призначення": [
        "Салати",
        "Консервування",
        "Сушіння",
        "Універсальне",
        "Свіжий вжиток",
    ],
    "Врожайність": [
        "Висока",
        "Середня",
        "Низька",
    ],
}


def normalize_filter_value(group_ua: str, raw_value: str) -> str | None:
    """
    Map raw attribute text to canonical taxonomy value, or return None if no match.
    Uses case-insensitive strip and optional alias mapping.
    """
    raw = (raw_value or "").strip()
    if not raw:
        return None
    raw_lower = raw.lower()
    values = FILTER_VALUE_TAXONOMY.get(group_ua)
    if not values:
        return raw  # pass through if group unknown
    for canon in values:
        if canon.lower() == raw_lower:
            return canon
    return raw  # return as-is if not in taxonomy (allow new values)
