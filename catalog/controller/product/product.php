<?php
namespace Opencart\Catalog\Controller\Product;

use Opencart\System\Library\Pack\PackLabel;
use Opencart\System\Library\Pack\PackPricing;

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
				if ((float)$product_info['special']) {
					// Was price = catalog base (product.price); sale = special — never use tier-discounted price as "was" when a sale exists.
					$data['price'] = $this->currency->format($this->tax->calculate((float)$product_info['raw_price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
					$data['special'] = $this->currency->format($this->tax->calculate((float)$product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				} else {
					$data['price'] = $this->currency->format($this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
					$data['special'] = false;
				}
			} else {
				$data['price'] = false;
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
							$can_show_option_price = ($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price');
							$raw_option_price = (float)$option_value['price'];
							$raw_option_prefix = (string)($option_value['price_prefix'] ?? '+');

							$price = false;

							if ($can_show_option_price && $raw_option_price != 0.0) {
								$price = $this->currency->format($this->tax->calculate($raw_option_price, $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
							}

							if ($option_value['image'] && is_file(DIR_IMAGE . html_entity_decode($option_value['image'], ENT_QUOTES, 'UTF-8'))) {
								$image = $option_value['image'];
							} else {
								$image = '';
							}

							$product_option_value_data[] = [
								'image'             => $this->model_tool_image->resize($image, 50, 50),
								'price'             => $price,
								'option_price_raw'  => $raw_option_price,
								'option_price_prefix' => $raw_option_prefix,
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

			$this->enrichPackOptionValues($product_info, $data['options']);

			$this->applyPdpPackDisplay($data);

			$data['pdp_size_intro'] = $this->buildPdpSinglePackIntro($data['attribute_groups'] ?? [], $data['pdp_pack_unit_label'] ?? '');

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
	 * Replace raw option modifiers with deterministic pack lines (OPTION → special ratio → unit).
	 *
	 * @param array<string, mixed>             $product_info
	 * @param array<int, array<string, mixed>> $options
	 */
	private function enrichPackOptionValues(array $product_info, array &$options): void {
		$catalog_base = (float)($product_info['catalog_base_price'] ?? $product_info['raw_price'] ?? 0);
		$special = (float)($product_info['special'] ?? 0);
		$can_show = ($this->customer->isLogged() || !$this->config->get('config_customer_price'));

		foreach ($options as &$option) {
			if (empty($option['product_option_value'])) {
				continue;
			}

			foreach ($option['product_option_value'] as &$ov) {
				$pack_size = PackPricing::parsePackSizeFromLabel((string)($ov['name'] ?? ''));

				if ($pack_size === null) {
					continue;
				}

				$row = PackPricing::computePackDisplayRow(
					$catalog_base,
					(float)($ov['option_price_raw'] ?? 0),
					(string)($ov['option_price_prefix'] ?? '+'),
					$special,
					$pack_size
				);

				if ($can_show) {
					$ov['pack_display'] = $this->buildPackOptionDisplay($product_info, $row, $pack_size);
					$ov['price'] = false;
				}
			}
			unset($ov);
		}
		unset($option);
	}

	/**
	 * Multi-line pack display: label, total, unit; optional savings vs catalog list unit (same as raw_price).
	 *
	 * @param array<string, mixed> $product_info
	 * @param array{final_price: float, unit_price: float, pack_size: int} $row
	 *
	 * @return array{label: string, total_formatted: string, unit_formatted: string, savings_percent: float|null, select_label: string, line_was_formatted: string}
	 */
	private function buildPackOptionDisplay(array $product_info, array $row, int $pack_size): array {
		$list_unit = (float)($product_info['catalog_base_price'] ?? $product_info['raw_price'] ?? 0);
		$catalog_base = (float)($product_info['catalog_base_price'] ?? $product_info['raw_price'] ?? 0);
		$tax_class_id = (int)$product_info['tax_class_id'];
		$config_tax = $this->config->get('config_tax');
		$final_taxed = $this->tax->calculate($row['final_price'], $tax_class_id, $config_tax);
		$unit_taxed = $this->tax->calculate($row['unit_price'], $tax_class_id, $config_tax);
		$total_fmt = $this->currency->format($final_taxed, $this->session->data['currency']);
		$unit_fmt = $this->currency->format($unit_taxed, $this->session->data['currency']);
		$savings = PackPricing::unitSavingsVsListUnit( $list_unit, $row['unit_price'] );

		$list_line = $catalog_base * (float) $pack_size;
		$sale_line = $row['final_price'];
		$line_was_formatted = '';

		if (abs($list_line - $sale_line) > 0.0001) {
			$line_was_formatted = $this->currency->format(
				$this->tax->calculate($list_line, $tax_class_id, $config_tax),
				$this->session->data['currency']
			);
		}

		$label = PackLabel::ukPackCount($pack_size);

		return [
			'label'               => $label,
			'total_formatted'     => $total_fmt,
			'unit_formatted'      => $unit_fmt . ' / пак.',
			'savings_percent'     => $savings,
			'select_label'        => PackLabel::ukPackShort($pack_size) . ' — ' . $total_fmt . ' · ' . $unit_fmt . '/п.',
			'line_was_formatted'  => $line_was_formatted,
		];
	}

	/**
	 * PDP «Фасування»: pack boxes when the product has an imported **radio** option that looks like pack quantity
	 * (three values; labels come from option value names in the workbook — not hardcoded IDs).
	 *
	 * @param array<string, mixed> $data
	 */
	private function applyPdpPackDisplay(array &$data): void {
		$data['pdp_pack_options'] = [];
		$data['pdp_pack_product_option_id'] = 0;
		$data['pdp_pack_default_product_option_value_id'] = 0;
		$data['pdp_pack_unit_label'] = '';
		$attrs = $data['attribute_groups'] ?? [];

		$pack_option = null;

		foreach ($data['options'] as $opt) {
			if ($this->isPackQuantityRadioOption($opt)) {
				$pack_option = $opt;

				break;
			}
		}

		if ($pack_option === null || empty($pack_option['product_option_value'])) {
			$data['pdp_pack_unit_label'] = $this->getPdpPackagingUnitLabel($attrs);

			return;
		}

		$values = $pack_option['product_option_value'];

		foreach ($values as $ov) {
			$pd = $ov['pack_display'] ?? [];

			$data['pdp_pack_options'][] = [
				'product_option_value_id'   => (int)$ov['product_option_value_id'],
				'option_value_id'           => (int)($ov['option_value_id'] ?? 0),
				'pack_label'                => (string)($pd['label'] ?? ''),
				'pack_total_formatted'      => (string)($pd['total_formatted'] ?? ''),
				'pack_unit_formatted'       => (string)($pd['unit_formatted'] ?? ''),
				'pack_savings_percent'      => $pd['savings_percent'] ?? null,
				'pack_line_was_formatted'   => (string)($pd['line_was_formatted'] ?? ''),
			];
		}

		$pack_product_option_id = (int)$pack_option['product_option_id'];
		$data['pdp_pack_product_option_id'] = $pack_product_option_id;
		$data['pdp_pack_default_product_option_value_id'] = (int)($values[0]['product_option_value_id'] ?? 0);

		$data['options'] = array_values(array_filter($data['options'], static function (array $o) use ($pack_product_option_id): bool {
			return (int)($o['product_option_id'] ?? 0) !== $pack_product_option_id;
		}));

		// Pack tiers (1 уп / 2 уп / 5 уп): show only Specification Weight (gram), not legacy «фасу» lines (e.g. bulk 5000 гр).
		$specGrams = $this->findSpecificationWeightGramInt($attrs);
		$data['pdp_pack_unit_label'] = ($specGrams !== null && $specGrams > 0) ? ($specGrams . ' гр') : '';
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
	 * Specification → Weight (e.g. 1.00) + Weight Class (gram / gramm) → integer grams, no conversions.
	 */
	private function findSpecificationWeightGramInt(array $attribute_groups): ?int {
		// 1) Merge Weight + Weight Class across all groups named Specification (OC often splits rows).
		$n = $this->parseWeightGramIntMergedFromGroups($attribute_groups, true);

		if ($n !== null) {
			return $n;
		}

		// 2) Same group has both attributes.
		foreach ($attribute_groups as $group) {
			$n = $this->parseWeightAndClassGramIntFromAttributeGroup($group);

			if ($n !== null) {
				return $n;
			}
		}

		return null;
	}

	/**
	 * Collect Weight and Weight Class from one or more attribute groups and return integer grams.
	 *
	 * @param array<int, array<string, mixed>> $attribute_groups
	 */
	private function parseWeightGramIntMergedFromGroups(array $attribute_groups, bool $specificationGroupsOnly): ?int {
		$weightRaw = null;
		$classRaw = null;

		foreach ($attribute_groups as $group) {
			$gn = isset($group['name']) ? trim((string)$group['name']) : '';

			if ($specificationGroupsOnly) {
				if (mb_stripos($gn, 'specification') === false && mb_stripos($gn, 'специфікац') === false) {
					continue;
				}
			}

			if (empty($group['attribute']) || !is_array($group['attribute'])) {
				continue;
			}

			foreach ($group['attribute'] as $attr) {
				$name = mb_strtolower(trim((string)($attr['name'] ?? '')));
				$text = trim((string)($attr['text'] ?? ''));

				if ($text === '') {
					continue;
				}

				if ($name === 'weight') {
					$weightRaw = $weightRaw ?? $text;
				}

				if ($name === 'weight class' || $name === 'weight_class') {
					$classRaw = $classRaw ?? $text;
				}
			}
		}

		if ($weightRaw === null || $classRaw === null || $weightRaw === '') {
			return null;
		}

		return $this->gramsIntFromWeightAndClassStrings($weightRaw, $classRaw);
	}

	/**
	 * @param array<string, mixed> $group
	 */
	private function parseWeightAndClassGramIntFromAttributeGroup(array $group): ?int {
		if (empty($group['attribute']) || !is_array($group['attribute'])) {
			return null;
		}

		$weightRaw = null;
		$classRaw = null;

		foreach ($group['attribute'] as $attr) {
			$name = mb_strtolower(trim((string)($attr['name'] ?? '')));
			$text = trim((string)($attr['text'] ?? ''));

			if ($name === 'weight') {
				$weightRaw = $text;
			}

			if ($name === 'weight class' || $name === 'weight_class') {
				$classRaw = $text;
			}
		}

		if ($weightRaw === null || $classRaw === null || $weightRaw === '') {
			return null;
		}

		return $this->gramsIntFromWeightAndClassStrings($weightRaw, $classRaw);
	}

	private function gramsIntFromWeightAndClassStrings(string $weightRaw, string $classRaw): ?int {
		if (!$this->isSpecificationGramClassLabel($classRaw)) {
			return null;
		}

		$normalized = str_replace(',', '.', preg_replace('/\s+/u', '', $weightRaw));

		if (!is_numeric($normalized)) {
			return null;
		}

		$w = (float)$normalized;

		if ($w <= 0) {
			return null;
		}

		return (int)floor($w);
	}

	private function isSpecificationGramClassLabel(string $label): bool {
		$s = mb_strtolower(trim($label));

		if ($s === '') {
			return false;
		}

		if (preg_match('/kilo|кг|\bkg\b|milli|мг\b|mg\b/ui', $s)) {
			return false;
		}

		return (bool)preg_match('/^(gramm|grams|gramme|grammes|gram|грам|г|гр|g)$/u', $s);
	}

	/**
	 * Label under each pack box (e.g. «1 гр», «30 шт») from Specification or legacy attributes.
	 *
	 * @param array<int, array<string, mixed>> $attribute_groups
	 */
	private function getPdpPackagingUnitLabel(array $attribute_groups): string {
		$specGrams = $this->findSpecificationWeightGramInt($attribute_groups);

		if ($specGrams !== null && $specGrams > 0) {
			return $specGrams . ' гр';
		}

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

		return '';
	}

	/**
	 * Single-pack Фасування line from Specification Weight + Weight Class (gram), else legacy unit label.
	 *
	 * @param array<int, array<string, mixed>> $attribute_groups
	 */
	private function buildPdpSinglePackIntro(array $attribute_groups, string $unit_label = ''): string {
		$lang = (string)$this->config->get('config_language');

		$specGrams = $this->findSpecificationWeightGramInt($attribute_groups);

		if ($specGrams !== null && $specGrams > 0) {
			if ($lang === 'uk-ua') {
				$phrase = $this->formatUkrainianGramsPhrase($specGrams);
			} elseif ($lang === 'fr-fr') {
				$phrase = $this->formatFrenchGramsPhrase($specGrams);
			} else {
				$phrase = $this->formatEnglishGramsPhrase($specGrams);
			}

			return sprintf($this->language->get('text_pdp_size_intro_pack'), $phrase);
		}

		$unit_label = trim($unit_label);

		if ($unit_label === '') {
			return '';
		}

		if (preg_match('/(\d+(?:[.,]\d+)?)\s*(гр\.?|г)\b/ui', $unit_label, $m)) {
			$n = (int)max(1, round((float)str_replace(',', '.', $m[1])));

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
