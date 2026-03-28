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

		$result = $this->model_assistant_assistant->heuristicSearch(
			$userMessage,
			$catId,
			$filterIds
		);

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
			$catalog = $this->url->link('product/category', 'language=' . $lang . '&path=' . (int) $db['categoryTree'][0]['id']);
		}

		$categories    = [];
		$categoriesById = [];

		foreach ($db['categoryTree'] ?? [] as $cat) {
			$name = $cat['name']['uk'] ?? $cat['name']['en'] ?? '';
			$kw   = [mb_strtolower($name)];

			if (!empty($cat['slug'])) {
				$kw[] = mb_strtolower(str_replace('-', ' ', (string) $cat['slug']));
			}

			$url = $this->url->link('product/category', 'language=' . $lang . '&path=' . (int) $cat['id']);

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
}
