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

[$source, $target, $language] = resolveArguments($argv ?? []);

$rows = readCsv($source);

if (empty($rows)) {
	throw new RuntimeException(sprintf('No category rows found in %s', $source));
}

$spreadsheet = buildWorkbook($rows, $language);
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
 */
function buildWorkbook(array $rows, string $language): Spreadsheet {
	$spreadsheet = new Spreadsheet();
	$categoriesSheet = $spreadsheet->getActiveSheet();
	$categoriesSheet->setTitle('Categories');

$categoryHeader = [
		'category_id',
		'parent_id',
		sprintf('name(%s)', $language),
		'sort_order',
		'image_name',
		sprintf('description(%s)', $language),
		sprintf('meta_title(%s)', $language),
		sprintf('meta_description(%s)', $language),
		sprintf('meta_keywords(%s)', $language),
		'store_ids',
		'layout',
		'status',
	];

	$categoriesSheet->fromArray($categoryHeader, null, 'A1', true);

$seoSheet = $spreadsheet->createSheet();
$seoSheet->setTitle('CategorySEOKeywords');
$seoHeader = [
	'category_id',
	'store_id',
	sprintf('keyword(%s)', $language),
];
$seoSheet->fromArray($seoHeader, null, 'A1', true);

$categoryRowIndex = 2;
$seoRowIndex = 2;
$keywordRegistry = [];

foreach ($rows as $row) {
		$categoryId = trim($row['id'] ?? '');
		if ($categoryId === '') {
			continue;
		}

		$name = trim($row['name_uk'] ?? '');
		$desc = trim($row['description_uk'] ?? '');
		$image = trim($row['primary_image'] ?? '');
		$parentId = trim($row['parent_id'] ?? '');
		$seoKeyword = trim($row['seo'] ?? '');

		$categoriesSheet->fromArray(
			[
				$categoryId,
				$parentId === '' ? '0' : $parentId,
				$name,
				(string)($categoryRowIndex - 1),
				$image,
				$desc,
				$name,
				$desc,
				'',
				DEFAULT_STORE_IDS,
				'',
				'true',
			],
			null,
			sprintf('A%d', $categoryRowIndex),
			true
		);

	$uniqueKeyword = ensureUniqueKeyword($seoKeyword, $categoryId, $language, DEFAULT_STORE_IDS, $keywordRegistry);

	if ($uniqueKeyword !== '') {
			$seoSheet->fromArray(
				[
					$categoryId,
					DEFAULT_STORE_IDS,
				$uniqueKeyword,
				],
				null,
				sprintf('A%d', $seoRowIndex),
				true
			);
			$seoRowIndex++;
		}

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
