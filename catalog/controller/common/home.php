<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Class Home
 *
 * Can be called from $this->load->controller('common/home');
 *
 * @package Opencart\Catalog\Controller\Common
 */
class Home extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$description = $this->config->get('config_description');
		$language_id = $this->config->get('config_language_id');
		$store_name = $this->config->get('config_name');

		// Default homepage SEO (overridden by System → Settings → Store description per language)
		$meta_title = 'Інтернет-магазин насіння овочів та квітів в Україні | ' . $store_name;

		$enhanced_description = 'Купити насіння овочів і квітів з доставкою по Україні. У каталозі ' . $store_name . ' — понад 500 сортів, перевірена схожість, добрива та супутні товари для городу. Швидка доставка Новою Поштою.';
		
		// Use configured meta tags if available, otherwise use enhanced SEO versions
		if (isset($description[$language_id])) {
			$meta_title = !empty($description[$language_id]['meta_title']) 
				? $description[$language_id]['meta_title'] 
				: $meta_title;
			
			$meta_description = !empty($description[$language_id]['meta_description']) 
				? $description[$language_id]['meta_description'] 
				: $enhanced_description;
			
			if (!empty($description[$language_id]['meta_keyword'])) {
				$this->document->setKeywords($description[$language_id]['meta_keyword']);
			}
		} else {
			$meta_description = $enhanced_description;
		}
		
		$this->document->setTitle($meta_title);
		$this->document->setDescription($meta_description);
		
		// Add canonical for homepage
		$canonical_url = $this->url->link('common/home', 'language=' . $this->config->get('config_language'));
		$this->document->addLink($canonical_url, 'canonical');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('common/home', $data));
	}
}
