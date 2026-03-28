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
	 * Match query against attribute group/name/value and product title (UA/EN).
	 *
	 * @param array<string, mixed> $p
	 * @param array<int, string>   $tokens
	 */
	private function scoreProductAttributeMatch(array $p, string $needle, array $tokens): int {
		$parts = [];

		foreach ($p['attributes'] ?? [] as $a) {
			if (!is_array($a)) {
				continue;
			}

			$parts[] = mb_strtolower((string) ($a['group'] ?? ''));
			$parts[] = mb_strtolower((string) ($a['name'] ?? ''));
			$parts[] = mb_strtolower((string) ($a['value'] ?? ''));
		}

		$nameUk = mb_strtolower((string) ($p['name']['uk'] ?? ''));
		$nameEn = mb_strtolower((string) ($p['name']['en'] ?? ''));
		$hay    = $nameUk . ' ' . $nameEn . ' ' . implode(' ', $parts);
		$score  = 0;

		if ($needle !== '' && mb_strpos($hay, $needle) !== false) {
			$score += 200 + mb_strlen($needle) * 3;
		}

		foreach ($tokens as $t) {
			if (mb_strlen($t) < 2) {
				continue;
			}

			if (mb_strpos($hay, $t) !== false) {
				$score += 25 + mb_strlen($t) * 2;
			}
		}

		return $score;
	}

	/**
	 * Products whose attributes (or name) match the query, best scores first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function searchProductsByAttributesSorted(string $query, ?int $categoryId): array {
		$db = $this->getDb();
		$q  = mb_strtolower(trim($query));

		if ($q === '') {
			return [];
		}

		$needle  = $q;
		$tokens  = preg_split('/[\s,\-]+/u', $q);
		$tokens  = array_values(array_filter($tokens, static fn($t) => is_string($t) && mb_strlen($t) > 1));

		$scored = [];

		foreach ($db['products'] ?? [] as $p) {
			if (!is_array($p) || !isset($p['id'])) {
				continue;
			}

			$cats = $p['categoryIds'] ?? [];

			if ($categoryId !== null && !in_array($categoryId, $cats, true)) {
				continue;
			}

			$score = $this->scoreProductAttributeMatch($p, $needle, $tokens);

			if ($score > 0) {
				$scored[(int) $p['id']] = ['p' => $p, 'score' => $score];
			}
		}

		uasort(
			$scored,
			static function (array $a, array $b): int {
				return $b['score'] <=> $a['score'];
			}
		);

		return array_map(
			static fn(array $x): array => $x['p'],
			array_values($scored)
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $a
	 * @param array<int, array<string, mixed>> $b
	 * @param array<int, array<string, mixed>> $c
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function mergeProductSearchResults(array $a, array $b, array $c): array {
		$byId  = [];
		$order = [];

		foreach ([$a, $b, $c] as $list) {
			foreach ($list as $p) {
				if (!is_array($p) || !isset($p['id'])) {
					continue;
				}

				$id = (int) $p['id'];

				if (!isset($byId[$id])) {
					$byId[$id]  = $p;
					$order[]    = $id;
				}
			}
		}

		$out = [];

		foreach ($order as $id) {
			$out[] = $byId[$id];
		}

		return $out;
	}

	/**
	 * If fewer than 5 products, append others from the same category (no duplicates).
	 *
	 * @param array<int, array<string, mixed>> $products
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function fillSearchResultsToFive(array $products, ?int $categoryId): array {
		$products = array_values(array_filter($products));

		if (count($products) >= 5) {
			return array_slice($products, 0, 5);
		}

		$have = [];

		foreach ($products as $p) {
			$have[(int) $p['id']] = true;
		}

		$fillCat = $categoryId;

		if (!$fillCat && $products) {
			$fillCat = (int) (($products[0]['categoryIds'] ?? [])[0] ?? 0);
		}

		if ($fillCat <= 0) {
			return $products;
		}

		$pool = $this->getProductsByFilters([], $fillCat);
		$pool = $this->rankProducts($pool);

		foreach ($pool as $p) {
			if (count($products) >= 5) {
				break;
			}

			$id = (int) $p['id'];

			if (empty($have[$id])) {
				$products[] = $p;
				$have[$id]  = true;
			}
		}

		return $products;
	}

	/**
	 * Build product/category filter_attr query segment from user text + top products (matches OC category filter chips).
	 *
	 * @param array<int, int> $productIds Assistant DB product ids (same as oc product_id in export).
	 *
	 * @return string comma-separated attribute_id:base64 pairs, or empty
	 */
	public function buildFilterAttrForCategoryBrowse(int $categoryId, string $rawQuery, array $productIds): string {
		if ($categoryId <= 0) {
			return '';
		}

		$this->load->model('catalog/category');
		$groups = $this->model_catalog_category->getAttributeFiltersForCategory($categoryId);

		if (!$groups) {
			return '';
		}

		$valueByNormalized = [];

		foreach ($groups as $g) {
			foreach ($g['filters'] ?? [] as $f) {
				$val = trim((string) ($f['attribute_value'] ?? ''));

				if ($val === '') {
					continue;
				}

				$lk                        = mb_strtolower($val);
				$valueByNormalized[$lk] = [
					'attribute_id' => (int) $f['attribute_id'],
					'text'         => $val,
				];
			}
		}

		if (!$valueByNormalized) {
			return '';
		}

		$selected = [];
		$q        = mb_strtolower(trim($rawQuery));

		foreach ($valueByNormalized as $lk => $pair) {
			if ($lk === '') {
				continue;
			}

			if (mb_strlen($lk) >= 2 && mb_strpos($q, $lk) !== false) {
				$selected[] = $pair;
			}
		}

		$db = $this->getDb();

		foreach ($productIds as $pid) {
			$p = $db['products'][(string) $pid] ?? null;

			if (!$p) {
				continue;
			}

			foreach ($p['attributes'] ?? [] as $a) {
				if (!is_array($a)) {
					continue;
				}

				$v = trim((string) ($a['value'] ?? ''));

				if ($v === '') {
					continue;
				}

				$lk = mb_strtolower($v);

				if (isset($valueByNormalized[$lk])) {
					$selected[] = $valueByNormalized[$lk];
				}
			}
		}

		$seen  = [];
		$parts = [];

		foreach ($selected as $s) {
			$k = $s['attribute_id'] . "\0" . $s['text'];

			if (isset($seen[$k])) {
				continue;
			}

			$seen[$k]  = true;
			$parts[]   = $s['attribute_id'] . ':' . strtr(base64_encode($s['text']), '+/', '-_');

			if (count($parts) >= 6) {
				break;
			}
		}

		return implode(',', $parts);
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
			$attrs   = $p['attributes'] ?? [];

			if (count($attrs) > 60) {
				$attrs = array_slice($attrs, 0, 60);
			}

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
	public function heuristicSearch(string $query, $activeCategoryId, array $activeFilterIds, array $pageContext = []): array {
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

		return $this->searchFlow($query, $q, $activeCategoryId, $activeFilterIds, $pageContext);
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

		if ($this->matchesIntent($q, ['каталог', 'весь каталог', 'всі товари', 'всі категорії', 'показати все', 'catalog'])) {
			$this->load->language('assistant/chat');
			$items = [];

			foreach ($nav['categories'] ?? [] as $navCat) {
				if (empty($navCat['url'])) {
					continue;
				}

				$name = trim((string) ($navCat['name'] ?? ''));

				$items[] = [
					'url'   => $navCat['url'],
					'label' => $name !== '' ? $name . ' →' : '→',
				];
			}

			if (!empty($nav['catalog'])) {
				$items[] = [
					'url'   => $nav['catalog'],
					'label' => $this->language->get('text_catalog_nav_all'),
				];
			}

			return [
				'text'    => $this->language->get('text_catalog_nav_intro'),
				'actions' => [
					[
						'type'  => 'navigateTags',
						'items' => $items,
					],
				],
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
	 * User named another culture or explicitly wants to leave current page context.
	 */
	private function userOverridesPageContext(string $q): bool {
		if ($this->detectCategory($q) !== null) {
			return true;
		}

		return $this->matchesIntent($q, [
			'інша категор',
			'інший розділ',
			'інший товар',
			'не тут',
			'не це',
			'не хочу',
			'something else',
			'other category',
		]);
	}

	/**
	 * @param array<string, mixed> $pageContext
	 */
	private function buildEffectiveSearchQuery(string $rawQuery, string $q, array $pageContext): string {
		$effective = $rawQuery;

		if ($this->userOverridesPageContext($q)) {
			return $effective;
		}

		if (!empty($pageContext['filter_attr']) && is_string($pageContext['filter_attr'])) {
			$fa         = preg_replace('/[,:]/u', ' ', $pageContext['filter_attr']);
			$effective .= ' ' . $fa;
		}

		if (!empty($pageContext['product_id'])) {
			$hint = $this->getProductAttributeHintString((int) $pageContext['product_id']);

			if ($hint !== '') {
				$effective .= ' ' . $hint;
			}
		}

		return trim($effective);
	}

	private function getProductAttributeHintString(int $productId): string {
		$db = $this->getDb();
		$p  = $db['products'][(string) $productId] ?? null;

		if (!$p) {
			return '';
		}

		$parts = [];

		foreach ($p['attributes'] ?? [] as $a) {
			if (!is_array($a)) {
				continue;
			}

			if (!empty($a['name'])) {
				$parts[] = (string) $a['name'];
			}

			if (!empty($a['value'])) {
				$parts[] = (string) $a['value'];
			}
		}

		$s = trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)));

		if (mb_strlen($s) > 800) {
			return mb_substr($s, 0, 800);
		}

		return $s;
	}

	/**
	 * OC category id must exist in assistant_db indexes (same numeric ids as in JSON export).
	 *
	 * @param mixed $activeCategoryId
	 * @param array<string, mixed> $pageContext
	 */
	private function resolveCategoryIdForSearch(string $q, $activeCategoryId, array $pageContext): ?int {
		$detected = $this->detectCategory($q);

		if ($detected !== null) {
			return $detected;
		}

		if (!empty($pageContext['category_id'])) {
			$c = (int) $pageContext['category_id'];

			if ($c > 0 && $this->categoryIdExistsInAssistantDb($c)) {
				return $c;
			}
		}

		if ($activeCategoryId !== null && (int) $activeCategoryId > 0) {
			$sid = (int) $activeCategoryId;

			if ($this->categoryIdExistsInAssistantDb($sid)) {
				return $sid;
			}
		}

		return null;
	}

	private function categoryIdExistsInAssistantDb(int $id): bool {
		if ($id <= 0) {
			return false;
		}

		$db = $this->getDb();
		$key = (string) $id;

		return isset($db['categories'][$key])
			|| isset($db['indexes']['productsByCategory'][$key]);
	}

	/**
	 * @param string|null         $activeCategoryId
	 * @param array<int, int>     $activeFilterIds
	 * @param array<string, mixed> $pageContext
	 *
	 * @return array<string, mixed>
	 */
	private function searchFlow(string $rawQuery, string $q, $activeCategoryId, array $activeFilterIds, array $pageContext = []): array {
		$db = $this->getDb();

		$categoryId = $this->resolveCategoryIdForSearch($q, $activeCategoryId, $pageContext);
		$effectiveQuery = $this->buildEffectiveSearchQuery($rawQuery, $q, $pageContext);

		$newFilters = $this->detectFilters($q);
		$filterIds  = array_values(array_unique(array_merge($activeFilterIds, $newFilters)));

		$fromFilters = $this->getProductsByFilters($filterIds, $categoryId);

		// 1) Attribute / title match (within active category when set). $effectiveQuery adds PDP + filter_attr context when appropriate.
		$attrList = $this->searchProductsByAttributesSorted($effectiveQuery, $categoryId);

		// 2) If nothing in category, search attributes globally (phrase like «Свіже споживання»).
		if ($categoryId !== null && count($attrList) === 0) {
			$attrList = $this->searchProductsByAttributesSorted($effectiveQuery, null);
		}

		$attrList = $this->rankProducts($attrList);

		$tokenProducts = $this->searchProducts($rawQuery);
		$tokenProducts = $this->rankProducts($tokenProducts);

		$fromFiltersRanked = $this->rankProducts($fromFilters);

		$merged = $this->mergeProductSearchResults($attrList, $tokenProducts, $fromFiltersRanked);

		if (!$merged) {
			$merged = $fromFiltersRanked;
		}

		if (!$merged && $categoryId) {
			$merged = $this->rankProducts($this->getProductsByFilters([], $categoryId));
		}

		if (!$merged) {
			$merged = $this->rankProducts($this->searchProducts($rawQuery));
		}

		$fillCat = $categoryId;

		if (!$fillCat && $merged) {
			$fillCat = (int) (($merged[0]['categoryIds'] ?? [])[0] ?? 0);
		}

		$merged = $this->fillSearchResultsToFive($merged, $fillCat > 0 ? $fillCat : null);

		// Preserve merge order: attribute matches, then token index, then filters; no full re-sort.
		$top = array_slice($merged, 0, 5);
		$ids = array_column($top, 'id');

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
					$fa = $this->buildFilterAttrForCategoryBrowse($categoryId, $rawQuery, $ids);

					if ($fa !== '' && stripos($catUrl, 'filter_attr=') === false) {
						$sep = (strpos($catUrl, '?') === false)
							? '?'
							: ((strpos($catUrl, '&amp;') !== false) ? '&amp;' : '&');
						$catUrl .= $sep . 'filter_attr=' . rawurlencode($fa);
					}

					$actions[] = ['type' => 'navigateTo', 'url' => $catUrl, 'label' => 'Переглянути весь розділ «' . $catName . '» →'];
				}
			}
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

}
