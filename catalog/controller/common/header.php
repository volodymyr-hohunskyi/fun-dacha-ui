<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Class Header
 *
 * Can be called from $this->load->controller('common/header');
 *
 * @package Opencart\Catalog\Controller\Common
 */
class Header extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		// Analytics
		$data['analytics'] = [];

		if (!$this->config->get('config_cookie_id') || (isset($this->request->cookie['policy']) && $this->request->cookie['policy'])) {
			// Extension
			$this->load->model('setting/extension');

			$analytics = $this->model_setting_extension->getExtensionsByType('analytics');

			foreach ($analytics as $analytic) {
				if ($this->config->get('analytics_' . $analytic['code'] . '_status')) {
					$data['analytics'][] = $this->load->controller('extension/' . $analytic['extension'] . '/analytics/' . $analytic['code'], $this->config->get('analytics_' . $analytic['code'] . '_status'));
				}
			}
		}

		$data['lang'] = $this->language->get('code');
		$data['direction'] = $this->language->get('direction');

		$data['title'] = $this->document->getTitle();
		$data['base'] = $this->config->get('config_url');
		$data['description'] = $this->document->getDescription();
		$data['keywords'] = $this->document->getKeywords();
		
		// Generate OpenGraph and Twitter Cards for homepage
		$current_route = $this->request->get['route'] ?? 'common/home';
		if ($current_route == 'common/home') {
			$data['og_tags'] = $this->generateHomepageOpenGraphTags();
			$data['twitter_tags'] = $this->generateHomepageTwitterTags();
		}

		// Hard coding css, so they can be replaced via the event's system.
		// Cache bust: bump version when layout/CSS changes
		$cssVer = '5';
		$data['bootstrap'] = 'catalog/view/stylesheet/bootstrap.css?v=' . $cssVer;
		$data['icons'] = 'catalog/view/stylesheet/fonts/fontawesome/css/all.min.css';
		$data['stylesheet'] = 'catalog/view/stylesheet/stylesheet.css?v=' . $cssVer;

		// Hard coding scripts, so they can be replaced via the event's system.
		$data['jquery'] = 'catalog/view/javascript/jquery/jquery-3.7.1.min.js';

		$data['links'] = $this->document->getLinks();
		$data['styles'] = $this->document->getStyles();
		$data['scripts'] = $this->document->getScripts('header');
		
		// Generate Organization Schema.org JSON-LD
		$data['schema_organization'] = $this->generateOrganizationSchema();
		
		// Generate WebSite Schema.org JSON-LD (separate from Organization as recommended by Google)
		$data['schema_website'] = $this->generateWebSiteSchema();

		$data['name'] = $this->config->get('config_name');

		// Fav icon
		if (is_file(DIR_IMAGE . $this->config->get('config_icon'))) {
			$data['icon'] = $this->config->get('config_url') . 'image/' . $this->config->get('config_icon');
		} else {
			$data['icon'] = '';
		}

		if (is_file(DIR_IMAGE . $this->config->get('config_logo'))) {
			$data['logo'] = $this->config->get('config_url') . 'image/' . $this->config->get('config_logo');
		} else {
			$data['logo'] = '';
		}

		$this->load->language('common/header');

		// Wishlist
		if ($this->customer->isLogged()) {
			$this->load->model('account/wishlist');

			$data['text_wishlist'] = sprintf($this->language->get('text_wishlist'), $this->model_account_wishlist->getTotalWishlist($this->customer->getId()));
		} else {
			$data['text_wishlist'] = sprintf($this->language->get('text_wishlist'), (isset($this->session->data['wishlist']) ? count($this->session->data['wishlist']) : 0));
		}

		$data['home'] = $this->url->link('common/home', 'language=' . $this->config->get('config_language'));
		$data['wishlist'] = $this->url->link('account/wishlist', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
		$data['logged'] = $this->customer->isLogged();

		// Account links removed from catalog UI - admin access still works
		$data['register'] = '';
		$data['login'] = '';
		$data['account'] = '';
		$data['order'] = '';
		$data['transaction'] = '';
		$data['download'] = '';
		$data['logout'] = '';

		$data['shopping_cart'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'));
		$data['checkout'] = $this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language'));
		$data['contact'] = $this->url->link('information/contact', 'language=' . $this->config->get('config_language'));
		$data['telephone'] = $this->config->get('config_telephone');

		$data['language'] = $this->load->controller('common/language');
		$data['currency'] = $this->load->controller('common/currency');
		$data['search'] = $this->load->controller('common/search');
		$data['cart'] = $this->load->controller('common/cart');
		$data['menu'] = $this->load->controller('common/menu');

		return $this->load->view('common/header', $data);
	}
	
	/**
	 * Generate Organization Schema.org JSON-LD
	 *
	 * @return string
	 */
	private function generateOrganizationSchema(): string {
		$base_url = rtrim($this->config->get('config_url'), '/');
		$store_name = $this->config->get('config_name');
		$store_phone = $this->config->get('config_telephone');
		$store_email = $this->config->get('config_email');
		
		$schema = [
			'@context' => 'https://schema.org',
			'@type' => 'Organization',
			'name' => $store_name,
			'url' => $base_url . '/',
		];
		
		if ($store_phone) {
			$schema['telephone'] = $store_phone;
		}
		
		if ($store_email) {
			$schema['email'] = $store_email;
		}
		
		// Add logo if available
		if (is_file(DIR_IMAGE . $this->config->get('config_logo'))) {
			$schema['logo'] = $base_url . '/image/' . $this->config->get('config_logo');
		}
		
		return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
	}
	
	/**
	 * Generate WebSite Schema.org JSON-LD
	 * Separate from Organization as recommended by Google for better sitelinks search box support
	 *
	 * @return string
	 */
	private function generateWebSiteSchema(): string {
		$base_url = rtrim($this->config->get('config_url'), '/');
		$store_name = $this->config->get('config_name');
		
		$schema = [
			'@context' => 'https://schema.org',
			'@type' => 'WebSite',
			'@id' => $base_url . '/#website',
			'url' => $base_url . '/',
			'name' => $store_name,
			'potentialAction' => [
				'@type' => 'SearchAction',
				'target' => [
					'@type' => 'EntryPoint',
					'urlTemplate' => $base_url . '/index.php?route=product/search&language=' . $this->config->get('config_language') . '&search={search_term_string}'
				],
				'query-input' => 'required name=search_term_string'
			]
		];
		
		return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
	}
	
	/**
	 * Generate OpenGraph meta tags for homepage
	 *
	 * @return array
	 */
	private function generateHomepageOpenGraphTags(): array {
		$base_url = rtrim($this->config->get('config_url'), '/');
		$store_name = $this->config->get('config_name');
		
		$og = [
			'og:type' => 'website',
			'og:title' => $store_name,
			'og:url' => $base_url . '/',
			'og:site_name' => $store_name,
		];
		
		// Description
		$description = $this->document->getDescription();
		if ($description) {
			$og['og:description'] = htmlspecialchars(strip_tags($description), ENT_QUOTES, 'UTF-8');
		}
		
		// Logo
		if (is_file(DIR_IMAGE . $this->config->get('config_logo'))) {
			$og['og:image'] = $base_url . '/image/' . $this->config->get('config_logo');
		}
		
		return $og;
	}
	
	/**
	 * Generate Twitter Card meta tags for homepage
	 *
	 * @return array
	 */
	private function generateHomepageTwitterTags(): array {
		$base_url = rtrim($this->config->get('config_url'), '/');
		$store_name = $this->config->get('config_name');
		
		$twitter = [
			'twitter:card' => 'summary',
			'twitter:title' => $store_name,
		];
		
		// Description
		$description = $this->document->getDescription();
		if ($description) {
			$twitter['twitter:description'] = htmlspecialchars(strip_tags($description), ENT_QUOTES, 'UTF-8');
		}
		
		// Logo
		if (is_file(DIR_IMAGE . $this->config->get('config_logo'))) {
			$twitter['twitter:image'] = $base_url . '/image/' . $this->config->get('config_logo');
		}
		
		return $twitter;
	}
}
