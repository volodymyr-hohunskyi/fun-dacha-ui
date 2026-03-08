<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Class OurCategory
 *
 * Displays "Our Category" widget - grid of top-level categories (Tomato, Avocado, etc.)
 * Shown on homepage just below the banner.
 *
 * @package Opencart\Catalog\Controller\Common
 */
class OurCategory extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('common/our_category');

		$this->load->model('catalog/category');
		$this->load->model('tool/image');

		$data['heading_title'] = $this->language->get('heading_title');
		$data['categories'] = [];

		$categories = $this->model_catalog_category->getCategories(0);

		foreach ($categories as $category) {
			$image = '';

			if ($category['image'] && is_file(DIR_IMAGE . html_entity_decode($category['image'], ENT_QUOTES, 'UTF-8'))) {
				$image = $this->model_tool_image->resize(html_entity_decode($category['image'], ENT_QUOTES, 'UTF-8'), 120, 120);
			} else {
				$image = $this->model_tool_image->resize('placeholder.png', 120, 120);
			}

			$data['categories'][] = [
				'category_id' => $category['category_id'],
				'name'        => $category['name'],
				'image'       => $image,
				'href'        => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $category['category_id']),
			];
		}

		return $this->load->view('common/our_category', $data);
	}
}
