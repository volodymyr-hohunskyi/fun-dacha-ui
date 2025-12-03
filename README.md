# fun-dacha-ui

## Category import helper

- Convert `shared/data/categories_list.csv` into an export/import ready workbook with `php tools/build_category_import.php [source_csv] [target_xlsx] [language_code]` (defaults shown in script docblock).
- The script relies on the bundled `PhpSpreadsheet` dependency that ships with the Export Import extension, so no extra Composer install is required.
- SEO keywords are auto-deduped per store/language by suffixing the category id, preventing `CategorySEOKeywords` import failures.
- A pre-built dataset is stored at `shared/data/categories_import.xlsx`; upload it through the Export Import module (`incremental` mode is supported) to populate the catalog categories.

## Product import helper

- Convert `shared/data/list.csv` into an export/import ready workbook with `php tools/build_product_import.php [source_csv] [target_xlsx]`.
- Images referenced in the CSV should live under `image/catalog/products/`; the CSV already contains the `catalog/products/` prefix for primary and secondary images so uploads match the generated workbook automatically.
- The script builds `Products`, `AdditionalImages`, and `ProductSEOKeywords` worksheets, filling multilingual columns (`en-gb`, `ru-ru`), deduplicating SEO slugs, and populating the product `location` column with the human-readable category path to make admin searches easier.
- A pre-built dataset is stored at `shared/data/products_import.xlsx`; import it via the Export/Import extension to load the full product list.