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

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_intro'] = $this->language->get('text_intro');

		$data['features'] = [
			['icon' => 'fa-seedling',      'title' => $this->language->get('trust_germination')],
			['icon' => 'fa-layer-group',    'title' => $this->language->get('trust_varieties')],
			['icon' => 'fa-cloud-sun',      'title' => $this->language->get('trust_climate')],
			['icon' => 'fa-truck-fast',     'title' => $this->language->get('trust_delivery')],
			['icon' => 'fa-headset',        'title' => $this->language->get('trust_advice')],
			['icon' => 'fa-shield-halved',  'title' => 'Гарантія якості'],
		];

		$data['library_href'] = $this->url->link('cms/blog', 'language=' . $this->config->get('config_language'));
		$data['text_library'] = $this->language->get('text_library');

		return $this->load->view('common/why_our_seeds', $data);
	}
}
