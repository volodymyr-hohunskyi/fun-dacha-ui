<?php
namespace Opencart\Extension\Ocnp\System\Library\Novaposhta;
/**
 * Class OCNPNovaPoshtaSettings
 *
 * @package Opencart\Extension\Ocnp\System\Library\Novaposhta
 */
class OCNPNovaPoshtaSettings {
	protected $registry;
	private $m_settings = [
		'extension_name' => 'shipping_novaposhta',
		'extension_path' => 'extension/ocnp/shipping/novaposhta',
		'name' => ['Delivery Nova Poshta'],
		'status' => '0',
		'api_url' => 'https://api.novaposhta.ua/v2.0/json/',
		'api_key' => '',
		'sort_order' => '0',
		'fixed_price' => 0,
		'free_delivery' => 0
	];

	public function __construct(\Opencart\System\Engine\Registry $registry) {
		$this->registry = $registry;
		// Don't load settings in constructor - let controller call loadSettings() when ready
		// This prevents errors if language paths aren't registered yet
		$this->loadFromDB();
	}

	public function setSettings(array $settings): void {
		foreach(array_keys($this->m_settings) as $name) {
			if (key_exists($name, $settings)) {
				$this->m_settings[$name] = $settings[$name];
			}
		}
	}

	private function loadNames(): void {
		try {
			$loader = $this->registry->get('load');
			if (!$loader) {
				return;
			}
			
			$loader->model('localisation/language');
			$model = $this->registry->get('model_localisation_language');
			if (!$model) {
				return;
			}
			
			$languages = $model->getLanguages();
			if (!$languages) {
				return;
			}

			$names = [];
			foreach($languages as $language) {
				$loader->language('extension/ocnp/shipping/novaposhta', '', $language['code']);
				$language_obj = $this->registry->get('language');
				if ($language_obj) {
					$names[$language['language_id']] = $language_obj->get('heading_title');
				} else {
					$names[$language['language_id']] = 'Delivery Nova Poshta';
				}
			}

			if (!empty($names)) {
				$this->m_settings['name'] = $names;
			}
		} catch (\Exception $e) {
			// If language loading fails, use default name
			$this->m_settings['name'] = [1 => 'Delivery Nova Poshta'];
		}
	}

	private function loadFromDB(): void {
		try {
			$loader = $this->registry->get('load');
			if (!$loader) {
				return;
			}
			
			$loader->model('setting/setting');
			$model = $this->registry->get('model_setting_setting');
			if (!$model) {
				return;
			}
			
			$db = $model->getSetting($this->get('extension_name'));
			if (!$db) {
				$db = [];
			}
			
			foreach(array_keys($this->m_settings) as $name) {
				$nameInDB = $this->settingName($name);
				if (key_exists($nameInDB, $db)) {
					$this->m_settings[$name] = $db[$nameInDB];
				}
			}
		} catch (\Exception $e) {
			// If DB loading fails, use defaults
		}
	}

	public function saveSettings(): void {
		try {
			$loader = $this->registry->get('load');
			if (!$loader) {
				return;
			}
			
			$loader->model('setting/setting');
			$model = $this->registry->get('model_setting_setting');
			if (!$model) {
				return;
			}

			$values = [];
			foreach($this->m_settings as $name => $value) {
				$values[$this->settingName($name)] = $value;
			}
			$model->editSetting($this->get('extension_name'), $values);
		} catch (\Exception $e) {
			// Log error if needed
		}
	}

	public function get(string $setting): mixed {
		$value = '';

		if (key_exists($setting, $this->m_settings)) {
			$value = $this->m_settings[$setting];
		}

		return $value;
	}

	public function getSettings(): array {
		// Load names when getSettings is called (lazy loading)
		// This ensures language paths are registered by the time we need them
		if (!isset($this->m_settings['name']) || !is_array($this->m_settings['name']) || count($this->m_settings['name']) == 0) {
			$this->loadNames();
		}
		return $this->m_settings;
	}

	private function settingName(string $setting): string {
		$extensionName = $this->get('extension_name');
		return $extensionName . '_' . $setting;
	}
}
