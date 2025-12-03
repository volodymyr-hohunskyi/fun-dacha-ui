#!/usr/bin/env php
<?php
declare(strict_types=1);

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
const PRODUCT_LANGUAGES = [
	'en-gb' => [
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
const CATEGORY_SOURCE = __DIR__ . '/../shared/data/categories_list.csv';

[$source, $target] = resolveArguments($argv ?? []);
$rows = readCsv($source);
$categoryNames = loadCategoryNames(CATEGORY_SOURCE);

if (empty($rows)) {
	throw new RuntimeException(sprintf('No product rows found in %s', $source));
}

$spreadsheet = buildProductWorkbook($rows, PRODUCT_LANGUAGES, $categoryNames);
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
 */
function buildProductWorkbook(array $rows, array $languages, array $categoryNames): Spreadsheet {
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
	}

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

	$sheetRow[] = buildCategoryString($row);
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

	return [
		'product_id' => $productId,
		'sheet' => $sheetRow,
		'additional_images' => $additionalImages,
		'seo_keyword' => trim($row['seo'] ?? '')
	];
}

function buildCategoryString(array $row): string {
	$categories = [];

	foreach (['category_id', 'subcategory_id'] as $key) {
		$value = trim((string)($row[$key] ?? ''));
		if ($value !== '' && $value !== '0') {
			$categories[] = $value;
		}
	}

	return implode(',', $categories);
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

		$name = $categoryNames[$id]['name'] ?? '';
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
		return sprintf('%s-01-01', $year);
	}

	return formatDate($fallback);
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

		$categories[$id] = [
			'name' => trim($row['name_uk'] ?? ''),
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

	if (class_exists('\Transliterator')) {
		$transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');
		if ($transliterator) {
			return $transliterator->transliterate($value);
		}
	}

	$converted = iconv('UTF-8', 'ASCII//TRANSLIT', $value);
	if ($converted !== false && $converted !== '') {
		return $converted;
	}

	return $value;
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
