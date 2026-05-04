<?php
namespace Opencart\Catalog\Model\Catalog;

class Promo extends \Opencart\System\Engine\Model {
	private ?array $promos = null;

	private function load(): array {
		if ($this->promos !== null) {
			return $this->promos;
		}

		$file = DIR_APPLICATION . 'data/promos.json';

		if (!is_file($file)) {
			$this->promos = [];
			return $this->promos;
		}

		$json = file_get_contents($file);
		$data = json_decode($json, true);

		$this->promos = is_array($data) ? $data : [];
		return $this->promos;
	}

	public function getPromos(): array {
		$promos = $this->load();

		$active = array_filter($promos, fn(array $p): bool => !empty($p['status']));

		usort($active, fn(array $a, array $b): int => ($a['sort_order'] ?? 99) <=> ($b['sort_order'] ?? 99));

		return $active;
	}

	public function getPromo(string $slug): ?array {
		$promos = $this->load();

		foreach ($promos as $promo) {
			if (($promo['slug'] ?? '') === $slug && !empty($promo['status'])) {
				return $promo;
			}
		}

		return null;
	}
}
