<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Class BlogCarousel
 *
 * Blog articles carousel for homepage.
 *
 * @package Opencart\Catalog\Controller\Common
 */
class BlogCarousel extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('common/blog_carousel');

		$this->load->model('cms/article');
		$this->load->model('tool/image');

		$data['articles'] = [];

		$filter_data = [
			'sort'  => 'date_added',
			'order' => 'DESC',
			'start' => 0,
			'limit' => 9
		];

		$results = $this->model_cms_article->getArticles($filter_data);

		if ($results) {
			foreach ($results as $result) {
				$image = '';
				if (!empty($result['image']) && is_file(DIR_IMAGE . html_entity_decode($result['image'], ENT_QUOTES, 'UTF-8'))) {
					$image = $this->model_tool_image->resize(html_entity_decode($result['image'], ENT_QUOTES, 'UTF-8'), 400, 260);
				}

				$data['articles'][] = [
					'article_id'  => $result['article_id'],
					'thumb'       => $image,
					'name'        => $result['name'],
					'description' => oc_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, 160) . '...',
					'href'        => $this->url->link('cms/blog.info', 'language=' . $this->config->get('config_language') . '&article_id=' . $result['article_id'])
				];
			}

			$data['blog_href'] = $this->url->link('cms/blog', 'language=' . $this->config->get('config_language'));

			return $this->load->view('common/blog_carousel', $data);
		}

		return '';
	}
}
