# Blog Import - Telegram to OpenCart CMS

Import blog posts extracted from Fun-Dacha Telegram channel into OpenCart CMS articles.

## Contents
- `blog_posts.json` - 365 articles (messages 100+ chars, sorted by date)
- `images/` - 196 images for posts

## Usage

### 1. Extract (already done)
```bash
# From project root
python3 scripts/extract_telegram_to_blog.py          # With RU->UA translation (slow)
python3 scripts/extract_telegram_to_blog.py --no-translate   # No translation (fast)
```

### 2. Upload to production
Upload the entire `blog_import/` folder to your OpenCart root (same level as adminpage/, catalog/, etc.)

### 3. Run import on production
```bash
cd /path/to/opencart   # Your production root
php scripts/import_blog_to_opencart.php
# Or with custom path:
php scripts/import_blog_to_opencart.php /path/to/blog_import
```

The script will:
- Copy images to `image/catalog/blog/`
- Insert articles into `oc_article`, `oc_article_description`, `oc_article_to_store`, `oc_seo_url`
- Use Ukrainian language (uk-ua) if available

## Translation
To translate content from Russian to Ukrainian, install and run without `--no-translate`:
```bash
pip install deep-translator
python3 scripts/extract_telegram_to_blog.py
```
Note: Translation is slow (~365 API calls). Run overnight or use --no-translate for Russian content.
