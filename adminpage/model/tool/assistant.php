<?php
namespace Opencart\Admin\Model\Tool;
/**
 * Assistant overlay JSON (payment/delivery text from information pages).
 *
 * @package Opencart\Admin\Model\Tool
 */
class Assistant extends \Opencart\System\Engine\Model {
	public function stripHtmlToText(string $html): string {
		$html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$html = str_replace(['</p>', '</div>', '<br>', '<br/>', '<br />'], "\n", $html);
		$text = strip_tags($html);
		$text = preg_replace("/[ \t]+/u", ' ', $text);
		$text = preg_replace("/\n{3,}/u", "\n\n", $text);

		return trim($text);
	}

	/**
	 * @param array<string, array<string, array<string, string>>> $info
	 */
	public function writeOverlay(array $info): bool {
		$dir = DIR_STORAGE . 'assistant/';

		if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
			return false;
		}

		$payload = ['info' => $info, 'updated_at' => date('c')];
		$path    = $dir . 'assistant_info.json';

		return (bool) file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
	}
}
