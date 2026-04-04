<?php
namespace Opencart\Catalog\Controller\Common;

/**
 * Blog article page: carousel of category links (parent + one level of children), tag-style.
 */
class BlogCategoryCarousel extends \Opencart\System\Engine\Controller {
	private const PER_SLIDE = 6;

	public function index(): string {
		$this->load->language('common/blog_category_carousel');
		$this->load->model('catalog/category');

		$lang = $this->config->get('config_language');
		$items = [];

		foreach ($this->model_catalog_category->getCategories(0) as $cat) {
			$cid = (int)$cat['category_id'];
			$path = $this->model_catalog_category->getCategoryPathString($cid);

			if ($path === '') {
				continue;
			}

			$items[] = [
				'name' => $cat['name'],
				'href' => $this->url->link('product/category', 'language=' . $lang . '&path=' . $path),
			];

			foreach ($this->model_catalog_category->getCategories($cid) as $child) {
				$child_id = (int)$child['category_id'];
				$path_ch = $this->model_catalog_category->getCategoryPathString($child_id);

				if ($path_ch === '') {
					continue;
				}

				$items[] = [
					'name' => $child['name'],
					'href' => $this->url->link('product/category', 'language=' . $lang . '&path=' . $path_ch),
				];
			}
		}

		if ($items === []) {
			return '';
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['categories'] = $items;
		$data['per_slide'] = self::PER_SLIDE;

		return $this->load->view('common/blog_category_carousel', $data);
	}
}
