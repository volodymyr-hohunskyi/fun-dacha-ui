<?php
namespace Opencart\Catalog\Model\Catalog;

class Bundle extends \Opencart\System\Engine\Model {
	private ?array $bundles = null;

	private function load(): array {
		if ($this->bundles !== null) {
			return $this->bundles;
		}

		$file = DIR_APPLICATION . 'data/bundles.json';

		if (!is_file($file)) {
			$this->bundles = [];
			return $this->bundles;
		}

		$json = file_get_contents($file);
		$data = json_decode($json, true);

		$this->bundles = is_array($data) ? $data : [];
		return $this->bundles;
	}

	public function getBundles(): array {
		$bundles = $this->load();

		$active = array_filter($bundles, fn(array $b): bool => !empty($b['status']));

		usort($active, fn(array $a, array $b): int => ($a['sort_order'] ?? 99) <=> ($b['sort_order'] ?? 99));

		return $active;
	}

	public function getBundle(string $slug): ?array {
		$bundles = $this->load();

		foreach ($bundles as $bundle) {
			if (($bundle['slug'] ?? '') === $slug && !empty($bundle['status'])) {
				return $bundle;
			}
		}

		return null;
	}
}
