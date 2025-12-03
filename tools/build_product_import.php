#!/usr/bin/env php
<?php
declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__ . '/../extension/export_import/system/library/export_import/vendor/autoload.php';

/**
 * Build an OpenCart Export/Import compatible workbook for Products based on shared/data/list.csv.
 *
 * Usage:
 *   php tools/build_product_import.php [source_csv] [target_xlsx]
 */

const DEFAULT_PRODUCT_SOURCE = __DIR__ . '/../shared/data/list.csv';
const DEFAULT_PRODUCT_TARGET = __DIR__ . '/../shared/data/products_import.xlsx';
const CATEGORY_WORKBOOK_SOURCE = __DIR__ . '/../shared/data/categories_import.xlsx';
const TAGS_SOURCE = __DIR__ . '/../shared/data/tags.csv';
const PRODUCT_LANGUAGES = [
	'en-gb' => [
		'name' => 'name',
		'description' => 'description',
		'tags' => 'tags'
	],
	'uk-ua' => [
		'name' => 'name',
		'description' => 'description',
		'tags' => 'tags'
	],
	'ru-ru' => [
		'name' => 'name_ru',
		'description' => 'description_ru',
		'tags' => 'tags'
	],
];
const FILTER_GROUP_LABELS = [
	'growth' => [
		'sort' => 1,
		'labels' => [
			'en-gb' => 'Growth',
			'uk-ua' => 'Ріст',
			'ru-ru' => 'Рост'
		]
	],
	'color' => [
		'sort' => 2,
		'labels' => [
			'en-gb' => 'Color',
			'uk-ua' => 'Колір',
			'ru-ru' => 'Цвет'
		]
	],
	'texture' => [
		'sort' => 3,
		'labels' => [
			'en-gb' => 'Texture',
			'uk-ua' => 'Текстура',
			'ru-ru' => 'Текстура'
		]
	],
	'resistance' => [
		'sort' => 4,
		'labels' => [
			'en-gb' => 'Resistance',
			'uk-ua' => 'Стійкість',
			'ru-ru' => 'Устойчивость'
		]
	],
	'traits' => [
		'sort' => 5,
		'labels' => [
			'en-gb' => 'Traits',
			'uk-ua' => 'Ознаки',
			'ru-ru' => 'Характеристики'
		]
	],
	'shape' => [
		'sort' => 6,
		'labels' => [
			'en-gb' => 'Shape',
			'uk-ua' => 'Форма',
			'ru-ru' => 'Форма'
		]
	],
	'maturity' => [
		'sort' => 7,
		'labels' => [
			'en-gb' => 'Maturity',
			'uk-ua' => 'Дозрівання',
			'ru-ru' => 'Созревание'
		]
	],
	'yield' => [
		'sort' => 8,
		'labels' => [
			'en-gb' => 'Yield',
			'uk-ua' => 'Урожайність',
			'ru-ru' => 'Урожайность'
		]
	],
	'usage' => [
		'sort' => 9,
		'labels' => [
			'en-gb' => 'Usage',
			'uk-ua' => 'Використання',
			'ru-ru' => 'Использование'
		]
	],
	'cultivation' => [
		'sort' => 10,
		'labels' => [
			'en-gb' => 'Cultivation',
			'uk-ua' => 'Вирощування',
			'ru-ru' => 'Культивация'
		]
	]
];
const CATEGORY_SOURCE = __DIR__ . '/../shared/data/categories_list.csv';
const CATEGORY_WORKSHEETS = ['Categories', 'CategorySEOKeywords'];
const WORKBOOK_SHEET_ORDER = [
	'Categories',
	'CategoryFilters',
	'CategorySEOKeywords',
	'Products',
	'ProductFilters',
	'ProductSEOKeywords',
	'AdditionalImages',
	'FilterGroups',
	'Filters'
];

[$source, $target] = resolveArguments($argv ?? []);
$rows = readCsv($source);
$categoryNames = loadCategoryNames(CATEGORY_SOURCE);
$tagDefinitions = loadTagDefinitions(TAGS_SOURCE);

if (empty($rows)) {
	throw new RuntimeException(sprintf('No product rows found in %s', $source));
}

$spreadsheet = buildProductWorkbook($rows, PRODUCT_LANGUAGES, $categoryNames, $tagDefinitions);
prependCategorySheets($spreadsheet, CATEGORY_WORKBOOK_SOURCE);
reorderWorkbookSheets($spreadsheet, WORKBOOK_SHEET_ORDER);
$writer = new Xlsx($spreadsheet);
$targetDir = dirname($target);

if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
	throw new RuntimeException(sprintf('Unable to create target directory %s', $targetDir));
}

$writer->save($target);
printf("Wrote %d products to %s\n", count($rows), $target);

/**
 * @param array<int,string> $argv
 * @return array{0:string,1:string}
 */
function resolveArguments(array $argv): array {
	$source = $argv[1] ?? DEFAULT_PRODUCT_SOURCE;
	$target = $argv[2] ?? DEFAULT_PRODUCT_TARGET;

	if (!is_file($source)) {
		throw new InvalidArgumentException(sprintf('Source CSV not found: %s', $source));
	}

	return [$source, $target];
}

/**
 * @return array<int,array<string,string>>
 */
function readCsv(string $path): array {
	$handle = fopen($path, 'r');

	if ($handle === false) {
		throw new RuntimeException(sprintf('Unable to open %s for reading', $path));
	}

	$rows = [];
	$headers = [];
	while (($data = fgetcsv($handle)) !== false) {
		if (empty($headers)) {
			$headers = array_map(
				static fn(string $value): string => ltrim($value, "\u{FEFF}"),
				$data
			);
			continue;
		}

		$row = [];
		foreach ($headers as $index => $header) {
			$row[$header] = $data[$index] ?? '';
		}

		$rows[] = $row;
	}

	fclose($handle);

	return $rows;
}

/**
 * @param array<int,array<string,string>> $rows
 * @param array<string,array<string,string>> $languages
 * @param array<string,array<string,string>> $categoryNames
 * @param array<string,array<string,array<string,array<string,string>>>> $tagDefinitions
 */
function buildProductWorkbook(array $rows, array $languages, array $categoryNames, array $tagDefinitions): Spreadsheet {
	$spreadsheet = new Spreadsheet();
	$productsSheet = $spreadsheet->getActiveSheet();
	$productsSheet->setTitle('Products');

	$languageCodes = array_keys($languages);
	$productHeader = buildProductHeader($languageCodes);
	$productsSheet->fromArray($productHeader, null, 'A1', true);

	$additionalImagesSheet = $spreadsheet->createSheet();
	$additionalImagesSheet->setTitle('AdditionalImages');
	$additionalImagesSheet->fromArray(['product_id', 'image', 'sort_order'], null, 'A1', true);

	$seoSheet = $spreadsheet->createSheet();
	$seoSheet->setTitle('ProductSEOKeywords');
	$seoHeader = ['product_id', 'store_id'];
	foreach ($languageCodes as $code) {
		$seoHeader[] = sprintf('keyword(%s)', $code);
	}
	$seoSheet->fromArray($seoHeader, null, 'A1', true);

	$filterGroups = [];
	$filters = [];
	$filterGroupMap = [];
	$filterMap = [];
	$categoryFilters = [];
	$productFilters = [];
	$nextFilterGroupId = 1;
	$nextFilterId = 1;

	$productRowIndex = 2;
	$additionalRowIndex = 2;
	$seoRowIndex = 2;
	$seoRegistry = [];

	foreach ($rows as $row) {
		$product = normalizeProductRow($row, $languages, $categoryNames);

		if ($product === null) {
			continue;
		}

		$productsSheet->fromArray($product['sheet'], null, sprintf('A%d', $productRowIndex), true);
		$productRowIndex++;

		foreach ($product['additional_images'] as $imageRow) {
			$additionalImagesSheet->fromArray($imageRow, null, sprintf('A%d', $additionalRowIndex), true);
			$additionalRowIndex++;
		}

		$seoSheet->fromArray(
			buildSeoRow($product['product_id'], $product['seo_keyword'], $languageCodes, $seoRegistry),
			null,
			sprintf('A%d', $seoRowIndex),
			true
		);
		$seoRowIndex++;

		if (!empty($tagDefinitions)) {
			attachFiltersToProduct(
				$product['product_id'],
				$product['category_ids'],
				$product['tags'],
				$tagDefinitions,
				$languages,
				$filterGroups,
				$filterGroupMap,
				$filters,
				$filterMap,
				$categoryFilters,
				$productFilters,
				$nextFilterGroupId,
				$nextFilterId
			);
		}
	}

	writeFilterSheets(
		$spreadsheet,
		$filterGroups,
		$filters,
		$categoryFilters,
		$productFilters,
		$languages
	);

	return $spreadsheet;
}

/**
 * @param array<string> $languageCodes
 * @return array<int,string>
 */
function buildProductHeader(array $languageCodes): array {
	$header = ['product_id'];

	foreach ($languageCodes as $code) {
		$header[] = sprintf('name(%s)', $code);
	}

	$header = array_merge(
		$header,
		[
			'categories',
			'location',
			'quantity',
			'model',
			'manufacturer',
			'image_name',
			'shipping',
			'price',
			'points',
			'date_added',
			'date_modified',
			'date_available',
			'weight',
			'weight_unit',
			'length',
			'width',
			'height',
			'length_unit',
			'status',
			'tax_class_id',
		]
	);

	foreach ($languageCodes as $code) {
		$header[] = sprintf('description(%s)', $code);
	}
	foreach ($languageCodes as $code) {
		$header[] = sprintf('meta_title(%s)', $code);
	}
	foreach ($languageCodes as $code) {
		$header[] = sprintf('meta_description(%s)', $code);
	}
	foreach ($languageCodes as $code) {
		$header[] = sprintf('meta_keywords(%s)', $code);
	}

	$header = array_merge(
		$header,
		[
			'stock_status_id',
			'store_ids',
			'layout',
			'related_ids',
		]
	);

	foreach ($languageCodes as $code) {
		$header[] = sprintf('tags(%s)', $code);
	}

	$header = array_merge(
		$header,
		[
			'sort_order',
			'subtract',
			'minimum',
			'master_id',
			'variant',
			'override'
		]
	);

	return $header;
}

/**
 * @param array<string,string> $row
 * @param array<string,array<string,string>> $languages
 * @param array<string,array<string,string>> $categoryNames
 */
function normalizeProductRow(array $row, array $languages, array $categoryNames): ?array {
	$productId = (int)trim((string)($row['id'] ?? ''));

	if ($productId <= 0) {
		return null;
	}

	$languageCodes = array_keys($languages);
	$sheetRow = [];

	$sheetRow[] = $productId;

	$namesByLanguage = [];
	foreach ($languageCodes as $code) {
		$nameValue = getLocalizedValue($row, $languages, $code, 'name');
		$namesByLanguage[$code] = $nameValue;
		$sheetRow[] = $nameValue;
	}

	$categoryIds = extractCategoryIds($row);
	$sheetRow[] = implode(',', $categoryIds);
	$sheetRow[] = buildLocation($row, $categoryNames);
	$sheetRow[] = (string)((int)($row['availability'] ?? 0));
	$sheetRow[] = trim($row['product_id'] ?? sprintf('P-%d', $productId));
	$sheetRow[] = '';
	$sheetRow[] = formatImagePath($row['primary_image'] ?? '');
	$sheetRow[] = 'true';
	$sheetRow[] = formatNumber($row['price (цена)'] ?? '0');
	$sheetRow[] = '0';
	$sheetRow[] = formatDate($row['created_at'] ?? '');
	$sheetRow[] = formatDate($row['updated_at'] ?? '');
	$sheetRow[] = formatYearDate($row['year'] ?? '', $row['created_at'] ?? '');
	$sheetRow[] = formatNumber($row['weight (вес)'] ?? '0');
	$sheetRow[] = mapWeightUnit($row['weight_class'] ?? '');
	$sheetRow[] = '0';
	$sheetRow[] = '0';
	$sheetRow[] = '0';
	$sheetRow[] = 'cm';
	$sheetRow[] = 'true';
	$sheetRow[] = '0';

	$descriptionsByLanguage = [];
	foreach ($languageCodes as $code) {
		$descriptionValue = getLocalizedValue($row, $languages, $code, 'description');
		$descriptionsByLanguage[$code] = $descriptionValue;
		$sheetRow[] = $descriptionValue;
	}

	foreach ($languageCodes as $code) {
		$sheetRow[] = $namesByLanguage[$code];
	}

	foreach ($languageCodes as $code) {
		$sheetRow[] = $descriptionsByLanguage[$code];
	}

	$tagsByLanguage = [];
	foreach ($languageCodes as $code) {
		$tagValue = getLocalizedValue($row, $languages, $code, 'tags');
		$tagsByLanguage[$code] = $tagValue;
		$sheetRow[] = $tagValue;
	}

	$stockStatus = ((int)($row['availability'] ?? 0) > 0) ? '7' : '5';
	$sheetRow[] = $stockStatus;
	$sheetRow[] = '0';
	$sheetRow[] = '';
	$sheetRow[] = '';

	foreach ($languageCodes as $code) {
		$sheetRow[] = $tagsByLanguage[$code];
	}

	$sheetRow[] = (string)$productId;
	$sheetRow[] = 'true';
	$sheetRow[] = '1';
	$sheetRow[] = '';
	$sheetRow[] = '';
	$sheetRow[] = '';

	$additionalImages = buildAdditionalImages($productId, $row['secondary_images'] ?? '');

	$tagKeys = extractTagKeys($row);

	return [
		'product_id' => $productId,
		'sheet' => $sheetRow,
		'additional_images' => $additionalImages,
		'seo_keyword' => trim($row['seo'] ?? ''),
		'category_ids' => $categoryIds,
		'tags' => $tagKeys
	];
}

/**
 * @return array<int,string>
 */
function extractCategoryIds(array $row): array {
	$categories = [];

	foreach (['category_id', 'subcategory_id'] as $key) {
		$value = trim((string)($row[$key] ?? ''));
		if ($value !== '' && $value !== '0') {
			$categories[] = $value;
		}
	}

	return array_values(array_unique($categories));
}

/**
 * @return array<int,string>
 */
function extractTagKeys(array $row): array {
	$raw = trim((string)($row['tags'] ?? ''));
	if ($raw === '') {
		return [];
	}

	$parts = array_filter(array_map('trim', explode(',', $raw)));

	return array_values(array_unique($parts));
}

/**
 * @param array<string,array<string,string>> $categoryNames
 */
function buildLocation(array $row, array $categoryNames): string {
	$parts = [];

	foreach (['category_id', 'subcategory_id'] as $key) {
		$id = trim((string)($row[$key] ?? ''));
		if ($id === '' || !isset($categoryNames[$id])) {
			continue;
		}

		$name = $categoryNames[$id]['name_uk'] ?? '';
		if ($name !== '') {
			$parts[] = $name;
		}
	}

	if (empty($parts)) {
		return '';
	}

	return implode(' / ', array_unique($parts));
}

function formatImagePath(string $path): string {
	$path = trim($path);
	if ($path === '') {
		return '';
	}
	return $path;
}

function formatNumber(string $value): string {
	$value = str_replace(',', '.', trim($value));
	if ($value === '') {
		return '0';
	}
	return (string)(float)$value;
}

function formatDate(string $value): string {
	$value = trim($value);
	if ($value === '') {
		return date('Y-m-d');
	}

	$timestamp = strtotime($value);
	if ($timestamp === false) {
		return date('Y-m-d');
	}

	return date('Y-m-d', $timestamp);
}

function formatYearDate(string $year, string $fallback): string {
	$year = trim($year);
	if ($year !== '' && ctype_digit($year)) {
		$date = sprintf('%s-01-01', $year);
	} else {
		$date = formatDate($fallback);
	}

	$timestamp = strtotime($date);
	if ($timestamp === false) {
		return '2025-12-01';
	}

	$cutoff = strtotime('2025-12-01');
	if ($timestamp > $cutoff) {
		return '2025-12-01';
	}

	return date('Y-m-d', $timestamp);
}

function mapWeightUnit(string $unit): string {
	$unit = mb_strtolower(trim($unit));

	return match ($unit) {
		'гр', 'г', 'gram', 'grams' => 'g',
		'кг', 'kg' => 'kg',
		'шт', 'sht', 'pcs', 'pieces' => 'pcs',
		default => 'g',
	};
}

/**
 * @return array<string,array<string,string>>
 */
function loadCategoryNames(string $path): array {
	if (!is_file($path)) {
		return [];
	}

	$handle = fopen($path, 'r');

	if ($handle === false) {
		return [];
	}

	$headers = [];
	$categories = [];

	while (($data = fgetcsv($handle)) !== false) {
		if (empty($headers)) {
			$headers = array_map(static fn(string $value): string => ltrim($value, "\u{FEFF}"), $data);
			continue;
		}

		$row = [];
		foreach ($headers as $index => $header) {
			$row[$header] = $data[$index] ?? '';
		}

		$id = trim((string)($row['id'] ?? ''));
		if ($id === '') {
			continue;
		}

		$nameUk = trim($row['name_uk'] ?? '');
		$categories[$id] = [
			'name_uk' => $nameUk,
			'name_en' => $nameUk !== '' ? transliterateToLatin($nameUk) : '',
			'parent_id' => trim($row['parent_id'] ?? '')
		];
	}

	fclose($handle);

	return $categories;
}

/**
 * @param array<string,array<string,string>> $languages
 */
function getLocalizedValue(array $row, array $languages, string $code, string $type): string {
	$field = $languages[$code][$type] ?? '';
	$value = trim($field !== '' ? ($row[$field] ?? '') : '');

	if ($value === '') {
		$fallbackOrder = match ($type) {
			'name' => ['name', 'name_ru'],
			'description' => ['description', 'description_ru'],
			'tags' => ['tags'],
			default => [],
		};

		foreach ($fallbackOrder as $fallback) {
			$value = trim($row[$fallback] ?? '');
			if ($value !== '') {
				break;
			}
		}
	}

	if ($code === 'en-gb' && $value !== '') {
		$value = transliterateToLatin($value);
	}

	return $value;
}

function transliterateToLatin(string $value): string {
	if ($value === '') {
		return '';
	}

	$map = [
		'А' => 'A',  'а' => 'a',
		'Б' => 'B',  'б' => 'b',
		'В' => 'V',  'в' => 'v',
		'Г' => 'H',  'г' => 'h',
		'Ґ' => 'G',  'ґ' => 'g',
		'Д' => 'D',  'д' => 'd',
		'Е' => 'E',  'е' => 'e',
		'Є' => 'Ye', 'є' => 'ie',
		'Ж' => 'Zh', 'ж' => 'zh',
		'З' => 'Z',  'з' => 'z',
		'И' => 'Y',  'и' => 'y',
		'І' => 'I',  'і' => 'i',
		'Ї' => 'Yi', 'ї' => 'i',
		'Й' => 'Y',  'й' => 'i',
		'К' => 'K',  'к' => 'k',
		'Л' => 'L',  'л' => 'l',
		'М' => 'M',  'м' => 'm',
		'Н' => 'N',  'н' => 'n',
		'О' => 'O',  'о' => 'o',
		'П' => 'P',  'п' => 'p',
		'Р' => 'R',  'р' => 'r',
		'С' => 'S',  'с' => 's',
		'Т' => 'T',  'т' => 't',
		'У' => 'U',  'у' => 'u',
		'Ф' => 'F',  'ф' => 'f',
		'Х' => 'Kh', 'х' => 'kh',
		'Ц' => 'Ts', 'ц' => 'ts',
		'Ч' => 'Ch', 'ч' => 'ch',
		'Ш' => 'Sh', 'ш' => 'sh',
		'Щ' => 'Shch', 'щ' => 'shch',
		'Ю' => 'Yu', 'ю' => 'yu',
		'Я' => 'Ya', 'я' => 'ya',
		'Ь' => '',   'ь' => '',
		'Ъ' => '',   'ъ' => '',
		'’' => '',   '\'' => ''
	];

	return strtr($value, $map);
}

/**
 * @return array<string,array<string,array<string,array<string,string>>>>
 */
function loadTagDefinitions(string $path): array {
	if (!is_file($path)) {
		return [];
	}

	$handle = fopen($path, 'r');
	if ($handle === false) {
		return [];
	}

	$headers = [];
	$definitions = [];
	while (($data = fgetcsv($handle)) !== false) {
		if (empty($headers)) {
			$headers = array_map(static fn(string $value): string => ltrim($value, "\u{FEFF}"), $data);
			continue;
		}

		$row = [];
		foreach ($headers as $index => $header) {
			$row[$header] = $data[$index] ?? '';
		}

		$category = trim((string)($row['category'] ?? ''));
		$group = trim($row['group'] ?? '');
		$key = trim($row['key'] ?? '');
		if ($category === '' || $group === '' || $key === '') {
			continue;
		}

		if (!isset($definitions[$category])) {
			$definitions[$category] = [];
		}

		$ua = trim($row['ua'] ?? '');
		$ru = trim($row['ru'] ?? '');
		$baseName = $ua !== '' ? $ua : humanizeLabel($key);

		$definitions[$category][$key] = [
			'group' => $group,
			'key' => $key,
			'names' => [
				'uk-ua' => $baseName,
				'ru-ru' => $ru !== '' ? $ru : $baseName,
				'en-gb' => transliterateToLatin($baseName)
			]
		];
	}

	fclose($handle);

	return $definitions;
}

function attachFiltersToProduct(
	int $productId,
	array $categoryIds,
	array $tags,
	array $tagDefinitions,
	array $languages,
	array &$filterGroups,
	array &$filterGroupMap,
	array &$filters,
	array &$filterMap,
	array &$categoryFilters,
	array &$productFilters,
	int &$nextFilterGroupId,
	int &$nextFilterId
): void {
	if (empty($tags)) {
		return;
	}

	$categoryCandidates = array_values(array_unique(array_filter($categoryIds)));
	if (empty($categoryCandidates)) {
		return;
	}

	foreach ($tags as $tag) {
		foreach ($categoryCandidates as $categoryId) {
			if (!isset($tagDefinitions[$categoryId][$tag])) {
				continue;
			}

			$definition = $tagDefinitions[$categoryId][$tag];
			[$filterGroupId, $filterId] = registerFilterDefinition(
				$definition,
				$languages,
				$filterGroups,
				$filterGroupMap,
				$filters,
				$filterMap,
				$nextFilterGroupId,
				$nextFilterId
			);

			$groupDisplayName = getFilterGroupLabel($definition['group'], 'uk-ua');
			$filterDisplayName = getFilterValueLabel($definition, 'uk-ua');

			registerFilterLink($categoryFilters, (string)$categoryId, $groupDisplayName, $filterDisplayName);
			registerFilterLink($productFilters, (string)$productId, $groupDisplayName, $filterDisplayName);

			break;
		}
	}
}

/**
 * @return array{0:int,1:int}
 */
function registerFilterDefinition(
	array $definition,
	array $languages,
	array &$filterGroups,
	array &$filterGroupMap,
	array &$filters,
	array &$filterMap,
	int &$nextFilterGroupId,
	int &$nextFilterId
): array {
	$groupKey = strtolower($definition['group']);
	if (!isset($filterGroupMap[$groupKey])) {
		$filterGroupMap[$groupKey] = $nextFilterGroupId;
		$filterGroups[] = [
			'id' => $nextFilterGroupId,
			'sort_order' => FILTER_GROUP_LABELS[$groupKey]['sort'] ?? (count($filterGroups) + 1),
			'names' => buildGroupNames($definition['group'], $languages)
		];
		$nextFilterGroupId++;
	}

	$filterKey = $groupKey . '|' . strtolower($definition['key']);
	if (!isset($filterMap[$filterKey])) {
		$filterMap[$filterKey] = $nextFilterId;
		$filters[] = [
			'id' => $nextFilterId,
			'group_id' => $filterGroupMap[$groupKey],
			'sort_order' => count($filters) + 1,
			'names' => buildFilterNames($definition, $languages)
		];
		$nextFilterId++;
	}

	return [$filterGroupMap[$groupKey], $filterMap[$filterKey]];
}

function buildGroupNames(string $group, array $languages): array {
	$key = strtolower($group);
	$names = [];
	foreach (array_keys($languages) as $code) {
		$names[$code] = getFilterGroupLabel($group, $code);
	}
	return $names;
}

function getFilterGroupLabel(string $group, string $language): string {
	$key = strtolower($group);
	$labels = FILTER_GROUP_LABELS[$key]['labels'] ?? [];

	return $labels[$language]
		?? $labels['uk-ua']
		?? $labels['en-gb']
		?? humanizeLabel($group);
}

function buildFilterNames(array $definition, array $languages): array {
	$names = [];
	$definitionNames = $definition['names'] ?? [];
	foreach (array_keys($languages) as $code) {
		$names[$code] = $definitionNames[$code]
			?? $definitionNames['uk-ua']
			?? $definitionNames['en-gb']
			?? humanizeLabel($definition['key']);
	}
	return $names;
}

function getFilterValueLabel(array $definition, string $language): string {
	$names = $definition['names'] ?? [];
	return $names[$language]
		?? $names['uk-ua']
		?? $names['en-gb']
		?? humanizeLabel($definition['key']);
}

/**
 * @param array<string,array<string,array<string,bool>>> $registry
 */
function registerFilterLink(array &$registry, string $entityId, string $groupName, string $filterName): void {
	if ($entityId === '' || $groupName === '' || $filterName === '') {
		return;
	}

	if (!isset($registry[$entityId])) {
		$registry[$entityId] = [];
	}

	if (!isset($registry[$entityId][$groupName])) {
		$registry[$entityId][$groupName] = [];
	}

	$registry[$entityId][$groupName][$filterName] = true;
}

/**
 * @param array<string,array<string,array<string,bool>>> $registry
 * @return array<int,array<int,string>>
 */
function buildFilterAssignmentRows(array $registry): array {
	$rows = [];

	foreach ($registry as $entityId => $groups) {
		ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
		foreach ($groups as $groupName => $filters) {
			$filterNames = array_keys($filters);
			sort($filterNames, SORT_NATURAL | SORT_FLAG_CASE);
			foreach ($filterNames as $filterName) {
				$rows[] = [$entityId, $groupName, $filterName];
			}
		}
	}

	usort(
		$rows,
		static function (array $left, array $right): int {
			$leftId = (int)$left[0];
			$rightId = (int)$right[0];
			$idComparison = $leftId <=> $rightId;
			if ($idComparison !== 0) {
				return $idComparison;
			}

			$groupComparison = strcmp($left[1], $right[1]);
			if ($groupComparison !== 0) {
				return $groupComparison;
			}

			return strcmp($left[2], $right[2]);
		}
	);

	return $rows;
}

function humanizeLabel(string $value): string {
	$value = str_replace(['_', '-'], ' ', $value);
	return ucwords($value);
}

function writeFilterSheets(
	Spreadsheet $spreadsheet,
	array $filterGroups,
	array $filters,
	array $categoryFilters,
	array $productFilters,
	array $languages
): void {
	if (empty($filterGroups)) {
		return;
	}

	$languageCodes = array_keys($languages);

	$categoryFiltersSheet = $spreadsheet->createSheet();
	$categoryFiltersSheet->setTitle('CategoryFilters');
	$categoryFiltersSheet->fromArray(['category_id', 'filter_group', 'filter'], null, 'A1', true);
	$rowIndex = 2;
	$categoryRows = buildFilterAssignmentRows($categoryFilters);
	foreach ($categoryRows as $row) {
		$categoryFiltersSheet->fromArray($row, null, sprintf('A%d', $rowIndex), true);
		$rowIndex++;
	}

	$filterGroupSheet = $spreadsheet->createSheet();
	$filterGroupSheet->setTitle('FilterGroups');
	$groupHeader = ['filter_group_id', 'sort_order'];
	foreach ($languageCodes as $code) {
		$groupHeader[] = sprintf('name(%s)', $code);
	}
	$filterGroupSheet->fromArray($groupHeader, null, 'A1', true);
	$rowIndex = 2;
	foreach ($filterGroups as $group) {
		$row = [$group['id'], $group['sort_order']];
		foreach ($languageCodes as $code) {
			$row[] = $group['names'][$code] ?? '';
		}
		$filterGroupSheet->fromArray($row, null, sprintf('A%d', $rowIndex), true);
		$rowIndex++;
	}

	$filtersSheet = $spreadsheet->createSheet();
	$filtersSheet->setTitle('Filters');
	$filterHeader = ['filter_id', 'filter_group_id', 'sort_order'];
	foreach ($languageCodes as $code) {
		$filterHeader[] = sprintf('name(%s)', $code);
	}
	$filtersSheet->fromArray($filterHeader, null, 'A1', true);
	$rowIndex = 2;
	foreach ($filters as $filter) {
		$row = [$filter['id'], $filter['group_id'], $filter['sort_order']];
		foreach ($languageCodes as $code) {
			$row[] = $filter['names'][$code] ?? '';
		}
		$filtersSheet->fromArray($row, null, sprintf('A%d', $rowIndex), true);
		$rowIndex++;
	}

	$productFiltersSheet = $spreadsheet->createSheet();
	$productFiltersSheet->setTitle('ProductFilters');
	$productFiltersSheet->fromArray(['product_id', 'filter_group', 'filter'], null, 'A1', true);
	$rowIndex = 2;
	$productRows = buildFilterAssignmentRows($productFilters);
	foreach ($productRows as $row) {
		$productFiltersSheet->fromArray($row, null, sprintf('A%d', $rowIndex), true);
		$rowIndex++;
	}
}

function prependCategorySheets(Spreadsheet $destination, string $categoryWorkbookPath): void {
	if (!is_file($categoryWorkbookPath)) {
		return;
	}

	$reader = IOFactory::createReaderForFile($categoryWorkbookPath);
	$categoryWorkbook = $reader->load($categoryWorkbookPath);

	foreach (array_reverse(CATEGORY_WORKSHEETS) as $sheetName) {
		$sourceSheet = $categoryWorkbook->getSheetByName($sheetName);
		if ($sourceSheet === null) {
			continue;
		}

		$existing = $destination->getSheetByName($sheetName);
		if ($existing !== null) {
			$destination->removeSheetByIndex($destination->getIndex($existing));
		}

		$clonedSheet = clone $sourceSheet;
		$clonedSheet->setTitle($sheetName);
		$destination->addSheet($clonedSheet, 0);
	}
}

/**
 * @param array<int,string> $orderedTitles
 */
function reorderWorkbookSheets(Spreadsheet $spreadsheet, array $orderedTitles): void {
	$position = 0;
	foreach ($orderedTitles as $title) {
		$sheet = $spreadsheet->getSheetByName($title);
		if ($sheet === null) {
			continue;
		}

		$currentIndex = $spreadsheet->getIndex($sheet);
		$spreadsheet->removeSheetByIndex($currentIndex);
		$spreadsheet->addSheet($sheet, $position);
		$position++;
	}
}

/**
 * @return array<int,array<int,string>>
 */
function buildAdditionalImages(int $productId, string $images): array {
	$images = trim($images);
	if ($images === '') {
		return [];
	}

	$list = array_values(array_filter(array_map('trim', explode(',', $images))));
	$rows = [];

	foreach ($list as $index => $image) {
		$rows[] = [
			$productId,
			formatImagePath($image),
			(string)($index + 1)
		];
	}

	return $rows;
}

/**
 * @param array<string,array<string,bool>> $registry
 * @return array<int,string>
 */
function buildSeoRow(int $productId, string $keyword, array $languageCodes, array &$registry): array {
	$row = [$productId, '0'];

	foreach ($languageCodes as $code) {
		$unique = ensureUniqueSeoKeyword($keyword, $code, $registry);
		$row[] = $unique;
	}

	return $row;
}

/**
 * @param array<string,array<string,bool>> $registry
 */
function ensureUniqueSeoKeyword(string $keyword, string $languageCode, array &$registry): string {
	$keyword = trim($keyword);
	if ($keyword === '') {
		return '';
	}

	if (!isset($registry[$languageCode])) {
		$registry[$languageCode] = [];
	}

	$candidate = $keyword;
	$counter = 1;

	while (isset($registry[$languageCode][$candidate])) {
		$candidate = $keyword . '-' . (++$counter);
	}

	$registry[$languageCode][$candidate] = true;

	return $candidate;
}
