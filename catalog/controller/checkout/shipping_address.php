<?php
namespace Opencart\Catalog\Controller\Checkout;
/**
 * Class ShippingAddress
 *
 * @package Opencart\Catalog\Controller\Checkout
 */
class ShippingAddress extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('checkout/shipping_address');

		$data['error_upload_size'] = sprintf($this->language->get('error_upload_size'), $this->config->get('config_file_max_size'));
		$data['config_file_max_size'] = ((int)$this->config->get('config_file_max_size') * 1024 * 1024);
		$data['payment_address_required'] = $this->config->get('config_checkout_payment_address');

		$this->session->data['upload_token'] = oc_token(32);

		$data['upload'] = $this->url->link('tool/upload', 'language=' . $this->config->get('config_language') . '&upload_token=' . $this->session->data['upload_token']);

		// Address
		$this->load->model('account/address');

		$data['addresses'] = $this->model_account_address->getAddresses($this->customer->getId());

		if (isset($this->session->data['shipping_address']['address_id'])) {
			$data['address_id'] = $this->session->data['shipping_address']['address_id'];
		} else {
			$data['address_id'] = 0;
		}

		if (isset($this->session->data['shipping_address'])) {
			$data['postcode'] = $this->session->data['shipping_address']['postcode'];
			$data['country_id'] = $this->session->data['shipping_address']['country_id'];
			$data['zone_id'] = $this->session->data['shipping_address']['zone_id'];
		} else {
			$data['postcode'] = '';
			$data['country_id'] = (int)$this->config->get('config_country_id');
			$data['zone_id'] = '';
		}

		// Country
		$this->load->model('localisation/country');

		$data['countries'] = $this->model_localisation_country->getCountries();

		// Zone
		$this->load->model('localisation/zone');

		$data['zones'] = $this->model_localisation_zone->getZonesByCountryId($data['country_id']);

		// Customer data for logged in users
		if ($this->customer->isLogged()) {
			$data['customer'] = [
				'firstname' => $this->customer->getFirstName(),
				'lastname' => $this->customer->getLastName()
			];
		} else {
			$data['customer'] = [
				'firstname' => '',
				'lastname' => ''
			];
		}

		// Custom Fields
		$data['custom_fields'] = [];

		$this->load->model('account/custom_field');

		$customer_group_id = $this->customer->isLogged() ? $this->customer->getGroupId() : (int)$this->config->get('config_customer_group_id');
		$custom_fields = $this->model_account_custom_field->getCustomFields($customer_group_id);

		foreach ($custom_fields as $custom_field) {
			if ($custom_field['location'] == 'address') {
				$data['custom_fields'][] = $custom_field;
			}
		}

		// Ukraine Regions (26 oblasts + Kyiv city + Crimea) - for Nova Poshta
		$data['ukraine_regions'] = [
			'Вінницька область',
			'Волинська область',
			'Дніпропетровська область',
			'Донецька область',
			'Житомирська область',
			'Закарпатська область',
			'Запорізька область',
			'Івано-Франківська область',
			'Київська область',
			'Кіровоградська область',
			'Луганська область',
			'Львівська область',
			'Миколаївська область',
			'Одеська область',
			'Полтавська область',
			'Рівненська область',
			'Сумська область',
			'Тернопільська область',
			'Харківська область',
			'Херсонська область',
			'Хмельницька область',
			'Черкаська область',
			'Чернівецька область',
			'Чернігівська область',
			'м. Київ',
			'АР Крим'
		];
		
		// Get Ukraine region from session or default
		if (isset($this->session->data['shipping_address']['region'])) {
			$data['shipping_region'] = $this->session->data['shipping_address']['region'];
		} else {
			$data['shipping_region'] = '';
		}
		
		// Set default country to Ukraine (country_id 220)
		if (!isset($this->session->data['shipping_address']['country_id']) || (int)$this->session->data['shipping_address']['country_id'] != 220) {
			$data['country_id'] = 220; // Ukraine
		}
		
		$data['config_country_id'] = (int)$this->config->get('config_country_id');
		$data['shipping_country_id'] = $data['country_id'];

		$data['language'] = $this->config->get('config_language');

		return $this->load->view('checkout/shipping_address', $data);
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('checkout/shipping_address');

		$json = [];

		$required = [
			'firstname'    => '',
			'lastname'     => '',
			'company'      => '',
			'address_1'    => '',
			'address_2'    => '',
			'city'         => '',
			'postcode'     => '',
			'country_id'   => 0,
			'zone_id'      => 0,
			'zone'         => '',
			'shipping_region' => '',
			'ocnp_novaposhta_city' => '',
			'ocnp_novaposhta_warehouse' => '',
			'custom_field' => []
		];

		$post_info = $this->request->post + $required;
		
		// For Nova Poshta: if shipping_region is provided, set country to Ukraine (220)
		if (!empty($post_info['shipping_region'])) {
			$post_info['country_id'] = 220; // Ukraine
		}

		// Validate cart has products and has stock.
		if (!$this->cart->hasProducts() || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout')) || !$this->cart->hasMinimum()) {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		// Validate if customer is logged in or customer session data is not set
		if (!$this->customer->isLogged() || !isset($this->session->data['customer'])) {
			$json['redirect'] = $this->url->link('account/login', 'language=' . $this->config->get('config_language'), true);
		}

		// Validate if shipping not required
		if (!$this->cart->hasShipping()) {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		if (!$json) {
			// Get customer name if logged in and fields are empty
			if ($this->customer->isLogged()) {
				if (empty($post_info['firstname'])) {
					$post_info['firstname'] = $this->customer->getFirstName();
				}
				if (empty($post_info['lastname'])) {
					$post_info['lastname'] = $this->customer->getLastName();
				}
			}
			
			if (!oc_validate_length($post_info['firstname'], 1, 32)) {
				$json['error']['firstname'] = $this->language->get('error_firstname');
			}

			if (!oc_validate_length($post_info['lastname'], 1, 32)) {
				$json['error']['lastname'] = $this->language->get('error_lastname');
			}

			// For Nova Poshta (Ukraine): validate region, city, warehouse instead of address_1
			if ((int)$post_info['country_id'] == 220 && !empty($post_info['shipping_region'])) {
				// Nova Poshta validation
				if (empty($post_info['shipping_region'])) {
					$json['error']['shipping_region'] = 'Будь ласка, оберіть область!';
				}
				if (empty($post_info['ocnp_novaposhta_city'])) {
					$json['error']['shipping_city_np'] = 'Будь ласка, оберіть місто!';
				}
				if (empty($post_info['ocnp_novaposhta_warehouse'])) {
					$json['error']['shipping_warehouse_np'] = 'Будь ласка, оберіть відділення!';
				}
				
				// Set address_1 from warehouse if not set
				if (empty($post_info['address_1']) && !empty($post_info['ocnp_novaposhta_warehouse'])) {
					$post_info['address_1'] = $post_info['ocnp_novaposhta_warehouse'];
				}
				// Set city from Nova Poshta city if not set
				if (empty($post_info['city']) && !empty($post_info['ocnp_novaposhta_city'])) {
					$post_info['city'] = $post_info['ocnp_novaposhta_city'];
				}
			} else {
				// Standard address validation
				if (!oc_validate_length($post_info['address_1'], 3, 128)) {
					$json['error']['address_1'] = $this->language->get('error_address_1');
				}

				if (!oc_validate_length($post_info['city'], 2, 128)) {
					$json['error']['city'] = $this->language->get('error_city');
				}
			}

			// Country
			$this->load->model('localisation/country');

			$country_info = $this->model_localisation_country->getCountry((int)$post_info['country_id']);

			if ($country_info && $country_info['postcode_required'] && !oc_validate_length($post_info['postcode'], 2, 10)) {
				$json['error']['postcode'] = $this->language->get('error_postcode');
			}

			if (!$country_info) {
				$json['error']['country'] = $this->language->get('error_country');
			}

			// Zone validation and setup - find zone_id by region name for Ukraine (country_id 220)
			$this->load->model('localisation/zone');

			// If shipping to Ukraine (country_id 220) and zone_id is not set, try to find it by region name
			if ((int)$post_info['country_id'] == 220) {
				$current_zone_id = isset($post_info['zone_id']) ? (int)$post_info['zone_id'] : 0;

				if ($current_zone_id <= 0) {
					// Get all zones for Ukraine
					$zones = $this->model_localisation_zone->getZonesByCountryId(220);

					if (!empty($zones)) {
						$zone_id_found = false;

						// Try to find zone by exact match with shipping_region
						if (!empty($post_info['shipping_region'])) {
							$region_name = trim($post_info['shipping_region']);
							$region_name_lower = mb_strtolower($region_name, 'UTF-8');
							$region_name_clean = str_replace(['область', 'обл.', 'обл'], '', $region_name_lower);
							$region_name_clean = trim($region_name_clean);

							// First try: exact match
							foreach ($zones as $zone) {
								$zone_name_lower = mb_strtolower(trim($zone['name']), 'UTF-8');
								$zone_name_clean = str_replace(['область', 'обл.', 'обл'], '', $zone_name_lower);
								$zone_name_clean = trim($zone_name_clean);

								if (trim($zone['name']) === $region_name) {
									$post_info['zone_id'] = (int)$zone['zone_id'];
									$zone_id_found = true;
									break;
								}
								// Second try: case-insensitive match
								if (mb_strtolower(trim($zone['name']), 'UTF-8') === $region_name_lower) {
									$post_info['zone_id'] = (int)$zone['zone_id'];
									$zone_id_found = true;
									break;
								}
								// Third try: cleaned name match
								if ($zone_name_clean === $region_name_clean) {
									$post_info['zone_id'] = (int)$zone['zone_id'];
									$zone_id_found = true;
									break;
								}

								// Also check if one contains the other
								if (strpos($zone_name_lower, $region_name_clean) !== false || strpos($region_name_clean, $zone_name_clean) !== false) {
									$post_info['zone_id'] = (int)$zone['zone_id'];
									$zone_id_found = true;
									break;
								}
							}
						}

						// If still not found, use first available zone as fallback
						if (!$zone_id_found && !empty($zones)) {
							$post_info['zone_id'] = (int)$zones[0]['zone_id'];
						}
					}
				}
			}

			$zone_total = $this->model_localisation_zone->getTotalZonesByCountryId((int)$post_info['country_id']);

			// Check if zone_id is valid (not empty, not 0, not '0')
			$shipping_zone_id = isset($post_info['zone_id']) ? (int)$post_info['zone_id'] : 0;

			// For Ukraine (country_id 220), skip validation if zone_id is 0 but region is provided
			if ($zone_total && $shipping_zone_id <= 0) {
				// Skip zone validation for Ukraine if shipping_region is provided (Nova Poshta)
				if ((int)$post_info['country_id'] == 220 && !empty($post_info['shipping_region'])) {
					// For Ukraine with region specified, zone_id lookup should have worked
					// But if it didn't, we'll use fallback: set zone_id to first available zone
					if (!isset($post_info['zone_id']) || $post_info['zone_id'] == '0' || (int)$post_info['zone_id'] == 0) {
						$zones = $this->model_localisation_zone->getZonesByCountryId(220);
						if (!empty($zones)) {
							$post_info['zone_id'] = (int)$zones[0]['zone_id'];
						}
					}
				} else {
					// For other countries or missing region, require zone_id
					$json['error']['zone'] = $this->language->get('error_zone');
				}
			}

			// Custom field validation
			$this->load->model('account/custom_field');

			$custom_fields = $this->model_account_custom_field->getCustomFields($this->customer->getGroupId());

			foreach ($custom_fields as $custom_field) {
				if ($custom_field['location'] == 'address') {
					if ($custom_field['required'] && empty($post_info['custom_field'][$custom_field['custom_field_id']])) {
						$json['error']['custom_field_' . $custom_field['custom_field_id']] = sprintf($this->language->get('error_custom_field'), $custom_field['name']);
					} elseif (($custom_field['type'] == 'text') && !empty($custom_field['validation']) && !oc_validate_regex($post_info['custom_field'][$custom_field['custom_field_id']], $custom_field['validation'])) {
						$json['error']['custom_field_' . $custom_field['custom_field_id']] = sprintf($this->language->get('error_regex'), $custom_field['name']);
					}
				}
			}
		}

		if (!$json) {
			// If no default address has been found, add it
			$address_id = $this->customer->getAddressId();

			if (!$address_id) {
				$post_info['default'] = 1;
			}

			$this->load->model('account/address');

			$json['address_id'] = $this->model_account_address->addAddress($this->customer->getId(), $post_info);

			$json['addresses'] = $this->model_account_address->getAddresses($this->customer->getId());

			$address_info = $this->model_account_address->getAddress($this->customer->getId(), $json['address_id']);
			
			// For Nova Poshta: add region and Nova Poshta fields to session
			if ((int)$post_info['country_id'] == 220 && !empty($post_info['shipping_region'])) {
				$address_info['region'] = $post_info['shipping_region'];
				if (isset($post_info['ocnp_novaposhta_area'])) {
					$address_info['ocnp_novaposhta_area'] = $post_info['ocnp_novaposhta_area'];
				}
				if (isset($post_info['ocnp_novaposhta_city'])) {
					$address_info['ocnp_novaposhta_city'] = $post_info['ocnp_novaposhta_city'];
				}
				if (isset($post_info['ocnp_novaposhta_warehouse'])) {
					$address_info['ocnp_novaposhta_warehouse'] = $post_info['ocnp_novaposhta_warehouse'];
				}
				// Set zone from region if zone is empty
				if (empty($address_info['zone']) && !empty($post_info['shipping_region'])) {
					$address_info['zone'] = $post_info['shipping_region'];
				}
			}
			
			$this->session->data['shipping_address'] = $address_info;

			$json['success'] = $this->language->get('text_success');

			// Clear payment and shipping methods
			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Address
	 *
	 * @return void
	 */
	public function address(): void {
		$this->load->language('checkout/shipping_address');

		$json = [];

		if (isset($this->request->get['address_id'])) {
			$address_id = (int)$this->request->get['address_id'];
		} else {
			$address_id = 0;
		}

		// Validate cart has products and has stock.
		if (!$this->cart->hasProducts() || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout')) || !$this->cart->hasMinimum()) {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		// Validate if customer is logged in or customer session data is not set
		if (!$this->customer->isLogged() || !isset($this->session->data['customer'])) {
			$json['redirect'] = $this->url->link('account/login', 'language=' . $this->config->get('config_language'), true);
		}

		// Validate if shipping is not required
		if (!$this->cart->hasShipping()) {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		if (!$json) {
			// Shipping Address
			$this->load->model('account/address');

			$address_info = $this->model_account_address->getAddress($this->customer->getId(), $address_id);

			if (!$address_info) {
				$json['error'] = $this->language->get('error_address');

				unset($this->session->data['shipping_address']);
				unset($this->session->data['shipping_method']);
				unset($this->session->data['shipping_methods']);
				unset($this->session->data['payment_method']);
				unset($this->session->data['payment_methods']);
			}
		}

		if (!$json) {
			$this->session->data['shipping_address'] = $address_info;

			$json['success'] = $this->language->get('text_success');

			// Clear payment and shipping methods
			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
