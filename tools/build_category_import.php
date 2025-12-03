#!/usr/bin/env php
<?php
declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__ . '/../extension/export_import/system/library/export_import/vendor/autoload.php';

/**
 * Helper script that converts shared/data/categories_list.csv into an
 * OpenCart export_import compatible XLSX workbook (Categories + CategorySEOKeywords sheets).
 *
 * Usage:
 *   php tools/build_category_import.php [source_csv] [target_xlsx] [language_code]
 *
 * Defaults:
 *   source_csv    => shared/data/categories_list.csv
 *   target_xlsx   => shared/data/categories_import.xlsx
 *   language_code => en-gb
 */

const DEFAULT_SOURCE = __DIR__ . '/../shared/data/categories_list.csv';
const DEFAULT_TARGET = __DIR__ . '/../shared/data/categories_import.xlsx';
const DEFAULT_LANGUAGE = 'en-gb';
const DEFAULT_STORE_IDS = '0';
const CATEGORY_LANGUAGES = ['en-gb', 'uk-ua'];

[$source, $target, $primaryLanguage] = resolveArguments($argv ?? []);
$languages = array_values(array_unique(array_merge([$primaryLanguage], CATEGORY_LANGUAGES)));

$rows = readCsv($source);

if (empty($rows)) {
	throw new RuntimeException(sprintf('No category rows found in %s', $source));
}

$spreadsheet = buildWorkbook($rows, $languages);
$writer = new Xlsx($spreadsheet);
$targetDir = dirname($target);

if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
	throw new RuntimeException(sprintf('Unable to create target directory %s', $targetDir));
}

$writer->save($target);
printf("Wrote %d categories to %s\n", count($rows), $target);

/**
 * @param array<int,string> $argv
 * @return array{0:string,1:string,2:string}
 */
function resolveArguments(array $argv): array {
	$source = $argv[1] ?? DEFAULT_SOURCE;
	$target = $argv[2] ?? DEFAULT_TARGET;
	$language = isset($argv[3]) ? strtolower(trim((string)$argv[3])) : DEFAULT_LANGUAGE;

	if (!is_file($source)) {
		throw new InvalidArgumentException(sprintf('Source CSV not found: %s', $source));
	}

	return [$source, $target, $language ?: DEFAULT_LANGUAGE];
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
 * @param array<int,string> $languages
 */
function buildWorkbook(array $rows, array $languages): Spreadsheet {
	$spreadsheet = new Spreadsheet();
	$categoriesSheet = $spreadsheet->getActiveSheet();
	$categoriesSheet->setTitle('Categories');
	$categoryHeader = ['category_id', 'parent_id'];
	foreach ($languages as $code) {
		$categoryHeader[] = sprintf('name(%s)', $code);
	}
	$categoryHeader[] = 'sort_order';
	$categoryHeader[] = 'image_name';
	foreach ($languages as $code) {
		$categoryHeader[] = sprintf('description(%s)', $code);
	}
	foreach ($languages as $code) {
		$categoryHeader[] = sprintf('meta_title(%s)', $code);
	}
	foreach ($languages as $code) {
		$categoryHeader[] = sprintf('meta_description(%s)', $code);
	}
	foreach ($languages as $code) {
		$categoryHeader[] = sprintf('meta_keywords(%s)', $code);
	}
	$categoryHeader[] = 'store_ids';
	$categoryHeader[] = 'layout';
	$categoryHeader[] = 'status';

	$categoriesSheet->fromArray($categoryHeader, null, 'A1', true);

	$seoSheet = $spreadsheet->createSheet();
	$seoSheet->setTitle('CategorySEOKeywords');
	$seoHeader = ['category_id', 'store_id'];
	foreach ($languages as $code) {
		$seoHeader[] = sprintf('keyword(%s)', $code);
	}
	$seoSheet->fromArray($seoHeader, null, 'A1', true);

$categoryRowIndex = 2;
$seoRowIndex = 2;
$keywordRegistry = [];

foreach ($rows as $row) {
		$categoryId = trim($row['id'] ?? '');
		if ($categoryId === '') {
			continue;
		}

		$nameByLanguage = [];
		$descByLanguage = [];
		foreach ($languages as $code) {
			$nameByLanguage[$code] = getLocalizedCategoryValue($row, $code, 'name');
			$descByLanguage[$code] = getLocalizedCategoryValue($row, $code, 'description');
		}
		$image = trim($row['primary_image'] ?? '');
		$parentId = trim($row['parent_id'] ?? '');
		$seoKeyword = trim($row['seo'] ?? '');

		$categoriesSheet->fromArray(
			[
				$categoryId,
				$parentId === '' ? '0' : $parentId,
				...array_values($nameByLanguage),
				(string)($categoryRowIndex - 1),
				$image,
				...array_values($descByLanguage),
				...array_values($nameByLanguage),
				...array_values($descByLanguage),
				...array_fill(0, count($languages), ''),
				DEFAULT_STORE_IDS,
				'',
				'true',
			],
			null,
			sprintf('A%d', $categoryRowIndex),
			true
		);

		$seoRow = [$categoryId, DEFAULT_STORE_IDS];
		foreach ($languages as $code) {
			$seoRow[] = ensureUniqueKeyword($seoKeyword, $categoryId, $code, DEFAULT_STORE_IDS, $keywordRegistry);
		}
		$seoSheet->fromArray($seoRow, null, sprintf('A%d', $seoRowIndex), true);
		$seoRowIndex++;

		$categoryRowIndex++;
	}

return $spreadsheet;
}

function ensureUniqueKeyword(string $keyword, string $categoryId, string $language, string $storeId, array &$registry): string {
	$keyword = trim($keyword);

	if ($keyword === '') {
		return '';
	}

	$registryKey = $storeId . '|' . $language;

	if (!isset($registry[$registryKey])) {
		$registry[$registryKey] = [];
	}

	$candidate = $keyword;
	$counter = 1;

	while (isset($registry[$registryKey][$candidate])) {
		$suffix = ($counter === 1) ? '-' . $categoryId : '-' . $categoryId . '-' . $counter;
		$candidate = $keyword . $suffix;
		$counter++;
	}

	$registry[$registryKey][$candidate] = true;

	return $candidate;
}

function getLocalizedCategoryValue(array $row, string $code, string $type): string {
	$fieldMap = [
		'uk-ua' => [
			'name' => 'name_uk',
			'description' => 'description_uk',
		],
		'en-gb' => [
			'name' => '',
			'description' => '',
		],
	];

	$field = $fieldMap[$code][$type] ?? '';
	$value = trim($field !== '' ? ($row[$field] ?? '') : '');

	if ($value === '') {
		$fallback = $type === 'description' ? 'description_uk' : 'name_uk';
		$value = trim($row[$fallback] ?? '');
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
