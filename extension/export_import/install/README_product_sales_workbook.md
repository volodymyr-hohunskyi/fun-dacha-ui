# Sales data in `shared/import/products_import.xlsx`

The Export/Import extension does **not** use a generic sheet name like “Sales”. You add a **new worksheet** with an exact name and header row, then upload the workbook.

## 1. Category seasonal promos — sheet **`SalesCalendar`**

- **Table:** `category_sales_calendar` (one row per **category_id**, not per product).
- **Headers (row 1):** `category_id`, `category_name`, `discount_percent`, `period_start`, `period_end`, `note`
- **Details:** see `README_sales_calendar.md` and `../samples/SalesCalendar.csv`.

Use this when “sales” are defined **per category** (seasonal windows). Products are linked indirectly through their categories.

---

## 2. Per-product sale price — depends on OpenCart version

### OpenCart **4.1.0.0 and newer**

The **`Specials`** worksheet is **not** imported. Use the **`Discounts`** worksheet instead; sale prices are rows in `product_discount` with **`special` = true**.

- **Sheet name:** `Discounts` (exact)
- **Must come after** the **`Products`** sheet in the same file (plugin validates order).
- **Headers (row 1):**  
  `product_id`, `customer_group`, `quantity`, `priority`, `price`, `type`, `special`, `date_start`, `date_end`

- **Typical sale row:** `quantity` = `1`; **`special`** = `true` (or `TRUE` / `YES`); **`customer_group`** = the name from **Sales → Customers → Customer Groups** (e.g. `Default`); **`type`** is usually `F` (fixed) or `P` (percentage) per your store’s rules; **`price`** = special price; dates as `YYYY-MM-DD` or `0000-00-00` if open-ended.

Template: `../samples/Discounts.csv`

### OpenCart **older than 4.1.0.0**

- **Sheet name:** `Specials`
- **Must come after** **`Products`**.
- **Headers:** `product_id`, `customer_group`, `priority`, `price`, `date_start`, `date_end`  
  → stored in `product_special`.

Template: `../samples/Specials.csv`

---

## 3. How to add the sheet to your `.xls`

1. Open `shared/import/products_import.xlsx` in Excel or LibreOffice (the repo copy includes a **`SalesCalendar`** sheet filled from `sales_calendar_by_category.csv`).
2. Import or paste the sample CSV into a **new** worksheet.
3. Rename the worksheet tab to the exact name: **`SalesCalendar`**, **`Discounts`**, or **`Specials`** (no extra spaces).
4. Ensure **`Products`** appears **before** `Discounts` / `Specials` / `AdditionalImages` in the workbook (tab order matters for validation).
5. Upload via **Extensions → Export/Import** as usual (`.xls` is supported).

---

## 4. Pack options (1 / 2 / 5) in `products_import.xlsx` — **recommended**

Use **product options** so the PDP can show pack choices (radio). The script builds:

- **`Options`** + **`OptionValues`** — one global option (e.g. “Pack quantity”) with values **1 pack**, **2 packs**, **5 packs** (wording per language in the sheet)
- **`ProductOptions`** + **`ProductOptionValues`** — per product, **required** option; **price** modifiers are computed from **`Products.price`** (treat this as your **per-unit selling price** — usually the same figure you use after applying **§2** sale rows / your pricing rules)

**Math (quantity in cart = 1):** total = `product.price + option_price` = `N × unit × (1 − tier%)` for N packs (−5% for 2, −10% for 5).

Regenerate after editing the workbook:

`shared/import/scripts/apply_pack_options_to_workbook.py`

It inserts/refreshes **`Options`**, **`OptionValues`**, **`ProductOptions`**, **`ProductOptionValues`** after **`Products`**, for products whose **`categories`** match the script’s target IDs and **`price` &gt; 0**. It **does not delete** the **`Discounts`** sheet: it only removes **legacy volume rows** (`quantity` &gt; **1**) so they do not double up with pack options. **`quantity` = 1** rows (OpenCart 4.1+ **special** sale price — see §2) are kept; maintain those **alongside** **`SalesCalendar`** (§1) as your business process requires (category windows vs per-product **Discounts**).

Set **`DEFAULT_LANG_CODE`** in the script to match **Admin → System → Settings → Store** default **language code** (e.g. `uk-ua`) so **`ProductOptions`!`option`** matches **`option_description.name`** for that language.

### Storefront

PDP pack tiles use **imported** option **option value** names (current language), not hardcoded IDs — the radio option is detected when it has three values and the option name or value names look like pack quantity.

### Import checklist

- **Order in file:** `Products` → `Options` → `OptionValues` → `ProductOptions` → `ProductOptionValues` (the extension loads global options **before** product options; the repo extension was updated accordingly).
- **Validation:** options defined **in the same file** are accepted (merged during validation).
- **Memory:** large XLSX may need **512M+** PHP memory; the extension raises the limit during import.

### Legacy: **`Discounts`** volume rows (`quantity` &gt; 1)

Still supported by OpenCart for automatic quantity pricing **without** options — see §2. With pack **options** (§4), remove volume rows from the workbook or let the script strip **`quantity` &gt; 1** so you do **not** mix both mechanisms for the same intent.
