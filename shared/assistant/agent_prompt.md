# Garden Assistant — Heuristic Search Engine Specification

This document specifies the logic for the **Fun Dacha** conversational product advisor. The assistant uses **heuristic semantic search only** — no external AI/LLM API calls. All intelligence is implemented in PHP via keyword matching, filter index lookups, and rule-based intent detection against `db.json`.

Response language follows the user's input: primarily **Ukrainian**, falling back to Russian or English.

---

## Knowledge Base

The structured JSON database at `db.json` (loaded server-side, cached) contains:

- **981 products** (vegetable seeds, herb seeds, flower seeds, fertilizers, accessories)
- **22 root categories** (Томати, Огірки, Капуста, Перець, Баклажани, Цибуля, Морква, Буряк, Кавуни, Дині, Гарбузи, Кабачки, Патисони, Зелені культури, Пряносмакові культури, Бобові культури, Квасоля, Кукурудза, Квіти, Добрива та захист, Супутні товари)
- **10 filter groups** with **102 filters** (Колір, Вирощування, Ріст, Стиглість, Стійкість, Форма, Текстура, Особливості, Призначення, Врожайність)
- **31 blog articles** across **5 topics** (Календар посадок, Гіди по вирощуванню, Порівняння сортів, Покрокові інструкції, Сезонні поради)
- **Info pages** — plain-text content from the store's Payment and Delivery information pages, cached at admin save time
- **Navigation index** — site section URLs (catalog, sales/specials, new arrivals, per-category pages)

---

## Database Access Patterns

The `db.json` has the following top-level keys:

```
meta, categories, categoryTree, products, filterGroups, filters, indexes, blog, info, navigation
```

### Finding products by category
```js
db.indexes.productsByCategory["101"]  // array of product IDs in subcategory
db.indexes.productsByCategory["100"]  // array of product IDs in root category
```

### Finding products by filter
```js
db.indexes.productsByFilter["22"]     // array of product IDs with filter 22 (e.g. "Високорослий")
```

### Intersecting filters (multi-filter search)
Intersect multiple filter ID arrays to find products matching ALL criteria:
```js
const ids = filterIds.map(fid => new Set(db.indexes.productsByFilter[fid]));
const result = ids.reduce((a, b) => new Set([...a].filter(x => b.has(x))));
```

### Text search
```js
db.indexes.productSearch["томат"]     // product IDs whose names/tags contain this token
```
Tokenize user input (split on spaces, commas, hyphens, lowercase) and union/intersect results.

### Available filters for a category
```js
db.indexes.filtersByCategory["101"]   // { "1": [filterIds], "3": [filterIds], ... }
```
Use this to show only relevant filters for the current category context.

### Getting product details
```js
db.products["1"]  // full product object
```

Product object shape:
```json
{
  "id": 1,
  "name": { "uk": "Амурський тигр", "en": "Amursky Tiger" },
  "slug": "amurskyi-tyhr",
  "categoryIds": [100, 101],
  "price": 15.0,
  "image": "catalog/products/p100001.jpg",
  "additionalImages": ["catalog/products/p100001_1.jpg"],
  "description": { "uk": "..." },
  "attributes": [
    { "group": "Характеристики", "name": "Колір", "value": "Жовтий" },
    { "group": "Характеристики", "name": "Ріст", "value": "Високорослий" }
  ],
  "filterIds": [1, 22, 44],
  "tags": ["tall", "red", "disease_resistant"],
  "status": true,
  "stockStatus": 7,
  "weight": 1,
  "weightUnit": "g"
}
```

### Blog articles
```js
db.blog.articles[0..30]               // article objects
db.blog.articlesByTopic["2"]          // indices of articles in topic 2
```

### Info pages (payment / delivery)
```js
db.info.payment.text   // plain-text content of the payment information page
db.info.payment.url    // URL of the payment information page
db.info.delivery.text  // plain-text content of the delivery information page
db.info.delivery.url   // URL of the delivery information page
```
Populated from OpenCart `information_description` table at admin save time. Never fabricate payment or delivery terms — always use this data.

### Navigation index
```js
db.navigation.catalog   // URL of the main catalog page
db.navigation.sales     // URL of the specials/discounts page
db.navigation.new       // URL of the new arrivals page
db.navigation.categories        // array of { categoryId, name, url, keywords[] }
db.navigation.categoriesById    // map of categoryId → URL (for quick lookup)
```
`categories[].keywords` contains lowercase Ukrainian/Russian keyword variants for matching (e.g. `["томат","помідор","tomato"]`).
Used to emit `navigateTo` CTAs pointing users to the correct catalog section.

---

## Conversation Flow

The PHP heuristic engine processes each user message step-by-step. Conversation state (active category, active filters) is maintained in PHP session across turns.

### Step 1 — Understand the user's need
Detect from the message (via keyword matching):
- **What crop** they want (maps to a root category ID)
- **Growing conditions**: open field, greenhouse, container/balcony
- **Experience level**: beginner, experienced
- **Goals**: fresh eating, canning/preserving, drying, children's diet, market sales
- **Preferences**: color, size, early/late ripening, disease resistance

If the message is too vague (no crop or filter detected), return one clarifying quick-reply prompt — do NOT ask multiple questions at once.

### Step 2 — Map user intent to filters
Translate keyword matches in the user's message into filter IDs and category IDs:

| User says | Maps to |
|---|---|
| "теплиця" / "greenhouse" | filter: Вирощування → Теплиця (or Теплиця/укриття) |
| "балкон" / "контейнер" | filter: Вирощування → Балкон / Контейнер |
| "рання" / "швидко" | filter: Стиглість → Рання / Ранній / Ранньостиглий |
| "висока врожайність" | filter: Врожайність → Висока / Високоврожайний |
| "для консервування" | filter: Призначення → Консервування / Засолювання |
| "без прив'язки" | filter: Вирощування → Підв'язка не потрібна |
| "стійкий до хвороб" | filter: Стійкість → Стійкий до хвороб |
| "дитяче харчування" | filter: Призначення → Дитяче харчування |
| "детермінантний" | filter: Ріст → Детермінантний |
| "червоний" | filter: Колір → Червоний |

### Step 3 — Query and rank products
1. Start with category filter (if user specified a crop type or session has active category)
2. Intersect with each applicable filter array
3. If too few results (< 3): relax the least important filter
4. If too many results (> 10): emit `askFilter` action for the next most differentiating filter group
5. Rank by: in-stock first (stockStatus=7), then price ascending

### Step 4 — Present results
Present 3–5 products. Emit a `showProducts` action with the product IDs. For each product the UI renders:
- Name (Ukrainian)
- Key attributes (2–3 most relevant to the user's query)
- Price
- Image

### Step 5 — Enrich with blog content
After resolving products, check if a relevant blog article exists:
- For growing guides → db.blog.articlesByTopic["2"] (Гіди по вирощуванню)
- For seasonal timing → db.blog.articlesByTopic["5"] (Сезонні поради)
- For planting calendar → db.blog.articlesByTopic["1"] (Календар посадок)
- For variety comparison → db.blog.articlesByTopic["3"] (Порівняння сортів)
- For how-to instructions → db.blog.articlesByTopic["4"] (Покрокові інструкції)

If a match is found, emit a `showArticle` action and append a text line: "До речі, у нас є стаття '[title]' — рекомендую переглянути!"

---

## Heuristic Rules

### If user is a beginner:
- Prefer filters: Детермінантний (self-limiting growth, easier), Підв'язка не потрібна, Стійкий до хвороб
- Prefer early/medium maturity
- Suggest popular categories: Томати, Огірки, Зелень

### If user wants containers/balcony:
- Filter: Вирощування → Балкон, Контейнер
- Filter: Ріст → Низькорослий, Компактний, Детермінантний

### If user wants greenhouse:
- Filter: Вирощування → Теплиця / Теплиця/укриття
- Optionally: Бджолозапилюваний or Партенокарпічний (for cucumbers)

### If user wants to preserve/pickle:
- Filter: Призначення → Консервування, Засолювання, Паста, Соус
- Good categories: Томати, Огірки, Перець, Капуста

### If season is spring (March–May):
- Mention planting calendar articles
- Suggest warm-season crops: Томати, Огірки, Перець, Баклажани

### If user asks "what grows in shade":
- Filter: Стійкість → Тіньовитривалий
- Suggest: Зелені культури, Пряносмакові культури

### If user asks for drought-resistant:
- Filter: Стійкість → До посухи / Посухостійкий

### If user asks about sales / discounts:
- Emit `navigateTo` → `db.navigation.sales`
- Text: "Переглядайте поточні акції та знижки в нашому магазині!"
- Quick replies after: "Консультація", "Показати каталог"

### If user asks for the full catalog or "show everything":
- Emit `navigateTo` → `db.navigation.catalog`

### If user asks about a specific category by name:
- Match against `db.navigation.categories[].keywords`
- Emit `navigateTo` → matched category URL + store `categoryId` in session for follow-up product search

### If user asks about payment:
- Emit `showInfo` with `db.info.payment.text` + `navigateTo` with `db.info.payment.url`
- Do NOT invent payment terms

### If user asks about delivery:
- Emit `showInfo` with `db.info.delivery.text` + `navigateTo` with `db.info.delivery.url`
- Do NOT invent delivery terms

---

## Response Guidelines

- **Tone**: warm, helpful, practical. Like advice from an experienced neighbor-gardener.
- **Length**: keep responses focused. 2–4 sentences + UI actions. Never dump everything at once.
- **Clarify before searching**: if no category or filter can be matched, emit one `askFilter` action.
- **Only real data**: every product recommendation must exist in db.products with a real ID. Never invent product names or attributes.
- **Prices**: always use the actual price from the product object. If price is 0, say "Ціна уточнюється".
- **Out-of-stock**: if stockStatus ≠ 7, note "Тимчасово відсутній" but still show the product.

---

## UI Actions Emitted by the Engine

The PHP heuristic engine returns structured actions for the UI layer:

```json
{
  "actions": [
    { "type": "showProducts", "productIds": [1, 5, 22, 101] },
    { "type": "showFilters", "categoryId": 101, "activeFilterIds": [22, 44] },
    { "type": "showArticle", "articleIndex": 3 },
    { "type": "showCategory", "categoryId": 101 },
    { "type": "askFilter", "groupId": 4, "question": "Яка стиглість вам підходить?" },
    { "type": "navigateTo", "url": "/index.php?route=product/special", "label": "Переглянути акції →" },
    { "type": "showInfo", "content": "Оплата можлива картою Visa/Mastercard, накладеним платежем..." }
  ]
}
```

Action types:
- `showProducts` — render product cards for these IDs
- `showFilters` — render a filter chip group for the user to tap/click
- `showArticle` — render a blog article preview card
- `showCategory` — navigate/highlight a category
- `askFilter` — show a quick-reply button group with filter options from filterGroup[groupId]
- `navigateTo` — render a prominent CTA link button pointing to a catalog/category/sales/info URL
- `showInfo` — render a formatted text block (payment or delivery page content)

### CTA placement rules
- Always emit `navigateTo` for the relevant category after showing products
- Emit `navigateTo` (sales page) when user asks about discounts/sales
- Emit `navigateTo` (catalog) when user asks to browse everything
- Emit `showInfo` for payment/delivery intents; also include `navigateTo` with the full page URL

Action types:
- `showProducts` — render product cards for these IDs
- `showFilters` — render a filter chip group for the user to tap/click
- `showArticle` — render a blog article preview card
- `showCategory` — navigate/highlight a category
- `askFilter` — show a quick-reply button group with filter options from filterGroup[groupId]

---

## Example Conversations

### Example 1 — Beginner tomatoes for greenhouse
**User**: Хочу посадити томати в теплиці, я початківець

**Assistant**: Чудово! Для теплиці початківцям найкраще підходять детермінантні або напівдетермінантні сорти — вони не потребують складного формування. Яка стиглість вам важливіша — рання (червень–липень) чи хочете збирати все літо?

*(after user answers)*

**Assistant**: Ось кілька відмінних варіантів для вашої теплиці:
[product cards]

До речі, у нас є стаття "Вирощування томатів у теплиці" — рекомендую переглянути!

---

### Example 2 — Balcony herbs
**User**: Що можна виростити на балконі?

**Assistant**: На балконі чудово ростуть зелені та пряносмакові культури! Базилік, петрушка, кріп, цибуля на перо — все це нетребує багато місця. Також є компактні сорти томатів і перцю спеціально для контейнерів. Що вас більше цікавить — зелень для їжі чи може щось декоративне?

---

### Example 3 — Pickling cucumbers
**User**: Шукаю огірки для засолювання

**Assistant**: Для засолювання потрібні сорти з щільною м'якоттю і тонкою шкіркою. Підкажіть — плануєте вирощувати у відкритому ґрунті чи в теплиці? Від цього залежить вибір сорту.
