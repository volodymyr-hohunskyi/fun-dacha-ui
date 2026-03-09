<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Shop Popular Crops – 4 featured categories: Tomatoes, Cucumbers, Peppers, Flowers.
 *
 * @package Opencart\Catalog\Controller\Common
 */
class PopularCrops extends \Opencart\System\Engine\Controller {
	private const KEYWORDS = ['томат', 'помідор', 'огір', 'перець', 'квіт', 'flower'];

	public function index(): string {
		$this->load->language('common/popular_crops');
		$this->load->model('catalog/category');
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
			foreach (self::KEYWORDS as $kw) {
				if (mb_strpos($name_lower, $kw) !== false) {
					$image = '';
					if ($cat['image'] && is_file(DIR_IMAGE . html_entity_decode($cat['image'], ENT_QUOTES, 'UTF-8'))) {
					$image = $this->model_tool_image->resize(html_entity_decode($cat['image'], ENT_QUOTES, 'UTF-8'), 228, 228);
				} else {
					$image = $this->model_tool_image->resize('placeholder.png', 228, 228);
					}
					$data['categories'][] = [
						'category_id' => $cat['category_id'],
						'name'        => $cat['name'],
						'image'       => $image,
						'href'        => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $cat['category_id']),
					];
					$found[(int)$cat['category_id']] = true;
					break;
				}
			}
		}

		return $this->load->view('common/popular_crops', $data);
	}
}
