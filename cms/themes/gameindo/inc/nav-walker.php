<?php
/**
 * Navigation: a flat-anchor walker that reproduces the original pillar nav
 * markup (`<a class="gi-pillarnav__item" data-pillar="…">`) while still being
 * fully driven by a WordPress menu, plus fallbacks that auto-build the nav
 * from the five pillar categories when no menu is assigned.
 *
 * @package GameIndo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Flat walker: outputs bare <a> elements (no <ul>/<li>) so the anchors sit as
 * direct flex children of .gi-pillarnav, matching the static design. Adds
 * data-pillar for any menu item that points at a pillar category.
 */
class Gameindo_Flat_Nav_Walker extends Walker_Nav_Menu {

	/** @var string CSS class applied to each anchor. */
	public $link_class = 'gi-pillarnav__item';

	public function __construct( $link_class = 'gi-pillarnav__item' ) {
		$this->link_class = $link_class;
	}

	public function start_lvl( &$output, $depth = 0, $args = null ) {}
	public function end_lvl( &$output, $depth = 0, $args = null ) {}
	public function end_el( &$output, $item, $depth = 0, $args = null ) {}

	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$pillar = '';
		if ( 'category' === $item->object ) {
			$term = get_term( (int) $item->object_id );
			if ( $term && ! is_wp_error( $term ) && array_key_exists( $term->slug, gameindo_pillars() ) ) {
				$pillar = $term->slug;
			}
		}
		if ( ! $pillar ) {
			$slug = sanitize_title( $item->title );
			if ( array_key_exists( $slug, gameindo_pillars() ) ) {
				$pillar = $slug;
			}
		}

		$classes = (array) $item->classes;
		$current = in_array( 'current-menu-item', $classes, true )
			|| in_array( 'current-menu-parent', $classes, true )
			|| in_array( 'current-category-ancestor', $classes, true );

		$attrs  = ' class="' . esc_attr( $this->link_class ) . '"';
		$attrs .= $pillar ? ' data-pillar="' . esc_attr( $pillar ) . '"' : '';
		$attrs .= ' href="' . esc_url( $item->url ) . '"';
		$attrs .= $current ? ' aria-current="page"' : '';

		$output .= '<a' . $attrs . '>' . esc_html( $item->title ) . '</a>';
	}
}

/**
 * Render the header pillar nav. Uses the 'primary' menu if assigned; otherwise
 * auto-builds it from the pillar categories.
 */
function gameindo_pillar_nav() {
	if ( has_nav_menu( 'primary' ) ) {
		wp_nav_menu( array(
			'theme_location' => 'primary',
			'container'      => false,
			'items_wrap'     => '%3$s',
			'walker'         => new Gameindo_Flat_Nav_Walker( 'gi-pillarnav__item' ),
			'fallback_cb'    => 'gameindo_pillar_nav_fallback',
			'depth'          => 1,
		) );
		return;
	}
	gameindo_pillar_nav_fallback();
}

/**
 * Auto-built pillar nav (Home + the five pillar categories).
 */
function gameindo_pillar_nav_fallback() {
	$current = '';
	if ( is_category() ) {
		$obj = get_queried_object();
		$current = $obj ? $obj->slug : '';
	} elseif ( is_front_page() ) {
		$current = 'home';
	}

	echo '<a class="gi-pillarnav__item" data-pillar="home" href="' . esc_url( home_url( '/' ) ) . '"' . ( 'home' === $current || is_front_page() ? ' aria-current="page"' : '' ) . '>Home</a>';

	foreach ( gameindo_pillars() as $slug => $name ) {
		if ( 'home' === $slug ) {
			continue;
		}
		$term = get_category_by_slug( $slug );
		$url  = $term ? get_category_link( $term->term_id ) : home_url( '/category/' . $slug . '/' );
		echo '<a class="gi-pillarnav__item" data-pillar="' . esc_attr( $slug ) . '" href="' . esc_url( $url ) . '"' . ( $current === $slug ? ' aria-current="page"' : '' ) . '>' . esc_html( $name ) . '</a>';
	}
}

/**
 * Render a simple flat list of anchors for a menu location (footer / drawer),
 * with a pillar-category fallback.
 */
function gameindo_flat_menu( $location, $link_class = '' ) {
	if ( has_nav_menu( $location ) ) {
		wp_nav_menu( array(
			'theme_location' => $location,
			'container'      => false,
			'items_wrap'     => '%3$s',
			'walker'         => new Gameindo_Flat_Nav_Walker( $link_class ),
			'fallback_cb'    => false,
			'depth'          => 1,
		) );
		return;
	}

	// Fallback: pillar links.
	foreach ( gameindo_pillars() as $slug => $name ) {
		$term = get_category_by_slug( $slug );
		$url  = ( 'home' === $slug ) ? home_url( '/' ) : ( $term ? get_category_link( $term->term_id ) : home_url( '/category/' . $slug . '/' ) );
		$cls  = $link_class ? ' class="' . esc_attr( $link_class ) . '"' : '';
		echo '<a' . $cls . ' href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>';
	}
}
