<?php
/**
 * Custom REST namespace `gameindo/v1` — mirrors the CUSTOM_API_BASE routes the
 * original js/cms-client.js expects (ticker, matches, standings, topics). Handy
 * for a future headless setup or the bundled static preview; the native theme
 * itself reads the data directly via includes/helpers.php.
 *
 * @package GameIndo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function gameindo_core_register_rest_routes() {
	$ns = 'gameindo/v1';
	$ro = array( 'methods' => 'GET', 'permission_callback' => '__return_true' );

	register_rest_route( $ns, '/ticker',    array_merge( $ro, array( 'callback' => 'gameindo_core_get_ticker' ) ) );
	register_rest_route( $ns, '/topics',    array_merge( $ro, array( 'callback' => 'gameindo_core_get_topics' ) ) );
	register_rest_route( $ns, '/matches',   array_merge( $ro, array( 'callback' => 'gameindo_core_get_matches' ) ) );
	register_rest_route( $ns, '/standings', array_merge( $ro, array( 'callback' => 'gameindo_core_get_standings' ) ) );
}
add_action( 'rest_api_init', 'gameindo_core_register_rest_routes' );
