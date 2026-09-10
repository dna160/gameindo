<?php
/**
 * Google Tag Manager — prints the container snippet from the ID stored by
 * gameindo-core (Container ID lives in wp-admin → GameIndo → Analytics, or
 * the GAMEINDO_GTM_ID constant). GA4 and every other tag are configured
 * inside the GTM container itself, not here — this file only ever prints
 * the two standard GTM snippets, unmodified.
 *
 * @package GameIndo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Same auto-defer shape as gameindo_seo_active(): if a dedicated analytics
 * plugin is already active, its own GTM/GA snippet would double up with
 * ours — same container firing twice inflates every pageview and event
 * count, which is worse than not tracking at all. GAMEINDO_GTM_ID doesn't
 * help distinguish that case, so this checks independently of it.
 */
function gameindo_analytics_other_plugin_active() {
	return defined( 'GOOGLESITEKIT_VERSION' )
		|| function_exists( 'gtm4wp_the_gtm_tag' )
		|| class_exists( 'MonsterInsights' )
		|| defined( 'EXACTMETRICS_VERSION' )
		|| class_exists( 'Analytify' );
}

function gameindo_analytics_active() {
	if ( ! function_exists( 'gameindo_core_gtm_id' ) || ! gameindo_core_gtm_id() ) {
		return false;
	}
	return ! (bool) apply_filters( 'gameindo_analytics_disable', gameindo_analytics_other_plugin_active() );
}

function gameindo_analytics_head() {
	if ( ! gameindo_analytics_active() ) {
		return;
	}
	$id = esc_js( gameindo_core_gtm_id() );
	echo "\n<!-- Google Tag Manager -->\n<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n"
		. "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n"
		. "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n"
		. "'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\n"
		. "})(window,document,'script','dataLayer','" . $id . "');</script>\n<!-- End Google Tag Manager -->\n";
}
add_action( 'wp_head', 'gameindo_analytics_head', 0 );

function gameindo_analytics_body() {
	if ( ! gameindo_analytics_active() ) {
		return;
	}
	$id = esc_attr( gameindo_core_gtm_id() );
	echo '<!-- Google Tag Manager (noscript) -->'
		. '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . $id . '"'
		. ' height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>'
		. "<!-- End Google Tag Manager (noscript) -->\n";
}
add_action( 'wp_body_open', 'gameindo_analytics_body' );
