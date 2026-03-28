<?php
namespace Opencart\Catalog\Controller\Assistant;
/**
 * Full-page garden assistant (same chat as widget, embedded layout).
 *
 * @package Opencart\Catalog\Controller\Assistant
 */
class Page extends \Opencart\System\Engine\Controller {
	public function index(): void {
		$this->load->language('assistant/page');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language')),
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('assistant/page', 'language=' . $this->config->get('config_language')),
		];

		$data['heading_title']   = $this->language->get('heading_title');
		$data['assistant_widget'] = $this->load->controller('assistant/widget', ['embed' => true]);

		$data['column_left']    = $this->load->controller('common/column_left');
		$data['column_right']   = $this->load->controller('common/column_right');
		$data['content_top']    = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['header']         = $this->load->controller('common/header');
		$data['footer']         = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('assistant/page', $data));
	}
}
