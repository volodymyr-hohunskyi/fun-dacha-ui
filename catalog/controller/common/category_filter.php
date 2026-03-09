<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Class Category Filter
 *
 * Filter widget for product category page. Filters are collected from product attributes.
 * OR logic for selected filters.
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

		$filter_groups = $this->model_catalog_category->getAttributeFiltersForCategory($category_id);
		if (empty($filter_groups)) {
			return '';
		}

		$current_filters = [];
		if (isset($this->request->get['filter_attr']) && is_string($this->request->get['filter_attr'])) {
			foreach (explode(',', $this->request->get['filter_attr']) as $part) {
				$part = trim($part);
				if ($part && strpos($part, ':') !== false) {
					$kv = explode(':', $part, 2);
					$aid = (int)$kv[0];
					$val = isset($kv[1]) ? base64_decode(strtr($kv[1], '-_', '+/')) : '';
					if ($aid && $val !== false && $val !== '') {
						$current_filters[] = ['attribute_id' => $aid, 'text' => $val];
					}
				}
			}
		}

		$url_params = [
			'path'  => $this->request->get['path'] ?? '',
			'sort'  => $this->request->get['sort'] ?? '',
			'order' => $this->request->get['order'] ?? '',
			'limit' => $this->request->get['limit'] ?? '',
		];

		$data['filter_groups'] = [];
		foreach ($filter_groups as $group) {
			$filters = [];
			foreach ($group['filters'] as $f) {
				$key = $f['attribute_id'] . ':' . $f['attribute_value'];
				$new_filters = $current_filters;
				$found = false;
				foreach ($new_filters as $i => $cf) {
					if ($cf['attribute_id'] === $f['attribute_id'] && $cf['text'] === $f['attribute_value']) {
						unset($new_filters[$i]);
						$found = true;
						break;
					}
				}
				if (!$found) {
					$new_filters[] = ['attribute_id' => $f['attribute_id'], 'text' => $f['attribute_value']];
				}
				$new_filters = array_values($new_filters);
				$filter_attr_param = '';
				if (!empty($new_filters)) {
					$parts = [];
					foreach ($new_filters as $nf) {
						$parts[] = $nf['attribute_id'] . ':' . strtr(base64_encode($nf['text']), '+/', '-_');
					}
					$filter_attr_param = implode(',', $parts);
				}
				$href = $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . ($url_params['path'] ?: $category_id));
				if ($filter_attr_param) {
					$href .= '&filter_attr=' . rawurlencode($filter_attr_param);
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
					'attribute_id' => $f['attribute_id'],
					'name'         => $f['attribute_value'],
					'href'         => $href,
					'checked'      => $found,
				];
			}
			$data['filter_groups'][] = [
				'name'    => $group['name'],
				'filters' => $filters,
			];
		}

		$this->load->language('product/category');
		$data['text_filter'] = $this->language->get('text_filter');
		$data['text_filter_reset'] = $this->language->get('text_filter_reset');

		$reset_href = $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . ($url_params['path'] ?: $category_id));
		if (!empty($url_params['sort'])) {
			$reset_href .= '&sort=' . $url_params['sort'];
		}
		if (!empty($url_params['order'])) {
			$reset_href .= '&order=' . $url_params['order'];
		}
		if (!empty($url_params['limit'])) {
			$reset_href .= '&limit=' . $url_params['limit'];
		}
		$data['filter_reset_href'] = !empty($current_filters) ? $reset_href : '';

		return $this->load->view('common/category_filter', $data);
	}
}
