<?php
namespace Opencart\Catalog\Controller\Extension\Ocnp\Shipping;
/**
 * Class Novaposhta
 *
 * @package Opencart\Catalog\Controller\Extension\Ocnp\Shipping
 */
class Novaposhta extends \Opencart\System\Engine\Controller {
	const EXTENSION_PATH = 'extension/ocnp/shipping/novaposhta';

	public function getCities(): void {
		$response_data = ['success' => false];

		if (isset($this->request->post['area_id'])) {
			$area_id = $this->request->post['area_id'];

			$this->load->model(self::EXTENSION_PATH);
			$cities = $this->model_extension_ocnp_shipping_novaposhta->getCitiesByAreaID($area_id);
			
			$response_data['success'] = true;
			$response_data['cities'] = $cities;
		}

		$this->sendResponse($response_data);
	}

	public function getWarehouses(): void {
		$response_data = ['success' => false];

		if (isset($this->request->post['city_id'])) {
			$city_id = $this->request->post['city_id'];

			$this->load->model(self::EXTENSION_PATH);
			$warehouses = $this->model_extension_ocnp_shipping_novaposhta->getWarehousesByCityID($city_id);
			
			$response_data['success'] = true;
			$response_data['warehouses'] = $warehouses;
		}

		$this->sendResponse($response_data);
	}

	public function getForm(): void {
		$this->load->model(self::EXTENSION_PATH);
		$form = $this->model_extension_ocnp_shipping_novaposhta->getForm();
		$this->response->setOutput($form);
	}

	public function addScripts(&$route, &$data): void {
		$this->document->addScript('extension/ocnp/catalog/view/javascript/ocnp_novaposhta/checkout.js');
	}

	public function addNovaPoshtaSaveAddress(&$route, &$data): void {
		if (isset($this->request->post['shipping_method']) && $this->request->post['shipping_method'] == 'novaposhta.warehouse') {
			$this->session->data['shipping_address']['zone'] = $this->OCNP_getValue('ocnp_novaposhta_area', 'zone');
			$this->session->data['shipping_address']['city'] = $this->OCNP_getValue('ocnp_novaposhta_city', 'city');
			$this->session->data['shipping_address']['address_1'] = $this->OCNP_getValue('ocnp_novaposhta_warehouse', 'address_1');
		}
	}

	public function addNovaPoshtaForm(&$route, &$data, &$output): void {
		$isAlreadyAdded = strpos($output, 'OCNP_ShowCity()');
		if ($isAlreadyAdded !== false) {
			return;
		}

		// Look for the shipping method code in the output
		$label = strpos($output, 'novaposhta.warehouse');
		if ($label === false) {
			return;
		}

		// For modal-based checkout, inject form after the label or after the form-check div
		$formPosition = strpos($output, '</label>', $label);
		if ($formPosition === false) {
			// Try to find the closing div of form-check instead
			$formCheckEnd = strpos($output, '</div>', $label);
			if ($formCheckEnd !== false) {
				$formPosition = $formCheckEnd + 6; // After </div>
			} else {
				return;
			}
		} else {
			$formPosition += 8; // After </label>
		}

		$this->load->model(self::EXTENSION_PATH);
		$form = $this->model_extension_ocnp_shipping_novaposhta->getForm();

		$output = $this->stringInsert($output, $form, $formPosition);
	}

	public function addNovaPoshtaCheckoutScript(&$route, &$data, &$output): void {
		// Get current language code
		$language = $this->config->get('config_language');
		
		// Inject JavaScript to handle NovaPoshta form in the checkout modal
		$script = '
<script type="text/javascript">
$(document).ready(function() {
	var language = "' . $language . '";
	
	// Hook into shipping method modal creation
	$(document).on("shown.bs.modal", "#modal-shipping", function() {
		// Check if NovaPoshta method exists and inject form if needed
		var novaposhtaRadio = $("input[name=\'shipping_method\'][value^=\'novaposhta\']");
		if (novaposhtaRadio.length > 0) {
			// Check if form already injected
			if ($("#ocnp_novaposhta_area").length === 0) {
				// Load and inject the form
				$.ajax({
					url: "index.php?route=extension/ocnp/shipping/novaposhta.getForm&language=" + language,
					dataType: "html",
					success: function(formHtml) {
						// Inject form after the selected NovaPoshta radio button
						var selectedRadio = novaposhtaRadio.filter(":checked");
						if (selectedRadio.length === 0) {
							selectedRadio = novaposhtaRadio.first();
						}
						var formCheckDiv = selectedRadio.closest(".form-check");
						if (formCheckDiv.length) {
							formCheckDiv.after(formHtml);
						}
					},
					error: function(xhr, status, error) {
						console.error("Failed to load NovaPoshta form:", error);
					}
				});
			}
			
			// Handle radio button change to show/hide form
			novaposhtaRadio.on("change", function() {
				if ($(this).is(":checked")) {
					$("#ocnp_novaposhta_area").closest(".form-group").show();
				} else {
					$("#ocnp_novaposhta_area, #ocnp_novaposhta_city, #ocnp_novaposhta_warehouse").closest(".form-group").hide();
				}
			});
			
			// Trigger change on page load if already selected
			if (novaposhtaRadio.is(":checked")) {
				novaposhtaRadio.trigger("change");
			}
		}
	});
});
</script>';
		
		// Inject script before closing body tag or at end of output
		if (strpos($output, '</body>') !== false) {
			$output = str_replace('</body>', $script . '</body>', $output);
		} else {
			$output .= $script;
		}
	}

	private function OCNP_getValue(string $name, string $address_name): string {
		$value = $this->session->data['shipping_address'][$address_name] ?? '';

		if (isset($this->request->post[$name])) {
			$value = $this->request->post[$name];
		}

		return $value;
	}

	private function stringInsert(string $str, string $insertstr, int $pos): string {
		$str = substr($str, 0, $pos) . $insertstr . substr($str, $pos);
		return $str;
	}

	private function sendResponse(array $data): void {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($data));
	}
}

