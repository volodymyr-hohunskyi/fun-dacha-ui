<?php
namespace Opencart\Catalog\Controller\Common;

use Opencart\Catalog\Controller\Product\Thumb;

/**
 * Home Promotions – products with discounts. Same product thumb widget as special.
 *
 * @package Opencart\Catalog\Controller\Common
 */
class HomePromotions extends \Opencart\System\Engine\Controller {
	public function index(): string {
		$this->load->language('common/home_promotions');
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$data['heading_title'] = $this->language->get('heading_title');
		$data['products'] = [];
		$data['axis'] = 'horizontal';

		$results = $this->model_catalog_product->getSpecials([
			'sort'  => 'pd.name',
			'order' => 'ASC',
			'start' => 0,
			'limit' => 8,
		]);

		foreach ($results as $result) {
			if ($result['image']) {
				$image = $this->model_tool_image->resize(html_entity_decode($result['image'], ENT_QUOTES, 'UTF-8'), Thumb::LIST_THUMB_WIDTH, Thumb::LIST_THUMB_HEIGHT);
			} else {
				$image = $this->model_tool_image->resize('placeholder.png', Thumb::LIST_THUMB_WIDTH, Thumb::LIST_THUMB_HEIGHT);
			}
			$price = false;
			$special = false;
			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				if ((float)$result['special']) {
					$special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				}
			}
			$tax = $this->config->get('config_tax') ? $this->currency->format((float)($result['special'] ?: $result['price']), $this->session->data['currency']) : false;
			$product_data = [
				'product_id'  => $result['product_id'],
				'thumb'       => $image,
				'name'        => $result['name'],
				'description' => oc_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('config_product_description_length')) . '..',
				'price'       => $price,
				'special'     => $special,
				'tax'         => $tax,
				'minimum'     => $result['minimum'] > 0 ? $result['minimum'] : 1,
				'rating'      => (int)$result['rating'],
				'href'        => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $result['product_id']),
			];
			$data['products'][] = $this->load->controller('product/thumb', $product_data);
		}

		if ($data['products']) {
			return $this->load->view('common/home_promotions', $data);
		}
		return '';
	}
}
