<?php
namespace Opencart\System\Library\Pack;

/**
 * Validates pack pricing: effective unit prices and monotonic volume discount (larger pack ≤ unit price).
 */
class PackPricingValidator {
	/**
	 * @param array<int, array<string, mixed>> $computedFromPackPricing output of PackPricing::computeModifiers()
	 * @param float                            $basePrice
	 * @param float                            $specialPercentOff       e.g. 10 for 10% off entire line after option
	 *
	 * @return array{ok: bool, errors: array<int, string>, rows: array<int, array<string, float>>}
	 */
	public static function validate(
		array $computedFromPackPricing,
		float $basePrice,
		float $specialPercentOff = 0.0
	): array {
		$errors = array();
		$rows = array();
		$sizes = array_keys( $computedFromPackPricing );
		sort( $sizes, SORT_NUMERIC );

		$prevUnit = null;
		$prevSize = null;
		foreach ( $sizes as $size ) {
			$row = $computedFromPackPricing[$size];
			$modifier = (float) ( $row['modifier'] ?? 0 );
			$afterOption = (float) $basePrice + $modifier;
			$final = PackPricing::priceAfterSpecialPercent( $afterOption, $specialPercentOff );
			$unit = PackPricing::effectiveUnitPrice( $final, (int) $size );

			$rows[$size] = array(
				'pack_size'            => (int) $size,
				'modifier'               => $modifier,
				'price_after_option'     => round( $afterOption, 4 ),
				'final_price'            => $final,
				'effective_unit_price'   => $unit,
			);

			if ( $prevUnit !== null && $unit > $prevUnit + 1e-5 ) {
				$errors[] = sprintf(
					'Pack size %d has higher effective unit price (%.6f) than pack size %d (%.6f): larger packs must be cheaper or equal per unit.',
					(int) $size,
					$unit,
					(int) $prevSize,
					$prevUnit
				);
			}
			$prevUnit = $unit;
			$prevSize = (int) $size;
		}

		return array(
			'ok'     => $errors === array(),
			'errors' => $errors,
			'rows'   => $rows,
		);
	}
}
