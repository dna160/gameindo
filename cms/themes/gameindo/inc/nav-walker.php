<?php
/**
 * Navigation: a flat-anchor walker that reproduces the original pillar nav
 * markup (`<a class="gi-pillarnav__item" data-pillar="…">`) while still being
 * fully driven by a WordPress menu, plus fallbacks that auto-build the nav
 * from the pillar categories when no menu is assigned.
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
		// Title fallback, so a custom link titled "Esports" still gets its
		// colour. 'home' is excluded: an item titled "Home" is the front page,
		// not the site's game pillar, and treating it as one would both colour
		// it as Video Games and make the nav look like it already carries that
		// section (see gameindo_menu_with_pillars()).
		if ( ! $pillar ) {
			$slug = sanitize_title( $item->title );
			if ( 'home' !== $slug && array_key_exists( $slug, gameindo_pillars() ) ) {
				$pillar = $slug;
			}
		}

		$pillar = $pillar ? gameindo_canonical_pillar( $pillar ) : '';

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
 * Pillars that have to appear in the nav even when the site's WP menus were
 * hand-built before the pillar existed.
 *
 * A menu assigned in Tampilan → Menu wins over the auto-built nav, so a pillar
 * added by a theme update would otherwise be invisible on exactly the sites
 * that follow the install guide — the editor would have to know to go and add
 * it. Appending is done at render time and writes nothing: the moment the
 * pillar is placed in the menu properly, this stops adding it and the editor's
 * ordering takes over. Filter to an empty array to opt out entirely.
 */
function gameindo_required_nav_pillars() {
	return (array) apply_filters( 'gameindo_required_nav_pillars', array( 'video-games' ) );
}

/**
 * A rendered menu with any required pillar it doesn't already link to folded
 * in. Presence is matched on the data-pillar attribute the walker emits, so a
 * category item, a custom link titled "Video Games" and a renamed entry all
 * count as already there.
 *
 * The additions go in front of the first pillar entry rather than at the end:
 * that puts them among the sections, in the same order as the auto-built nav,
 * instead of after trailing entries like the drawer's "Cari".
 */
function gameindo_menu_with_pillars( $menu_html, $link_class ) {
	$add = '';
	foreach ( gameindo_required_nav_pillars() as $slug ) {
		if ( false !== strpos( $menu_html, 'data-pillar="' . $slug . '"' ) ) {
			continue;
		}
		$obj     = is_category() ? get_queried_object() : null;
		$current = ( $obj && isset( $obj->slug ) && gameindo_canonical_pillar( $obj->slug ) === $slug );
		$cls     = $link_class ? ' class="' . esc_attr( $link_class ) . '"' : '';
		$add    .= '<a' . $cls . ' data-pillar="' . esc_attr( $slug ) . '" href="' . esc_url( gameindo_pillar_url( $slug ) ) . '"'
			. ( $current ? ' aria-current="page"' : '' ) . '>' . esc_html( gameindo_pillar_name( $slug ) ) . '</a>';
	}
	if ( '' === $add ) {
		return $menu_html;
	}

	if ( preg_match( '#<a\b[^>]*\sdata-pillar=#', $menu_html, $m, PREG_OFFSET_CAPTURE ) ) {
		$at = (int) $m[0][1];
		return substr( $menu_html, 0, $at ) . $add . substr( $menu_html, $at );
	}
	return $menu_html . $add;
}

/**
 * Render the header pillar nav. Uses the 'primary' menu if assigned; otherwise
 * auto-builds it from the pillar categories.
 */
function gameindo_pillar_nav() {
	if ( has_nav_menu( 'primary' ) ) {
		$html = wp_nav_menu( array(
			'theme_location' => 'primary',
			'container'      => false,
			'items_wrap'     => '%3$s',
			'walker'         => new Gameindo_Flat_Nav_Walker( 'gi-pillarnav__item' ),
			'fallback_cb'    => false,
			'depth'          => 1,
			'echo'           => false,
		) );
		if ( $html ) {
			echo gameindo_menu_with_pillars( $html, 'gi-pillarnav__item' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
	}
	gameindo_pillar_nav_fallback();
}

/**
 * Auto-built pillar nav: the front page, then every pillar section.
 *
 * The "Home" entry is the front page, not a pillar — which is why the site's
 * game coverage now has its own Video Games entry beside it. Both the
 * video-games slug and the legacy 'home' slug mark that entry current, since
 * either category archive is the same section to a reader.
 */
function gameindo_pillar_nav_fallback() {
	$current = '';
	if ( is_category() ) {
		$obj     = get_queried_object();
		$current = $obj ? gameindo_canonical_pillar( $obj->slug ) : '';
	}

	echo '<a class="gi-pillarnav__item" data-pillar="home" href="' . esc_url( home_url( '/' ) ) . '"' . ( is_front_page() ? ' aria-current="page"' : '' ) . '>Home</a>';

	foreach ( gameindo_nav_pillars() as $slug => $name ) {
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
		$html = wp_nav_menu( array(
			'theme_location' => $location,
			'container'      => false,
			'items_wrap'     => '%3$s',
			'walker'         => new Gameindo_Flat_Nav_Walker( $link_class ),
			'fallback_cb'    => false,
			'depth'          => 1,
			'echo'           => false,
		) );
		if ( $html ) {
			echo gameindo_menu_with_pillars( $html, $link_class ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
	}

	// Fallback: pillar links.
	foreach ( gameindo_nav_pillars() as $slug => $name ) {
		$cls = $link_class ? ' class="' . esc_attr( $link_class ) . '"' : '';
		echo '<a' . $cls . ' href="' . esc_url( gameindo_pillar_url( $slug ) ) . '">' . esc_html( $name ) . '</a>';
	}
}
