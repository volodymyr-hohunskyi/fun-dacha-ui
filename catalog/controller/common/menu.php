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
	private const SUPER_OVOCHI = 'ovochi';      // Овочі (Vegetables)
	private const SUPER_FRUKTY = 'fruity';     // Фрукти (Fruits)
	private const SUPER_KVITY = 'kvity';       // Квіти (Flowers/Plants) - Кріти in user spec
	private const SUPER_DOBRYVA = 'dobryva';   // Добрива (Fertilizers)
	private const SUPER_SUPUTNI = 'suputni';   // Супутні товари (Related/Accessories)

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
				'keywords' => ['овоч', 'vegetable', 'томат', 'tomato', 'картопл', 'potato', 'моркв', 'carrot', 'огір', 'cucumber', 'кабач', 'zucchini', 'перець', 'pepper', 'буряк', 'beet', 'цибул', 'onion', 'часник', 'garlic', 'салат', 'lettuce', 'капуст', 'cabbage', 'баклажан', 'eggplant', 'редис', 'radish', 'горох', 'pea', 'біб', 'bean', 'помідор', 'картопля', 'морква', 'капуста'],
			],
			self::SUPER_FRUKTY => [
				'name' => 'Фрукти',
				'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=0'),
				'keywords' => ['фрукт', 'fruit', 'яблук', 'apple', 'апельсин', 'orange', 'лимон', 'lemon', 'банан', 'banana', 'вишн', 'cherry', 'чорниц', 'blueberry', 'малин', 'raspberry', 'полун', 'strawberry', 'слив', 'plum', 'груш', 'pear', 'виногр', 'grape', 'персик', 'peach', 'абрикос', 'apricot', 'авокадо', 'avocado', 'диня', 'melon', 'кавун', 'watermelon', 'гранат', 'pomegranate', 'ківі', 'kiwi', 'манго', 'mango', 'ананас', 'pineapple'],
			],
			self::SUPER_KVITY => [
				'name' => 'Кріти',
				'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=0'),
				'keywords' => ['квіт', 'flower', 'plant', 'рослин', 'дерев', 'tree', 'кущ', 'bush', 'сад', 'garden', 'насіння', 'seed', 'flower', 'бульб', 'bulb', 'розсада', 'розсади', 'plant', 'оранжере', 'greenhouse'],
			],
			self::SUPER_DOBRYVA => [
				'name' => 'Добрива',
				'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=0'),
				'keywords' => ['добрив', 'fertilizer', 'удобрен', 'гумус', 'humus', 'компост', 'compost', 'органік', 'organic', 'мінерал', 'mineral', 'субстрат', 'substrate', 'грунт', 'soil', 'земл', 'земля'],
			],
			self::SUPER_SUPUTNI => [
				'name' => 'Супутні товари',
				'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=0'),
				'keywords' => ['інструмент', 'tool', 'інвентар', 'equipment', 'гірк', 'pot', 'посуд', 'тара', 'аксесуар', 'accessory', 'супут', 'related', 'допоміжн', 'інше', 'other', 'засіб', 'препарат', 'захист', 'protection'],
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

		// Unassigned categories go to Супутні товари
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
