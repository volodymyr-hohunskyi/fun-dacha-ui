<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Shop Popular Crops – up to 4 featured categories (keyword match), image-only tiles linking to category.
 *
 * @package Opencart\Catalog\Controller\Common
 */
class PopularCrops extends \Opencart\System\Engine\Controller {
	/** Substrings matched against category name (lowercase) to include in the widget. */
	private const KEYWORDS = ['томат', 'помідор', 'огір', 'перець', 'квіт', 'flower'];

	public function index(): string {
		$this->load->language('common/popular_crops');
		$this->load->model('catalog/category');
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$data['heading_title'] = $this->language->get('heading_title');
		$data['categories'] = [];

		$all = $this->model_catalog_category->getCategories(0);
		$found = [];

		foreach ($all as $cat) {
			if (count($data['categories']) >= 4) {
				break;
			}
			if (isset($found[(int)$cat['category_id']])) {
				continue;
			}
			$name_lower = mb_strtolower($cat['name']);
			$matched = false;
			foreach (self::KEYWORDS as $kw) {
				if (mb_strpos($name_lower, $kw) !== false) {
					$matched = true;
					break;
				}
			}
			if (!$matched) {
				continue;
			}
			$image = '';
			if ($cat['image'] && is_file(DIR_IMAGE . html_entity_decode($cat['image'], ENT_QUOTES, 'UTF-8'))) {
				$image = $this->model_tool_image->resize(html_entity_decode($cat['image'], ENT_QUOTES, 'UTF-8'), 600, 450);
			} else {
				$image = $this->model_tool_image->resize('placeholder.png', 600, 450);
			}
			$data['categories'][] = [
				'category_id'   => $cat['category_id'],
				'name'          => $cat['name'],
				'image'         => $image,
				'product_count' => $this->model_catalog_product->getTotalProducts(['filter_category_id' => (int)$cat['category_id']]),
				'href'          => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $cat['category_id']),
			];
			$found[(int)$cat['category_id']] = true;
		}

		return $this->load->view('common/popular_crops', $data);
	}
}
