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

		// Validate payment method
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
			// Ensure confirm controller creates the order
			// Directly create order by instantiating confirm controller
			$confirm_controller = new \Opencart\Catalog\Controller\Checkout\Confirm($this->registry);
			// Call index method which creates the order
			$confirm_controller->index();
			
			// Check if order was created
			if (!isset($this->session->data['order_id'])) {
				// Order still not created - check why
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
				
				$json['error'] = $this->language->get('error_order') . ' (Order not created)';
				if (!empty($missing_data)) {
					$json['error'] .= ' - Missing: ' . implode(', ', $missing_data);
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
				// Directly create order by instantiating confirm controller
				$confirm_controller = new \Opencart\Catalog\Controller\Checkout\Confirm($this->registry);
				$confirm_controller->index();
				
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
