<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Class Menu
 *
 * Navigation: Овочі, Зелень, Квіти, Добрива та захист, Супутні товари, Блог, Знижки.
 * Blog dropdown shows articles. Product categories grouped by keyword matching.
 *
 * @package Opencart\Catalog\Controller\Common
 */
class Menu extends \Opencart\System\Engine\Controller {
	private const SUPER_OVOCHI = 'ovochi';       // Овочі
	private const SUPER_ZELEN = 'zelen';         // Зелень
	private const SUPER_KVITY = 'kvity';         // Квіти
	private const SUPER_DOBRYVA = 'dobryva';     // Добрива та захист
	private const SUPER_SUPUTNI = 'suputni';     // Супутні товари
	private const SUPER_BLOG = 'blog';            // Блог (articles dropdown)
	private const SUPER_ZNIZHKY = 'znizhky';     // Знижки

	public function index(): string {
		$this->load->language('common/menu');

		$this->load->model('catalog/category');
		$this->load->model('catalog/product');

		$data['super_categories'] = $this->buildSuperCategories();

		return $this->load->view('common/menu', $data);
	}

	private function buildSuperCategories(): array {
		$allCategories = $this->collectAllCategories();
		$assigned = [];

		// Product super categories
		$superDefs = [
			self::SUPER_OVOCHI => [
				'name' => 'Овочі',
				'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=0'),
				'keywords' => [
					'томат', 'помідор', 'огір', 'капуст', 'редис', 'перець', 'баклажан', 'цибул', 'моркв', 'буряк', 'кукурудз',
					'кавун', 'дин', 'гарбуз', 'кабач', 'патисон', 'квасол', 'бобов', 'горох',
				],
			],
			self::SUPER_ZELEN => [
				'name' => 'Зелень',
				'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=0'),
				'keywords' => ['зелен', 'пряно', 'кріп', 'петруш', 'базил', 'салат', 'шпинат', 'кінз'],
			],
			self::SUPER_KVITY => [
				'name' => 'Квіти',
				'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=0'),
				'keywords' => ['квіт', 'flower'],
			],
			self::SUPER_DOBRYVA => [
				'name' => 'Добрива та захист',
				'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=0'),
				'keywords' => [
					'добрив', 'удобрен', 'гумус', 'компост', 'субстрат', 'грунт', 'земля', 'захист',
					'препарат', 'гербіцид', 'інсектицид', 'фунгіцид', 'прилипач',
				],
			],
			self::SUPER_SUPUTNI => [
				'name' => 'Супутні товари',
				'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=0'),
				'keywords' => ['інструмент', 'інвентар', 'аксесуар', 'тара', 'горщик', 'касета', 'контейнер'],
				'no_dropdown' => true,
			],
		];

		foreach ($superDefs as $key => $def) {
			$children = [];
			foreach ($allCategories as $cat) {
				if (isset($assigned[$cat['category_id']])) {
					continue;
				}
				$nameLower = mb_strtolower($cat['name']);
				foreach ($def['keywords'] as $kw) {
					if (mb_strpos($nameLower, mb_strtolower($kw)) !== false) {
						$children[] = $cat;
						$assigned[$cat['category_id']] = true;
						break;
					}
				}
			}
			$superDefs[$key]['children'] = $children;
		}

		foreach ($allCategories as $cat) {
			if (!isset($assigned[$cat['category_id']])) {
				$superDefs[self::SUPER_SUPUTNI]['children'][] = $cat;
			}
		}

		$result = [];

		foreach ($superDefs as $key => $def) {
			$href = $def['href'];
			if (!empty($def['no_dropdown'])) {
				$href = $this->findCategoryHrefByName($allCategories, 'супутні') ?: $href;
				$children = [];
			} else {
				$href = $this->getFirstCategoryHref($def['children']) ?: $href;
				$children = $def['children'];
			}
			$result[] = [
				'key'         => $key,
				'name'        => $def['name'],
				'href'        => $href,
				'children'    => $children,
				'no_dropdown' => !empty($def['no_dropdown']),
			];
		}

		// Блог – dropdown: topics only, main link clickable
		$blogChildren = $this->getBlogTopics();
		$result[] = [
			'key'            => self::SUPER_BLOG,
			'name'           => 'Блог',
			'href'           => $this->url->link('cms/blog', 'language=' . $this->config->get('config_language')),
			'children'       => $blogChildren,
			'no_dropdown'    => empty($blogChildren),
			'link_clickable' => true,
		];

		// Знижки – no dropdown
		$result[] = [
			'key'         => self::SUPER_ZNIZHKY,
			'name'        => 'Знижки',
			'href'        => $this->url->link('product/special', 'language=' . $this->config->get('config_language')),
			'children'    => [],
			'no_dropdown' => true,
		];

		return $result;
	}

	private function getBlogTopics(): array {
		$this->load->model('cms/topic');
		$topics = $this->model_cms_topic->getTopics();
		$children = [];
		foreach ($topics as $topic) {
			$children[] = [
				'name' => $topic['name'],
				'href' => $this->url->link('cms/blog', 'language=' . $this->config->get('config_language') . '&topic_id=' . $topic['topic_id']),
			];
		}
		return $children;
	}

	private function collectAllCategories(): array {
		$out = [];
		$top = $this->model_catalog_category->getCategories(0);

		foreach ($top as $cat) {
			$out[] = [
				'category_id' => (int)$cat['category_id'],
				'name'        => $cat['name'],
				'href'        => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $cat['category_id']),
			];

			$children = $this->model_catalog_category->getCategories($cat['category_id']);
			foreach ($children as $child) {
				$filter_data = [
					'filter_category_id'  => $child['category_id'],
					'filter_sub_category' => true,
				];
				$count = $this->config->get('config_product_count') ? ' (' . $this->model_catalog_product->getTotalProducts($filter_data) . ')' : '';
				$out[] = [
					'category_id' => (int)$child['category_id'],
					'name'        => $child['name'] . $count,
					'href'        => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $cat['category_id'] . '_' . $child['category_id']),
				];
			}
		}

		return $out;
	}

	private function getFirstCategoryHref(array $children): ?string {
		return $children[0]['href'] ?? null;
	}

	private function findCategoryHrefByName(array $categories, string $keyword): ?string {
		$kw = mb_strtolower($keyword);
		foreach ($categories as $cat) {
			if (mb_strpos(mb_strtolower($cat['name']), $kw) !== false) {
				return $cat['href'];
			}
		}
		return null;
	}
}
