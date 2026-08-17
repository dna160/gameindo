<?php
/**
 * GameIndo theme bootstrap.
 *
 * @package GameIndo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GAMEINDO_VERSION', '1.1.1' );
define( 'GAMEINDO_DIR', get_template_directory() );
define( 'GAMEINDO_URI', get_template_directory_uri() );

/**
 * The five content pillars. The slugs MUST match the WordPress category
 * slugs and the [data-pillar] scopes in assets/css/tokens/colors.css.
 * Renaming a pillar is done in wp-admin (category name); slugs stay fixed.
 */
function gameindo_pillars() {
	return array(
		'home'          => 'Video Game',
		'esports'       => 'Esports',
		'streamer'      => 'Streamer',
		'tech'          => 'Tech',
		'entertainment' => 'Entertainment',
	);
}

require_once GAMEINDO_DIR . '/inc/template-helpers.php';
require_once GAMEINDO_DIR . '/inc/nav-walker.php';

/**
 * Theme setup.
 */
function gameindo_setup() {
	load_theme_textdomain( 'gameindo', GAMEINDO_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'custom-logo', array(
		'height'      => 40,
		'width'       => 160,
		'flex-height' => true,
		'flex-width'  => true,
	) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );

	// Featured-image crops tuned to the card/hero art in the original design.
	add_image_size( 'gameindo-card', 640, 420, true );
	add_image_size( 'gameindo-hero', 1200, 800, true );
	add_image_size( 'gameindo-thumb', 120, 120, true );

	register_nav_menus( array(
		'primary' => __( 'Pilar Utama (Header)', 'gameindo' ),
		'footer'  => __( 'Footer', 'gameindo' ),
		'drawer'  => __( 'Menu Mobile (Drawer)', 'gameindo' ),
	) );
}
add_action( 'after_setup_theme', 'gameindo_setup' );

/**
 * Front-end assets. main.css pulls in the token sheets via @import, so the
 * whole design system loads from one handle; per-template sheets layer on top.
 */
function gameindo_assets() {
	$css = GAMEINDO_URI . '/assets/css';

	wp_enqueue_style( 'gameindo-main', $css . '/main.css', array(), GAMEINDO_VERSION );
	wp_add_inline_style( 'gameindo-main', '.gi-is-hidden{display:none !important}' );

	if ( is_front_page() ) {
		wp_enqueue_style( 'gameindo-home', $css . '/home.css', array( 'gameindo-main' ), GAMEINDO_VERSION );
	}
	if ( is_singular( 'post' ) || is_page() ) {
		wp_enqueue_style( 'gameindo-article', $css . '/article.css', array( 'gameindo-main' ), GAMEINDO_VERSION );
	}
	if ( is_category() || is_tag() || is_tax() || ( is_home() && ! is_front_page() ) ) {
		wp_enqueue_style( 'gameindo-pillar', $css . '/pillar.css', array( 'gameindo-main' ), GAMEINDO_VERSION );
	}
	if ( is_author() ) {
		wp_enqueue_style( 'gameindo-author', $css . '/author.css', array( 'gameindo-main' ), GAMEINDO_VERSION );
	}
	if ( is_search() ) {
		wp_enqueue_style( 'gameindo-search', $css . '/search.css', array( 'gameindo-main' ), GAMEINDO_VERSION );
	}

	wp_enqueue_script( 'gameindo-main', GAMEINDO_URI . '/js/theme.js', array(), GAMEINDO_VERSION, true );
	wp_localize_script( 'gameindo-main', 'GameIndoData', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'gameindo_newsletter' ),
	) );
}
add_action( 'wp_enqueue_scripts', 'gameindo_assets' );

/**
 * Body classes: add the active pillar so [data-pillar] theming and any
 * body-scoped rules line up with the original pages.
 */
function gameindo_body_class( $classes ) {
	$classes[] = 'gi-body';
	return $classes;
}
add_filter( 'body_class', 'gameindo_body_class' );

/**
 * Put the active pillar on <body data-pillar="…"> for archive/author/single
 * pages, mirroring the original static markup.
 */
function gameindo_body_pillar_attr() {
	$pillar = '';

	if ( is_singular( 'post' ) ) {
		$pillar = gameindo_get_pillar( get_the_ID() );
	} elseif ( is_category() ) {
		$cat = get_queried_object();
		if ( $cat && isset( $cat->slug ) && array_key_exists( $cat->slug, gameindo_pillars() ) ) {
			$pillar = $cat->slug;
		}
	} elseif ( is_author() ) {
		$pillar = 'esports'; // author masthead uses the night/violet treatment
	}

	return $pillar ? ' data-pillar="' . esc_attr( $pillar ) . '"' : '';
}

/**
 * Trim auto excerpts to a card-friendly length and drop the […] marker.
 */
function gameindo_excerpt_length() {
	return 24;
}
add_filter( 'excerpt_length', 'gameindo_excerpt_length' );

function gameindo_excerpt_more() {
	return '…';
}
add_filter( 'excerpt_more', 'gameindo_excerpt_more' );

/**
 * Newsletter opt-in handler (AJAX). Stores nothing by default — this is the
 * hook point to connect Mailchimp/Sendinblue/etc. Returns a friendly message.
 */
function gameindo_newsletter_submit() {
	check_ajax_referer( 'gameindo_newsletter', 'nonce' );
	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => 'Email tidak valid.' ) );
	}
	/**
	 * Fire so integrations can subscribe the address.
	 *
	 * @param string $email Subscriber email.
	 */
	do_action( 'gameindo_newsletter_subscribe', $email );
	wp_send_json_success( array( 'message' => 'Terima kasih! Cek inbox kamu untuk konfirmasi.' ) );
}
add_action( 'wp_ajax_gameindo_newsletter', 'gameindo_newsletter_submit' );
add_action( 'wp_ajax_nopriv_gameindo_newsletter', 'gameindo_newsletter_submit' );

/**
 * Use the search query var `q` as an alias for WP's `s`, so the original
 * /?s= search bar and any legacy links using ?q= both work.
 */
function gameindo_q_alias( $query ) {
	if ( ! is_admin() && $query->is_main_query() && isset( $_GET['q'] ) && ! isset( $_GET['s'] ) ) {
		$query->set( 's', sanitize_text_field( wp_unslash( $_GET['q'] ) ) );
		$query->is_search = true;
		$query->is_home   = false;
	}
}
add_action( 'pre_get_posts', 'gameindo_q_alias' );
