<?php
namespace Opencart\Admin\Controller\Extension\Ocnp\Shipping;
/**
 * Class Novaposhta
 *
 * @package Opencart\Admin\Controller\Extension\Ocnp\Shipping
 */
class Novaposhta extends \Opencart\System\Engine\Controller {
	private $m_data = [];
	private $OCNPNovaPoshtaSettings;

	private function getSettings() {
		if ($this->OCNPNovaPoshtaSettings === null) {
			try {
				// Fallback: if the library class does not exist, try to include file directly
				// (namespace must match OpenCart 4 conventions)
				if (!class_exists('\Opencart\Extension\Ocnp\System\Library\Novaposhta\OCNPNovaPoshtaSettings')) {
					$library_file = DIR_EXTENSION . 'ocnp/system/library/novaposhta/ocnpnovaposhtasettings.php';
					if (is_file($library_file)) {
						include_once($library_file);
					}
				}

				// instantiate the settings class using the OC4 namespace
				$this->OCNPNovaPoshtaSettings = new \Opencart\Extension\Ocnp\System\Library\Novaposhta\OCNPNovaPoshtaSettings($this->registry);
			} catch (\Exception $e) {
				// If library fails to load, create a minimal settings object
				$this->OCNPNovaPoshtaSettings = new class($this->registry) {
					private $registry;
					private $settings = [
						'extension_name' => 'shipping_novaposhta',
						'extension_path' => 'extension/ocnp/shipping/novaposhta',
						'name' => [1 => 'Delivery Nova Poshta'],
						'status' => '0',
						'api_url' => 'https://api.novaposhta.ua/v2.0/json/',
						'api_key' => '',
						'sort_order' => '0',
						'fixed_price' => 0,
						'free_delivery' => 0
					];
					
					public function __construct($registry) {
						$this->registry = $registry;
					}
					
					public function setSettings(array $settings): void {
						foreach(array_keys($this->settings) as $name) {
							if (key_exists($name, $settings)) {
								$this->settings[$name] = $settings[$name];
							}
						}
					}
					
					public function get(string $setting): mixed {
						return $this->settings[$setting] ?? '';
					}
					
					public function getSettings(): array {
						return $this->settings;
					}
					
					public function saveSettings(): void {
						// Minimal save - would need proper implementation
					}
				};
			}
		}
		return $this->OCNPNovaPoshtaSettings;
	}

	public function install(): void {
		$this->load->model('extension/ocnp/shipping/novaposhta');
		$this->model_extension_ocnp_shipping_novaposhta->install();
      $this->installEvents();
   }

	public function uninstall(): void {
		$this->load->model('extension/ocnp/shipping/novaposhta');
		$this->model_extension_ocnp_shipping_novaposhta->uninstall();
		$this->uninstallEvents();
	}

	public function index(): void {
		// NOTE: removed autoloader->register() calls — OC4 loads extensions by namespace+path.
		$this->load->language('extension/ocnp/shipping/novaposhta');
		$this->load->model('localisation/language');

		$this->document->setTitle($this->language->get('heading_title'));

		$data = [];
		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/ocnp/shipping/novaposhta', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/ocnp/shipping/novaposhta.save', 'user_token=' . $this->session->data['user_token']);
		$data['user_token'] = $this->session->data['user_token'];
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping');

		// Try to get settings, but don't fail if it errors
		try {
			$settings_obj = $this->getSettings();
			$settings_obj->setSettings($this->request->post);
			
			if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
				$settings_obj->saveSettings();
				$this->session->data['success'] = $this->language->get('text_success');
				$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping'));
			}

			$settings = $settings_obj->getSettings();
			$data = array_merge($data, $settings);
		} catch (\Exception $e) {
			// If settings fail to load, use defaults
			$data['extension_name'] = 'shipping_novaposhta';
			$data['extension_path'] = 'extension/ocnp/shipping/novaposhta';
			$data['name'] = [1 => 'Delivery Nova Poshta'];
			$data['status'] = '0';
			$data['api_url'] = 'https://api.novaposhta.ua/v2.0/json/';
			$data['api_key'] = '';
			$data['sort_order'] = '0';
			$data['fixed_price'] = 0;
			$data['free_delivery'] = 0;
		}

		$this->loadResources($data);
		$this->setSyncTableInfo($data);
		$data['languages'] = $this->model_localisation_language->getLanguages();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/ocnp/shipping/novaposhta', $data));
	}

	public function save(): void {
		$this->load->language('extension/ocnp/shipping/novaposhta');
		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/ocnp/shipping/novaposhta')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			// Handle both JSON (from AJAX) and regular POST (from form)
			if ($this->request->server['CONTENT_TYPE'] && strpos($this->request->server['CONTENT_TYPE'], 'application/json') !== false) {
      $request = $this->parseRequest();
			} else {
				$request = $this->request->post;
			}
			
			$this->getSettings()->setSettings($request);
			$this->getSettings()->saveSettings();
			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function saveSettings(): void {
		$this->load->language('extension/ocnp/shipping/novaposhta');
		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/ocnp/shipping/novaposhta')) {
			$json['error'] = $this->language->get('error_permission');
			$this->sendResponse($json);
			return;
		}

		$request = $this->parseRequest();
		
		if (isset($request['api_key']) && isset($request['api_url'])) {
			$this->getSettings()->setSettings([
				'api_key' => $request['api_key'],
				'api_url' => $request['api_url']
			]);
			$this->getSettings()->saveSettings();
			$json['success'] = true;
		} else {
			$json['success'] = false;
			$json['message'] = $this->language->get('ocnp_error_bad_request');
		}

		$this->sendResponse($json);
	}

	public function addWarehouses(): void {
		$this->load->language('extension/ocnp/shipping/novaposhta');
		$response = [
         'success' => false,
         'timestamp' => 0,
         'count' => 0,
         'message' => ''
		];

		$request = $this->parseRequest();
		if (!is_array($request) || count($request) == 0) {
         $response['message'] = $this->language->get('ocnp_error_bad_request');
         $this->sendResponse($response);
         return;
      }

      $model = $this->loadModel();
		if (!$model->addWarehouses($request)) {
         $response['message'] = $this->language->get('ocnp_error_add_data');
         $this->sendResponse($response);
         return;
      }
      $model->updateWarehousesSync();
      $WarehouseTable = $model->getWarehousesTableInfo();

      $response['success'] = true;
      $response['timestamp'] = $WarehouseTable['Timestamp'];
      $response['count'] = $WarehouseTable['RecordsCount'];
      $response['message'] = $this->language->get('ocnp_text_sync_success_warehouses');

      $this->sendResponse($response);
   }

	public function addAreas(): void {
		$this->load->language('extension/ocnp/shipping/novaposhta');

      $request = $this->parseRequest();
		$response = [
         'success' => false,
         'timestamp' => 0,
         'count' => 0,
         'message' => ''
		];

		if (!is_array($request) || count($request) == 0) {
         $response['message'] = $this->language->get('ocnp_error_bad_request');
			$this->sendResponse($response);
			return;
      }

      $model = $this->loadModel();
		if (!$model->addAreas($request)) {
         $response['message'] = $this->language->get('ocnp_error_add_data');
         $this->sendResponse($response);
         return;
      }
      $model->updateAreasSync();
      $AreasTable = $model->getAreasTableInfo();

      $response['success'] = true;
      $response['timestamp'] = $AreasTable['Timestamp'];
      $response['count'] = $AreasTable['RecordsCount'];
      $response['message'] = $this->language->get('ocnp_text_sync_success_areas');

      $this->sendResponse($response);
   }

	public function clearWarehouses(): void {
      $model = $this->loadModel();
      $model->clearWarehouses();
      $model->updateWarehousesSync();
      $info = $model->getWarehousesTableInfo();

		$response = [
         'success' => true,
         'timestamp' => $info['Timestamp'],
         'count' => $info["RecordsCount"],
         'message' => ''
		];

      $this->sendResponse($response);
   }

	public function clearAreas(): void {
      $model = $this->loadModel();
      $model->clearAreas();
      $model->updateAreasSync();
      $info = $model->getAreasTableInfo();

		$response = [
         'success' => true,
         'timestamp' => $info['Timestamp'],
         'count' => $info["RecordsCount"],
         'message' => ''
		];

      $this->sendResponse($response);
   }

	public function clearCities(): void {
      $model = $this->loadModel();
      $model->clearCities();
      $model->updateCitiesSync();
      $info = $model->getCitiesTableInfo();

		$response = [
         'success' => true,
         'timestamp' => $info['Timestamp'],
         'count' => $info["RecordsCount"],
         'message' => ''
		];

      $this->sendResponse($response);
   }

	public function addCities(): void {
		$this->load->language('extension/ocnp/shipping/novaposhta');

		$response = [
         'success' => false,
         'timestamp' => 0,
         'count' => 0,
         'message' => ""
		];

      $request = $this->parseRequest();

		if (!is_array($request) || count($request) == 0) {
         $response['message'] = $this->language->get('ocnp_error_bad_request');
         $this->sendResponse($response);
         return;
      }

      $model = $this->loadModel();
		if (!$model->addCities($request)) {
         $response['message'] = $this->language->get('ocnp_error_add_data');
         $this->sendResponse($response);
         return;
      }

      $model->updateCitiesSync();
      $CitiesTable = $model->getCitiesTableInfo();

      $response['success'] = true;
      $response['timestamp'] = $CitiesTable['Timestamp'];
      $response['count'] = $CitiesTable['RecordsCount'];
      $response['message'] = $this->language->get('ocnp_text_sync_success_cities');

      $this->sendResponse($response);
   }

	private function installEvents(): void {
      $this->load->model('setting/event');

      $this->model_setting_event->addEvent(
         'ocnp_novaposhta_add_scripts',
         'catalog/controller/common/header/before',
			'extension/ocnp/shipping/novaposhta.addScripts'
      );

      $this->model_setting_event->addEvent(
         'ocnp_novaposhta_checkout_form',
         'catalog/view/checkout/shipping_method/after',
			'extension/ocnp/shipping/novaposhta.addNovaPoshtaForm'
      );

      $this->model_setting_event->addEvent(
         'ocnp_novaposhta_checkout_script',
         'catalog/view/checkout/checkout/after',
			'extension/ocnp/shipping/novaposhta.addNovaPoshtaCheckoutScript'
      );

      $this->model_setting_event->addEvent(
         'ocnp_novaposhta_checkout_save_address',
         'catalog/controller/checkout/shipping_method/save/before',
			'extension/ocnp/shipping/novaposhta.addNovaPoshtaSaveAddress'
      );
   }

	private function uninstallEvents(): void {
      $this->load->model('setting/event');
      $this->model_setting_event->deleteEventByCode("ocnp_novaposhta_add_scripts");
      $this->model_setting_event->deleteEventByCode("ocnp_novaposhta_checkout_form");
      $this->model_setting_event->deleteEventByCode("ocnp_novaposhta_checkout_script");
      $this->model_setting_event->deleteEventByCode("ocnp_novaposhta_checkout_save_address");
   }

	private function loadResources(array &$data): void {
		$this->load->language('extension/ocnp/shipping/novaposhta');
		// Use HTTP_CATALOG since extensions are at the root level, not in adminpage/
		$data['js_url'] = HTTP_CATALOG . 'extension/ocnp/admin/view/javascript/ocnp/ocnp_novaposhta.js?47';

      $this->document->setTitle($this->language->get('heading_title'));
		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['ocnp_entry_sort_order'] = $this->language->get('entry_sort_order');
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
	}

	private function setSyncTableInfo(array &$data): void {
		try {
			$this->load->model('extension/ocnp/shipping/novaposhta');
			$cities = $this->model_extension_ocnp_shipping_novaposhta->getCitiesTableInfo();
			$data['ocnp_sync_table_cities_timestamp'] = $cities['Timestamp'] ?? '';
			$data['ocnp_sync_table_cities_count'] = $cities['RecordsCount'] ?? 0;

			$areas = $this->model_extension_ocnp_shipping_novaposhta->getAreasTableInfo();
			$data['ocnp_sync_table_areas_timestamp'] = $areas['Timestamp'] ?? '';
			$data['ocnp_sync_table_areas_count'] = $areas['RecordsCount'] ?? 0;

			$warehouses = $this->model_extension_ocnp_shipping_novaposhta->getWarehousesTableInfo();
			$data['ocnp_sync_table_warehouses_timestamp'] = $warehouses['Timestamp'] ?? '';
			$data['ocnp_sync_table_warehouses_count'] = $warehouses['RecordsCount'] ?? 0;
		} catch (\Exception $e) {
			// If sync info fails to load, set defaults
			$data['ocnp_sync_table_cities_timestamp'] = '';
			$data['ocnp_sync_table_cities_count'] = 0;
			$data['ocnp_sync_table_areas_timestamp'] = '';
			$data['ocnp_sync_table_areas_count'] = 0;
			$data['ocnp_sync_table_warehouses_timestamp'] = '';
			$data['ocnp_sync_table_warehouses_count'] = 0;
		}
	}

	private function parseRequest(): array {
		return json_decode(file_get_contents('php://input'), true) ?? [];
	}

	private function loadModel(): object {
		$this->load->model('extension/ocnp/shipping/novaposhta');
		return $this->model_extension_ocnp_shipping_novaposhta;
	}

	private function sendResponse(array $response): void {
      $this->response->addHeader('Content-Type: application/json');
      $this->response->setOutput(json_encode($response));
   }

	private function validate(): bool {
      $IsValid = true;

		if (!$this->user->hasPermission('modify', 'extension/ocnp/shipping/novaposhta')) {
         $this->m_data['warning'] = $this->language->get('error_permission');
         $IsValid = false;
		} else {
			$RequiredFields = [
            'api_url',
            'api_key'
			];

			foreach($RequiredFields as $field) {
				if (empty($this->getSettings()->get($field))) {
               $this->m_data['error_'.$field] = $this->language->get('error_'.$field);
               $IsValid = false;
            }
         }
      }

      return $IsValid;
   }
}
