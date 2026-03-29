<?php
namespace Opencart\Catalog\Controller\Common;

use Opencart\Catalog\Controller\Product\Thumb;

/**
 * Homepage: latest customer reviews in a horizontal carousel (5 cards per slide).
 */
class HomeReviewsCarousel extends \Opencart\System\Engine\Controller {
	/**
	 * @return string
	 */
	public function index(): string {
		if (!$this->config->get('config_review_status')) {
			return '';
		}

		$this->load->language('common/home_reviews_carousel');
		$this->load->language('default');
		$this->load->model('catalog/review');
		$this->load->model('tool/image');

		[$thumb_w, $thumb_h] = Thumb::listThumbDimensions($this->config);

		$results = $this->model_catalog_review->getLatestReviews(25);

		if (!$results) {
			return '';
		}

		$data['reviews'] = [];

		foreach ($results as $result) {
			if (!empty($result['image']) && is_file(DIR_IMAGE . html_entity_decode($result['image'], ENT_QUOTES, 'UTF-8'))) {
				$thumb = $this->model_tool_image->resize(html_entity_decode($result['image'], ENT_QUOTES, 'UTF-8'), $thumb_w, $thumb_h);
			} else {
				$thumb = $this->model_tool_image->resize('placeholder.png', $thumb_w, $thumb_h);
			}

			$text_plain = trim(strip_tags(html_entity_decode($result['text'], ENT_QUOTES, 'UTF-8')));
			$text_short = oc_substr($text_plain, 0, 180);
			if (oc_strlen($text_plain) > 180) {
				$text_short .= '…';
			}

			$data['reviews'][] = [
				'review_id'   => (int)$result['review_id'],
				'author'      => $result['author'],
				'rating'      => (int)$result['rating'],
				'text'        => $text_short,
				'date_added'  => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'product_id'  => (int)$result['product_id'],
				'product_name'=> $result['product_name'],
				'thumb'       => $thumb,
				'href'        => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . (int)$result['product_id']),
			];
		}

		$data['heading_title'] = $this->language->get('heading_title');

		return $this->load->view('common/home_reviews_carousel', $data);
	}
}
