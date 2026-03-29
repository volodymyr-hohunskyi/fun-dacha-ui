# Sales calendar (категорії)

Цей аркуш стосується **акцій по категоріях** (`category_sales_calendar`), не цін товарів. Для **ціни розпродажу по товару** див. `README_product_sales_workbook.md` (аркуші `Discounts` / `Specials`).

## База даних

Таблиця `PREFIXcategory_sales_calendar` створюється автоматично при першому імпорті/експорті через **Export/Import**, або виконайте SQL з `category_sales_calendar.sql` (замініть `oc_` на ваш `DB_PREFIX`).

## Формат аркуша **SalesCalendar**

Колонки (перший рядок — заголовки):

| category_id | category_name | discount_percent | period_start | period_end | note |
|-------------|-----------------|------------------|--------------|------------|------|

- **category_id** — існуючий ID категорії в магазині.
- **category_name** — лише для зручності; при імпорті не записується в БД.
- **discount_percent** — наприклад `10%`.
- **period_start** / **period_end** — дати у форматі **DD.MM** (річний календар).
- **note** — коментар (сезон, акцент).

## Файли

- `../samples/SalesCalendar.csv` — готові дані для копіювання в Excel.
- Експорт **Categories** або **Products** у адмінці тепер додає аркуш **SalesCalendar** (якщо таблиця вже заповнена).

## Імпорт

Плагін приймає **XLS, XLSX або ODS** з аркушем з назвою точно **`SalesCalendar`**. Відкрийте CSV у Excel / LibreOffice, перейменуйте аркуш на `SalesCalendar`, додайте до вашого каталогу (наприклад **`products_import.xls`**) або імпортуйте разом з іншими аркушами.

Режими: **повна заміна** — очищає таблицю календаря перед завантаженням; **інкрементальний** — лише оновлює рядки з вказаними `category_id`.
