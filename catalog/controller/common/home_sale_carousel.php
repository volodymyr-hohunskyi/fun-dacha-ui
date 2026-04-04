<?php
namespace Opencart\Catalog\Controller\Common;

use Opencart\Catalog\Controller\Product\Thumb;

/**
 * Home – carousel of products on sale (up to 25), preferring one product per category.
 *
 * @package Opencart\Catalog\Controller\Common
 */
class HomeSaleCarousel extends \Opencart\System\Engine\Controller {
	private const MAX_PRODUCTS = 25;
	private const POOL_LIMIT = 500;
	private const PER_SLIDE = 5;

	/**
	 * Pick one category id per product for diversification (main category when set).
	 *
	 * @param array<int, array<string, mixed>> $category_rows
	 */
	private function representativeCategoryId(array $category_rows): int {
		if (!$category_rows) {
			return 0;
		}
		foreach ($category_rows as $row) {
			if (isset($row['main']) && (int)$row['main'] === 1) {
				return (int)$row['category_id'];
			}
		}
		$ids = [];

		foreach ($category_rows as $row) {
			$ids[] = (int)$row['category_id'];
		}

		return (int)min($ids);
	}

	public function index(): string {
		$this->load->language('common/home_sale_carousel');
		$this->load->model('catalog/product');
		$this->load->model('catalog/category');
		$this->load->model('tool/image');

		$data['heading_title'] = $this->language->get('heading_title');

		$pool = $this->model_catalog_product->getSpecials([
			'sort'  => 'p.sort_order',
			'order' => 'ASC',
			'start' => 0,
			'limit' => self::POOL_LIMIT,
		]);

		if (!$pool) {
			return '';
		}

		[$thumb_w, $thumb_h] = Thumb::listThumbDimensions($this->config);

		$chosen = [];
		$used_categories = [];
		$chosen_ids = [];

		foreach ($pool as $result) {
			if (count($chosen) >= self::MAX_PRODUCTS) {
				break;
			}
			$pid = (int)$result['product_id'];
			if (isset($chosen_ids[$pid])) {
				continue;
			}
			$cat_rows = $this->model_catalog_product->getCategories($pid);
			$rep = $this->representativeCategoryId($cat_rows);
			if ($rep > 0 && isset($used_categories[$rep])) {
				continue;
			}
			if ($rep > 0) {
				$used_categories[$rep] = true;
			}
			$chosen_ids[$pid] = true;
			$chosen[] = $result;
		}

		if (count($chosen) < self::MAX_PRODUCTS) {
			foreach ($pool as $result) {
				if (count($chosen) >= self::MAX_PRODUCTS) {
					break;
				}
				$pid = (int)$result['product_id'];
				if (isset($chosen_ids[$pid])) {
					continue;
				}
				$chosen_ids[$pid] = true;
				$chosen[] = $result;
			}
		}

		$data['products'] = [];

		foreach ($chosen as $result) {
			if ($result['image'] && is_file(DIR_IMAGE . html_entity_decode($result['image'], ENT_QUOTES, 'UTF-8'))) {
				$image = $this->model_tool_image->resize(html_entity_decode($result['image'], ENT_QUOTES, 'UTF-8'), $thumb_w, $thumb_h);
			} else {
				$image = $this->model_tool_image->resize('placeholder.png', $thumb_w, $thumb_h);
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

			$desc = trim(strip_tags(html_entity_decode($result['description'] ?? '', ENT_QUOTES, 'UTF-8')));
			$len = (int)$this->config->get('config_product_description_length');

			if ($len > 0 && oc_strlen($desc) > $len) {
				$desc = oc_substr($desc, 0, $len) . '..';
			}

			$path_str = '';
			$cat_rows = $this->model_catalog_product->getCategories((int)$result['product_id']);
			$rep = $this->representativeCategoryId($cat_rows);

			if ($rep > 0) {
				$path_str = $this->model_catalog_category->getCategoryPathString($rep);
			}

			$product_href = $this->url->link(
				'product/product',
				'language=' . $this->config->get('config_language') . '&product_id=' . $result['product_id'] . ($path_str !== '' ? '&path=' . $path_str : '')
			);

			$product_data = [
				'thumb'       => $image,
				'name'        => $result['name'],
				'description' => $desc,
				'price'       => $price,
				'special'     => $special,
				'tax'         => $tax,
				'minimum'     => $result['minimum'] > 0 ? $result['minimum'] : 1,
				'rating'      => (int)$result['rating'],
				'href'        => $product_href,
			] + $result;

			$data['products'][] = $this->load->controller('product/thumb', $product_data);
		}

		if (!$data['products']) {
			return '';
		}

		$data['per_slide'] = self::PER_SLIDE;

		return $this->load->view('common/home_sale_carousel', $data);
	}
}
