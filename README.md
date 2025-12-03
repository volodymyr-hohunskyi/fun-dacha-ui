# fun-dacha-ui

## Category import helper

- Convert `shared/data/categories_list.csv` into an export/import ready workbook with `php tools/build_category_import.php [source_csv] [target_xlsx] [language_code]` (defaults shown in script docblock).
- The script relies on the bundled `PhpSpreadsheet` dependency that ships with the Export Import extension, so no extra Composer install is required.
- SEO keywords are auto-deduped per store/language by suffixing the category id, preventing `CategorySEOKeywords` import failures.
- A pre-built dataset is stored at `shared/data/categories_import.xlsx`; upload it through the Export Import module (`incremental` mode is supported) to populate the catalog categories.