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

		// Validate order exists or create it
		if (!isset($this->session->data['order_id'])) {
			// Order doesn't exist - try to create it by loading confirm controller
			$this->load->controller('checkout/confirm');
			
			// Check again if order was created
			if (!isset($this->session->data['order_id'])) {
				$json['error'] = $this->language->get('error_order') . ' (Order not created)';
			}
		}

		// Validate order exists in database
		if (!isset($json['error']) && isset($this->session->data['order_id'])) {
			$this->load->model('checkout/order');

			$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

			if (!$order_info) {
				// Order ID exists in session but not in database - try to create order again
				unset($this->session->data['order_id']);
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
