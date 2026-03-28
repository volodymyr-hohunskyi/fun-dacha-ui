<?php
namespace Opencart\Admin\Controller\Tool;
/**
 * Sync payment/delivery copy into storage overlay for catalog assistant.
 *
 * @package Opencart\Admin\Controller\Tool
 */
class Assistant extends \Opencart\System\Engine\Controller {
	public function index(): void {
		$this->load->language('tool/assistant');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['heading_title'] = $this->language->get('heading_title');

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token']),
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('tool/assistant', 'user_token=' . $this->session->data['user_token']),
		];

		$this->load->model('setting/setting');

		$settings = $this->model_setting_setting->getSetting('assistant', 0);

		if (isset($this->request->post['assistant_payment_information_id'])) {
			$data['assistant_payment_information_id'] = (int) $this->request->post['assistant_payment_information_id'];
		} else {
			$data['assistant_payment_information_id'] = (int) ($settings['assistant_payment_information_id'] ?? 0);
		}

		if (isset($this->request->post['assistant_delivery_information_id'])) {
			$data['assistant_delivery_information_id'] = (int) $this->request->post['assistant_delivery_information_id'];
		} else {
			$data['assistant_delivery_information_id'] = (int) ($settings['assistant_delivery_information_id'] ?? 0);
		}

		$data['error_warning'] = '';

		if (!empty($this->session->data['error_warning'])) {
			$data['error_warning'] = (string) $this->session->data['error_warning'];
			unset($this->session->data['error_warning']);
		}

		$data['success'] = '';

		if (!empty($this->session->data['success'])) {
			$data['success'] = (string) $this->session->data['success'];
			unset($this->session->data['success']);
		}

		$data['user_token'] = $this->session->data['user_token'];
		$data['action']     = $this->url->link('tool/assistant.save', 'user_token=' . $this->session->data['user_token']);
		$data['sync']       = $this->url->link('tool/assistant.sync', 'user_token=' . $this->session->data['user_token']);

		$overlayPath = DIR_STORAGE . 'assistant/assistant_info.json';
		$data['overlay_path']   = $overlayPath;
		$data['overlay_exists'] = is_file($overlayPath);
		$data['overlay_mtime']  = $data['overlay_exists'] ? date($this->language->get('datetime_format'), filemtime($overlayPath)) : '';

		$data['text_home']        = $this->language->get('text_home');
		$data['text_help']        = $this->language->get('text_help');
		$data['text_overlay']     = $this->language->get('text_overlay');
		$data['text_permission']  = $this->language->get('text_permission');
		$data['entry_payment']    = $this->language->get('entry_payment');
		$data['entry_delivery']   = $this->language->get('entry_delivery');
		$data['button_save']      = $this->language->get('button_save');
		$data['button_sync']      = $this->language->get('button_sync');

		$data['header']      = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer']      = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('tool/assistant', $data));
	}

	public function save(): void {
		$this->load->language('tool/assistant');

		if (!$this->user->hasPermission('modify', 'tool/assistant')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
			$this->response->redirect($this->url->link('tool/assistant', 'user_token=' . $this->session->data['user_token'], true));

			return;
		}

		$this->load->model('setting/setting');

		$this->model_setting_setting->editSetting('assistant', [
			'assistant_payment_information_id'  => (int) ($this->request->post['assistant_payment_information_id'] ?? 0),
			'assistant_delivery_information_id' => (int) ($this->request->post['assistant_delivery_information_id'] ?? 0),
		], 0);

		$this->session->data['success'] = $this->language->get('text_success');

		$this->response->redirect($this->url->link('tool/assistant', 'user_token=' . $this->session->data['user_token'], true));
	}

	public function sync(): void {
		$this->load->language('tool/assistant');

		if (!$this->user->hasPermission('modify', 'tool/assistant')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
			$this->response->redirect($this->url->link('tool/assistant', 'user_token=' . $this->session->data['user_token'], true));

			return;
		}

		$this->load->model('setting/setting');
		$this->load->model('catalog/information');
		$this->load->model('tool/assistant');
		$this->load->model('localisation/language');

		$settings = $this->model_setting_setting->getSetting('assistant', 0);

		$paymentId  = (int) ($settings['assistant_payment_information_id'] ?? 0);
		$deliveryId = (int) ($settings['assistant_delivery_information_id'] ?? 0);

		if ($paymentId < 1 || $deliveryId < 1) {
			$this->session->data['error_warning'] = $this->language->get('error_information_ids');
			$this->response->redirect($this->url->link('tool/assistant', 'user_token=' . $this->session->data['user_token'], true));

			return;
		}

		$languages = $this->model_localisation_language->getLanguages();

		$catalogBase = defined('HTTP_CATALOG') ? rtrim((string) HTTP_CATALOG, '/') . '/' : '';

		if ($catalogBase === '') {
			$cfg         = $this->model_setting_setting->getSetting('config', 0);
			$catalogBase = rtrim((string) ($cfg['config_url'] ?? ''), '/') . '/';
		}

		$info = [
			'payment'  => ['text' => [], 'url' => []],
			'delivery' => ['text' => [], 'url' => []],
		];

		foreach (['payment' => $paymentId, 'delivery' => $deliveryId] as $slot => $information_id) {
			$descriptions = $this->model_catalog_information->getDescriptions($information_id);

			foreach ($languages as $language) {
				$language_id = (int) $language['language_id'];
				$code        = (string) $language['code'];
				$desc        = $descriptions[$language_id] ?? null;

				if (!$desc) {
					continue;
				}

				$text = $this->model_tool_assistant->stripHtmlToText((string) ($desc['description'] ?? ''));

				if ($text === '') {
					$text = $this->model_tool_assistant->stripHtmlToText((string) ($desc['title'] ?? ''));
				}

				$info[$slot]['text'][$code] = $text;
				$info[$slot]['url'][$code]  = $catalogBase . 'index.php?route=information/information&language=' . rawurlencode($code) . '&information_id=' . $information_id;
			}
		}

		if (!$this->model_tool_assistant->writeOverlay($info)) {
			$this->session->data['error_warning'] = $this->language->get('error_write');
			$this->response->redirect($this->url->link('tool/assistant', 'user_token=' . $this->session->data['user_token'], true));

			return;
		}

		$this->session->data['success'] = $this->language->get('text_synced');

		$this->response->redirect($this->url->link('tool/assistant', 'user_token=' . $this->session->data['user_token'], true));
	}
}
