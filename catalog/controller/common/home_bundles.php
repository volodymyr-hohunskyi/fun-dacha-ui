<?php
namespace Opencart\Catalog\Controller\Common;

class HomeBundles extends \Opencart\System\Engine\Controller {
	public function index(): string {
		$this->load->model('catalog/bundle');

		$bundles = $this->model_catalog_bundle->getBundles();

		if (empty($bundles)) {
			return '';
		}

		$lang_key = str_contains($this->config->get('config_language'), 'uk') ? 'uk' : 'en';

		$data['bundles'] = [];

		foreach ($bundles as $bundle) {
			$data['bundles'][] = [
				'name' => $bundle['name_' . $lang_key] ?? $bundle['name_uk'],
				'description' => $bundle['description_uk'] ?? '',
				'icon' => $bundle['icon'] ?? 'fa-box',
				'href' => $this->url->link('product/bundle', 'language=' . $this->config->get('config_language') . '&slug=' . $bundle['slug']),
			];
		}

		$data['heading_title'] = $lang_key === 'uk' ? 'Готові набори' : 'Curated Bundles';

		return $this->load->view('common/home_bundles', $data);
	}
}
