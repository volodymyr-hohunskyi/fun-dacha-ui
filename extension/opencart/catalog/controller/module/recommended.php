<?php
namespace Opencart\Catalog\Controller\Extension\Opencart\Module;

class Recommended extends \Opencart\System\Engine\Controller {
	public function index(array $setting): string {
		$this->load->language('extension/opencart/module/recommended');

		$limit = isset($setting['limit']) ? (int)$setting['limit'] : 8;
		if ($limit < 1 || $limit > 20) {
			$limit = 8;
		}

		$data['products'] = [];
		$product_ids = [];

		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		// Strategy 1: Get products from recently viewed categories/manufacturers
		if (isset($this->session->data['recently_viewed']) && is_array($this->session->data['recently_viewed']) && !empty($this->session->data['recently_viewed'])) {
			$recent_product_ids = array_slice($this->session->data['recently_viewed'], 0, 5);
			$exclude_list = !empty($recent_product_ids) ? implode(',', array_map('intval', $recent_product_ids)) : '0';
			
			foreach ($recent_product_ids as $product_id) {
				$product_info = $this->model_catalog_product->getProduct((int)$product_id);
				
				if ($product_info && count($product_ids) < $limit) {
					// Get category IDs for this product
					$category_query = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "product_to_category` WHERE product_id = '" . (int)$product_id . "' LIMIT 1");
					
					if ($category_query->num_rows) {
						$category_id = (int)$category_query->row['category_id'];
						$current_exclude = !empty($product_ids) ? $exclude_list . ',' . implode(',', array_map('intval', $product_ids)) : $exclude_list;
						
						// Get products from same category
						$category_products_query = $this->db->query("SELECT DISTINCT p2c.product_id FROM `" . DB_PREFIX . "product_to_category` p2c LEFT JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p2c.product_id = p2s.product_id) LEFT JOIN `" . DB_PREFIX . "product` p ON (p2s.product_id = p.product_id) WHERE p2c.category_id = '" . $category_id . "' AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND p.status = '1' AND p.date_available <= NOW() AND p2c.product_id NOT IN (" . $current_exclude . ") ORDER BY p.sort_order ASC LIMIT " . ($limit - count($product_ids)));
						
						foreach ($category_products_query->rows as $row) {
							if (!in_array($row['product_id'], $product_ids) && count($product_ids) < $limit) {
								$product_ids[] = $row['product_id'];
							}
						}
					}
					
					// Get products from same manufacturer
					if ($product_info['manufacturer_id'] && count($product_ids) < $limit) {
						$current_exclude = !empty($product_ids) ? $exclude_list . ',' . implode(',', array_map('intval', $product_ids)) : $exclude_list;
						$manufacturer_products_query = $this->db->query("SELECT DISTINCT p.product_id FROM `" . DB_PREFIX . "product` p LEFT JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.product_id = p2s.product_id) WHERE p.manufacturer_id = '" . (int)$product_info['manufacturer_id'] . "' AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND p.status = '1' AND p.date_available <= NOW() AND p.product_id NOT IN (" . $current_exclude . ") ORDER BY p.sort_order ASC LIMIT " . ($limit - count($product_ids)));
						
						foreach ($manufacturer_products_query->rows as $row) {
							if (!in_array($row['product_id'], $product_ids) && count($product_ids) < $limit) {
								$product_ids[] = $row['product_id'];
							}
						}
					}
				}
			}
		}

		// Strategy 2: Fallback to bestsellers (most viewed) if not enough products
		if (count($product_ids) < $limit) {
			$exclude_ids = !empty($product_ids) ? " AND p.product_id NOT IN (" . implode(',', array_map('intval', $product_ids)) . ")" : "";
			$bestsellers_query = $this->db->query("SELECT DISTINCT p.product_id FROM `" . DB_PREFIX . "product` p LEFT JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.product_id = p2s.product_id) LEFT JOIN `" . DB_PREFIX . "product_viewed` pv ON (p.product_id = pv.product_id) WHERE p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND p.status = '1' AND p.date_available <= NOW()" . $exclude_ids . " ORDER BY COALESCE(pv.viewed, 0) DESC, p.sort_order ASC LIMIT " . ($limit - count($product_ids)));
			
			if ($bestsellers_query->num_rows) {
				foreach ($bestsellers_query->rows as $row) {
					if (!in_array($row['product_id'], $product_ids) && count($product_ids) < $limit) {
						$product_ids[] = $row['product_id'];
					}
				}
			}
		}

		// Strategy 3: Fallback to featured products if still not enough
		if (count($product_ids) < $limit && isset($setting['featured_products']) && is_array($setting['featured_products'])) {
			foreach ($setting['featured_products'] as $featured_id) {
				if (!in_array($featured_id, $product_ids) && count($product_ids) < $limit) {
					$product_ids[] = $featured_id;
				}
			}
		}

		// Render products
		foreach ($product_ids as $product_id) {
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

		if ($data['products']) {
			$data['heading_title'] = !empty($setting['heading_override']) ? $setting['heading_override'] : $this->language->get('heading_title');
			$data['axis'] = $setting['axis'] ?? 'horizontal';
			return $this->load->view('extension/opencart/module/recommended', $data);
		} else {
			return '';
		}
	}
}

