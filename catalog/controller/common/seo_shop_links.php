<?php
namespace Opencart\Catalog\Controller\Common;

/**
 * SEO cluster: top-level category links for internal linking from blog articles.
 */
class SeoShopLinks extends \Opencart\System\Engine\Controller {
	/**
	 * @return string
	 */
	public function index(): string {
		$this->load->language('common/seo_shop_links');
		$this->load->model('catalog/category');

		$lang = $this->config->get('config_language');
		$categories = $this->model_catalog_category->getCategories(0);

		$links = [];

		foreach ($categories as $category) {
			$links[] = [
				'name' => $category['name'],
				'href' => $this->url->link('product/category', 'language=' . $lang . '&path=' . (int)$category['category_id']),
			];
		}

		$data['heading'] = $this->language->get('heading_title');
		$data['text_intro'] = $this->language->get('text_intro');
		$data['links'] = $links;

		return $this->load->view('common/seo_shop_links', $data);
	}
}
