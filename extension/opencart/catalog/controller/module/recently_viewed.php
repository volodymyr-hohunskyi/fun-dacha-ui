<?php
namespace Opencart\Catalog\Controller\Extension\Opencart\Module;

class RecentlyViewed extends \Opencart\System\Engine\Controller {
	public function index(array $setting): string {
		$this->load->language('extension/opencart/module/recently_viewed');

		$limit = isset($setting['limit']) ? (int)$setting['limit'] : 6;
		if ($limit < 1 || $limit > 20) {
			$limit = 6;
		}

		$data['products'] = [];

		// Get recently viewed products from session
		if (isset($this->session->data['recently_viewed']) && is_array($this->session->data['recently_viewed'])) {
			$recently_viewed = array_slice($this->session->data['recently_viewed'], 0, $limit);
			
			// Remove duplicates
			$recently_viewed = array_unique($recently_viewed);
			
			if (!empty($recently_viewed)) {
				$this->load->model('catalog/product');
				$this->load->model('tool/image');

				foreach ($recently_viewed as $product_id) {
					$product_info = $this->model_catalog_product->getProduct((int)$product_id);

					if ($product_info) {
						if ($product_info['image']) {
							$image = $this->model_tool_image->resize(html_entity_decode($product_info['image'], ENT_QUOTES, 'UTF-8'), $setting['width'] ?? 200, $setting['height'] ?? 200);
						} else {
							$image = $this->model_tool_image->resize('placeholder.png', $setting['width'] ?? 200, $setting['height'] ?? 200);
						}

						if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
							$price = $this->currency->format($this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
						} else {
							$price = false;
						}

						if ((float)$product_info['special']) {
							$special = $this->currency->format($this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
						} else {
							$special = false;
						}

						if ($this->config->get('config_tax')) {
							$tax = $this->currency->format((float)$product_info['special'] ? $product_info['special'] : $product_info['price'], $this->session->data['currency']);
						} else {
							$tax = false;
						}

						$product_data = [
							'product_id'  => $product_info['product_id'],
							'thumb'       => $image,
							'name'        => $product_info['name'],
							'description' => oc_substr(trim(strip_tags(html_entity_decode($product_info['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('config_product_description_length')) . '..',
							'price'       => $price,
							'special'     => $special,
							'tax'         => $tax,
							'minimum'     => $product_info['minimum'] > 0 ? $product_info['minimum'] : 1,
							'rating'      => (int)$product_info['rating'],
							'href'        => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $product_info['product_id'])
						];

						$data['products'][] = $this->load->controller('product/thumb', $product_data);
					}
				}
			}
		}

		if ($data['products']) {
			$data['heading_title'] = $this->language->get('heading_title');
			$data['axis'] = $setting['axis'] ?? 'horizontal';
			return $this->load->view('extension/opencart/module/recently_viewed', $data);
		} else {
			return '';
		}
	}
}




