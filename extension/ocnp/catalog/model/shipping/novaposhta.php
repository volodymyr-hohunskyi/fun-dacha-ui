<?php
namespace Opencart\Catalog\Model\Extension\Ocnp\Shipping;
/**
 * Class Novaposhta
 *
 * @package Opencart\Catalog\Model\Extension\Ocnp\Shipping
 */
class Novaposhta extends \Opencart\System\Engine\Model {
	const MODULE_NAME = 'shipping_novaposhta';
	const MODULE_PATH = 'extension/ocnp/shipping/novaposhta';
	const CITIES_TABLE = 'ocnp_novaposhta_cities';
	const AREAS_TABLE = 'ocnp_novaposhta_areas';
	const WAREHOUSES_TABLE = 'ocnp_novaposhta_warehouses';

	private $m_data = [];

	public function getQuote(array $address): array {
		// Check if module is enabled in config (OpenCart checks this before loading the model)
		// But we also check here as a fallback
		if (!$this->config->get('shipping_novaposhta_status')) {
			return [];
		}
		
		// Try to load the library class
		if (!class_exists('\Opencart\Extension\Ocnp\System\Library\Novaposhta\OCNPNovaPoshtaSettings')) {
			$library_file = DIR_EXTENSION . 'ocnp/system/library/novaposhta/ocnpnovaposhtasettings.php';
			if (is_file($library_file)) {
				include_once($library_file);
			}
		}
		
		try {
			$OCNPNovaPoshtaSettings = new \Opencart\Extension\Ocnp\System\Library\Novaposhta\OCNPNovaPoshtaSettings($this->registry);
		} catch (\Exception $e) {
			return [];
		}
		
		// Get status from settings
		$status = $OCNPNovaPoshtaSettings->get('status');
		if (!$status || $status == '0') {
			return [];
		}
		
		$this->load->language(self::MODULE_PATH);
		$title = $OCNPNovaPoshtaSettings->get('name');

		$method_title = $title[$this->config->get('config_language_id')] ?? 'Delivery Nova Poshta';
		
		$this->m_data = [
			'code' => 'novaposhta',
			'name' => $method_title,
			'sort_order' => (int)$OCNPNovaPoshtaSettings->get('sort_order'),
			'error' => false,
			'quote' => []
		];

		$cost = $this->getCost($OCNPNovaPoshtaSettings);

		$this->m_data['quote'] = [
			'warehouse' => [
				'code' => 'novaposhta.warehouse',
				'name' => $method_title,
				'cost' => $cost,
				'tax_class_id' => 0,
				'text' => $this->currency->format($cost, $this->session->data['currency'])
			]
		];

		return $this->m_data;
	}

	private function getCost(\Opencart\Extension\Ocnp\System\Library\Novaposhta\OCNPNovaPoshtaSettings $OCNPNovaPoshtaSettings): float {
		$cost = 0;
		$free_delivery = $OCNPNovaPoshtaSettings->get('free_delivery');

		if ($free_delivery == 0 || $free_delivery > $this->cart->getTotal()) {
			$cost = $OCNPNovaPoshtaSettings->get('fixed_price');
		}

		return (float)$cost;
	}

	public function getForm(): string {
		$this->load->language(self::MODULE_PATH);
		$data = [
			'areas' => $this->getAreas(),
			'text_ocnp_area_label' => $this->language->get('text_ocnp_area_label'),
			'text_ocnp_select_area_placeholder' => $this->language->get('text_ocnp_select_area_placeholder'),
			'text_ocnp_city_label' => $this->language->get('text_ocnp_city_label'),
			'text_ocnp_select_city_placeholder' => $this->language->get('text_ocnp_select_city_placeholder'),
			'text_ocnp_warehouse_label' => $this->language->get('text_ocnp_warehouse_label'),
			'text_ocnp_select_warehouse_placeholder' => $this->language->get('text_ocnp_select_warehouse_placeholder')
		];
		return $this->load->view(self::MODULE_PATH, $data);
	}

	public function getCitiesByAreaID(string $area): array {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . self::CITIES_TABLE . " WHERE Area = '" . $this->db->escape($area) . "'");
		$this->updateDescriptions($query->rows);
		return $query->rows;
	}

	public function getWarehousesByCityID(string $city): array {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . self::WAREHOUSES_TABLE . " WHERE CityRef = '" . $this->db->escape($city) . "'");
		$this->updateDescriptions($query->rows);

		return $query->rows;
	}

	private function getAreas(): array {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . self::AREAS_TABLE);
		$this->updateDescriptions($query->rows);

		return $query->rows;
	}

	private function updateDescriptions(array &$rows): void {
		if ($this->language->get('code') == 'ru') {
			foreach($rows as &$row) {
				$row['Description'] = $row['DescriptionRu'];
			}
		}
	}
}

