<?php
/**
 * Google Tag Manager container ID — resolved here so both the front-end
 * (theme, which prints the snippet) and the admin settings page can read
 * it without a load-order dependency between the two.
 *
 * @package GameIndo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A wp-config.php constant wins over the wp-admin field, same precedence as
 * GAMEINDO_RAWG_KEY and GAMEINDO_PANDASCORE_TOKEN.
 */
function gameindo_core_gtm_id() {
	if ( defined( 'GAMEINDO_GTM_ID' ) && GAMEINDO_GTM_ID ) {
		return GAMEINDO_GTM_ID;
	}
	return trim( (string) get_option( 'gameindo_gtm_id', '' ) );
}
