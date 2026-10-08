<?php
/**
 * Google Tag Manager + a direct GA4 install — both read their IDs from
 * gameindo-core (wp-admin → GameIndo → Analytics, or the GAMEINDO_GTM_ID /
 * GAMEINDO_GA4_ID constants). Each prints the standard snippet Google's own
 * dashboards give you, unmodified, and each is independently toggled by
 * whether its own ID is set — a site can run either, both, or neither.
 *
 * @package GameIndo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Same auto-defer shape as gameindo_seo_active(): if a dedicated analytics
 * plugin is already active, its own GTM/GA snippet would double up with
 * ours — the same container or property firing twice inflates every
 * pageview and event count, which is worse than not tracking at all.
 */
function gameindo_analytics_other_plugin_active() {
	return defined( 'GOOGLESITEKIT_VERSION' )
		|| function_exists( 'gtm4wp_the_gtm_tag' )
		|| class_exists( 'MonsterInsights' )
		|| defined( 'EXACTMETRICS_VERSION' )
		|| class_exists( 'Analytify' );
}

function gameindo_gtm_active() {
	if ( ! function_exists( 'gameindo_core_gtm_id' ) || ! gameindo_core_gtm_id() ) {
		return false;
	}
	return ! (bool) apply_filters( 'gameindo_analytics_disable', gameindo_analytics_other_plugin_active() );
}

function gameindo_ga4_active() {
	if ( ! function_exists( 'gameindo_core_ga4_id' ) || ! gameindo_core_ga4_id() ) {
		return false;
	}
	return ! (bool) apply_filters( 'gameindo_analytics_disable', gameindo_analytics_other_plugin_active() );
}

function gameindo_analytics_head() {
	if ( gameindo_gtm_active() ) {
		$id = esc_js( gameindo_core_gtm_id() );
		echo "\n<!-- Google Tag Manager -->\n<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n"
			. "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n"
			. "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n"
			. "'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\n"
			. "})(window,document,'script','dataLayer','" . $id . "');</script>\n<!-- End Google Tag Manager -->\n";
	}
	if ( gameindo_ga4_active() ) {
		$id = esc_js( gameindo_core_ga4_id() );
		echo "\n<!-- Google tag (gtag.js) -->\n"
			. '<script async src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr( gameindo_core_ga4_id() ) . "\"></script>\n"
			. "<script>\nwindow.dataLayer = window.dataLayer || [];\nfunction gtag(){dataLayer.push(arguments);}\n"
			. "gtag('js', new Date());\ngtag('config', '" . $id . "');\n</script>\n<!-- End Google tag (gtag.js) -->\n";
	}
}
add_action( 'wp_head', 'gameindo_analytics_head', 0 );

function gameindo_analytics_body() {
	if ( ! gameindo_gtm_active() ) {
		return;
	}
	$id = esc_attr( gameindo_core_gtm_id() );
	echo '<!-- Google Tag Manager (noscript) -->'
		. '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . $id . '"'
		. ' height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>'
		. "<!-- End Google Tag Manager (noscript) -->\n";
}
add_action( 'wp_body_open', 'gameindo_analytics_body' );
