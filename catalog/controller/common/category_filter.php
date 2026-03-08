<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Class Category Filter
 *
 * Filter widget for product category page. Replaces category list in left column.
 * Uses Filter/FilterGroup via category model. AND logic for selected filters.
 *
 * @package Opencart\Catalog\Controller\Common
 */
class CategoryFilter extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @param int|array $category_id Category ID, or array with category_id as first element
	 *
	 * @return string
	 */
	public function index($category_id = 0): string {
		$category_id = is_array($category_id) ? (int)($category_id[0] ?? 0) : (int)$category_id;
		if (!$category_id) {
			return '';
		}
		$this->load->model('catalog/category');

		$filter_groups = $this->model_catalog_category->getFilters($category_id);

		if (empty($filter_groups)) {
			return '';
		}

		$data['filter_groups'] = [];
		$current_filters = [];
		if (isset($this->request->get['filter']) && $this->request->get['filter'] !== '') {
			$current_filters = array_filter(array_map('intval', explode(',', (string)$this->request->get['filter'])));
		}

		$base_url = 'index.php?route=product/category';
		$url_params = [
			'path'     => $this->request->get['path'] ?? '',
			'sort'     => $this->request->get['sort'] ?? '',
			'order'    => $this->request->get['order'] ?? '',
			'limit'    => $this->request->get['limit'] ?? '',
		];

		foreach ($filter_groups as $group) {
			$filters = [];
			foreach ($group['filter'] as $filter) {
				$new_filter_ids = $current_filters;
				$filter_id = (int)$filter['filter_id'];
				$key = array_search($filter_id, $new_filter_ids);
				if ($key !== false) {
					unset($new_filter_ids[$key]);
					$new_filter_ids = array_values($new_filter_ids);
					$checked = true;
				} else {
					$new_filter_ids[] = $filter_id;
					$new_filter_ids = array_values(array_unique($new_filter_ids));
					$checked = false;
				}
				sort($new_filter_ids);
				$filter_param = $new_filter_ids ? implode(',', $new_filter_ids) : '';
				$href = $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . ($url_params['path'] ?: $category_id));
				if ($filter_param) {
					$href .= '&filter=' . $filter_param;
				}
				if (!empty($url_params['sort'])) {
					$href .= '&sort=' . $url_params['sort'];
				}
				if (!empty($url_params['order'])) {
					$href .= '&order=' . $url_params['order'];
				}
				if (!empty($url_params['limit'])) {
					$href .= '&limit=' . $url_params['limit'];
				}
				$filters[] = [
					'filter_id' => $filter_id,
					'name'      => $filter['name'],
					'href'      => $href,
					'checked'   => $checked,
				];
			}
			$data['filter_groups'][] = [
				'name'    => $group['name'],
				'filters' => $filters,
			];
		}

		$this->load->language('product/category');
		$data['text_filter'] = $this->language->get('text_filter');

		return $this->load->view('common/category_filter', $data);
	}
}
