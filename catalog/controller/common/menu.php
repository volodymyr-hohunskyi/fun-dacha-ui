<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Class Menu
 *
 * Builds super-categories (Овочі, Фрукти, Квіти, Добрива, Супутні товари) and maps
 * DB categories to them. On hover, shows dropdown with DB categories for that super category.
 *
 * @package Opencart\Catalog\Controller\Common
 */
class Menu extends \Opencart\System\Engine\Controller {
	/** Super category keys - fixed set */
	private const SUPER_OVOCHI = 'ovochi';       // Овочі
	private const SUPER_BASHTANNI = 'bashtanni';   // Баштанні
	private const SUPER_GARBUZOVI = 'garbuzovi';   // Гарбузові
	private const SUPER_BOBOVI = 'bobovi';         // Бобові
	private const SUPER_ZELEN = 'zelen';           // Зелень
	private const SUPER_KVITY = 'kvity';        // Квіти
	private const SUPER_DOBRYVA = 'dobryva';   // Добрива та захист
	private const SUPER_SUPUTNI = 'suputni';    // Супутні товари

	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('common/menu');

		$this->load->model('catalog/category');
		$this->load->model('catalog/product');

		$data['super_categories'] = $this->buildSuperCategories();

		return $this->load->view('common/menu', $data);
	}

	/**
	 * Build super categories with DB categories grouped by keyword matching.
	 *
	 * @return array<int, array{key: string, name: string, href: string, children: array}>
	 */
	private function buildSuperCategories(): array {
		$superDefs = [
			self::SUPER_OVOCHI => [
				'name' => 'Овочі',
				'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=0'),
				'keywords' => [
					'томат', 'помідор',
					'огір',
					'капуст',
					'редис',
					'перець',
					'баклажан',
					'цибул',
					'моркв',
					'буряк',
					'кукурудз',
				],
			],
			self::SUPER_BASHTANNI => [
				'name' => 'Баштанні',
				'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=0'),
				'keywords' => [
					'кавун',
					'дин',
					'гарбуз',
				],
			],
			self::SUPER_GARBUZOVI => [
				'name' => 'Гарбузові',
				'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=0'),
				'keywords' => [
					'кабач',
					'патисон',
				],
			],
			self::SUPER_BOBOVI => [
				'name' => 'Бобові',
				'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=0'),
				'keywords' => [
					'квасол',
					'бобов',
					'горох',
				],
			],
			self::SUPER_ZELEN => [
				'name' => 'Зелень',
				'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=0'),
				'keywords' => [
					'зелен',
					'пряно',
					'кріп',
					'петруш',
					'базил',
					'салат',
					'шпинат',
					'кінз',
				],
			],
			self::SUPER_KVITY => [
				'name' => 'Квіти',
				'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=0'),
				'keywords' => [
					'квіт',
					'flower',
				],
			],
			self::SUPER_DOBRYVA => [
				'name' => 'Добрива та захист',
				'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=0'),
				'keywords' => [
					'добрив',
					'удобрен',
					'гумус',
					'компост',
					'субстрат',
					'грунт',
					'земля',
					'захист',
					'препарат',
				],
			],
			self::SUPER_SUPUTNI => [
				'name' => 'Супутні товари',
				'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=0'),
				'keywords' => [
					'інструмент',
					'інвентар',
					'аксесуар',
					'тара',
					'горщик',
					'касета',
					'контейнер',
				],
			],
		];

		$allCategories = $this->collectAllCategories();
		$assigned = [];

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

		// everything else → Супутні товари
		foreach ($allCategories as $cat) {
			if (isset($assigned[$cat['category_id']])) {
				continue;
			}

			$superDefs[self::SUPER_SUPUTNI]['children'][] = $cat;
		}

		$result = [];

		foreach ($superDefs as $key => $def) {
			$result[] = [
				'key'      => $key,
				'name'     => $def['name'],
				'href'     => $this->getFirstCategoryHref($def['children']) ?: $def['href'],
				'children' => $def['children'],
			];
		}

		return $result;
	}

	/**
	 * Collect all categories (top-level + children) as flat list with href.
	 *
	 * @return array<int, array{category_id: int, name: string, href: string}>
	 */
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
}
