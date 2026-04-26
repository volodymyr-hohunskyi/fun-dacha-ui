<?php
namespace Opencart\Catalog\Controller\Common;

class HomeSeasons extends \Opencart\System\Engine\Controller {
	public function index(): string {
		$this->load->model('catalog/season');

		$seasons = $this->model_catalog_season->getCurrentSeasons();

		if (empty($seasons)) {
			return '';
		}

		$lang_key = str_contains($this->config->get('config_language'), 'uk') ? 'uk' : 'en';

		$data['seasons'] = [];

		foreach ($seasons as $season) {
			$data['seasons'][] = [
				'name'        => $season['name_' . $lang_key] ?? $season['name_uk'],
				'description' => $season['description_uk'] ?? '',
				'icon'        => $season['icon'] ?? 'fa-calendar',
				'emoji'       => $season['emoji'] ?? '',
				'color'       => $season['color'] ?? '#4CAF50',
				'href'        => $this->url->link('product/season', 'language=' . $this->config->get('config_language') . '&slug=' . $season['slug']),
			];
		}

		$data['heading_title'] = $lang_key === 'uk' ? 'Що саджати зараз' : 'What to plant now';
		$data['month_name'] = $this->getUkrainianMonth((int)date('n'));

		return $this->load->view('common/home_seasons', $data);
	}

	private function getUkrainianMonth(int $month): string {
		$months = [
			1 => 'Січень', 2 => 'Лютий', 3 => 'Березень',
			4 => 'Квітень', 5 => 'Травень', 6 => 'Червень',
			7 => 'Липень', 8 => 'Серпень', 9 => 'Вересень',
			10 => 'Жовтень', 11 => 'Листопад', 12 => 'Грудень',
		];

		return $months[$month] ?? '';
	}
}
