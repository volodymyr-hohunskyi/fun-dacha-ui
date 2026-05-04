<?php
namespace Opencart\Catalog\Controller\Product;

class Promo extends \Opencart\System\Engine\Controller {
	public function index(): \Opencart\System\Engine\Action|null {
		$this->load->language('product/promo');

		$slug = isset($this->request->get['slug']) ? (string)$this->request->get['slug'] : '';

		$this->load->model('catalog/promo');

		$promo = $this->model_catalog_promo->getPromo($slug);

		if (!$promo) {
			return new \Opencart\System\Engine\Action('error/not_found');
		}

		$lang_key = 'name_' . (str_contains($this->config->get('config_language'), 'uk') ? 'uk' : 'en');
		$promo_name = $promo[$lang_key] ?? $promo['name_uk'];

		$this->document->setTitle($promo_name . ' | ' . $this->config->get('config_name'));
		$this->document->setDescription($promo['description_uk'] ?? '');

		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_promos'),
			'href' => ''
		];
		$data['breadcrumbs'][] = [
			'text' => $promo_name,
			'href' => $this->url->link('product/promo', 'language=' . $this->config->get('config_language') . '&slug=' . $slug)
		];

		$data['heading_title'] = $promo_name;
		$data['description'] = $promo['description_uk'] ?? '';
		$data['subtitle'] = $promo['subtitle_uk'] ?? '';
		$data['icon'] = $promo['icon'] ?? 'fa-tag';
		$data['color'] = $promo['color'] ?? '#207D43';
		$data['text_add_all'] = $this->language->get('text_add_all');
		$data['text_empty'] = $this->language->get('text_empty');

		$image = '';
		if (!empty($promo['image']) && is_file(DIR_IMAGE . $promo['image'])) {
			$image = $this->config->get('config_url') . 'image/' . $promo['image'];
		}
		$data['image'] = $image;

		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$product_results = [];
		$collected_ids = [];

		if (!empty($promo['product_ids'])) {
			foreach ($promo['product_ids'] as $pid) {
				$result = $this->model_catalog_product->getProduct((int)$pid);
				if ($result) {
					$product_results[] = $result;
					$collected_ids[$result['product_id']] = true;
				}
			}
		}

		$badge_ids = array_column($product_results, 'product_id');
		$all_badges = !empty($badge_ids) ? $this->model_catalog_product->getBadgeAttributes($badge_ids) : [];

		[$thumb_w, $thumb_h] = Thumb::listThumbDimensions($this->config);

		$data['products'] = [];
		$data['product_ids'] = [];

		foreach ($product_results as $result) {
			if ($result['image'] && is_file(DIR_IMAGE . html_entity_decode($result['image'], ENT_QUOTES, 'UTF-8'))) {
				$image = $result['image'];
			} else {
				$image = 'placeholder.png';
			}

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
				'badges'  => $all_badges[(int)$result['product_id']] ?? [],
				'href'    => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $result['product_id'])
			] + $result;

			$data['products'][] = $this->load->controller('product/thumb', $product_data);
			$data['product_ids'][] = [
				'product_id' => $result['product_id'],
				'quantity'   => $result['minimum'] > 0 ? $result['minimum'] : 1,
			];
		}

		$data['cart_add_url'] = $this->url->link('checkout/cart.add', 'language=' . $this->config->get('config_language'));

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('product/promo', $data));
		return null;
	}
}
