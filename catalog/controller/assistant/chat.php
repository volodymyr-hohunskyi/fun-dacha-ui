<?php
namespace Opencart\Catalog\Controller\Assistant;
/**
 * Chat API for garden assistant (not under api/* — avoids OC api permission gate).
 *
 * @package Opencart\Catalog\Controller\Assistant
 */
class Chat extends \Opencart\System\Engine\Controller {
	/**
	 * POST JSON { "message": "..." }
	 */
	public function index(): void {
		$this->response->addHeader('Content-Type: application/json; charset=utf-8');

		if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
			$this->response->setOutput(json_encode(['error' => 'Method not allowed']));

			return;
		}

		$raw = file_get_contents('php://input');
		$input = json_decode($raw ?: '[]', true);

		if (!is_array($input)) {
			$this->response->setOutput(json_encode(['error' => 'Invalid JSON']));

			return;
		}

		$userMessage = trim((string) ($input['message'] ?? ''));

		if ($userMessage === '') {
			$this->response->setOutput(json_encode(['error' => 'Empty message']));

			return;
		}

		$this->load->model('assistant/assistant');
		$this->model_assistant_assistant->setNavUrls($this->buildNavUrls());

		$stateKey = 'ai_assistant_state';

		if (!isset($this->session->data[$stateKey]) || !is_array($this->session->data[$stateKey])) {
			$this->session->data[$stateKey] = [
				'categoryId' => null,
				'filterIds'  => [],
				'history'    => [],
			];
		}

		$state = &$this->session->data[$stateKey];

		$state['history'][] = ['role' => 'user', 'content' => $userMessage];

		if (count($state['history']) > 20) {
			$state['history'] = array_slice($state['history'], -20);
		}

		$catId = isset($state['categoryId']) ? (int) $state['categoryId'] : null;

		if ($catId === 0) {
			$catId = null;
		}

		$filterIds = isset($state['filterIds']) && is_array($state['filterIds'])
			? array_map('intval', $state['filterIds'])
			: [];

		$pageContext = [];

		if (isset($input['context']) && is_array($input['context'])) {
			$pageContext = $this->sanitizeAssistantPageContext($input['context']);
		}

		$result = $this->model_assistant_assistant->heuristicSearch(
			$userMessage,
			$catId,
			$filterIds,
			$pageContext
		);

		if (($result['assistantShortcut'] ?? '') === 'consult') {
			$this->load->language('assistant/chat');
			$result['text']    = $this->language->get('text_consult_intro');
			$result['actions'] = [
				[
					'type'  => 'navigateTo',
					'url'   => $this->url->link('information/contact', 'language=' . $this->config->get('config_language')),
					'label' => $this->language->get('text_consult_cta'),
				],
			];
			unset($result['assistantShortcut']);
		}

		if (array_key_exists('categoryId', $result)) {
			$state['categoryId'] = $result['categoryId'];
		}

		if (isset($result['filterIds']) && is_array($result['filterIds'])) {
			$state['filterIds'] = array_values(array_map('intval', $result['filterIds']));
		}

		$state['history'][] = ['role' => 'assistant', 'content' => $result['text'] ?? ''];

		$actions = $result['actions'] ?? [];

		$currency_code = $this->session->data['currency'] ?? $this->config->get('config_currency');

		foreach ($actions as &$action) {
			if (($action['type'] ?? '') === 'showProducts' && !empty($action['productIds'])) {
				$action['products'] = $this->model_assistant_assistant->getProductCards($action['productIds']);

				foreach ($action['products'] as &$p) {
					$p['priceFormatted'] = $this->currency->format((float) ($p['price'] ?? 0), $currency_code);
				}
				unset($p);
			}

			if (($action['type'] ?? '') === 'showArticle' && isset($action['articleIndex'])) {
				$action['article'] = $this->model_assistant_assistant->getArticle((int) $action['articleIndex']);

				if (!empty($action['article'])) {
					$lang                   = $this->config->get('config_language');
					$action['article']['href'] = $this->url->link('cms/blog', 'language=' . $lang);
				}
			}

			if (($action['type'] ?? '') === 'askFilter' && isset($action['groupId'])) {
				$action['filters'] = $this->model_assistant_assistant->getFilterOptions((int) $action['groupId']);
			}
		}
		unset($action);

		$this->response->setOutput(json_encode([
			'text'    => $result['text'] ?? '',
			'actions' => $actions,
		], JSON_UNESCAPED_UNICODE));
	}

	public function reset(): void {
		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->session->data['ai_assistant_state'] = [
			'categoryId' => null,
			'filterIds'  => [],
			'history'    => [],
		];
		$this->response->setOutput(json_encode(['ok' => true]));
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildNavUrls(): array {
		$lang = $this->config->get('config_language');

		$this->load->model('assistant/assistant');
		$db = $this->model_assistant_assistant->getDb();

		$sales   = $this->url->link('product/special', 'language=' . $lang);
		$catalog = $this->url->link('common/home', 'language=' . $lang);

		if (!empty($db['categoryTree'][0]['id'])) {
			$rootId = $this->resolveCategoryId((int) $db['categoryTree'][0]['id']);
			if ($rootId > 0) {
				$catalog = $this->url->link(
					'product/category',
					'language=' . $lang . '&path=' . $this->buildCategoryPathString($rootId)
				);
			}
		}

		$categories    = [];
		$categoriesById = [];

		foreach ($db['categoryTree'] ?? [] as $cat) {
			$name = $this->model_assistant_assistant->getCategoryTreeDisplayName($cat);
			$kw   = $name !== '' ? [mb_strtolower($name)] : [];

			if (!empty($cat['slug'])) {
				$kw[] = mb_strtolower(str_replace('-', ' ', (string) $cat['slug']));
			}

			$resolvedId = $this->resolveCategoryId((int) $cat['id']);
			if ($resolvedId > 0) {
				$url = $this->url->link(
					'product/category',
					'language=' . $lang . '&path=' . $this->buildCategoryPathString($resolvedId)
				);
			} else {
				$url = $catalog;
			}

			$categories[] = [
				'categoryId' => (int) $cat['id'],
				'name'       => $name,
				'url'        => $url,
				'keywords'   => array_values(array_unique(array_filter($kw))),
			];

			$categoriesById[(string) $cat['id']] = $url;
		}

		return [
			'sales'          => $sales,
			'new'            => $sales,
			'catalog'        => $catalog,
			'categories'     => $categories,
			'categoriesById' => $categoriesById,
		];
	}

	/**
	 * Prefer a live category from the catalog; JSON ids may be stale vs oc_category.
	 */
	private function resolveCategoryId(int $candidate_id): int {
		$this->load->model('catalog/category');

		if ($candidate_id > 0) {
			$info = $this->model_catalog_category->getCategory($candidate_id);

			if ($info) {
				return $candidate_id;
			}
		}

		$roots = $this->model_catalog_category->getCategories(0);

		if (!empty($roots[0]['category_id'])) {
			return (int) $roots[0]['category_id'];
		}

		return 0;
	}

	/**
	 * Full path segment for product/category (matches menu / oc_category_path).
	 */
	private function buildCategoryPathString(int $category_id): string {
		if ($category_id <= 0) {
			return '';
		}

		$query = $this->db->query(
			"SELECT `path_id` FROM `" . DB_PREFIX . "category_path` WHERE `category_id` = '" . (int) $category_id . "' ORDER BY `level` ASC"
		);

		if ($query->num_rows) {
			$ids = [];

			foreach ($query->rows as $row) {
				$ids[] = (int) $row['path_id'];
			}

			return implode('_', $ids);
		}

		return (string) (int) $category_id;
	}

	/**
	 * @param array<string, mixed> $ctx
	 *
	 * @return array<string, mixed>
	 */
	private function sanitizeAssistantPageContext(array $ctx): array {
		$out = [];

		if (isset($ctx['route'])) {
			$out['route'] = (string) $ctx['route'];
		}

		if (isset($ctx['category_id'])) {
			$out['category_id'] = (int) $ctx['category_id'];
		}

		if (isset($ctx['product_id'])) {
			$out['product_id'] = (int) $ctx['product_id'];
		}

		if (isset($ctx['path'])) {
			$out['path'] = (string) $ctx['path'];
		}

		if (isset($ctx['filter_attr']) && is_string($ctx['filter_attr'])) {
			$out['filter_attr'] = substr($ctx['filter_attr'], 0, 2000);
		}

		return $out;
	}
}
