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

## Images – where to place them

**Copy all blog images to:**

```
{OPEN CART ROOT}/image/catalog/blog/
```

Example: `/home/xjgqivxn/public_html/image/catalog/blog/`

**Source images** (all 31 files):

- Current copies: `shared/blog/images/` (18 files)
- Source in JSON: filenames like `blog_1_photo_2@19-05-2020_19-55-28.jpg`

**Full list of filenames** (from blog_articles.json):

```
blog_1_photo_2@19-05-2020_19-55-28.jpg
blog_2_photo_3@19-05-2020_22-04-52.jpg
blog_6_photo_11@23-05-2020_12-21-06.jpg
blog_11_photo_30@03-06-2020_00-16-10.jpg
blog_32_photo_68@13-07-2020_21-49-17.jpg
blog_38_photo_77@25-07-2020_22-12-15.jpg
blog_43_photo_86@11-08-2020_15-20-42.jpg
blog_52_photo_97@09-09-2020_20-29-06.jpg
blog_55_photo_104@27-10-2020_09-01-46.jpg
blog_58_photo_135@19-09-2022_15-49-33.jpg
blog_78_photo_231@23-06-2024_09-32-27.jpg
blog_84_photo_239@05-07-2024_10-31-16.jpg
blog_85_photo_241@07-07-2024_09-31-53.jpg
blog_89_photo_271@19-07-2024_21-04-37.jpg
blog_96_photo_297@28-08-2024_12-30-21.jpg
blog_108_photo_327@22-10-2024_16-08-52.jpg
blog_119_photo_354@01-02-2025_20-26-26.jpg
blog_139_photo_386@25-02-2025_10-56-14.jpg
blog_143_photo_398@28-02-2025_23-50-16.jpg
blog_189_photo_504@17-04-2025_16-08-33.jpg
blog_228_photo_582@30-05-2025_12-23-34.jpg
blog_334_photo_781@18-11-2025_11-24-10.jpg
blog_339_photo_789@17-12-2025_15-14-09.jpg
blog_341_photo_790@24-12-2025_22-55-11.jpg
blog_351_photo_806@16-01-2026_12-19-15.jpg
blog_353_photo_810@23-01-2026_14-44-11.jpg
blog_359_photo_822@12-02-2026_12-49-41.jpg
blog_360_photo_823@12-02-2026_13-13-59.jpg
blog_361_photo_824@12-02-2026_13-17-26.jpg
blog_363_photo_826@28-02-2026_09-39-29.jpg
blog_364_photo_827@01-03-2026_20-03-35.jpg
```

Excel expects paths like `catalog/blog/filename.jpg` (relative to the `image` folder).

## Requirements

- OpenCart 4.x with CMS (cms/topic, cms/article)
- PhpSpreadsheet (from export_import extension)
- Python 3 + openpyxl (for generating Excel)
