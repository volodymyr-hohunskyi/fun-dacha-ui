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
