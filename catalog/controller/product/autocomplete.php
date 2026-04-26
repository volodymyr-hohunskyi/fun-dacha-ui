<?php
namespace Opencart\Catalog\Controller\Product;

class Autocomplete extends \Opencart\System\Engine\Controller {
	public function index(): void {
		$this->load->language('product/search');
		$this->load->model('catalog/product');
		$this->load->model('catalog/category');
		$this->load->model('tool/image');

		$json = ['products' => [], 'categories' => []];

		if (isset($this->request->get['term'])) {
			$term = trim($this->request->get['term']);
		} else {
			$term = '';
		}

		if (oc_strlen($term) < 2) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}

		$filter_data = [
			'filter_search'      => $term,
			'sort'               => 'p.sort_order',
			'order'              => 'ASC',
			'start'              => 0,
			'limit'              => 5
		];

		$results = $this->model_catalog_product->getProducts($filter_data);

		foreach ($results as $result) {
			$thumb = $this->model_tool_image->resize(
				$result['image'] ?: 'placeholder.png',
				60,
				60
			);

			$price = '';
			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$price = $this->currency->format(
					$this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')),
					$this->session->data['currency']
				);
			}

			$json['products'][] = [
				'name'  => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8')),
				'thumb' => $thumb,
				'price' => $price,
				'href'  => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $result['product_id'])
			];
		}

		$categories = $this->model_catalog_category->getCategories(0);
		$term_lower = oc_strtolower($term);
		$cat_matches = [];

		foreach ($categories as $category) {
			$cat_name = strip_tags(html_entity_decode($category['name'], ENT_QUOTES, 'UTF-8'));
			if (mb_stripos($cat_name, $term_lower) !== false) {
				$cat_matches[] = [
					'name' => $cat_name,
					'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $category['category_id'])
				];
				if (count($cat_matches) >= 2) break;
			}
		}

		$json['categories'] = $cat_matches;

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
