<?php
$currencies = Currency::query()->with('history');

if( $source ) {
	$currencies = $currencies->where( 'source', $source );
}
if( $symbols ) {
	$currencies = $currencies->whereIn( 'symbol', $symbols );
}
if( !empty( $excludes_currencies ) ) {
	$currencies = $currencies->whereNotIn( 'symbol', $excludes_currencies );
}
foreach( $currencies->get() as $currency ) {
	var_dump($history); // Should be an array of History models
    die;
}