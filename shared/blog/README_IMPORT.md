# Blog Import - Topics & Articles

Import blog articles from `blog_articles.json` into the CMS (oc_article, oc_topic).

## 1. Generate Excel

```bash
cd /workspace
python3 shared/data/blog_to_cms_import.py
```

Creates: `shared/data/blog_import.xlsx` with two sheets:
- **Topics**: The 5 existing topics (for reference; import uses existing DB topics)
- **Articles**: 31 articles distributed across topics by content

## 2. Import to Database

### Option A: Admin controller (recommended)

1. Log in to Admin
2. Visit: `https://yoursite.com/adminpage/index.php?route=tool/blog_import.import&user_token=YOUR_TOKEN`
3. The script imports articles and returns JSON with success/errors

### Option B: Export/Import plugin

The standard Export/Import plugin does **not** support Topics or Articles worksheets.
Use Option A (admin controller) instead.

## Topic mapping

Articles are distributed across these 5 topics by keyword matching:

| Topic | Keywords |
|-------|----------|
| Календар посадок (по місяцях) | календар, місяць, посів, погодник, луна |
| Гіди по вирощуванню культур | вирощування, огірок, томат, горох, теплич |
| Порівняння сортів | порівняння, сорт, гібрид |
| Покрокові інструкції | покроков, інструкція, посів, пересадка, збір |
| Сезонні поради | сезон, порада, зберігання, зим, сад |

## Image paths

Blog images use paths like `images/blog_1_photo_2@....jpg`. Ensure images exist in:
`image/catalog/blog/` (or adjust path in the Excel/script).

## Requirements

- OpenCart 4.x with CMS (cms/topic, cms/article)
- PhpSpreadsheet (from export_import extension)
- Python 3 + openpyxl (for generating Excel)
