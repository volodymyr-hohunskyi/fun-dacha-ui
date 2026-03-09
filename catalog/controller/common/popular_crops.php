<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Shop Popular Crops – 4 featured categories with image, title, description, SHOP NOW.
 *
 * @package Opencart\Catalog\Controller\Common
 */
class PopularCrops extends \Opencart\System\Engine\Controller {
	private const KEYWORDS = [
		'томат'   => 'desc_tomatoes',
		'помідор' => 'desc_tomatoes',
		'огір'    => 'desc_cucumbers',
		'перець'  => 'desc_peppers',
		'квіт'    => 'desc_flowers',
		'flower'  => 'desc_flowers',
	];

	public function index(): string {
		$this->load->language('common/popular_crops');
		$this->load->model('catalog/category');
		$this->load->model('tool/image');

		$data['heading_title'] = $this->language->get('heading_title');
		$data['button_shop'] = $this->language->get('button_shop');
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
			$desc_key = null;
			foreach (self::KEYWORDS as $kw => $lang_key) {
				if (mb_strpos($name_lower, $kw) !== false) {
					$desc_key = $lang_key;
					break;
				}
			}
			if ($desc_key === null) {
				continue;
			}
			$description = trim((string)($cat['meta_description'] ?? $cat['description'] ?? ''));
			if ($description === '' || oc_strlen(strip_tags(html_entity_decode($description, ENT_QUOTES, 'UTF-8'))) > 200) {
				$description = $this->language->get($desc_key);
			} else {
				$description = trim(strip_tags(html_entity_decode($description, ENT_QUOTES, 'UTF-8')));
				if (oc_strlen($description) > 120) {
					$description = oc_substr($description, 0, 117) . '...';
				}
			}
			$image = '';
			if ($cat['image'] && is_file(DIR_IMAGE . html_entity_decode($cat['image'], ENT_QUOTES, 'UTF-8'))) {
				$image = $this->model_tool_image->resize(html_entity_decode($cat['image'], ENT_QUOTES, 'UTF-8'), 400, 300);
			} else {
				$image = $this->model_tool_image->resize('placeholder.png', 400, 300);
			}
			$data['categories'][] = [
				'category_id'  => $cat['category_id'],
				'name'         => $cat['name'],
				'description'  => $description,
				'image'        => $image,
				'href'         => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $cat['category_id']),
			];
			$found[(int)$cat['category_id']] = true;
		}

		return $this->load->view('common/popular_crops', $data);
	}
}
