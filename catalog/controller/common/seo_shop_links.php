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

		// Admin sort often puts «Томати» first; for blog internal links, surface «Квіти» before other categories.
		$flower = [];
		$rest   = [];
		foreach ($links as $link) {
			if (mb_stripos($link['name'], 'квіт') !== false) {
				$flower[] = $link;
			} else {
				$rest[] = $link;
			}
		}
		$sortByName = static function (array $a, array $b): int {
			return strcmp(mb_strtolower($a['name'], 'UTF-8'), mb_strtolower($b['name'], 'UTF-8'));
		};
		usort($flower, $sortByName);
		usort($rest, $sortByName);
		$links = array_merge($flower, $rest);

		$data['heading'] = $this->language->get('heading_title');
		$data['text_intro'] = $this->language->get('text_intro');
		$data['links'] = $links;

		return $this->load->view('common/seo_shop_links', $data);
	}
}
