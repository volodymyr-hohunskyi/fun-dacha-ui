<?php
namespace Opencart\Catalog\Controller\Product;

class CrossSell extends \Opencart\System\Engine\Controller {
	private const SEED_CATEGORIES = [100, 101, 102, 103, 110, 120, 121, 122, 123, 124, 130, 140, 141, 142, 150, 160, 170, 180, 190, 200, 210, 220, 230, 240, 250, 270, 280, 281, 282, 290, 330, 331, 332];

	private const CROSS_SELL_MAP = [
		100 => [403, 407, 402],
		110 => [403, 407, 402],
		120 => [403, 407, 401],
		130 => [407, 405],
		140 => [403, 407, 402],
		150 => [403, 407, 402],
		160 => [407, 403],
		170 => [407, 401],
		180 => [407, 401],
		190 => [407, 402],
		200 => [407, 402],
		210 => [407, 402],
		220 => [407, 402],
		230 => [407, 402],
		240 => [407, 405],
		250 => [407, 405],
		270 => [407, 405],
		280 => [407, 405],
		290 => [407, 401],
		330 => [407, 405, 402],
	];

	private const DEFAULT_CROSS_SELL = [407, 403];

	public function index(): string {
		$this->load->language('product/cross_sell');

		if (!isset($this->request->get['product_id'])) {
			return '';
		}

		$product_id = (int)$this->request->get['product_id'];

		$this->load->model('catalog/product');
		$this->load->model('catalog/category');
		$this->load->model('tool/image');

		$product_categories = $this->model_catalog_product->getCategories($product_id);

		$parent_category_id = $this->findSeedParentCategory($product_categories);

		if ($parent_category_id === 0) {
			return '';
		}

		$cross_sell_category_ids = self::CROSS_SELL_MAP[$parent_category_id] ?? self::DEFAULT_CROSS_SELL;

		$data['products'] = [];
		$collected = [];

		foreach ($cross_sell_category_ids as $cat_id) {
			if (count($collected) >= 6) {
				break;
			}

			$results = $this->model_catalog_product->getProducts([
				'filter_category_id' => $cat_id,
				'sort'               => 'pd.name',
				'order'              => 'ASC',
				'start'              => 0,
				'limit'              => 3,
			]);

			foreach ($results as $result) {
				if (count($collected) >= 6) {
					break;
				}

				if (isset($collected[$result['product_id']])) {
					continue;
				}

				$collected[$result['product_id']] = true;

				if ($result['image'] && is_file(DIR_IMAGE . html_entity_decode($result['image'], ENT_QUOTES, 'UTF-8'))) {
					$image = $result['image'];
				} else {
					$image = 'placeholder.png';
				}

				[$thumb_w, $thumb_h] = Thumb::listThumbDimensions($this->config);

				if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
					$price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				} else {
					$price = false;
				}

				if ((float)$result['special']) {
					$special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				} else {
					$special = false;
				}

				$product_data = [
					'thumb'   => $this->model_tool_image->resize($image, $thumb_w, $thumb_h),
					'price'   => $price,
					'special' => $special,
					'tax'     => false,
					'minimum' => $result['minimum'] > 0 ? $result['minimum'] : 1,
					'href'    => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $result['product_id'])
				] + $result;

				$data['products'][] = $this->load->controller('product/thumb', $product_data);
			}
		}

		if (empty($data['products'])) {
			return '';
		}

		return $this->load->view('product/cross_sell', $data);
	}

	private function findSeedParentCategory(array $product_categories): int {
		foreach ($product_categories as $pc) {
			$cat_id = (int)$pc['category_id'];

			if (in_array($cat_id, self::SEED_CATEGORIES, true)) {
				$cat_info = $this->model_catalog_category->getCategory($cat_id);

				if ($cat_info && (int)$cat_info['parent_id'] > 0) {
					$parent_id = (int)$cat_info['parent_id'];
					if (isset(self::CROSS_SELL_MAP[$parent_id])) {
						return $parent_id;
					}
				}

				if (isset(self::CROSS_SELL_MAP[$cat_id])) {
					return $cat_id;
				}
			}
		}

		return 0;
	}
}
