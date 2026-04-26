<?php
namespace Opencart\Catalog\Model\Catalog;

class Season extends \Opencart\System\Engine\Model {
	private ?array $seasons = null;

	private function load(): array {
		if ($this->seasons !== null) {
			return $this->seasons;
		}

		$file = DIR_APPLICATION . 'data/seasons.json';

		if (!is_file($file)) {
			$this->seasons = [];
			return $this->seasons;
		}

		$json = file_get_contents($file);
		$data = json_decode($json, true);

		$this->seasons = is_array($data) ? $data : [];
		return $this->seasons;
	}

	public function getSeasons(): array {
		$seasons = $this->load();

		$active = array_filter($seasons, fn(array $s): bool => !empty($s['status']));

		usort($active, fn(array $a, array $b): int => ($a['sort_order'] ?? 99) <=> ($b['sort_order'] ?? 99));

		return $active;
	}

	public function getCurrentSeasons(): array {
		$month = (int)date('n');

		return array_filter($this->getSeasons(), fn(array $s): bool => in_array($month, $s['months'] ?? [], true));
	}

	public function getSeason(string $slug): ?array {
		$seasons = $this->load();

		foreach ($seasons as $season) {
			if (($season['slug'] ?? '') === $slug && !empty($season['status'])) {
				return $season;
			}
		}

		return null;
	}
}
