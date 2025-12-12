<?php
namespace Opencart\Catalog\Controller\Extension\Opencart\Module;
/**
 * Class Manufacturer
 *
 * @package Opencart\Catalog\Controller\Extension\Opencart\Module
 */
class Manufacturer extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('extension/opencart/module/manufacturer');

		$data['heading_title'] = $this->language->get('heading_title');

		// Manufacturer
		$this->load->model('catalog/manufacturer');
		$this->load->model('tool/image');

		$data['manufacturers'] = [];

		$manufacturers = $this->model_catalog_manufacturer->getManufacturers();

		foreach ($manufacturers as $manufacturer) {
			if ($manufacturer['image']) {
				$image = $this->model_tool_image->resize(html_entity_decode($manufacturer['image'], ENT_QUOTES, 'UTF-8'), 150, 150);
			} else {
				$image = $this->model_tool_image->resize('placeholder.png', 150, 150);
			}

			$data['manufacturers'][] = [
				'manufacturer_id' => $manufacturer['manufacturer_id'],
				'name'            => $manufacturer['name'],
				'image'           => $image,
				'href'            => $this->url->link('product/manufacturer.info', 'language=' . $this->config->get('config_language') . '&manufacturer_id=' . $manufacturer['manufacturer_id'])
			];
		}

		return $this->load->view('extension/opencart/module/manufacturer', $data);
	}
}

