<?php
namespace Opencart\Catalog\Controller\Startup;
/**
 * Class Language
 *
 * @package Opencart\Catalog\Controller\Startup
 */
class Language extends \Opencart\System\Engine\Controller {
	/**
	 * @var array<string, array<string, mixed>>
	 */
	private static array $languages = [];

	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		// Language
		$this->load->model('localisation/language');

		self::$languages = $this->model_localisation_language->getLanguages();

		$language_info = [];

		// Set default language
		if (isset(self::$languages[$this->config->get('config_language_catalog')])) {
			$language_info = self::$languages[$this->config->get('config_language_catalog')];
		}

		// If GET has language var
		if (isset($this->request->get['language']) && isset(self::$languages[$this->request->get['language']])) {
			$language_info = self::$languages[$this->request->get['language']];
		}

		if ($language_info) {
			// If extension switch add language directory
			if ($language_info['extension']) {
				$this->language->addPath('extension/' . $language_info['extension'], DIR_EXTENSION . $language_info['extension'] . '/catalog/language/');
			}

			// Set the config language_id key
			$this->config->set('config_language_id', $language_info['language_id']);
			$this->config->set('config_language', $language_info['code']);

			$this->load->language('default');

			$this->applyLocaleOverrides($language_info['code']);
		}
	}

	/**
	 * After
	 *
	 * Override the language default values
	 *
	 * @param string       $route
	 * @param string       $prefix
	 * @param string       $code
	 * @param array<mixed> $output
	 *
	 * @return void
	 */
	public function after(&$route, &$prefix, &$code, &$output): void {
		if (!$code) {
			$code = $this->config->get('config_language');
		}

		// Use $this->language->load so it's not triggering infinite loops
		$this->language->load($route, $prefix, $code);

		if (isset(self::$languages[$code])) {
			$language_info = self::$languages[$code];

			$path = '';

			if ($language_info['extension']) {
				$extension = 'extension/' . $language_info['extension'];

				if (oc_substr($route, 0, strlen($extension)) != $extension) {
					$path = $extension . '/';
				}
			}

			// Use $this->language->load so it's not triggering infinite loops
			$this->language->load($path . $route, $prefix, $code);

			$this->applyLocaleOverrides($code);
		}
	}

	/**
	 * Apply locale specific string overrides
	 *
	 * Ensures we can localize high-visibility strings even when we rely on the
	 * base language pack for most content (e.g. UA store reusing en-gb files).
	 *
	 * @param string $code
	 *
	 * @return void
	 */
	private function applyLocaleOverrides(string $code): void {
		if (!$code) {
			return;
		}

		static $override_map = [
			'uk-ua' => [
				'button_cart' => 'Додати до кошика',
			],
		];

		$normalized_code = strtolower($code);

		if (!isset($override_map[$normalized_code])) {
			return;
		}

		foreach ($override_map[$normalized_code] as $key => $value) {
			$this->language->set($key, $value);
		}
	}
}
