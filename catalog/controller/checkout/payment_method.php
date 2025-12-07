<?php
namespace Opencart\Catalog\Controller\Checkout;
/**
 * Class PaymentMethod
 *
 * @package Opencart\Catalog\Controller\Checkout
 */
class PaymentMethod extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('checkout/payment_method');

		// Auto-select COD (cash on delivery) if not already set
		if (!isset($this->session->data['payment_method'])) {
			$this->autoSelectCOD();
		}

		if (isset($this->session->data['payment_method'])) {
			$data['payment_method'] = $this->session->data['payment_method']['name'];
			$data['code'] = $this->session->data['payment_method']['code'];
		} else {
			$data['payment_method'] = 'Післяплата Нової Пошти';
			$data['code'] = 'cod.novaposhta';
		}

		if (isset($this->session->data['comment'])) {
			$data['comment'] = $this->session->data['comment'];
		} else {
			$data['comment'] = '';
		}

		if (isset($this->session->data['agree'])) {
			$data['agree'] = $this->session->data['agree'];
		} else {
			$data['agree'] = '';
		}

		// Information
		$this->load->model('catalog/information');

		$information_info = $this->model_catalog_information->getInformation((int)$this->config->get('config_checkout_id'));

		if ($information_info) {
			$data['text_agree'] = sprintf($this->language->get('text_agree'), $this->url->link('information/information.info', 'language=' . $this->config->get('config_language') . '&information_id=' . $this->config->get('config_checkout_id')), $information_info['title']);
		} else {
			$data['text_agree'] = '';
		}

		$data['language'] = $this->config->get('config_language');
		
		// Always show COD disclaimer
		$data['show_cod_disclaimer'] = true;

		return $this->load->view('checkout/payment_method', $data);
	}
	
	/**
	 * Auto-select COD payment method
	 */
	private function autoSelectCOD(): void {
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
			foreach ($payment_methods as $extension => $method_data) {
				if (isset($method_data['option'])) {
					foreach ($method_data['option'] as $option_key => $option) {
						$code = $extension . '.' . $option_key;
						// Check if it's COD (cash on delivery) - common codes: cod, afterpay, cod.novaposhta
						if (stripos($code, 'cod') !== false || stripos($code, 'afterpay') !== false || stripos($option['name'], 'післяплата') !== false || stripos($option['name'], 'cod') !== false) {
							$this->session->data['payment_method'] = $option;
							$this->session->data['payment_methods'] = $payment_methods;
							return;
						}
					}
				}
			}
			// If no COD found, select first available method
			foreach ($payment_methods as $extension => $method_data) {
				if (isset($method_data['option']) && !empty($method_data['option'])) {
					$first_option = reset($method_data['option']);
					$this->session->data['payment_method'] = $first_option;
					$this->session->data['payment_methods'] = $payment_methods;
					return;
				}
			}
		}
	}

	/**
	 * Get Methods
	 *
	 * @return void
	 */
	public function getMethods(): void {
		$this->load->language('checkout/payment_method');

		$json = [];

		// Validate cart has products and has stock.
		if (!$this->cart->hasProducts() || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout')) || !$this->cart->hasMinimum()) {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		if (!$json) {
			// Validate if customer session data is set
			if (!isset($this->session->data['customer'])) {
				$json['error'] = $this->language->get('error_customer');
			}

			if ($this->config->get('config_checkout_payment_address') && !isset($this->session->data['payment_address'])) {
				$json['error'] = $this->language->get('error_payment_address');
			}

			// Validate shipping
			if ($this->cart->hasShipping()) {
				// Validate shipping address
				if (!isset($this->session->data['shipping_address']['address_id'])) {
					$json['error'] = $this->language->get('error_shipping_address');
				}

				// Validate shipping method
				if (!isset($this->session->data['shipping_method'])) {
					$json['error'] = $this->language->get('error_shipping_method');
				}
			}
		}

		if (!$json) {
			$payment_address = [];

			if ($this->config->get('config_checkout_payment_address') && isset($this->session->data['payment_address'])) {
				$payment_address = $this->session->data['payment_address'];
			} elseif ($this->config->get('config_checkout_shipping_address') && isset($this->session->data['shipping_address']['address_id'])) {
				$payment_address = $this->session->data['shipping_address'];
			}

			// Payment method
			$this->load->model('checkout/payment_method');

			$payment_methods = $this->model_checkout_payment_method->getMethods($payment_address);

			if ($payment_methods) {
				$json['payment_methods'] = $this->session->data['payment_methods'] = $payment_methods;
			} else {
				$json['error'] = sprintf($this->language->get('error_no_payment'), $this->url->link('information/contact', 'language=' . $this->config->get('config_language')));
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('checkout/payment_method');

		$json = [];

		// Validate cart has products and has stock.
		if (!$this->cart->hasProducts() || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout')) || !$this->cart->hasMinimum()) {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		if (!$json) {
			// Validate has payment address if required
			if ($this->config->get('config_checkout_payment_address') && !isset($this->session->data['payment_address'])) {
				$json['error'] = $this->language->get('error_payment_address');
			}

			// Validate shipping
			if ($this->cart->hasShipping()) {
				// Validate shipping address
				if (!isset($this->session->data['shipping_address']['address_id'])) {
					$json['error'] = $this->language->get('error_shipping_address');
				}

				// Validate shipping method
				if (!isset($this->session->data['shipping_method'])) {
					$json['error'] = $this->language->get('error_shipping_method');
				}
			}

			// Validate payment methods - if not in POST, use auto-selected COD
			if (!isset($this->request->post['payment_method']) || empty($this->request->post['payment_method'])) {
				// Auto-select COD if not provided
				$this->autoSelectCOD();
			}
			
			if (isset($this->request->post['payment_method']) && isset($this->session->data['payment_methods'])) {
				$payment = explode('.', $this->request->post['payment_method']);

				if (!isset($payment[0]) || !isset($payment[1]) || !isset($this->session->data['payment_methods'][$payment[0]]['option'][$payment[1]])) {
					$json['error'] = $this->language->get('error_payment_method');
				}
			}
			
			// If payment method already set in session (from auto-select), use it
			if (!isset($this->request->post['payment_method']) && isset($this->session->data['payment_method'])) {
				// Payment method already auto-selected, skip validation
			} elseif (!isset($this->request->post['payment_method']) || !isset($this->session->data['payment_methods'])) {
				// Try to auto-select if not set
				$this->autoSelectCOD();
				if (!isset($this->session->data['payment_method'])) {
					$json['error'] = $this->language->get('error_payment_method');
				}
			}
		}

		if (!$json) {
			$this->session->data['payment_method'] = $this->session->data['payment_methods'][$payment[0]]['option'][$payment[1]];

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Comment
	 *
	 * @return void
	 */
	public function comment(): void {
		$this->load->language('checkout/payment_method');
		$this->load->model('checkout/order');

		$json = [];

		if (isset($this->session->data['order_id'])) {
			$order_id = (int)$this->session->data['order_id'];
		} else {
			$order_id = 0;
		}

		if (isset($this->request->post['comment'])) {
			$comment = (string)$this->request->post['comment'];
		} else {
			$comment = '';
		}

		// Save comment to session first
		$this->session->data['comment'] = $comment;

		// If order doesn't exist yet, try to create it by loading confirm controller
		if (!$order_id || !$this->model_checkout_order->getOrder($order_id)) {
			// Order doesn't exist - try to create it by ensuring confirm section is loaded
			// This will create the order if all validation passes
			$this->load->controller('checkout/confirm');
			
			// Check again if order was created
			if (isset($this->session->data['order_id'])) {
				$order_id = $this->session->data['order_id'];
				$order_info = $this->model_checkout_order->getOrder($order_id);
				
				if ($order_info) {
					// Order exists now - update comment
					$this->model_checkout_order->editComment($order_id, $comment);
					$json['success'] = $this->language->get('text_comment');
				} else {
					// Still can't find order - don't show error, just save to session
					// Order will be created when confirm section is loaded
					$json['success'] = $this->language->get('text_comment');
				}
			} else {
				// Order not created yet - just save comment to session
				// It will be attached when order is created
				$json['success'] = $this->language->get('text_comment');
			}
		} else {
			// Order exists - update comment
			$order_info = $this->model_checkout_order->getOrder($order_id);
			if ($order_info) {
				$this->model_checkout_order->editComment($order_id, $comment);
				$json['success'] = $this->language->get('text_comment');
			} else {
				// Order ID exists but order not found in DB - save to session
				$json['success'] = $this->language->get('text_comment');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Agree
	 *
	 * @return void
	 */
	public function agree(): void {
		$this->load->language('checkout/payment_method');

		$json = [];

		if (isset($this->request->post['agree'])) {
			$this->session->data['agree'] = $this->request->post['agree'];
		} else {
			unset($this->session->data['agree']);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
