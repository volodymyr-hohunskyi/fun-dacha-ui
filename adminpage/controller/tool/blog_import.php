<?php
namespace Opencart\Admin\Controller\Tool;
/**
 * Blog Import - imports Topics and Articles from Excel (blog_import.xlsx).
 * Use via: index.php?route=tool/blog_import.import&user_token=YOUR_TOKEN
 *
 * Excel: shared/import/blog_import.xlsx or shared/data/blog_import.xlsx
 * Column P (product_links): optional; appended to HTML description (see appendBlogProductLinksHtml).
 */
class BlogImport extends \Opencart\System\Engine\Controller {
	public function import(): void {
		$json = [];
		if (!$this->user->hasPermission('modify', 'cms/article')) {
			$json['error'] = 'Permission denied';
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}
		$candidates = [
			DIR_OPENCART . 'shared/import/blog_import.xlsx',
			DIR_OPENCART . 'shared/data/blog_import.xlsx',
		];
		$excel_path = '';

		foreach ($candidates as $p) {
			if (is_file($p)) {
				$excel_path = $p;
				break;
			}
		}

		if ($excel_path === '') {
			$json['error'] = 'File not found: place blog_import.xlsx in shared/import/ or shared/data/';
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}
		require_once DIR_EXTENSION . 'export_import/system/library/export_import/vendor/autoload.php';
		$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
		$spreadsheet = $reader->load($excel_path);
		$topic_name_to_id = [];
		$this->load->model('cms/topic');
		$this->load->model('cms/article');
		$this->load->model('localisation/language');
		$languages = $this->model_localisation_language->getLanguages();
		$lang_code_to_id = array_column($languages, 'language_id', 'code');
		$existing_topics = $this->model_cms_topic->getTopics();
		foreach ($existing_topics as $t) {
			$descs = $this->model_cms_topic->getDescriptions($t['topic_id']);
			foreach ($descs as $lid => $d) {
				$topic_name_to_id[$d['name']] = $t['topic_id'];
			}
		}
		$articles_sheet = $spreadsheet->getSheetByName('Articles');
		if (!$articles_sheet) {
			$json['error'] = 'Articles sheet not found';
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}
		$imported = 0;
		$errors = [];
		$maxRow = $articles_sheet->getHighestRow();
		for ($r = 2; $r <= $maxRow; $r++) {
			$topic_name = trim($articles_sheet->getCell('B' . $r)->getValue());
			$topic_id = $topic_name_to_id[$topic_name] ?? null;
			if (!$topic_id) {
				$errors[] = "Row $r: Topic not found: $topic_name";
				continue;
			}
			$name = $articles_sheet->getCell('C' . $r)->getValue();
			$description = $articles_sheet->getCell('D' . $r)->getValue();
			$image = $articles_sheet->getCell('E' . $r)->getValue();
			$author = $articles_sheet->getCell('F' . $r)->getValue();
			$status = (int) $articles_sheet->getCell('G' . $r)->getValue();
			$store_id = (int) $articles_sheet->getCell('H' . $r)->getValue();
			$language_id = (int) $articles_sheet->getCell('I' . $r)->getValue();
			$meta_title = $articles_sheet->getCell('J' . $r)->getValue();
			$meta_description = $articles_sheet->getCell('K' . $r)->getValue();
			$meta_keyword = $articles_sheet->getCell('L' . $r)->getValue();
			$tag = $articles_sheet->getCell('M' . $r)->getValue();
			$product_links_cell = $articles_sheet->getCell('P' . $r)->getValue();
			$seo_keyword = trim($articles_sheet->getCell('O' . $r)->getValue());
			if (!$language_id && $languages) {
				$language_id = (int) array_values($languages)[0]['language_id'];
			}
			$description = (string) $description;
			$description .= $this->appendBlogProductLinksHtml($product_links_cell);
			$article_data = [
				'topic_id' => $topic_id,
				'author' => $author ?: 'Гогунська Людмила',
				'status' => $status ?: 1,
				'article_description' => [
					$language_id => [
						'image' => $image ?: '',
						'name' => $name ?: 'Article',
						'description' => $description ?: '',
						'tag' => $tag ?: '',
						'meta_title' => $meta_title ?: '',
						'meta_description' => $meta_description ?: '',
						'meta_keyword' => $meta_keyword ?: '',
					],
				],
				'article_store' => [$store_id ?: 0],
				'article_seo_url' => [
					$store_id ?: 0 => [$language_id => $seo_keyword ?: 'article-' . $r],
				],
			];
			try {
				$this->model_cms_article->addArticle($article_data);
				$imported++;
			} catch (\Throwable $e) {
				$errors[] = "Row $r: " . $e->getMessage();
			}
		}
		$json['success'] = "Imported $imported articles.";
		if ($errors) {
			$json['errors'] = $errors;
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Column P `product_links`: lines of `https://...|Anchor text` or plain URL (anchor = URL).
	 * Appends a small HTML block to the article body for internal links to products/categories.
	 *
	 * @param mixed $cell
	 */
	private function appendBlogProductLinksHtml($cell): string {
		if ($cell === null || $cell === '') {
			return '';
		}

		$raw = trim((string) $cell);

		if ($raw === '') {
			return '';
		}

		$lines = preg_split('/\r\n|\n|\r/', $raw);
		$items = [];

		foreach ($lines as $line) {
			$line = trim($line);

			if ($line === '') {
				continue;
			}

			$href = $line;
			$label = $line;

			if (str_contains($line, '|')) {
				$parts = explode('|', $line, 2);
				$href = trim($parts[0]);
				$label = trim($parts[1] ?? $href);
			}

			if ($href === '') {
				continue;
			}

			$items[] = '<li><a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a></li>';
		}

		if ($items === []) {
			return '';
		}

		return '<div class="blog-product-links"><p><strong>Корисні посилання каталогу</strong></p><ul>'
			. implode('', $items) . '</ul></div>';
	}
}
