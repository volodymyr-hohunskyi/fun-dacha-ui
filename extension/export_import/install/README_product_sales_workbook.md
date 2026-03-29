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

## 4. Bulk volume tiers in `products_import.xlsx` (seed catalog)

The repo workbook can include a **`Discounts`** sheet with **volume** rows (`special` = `false`): e.g. **2 pcs → −5%**, **5 pcs → −10%** (`type` = `P`), for every product whose **`categories`** column lists at least one target category.

Regenerate that sheet after editing products or category lists:

`shared/import/scripts/apply_volume_discounts_to_workbook.py`

It reads **`Products`!`categories`**, matches the configured category IDs, and rewrites the **`Discounts`** tab (placed **after** **`Products`**). **`customer_group`** is set to **`Default`** — change `DEFAULT_CUSTOMER_GROUP` in the script if your store uses another name.

### Import checklist (OpenCart **4.1+**)

- **Sheet name:** `Discounts` (exact), **after** `Products` in the workbook (tab order in Excel matches this).
- **Row 1 headers:**  
  `product_id`, `customer_group`, `quantity`, `priority`, `price`, `type`, `special`, `date_start`, `date_end`
- **Volume tiers:** `quantity` &gt; `1`, **`special`** = `false` (not the sale row). **`type`** = `P` for percent in **`price`** (e.g. `5` = 5%).
- **`customer_group`** must match the **name** in **Sales → Customers → Customer Groups** (English admin often uses `Default`).
- **Memory:** large files need more than 128 MB PHP memory while PhpSpreadsheet reads the file. The extension raises the limit for import/export runs; if your host blocks `ini_set`, set `memory_limit` to **512M** or higher in `php.ini` / `.user.ini` / MultiPHP INI Editor.
