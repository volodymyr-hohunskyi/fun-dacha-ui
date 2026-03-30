<?php
namespace Opencart\System\Library\Pack;

/**
 * Ukrainian labels for pack quantity (replaces legacy «уп» in UI).
 */
class PackLabel {
	/**
	 * Full phrase: «1 пакет», «2 пакети», «5 пакетів», …
	 */
	public static function ukPackCount(int $n): string {
		if ($n < 1) {
			return '';
		}

		if ($n === 1) {
			return '1 пакет';
		}

		$n10  = $n % 10;
		$n100 = $n % 100;

		// 11–14 пакетів
		if ($n100 >= 11 && $n100 <= 14) {
			return $n . ' пакетів';
		}

		if ($n10 === 1) {
			return $n . ' пакет';
		}

		if ($n10 >= 2 && $n10 <= 4) {
			return $n . ' пакети';
		}

		return $n . ' пакетів';
	}


	/**
	 * Short form for selects: «2 п.», «5 п.»
	 */
	public static function ukPackShort(int $n): string {
		return $n . ' п.';
	}
}
