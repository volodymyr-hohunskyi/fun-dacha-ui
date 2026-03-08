<?php
namespace Opencart\Catalog\Controller\Product;
/**
 * Class Compare
 *
 * @package Opencart\Catalog\Controller\Product
 */
class Compare extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->response->redirect($this->url->link('common/home', 'language=' . $this->config->get('config_language'), true));
		return;
	}

	/**
	 * Add
	 *
	 * @return void
	 */
	public function add(): void {
		$this->load->language('product/compare');

		$json = [];

		if (!isset($this->session->data['compare'])) {
			$this->session->data['compare'] = [];
		}

		if (isset($this->request->post['product_id'])) {
			$product_id = (int)$this->request->post['product_id'];
		} else {
			$product_id = 0;
		}

		// Product
		$this->load->model('catalog/product');

		$product_info = $this->model_catalog_product->getProduct($product_id);

		if (!$product_info) {
			$json['error'] = $this->language->get('error_product');
		}

		if (!$json) {
			// If already in array remove the product_id so it will be added to the back of the array
			$key = array_search($product_info['product_id'], $this->session->data['compare']);

			if ($key !== false) {
				unset($this->session->data['compare'][$key]);
			}

			// If we count a numeric value that is greater than 4 products, we remove the first one
			if (count($this->session->data['compare']) >= 4) {
				array_shift($this->session->data['compare']);
			}

			$this->session->data['compare'][] = $product_info['product_id'];

			$json['success'] = sprintf($this->language->get('text_success'), $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $product_info['product_id']), $product_info['name'], $this->url->link('product/compare', 'language=' . $this->config->get('config_language')));

			$json['total'] = sprintf($this->language->get('text_compare'), (isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0));
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
