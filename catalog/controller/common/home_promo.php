<?php
namespace Opencart\Catalog\Controller\Common;

class HomePromo extends \Opencart\System\Engine\Controller {
	public function index(): string {
		$this->load->model('catalog/promo');

		$promos = $this->model_catalog_promo->getPromos();

		if (empty($promos)) {
			return '';
		}

		$lang_key = str_contains($this->config->get('config_language'), 'uk') ? 'uk' : 'en';

		$data['promos'] = [];

		foreach ($promos as $promo) {
			$image = '';
			if (!empty($promo['image']) && is_file(DIR_IMAGE . $promo['image'])) {
				$image = $this->config->get('config_url') . 'image/' . $promo['image'];
			}

			$data['promos'][] = [
				'name'        => $promo['name_' . $lang_key] ?? $promo['name_uk'],
				'description' => $promo['description_uk'] ?? '',
				'subtitle'    => $promo['subtitle_uk'] ?? '',
				'icon'        => $promo['icon'] ?? 'fa-tag',
				'image'       => $image,
				'color'       => $promo['color'] ?? '#207D43',
				'href'        => $this->url->link('product/promo', 'language=' . $this->config->get('config_language') . '&slug=' . $promo['slug']),
			];
		}

		$data['heading_title'] = $lang_key === 'uk' ? 'Акції та пропозиції' : 'Deals & Offers';
		$data['heading_subtitle'] = $lang_key === 'uk' ? 'Вигідні пропозиції для вашого городу' : 'Great deals for your garden';

		return $this->load->view('common/home_promo', $data);
	}
}
