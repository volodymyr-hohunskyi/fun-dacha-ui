<?php
namespace Opencart\Catalog\Controller\Common;

use Opencart\Catalog\Controller\Product\Thumb;

class HomeBundles extends \Opencart\System\Engine\Controller {
	private const BUNDLE_CATEGORY_ID = 600;

	public function index(): string {
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$results = $this->model_catalog_product->getProducts([
			'filter_category_id' => self::BUNDLE_CATEGORY_ID,
			'sort'               => 'p.sort_order',
			'order'              => 'ASC',
			'start'              => 0,
			'limit'              => 10,
		]);

		if (empty($results)) {
			return '';
		}

		[$thumb_w, $thumb_h] = Thumb::listThumbDimensions($this->config);

		$data['bundles'] = [];

		foreach ($results as $result) {
			if ($result['image'] && is_file(DIR_IMAGE . html_entity_decode($result['image'], ENT_QUOTES, 'UTF-8'))) {
				$image = $this->model_tool_image->resize($result['image'], $thumb_w, $thumb_h);
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

			$desc = trim(strip_tags(html_entity_decode($result['description'] ?? '', ENT_QUOTES, 'UTF-8')));
			if (oc_strlen($desc) > 80) {
				$desc = oc_substr($desc, 0, 80) . '…';
			}

			$data['bundles'][] = [
				'name'        => $result['name'],
				'description' => $desc,
				'thumb'       => $image,
				'price'       => $price,
				'special'     => $special,
				'href'        => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $result['product_id']),
			];
		}

		$lang_key = str_contains($this->config->get('config_language'), 'uk') ? 'uk' : 'en';
		$data['heading_title'] = $lang_key === 'uk' ? 'Готові набори' : 'Curated Bundles';
		$data['heading_subtitle'] = $lang_key === 'uk' ? 'Зберіть більше — заощадьте 20%' : 'Buy more — save 20%';

		return $this->load->view('common/home_bundles', $data);
	}
}
