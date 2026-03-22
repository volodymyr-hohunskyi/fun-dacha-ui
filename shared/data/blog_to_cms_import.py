#!/usr/bin/env python3
"""
Generate blog import Excel from shared/blog/blog_articles.json.
Maps oc_blog data to CMS article format. Distributes articles across existing topics.
Creates Topics and Articles sheets for import.

Usage:
    cd /workspace
    python3 shared/data/blog_to_cms_import.py

Output: shared/data/blog_import.xlsx
"""

from __future__ import annotations

import json
import re
from pathlib import Path

try:
    import openpyxl
except ImportError:
    print("Installing openpyxl...")
    import subprocess
    subprocess.check_call(["pip", "install", "openpyxl", "-q"])
    import openpyxl

# Existing topics - distribute articles across these
TOPICS = [
    "Календар посадок (по місяцях)",
    "Гіди по вирощуванню культур",
    "Порівняння сортів",
    "Покрокові інструкції (посів, пересадка, збір врожаю)",
    "Сезонні поради",
]

# Keywords to help assign articles to topics (topic_index: list of keywords)
TOPIC_KEYWORDS = {
    0: ["календар", "місяць", "лютий", "березень", "квітень", "посів", "погодник", "луна", "календарний"],
    1: ["вирощування", "гід", "культур", "огірок", "томат", "перець", "горох", "селер", "салат", "теплич"],
    2: ["порівняння", "сорт", "гібрид", "різниця", "вибір сорту"],
    3: ["покроков", "інструкція", "посів", "пересадка", "збір", "пасинкування", "підготовк", "як"],
    4: ["сезон", "порада", "зберігання", "зим", "осін", "весн", "літо", "сад", "декор"],
}


def sanitize_for_excel(s: str) -> str:
    """Escape quotes and control chars for Excel."""
    if not s:
        return ""
    s = str(s).replace("\x00", "")
    return s[:32767] if len(s) > 32767 else s


def guess_topic_index(title: str, description: str) -> int:
    """Guess best topic based on title and description content."""
    text = (title + " " + description).lower()
    scores = [0] * len(TOPICS)
    for idx, keywords in TOPIC_KEYWORDS.items():
        for kw in keywords:
            if kw in text:
                scores[idx] += 1
    if max(scores) > 0:
        return scores.index(max(scores))
    return 0  # default


def slug_from_title(title: str) -> str:
    """Generate URL-safe slug from title."""
    s = title[:80].lower()
    s = re.sub(r"[^\w\s-]", "", s)
    s = re.sub(r"[-\s]+", "-", s).strip("-")
    return s or "article"


def main() -> None:
    script_dir = Path(__file__).resolve().parent
    blog_path = script_dir.parent / "blog" / "blog_articles.json"
    output_path = script_dir / "blog_import.xlsx"

    if not blog_path.exists():
        print(f"Error: {blog_path} not found. Run from workspace root or ensure shared/blog exists.")
        return

    with open(blog_path, "r", encoding="utf-8") as f:
        data = json.load(f)

    blogs = data.get("oc_blog", [])
    descriptions = {d["blog_id"]: d for d in data.get("oc_blog_description", [])}
    stores = {}
    for row in data.get("oc_blog_to_store", []):
        stores.setdefault(row["blog_id"], []).append(row["store_id"])
    seo_urls = {}  # query like article_id=123 -> keyword
    for row in data.get("oc_seo_url", []):
        q = row.get("query", "")
        if "article_id" in q or "blog" in q:
            seo_urls[row.get("store_id", 0)] = row.get("keyword", "")

    wb = openpyxl.Workbook()

    # ----- Topics sheet -----
    ws_topics = wb.active
    ws_topics.title = "Topics"
    ws_topics.append([
        "topic_id", "name(uk-ua)", "sort_order", "status", "store_id", "language_code"
    ])
    for i, name in enumerate(TOPICS):
        ws_topics.append([i + 1, name, 0, 1, 0, "uk-ua"])

    # ----- Articles sheet -----
    ws_articles = wb.create_sheet("Articles")
    ws_articles.append([
        "article_id", "topic_name", "name", "description", "image", "author", "status",
        "store_id", "language_id", "meta_title", "meta_description", "meta_keyword", "tag",
        "date_added", "seo_keyword"
    ])

    for blog in blogs:
        bid = blog["blog_id"]
        desc = descriptions.get(bid, {})
        topic_idx = guess_topic_index(
            desc.get("title", ""),
            desc.get("description", ""),
        )
        topic_name = TOPICS[topic_idx]
        store_ids = stores.get(bid, [0])
        store_id = store_ids[0] if store_ids else 0

        name = desc.get("title", "")[:255] or f"Article {bid}"
        description = desc.get("description", "")
        image = blog.get("image", "")
        if image and not image.startswith("catalog/"):
            image = "catalog/blog/" + Path(image).name if "blog" in image else image

        author = "Гогунська Людмила" if blog.get("author_id") == 0 else ""
        status = 1 if blog.get("status", 1) else 0
        date_added = blog.get("date_added", "")

        meta_title = desc.get("meta_title", "")[:255]
        meta_desc = desc.get("meta_description", "")[:500]
        meta_kw = desc.get("meta_keyword", "")[:255]
        tag = desc.get("tag", "")[:255]
        lang_id = desc.get("language_id", 1)

        seo_kw = ""
        for row in data.get("oc_seo_url", []):
            q = str(row.get("query", ""))
            if f"blog_id={bid}" in q:
                seo_kw = row.get("keyword", "")
                break
        if not seo_kw:
            seo_kw = f"blog-post-{bid}" if bid <= 31 else slug_from_title(name) + f"-{bid}"

        ws_articles.append([
            "",  # article_id empty = new
            sanitize_for_excel(topic_name),
            sanitize_for_excel(name),
            sanitize_for_excel(description),
            sanitize_for_excel(image),
            sanitize_for_excel(author),
            status,
            store_id,
            lang_id,
            sanitize_for_excel(meta_title),
            sanitize_for_excel(meta_desc),
            sanitize_for_excel(meta_kw),
            sanitize_for_excel(tag),
            date_added,
            sanitize_for_excel(seo_kw),
        ])

    wb.save(output_path)
    print(f"Created {output_path}")
    print(f"  Topics: {len(TOPICS)}")
    print(f"  Articles: {len(blogs)}")


if __name__ == "__main__":
    main()
