# Product Attribute Normalization for Seed E-Commerce (OpenCart)

E-commerce data architecture guide for normalizing flat product attributes into a filterable, scalable structure for an OpenCart seed store.

---

## 1. Normalized Data Model

### Before (Flat Format)

| ProductID | ProductAttributes |
|-----------|-------------------|
| 101 | Колір: Жовтий, Оранжевий |
| 102 | Ріст: Високорослий, Розлогий |
| 103 | Колір: Рожевий, Малиновий<br>Вирощування: Відкритий ґрунт, Потребує підв'язки |

**Problems:** Combined values break filtering; no multi-select; poor UX.

### After (Normalized)

**ProductAttributes** (for product page display):

| product_id | attribute_group | attribute | text(en-gb) | text(uk-ua) | text(ru-ru) |
|------------|-----------------|-----------|-------------|-------------|-------------|
| 101 | Характеристики | Колір | Жовтий, Оранжевий | Жовтий, Оранжевий | Жовтий, Оранжевий |
| 101 | Характеристики | Ріст | Високорослий | Високорослий | Високорослий |

**ProductFilters** (for filter sidebar – one row per value):

| product_id | filter_group | filter |
|------------|--------------|--------|
| 101 | Колір | Жовтий |
| 101 | Колір | Оранжевий |
| 101 | Ріст | Високорослий |

### Key Design Decisions

| Component | Purpose |
|-----------|---------|
| **ProductAttributes** | Display on product page; one row per product+attribute; text can be comma-joined for multi-value display |
| **ProductFilters** | Powers category filter sidebar; **one row per product per filter value** – enables multi-select filtering |
| **FilterGroups / Filters** | Taxonomy: group names (Колір, Ріст) and filter values (Жовтий, Оранжевий) |

OpenCart uses two systems: **Attributes** (product display) and **Filters** (category filtering). Both are required for full UX.

---

## 2. Migration Approach for products_import.xlsx

### Step-by-Step Process

1. **Prepare source file**
   - Ensure `products_import.xlsx` has a sheet with `ProductID` (or `product_id`) and `ProductAttributes` columns.
   - Supported formats in `ProductAttributes`:
     - Single: `Колір: Жовтий, Оранжевий`
     - Multi-line: `Колір: Жовтий\nРіст: Високорослий, Розлогий`

2. **Run normalization script**
   ```bash
   cd shared/data
   python3 normalize_flat_attributes.py --input products_import.xlsx --output products_import_normalized.xlsx
   ```

3. **Inspect output**
   - `NormalizedAttributes` – Canonical format: ProductID | AttributeGroup | AttributeValue (one row per value)
   - `ProductAttributes` – OpenCart ProductAttributes worksheet
   - `ProductFilters` – OpenCart ProductFilters worksheet
   - `FilterGroups` – Filter group taxonomy
   - `Filters` – Filter value taxonomy

4. **Merge with full OpenCart export**
   - Export from OpenCart (Extensions → Import/Export) to get `Products`, `Categories`, etc.
   - Copy `ProductAttributes`, `ProductFilters`, `FilterGroups`, `Filters` sheets from `products_import_normalized.xlsx` into the full export.
   - Ensure import order: `FilterGroups` → `Filters` → `Products` → `ProductAttributes` → `ProductFilters`.

5. **Import**
   - Use Export/Import extension to upload the merged workbook.
   - Use **names** (not IDs) for attribute_group, attribute, filter_group, filter if importing into a fresh store.

### Normalization Rules Applied

1. Split values by comma
2. Trim whitespace
3. Keep attribute group name (resolved via schema)
4. Create one ProductFilters row per attribute value
5. Preserve ProductID linkage
6. Map raw values to canonical taxonomy when defined

---

## 3. Final Attribute Structure

### Attribute Groups (Характеристики)

All attributes belong to the group **Характеристики** (Characteristics). Individual attributes:

| Attribute (uk-ua) | Key | Description |
|-------------------|-----|-------------|
| Колір | Color | Fruit/flower color |
| Вирощування | Cultivation | Growing conditions |
| Ріст | Growth | Plant growth type |
| Стиглість | Maturity | Ripening period |
| Стійкість | Resistance | Disease/pest resistance |
| Форма | Shape | Fruit shape |
| Текстура | Texture | Fruit texture |
| Особливості | Traits | Taste/aroma |
| Призначення | Usage | Culinary use |
| Врожайність | Yield | Yield level |

### Filter Value Taxonomy (Examples)

**Колір**
- Жовтий, Оранжевий, Рожевий, Малиновий, Червоний, Коричневий, Чорний, Білий, Зелений, Фіолетовий, Різнокольоровий

**Вирощування**
- Відкритий ґрунт, Теплиця, Потребує підв'язки, Підв'язка не потрібна, Контейнер, Балкон

**Ріст**
- Високорослий, Розлогий, Детермінантний, Індетермінантний, Низькорослий, Середньорослий

**Стиглість**
- Рання, Середньорання, Середня, Середньопізня, Пізня

Full taxonomy is defined in `shared/data/attribute_schema.py` (`FILTER_VALUE_TAXONOMY`).

---

## 4. Example Normalized Dataset

### Input
```
ProductID | ProductAttributes
101       | Колір: Жовтий, Оранжевий
102       | Колір: Червоний, Жовтий
          | Ріст: Високорослий, Розлогий
```

### Output – ProductAttributes (Display)
```
product_id | attribute_group | attribute | text(uk-ua)
101        | Характеристики  | Колір     | Жовтий, Оранжевий
102        | Характеристики  | Колір     | Червоний, Жовтий
102        | Характеристики  | Ріст      | Високорослий, Розлогий
```

### Output – ProductFilters (Filtering)
```
product_id | filter_group | filter
101        | Колір        | Жовтий
101        | Колір        | Оранжевий
102        | Колір        | Червоний
102        | Колір        | Жовтий
102        | Ріст         | Високорослий
102        | Ріст         | Розлогий
```

---

## 5. OpenCart Filter Implementation

### Prerequisites

1. **Filter taxonomy must exist**
   - Import `FilterGroups` and `Filters` before `ProductFilters`.

2. **Category–filter linking**
   - OpenCart filters apply per category. Link filter groups to categories:
     - Admin → Catalog → Categories → Edit category → Data tab → Filters.

3. **Filter module**
   - Enable **Filter** module in Layout (e.g. Category layout, Column Left).

### Filter UI Structure (Target)

```
Характеристики

Колір
☐ Жовтий
☐ Оранжевий
☐ Рожевий
☐ Малиновий
☐ Червоний
☐ Коричневий
☐ Чорний

Вирощування
☐ Відкритий ґрунт
☐ Потребує підв'язки
☐ Теплиця

Ріст
☐ Високорослий
☐ Розлогий
☐ Детермінантний
☐ Індетермінантний
```

- Multi-select checkboxes
- Product count per filter (if configured)
- URL: `?filter=1,2,5` (filter_ids)

### Scalable Growth

1. **Add new values**
   - Extend `FILTER_VALUE_TAXONOMY` in `attribute_schema.py`.
   - Re-run normalization or manually add rows to Filters worksheet.

2. **Add new groups**
   - Add to `ATTRIBUTE_DEFINITIONS` and `FILTER_VALUE_TAXONOMY`.
   - Run `build_attribute_workbook.py` for Attributes; normalization script for Filters.

3. **Category assignment**
   - Assign relevant filter groups to each seed category (Vegetables, Flowers, Herbs, etc.).

---

## 6. Optional CSS/UI Improvements for Filter UX

```css
/* Filter section styling */
#filter-group-1 .form-check {
  margin-bottom: 0.35rem;
}
#filter-group-1 .form-check-input {
  margin-top: 0.2em;
  cursor: pointer;
}
#filter-group-1 .form-check-label {
  cursor: pointer;
  font-size: 0.95rem;
}

/* Square checkboxes (Bootstrap override) */
.form-check-input {
  border-radius: 0.2rem;
}

/* Collapsible groups */
.filter-group-toggle {
  cursor: pointer;
  user-select: none;
}
.filter-group-toggle[aria-expanded="false"] .fa-chevron-down {
  transform: rotate(-90deg);
}
```

### Suggested Enhancements

- **Collapsible groups** – Accordion for Колір, Ріст, Вирощування.
- **Active filter badges** – Show selected filters as removable chips.
- **Product count** – Enable `config_product_count` for counts per filter.
- **Mobile-first** – Stack filters vertically; sticky filter panel on scroll.

---

## 7. Additional Filters for Seed Stores

| Filter Group | Example Values | Use Case |
|--------------|----------------|----------|
| **Сезон посіву** | Весна, Літо, Осінь | Planting season |
| **Світло** | Сонячне місце, Півтінь, Тінь | Light requirements |
| **Полив** | Помірний, Регулярний, Мінімальний | Water needs |
| **Тип рослини** | Однорічна, Дворічна, Багаторічна | Plant lifecycle |
| **Зона морозостійкості** | 3, 4, 5, 6, 7 | USDA zones |
| **Висота рослини** | До 30 см, 30–60 см, 60–120 см | Mature height |
| **Органічне** | Так, Ні | Organic certification |
| **Країна походження** | Україна, Нідерланди | Origin |

Add these to `FILTER_VALUE_TAXONOMY` and `ATTRIBUTE_DEFINITIONS` as needed.

---

## File Reference

| File | Purpose |
|------|---------|
| `shared/data/attribute_schema.py` | Attribute definitions, filter taxonomy, value normalization |
| `shared/data/normalize_flat_attributes.py` | Main normalization script for flat ProductAttributes |
| `shared/data/create_sample_import.py` | Creates sample `products_import.xlsx` |
| `shared/data/build_attribute_workbook.py` | Generates AttributeGroups + Attributes for OpenCart |
| `shared/data/products_import.xlsx` | Source (create via create_sample_import or use your own) |
| `shared/data/products_import_normalized.xlsx` | Output: NormalizedAttributes, ProductAttributes, ProductFilters, FilterGroups, Filters |

---

## Quick Start

```bash
# 1. Create sample (or use your products_import.xlsx)
python3 shared/data/create_sample_import.py

# 2. Normalize
python3 shared/data/normalize_flat_attributes.py

# 3. Build attribute taxonomy (if needed)
python3 shared/data/build_attribute_workbook.py

# 4. Merge products_import_normalized.xlsx sheets into full OpenCart export
# 5. Import via Export/Import extension
```
