<?php
namespace Opencart\Catalog\Controller\Common;

class TrustBar extends \Opencart\System\Engine\Controller {
	public function index(): string {
		$data['items'] = [
			[
				'icon'  => 'fa-truck-fast',
				'title' => 'Доставка Новою Поштою',
				'text'  => 'По всій Україні 1-3 дні',
			],
			[
				'icon'  => 'fa-money-bill-wave',
				'title' => 'Оплата при отриманні',
				'text'  => 'Накладений платіж',
			],
			[
				'icon'  => 'fa-seedling',
				'title' => '1000+ сортів',
				'text'  => 'Перевірене насіння',
			],
			[
				'icon'  => 'fa-circle-check',
				'title' => 'Гарантія сходження',
				'text'  => 'Якість підтверджена',
			],
		];

		return $this->load->view('common/trust_bar', $data);
	}
}
