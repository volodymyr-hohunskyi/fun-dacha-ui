<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Home Recommended – Рекомендоване section using the recommended module.
 *
 * @package Opencart\Catalog\Controller\Common
 */
class HomeRecommended extends \Opencart\System\Engine\Controller {
	public function index(): string {
		$this->load->language('common/home_recommended');
		$setting = [
			'limit'            => 8,
			'width'            => 200,
			'height'           => 200,
			'axis'             => 'horizontal',
			'heading_override' => $this->language->get('heading_title'),
		];
		return $this->load->controller('extension/opencart/module/recommended', $setting);
	}
}
