#!/usr/bin/env python3
"""
Extract Telegram channel messages to OpenCart blog JSON format.
- Messages with 100+ characters only
- Sorted by date
- Translated RU -> UA (optional, via MyMemory API)
- Extracts image per post
"""

import json
import re
import os
import shutil
from datetime import datetime
from pathlib import Path

try:
    from bs4 import BeautifulSoup
except ImportError:
    print("Installing beautifulsoup4...")
    os.system("pip install beautifulsoup4 -q")
    from bs4 import BeautifulSoup

try:
    from deep_translator import GoogleTranslator
    HAS_TRANSLATOR = True
except ImportError:
    HAS_TRANSLATOR = False

SCRIPT_DIR = Path(__file__).parent.resolve()
CHAT_DIR = SCRIPT_DIR / ".." / "shared" / "chat"
OUTPUT_DIR = SCRIPT_DIR / ".." / "blog_import"
IMAGES_DIR = OUTPUT_DIR / "images"


def parse_date_from_title(title_attr):
    """Parse date from title like '19.05.2020 19:55:28 UTC+02:00' or '26.07.2025 07:47:33 UTC+02:00'"""
    if not title_attr:
        return None
    try:
        parts = title_attr.strip().split()
        if len(parts) >= 2:
            date_part = parts[0]  # DD.MM.YYYY
            time_part = parts[1]  # HH:MM:SS
            dt_str = f"{date_part} {time_part}"
            return datetime.strptime(dt_str, "%d.%m.%Y %H:%M:%S")
    except (ValueError, IndexError):
        pass
    return None


def extract_text_html(tag):
    """Extract text, preserving basic HTML (br, strong, etc.)"""
    if not tag:
        return ""
    html = str(tag)
    # Clean: keep <br>, <strong>, <b>, <i>, <em>, strip scripts
    html = re.sub(r'<script[^>]*>.*?</script>', '', html, flags=re.DOTALL | re.IGNORECASE)
    return html.strip()


def translate_ru_to_ua(text):
    """Translate Russian to Ukrainian - skip HTML, translate plain text only"""
    if not HAS_TRANSLATOR or not text:
        return text
    from bs4 import BeautifulSoup
    try:
        # Strip HTML for translation
        plain = BeautifulSoup(text, 'html.parser').get_text(separator=' ', strip=True)
        if len(plain) < 10:
            return text
        if len(plain) > 4500:
            parts = [plain[i:i+4000] for i in range(0, len(plain), 4000)]
            translated = "\n".join(GoogleTranslator(source='ru', target='uk').translate(p) or p for p in parts)
        else:
            translated = GoogleTranslator(source='ru', target='uk').translate(plain) or plain
        if translated and '<' not in text:
            return translated
        return text
    except Exception as e:
        return text


def extract_photo_href(msg):
    """Get full-size photo href from message (prefer non-thumb)"""
    for a in msg.select('a[href*="photos/"]'):
        href = a.get('href', '')
        if href and '_thumb' not in href and href.endswith('.jpg'):
            return href.split('/')[-1]
    for a in msg.select('a[href*="photos/"]'):
        href = a.get('href', '')
        if href:
            name = href.split('/')[-1]
            # Convert thumb to full: photo_2@19-05-2020_19-55-28_thumb.jpg -> photo_2@19-05-2020_19-55-28.jpg
            if '_thumb' in name:
                name = name.replace('_thumb.jpg', '.jpg').replace('_thumb ', '.jpg ')
            if name.endswith('.jpg'):
                return name
    return None


def extract_messages_from_file(filepath):
    """Extract messages with 100+ chars from HTML file"""
    with open(filepath, 'r', encoding='utf-8') as f:
        soup = BeautifulSoup(f.read(), 'html.parser')

    messages = []
    for msg in soup.select('div.message'):
        if 'service' in msg.get('class', []):
            continue
        body = msg.select_one('.body') or msg.select_one('.forwarded.body')
        if not body:
            continue
        text_el = body.select_one('.text')
        if not text_el:
            continue
        text = text_el.get_text(separator=' ', strip=True)
        if len(text) < 100:
            continue
        date_el = msg.select_one('.date[title]') or body.select_one('.date[title]')
        from_name = body.select_one('.from_name')
        date_str = date_el.get('title', '') if date_el else ''
        dt = parse_date_from_title(date_str)
        photo = extract_photo_href(msg)
        desc_html = extract_text_html(text_el)
        messages.append({
            'text': text,
            'description_html': desc_html,
            'date': dt,
            'date_str': date_str,
            'photo': photo,
            'from_name': from_name.get_text(strip=True) if from_name else 'Fun-Dacha'
        })
    return messages


def main():
    CHAT_DIR_RES = CHAT_DIR.resolve()
    OUTPUT_DIR_RES = OUTPUT_DIR.resolve()
    IMAGES_DIR_RES = IMAGES_DIR.resolve()
    PHOTOS_SRC = CHAT_DIR_RES / "photos"

    OUTPUT_DIR_RES.mkdir(parents=True, exist_ok=True)
    IMAGES_DIR_RES.mkdir(parents=True, exist_ok=True)

    all_messages = []
    for fname in ['messages.html', 'messages2.html']:
        fp = CHAT_DIR_RES / fname
        if fp.exists():
            msgs = extract_messages_from_file(fp)
            all_messages.extend(msgs)
            print(f"  {fname}: {len(msgs)} messages")

    # Sort by date (oldest first)
    all_messages.sort(key=lambda m: m['date'] or datetime.min)

    print(f"\nTotal: {len(all_messages)} messages.")
    if HAS_TRANSLATOR:
        print("Translating to Ukrainian (this may take a while)...")
    else:
        print("Skipping translation (install: pip install deep-translator). Keeping original.")

    blog_posts = []
    for i, m in enumerate(all_messages):
        text_ua = translate_ru_to_ua(m['text']) if HAS_TRANSLATOR else m['text']
        desc_html = m['description_html']
        if desc_html and HAS_TRANSLATOR:
            try:
                desc_html = translate_ru_to_ua(desc_html)
            except Exception:
                pass
        if not desc_html:
            desc_html = f"<p>{text_ua}</p>"

        dt = m['date'] or datetime.now()
        title = (text_ua[:80] + '...') if len(text_ua) > 80 else text_ua
        title = title.replace('\n', ' ').strip()

        image_path = None
        if m['photo'] and PHOTOS_SRC.exists():
            src_file = PHOTOS_SRC / m['photo']
            if not src_file.exists():
                alt_name = m['photo'].replace('_thumb.jpg', '.jpg').replace('_thumb', '.jpg')
                src_file = PHOTOS_SRC / alt_name
            if not src_file.exists() and '_thumb' in m['photo']:
                src_file = PHOTOS_SRC / m['photo'].replace('_thumb', '')
            if src_file.exists():
                dest_name = f"blog_{i+1}_{src_file.name}"
                dest_file = IMAGES_DIR_RES / dest_name
                shutil.copy2(src_file, dest_file)
                image_path = f"catalog/blog/{dest_name}"

        # Use language_id 3 for uk-ua if available, else 1 (configurable in import script)
        blog_posts.append({
            "sort_order": i + 1,
            "author": "Fun-Dacha",
            "status": 1,
            "date_added": dt.strftime("%Y-%m-%d %H:%M:%S"),
            "topic_id": 0,
            "article_description": {
                "1": {
                    "name": title,
                    "description": desc_html,
                    "image": image_path or "",
                    "tag": "",
                    "meta_title": title[:255],
                    "meta_description": text_ua[:320] if len(text_ua) > 320 else text_ua,
                    "meta_keyword": ""
                }
            },
            "article_store": [0],
            "article_seo_url": {
                "0": {
                    "1": f"blog-post-{i+1}"
                }
            }
        })
        if (i + 1) % 50 == 0:
            print(f"  Processed {i+1}/{len(all_messages)}...")

    output = {
        "language_id": 1,
        "store_id": 0,
        "topic_id": 0,
        "posts": blog_posts
    }

    json_path = OUTPUT_DIR_RES / "blog_posts.json"
    with open(json_path, 'w', encoding='utf-8') as f:
        json.dump(output, f, ensure_ascii=False, indent=2)

    print(f"\nDone! Output: {json_path}")
    print(f"Images: {IMAGES_DIR_RES} ({len(list(IMAGES_DIR_RES.glob('*')))} files)")
    return 0


if __name__ == "__main__":
    import sys
    if "--no-translate" in sys.argv:
        globals()["HAS_TRANSLATOR"] = False
    main()
