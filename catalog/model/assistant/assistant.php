<?php
namespace Opencart\Catalog\Model\Assistant;
/**
 * Heuristic assistant over shared/assistant/assistant_db.json
 *
 * @package Opencart\Catalog\Model\Assistant
 */
class Assistant extends \Opencart\System\Engine\Model {
	/**
	 * @var array<string, mixed>|null
	 */
	private static ?array $db = null;

	/**
	 * Storefront navigation URLs + category rows (set by controller).
	 *
	 * @var array<string, mixed>
	 */
	private array $navUrls = [];

	public function setNavUrls(array $urls): void {
		$this->navUrls = $urls;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getDb(): array {
		if (self::$db !== null) {
			return self::$db;
		}

		$path = DIR_SYSTEM . '../shared/assistant/assistant_db.json';

		if (!is_file($path)) {
			self::$db = [];
		} else {
			$json = file_get_contents($path);
			$data = json_decode($json, true);
			self::$db = is_array($data) ? $data : [];
		}

		$overlayPath = DIR_STORAGE . 'assistant/assistant_info.json';

		if (is_file($overlayPath)) {
			$oj = file_get_contents($overlayPath);
			$overlay = json_decode($oj, true);

			if (is_array($overlay) && isset($overlay['info']) && is_array($overlay['info'])) {
				if (!isset(self::$db['info']) || !is_array(self::$db['info'])) {
					self::$db['info'] = [];
				}

				foreach ($overlay['info'] as $k => $v) {
					if (is_array($v)) {
						self::$db['info'][$k] = $v;
					}
				}
			}
		}

		return self::$db;
	}

	/**
	 * @param mixed $field
	 */
	private function resolveInfoLocalizedField($field, string $lang): string {
		if (is_array($field)) {
			if (isset($field[$lang])) {
				return (string) $field[$lang];
			}

			if (isset($field['uk-ua'])) {
				return (string) $field['uk-ua'];
			}

			if (isset($field['en-gb'])) {
				return (string) $field['en-gb'];
			}

			$first = reset($field);

			return is_string($first) ? $first : '';
		}

		return (string) $field;
	}

	public function getTopCategories(int $limit = 8): array {
		$db     = $this->getDb();
		$emojis = $this->getCategoryEmojis();
		$result = [];

		foreach ($db['categoryTree'] ?? [] as $cat) {
			$result[] = [
				'id'    => $cat['id'],
				'name'  => $cat['name']['uk'] ?? $cat['name']['en'] ?? '',
				'slug'  => $cat['slug'] ?? '',
				'emoji' => $emojis[$cat['id']] ?? '🌱',
			];
			if (count($result) >= $limit) {
				break;
			}
		}

		return $result;
	}

	/**
	 * @return array<int, string>
	 */
	private function getCategoryEmojis(): array {
		return [
			100 => '🍅', 110 => '🥒', 120 => '🥬', 130 => '🌱',
			140 => '🌶', 150 => '🫆', 160 => '🧅', 170 => '🥕',
			180 => '🟣', 190 => '🍉', 200 => '🍈', 210 => '🎃',
			220 => '🥦', 230 => '🟡', 240 => '🌿', 250 => '🌾',
			270 => '🫘', 280 => '🫘', 290 => '🌽', 330 => '🌸',
			400 => '🧪', 500 => '🛒',
		];
	}

	public function getProductsByFilters(array $filterIds, ?int $categoryId = null): array {
		$db  = $this->getDb();
		$ids = null;

		foreach ($filterIds as $fid) {
			$set = array_flip($db['indexes']['productsByFilter'][(string) $fid] ?? []);
			$ids = $ids === null ? $set : array_intersect_key($ids, $set);
		}

		if ($categoryId !== null) {
			$catSet = array_flip($db['indexes']['productsByCategory'][(string) $categoryId] ?? []);
			$ids    = $ids === null ? $catSet : array_intersect_key($ids, $catSet);
		}

		if ($ids === null) {
			return [];
		}

		return array_map(
			fn($id) => $db['products'][(string) $id] ?? null,
			array_keys($ids)
		);
	}

	public function searchProducts(string $query): array {
		$db     = $this->getDb();
		$tokens = preg_split('/[\s,\-]+/u', mb_strtolower($query));
		$tokens = array_filter($tokens, fn($t) => mb_strlen($t) > 2);

		if (!$tokens) {
			return [];
		}

		$unionIds = [];

		foreach ($tokens as $token) {
			$found      = $db['indexes']['productSearch'][$token] ?? [];
			$unionIds   = array_merge($unionIds, $found);
		}

		$uniqueIds = array_unique($unionIds);

		return array_map(fn($id) => $db['products'][(string) $id] ?? null, $uniqueIds);
	}

	/**
	 * @param array<int, mixed> $products
	 *
	 * @return array<int, mixed>
	 */
	public function rankProducts(array $products): array {
		$products = array_filter($products);
		usort($products, function ($a, $b) {
			$aStock = ($a['stockStatus'] === 7) ? 0 : 1;
			$bStock = ($b['stockStatus'] === 7) ? 0 : 1;

			if ($aStock !== $bStock) {
				return $aStock - $bStock;
			}

			return ($a['price'] ?: 999) <=> ($b['price'] ?: 999);
		});

		return $products;
	}

	/**
	 * @param array<int, int|string> $productIds
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getProductCards(array $productIds): array {
		$db    = $this->getDb();
		$cards = [];

		foreach (array_slice($productIds, 0, 5) as $id) {
			$p = $db['products'][(string) $id] ?? null;

			if (!$p) {
				continue;
			}

			$catId   = $p['categoryIds'][0] ?? null;
			$catName = $catId ? ($db['categories'][(string) $catId]['name']['uk'] ?? '') : '';
			$attrs   = array_slice($p['attributes'] ?? [], 0, 3);

			$cards[] = [
				'id'         => $p['id'],
				'name'       => $p['name']['uk'] ?? $p['name']['en'] ?? '',
				'slug'       => $p['slug'] ?? '',
				'category'   => $catName,
				'price'      => $p['price'],
				'image'      => $p['image'],
				'inStock'    => ($p['stockStatus'] === 7),
				'attributes' => $attrs,
			];
		}

		return $cards;
	}

	public function getFilterOptions(int $groupId): array {
		$db    = $this->getDb();
		$group = $db['filterGroups'][(string) $groupId] ?? null;

		if (!$group) {
			return [];
		}

		$result = [];

		foreach ($group['filterIds'] ?? [] as $fid) {
			$f = $db['filters'][(string) $fid] ?? null;

			if ($f) {
				$result[] = [
					'id'   => $f['id'],
					'name' => $f['name']['uk'] ?? $f['name']['en'] ?? '',
				];
			}
		}

		return $result;
	}

	public function getArticle(int $index): ?array {
		$db = $this->getDb();

		return $db['blog']['articles'][$index] ?? null;
	}

	/**
	 * @param string|null    $activeCategoryId
	 * @param array<int, int> $activeFilterIds
	 *
	 * @return array<string, mixed>
	 */
	public function heuristicSearch(string $query, $activeCategoryId, array $activeFilterIds): array {
		$q = mb_strtolower(trim($query));

		if ($this->matchesIntent($q, ['оплат', 'заплатити', 'оплатити', 'способи оплати', 'payment', 'оплату'])) {
			return $this->getInfoResponse('payment');
		}

		if ($this->matchesIntent($q, ['достав', 'кур\'єр', 'пошта', 'нова пошта', 'укрпошта', 'delivery', 'доставку'])) {
			return $this->getInfoResponse('delivery');
		}

		// Welcome intent button «Консультація» / EN «Product advice» — filled in controller (assistant/chat).
		if ($this->matchesIntent($q, ['консультац', 'consultation', 'product advice', 'порада', 'порад'])) {
			return [
				'text'               => '',
				'actions'            => [],
				'assistantShortcut'  => 'consult',
			];
		}

		$navResult = $this->matchNavigationIntent($q);

		if ($navResult !== null) {
			return $navResult;
		}

		return $this->searchFlow($query, $q, $activeCategoryId, $activeFilterIds);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function getInfoResponse(string $key): array {
		$db   = $this->getDb();
		$lang = (string) ($this->config->get('config_language') ?: 'uk-ua');

		if (isset($db['info'][$key])) {
			$node = $db['info'][$key];
			$text = $this->resolveInfoLocalizedField($node['text'] ?? '', $lang);
			$url  = $this->resolveInfoLocalizedField($node['url'] ?? '', $lang);
		} else {
			$text = $key === 'payment'
				? 'Умови оплати дивіться в розділі інформації на сайті або зателефонуйте нам.'
				: 'Умови доставки дивіться в розділі інформації на сайті або зателефонуйте нам.';
			$url  = '';
		}

		$actions = [];

		if ($url) {
			$actions[] = ['type' => 'navigateTo', 'url' => $url, 'label' => 'Детальніше →'];
		}

		return ['text' => $text, 'actions' => $actions];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function matchNavigationIntent(string $q): ?array {
		$nav = $this->navUrls;

		if ($this->matchesIntent($q, ['акці', 'знижк', 'розпродаж', 'дешевш', 'sale', 'спеціальн'])) {
			return [
				'text'    => 'Переглядайте поточні акції та знижки в нашому магазині!',
				'actions' => [['type' => 'navigateTo', 'url' => $nav['sales'] ?? '', 'label' => 'Переглянути акції →']],
			];
		}

		if ($this->matchesIntent($q, ['новинк', 'нові товар', 'нове надходження', 'new'])) {
			return [
				'text'    => 'Ось наші нові надходження та актуальні товари:',
				'actions' => [['type' => 'navigateTo', 'url' => $nav['new'] ?? $nav['sales'] ?? '', 'label' => 'Переглянути →']],
			];
		}

		if ($this->matchesIntent($q, ['весь каталог', 'всі товари', 'всі категорії', 'показати все', 'catalog'])) {
			return [
				'text'    => 'Переглядайте повний каталог насіння та товарів для саду:',
				'actions' => [['type' => 'navigateTo', 'url' => $nav['catalog'] ?? '', 'label' => 'Відкрити каталог →']],
			];
		}

		foreach ($nav['categories'] ?? [] as $navCat) {
			foreach ($navCat['keywords'] ?? [] as $kw) {
				if ($kw !== '' && mb_strpos($q, $kw) !== false) {
					return [
						'text'       => 'Переходьте до розділу ' . $navCat['name'] . ':',
						'actions'    => [
							['type' => 'navigateTo', 'url' => $navCat['url'], 'label' => 'Переглянути ' . $navCat['name'] . ' →'],
						],
						'categoryId' => (int) $navCat['categoryId'],
					];
				}
			}
		}

		return null;
	}

	/**
	 * @param string|null    $activeCategoryId
	 * @param array<int, int> $activeFilterIds
	 *
	 * @return array<string, mixed>
	 */
	private function searchFlow(string $rawQuery, string $q, $activeCategoryId, array $activeFilterIds): array {
		$db = $this->getDb();

		$categoryId = $activeCategoryId ?? $this->detectCategory($q);
		$newFilters = $this->detectFilters($q);
		$filterIds  = array_values(array_unique(array_merge($activeFilterIds, $newFilters)));

		$products = $this->getProductsByFilters($filterIds, $categoryId);

		if (!$products) {
			$products = $this->searchProducts($rawQuery);
		}

		if (!$products && $categoryId) {
			$products = $this->getProductsByFilters([], $categoryId);
		}

		$products = $this->rankProducts($products);
		$top        = array_slice($products, 0, 5);
		$ids        = array_column($top, 'id');

		if (!$ids) {
			return [
				'text'       => 'На жаль, нічого не знайшлось. Уточніть, будь ласка, запит.',
				'actions'    => [['type' => 'askFilter', 'groupId' => 1, 'question' => 'Яка культура вас цікавить?']],
				'categoryId' => $categoryId,
				'filterIds'  => $filterIds,
			];
		}

		$actions = [['type' => 'showProducts', 'productIds' => $ids]];

		if ($categoryId) {
			$cat = $db['categories'][(string) $categoryId] ?? null;

			if ($cat) {
				$catName = $cat['name']['uk'] ?? $cat['name']['en'] ?? '';
				$catUrl  = $this->navUrls['categoriesById'][(string) $categoryId] ?? '';

				if ($catUrl) {
					$actions[] = ['type' => 'navigateTo', 'url' => $catUrl, 'label' => 'Переглянути весь розділ «' . $catName . '» →'];
				}
			}
		}

		if (count($top) >= 5 && $categoryId) {
			$nextGroup = $this->getNextFilterGroup((int) $categoryId, $filterIds);

			if ($nextGroup) {
				$actions[] = ['type' => 'askFilter', 'groupId' => $nextGroup['id'], 'question' => $nextGroup['question']];
			}
		}

		$articleIdx = $this->findRelevantArticle($categoryId);

		if ($articleIdx !== null) {
			$actions[] = ['type' => 'showArticle', 'articleIndex' => $articleIdx];
		}

		return [
			'text'       => 'Ось що знайшов для вас:',
			'actions'    => $actions,
			'categoryId' => $categoryId,
			'filterIds'  => $filterIds,
		];
	}

	/**
	 * @param array<int, string> $keywords
	 */
	private function matchesIntent(string $q, array $keywords): bool {
		foreach ($keywords as $kw) {
			if (mb_strpos($q, $kw) !== false) {
				return true;
			}
		}

		return false;
	}

	private function detectCategory(string $q): ?int {
		// Longer / more specific keywords first (uksort) so e.g. «огурець» wins over loose substrings.
		$map = [
			// Cucumbers 110 — Ukrainian + Russian + common typos
			'огурчик'  => 110,
			'огурець'  => 110,
			'огірки'   => 110,
			'огірця'   => 110,
			'огірці'   => 110,
			'огірок'   => 110,
			'огурец'   => 110,
			'огурок'   => 110,
			'cucumber' => 110,
			// Tomatoes 100
			'помідор' => 100,
			'томат'   => 100,
			'tomat'   => 100,
			// Cabbage 120
			'капуст' => 120,
			// Pepper 130 — UA + RU
			'перець' => 130,
			'перец'  => 130,
			'pepper' => 130,
			// Eggplant 150
			'баклажан' => 150,
			// Onion 160
			'цибул' => 160,
			// Carrot 170
			'морков' => 170,
			'морква' => 170,
			// Beet / radish family 180 — so «редис» / «редька» map here, not to random search hits
			'редиск' => 180,
			'редис'  => 180,
			'редька' => 180,
			'буряк'  => 180,
			// Melons / squash
			'кавун'  => 190,
			'диня'   => 200,
			'гарбуз' => 210,
			'кабачок' => 220,
			'кабачки' => 220,
			// Greens / herbs 240–250
			'зелен'   => 240,
			'петрушк' => 250,
			'базил'   => 250,
			'кріп'    => 250,
			// Flowers 330
			'квіти' => 330,
			'квіт'  => 330,
			// Fertilizer 400
			'добрив' => 400,
		];

		uksort(
			$map,
			static function (string $a, string $b): int {
				return mb_strlen($b) <=> mb_strlen($a);
			}
		);

		foreach ($map as $kw => $catId) {
			if (mb_strpos($q, $kw) !== false) {
				return $catId;
			}
		}

		return null;
	}

	/**
	 * @return array<int, int>
	 */
	private function detectFilters(string $q): array {
		return [];
	}

	/**
	 * @param array<int, int> $usedFilterIds
	 *
	 * @return array<string, mixed>|null
	 */
	private function getNextFilterGroup(int $categoryId, array $usedFilterIds): ?array {
		$db               = $this->getDb();
		$groupsByCategory = $db['indexes']['filtersByCategory'][(string) $categoryId] ?? [];
		$priority         = [4 => 'Яка стиглість вам підходить?', 1 => 'Який колір плодів?', 2 => 'Де будете вирощувати?'];

		foreach ($priority as $groupId => $question) {
			if (!isset($groupsByCategory[(string) $groupId])) {
				continue;
			}

			$groupFilterIds = $groupsByCategory[(string) $groupId];

			if (!array_intersect($usedFilterIds, $groupFilterIds)) {
				return ['id' => $groupId, 'question' => $question];
			}
		}

		return null;
	}

	private function findRelevantArticle(?int $categoryId): ?int {
		if (!$categoryId) {
			return null;
		}

		$db       = $this->getDb();
		$topicMap = [100 => '2', 110 => '2', 130 => '2', 140 => '2'];
		$topic    = $topicMap[$categoryId] ?? null;

		if (!$topic) {
			return null;
		}

		$indices = $db['blog']['articlesByTopic'][$topic] ?? [];

		return $indices[0] ?? null;
	}
}
