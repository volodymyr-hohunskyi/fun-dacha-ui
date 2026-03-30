<?php
namespace Opencart\System\Library\Pack;

/**
 * Deterministic pack / option pricing: static absolute modifiers only (OpenCart + prefix).
 *
 * Rules:
 * - Base product price is fixed (catalog `product.price`).
 * - Each pack size has a target unit price; total pack price = unit * pack_size.
 * - Option modifier = total_pack_price - base_price (stored in `product_option_value.price` with prefix +).
 * - Runtime: line = base + modifier (cart `system/library/cart/cart.php`).
 * - Percentage sale applies to (base + modifier), not to base alone (see cart for `product_discount` type P).
 */
class PackPricing {
	/**
	 * @param float $basePrice        Catalog base price (e.g. 12.00) — same as 1-pack total when 1-pack uses that price.
	 * @param array<int,float> $targetUnitPriceByPackSize pack_size => target unit price (e.g. [1 => 12.0, 2 => 11.0, 5 => 10.0])
	 *
	 * @return array<int, array<string, float|int|string>>
	 */
	public static function computeModifiers(float $basePrice, array $targetUnitPriceByPackSize): array {
		$out = [];
		foreach ($targetUnitPriceByPackSize as $packSize => $unitPrice) {
			$packSize = (int) $packSize;
			if ($packSize < 1) {
				continue;
			}
			$unit = (float) $unitPrice;
			$totalPackPrice = $unit * (float) $packSize;
			$modifier = $totalPackPrice - $basePrice;
			$out[$packSize] = [
				'pack_size'         => $packSize,
				'target_unit_price' => round( $unit, 4 ),
				'total_pack_price'  => round( $totalPackPrice, 4 ),
				'modifier'          => round( $modifier, 4 ),
				'price_prefix'      => '+',
			];
		}
		ksort( $out, SORT_NUMERIC );

		return $out;
	}


	/**
	 * Apply a store sale percentage after option pricing: final = (base + modifier) * (1 - rate/100).
	 */
	public static function priceAfterSpecialPercent(float $priceAfterOption, float $percentOff): float {
		$p = max( 0.0, (float) $percentOff );

		return round( $priceAfterOption * ( 1 - $p / 100.0 ), 4 );
	}


	/**
	 * Effective unit price after special: final_price / pack_size.
	 */
	public static function effectiveUnitPrice(float $finalLinePrice, int $packSize): float {
		if ($packSize < 1) {
			return 0.0;
		}

		return round( $finalLinePrice / (float) $packSize, 6 );
	}


	/**
	 * Leading integer in option value name (e.g. "2 уп" → 2).
	 */
	public static function parsePackSizeFromLabel(string $name): ?int {
		if (preg_match( '/^\s*(\d+)/u', $name, $m )) {
			$n = (int) $m[1];

			return $n >= 1 ? $n : null;
		}

		return null;
	}


	/**
	 * Signed option modifier from DB (absolute amount + prefix).
	 */
	public static function optionModifierAmount(float $price, string $prefix): float {
		$p = (float) $price;

		return ($prefix === '-') ? -$p : $p;
	}


	/**
	 * Line before special: raw catalog base + signed modifier.
	 */
	public static function priceAfterOption(float $rawBase, float $modifierSigned): float {
		return round( $rawBase + $modifierSigned, 4 );
	}


	/**
	 * Apply catalog special as ratio on the full line: final = price_after_option * (special / base).
	 * When no special, returns rounded price_after_option.
	 */
	public static function finalPriceWithSpecialRatio(float $priceAfterOption, float $rawBase, float $specialAbsolute): float {
		if ($rawBase <= 0.0) {
			return round( $priceAfterOption, 2 );
		}

		if ($specialAbsolute > 0.0) {
			return round( $priceAfterOption * ( $specialAbsolute / $rawBase ), 2 );
		}

		return round( $priceAfterOption, 2 );
	}


	/**
	 * Deterministic storefront row: OPTION → DISCOUNT → unit (all rounded to 2 decimals).
	 *
	 * @return array{final_price: float, unit_price: float, pack_size: int}
	 */
	public static function computePackDisplayRow(float $rawBase, float $optionPrice, string $pricePrefix, float $specialAbsolute, int $packSize): array {
		$modifier = self::optionModifierAmount( $optionPrice, $pricePrefix );
		$after    = self::priceAfterOption( $rawBase, $modifier );
		$final    = self::finalPriceWithSpecialRatio( $after, $rawBase, $specialAbsolute );
		$unit     = ($packSize >= 1) ? round( $final / (float) $packSize, 2 ) : 0.0;

		return [
			'final_price' => $final,
			'unit_price'  => $unit,
			'pack_size'   => $packSize,
		];
	}


	/**
	 * Total discount vs list unit price (catalog base per unit), e.g. list 12.00 vs unit 9.90 → 17.5%.
	 */
	public static function unitSavingsPercentVsListUnit(float $listUnitPrice, float $unitPriceAfterAll): ?float {
		if ($listUnitPrice <= 0.0) {
			return null;
		}

		$p = round( 100.0 * ( 1.0 - ( $unitPriceAfterAll / $listUnitPrice ) ), 1 );

		return $p > 0.0 ? $p : null;
	}
}
