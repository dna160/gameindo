<?php
/**
 * GameIndo theme bootstrap.
 *
 * @package GameIndo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GAMEINDO_VERSION', '1.7.1' );
define( 'GAMEINDO_DIR', get_template_directory() );
define( 'GAMEINDO_URI', get_template_directory_uri() );

/**
 * Every pillar slug the site knows about. The slugs MUST match the WordPress
 * category slugs and the [data-pillar] scopes in assets/css/tokens/colors.css.
 * Renaming a pillar is done in wp-admin (category name); slugs stay fixed.
 *
 * 'home' is kept for the archive of posts filed before the Video Games pillar
 * existed. It is not a section of its own any more — everything user-facing
 * resolves it to 'video-games' (see gameindo_canonical_pillar()), so the site's
 * game coverage reads as one pillar however an old post happens to be filed.
 */
function gameindo_pillars() {
	return array(
		'home'          => 'Video Game',
		'video-games'   => 'Video Games',
		'esports'       => 'Esports',
		'streamer'      => 'Streamer',
		'tech'          => 'Tech',
		'entertainment' => 'Entertainment',
	);
}

/**
 * The pillars that are sections in their own right: what the header nav, the
 * footer, the mega menu, the homepage tiles and the pillar bands list.
 *
 * Excludes the legacy 'home' slug, which never had a nav entry of its own (the
 * header's "Home" link goes to the front page) and whose articles now appear
 * under Video Games — listing both would file one beat under two headings.
 */
function gameindo_nav_pillars() {
	$pillars = gameindo_pillars();
	unset( $pillars['home'] );
	return $pillars;
}

/**
 * Resolve a pillar slug to the one the reader sees. Only 'home' moves.
 */
function gameindo_canonical_pillar( $slug ) {
	return ( 'home' === $slug ) ? 'video-games' : $slug;
}

/**
 * Make sure every pillar has its category, so a pillar's nav entry always
 * lands on a real archive.
 *
 * The Core plugin does this too, but only on activation — a pillar introduced
 * in a theme update arrives on sites where the plugin was activated long ago,
 * and there its link would 404. Guarded by an option so it costs one autoloaded
 * read per request rather than a term lookup.
 */
function gameindo_ensure_pillar_terms() {
	if ( GAMEINDO_VERSION === get_option( 'gameindo_pillar_terms' ) ) {
		return;
	}
	foreach ( gameindo_pillars() as $slug => $name ) {
		if ( ! term_exists( $slug, 'category' ) ) {
			wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
		}
	}
	// The mega menu caches its columns as markup, so without this a new pillar
	// would be missing from it for up to the cache's ten minutes after an update.
	gameindo_flush_hot_topics();
	update_option( 'gameindo_pillar_terms', GAMEINDO_VERSION );
}
add_action( 'init', 'gameindo_ensure_pillar_terms' );

/**
 * Put the Video Games entry into the site's own menus, once.
 *
 * The nav renders it either way — gameindo_menu_with_pillars() folds a missing
 * pillar into an assigned menu at render time — but that entry is generated,
 * so it cannot be reordered, renamed or unpublished from Tampilan → Menu. This
 * turns it into a real menu item the editor owns. It runs a single time, so
 * removing the item afterwards makes it stay removed.
 */
function gameindo_seed_pillar_menu_items() {
	if ( GAMEINDO_VERSION === get_option( 'gameindo_menu_seed' ) ) {
		return;
	}
	// Runs after gameindo_ensure_pillar_terms() on the same hook; if the term
	// still isn't there, leave the flag unset and try again next request.
	if ( ! get_category_by_slug( 'video-games' ) ) {
		return;
	}
	foreach ( array( 'primary', 'footer', 'drawer' ) as $location ) {
		gameindo_seed_menu_pillar( $location, 'video-games' );
	}
	update_option( 'gameindo_menu_seed', GAMEINDO_VERSION );
}
add_action( 'init', 'gameindo_seed_pillar_menu_items', 11 );

/**
 * Which pillar a menu item stands for, or '' for anything else. Same reading
 * as Gameindo_Flat_Nav_Walker, including why a custom link titled "Home" is
 * not the game pillar: it is the front page.
 */
function gameindo_menu_item_pillar( $item ) {
	if ( 'taxonomy' === $item->type && 'category' === $item->object ) {
		$term = get_term( (int) $item->object_id );
		if ( $term && ! is_wp_error( $term ) && array_key_exists( $term->slug, gameindo_pillars() ) ) {
			return $term->slug;
		}
	}
	if ( 'custom' === $item->type ) {
		$slug = sanitize_title( $item->title );
		if ( 'home' !== $slug && array_key_exists( $slug, gameindo_pillars() ) ) {
			return $slug;
		}
	}
	return '';
}

/**
 * Add one pillar to one menu location, in the right place and only if it isn't
 * already there.
 */
function gameindo_seed_menu_pillar( $location, $slug ) {
	$locations = get_nav_menu_locations();
	if ( empty( $locations[ $location ] ) ) {
		return; // nothing assigned here — the auto-built nav already lists the pillar
	}
	$menu_id = (int) $locations[ $location ];
	$items   = wp_get_nav_menu_items( $menu_id );
	$term    = get_category_by_slug( $slug );
	if ( ! is_array( $items ) || ! $term ) {
		return;
	}

	$legacy   = null;
	$position = null;
	foreach ( $items as $item ) {
		$pillar = gameindo_menu_item_pillar( $item );
		if ( $slug === $pillar ) {
			return; // already in this menu
		}
		if ( 'home' === $pillar && null === $legacy ) {
			$legacy = $item;
		}
		if ( $pillar && null === $position ) {
			$position = (int) $item->menu_order;
		}
	}

	// A menu carrying the legacy "Video Game" category points at this same
	// section under the old slug. Repoint that item instead of adding a second
	// entry, which would file one beat under two names.
	if ( $legacy ) {
		gameindo_retarget_menu_item( $menu_id, $legacy, $term );
		return;
	}

	// Otherwise slot it in front of the first pillar entry — with the sections,
	// rather than after trailing items like the drawer's "Cari".
	if ( null === $position ) {
		$position = count( $items ) + 1;
	}
	foreach ( $items as $item ) {
		if ( (int) $item->menu_order >= $position ) {
			wp_update_post( array(
				'ID'         => (int) $item->db_id,
				'menu_order' => (int) $item->menu_order + 1,
			) );
		}
	}

	wp_update_nav_menu_item( $menu_id, 0, array(
		'menu-item-object-id' => (int) $term->term_id,
		'menu-item-object'    => 'category',
		'menu-item-type'      => 'taxonomy',
		'menu-item-title'     => $term->name,
		'menu-item-status'    => 'publish',
		'menu-item-position'  => $position,
	) );
}

/**
 * Point an existing menu item at a different category, keeping everything else
 * about it. Every field has to be passed back: wp_update_nav_menu_item()
 * rewrites the item from the arguments given, so anything omitted — the
 * item's parent, its CSS classes, its link target — would be cleared.
 *
 * The label is only replaced when it is still the old category's name; an
 * editor who typed their own wording keeps it.
 */
function gameindo_retarget_menu_item( $menu_id, $item, $term ) {
	$old   = get_term( (int) $item->object_id );
	$title = ( $old && ! is_wp_error( $old ) && trim( $item->title ) === $old->name ) ? $term->name : $item->title;

	wp_update_nav_menu_item( $menu_id, (int) $item->db_id, array(
		'menu-item-db-id'       => (int) $item->db_id,
		'menu-item-object-id'   => (int) $term->term_id,
		'menu-item-object'      => 'category',
		'menu-item-type'        => 'taxonomy',
		'menu-item-parent-id'   => (int) $item->menu_item_parent,
		'menu-item-position'    => (int) $item->menu_order,
		'menu-item-title'       => $title,
		'menu-item-url'         => '',
		'menu-item-description' => $item->description,
		'menu-item-attr-title'  => $item->attr_title,
		'menu-item-target'      => $item->target,
		'menu-item-classes'     => implode( ' ', (array) $item->classes ),
		'menu-item-xfn'         => $item->xfn,
		'menu-item-status'      => 'publish',
	) );
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
			$pillar = gameindo_canonical_pillar( $cat->slug );
		}
	} elseif ( is_author() ) {
		$pillar = 'esports'; // author masthead uses the night/violet treatment
	}

	return $pillar ? ' data-pillar="' . esc_attr( $pillar ) . '"' : '';
}

/**
 * The hot-topics row is assembled and cached for 15 minutes. An editor pinning
 * a topic expects to see it immediately, so drop the cache on save rather than
 * making them wait out the TTL.
 */
function gameindo_flush_hot_topics() {
	delete_transient( 'gi_hot_topics' );
	delete_transient( 'gi_megamenu_cols' );
}
add_action( 'save_post_gi_topic', 'gameindo_flush_hot_topics' );
add_action( 'save_post_post', 'gameindo_flush_hot_topics' );
add_action( 'trashed_post', 'gameindo_flush_hot_topics' );

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
