<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Home Brands – manufacturer carousel (Наші партнери).
 *
 * @package Opencart\Catalog\Controller\Common
 */
class HomeBrands extends \Opencart\System\Engine\Controller {
	public function index(): string {
		$output = $this->load->controller('extension/opencart/module/manufacturer');
		return $output ? '<section class="home-brands-section"><div class="container">' . $output . '</div></section>' : '';
	}
}
