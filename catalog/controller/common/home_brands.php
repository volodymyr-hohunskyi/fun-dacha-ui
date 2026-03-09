<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Home Brands – manufacturer carousel.
 *
 * @package Opencart\Catalog\Controller\Common
 */
class HomeBrands extends \Opencart\System\Engine\Controller {
	public function index(): string {
		return $this->load->controller('extension/opencart/module/manufacturer');
	}
}
