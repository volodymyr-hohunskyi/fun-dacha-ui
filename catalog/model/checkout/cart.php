<?php
namespace Opencart\Catalog\Model\Checkout;

use Opencart\System\Library\Pack\PackLabel;
use Opencart\System\Library\Pack\PackPricing;

/**
 * Class Cart
 *
 * Can be called using $this->load->model('checkout/cart');
 *
 * @package Opencart\Catalog\Model\Checkout
 */
class Cart extends \Opencart\System\Engine\Model {
	/**
	 * Get Products
	 *
	 * @return array<int, array<string, mixed>> product records
	 *
	 * @example
	 *
	 * $this->load->model('checkout/cart');
	 *
	 * $cart = $this->model_checkout_cart->getProducts();
	 */
	public function getProducts(): array {
		$this->load->language('checkout/cart');

		// Upload
		$this->load->model('tool/upload');
		$this->load->model('catalog/product');

		// Products
		$product_data = [];

		$products = $this->cart->getProducts();
		$product_info_cache = [];
		$product_options_cache = [];

		foreach ($products as $product) {
			if ($product['image'] && is_file(DIR_IMAGE . html_entity_decode($product['image'], ENT_QUOTES, 'UTF-8'))) {
				$image = $product['image'];
			} else {
				$image = 'placeholder.png';
			}

			$option_data = [];

			foreach ($product['option'] as $option) {
				$value = $option['value'];

				if ($option['type'] == 'date') {
					$value = date('Y-m-d', strtotime($option['value']));
				}

				if ($option['type'] == 'time') {
					$value = date('H:i:s', strtotime($option['value']));
				}

				if ($option['type'] == 'datetime') {
					$value = date('Y-m-d H:i:s', strtotime($option['value']));
				}

				if ($option['type'] == 'file') {
					$upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

					if ($upload_info) {
						$value = $upload_info['code'];
					}
				}

				$option_data[] = ['value' => $value] + $option;
			}

			$pid = (int)$product['product_id'];

			if (!isset($product_info_cache[$pid])) {
				$product_info_cache[$pid] = $this->model_catalog_product->getProduct($pid);
			}

			$pinfo = $product_info_cache[$pid] ?? [];

			if ($pinfo) {
				$this->enrichPackOptionDisplay($option_data, $product, $pinfo);

				if (!isset($product_options_cache[$pid])) {
					$product_options_cache[$pid] = $this->model_catalog_product->getOptions($pid);
				}
			}

			$cart_pack = ($pinfo) ? $this->buildCartPackPricingRow($product, $option_data, $pinfo, $product_options_cache[$pid] ?? []) : ['has_pack' => false];

			$subscription_data = [];

			if ($product['subscription']) {
				$subscription_data = [
					'trial_frequency_text' => $this->language->get('text_' . $product['subscription']['trial_frequency']),
					'trial_price_text'     => $this->currency->format($this->tax->calculate($product['subscription']['trial_price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']),
					'frequency_text'       => $this->language->get('text_' . $product['subscription']['frequency']),
					'price_text'           => $this->currency->format($this->tax->calculate($product['subscription']['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency'])
				] + $product['subscription'];
			}

			$product_data[] = [
				'image'        => $image,
				'subscription' => $subscription_data,
				'option'       => $option_data,
				'price_text'   => $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']),
				'total_text'   => $this->currency->format($this->tax->calculate($product['total'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']),
				'cart_pack'    => !empty($cart_pack['has_pack']) ? $cart_pack : null,
			] + $product;
		}

		return $product_data;
	}

	/**
	 * Get Totals
	 *
	 * @param array<int, array<string, mixed>> $totals
	 * @param array<int, float>                $taxes
	 * @param int                              $total
	 *
	 * @return void
	 */
	public function getTotals(array &$totals, array &$taxes, int &$total): void {
		$sort_order = [];

		// Extension
		$this->load->model('setting/extension');

		$results = $this->model_setting_extension->getExtensionsByType('total');

		foreach ($results as $key => $value) {
			$sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
		}

		array_multisort($sort_order, SORT_ASC, $results);

		foreach ($results as $result) {
			if ($this->config->get('total_' . $result['code'] . '_status')) {
				$this->load->model('extension/' . $result['extension'] . '/total/' . $result['code']);

				// __call magic method cannot pass-by-reference so PHP calls it as an anonymous function.
				($this->{'model_extension_' . $result['extension'] . '_total_' . $result['code']}->getTotal)($totals, $taxes, $total);
			}
		}

		$sort_order = [];

		foreach ($totals as $key => $value) {
			$sort_order[$key] = $value['sort_order'];
		}

		array_multisort($sort_order, SORT_ASC, $totals);
	}

	/**
	 * Replace option value labels with pack display lines (same logic as product page).
	 *
	 * @param array<int, array<string, mixed>> $option_data
	 * @param array<string, mixed>             $product
	 * @param array<string, mixed>             $product_info
	 */
	private function enrichPackOptionDisplay(array &$option_data, array $product, array $product_info): void {
		if (($this->config->get('config_customer_price') && !$this->customer->isLogged()) || !isset($product_info['raw_price'])) {
			return;
		}

		$catalog_base = (float)($product_info['catalog_base_price'] ?? $product_info['raw_price']);
		$special = (float)($product_info['special'] ?? 0);
		$tax_class_id = (int)($product['tax_class_id'] ?? 0);

		foreach ($option_data as &$opt) {
			$type = $opt['type'] ?? '';

			if (!in_array($type, ['select', 'radio', 'checkbox'], true)) {
				continue;
			}

			$label = (string)($opt['value'] ?? '');
			$pack_size = PackPricing::parsePackSizeFromLabel($label);

			if ($pack_size === null) {
				continue;
			}

			$row = PackPricing::computePackDisplayRow(
				$catalog_base,
				(float)($opt['price'] ?? 0),
				(string)($opt['price_prefix'] ?? '+'),
				$special,
				$pack_size
			);

			$final_taxed = $this->tax->calculate($row['final_price'], $tax_class_id, $this->config->get('config_tax'));
			$unit_taxed = $this->tax->calculate($row['unit_price'], $tax_class_id, $this->config->get('config_tax'));
			$fmt = $this->currency->format($final_taxed, $this->session->data['currency']);
			$unit_fmt = $this->currency->format($unit_taxed, $this->session->data['currency']);
			$unit_line = $unit_fmt . ' / пак.';
			$title = PackLabel::ukPackCount($pack_size);
			$opt['pack_size'] = $pack_size;

			$html = '<span class="d-block fw-semibold">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span>';
			$html .= '<span class="d-block">' . htmlspecialchars($fmt, ENT_QUOTES, 'UTF-8') . '</span>';
			$html .= '<span class="d-block text-muted small">' . htmlspecialchars($unit_line, ENT_QUOTES, 'UTF-8') . '</span>';

			$opt['pack_display_html'] = $html;
			$opt['value'] = $title . ' — ' . $fmt . ' — ' . $unit_fmt . '/пак.';
			$opt['pack_cart_line'] = true;
		}
		unset($opt);
	}


	/**
	 * Cart table row: pack label + sale for full option line; “was” = N × catalog list for 1 pack (same as 1-pack strikethrough × quantity of packs).
	 *
	 * @param array<string, mixed>             $product
	 * @param array<int, array<string, mixed>> $option_data
	 * @param array<string, mixed>             $product_info
	 *
	 * @param array<int, array<string, mixed>> $product_options_all
	 *
	 * @return array{has_pack: bool, pack_label?: string, line_sale?: string, line_was?: string, tier_subtitle?: string}
	 */
	private function buildCartPackPricingRow(array $product, array $option_data, array $product_info, array $product_options_all): array {
		if (($this->config->get('config_customer_price') && !$this->customer->isLogged()) || !isset($product_info['raw_price'])) {
			return ['has_pack' => false];
		}

		$catalog_base = (float)($product_info['catalog_base_price'] ?? $product_info['raw_price']);
		$special = (float)($product_info['special'] ?? 0);
		$tax_class_id = (int)($product['tax_class_id'] ?? 0);
		$tax_mode = $this->config->get('config_tax');

		foreach ($option_data as $opt) {
			$pack_size = (int)($opt['pack_size'] ?? 0);

			if ($pack_size < 1) {
				continue;
			}

			$row = PackPricing::computePackDisplayRow(
				$catalog_base,
				(float)($opt['price'] ?? 0),
				(string)($opt['price_prefix'] ?? '+'),
				$special,
				$pack_size
			);

			// Sale = full option line after special (e.g. 5 packs → ₴50.40). “Was” = N × 1-pack catalog list (e.g. 5×₴14 = ₴70), not option list from modifier alone.
			$list_line = $catalog_base * (float) $pack_size;
			$sale_one = $row['final_price'];

			$list_one_f = $this->currency->format($this->tax->calculate($list_line, $tax_class_id, $tax_mode), $this->session->data['currency']);
			$sale_one_f = $this->currency->format($this->tax->calculate($sale_one, $tax_class_id, $tax_mode), $this->session->data['currency']);
			$show_line_was = (abs($list_line - $sale_one) > 0.0001);

			$product_option_id = (int)($opt['product_option_id'] ?? 0);
			$tier_subtitle = $this->buildPackUnitTierSubtitle($product_info, $product, $product_option_id, $product_options_all);

			return [
				'has_pack'       => true,
				'pack_label'     => PackLabel::ukPackCount($pack_size),
				'line_sale'      => $sale_one_f,
				'line_was'       => $show_line_was ? $list_one_f : '',
				'tier_subtitle'  => $tier_subtitle,
			];
		}

		return ['has_pack' => false];
	}

	/**
	 * Cart «Ціна за одиницю» subtitle: all pack tiers as «1 п. * ₴12.80 (−10%), 5 п. * ₴10.08 (−29%)».
	 *
	 * @param array<string, mixed>             $product_info
	 * @param array<string, mixed>             $product
	 * @param array<int, array<string, mixed>> $product_options_all
	 */
	private function buildPackUnitTierSubtitle(array $product_info, array $product, int $product_option_id, array $product_options_all): string {
		if ($product_option_id < 1 || $product_options_all === []) {
			return '';
		}

		$catalog_base = (float)($product_info['catalog_base_price'] ?? $product_info['raw_price']);
		$special = (float)($product_info['special'] ?? 0);
		$tax_class_id = (int)($product['tax_class_id'] ?? 0);
		$tax_mode = $this->config->get('config_tax');

		$values = [];

		foreach ($product_options_all as $po) {
			if ((int)($po['product_option_id'] ?? 0) === $product_option_id) {
				$values = $po['product_option_value'] ?? [];

				break;
			}
		}

		if ($values === []) {
			return '';
		}

		$rows = [];

		foreach ($values as $pov) {
			$pack_size = PackPricing::parsePackSizeFromLabel((string)($pov['name'] ?? ''));

			if ($pack_size === null) {
				continue;
			}

			$row = PackPricing::computePackDisplayRow(
				$catalog_base,
				(float)($pov['price'] ?? 0),
				(string)($pov['price_prefix'] ?? '+'),
				$special,
				$pack_size
			);
			$rows[] = ['pack_size' => $pack_size, 'unit' => $row['unit_price']];
		}

		if ($rows === []) {
			return '';
		}

		usort($rows, static function (array $a, array $b): int {
			return $a['pack_size'] <=> $b['pack_size'];
		});

		$parts = [];

		foreach ($rows as $item) {
			$unit_taxed = $this->tax->calculate($item['unit'], $tax_class_id, $tax_mode);
			$unit_fmt = $this->currency->format($unit_taxed, $this->session->data['currency']);
			$pct = PackPricing::unitSavingsPercentVsListUnit($catalog_base, $item['unit']);
			$label = PackLabel::ukPackShort($item['pack_size']);
			$segment = $label . ' * ' . $unit_fmt;

			if ($pct !== null && $pct > 0.0) {
				$pct_str = (abs($pct - round($pct)) < 0.05) ? (string)(int)round($pct) : rtrim(rtrim(number_format($pct, 1, '.', ''), '0'), '.');
				$segment .= ' (−' . $pct_str . '%)';
			}

			$parts[] = $segment;
		}

		return implode(', ', $parts);
	}
}
