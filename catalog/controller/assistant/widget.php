<?php
namespace Opencart\Catalog\Controller\Assistant;
/**
 * Injects floating assistant widget + assets.
 *
 * @package Opencart\Catalog\Controller\Assistant
 */
class Widget extends \Opencart\System\Engine\Controller {
	/**
	 * @param array<string, mixed> $options embed: full-page layout; pageUrl: link shown in panel toolbar
	 *
	 * @return string
	 */
	public function index(array $options = []): string {
		$this->load->language('assistant/widget');

		$lang = $this->config->get('config_language');

		$embed = !empty($options['embed']);

		$data['assistant_embed']     = $embed;
		$data['assistant_page_url']  = $this->url->link('assistant/page', 'language=' . $lang);

		$data['ajax_url']        = $this->url->link('assistant/chat', 'language=' . $lang, true);
		$data['reset_url']       = $this->url->link('assistant/chat.reset', 'language=' . $lang, true);
		$data['image_base']      = rtrim($this->config->get('config_url'), '/') . '/image/';
		$data['product_url_tpl'] = $this->url->link('product/product', 'language=' . $lang . '&product_id=', true);
		$data['callback_url']    = $this->url->link('information/contact', 'language=' . $lang);
		$data['lang_code']       = $lang;

		$data['text_assistant']        = $this->language->get('text_assistant');
		$data['text_open']             = $this->language->get('text_open');
		$data['text_close']            = $this->language->get('text_close');
		$data['text_welcome']          = $this->language->get('text_welcome');
		$data['text_welcome_sub']      = $this->language->get('text_welcome_sub');
		$data['text_search_hint']      = $this->language->get('text_search_hint');
		$data['text_intent_consult']     = $this->language->get('text_intent_consult');
		$data['text_intent_payment']   = $this->language->get('text_intent_payment');
		$data['text_intent_delivery']  = $this->language->get('text_intent_delivery');
		$data['text_placeholder']      = $this->language->get('text_placeholder');
		$data['text_send']             = $this->language->get('text_send');
		$data['text_suggestions']      = $this->language->get('text_suggestions');
		$data['text_callback']         = $this->language->get('text_callback');
		$data['text_loading']          = $this->language->get('text_loading');
		$data['text_error']            = $this->language->get('text_error');
		$data['text_start_over']       = $this->language->get('text_start_over');
		$data['text_reset_done']       = $this->language->get('text_reset_done');
		$data['text_open_full_page']   = $this->language->get('text_open_full_page');

		$cfg = [
			'ajaxUrl'     => $data['ajax_url'],
			'resetUrl'    => $data['reset_url'],
			'imageBase'   => $data['image_base'],
			'productUrl'  => $data['product_url_tpl'],
			'callbackUrl' => $data['callback_url'],
			'lang'        => $data['lang_code'],
			'pageContext' => $this->getAssistantPageContext(),
			'i18n'        => [
				'loading'   => $data['text_loading'],
				'error'     => $data['text_error'],
				'resetDone' => $data['text_reset_done'],
			],
		];

		if ($embed) {
			$cfg['embedMode'] = 'full';
		}

		$data['assistant_config_json'] = json_encode($cfg, JSON_UNESCAPED_UNICODE);
		$data['assistant_chat_js_ver']   = '12';

		$this->document->addStyle('catalog/view/stylesheet/assistant.css');
		// chat.js is loaded from widget.twig (after inline config) so it always runs even if footer getScripts order differs.

		return $this->load->view('assistant/widget', $data);
	}

	/**
	 * Current storefront route + category / product / filters for assistant context (JSON to chat API).
	 *
	 * @return array<string, mixed>
	 */
	private function getAssistantPageContext(): array {
		$route = (string) ($this->request->get['route'] ?? '');
		$ctx   = ['route' => $route];

		if ($route === 'product/product' && isset($this->request->get['product_id'])) {
			$ctx['product_id'] = (int) $this->request->get['product_id'];
		}

		if ($route === 'product/category' && isset($this->request->get['path'])) {
			$path  = (string) $this->request->get['path'];
			$parts = explode('_', $path);
			$cid   = (int) array_pop($parts);

			if ($cid > 0) {
				$ctx['category_id'] = $cid;
				$ctx['path']        = $path;
			}
		}

		if (isset($this->request->get['filter_attr']) && is_string($this->request->get['filter_attr'])) {
			$ctx['filter_attr'] = $this->request->get['filter_attr'];
		}

		if ($route === 'product/search' && isset($this->request->get['category_id'])) {
			$ctx['category_id'] = (int) $this->request->get['category_id'];
		}

		return $ctx;
	}
}
