<?php
namespace Opencart\Catalog\Controller\Common;

class Newsletter extends \Opencart\System\Engine\Controller {
	public function index(): string {
		$data['action'] = $this->url->link('common/newsletter.subscribe', 'language=' . $this->config->get('config_language'));
		return $this->load->view('common/newsletter', $data);
	}

	public function subscribe(): void {
		$json = [];

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			$email = trim($this->request->post['email'] ?? '');

			if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$json['error'] = 'Введіть коректну email адресу';
			} else {
				$json['success'] = 'Дякуємо за підписку!';
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
