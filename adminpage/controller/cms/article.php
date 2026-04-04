<?php
namespace Opencart\Admin\Controller\Cms;
/**
 * Class Article
 *
 * @package Opencart\Admin\Controller\Cms
 */
class Article extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('cms/article');

		$this->document->setTitle($this->language->get('heading_title'));

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

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('cms/article', 'user_token=' . $this->session->data['user_token'] . $url)
		];

		$data['add'] = $this->url->link('cms/article.form', 'user_token=' . $this->session->data['user_token'] . $url);
		$data['delete'] = $this->url->link('cms/article.delete', 'user_token=' . $this->session->data['user_token']);
		$data['export'] = $this->url->link('cms/article.export', 'user_token=' . $this->session->data['user_token']);

		$data['list'] = $this->getList();

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('cms/article', $data));
	}

	/**
	 * List
	 *
	 * @return void
	 */
	public function list(): void {
		$this->load->language('cms/article');

		$this->response->setOutput($this->getList());
	}

	/**
	 * Get List
	 *
	 * @return string
	 */
	public function getList(): string {
		if (isset($this->request->get['sort'])) {
			$sort = (string)$this->request->get['sort'];
		} else {
			$sort = 'a.date_added';
		}

		if (isset($this->request->get['order'])) {
			$order = (string)$this->request->get['order'];
		} else {
			$order = 'ASC';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

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

		$data['action'] = $this->url->link('cms/article.list', 'user_token=' . $this->session->data['user_token'] . $url);

		// Article
		$data['articles'] = [];

		$filter_data = [
			'sort'  => $sort,
			'order' => $order,
			'start' => ($page - 1) * $this->config->get('config_pagination_admin'),
			'limit' => $this->config->get('config_pagination_admin')
		];

		$this->load->model('cms/article');

		$results = $this->model_cms_article->getArticles($filter_data);

		foreach ($results as $result) {
			$data['articles'][] = [
				'rating'     => (int)$result['rating'],
				'date_added' => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'edit'       => $this->url->link('cms/article.form', 'user_token=' . $this->session->data['user_token'] . '&article_id=' . $result['article_id'] . $url)
			] + $result;
		}

		$url = '';

		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}

		$data['sort_name'] = $this->url->link('cms/article.list', 'user_token=' . $this->session->data['user_token'] . '&sort=ad.name' . $url);
		$data['sort_author'] = $this->url->link('cms/article.list', 'user_token=' . $this->session->data['user_token'] . '&sort=a.author' . $url);
		$data['sort_rating'] = $this->url->link('cms/article.list', 'user_token=' . $this->session->data['user_token'] . '&sort=a.rating' . $url);
		$data['sort_date_added'] = $this->url->link('cms/article.list', 'user_token=' . $this->session->data['user_token'] . '&sort=a.date_added' . $url);

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$article_total = $this->model_cms_article->getTotalArticles();

		$data['pagination'] = $this->load->controller('common/pagination', [
			'total' => $article_total,
			'page'  => $page,
			'limit' => $this->config->get('config_pagination_admin'),
			'url'   => $this->url->link('cms/article.list', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}')
		]);

		$data['results'] = sprintf($this->language->get('text_pagination'), ($article_total) ? (($page - 1) * $this->config->get('config_pagination_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_pagination_admin')) > ($article_total - $this->config->get('config_pagination_admin'))) ? $article_total : ((($page - 1) * $this->config->get('config_pagination_admin')) + $this->config->get('config_pagination_admin')), $article_total, ceil($article_total / $this->config->get('config_pagination_admin')));

		$data['sort'] = $sort;
		$data['order'] = $order;

		return $this->load->view('cms/article_list', $data);
	}

	/**
	 * Form
	 *
	 * @return void
	 */
	public function form(): void {
		$this->load->language('cms/article');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->document->addScript('view/javascript/ckeditor/ckeditor.js');
		$this->document->addScript('view/javascript/ckeditor/adapters/jquery.js');

		$data['text_form'] = !isset($this->request->get['article_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');

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

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('cms/article', 'user_token=' . $this->session->data['user_token'] . $url)
		];

		$data['save'] = $this->url->link('cms/article.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('cms/article', 'user_token=' . $this->session->data['user_token'] . $url);

		if (isset($this->request->get['article_id'])) {
			$this->load->model('cms/article');

			$article_info = $this->model_cms_article->getArticle((int)$this->request->get['article_id']);
		}

		if (!empty($article_info)) {
			$data['article_id'] = $article_info['article_id'];
		} else {
			$data['article_id'] = 0;
		}

		// Language
		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		// Image
		$this->load->model('tool/image');

		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', $this->config->get('config_image_default_width'), $this->config->get('config_image_default_height'));

		$data['article_description'] = [];

		if (!empty($article_info)) {
			$results = $this->model_cms_article->getDescriptions($article_info['article_id']);

			foreach ($results as $key => $result) {
				$data['article_description'][$key] = $result;

				if ($result['image'] && is_file(DIR_IMAGE . html_entity_decode($result['image'], ENT_QUOTES, 'UTF-8'))) {
					$data['article_description'][$key]['thumb'] = $this->model_tool_image->resize($result['image'], $this->config->get('config_image_default_width'), $this->config->get('config_image_default_height'));
				} else {
					$data['article_description'][$key]['thumb'] = $data['placeholder'];
				}
			}
		}

		if (!empty($article_info)) {
			$data['author'] = $article_info['author'];
		} else {
			$data['author'] = $this->user->getFirstName() . ' ' . $this->user->getLastName();
		}

		// Topic
		$this->load->model('cms/topic');

		$data['topics'] = $this->model_cms_topic->getTopics();

		if (!empty($article_info)) {
			$data['topic_id'] = $article_info['topic_id'];
		} else {
			$data['topic_id'] = 0;
		}

		// Store
		$data['stores'] = [];

		$data['stores'][] = [
			'store_id' => 0,
			'name'     => $this->language->get('text_default')
		];

		$this->load->model('setting/store');

		$results = $this->model_setting_store->getStores();

		foreach ($results as $result) {
			$data['stores'][] = $result;
		}

		if (!empty($article_info)) {
			$data['article_store'] = $this->model_cms_article->getStores($article_info['article_id']);
		} else {
			$data['article_store'] = [0];
		}

		if (!empty($article_info)) {
			$data['status'] = $article_info['status'];
		} else {
			$data['status'] = true;
		}

		// SEO
		if (!empty($article_info)) {
			$this->load->model('design/seo_url');

			$data['article_seo_url'] = $this->model_design_seo_url->getSeoUrlsByKeyValue('article_id', $article_info['article_id']);
		} else {
			$data['article_seo_url'] = [];
		}

		// Layout
		$this->load->model('design/layout');

		$data['layouts'] = $this->model_design_layout->getLayouts();

		if (!empty($article_info)) {
			$data['article_layout'] = $this->model_cms_article->getLayouts($article_info['article_id']);
		} else {
			$data['article_layout'] = [];
		}

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('cms/article_form', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('cms/article');

		$json = [];

		if (!$this->user->hasPermission('modify', 'cms/article')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$required = [
			'article_id'          => 0,
			'article_description' => [],
			'author'              => '',
			'status'              => 0,
			'article_seo_url'     => []
		];

		$post_info = $this->request->post + $required;

		foreach ($post_info['article_description'] as $language_id => $value) {
			if (!oc_validate_length((string)$value['name'], 1, 255)) {
				$json['error']['name_' . (int)$language_id] = $this->language->get('error_name');
			}

			if (!oc_validate_length((string)$value['meta_title'], 1, 255)) {
				$json['error']['meta_title_' . (int)$language_id] = $this->language->get('error_meta_title');
			}
		}

		if (!oc_validate_length((string)$post_info['author'], 3, 64)) {
			$json['error']['author'] = $this->language->get('error_author');
		}

		// SEO
		if ($post_info['article_seo_url']) {
			$this->load->model('design/seo_url');

			foreach ($post_info['article_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (!oc_validate_length((string)$keyword, 1, 64)) {
						$json['error']['keyword_' . (int)$store_id . '_' . (int)$language_id] = $this->language->get('error_keyword');
					}

					if (!oc_validate_path((string)$keyword)) {
						$json['error']['keyword_' . (int)$store_id . '_' . (int)$language_id] = $this->language->get('error_keyword_character');
					}

					$seo_url_info = $this->model_design_seo_url->getSeoUrlByKeyword((string)$keyword, $store_id);

					if ($seo_url_info && (!$post_info['article_id'] || $seo_url_info['key'] != 'article_id' || $seo_url_info['value'] != (int)$post_info['article_id'])) {
						$json['error']['keyword_' . (int)$store_id . '_' . (int)$language_id] = $this->language->get('error_keyword_exists');
					}
				}
			}
		}

		if (isset($json['error']) && !isset($json['error']['warning'])) {
			$json['error']['warning'] = $this->language->get('error_warning');
		}

		if (!$json) {
			$this->load->model('cms/article');

			if (!$post_info['article_id']) {
				$json['article_id'] = $this->model_cms_article->addArticle($post_info);
			} else {
				$this->model_cms_article->editArticle($post_info['article_id'], $post_info);
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Delete
	 *
	 * @return void
	 */
	public function delete(): void {
		$this->load->language('cms/article');

		$json = [];

		if (isset($this->request->post['selected'])) {
			$selected = (array)$this->request->post['selected'];
		} else {
			$selected = [];
		}

		if (!$this->user->hasPermission('modify', 'cms/article')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('cms/article');

			foreach ($selected as $article_id) {
				$this->model_cms_article->deleteArticle($article_id);
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Rating
	 *
	 * @return void
	 */
	public function rating(): void {
		$this->load->language('cms/article');

		$json = [];

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		if (!$this->user->hasPermission('modify', 'cms/article')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$limit = 100;

			$filter_data = [
				'sort'  => 'date_added',
				'order' => 'ASC',
				'start' => ($page - 1) * $limit,
				'limit' => $limit
			];

			// Article
			$this->load->model('cms/article');

			$results = $this->model_cms_article->getArticles($filter_data);

			foreach ($results as $result) {
				$like = 0;
				$dislike = 0;

				$ratings = $this->model_cms_article->getRatings($result['article_id']);

				foreach ($ratings as $rating) {
					if ($rating['rating'] == 1) {
						$like = $rating['total'];
					}

					if ($rating['rating'] == 0) {
						$dislike = $rating['total'];
					}
				}

				$this->model_cms_article->editRating($result['article_id'], $like - $dislike);
			}

			$article_total = $this->model_cms_article->getTotalArticles();

			$start = ($page - 1) * $limit;
			$end = ($start > ($article_total - $limit)) ? $article_total : ($start + $limit);

			if ($end < $article_total) {
				$json['text'] = sprintf($this->language->get('text_next'), $start ?: 1, $end, $article_total);

				$json['next'] = $this->url->link('cms/article.rating', 'user_token=' . $this->session->data['user_token'] . '&page=' . ($page + 1), true);
			} else {
				$json['success'] = $this->language->get('text_success');

				$json['next'] = '';
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Export all topics and articles as .xlsx (same layout as shared blog import: Topics + Articles sheets).
	 *
	 * @return void
	 */
	public function export(): void {
		if (!$this->user->hasPermission('access', 'cms/article')) {
			$this->response->addHeader('HTTP/1.1 403 Forbidden');
			$this->response->setOutput('Permission denied');

			return;
		}

		require_once DIR_EXTENSION . 'export_import/system/library/export_import/vendor/autoload.php';

		$this->load->model('cms/topic');
		$this->load->model('cms/article');
		$this->load->model('design/seo_url');
		$this->load->model('localisation/language');

		$languages = $this->model_localisation_language->getLanguages();
		$config_language_id = (int)$this->config->get('config_language_id');
		$default_lang_code = '';

		foreach ($languages as $lang) {
			if ((int)$lang['language_id'] === $config_language_id) {
				$default_lang_code = $lang['code'];
				break;
			}
		}

		if ($default_lang_code === '') {
			$default_lang_code = 'en-gb';
		}

		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$topics_ws = $spreadsheet->getActiveSheet();
		$topics_ws->setTitle('Topics');

		$topics_ws->fromArray([
			['topic_id', 'name(uk-ua)', 'sort_order', 'status', 'store_id', 'language_code'],
		], null, 'A1');

		$topic_rows = $this->model_cms_topic->getTopics();
		$r = 2;

		foreach ($topic_rows as $topic) {
			$topic_id = (int)$topic['topic_id'];
			$descs = $this->model_cms_topic->getDescriptions($topic_id);
			$name = '';

			if (isset($descs[$config_language_id]['name'])) {
				$name = $descs[$config_language_id]['name'];
			} elseif ($descs) {
				$first = reset($descs);
				$name = $first['name'] ?? '';
			}

			$stores = $this->model_cms_topic->getStores($topic_id);
			$store_id = $stores ? (int)min($stores) : 0;

			$topics_ws->setCellValue('A' . $r, $topic_id);
			$topics_ws->setCellValue('B' . $r, $name);
			$topics_ws->setCellValue('C' . $r, (int)$topic['sort_order']);
			$topics_ws->setCellValue('D' . $r, (int)$topic['status']);
			$topics_ws->setCellValue('E' . $r, $store_id);
			$topics_ws->setCellValue('F' . $r, $default_lang_code);
			$r++;
		}

		$articles_ws = $spreadsheet->createSheet();
		$articles_ws->setTitle('Articles');

		$articles_ws->fromArray([
			[
				'article_id',
				'topic_name',
				'name',
				'description',
				'image',
				'author',
				'status',
				'store_id',
				'language_id',
				'meta_title',
				'meta_description',
				'meta_keyword',
				'tag',
				'date_added',
				'seo_keyword',
			],
		], null, 'A1');

		$sql = "SELECT `a`.`article_id`, `a`.`topic_id`, `a`.`author`, `a`.`status`, `a`.`date_added`, `ad`.`language_id`, `ad`.`image`, `ad`.`name`, `ad`.`description`, `ad`.`tag`, `ad`.`meta_title`, `ad`.`meta_description`, `ad`.`meta_keyword` FROM `" . DB_PREFIX . "article` `a` INNER JOIN `" . DB_PREFIX . "article_description` `ad` ON (`a`.`article_id` = `ad`.`article_id`) ORDER BY `a`.`article_id` ASC, `ad`.`language_id` ASC";

		$query = $this->db->query($sql);

		$topic_name_cache = [];
		$ar = 2;

		foreach ($query->rows as $row) {
			$article_id = (int)$row['article_id'];
			$topic_id = (int)$row['topic_id'];
			$lang_id = (int)$row['language_id'];

			if (!isset($topic_name_cache[$topic_id])) {
				$topic_name_cache[$topic_id] = $this->model_cms_topic->getDescriptions($topic_id);
			}

			$td = $topic_name_cache[$topic_id];
			$topic_name = '';

			if (isset($td[$lang_id]['name'])) {
				$topic_name = $td[$lang_id]['name'];
			} elseif (isset($td[$config_language_id]['name'])) {
				$topic_name = $td[$config_language_id]['name'];
			} elseif ($td) {
				$first = reset($td);
				$topic_name = $first['name'] ?? '';
			}

			$stores = $this->model_cms_article->getStores($article_id);
			$store_id = $stores ? (int)min($stores) : 0;

			$seo_map = $this->model_design_seo_url->getSeoUrlsByKeyValue('article_id', (string)$article_id);
			$seo_keyword = '';

			if (isset($seo_map[$store_id][$lang_id])) {
				$seo_keyword = $seo_map[$store_id][$lang_id];
			} elseif (isset($seo_map[0][$lang_id])) {
				$seo_keyword = $seo_map[0][$lang_id];
			} else {
				foreach ($seo_map as $_stores) {
					if (isset($_stores[$lang_id])) {
						$seo_keyword = $_stores[$lang_id];
						break;
					}
				}
			}

			$date_added = $row['date_added'];

			if ($date_added && strtotime((string)$date_added)) {
				$date_added = date('Y-m-d H:i:s', strtotime((string)$date_added));
			}

			$articles_ws->setCellValue('A' . $ar, $article_id);
			$articles_ws->setCellValue('B' . $ar, $topic_name);
			$articles_ws->setCellValue('C' . $ar, $row['name']);
			$articles_ws->setCellValue('D' . $ar, $row['description']);
			$articles_ws->setCellValue('E' . $ar, $row['image']);
			$articles_ws->setCellValue('F' . $ar, $row['author']);
			$articles_ws->setCellValue('G' . $ar, (int)$row['status']);
			$articles_ws->setCellValue('H' . $ar, $store_id);
			$articles_ws->setCellValue('I' . $ar, $lang_id);
			$articles_ws->setCellValue('J' . $ar, $row['meta_title']);
			$articles_ws->setCellValue('K' . $ar, $row['meta_description']);
			$articles_ws->setCellValue('L' . $ar, $row['meta_keyword']);
			$articles_ws->setCellValue('M' . $ar, $row['tag']);
			$articles_ws->setCellValue('N' . $ar, $date_added);
			$articles_ws->setCellValue('O' . $ar, $seo_keyword);
			$ar++;
		}

		$spreadsheet->setActiveSheetIndex(0);

		$filename = 'blog_export_' . date('Y-m-d_His') . '.xlsx';

		$this->response->addHeader('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		$this->response->addHeader('Content-Disposition: attachment; filename="' . $filename . '"');
		$this->response->addHeader('Cache-Control: max-age=0');

		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
		ob_start();
		$writer->save('php://output');
		$this->response->setOutput(ob_get_clean());
	}
}
