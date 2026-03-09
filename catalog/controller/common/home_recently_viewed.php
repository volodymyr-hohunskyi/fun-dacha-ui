<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Home Recently Viewed – wraps extension module with default settings.
 *
 * @package Opencart\Catalog\Controller\Common
 */
class HomeRecentlyViewed extends \Opencart\System\Engine\Controller {
	public function index(): string {
		$setting = [
			'limit'  => 6,
			'width'  => 200,
			'height' => 200,
			'axis'   => 'horizontal',
		];
		$output = $this->load->controller('extension/opencart/module/recently_viewed', $setting);
		return $output ? '<div class="home-recently-viewed-wrapper"><div class="container">' . $output . '</div></div>' : '';
	}
}
