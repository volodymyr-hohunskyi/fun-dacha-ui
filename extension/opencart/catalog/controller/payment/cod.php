<?php
namespace Opencart\Catalog\Controller\Extension\Opencart\Payment;
/**
 * Class Cod
 *
 * @package Opencart\Catalog\Controller\Extension\Opencart\Payment
 */
class Cod extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('extension/opencart/payment/cod');

		$data['language'] = $this->config->get('config_language');

		return $this->load->view('extension/opencart/payment/cod', $data);
	}

	/**
	 * Confirm
	 *
	 * @return void
	 */
	public function confirm(): void {
		$this->load->language('extension/opencart/payment/cod');

		$json = [];

		// AUTO-ASSIGN COD PAYMENT METHOD if not set
		if (!isset($this->session->data['payment_method']) || $this->session->data['payment_method']['code'] != 'cod.cod') {
			// Try to get payment methods and auto-select COD
			$payment_address = [];
			
			if ($this->config->get('config_checkout_payment_address') && isset($this->session->data['payment_address'])) {
				$payment_address = $this->session->data['payment_address'];
			} elseif (isset($this->session->data['shipping_address'])) {
				$payment_address = $this->session->data['shipping_address'];
			}
			
			$this->load->model('checkout/payment_method');
			$payment_methods = $this->model_checkout_payment_method->getMethods($payment_address);
			
			if ($payment_methods) {
				// Look for COD payment method
				$cod_found = false;
				foreach ($payment_methods as $extension => $method_data) {
					if (isset($method_data['option'])) {
						foreach ($method_data['option'] as $option_key => $option) {
							$code = $extension . '.' . $option_key;
							// Check if it's COD (cash on delivery)
							if ($code === 'cod.cod' || stripos($code, 'cod') !== false) {
								$this->session->data['payment_method'] = $option;
								$this->session->data['payment_methods'] = $payment_methods;
								$cod_found = true;
								break 2;
							}
						}
					}
				}
				
				// If COD not found, use first available method
				if (!$cod_found) {
					foreach ($payment_methods as $extension => $method_data) {
						if (isset($method_data['option'])) {
							foreach ($method_data['option'] as $option_key => $option) {
								$this->session->data['payment_method'] = $option;
								$this->session->data['payment_methods'] = $payment_methods;
								break 2;
							}
						}
					}
				}
			} else {
				// If no payment methods available, set default COD structure
				$this->session->data['payment_method'] = [
					'code' => 'cod.cod',
					'name' => 'Післяплата',
					'terms' => '',
					'sort_order' => 1
				];
			}
		}

		// Validate payment method after auto-assignment
		if (!isset($this->session->data['payment_method']) || $this->session->data['payment_method']['code'] != 'cod.cod') {
			$json['error'] = $this->language->get('error_payment_method');
		}

		// AUTO-ASSIGN NOVA POSHTA SHIPPING METHOD
		if (!isset($this->session->data['shipping_method'])) {
			$this->session->data['shipping_method'] = [
				'code'       => 'nova_poshta.nova_poshta',
				'name'       => 'Доставка Новою Поштою',
				'cost'       => 0,
				'tax_class_id' => 0,
				'text'       => 'Нова Пошта'
			];

			$this->session->data['shipping_methods'] = [
				'nova_poshta' => [
					'name'       => 'Нова Пошта',
					'quote'      => [
						'nova_poshta' => [
							'code'       => 'nova_poshta.nova_poshta',
							'name'       => 'Доставка Новою Поштою',
							'cost'       => 0,
							'tax_class_id' => 0,
							'text'       => 'Нова Пошта'
						]
					],
					'sort_order' => 1,
					'error'      => false
				]
			];
		}

		// Validate order exists or create it
		if (!isset($this->session->data['order_id'])) {
			// Ensure all required data is present before creating order
			$missing_data = [];
			
			if (!isset($this->session->data['customer'])) {
				$missing_data[] = 'customer';
			}
			if ($this->cart->hasShipping() && !isset($this->session->data['shipping_address'])) {
				$missing_data[] = 'shipping_address';
			}
			if ($this->cart->hasShipping() && !isset($this->session->data['shipping_method'])) {
				$missing_data[] = 'shipping_method';
			}
			if (!isset($this->session->data['payment_method'])) {
				$missing_data[] = 'payment_method';
			}
			if (!$this->cart->hasProducts()) {
				$missing_data[] = 'cart_empty';
			}
			if ($this->config->get('config_checkout_id') && empty($this->session->data['agree'])) {
				$missing_data[] = 'agree';
			}
			
			if (!empty($missing_data)) {
				// Return error with helpful message
				$json['error'] = $this->language->get('error_order');
				$json['error_details'] = 'Будь ласка, заповніть всі обов\'язкові поля перед підтвердженням замовлення. Відсутні: ' . implode(', ', $missing_data);
				$json['redirect'] = false; // Don't redirect, just show error
			} else {
				// All data present - create order by directly instantiating and calling confirm controller
				// This ensures the order creation logic is executed
				$confirm_controller = new \Opencart\Catalog\Controller\Checkout\Confirm($this->registry);
				// Call index method which creates the order if status is true
				$confirm_controller->index();
				
				// Check if order was created
				if (!isset($this->session->data['order_id'])) {
					// Order not created - check why by examining status conditions
					$status_checks = [];
					
					// Check the same conditions as confirm controller
					if (!($this->customer->isLogged() || !$this->config->get('config_customer_price'))) {
						$status_checks[] = 'customer_price_check_failed';
					}
					if (!isset($this->session->data['customer'])) {
						$status_checks[] = 'customer_missing';
					}
					if (!$this->cart->hasProducts() || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout')) || !$this->cart->hasMinimum()) {
						$status_checks[] = 'cart_validation_failed';
					}
					if ($this->cart->hasShipping()) {
						if (!isset($this->session->data['shipping_address'])) {
							$status_checks[] = 'shipping_address_missing';
						}
						if (!isset($this->session->data['shipping_method'])) {
							$status_checks[] = 'shipping_method_missing';
						}
					}
					if ($this->config->get('config_checkout_payment_address') && !isset($this->session->data['payment_address'])) {
						$status_checks[] = 'payment_address_missing';
					}
					if (!isset($this->session->data['payment_method'])) {
						$status_checks[] = 'payment_method_missing';
					}
					if ($this->config->get('config_checkout_id') && empty($this->session->data['agree'])) {
						$status_checks[] = 'agree_missing';
					}
					
					$json['error'] = $this->language->get('error_order');
					if (!empty($status_checks)) {
						$json['error_details'] = 'Не вдалося створити замовлення. Проблеми: ' . implode(', ', $status_checks);
					} else {
						$json['error_details'] = 'Не вдалося створити замовлення. Будь ласка, спробуйте ще раз або оновіть сторінку.';
					}
				}
			}
		}

		// Validate order exists in database
		if (!isset($json['error']) && isset($this->session->data['order_id'])) {
			$this->load->model('checkout/order');

			$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

			if (!$order_info) {
				// Order ID exists in session but not in database - try to create order again
				unset($this->session->data['order_id']);
				// Load confirm controller to create the order
				$this->load->controller('checkout/confirm');
				
				// Check if order was created this time
				if (isset($this->session->data['order_id'])) {
					$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
					if (!$order_info) {
						$json['error'] = $this->language->get('error_order') . ' (Order creation failed)';
					}
				} else {
					$json['error'] = $this->language->get('error_order') . ' (Could not create order)';
				}
			}
		}

		if (!isset($json['error']) && isset($this->session->data['order_id'])) {
			// Order exists - proceed with confirmation
			$this->load->model('checkout/order');

			$this->model_checkout_order->addHistory($this->session->data['order_id'], $this->config->get('payment_cod_order_status_id'));

			$json['redirect'] = $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
