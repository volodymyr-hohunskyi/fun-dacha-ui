<?php
namespace Opencart\Catalog\Controller\Common;

class HomeBundles extends \Opencart\System\Engine\Controller {
	private const BUNDLE_CATEGORY_ID = 600;

	public function index(): string {
		$this->load->model('catalog/product');

		$lang_key = str_contains($this->config->get('config_language'), 'uk') ? 'uk' : 'en';

		$bundles_meta = [
			['product_id' => 2001, 'image' => 'brand/bundle/bundle-nabir-novachka.png',   'name_uk' => 'Набір новачка',            'name_en' => 'Starter Bundle'],
			['product_id' => 2002, 'image' => 'brand/bundle/bundle-pryanosmakovi.png',     'name_uk' => 'Пряносмакові + Зелень',    'name_en' => 'Herbs & Greens'],
			['product_id' => 2003, 'image' => 'brand/bundle/bundle-zakhyst.png',           'name_uk' => 'Захист врожаю',            'name_en' => 'Crop Protection'],
			['product_id' => 2004, 'image' => 'brand/bundle/bundle-tomaty.png',            'name_uk' => 'Набір Томати',             'name_en' => 'Tomato Bundle'],
			['product_id' => 2005, 'image' => 'brand/bundle/bundle-kapusty.png',           'name_uk' => 'Набір Капусти',            'name_en' => 'Cabbage Bundle'],
		];

		$data['bundles'] = [];

		foreach ($bundles_meta as $meta) {
			$product_info = $this->model_catalog_product->getProduct($meta['product_id']);

			$href = $product_info
				? $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $meta['product_id'])
				: $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . self::BUNDLE_CATEGORY_ID);

			$data['bundles'][] = [
				'name'  => $meta['name_' . $lang_key],
				'image' => 'image/' . $meta['image'],
				'href'  => $href,
			];
		}

		$data['heading_title'] = $lang_key === 'uk' ? 'Готові набори' : 'Curated Bundles';
		$data['heading_subtitle'] = $lang_key === 'uk' ? 'Зберіть більше — заощадьте 20%' : 'Buy more — save 20%';

		return $this->load->view('common/home_bundles', $data);
	}
}
