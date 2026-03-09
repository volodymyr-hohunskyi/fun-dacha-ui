<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Why Our Seeds – About Us preview with trust blocks.
 *
 * @package Opencart\Catalog\Controller\Common
 */
class WhyOurSeeds extends \Opencart\System\Engine\Controller {
	public function index(): string {
		$this->load->language('common/why_our_seeds');
		$this->load->model('tool/image');

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_intro'] = $this->language->get('text_intro');
		$data['trust_blocks'] = [
			$this->language->get('trust_germination'),
			$this->language->get('trust_varieties'),
			$this->language->get('trust_climate'),
			$this->language->get('trust_delivery'),
			$this->language->get('trust_advice'),
		];
		$data['library_href'] = $this->url->link('cms/blog', 'language=' . $this->config->get('config_language'));
		$data['text_library'] = $this->language->get('text_library');

		$image_path = 'catalog/seeds.jpg';
		if (is_file(DIR_IMAGE . $image_path)) {
			$data['image'] = $this->model_tool_image->resize($image_path, 400, 300);
		} else {
			$data['image'] = $this->model_tool_image->resize('placeholder.png', 400, 300);
		}

		return $this->load->view('common/why_our_seeds', $data);
	}
}
