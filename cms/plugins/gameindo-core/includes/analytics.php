<?php
/**
 * Google Tag Manager container ID, and a GA4 Measurement ID for a direct
 * (non-GTM) gtag.js install — resolved here so both the front-end (theme,
 * which prints the snippets) and the admin settings page can read them
 * without a load-order dependency between the two.
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

/**
 * Same precedence as gameindo_core_gtm_id(). This is a direct GA4 install,
 * independent of GTM — set it when you want GA4 live without configuring a
 * GA4 Configuration tag inside GTM's dashboard first. If a GA4 tag is later
 * added inside GTM too, this field should be cleared, or GA4 receives every
 * event twice (see the warning on the Analytics settings page).
 */
function gameindo_core_ga4_id() {
	if ( defined( 'GAMEINDO_GA4_ID' ) && GAMEINDO_GA4_ID ) {
		return GAMEINDO_GA4_ID;
	}
	return trim( (string) get_option( 'gameindo_ga4_id', '' ) );
}
