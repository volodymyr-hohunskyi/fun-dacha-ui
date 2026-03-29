<?php
namespace Opencart\Catalog\Controller\Product;
/**
 * Class Product
 *
 * @package Opencart\Catalog\Controller\Product
 */
class Product extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return ?\Opencart\System\Engine\Action
	 */
	public function index(): ?\Opencart\System\Engine\Action {
		$this->load->language('product/product');

		if (isset($this->request->get['product_id'])) {
			$product_id = (int)$this->request->get['product_id'];
		} else {
			$product_id = 0;
		}

		$this->load->model('catalog/product');

		$product_info = $this->model_catalog_product->getProduct($product_id);

		if ($product_info) {
			// Track recently viewed products
			if (!isset($this->session->data['recently_viewed'])) {
				$this->session->data['recently_viewed'] = [];
			}
			
			// Remove if already exists (to avoid duplicates)
			$key = array_search($product_id, $this->session->data['recently_viewed']);
			if ($key !== false) {
				unset($this->session->data['recently_viewed'][$key]);
			}
			
			// Add to beginning of array
			array_unshift($this->session->data['recently_viewed'], $product_id);
			
			// Keep only last 20 products
			$this->session->data['recently_viewed'] = array_slice($this->session->data['recently_viewed'], 0, 20);
			
			// Store for logged-in customers (optional - can be implemented later)
			if ($this->customer->isLogged()) {
				// Could store in database here if needed
			}
			
			$this->document->setTitle($product_info['meta_title']);
			$this->document->setDescription($product_info['meta_description']);
			$this->document->setKeywords($product_info['meta_keyword']);
			$this->document->addLink($this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $product_id), 'canonical');

			$data['breadcrumbs'] = [];

			$data['breadcrumbs'][] = [
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
			];

			// Category
			$this->load->model('catalog/category');

			if (isset($this->request->get['path'])) {
				$path = '';

				$parts = explode('_', (string)$this->request->get['path']);

				$category_id = (int)array_pop($parts);

				foreach ($parts as $path_id) {
					if (!$path) {
						$path = $path_id;
					} else {
						$path .= '_' . $path_id;
					}

					$category_info = $this->model_catalog_category->getCategory((int)$path_id);

					if ($category_info) {
						$data['breadcrumbs'][] = [
							'text' => $category_info['name'],
							'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $path)
						];
					}
				}

				// Set the last category breadcrumb
				$category_info = $this->model_catalog_category->getCategory($category_id);

				if ($category_info) {
					$url = '';

					if (isset($this->request->get['sort'])) {
						$url .= '&sort=' . $this->request->get['sort'];
					}

					if (isset($this->request->get['order'])) {
						$url .= '&order=' . $this->request->get['order'];
					}

					if (isset($this->request->get['page'])) {
						$url .= '&page=' . $this->request->get['page'];
					}

					if (isset($this->request->get['limit'])) {
						$url .= '&limit=' . $this->request->get['limit'];
					}

					$data['breadcrumbs'][] = [
						'text' => $category_info['name'],
						'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $this->request->get['path'] . $url)
					];
				}
			}

			// Manufacturer
			$this->load->model('catalog/manufacturer');

			if (isset($this->request->get['manufacturer_id'])) {
				$data['breadcrumbs'][] = [
					'text' => $this->language->get('text_brand'),
					'href' => $this->url->link('product/manufacturer', 'language=' . $this->config->get('config_language'))
				];

				$url = '';

				if (isset($this->request->get['sort'])) {
					$url .= '&sort=' . $this->request->get['sort'];
				}

				if (isset($this->request->get['order'])) {
					$url .= '&order=' . $this->request->get['order'];
				}

				if (isset($this->request->get['page'])) {
					$url .= '&page=' . $this->request->get['page'];
				}

				if (isset($this->request->get['limit'])) {
					$url .= '&limit=' . $this->request->get['limit'];
				}

				$manufacturer_info = $this->model_catalog_manufacturer->getManufacturer($this->request->get['manufacturer_id']);

				if ($manufacturer_info) {
					$data['breadcrumbs'][] = [
						'text' => $manufacturer_info['name'],
						'href' => $this->url->link('product/manufacturer.info', 'language=' . $this->config->get('config_language') . '&manufacturer_id=' . $this->request->get['manufacturer_id'] . $url)
					];
				}
			}

			if (isset($this->request->get['search']) || isset($this->request->get['tag'])) {
				$url = '';

				if (isset($this->request->get['search'])) {
					$url .= '&search=' . $this->request->get['search'];
				}

				if (isset($this->request->get['tag'])) {
					$url .= '&tag=' . $this->request->get['tag'];
				}

				if (isset($this->request->get['description'])) {
					$url .= '&description=' . $this->request->get['description'];
				}

				if (isset($this->request->get['category_id'])) {
					$url .= '&category_id=' . $this->request->get['category_id'];
				}

				if (isset($this->request->get['sub_category'])) {
					$url .= '&sub_category=' . $this->request->get['sub_category'];
				}

				if (isset($this->request->get['sort'])) {
					$url .= '&sort=' . $this->request->get['sort'];
				}

				if (isset($this->request->get['order'])) {
					$url .= '&order=' . $this->request->get['order'];
				}

				if (isset($this->request->get['page'])) {
					$url .= '&page=' . $this->request->get['page'];
				}

				if (isset($this->request->get['limit'])) {
					$url .= '&limit=' . $this->request->get['limit'];
				}

				$data['breadcrumbs'][] = [
					'text' => $this->language->get('text_search'),
					'href' => $this->url->link('product/search', 'language=' . $this->config->get('config_language') . $url)
				];
			}

			$url = '';

			if (isset($this->request->get['path'])) {
				$url .= '&path=' . $this->request->get['path'];
			}

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['manufacturer_id'])) {
				$url .= '&manufacturer_id=' . $this->request->get['manufacturer_id'];
			}

			if (isset($this->request->get['search'])) {
				$url .= '&search=' . $this->request->get['search'];
			}

			if (isset($this->request->get['tag'])) {
				$url .= '&tag=' . $this->request->get['tag'];
			}

			if (isset($this->request->get['description'])) {
				$url .= '&description=' . $this->request->get['description'];
			}

			if (isset($this->request->get['category_id'])) {
				$url .= '&category_id=' . $this->request->get['category_id'];
			}

			if (isset($this->request->get['sub_category'])) {
				$url .= '&sub_category=' . $this->request->get['sub_category'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$data['breadcrumbs'][] = [
				'text' => $product_info['name'],
				'href' => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . $url . '&product_id=' . $product_id)
			];

			$this->document->setTitle($product_info['meta_title']);
			$this->document->setDescription($product_info['meta_description']);
			$this->document->setKeywords($product_info['meta_keyword']);
			$this->document->addLink($this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $product_id), 'canonical');

			$this->document->addScript('catalog/view/javascript/jquery/magnific/jquery.magnific-popup.min.js');
			$this->document->addStyle('catalog/view/javascript/jquery/magnific/magnific-popup.css');

			$data['heading_title'] = $product_info['name'];

			$data['text_minimum'] = sprintf($this->language->get('text_minimum'), $product_info['minimum']);
			$data['text_login'] = sprintf($this->language->get('text_login'), $this->url->link('account/login', 'language=' . $this->config->get('config_language')), $this->url->link('account/register', 'language=' . $this->config->get('config_language')));
			$data['text_reviews'] = sprintf($this->language->get('text_reviews'), (int)$product_info['reviews']);

			$data['tab_review'] = sprintf($this->language->get('tab_review'), $product_info['reviews']);

			$data['error_upload_size'] = sprintf($this->language->get('error_upload_size'), $this->config->get('config_file_max_size'));

			$data['config_file_max_size'] = ((int)$this->config->get('config_file_max_size') * 1024 * 1024);

			$this->session->data['upload_token'] = oc_token(32);

			$data['upload'] = $this->url->link('tool/upload', 'language=' . $this->config->get('config_language') . '&upload_token=' . $this->session->data['upload_token']);

			$data['product_id'] = $product_id;

			$manufacturer_info = $this->model_catalog_manufacturer->getManufacturer($product_info['manufacturer_id']);

			if ($manufacturer_info) {
				$data['manufacturer'] = $manufacturer_info['name'];
			} else {
				$data['manufacturer'] = '';
			}

			$data['manufacturers'] = $this->url->link('product/manufacturer.info', 'language=' . $this->config->get('config_language') . '&manufacturer_id=' . $product_info['manufacturer_id']);
			$data['model'] = $product_info['model'];

			$data['product_codes'] = [];

			$results = $this->model_catalog_product->getCodes($product_id);

			foreach ($results as $result) {
				if ($result['status']) {
					$data['product_codes'][] = $result;
				}
			}

			$data['reward'] = $product_info['reward'];
			$data['points'] = $product_info['points'];
			$data['description'] = html_entity_decode($product_info['description'], ENT_QUOTES, 'UTF-8');

			// Stock Status
			if ($product_info['quantity'] <= 0) {
				$stock_status_id = $product_info['stock_status_id'];
			} elseif (!$this->config->get('config_stock_display')) {
				$stock_status_id = (int)$this->config->get('config_stock_status_id');
			} else {
				$stock_status_id = 0;
			}

			// Stock Status
			$this->load->model('localisation/stock_status');

			$stock_status_info = $this->model_localisation_stock_status->getStockStatus($stock_status_id);

			if ($stock_status_info) {
				$data['stock'] = $stock_status_info['name'];
			} else {
				$data['stock'] = $product_info['quantity'];
			}

			$data['rating'] = (int)$product_info['rating'];
			$data['review_status'] = (int)$this->config->get('config_review_status');
			$data['review'] = $this->load->controller('product/review');

			$data['wishlist_add'] = $this->url->link('account/wishlist.add', 'language=' . $this->config->get('config_language'));
			$data['compare_add'] = $this->url->link('product/compare.add', 'language=' . $this->config->get('config_language'));

			// Image
			$this->load->model('tool/image');

			if ($product_info['image'] && is_file(DIR_IMAGE . html_entity_decode($product_info['image'], ENT_QUOTES, 'UTF-8'))) {
				$data['popup'] = $this->model_tool_image->resize($product_info['image'], $this->config->get('config_image_popup_width'), $this->config->get('config_image_popup_height'));
				$data['thumb'] = $this->model_tool_image->resize($product_info['image'], $this->config->get('config_image_thumb_width'), $this->config->get('config_image_thumb_height'));
			} else {
				$data['popup'] = '';
				$data['thumb'] = '';
			}

			$data['images'] = [];

			$results = $this->model_catalog_product->getImages($product_id);

			foreach ($results as $result) {
				if ($result['image'] && is_file(DIR_IMAGE . html_entity_decode($result['image'], ENT_QUOTES, 'UTF-8'))) {
					$data['images'][] = [
						'popup' => $this->model_tool_image->resize($result['image'], $this->config->get('config_image_popup_width'), $this->config->get('config_image_popup_height')),
						'thumb' => $this->model_tool_image->resize($result['image'], $this->config->get('config_image_additional_width'), $this->config->get('config_image_additional_height'))
					];
				}
			}

			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$data['price'] = $this->currency->format($this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
			} else {
				$data['price'] = false;
			}

			if ((float)$product_info['special']) {
				$data['special'] = $this->currency->format($this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
			} else {
				$data['special'] = false;
			}

			if ($this->config->get('config_tax')) {
				$data['tax'] = $this->currency->format((float)$product_info['special'] ? $product_info['special'] : $product_info['price'], $this->session->data['currency']);
			} else {
				$data['tax'] = false;
			}

			$data['discounts'] = [];

			$data['options'] = [];

			// Check if product is variant
			if ($product_info['master_id']) {
				$master_id = (int)$product_info['master_id'];
			} else {
				$master_id = (int)$this->request->get['product_id'];
			}

			$product_options = $this->model_catalog_product->getOptions($master_id);

			foreach ($product_options as $option) {
				if ((int)$this->request->get['product_id'] && !isset($product_info['override']['variant'][$option['product_option_id']])) {
					$product_option_value_data = [];

					foreach ($option['product_option_value'] as $option_value) {
						if (!$option_value['subtract'] || ($option_value['quantity'] > 0)) {
							if ((($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price')) && (float)$option_value['price']) {
								$price = $this->currency->format($this->tax->calculate($option_value['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
							} else {
								$price = false;
							}

							if ($option_value['image'] && is_file(DIR_IMAGE . html_entity_decode($option_value['image'], ENT_QUOTES, 'UTF-8'))) {
								$image = $option_value['image'];
							} else {
								$image = '';
							}

							$product_option_value_data[] = [
								'image' => $this->model_tool_image->resize($image, 50, 50),
								'price' => $price
							] + $option_value;
						}
					}

					$data['options'][] = ['product_option_value' => $product_option_value_data] + $option;
				}
			}

			// Subscriptions
			$data['subscription_plans'] = [];

			$results = $this->model_catalog_product->getSubscriptions($product_id);

			foreach ($results as $result) {
				$description = '';

				if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
					if ($result['duration']) {
						$price = ($product_info['special'] ?: $product_info['price']) / $result['duration'];
					} else {
						$price = ($product_info['special'] ?: $product_info['price']);
					}

					$price = $this->currency->format($this->tax->calculate($price, $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
					$cycle = $result['cycle'];
					$frequency = $this->language->get('text_' . $result['frequency']);
					$duration = $result['duration'];

					if ($duration) {
						$description = sprintf($this->language->get('text_subscription_duration'), $price, $cycle, $frequency, $duration);
					} else {
						$description = sprintf($this->language->get('text_subscription_cancel'), $price, $cycle, $frequency);
					}
				}

				$data['subscription_plans'][] = ['description' => $description] + $result;
			}

			if ($product_info['minimum']) {
				$data['minimum'] = $product_info['minimum'];
			} else {
				$data['minimum'] = 1;
			}

			$data['share'] = $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . (int)$this->request->get['product_id']);

			$data['attribute_groups'] = $this->model_catalog_product->getAttributes($product_id);

			$this->applyPdpPackDisplay($data, $product_info);

			$data['pdp_size_intro'] = $this->buildPdpSinglePackIntro($data['pdp_pack_unit_label'] ?? '', $product_info);

			$data['related'] = $this->load->controller('product/related');

			$data['tags'] = [];

			if ($product_info['tag']) {
				$tags = explode(',', $product_info['tag']);

				foreach ($tags as $tag) {
					$data['tags'][] = [
						'tag'  => trim($tag),
						'href' => $this->url->link('product/search', 'language=' . $this->config->get('config_language') . '&tag=' . trim($tag))
					];
				}
			}

			if ($this->config->get('config_product_report_status')) {
				$this->model_catalog_product->addReport($this->request->get['product_id'], oc_get_ip());
			}
			
			// Generate Schema.org markup for Product and BreadcrumbList (after all data is prepared)
			$data['schema_product'] = $this->generateProductSchema($product_info, $product_id, $data);
			$data['schema_breadcrumb'] = $this->generateBreadcrumbSchema($data['breadcrumbs']);
			
			// Generate FAQPage Schema if FAQ data exists (optional - for future FAQ blocks)
			if (!empty($data['faq_items'])) {
				$data['schema_faq'] = $this->generateFAQPageSchema($data['faq_items']);
			}
			
			// Generate OpenGraph and Twitter Cards meta tags
			$data['og_tags'] = $this->generateOpenGraphTags($product_info, $product_id, $data);
			$data['twitter_tags'] = $this->generateTwitterTags($product_info, $product_id, $data);

			$data['language'] = $this->config->get('config_language');

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('product/product', $data));
		} else {
			return new \Opencart\System\Engine\Action('error/not_found');
		}

		return null;
	}

	/**
	 * PDP «Фасування»: pack boxes when the product has an imported **radio** option that looks like pack quantity
	 * (three values; labels come from option value names in the workbook — not hardcoded IDs).
	 *
	 * @param array<string, mixed> $data
	 */
	private function applyPdpPackDisplay(array &$data, array $product_info): void {
		$data['pdp_pack_options'] = [];
		$data['pdp_pack_product_option_id'] = 0;
		$data['pdp_pack_default_product_option_value_id'] = 0;
		$data['pdp_pack_unit_label'] = $this->getPdpPackagingUnitLabel($data['attribute_groups'] ?? [], $product_info);

		$pack_option = null;

		foreach ($data['options'] as $opt) {
			if ($this->isPackQuantityRadioOption($opt)) {
				$pack_option = $opt;

				break;
			}
		}

		if ($pack_option === null || empty($pack_option['product_option_value'])) {
			return;
		}

		$values = $pack_option['product_option_value'];

		foreach ($values as $ov) {
			$data['pdp_pack_options'][] = [
				'product_option_value_id' => (int)$ov['product_option_value_id'],
				'option_value_id'         => (int)($ov['option_value_id'] ?? 0),
				'title'                     => trim((string)($ov['name'] ?? '')),
				'price'                     => $ov['price'] ?? false,
			];
		}

		$pack_product_option_id = (int)$pack_option['product_option_id'];
		$data['pdp_pack_product_option_id'] = $pack_product_option_id;
		$data['pdp_pack_default_product_option_value_id'] = (int)($values[0]['product_option_value_id'] ?? 0);

		$data['options'] = array_values(array_filter($data['options'], static function (array $o) use ($pack_product_option_id): bool {
			return (int)($o['product_option_id'] ?? 0) !== $pack_product_option_id;
		}));
	}

	/**
	 * Radio option with three values whose names look like pack tiers (imported), or option name suggests pack quantity.
	 *
	 * @param array<string, mixed> $option
	 */
	private function isPackQuantityRadioOption(array $option): bool {
		if (($option['type'] ?? '') !== 'radio') {
			return false;
		}

		$values = $option['product_option_value'] ?? [];

		if (count($values) !== 3) {
			return false;
		}

		$name = mb_strtolower(trim((string)($option['name'] ?? '')));

		if (preg_match('/pack|упаков|кількість|quantity|количеств|фасу/i', $name)) {
			return true;
		}

		$numeric_prefix = 0;

		foreach ($values as $v) {
			$vn = trim((string)($v['name'] ?? ''));

			if ($vn !== '' && preg_match('/^\s*\d+/u', $vn)) {
				$numeric_prefix++;
			}
		}

		return $numeric_prefix === 3;
	}

	/**
	 * Label under each pack box (e.g. «1 гр», «30 шт») from ProductAttributes / Specification in import.
	 *
	 * @param array<int, array<string, mixed>> $attribute_groups
	 * @param array<string, mixed>            $product_info
	 */
	private function getPdpPackagingUnitLabel(array $attribute_groups, array $product_info = []): string {
		foreach ($attribute_groups as $group) {
			if (empty($group['attribute']) || !is_array($group['attribute'])) {
				continue;
			}

			foreach ($group['attribute'] as $attr) {
				$name = isset($attr['name']) ? trim((string)$attr['name']) : '';
				$text = isset($attr['text']) ? trim((string)$attr['text']) : '';

				if ($text === '' || $name === '') {
					continue;
				}

				if (mb_stripos($name, 'фасу') !== false) {
					return $text;
				}
			}
		}

		foreach ($attribute_groups as $group) {
			$group_name = isset($group['name']) ? trim((string)$group['name']) : '';

			if ($group_name === '') {
				continue;
			}

			if (mb_stripos($group_name, 'specification') === false && mb_stripos($group_name, 'специфікац') === false) {
				continue;
			}

			if (empty($group['attribute']) || !is_array($group['attribute'])) {
				continue;
			}

			foreach ($group['attribute'] as $attr) {
				$name = isset($attr['name']) ? trim((string)$attr['name']) : '';
				$text = isset($attr['text']) ? trim((string)$attr['text']) : '';

				if ($text === '') {
					continue;
				}

				if (preg_match('/фасу|упаков|weight|pack|вага|насіння\s+в/ui', $name)) {
					return $text;
				}
			}

			foreach ($group['attribute'] as $attr) {
				$text = isset($attr['text']) ? trim((string)$attr['text']) : '';

				if ($text !== '' && preg_match('/\d+\s*(гр|шт)/ui', $text)) {
					return $text;
				}
			}
		}

		foreach ($attribute_groups as $group) {
			if (empty($group['attribute']) || !is_array($group['attribute'])) {
				continue;
			}

			foreach ($group['attribute'] as $attr) {
				$text = isset($attr['text']) ? trim((string)$attr['text']) : '';

				if ($text !== '' && preg_match('/\d+\s*(гр|шт)/ui', $text)) {
					return $text;
				}
			}
		}

		$w = (float)($product_info['weight'] ?? 0);

		if ($w > 0) {
			return $this->weight->format(
				$w,
				(int)($product_info['weight_class_id'] ?? 0),
				$this->language->get('decimal_point'),
				$this->language->get('thousand_point')
			);
		}

		return '';
	}

	/**
	 * Single-pack Фасування line: "(1 грам)" / "(35 штук)" from attribute unit (гр / шт).
	 */
	private function buildPdpSinglePackIntro(string $unit_label, array $product_info = []): string {
		$unit_label = trim($unit_label);

		if ($unit_label === '') {
			return '';
		}

		$lang = (string)$this->config->get('config_language');

		if (preg_match('/(\d+(?:[.,]\d+)?)\s*(гр\.?|г)\b/ui', $unit_label, $m)) {
			$n = (int)round((float)str_replace(',', '.', $m[1]));

			if ($lang === 'uk-ua') {
				$phrase = $this->formatUkrainianGramsPhrase($n);
			} elseif ($lang === 'fr-fr') {
				$phrase = $this->formatFrenchGramsPhrase($n);
			} else {
				$phrase = $this->formatEnglishGramsPhrase($n);
			}

			return sprintf($this->language->get('text_pdp_size_intro_pack'), $phrase);
		}

		if (preg_match('/(\d+)\s*шт\.?/ui', $unit_label, $m)) {
			$n = (int)$m[1];

			if ($lang === 'uk-ua') {
				$phrase = $this->formatUkrainianPiecesPhrase($n);
			} elseif ($lang === 'fr-fr') {
				$phrase = $this->formatFrenchPiecesPhrase($n);
			} else {
				$phrase = $this->formatEnglishPiecesPhrase($n);
			}

			return sprintf($this->language->get('text_pdp_size_intro_pack'), $phrase);
		}

		return sprintf($this->language->get('text_pdp_size_intro_pack'), $unit_label);
	}

	private function formatUkrainianGramsPhrase(int $n): string {
		$mod100 = $n % 100;
		$mod10 = $n % 10;

		if ($mod100 >= 11 && $mod100 <= 14) {
			return $n . ' грамів';
		}

		if ($mod10 === 1) {
			return $n . ' грам';
		}

		if ($mod10 >= 2 && $mod10 <= 4) {
			return $n . ' грами';
		}

		return $n . ' грамів';
	}

	private function formatUkrainianPiecesPhrase(int $n): string {
		$mod100 = $n % 100;
		$mod10 = $n % 10;

		if ($mod100 >= 11 && $mod100 <= 14) {
			return $n . ' штук';
		}

		if ($mod10 === 1) {
			return $n . ' штука';
		}

		if ($mod10 >= 2 && $mod10 <= 4) {
			return $n . ' штуки';
		}

		return $n . ' штук';
	}

	private function formatEnglishGramsPhrase(int $n): string {
		return $n === 1 ? '1 gram' : $n . ' grams';
	}

	private function formatEnglishPiecesPhrase(int $n): string {
		return $n === 1 ? '1 piece' : $n . ' pieces';
	}

	private function formatFrenchGramsPhrase(int $n): string {
		return $n === 1 ? '1 gramme' : $n . ' grammes';
	}

	private function formatFrenchPiecesPhrase(int $n): string {
		return $n === 1 ? '1 pièce' : $n . ' pièces';
	}
	
	/**
	 * Generate Product Schema.org JSON-LD
	 *
	 * @param array $product_info
	 * @param int   $product_id
	 * @param array $data
	 *
	 * @return string
	 */
	private function generateProductSchema(array $product_info, int $product_id, array $data): string {
		$base_url = $this->config->get('config_url');
		$product_url = $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $product_id, true);
		
		// Get price (use special if available, otherwise regular price)
		$price_value = (float)($product_info['special'] ?: $product_info['price']);
		$currency_code = $this->session->data['currency'] ?? $this->config->get('config_currency');
		
		// Get availability
		$availability = 'https://schema.org/InStock';
		if ($product_info['quantity'] <= 0) {
			$availability = 'https://schema.org/OutOfStock';
		}
		
		// Get image with ImageObject structure
		$image_data = [];
		if (!empty($data['popup'])) {
			$image_url = $data['popup'];
			// Try to get image dimensions if available
			$image_width = $this->config->get('config_image_popup_width') ?: 800;
			$image_height = $this->config->get('config_image_popup_height') ?: 800;
			$image_data[] = [
				'@type' => 'ImageObject',
				'url' => $image_url,
				'width' => (int)$image_width,
				'height' => (int)$image_height
			];
		} elseif (!empty($product_info['image'])) {
			$this->load->model('tool/image');
			$image_url = $this->model_tool_image->resize($product_info['image'], $this->config->get('config_image_popup_width'), $this->config->get('config_image_popup_height'));
			$image_width = $this->config->get('config_image_popup_width') ?: 800;
			$image_height = $this->config->get('config_image_popup_height') ?: 800;
			$image_data[] = [
				'@type' => 'ImageObject',
				'url' => $image_url,
				'width' => (int)$image_width,
				'height' => (int)$image_height
			];
		}
		
		// Get brand/manufacturer
		$brand = '';
		if (!empty($data['manufacturer'])) {
			$brand = $data['manufacturer'];
		}
		
		// Get SKU
		$sku = $product_info['model'] ?? '';
		
		// Get rating - ONLY if reviews exist
		$rating_value = (float)$product_info['rating'];
		$review_count = (int)$product_info['reviews'];
		
		$schema = [
			'@context' => 'https://schema.org',
			'@type' => 'Product',
			'name' => $product_info['name'],
			'description' => strip_tags(html_entity_decode($product_info['description'] ?? '', ENT_QUOTES, 'UTF-8')),
			'image' => $image_data ?: [],
			'sku' => $sku,
			'brand' => $brand ? ['@type' => 'Brand', 'name' => $brand] : null,
			'offers' => [
				'@type' => 'Offer',
				'url' => $product_url,
				'priceCurrency' => $currency_code,
				'price' => number_format($price_value, 2, '.', ''),
				'availability' => $availability,
				'priceValidUntil' => date('Y-m-d', strtotime('+1 year')),
				'seller' => [
					'@type' => 'Organization',
					'name' => $this->config->get('config_name')
				]
			]
		];
		
		// Add rating ONLY if reviews exist (Google requirement)
		// Do not add AggregateRating if review_count is 0 to avoid schema rejection
		if ($review_count > 0 && $rating_value > 0) {
			$schema['aggregateRating'] = [
				'@type' => 'AggregateRating',
				'ratingValue' => number_format($rating_value, 1),
				'reviewCount' => $review_count
			];
		}
		
		// Remove null values
		$schema = array_filter($schema, function($value) {
			return $value !== null;
		});
		
		return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
	}
	
	/**
	 * Generate BreadcrumbList Schema.org JSON-LD
	 *
	 * @param array $breadcrumbs
	 *
	 * @return string
	 */
	private function generateBreadcrumbSchema(array $breadcrumbs): string {
		$items = [];
		$position = 1;
		
		foreach ($breadcrumbs as $breadcrumb) {
			$items[] = [
				'@type' => 'ListItem',
				'position' => $position++,
				'name' => $breadcrumb['text'],
				'item' => $breadcrumb['href']
			];
		}
		
		$schema = [
			'@context' => 'https://schema.org',
			'@type' => 'BreadcrumbList',
			'itemListElement' => $items
		];
		
		return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
	}
	
	/**
	 * Generate FAQPage Schema.org JSON-LD
	 * For FAQ blocks on product pages (when FAQ data is available)
	 *
	 * @param array $faq_items Array of FAQ items with 'question' and 'answer' keys
	 *
	 * @return string
	 */
	private function generateFAQPageSchema(array $faq_items): string {
		$main_entity = [];
		
		foreach ($faq_items as $faq) {
			if (!empty($faq['question']) && !empty($faq['answer'])) {
				$main_entity[] = [
					'@type' => 'Question',
					'name' => strip_tags($faq['question']),
					'acceptedAnswer' => [
						'@type' => 'Answer',
						'text' => strip_tags($faq['answer'])
					]
				];
			}
		}
		
		if (empty($main_entity)) {
			return '';
		}
		
		$schema = [
			'@context' => 'https://schema.org',
			'@type' => 'FAQPage',
			'mainEntity' => $main_entity
		];
		
		return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
	}
	
	/**
	 * Generate OpenGraph meta tags
	 *
	 * @param array $product_info
	 * @param int   $product_id
	 * @param array $data
	 *
	 * @return array
	 */
	private function generateOpenGraphTags(array $product_info, int $product_id, array $data): array {
		$base_url = rtrim($this->config->get('config_url'), '/');
		$product_url = $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $product_id, true);
		
		$og = [
			'og:type' => 'product',
			'og:title' => $product_info['name'],
			'og:url' => $product_url,
			'og:site_name' => $this->config->get('config_name'),
		];
		
		// Description
		if (!empty($product_info['meta_description'])) {
			$og['og:description'] = htmlspecialchars(strip_tags($product_info['meta_description']), ENT_QUOTES, 'UTF-8');
		} elseif (!empty($product_info['description'])) {
			$description = strip_tags(html_entity_decode($product_info['description'], ENT_QUOTES, 'UTF-8'));
			$og['og:description'] = htmlspecialchars(mb_substr($description, 0, 200), ENT_QUOTES, 'UTF-8');
		}
		
		// Image
		if (!empty($data['popup'])) {
			$og['og:image'] = $data['popup'];
			$og['og:image:width'] = $this->config->get('config_image_popup_width') ?: 800;
			$og['og:image:height'] = $this->config->get('config_image_popup_height') ?: 800;
		}
		
		// Price
		if (!empty($product_info['price'])) {
			$price_value = (float)($product_info['special'] ?: $product_info['price']);
			$currency_code = $this->session->data['currency'] ?? $this->config->get('config_currency');
			$og['product:price:amount'] = number_format($price_value, 2, '.', '');
			$og['product:price:currency'] = $currency_code;
		}
		
		// Availability
		if ($product_info['quantity'] > 0) {
			$og['product:availability'] = 'in stock';
		} else {
			$og['product:availability'] = 'out of stock';
		}
		
		return $og;
	}
	
	/**
	 * Generate Twitter Card meta tags
	 *
	 * @param array $product_info
	 * @param int   $product_id
	 * @param array $data
	 *
	 * @return array
	 */
	private function generateTwitterTags(array $product_info, int $product_id, array $data): array {
		$twitter = [
			'twitter:card' => 'summary_large_image',
			'twitter:title' => $product_info['name'],
		];
		
		// Description
		if (!empty($product_info['meta_description'])) {
			$twitter['twitter:description'] = htmlspecialchars(strip_tags($product_info['meta_description']), ENT_QUOTES, 'UTF-8');
		} elseif (!empty($product_info['description'])) {
			$description = strip_tags(html_entity_decode($product_info['description'], ENT_QUOTES, 'UTF-8'));
			$twitter['twitter:description'] = htmlspecialchars(mb_substr($description, 0, 200), ENT_QUOTES, 'UTF-8');
		}
		
		// Image
		if (!empty($data['popup'])) {
			$twitter['twitter:image'] = $data['popup'];
		}
		
		return $twitter;
	}
}
