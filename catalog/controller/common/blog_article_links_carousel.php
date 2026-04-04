<?php
namespace Opencart\Catalog\Controller\Common;

/**
 * Single carousel on article pages: all catalog links found in the article HTML (products, categories, manufacturers).
 *
 * @package Opencart\Catalog\Controller\Common
 */
class BlogArticleLinksCarousel extends \Opencart\System\Engine\Controller {
	private const PER_SLIDE = 6;

	/**
	 * @param array<string, mixed> $data Expects `html` (article body) and `article_id` (for unique carousel id).
	 */
	public function index(array $data = []): string {
		$html = (string)($data['html'] ?? '');
		$article_id = (int)($data['article_id'] ?? 0);

		if ($html === '') {
			return '';
		}

		$this->load->model('design/seo_url');

		$links = $this->extractCatalogLinks($html);

		if ($links === []) {
			return '';
		}

		$this->load->language('common/blog_article_links_carousel');

		$view_data = [
			'heading_title' => $this->language->get('heading_title'),
			'links'         => $links,
			'per_slide'     => self::PER_SLIDE,
			'article_id'    => $article_id,
			'carousel_id'   => 'carousel-article-links-' . ($article_id > 0 ? $article_id : 'article'),
		];

		return $this->load->view('common/blog_article_links_carousel', $view_data);
	}

	/**
	 * @return array<int, array{href: string, name: string}>
	 */
	private function extractCatalogLinks(string $html): array {
		libxml_use_internal_errors(true);
		$doc = new \DOMDocument();
		@$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
		$xpath = new \DOMXPath($doc);
		$seen = [];
		$out = [];

		foreach ($xpath->query('//a[@href]') as $a) {
			if (!($a instanceof \DOMElement)) {
				continue;
			}

			$href = trim($a->getAttribute('href'));

			if ($href === '' || $href === '#' || str_starts_with(strtolower($href), 'javascript:') || str_starts_with(strtolower($href), 'mailto:') || str_starts_with(strtolower($href), 'tel:')) {
				continue;
			}

			$absolute = $this->normalizeToAbsoluteUrl($href);

			if ($absolute === null) {
				continue;
			}

			if (!$this->isInternalUrl($absolute)) {
				continue;
			}

			if (!$this->isCatalogDestination($absolute)) {
				continue;
			}

			$key = $this->canonicalHref($absolute);

			if (isset($seen[$key])) {
				continue;
			}

			$seen[$key] = true;

			$name = trim(preg_replace('/\s+/u', ' ', $a->textContent));

			if ($name === '') {
				$name = $key;
			}

			$out[] = [
				'href' => $absolute,
				'name' => $name,
			];
		}

		return $out;
	}

	private function normalizeToAbsoluteUrl(string $href): ?string {
		$href = trim($href);

		if ($href === '') {
			return null;
		}

		$base = rtrim((string)$this->config->get('config_url'), '/');

		if (preg_match('#^https?://#i', $href)) {
			return $href;
		}

		if (str_starts_with($href, '//')) {
			$scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';

			return $scheme . ':' . $href;
		}

		if (str_starts_with($href, '/')) {
			return $base . $href;
		}

		return $base . '/' . $href;
	}

	private function isInternalUrl(string $absolute): bool {
		$hosts = [];

		foreach ([$this->config->get('config_url'), $this->config->get('config_ssl')] as $h) {
			if (!$h) {
				continue;
			}

			$p = parse_url((string)$h);

			if (!empty($p['host'])) {
				$hosts[strtolower($p['host'])] = true;
			}
		}

		$link = parse_url($absolute);

		if (empty($link['host'])) {
			return true;
		}

		return isset($hosts[strtolower($link['host'])]);
	}

	private function isCatalogDestination(string $absoluteUrl): bool {
		$parts = parse_url($absoluteUrl);

		if ($parts === false) {
			return false;
		}

		$query = [];

		if (!empty($parts['query'])) {
			parse_str($parts['query'], $query);
		}

		if (!empty($query['route'])) {
			$r = (string)$query['route'];

			return str_starts_with($r, 'product/product')
				|| str_starts_with($r, 'product/category')
				|| str_starts_with($r, 'product/manufacturer');
		}

		if (isset($query['product_id']) && (int)$query['product_id'] > 0) {
			return true;
		}

		if (isset($query['path']) && $query['path'] !== '') {
			return true;
		}

		if (isset($query['manufacturer_id']) && (int)$query['manufacturer_id'] > 0) {
			return true;
		}

		$path = trim((string)($parts['path'] ?? ''), '/');

		if ($path === '') {
			return false;
		}

		if (str_contains($path, 'index.php')) {
			return false;
		}

		$segments = array_values(array_filter(explode('/', $path), static fn ($s) => $s !== ''));

		for ($i = 0; $i < count($segments); $i++) {
			$keyword = implode('/', array_slice($segments, $i));
			$seo = $this->model_design_seo_url->getSeoUrlByKeyword($keyword);

			if ($seo && isset($seo['key'])) {
				$k = (string)$seo['key'];

				if ($k === 'product_id' || $k === 'path' || $k === 'manufacturer_id') {
					return true;
				}
			}
		}

		return false;
	}

	private function canonicalHref(string $url): string {
		$parts = parse_url($url);

		if ($parts === false) {
			return $url;
		}

		$scheme = strtolower($parts['scheme'] ?? 'https');
		$host = strtolower($parts['host'] ?? '');
		$path = $parts['path'] ?? '';
		$query = '';

		if (!empty($parts['query'])) {
			parse_str($parts['query'], $q);
			ksort($q);
			$query = http_build_query($q);
		}

		return $scheme . '://' . $host . $path . ($query !== '' ? '?' . $query : '');
	}
}
