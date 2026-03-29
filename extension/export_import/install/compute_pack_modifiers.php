#!/usr/bin/env php
<?php
/**
 * CLI: compute OpenCart option price modifiers from target unit prices per pack size.
 *
 * Usage:
 *   php compute_pack_modifiers.php --base=12 --units="1:12,2:11,5:10" [--discount=10]
 *
 * Paths: run from repo root or this directory; resolves ../../system/library/pack/
 */

$root = dirname( __DIR__, 3 );
$packDir = $root . '/system/library/pack/pack_pricing.php';
$valDir = $root . '/system/library/pack/pack_pricing_validator.php';

if ( ! is_file( $packDir ) || ! is_file( $valDir ) ) {
	fwrite( STDERR, "Cannot find pack pricing library under system/library/pack/ (looked from {$root}).\n" );
	exit( 1 );
}

require_once $packDir;
require_once $valDir;

$opts = getopt( '', array( 'base:', 'units:', 'discount::' ) );

if ( empty( $opts['base'] ) || empty( $opts['units'] ) ) {
	echo "Usage: php compute_pack_modifiers.php --base=12 --units=\"1:12,2:11,5:10\" [--discount=10]\n";
	exit( 1 );
}

$base = (float) $opts['base'];
$discount = isset( $opts['discount'] ) ? (float) $opts['discount'] : 0.0;

$pairs = explode( ',', (string) $opts['units'] );
$map = array();
foreach ( $pairs as $pair ) {
	$pair = trim( $pair );
	if ( $pair === '' ) {
		continue;
	}
	$kv = explode( ':', $pair, 2 );
	if ( count( $kv ) !== 2 ) {
		fwrite( STDERR, "Bad pair: {$pair}\n" );
		exit( 1 );
	}
	$map[(int) trim( $kv[0] )] = (float) trim( $kv[1] );
}

$computed = \Opencart\System\Library\Pack\PackPricing::computeModifiers( $base, $map );
$validation = \Opencart\System\Library\Pack\PackPricingValidator::validate( $computed, $base, $discount );

echo "Base price: {$base}\n";
echo "Sale % after option (0 = none): {$discount}\n\n";

echo str_pad( 'Pack', 6 ) . str_pad( 'Target unit', 14 ) . str_pad( 'Total pack', 12 ) . str_pad( 'Modifier (+)', 14 ) . str_pad( 'Final', 12 ) . "Eff. unit\n";
foreach ( $validation['rows'] as $row ) {
	echo str_pad( (string) $row['pack_size'], 6 );
	echo str_pad( (string) ( $computed[$row['pack_size']]['target_unit_price'] ?? '' ), 14 );
	echo str_pad( (string) ( $computed[$row['pack_size']]['total_pack_price'] ?? '' ), 12 );
	echo str_pad( (string) $row['modifier'], 14 );
	echo str_pad( (string) $row['final_price'], 12 );
	echo (string) $row['effective_unit_price'] . "\n";
}

echo "\nProductOptionValues columns: price_prefix = +, price = modifier (same row as option value).\n";

if ( ! $validation['ok'] ) {
	echo "\nValidation failed:\n";
	foreach ( $validation['errors'] as $e ) {
		echo "  - {$e}\n";
	}
	exit( 2 );
}

echo "\nOK: unit price is non-increasing as pack size increases.\n";
exit( 0 );
