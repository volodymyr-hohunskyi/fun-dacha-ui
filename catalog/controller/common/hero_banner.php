<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Class HeroBanner
 *
 * Hero banner carousel with JSON config. Background stays the same;
 * slides change title, subtitle, image, and shop button.
 *
 * @package Opencart\Catalog\Controller\Common
 */
class HeroBanner extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->model('tool/image');

		$catalog_dir = defined('DIR_APPLICATION') ? DIR_APPLICATION : dirname(__DIR__, 2) . '/';
		$config_path = $catalog_dir . 'config/hero-banner.json';
		$image_dir = defined('DIR_IMAGE') ? DIR_IMAGE : (dirname($catalog_dir) . '/image/');

		if (!is_file($config_path)) {
			return '';
		}

		$json = file_get_contents($config_path);
		$config = json_decode($json, true);

		if (!$config || empty($config['slides'])) {
			return '';
		}

		$base_url = $this->config->get('config_url');
		$image_base = $base_url . 'image/';

		$data['background'] = '';
		$data['use_slide_image_as_bg'] = false;
		if (!empty($config['background_image'])) {
			$bg_file = $image_dir . html_entity_decode($config['background_image'], ENT_QUOTES, 'UTF-8');
			if (is_file($bg_file)) {
				$data['background'] = $image_base . $config['background_image'];
			}
		}
		if (empty($data['background'])) {
			$data['use_slide_image_as_bg'] = true;
		}

		$data['interval'] = (int)($config['interval_ms'] ?? 5000);
		$data['slides'] = [];

		foreach ($config['slides'] as $i => $slide) {
			$image_url = $image_base . 'placeholder.png';
			if (!empty($slide['image'])) {
				$img_file = $image_dir . html_entity_decode($slide['image'], ENT_QUOTES, 'UTF-8');
				if (is_file($img_file)) {
					$image_url = $image_base . $slide['image'];
				} else {
					$image_url = $this->model_tool_image->resize('placeholder.png', 600, 500);
				}
			}

			$button_link = $slide['button_link'] ?? '';
			if ($button_link) {
				if (strpos($button_link, 'http') === 0) {
					// external URL - use as is
				} elseif (strpos($button_link, '&') !== false) {
					$parts = explode('&', $button_link, 2);
					$route = $parts[0];
					$args = $parts[1] ?? '';
					$button_link = $this->url->link($route, $args);
				} else {
					$button_link = $this->url->link($button_link);
				}
			}

			$data['slides'][] = [
				'title'       => $slide['title'] ?? '',
				'subtitle'    => $slide['subtitle'] ?? '',
				'button_text' => $slide['button_text'] ?? 'До каталогу',
				'button_link' => $button_link,
				'image'       => $image_url,
				'index'       => $i,
			];
		}

		return $this->load->view('common/hero_banner', $data);
	}
}
