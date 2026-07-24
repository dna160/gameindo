<?php
/**
 * Custom post types for the editable esports widgets.
 *
 * @package GameIndo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the four widget CPTs. All are non-public (admin-managed data that
 * the theme renders into its own widgets), page-attributes enabled so editors
 * can drag to reorder.
 */
function gameindo_core_register_cpts() {

	register_post_type( 'gi_ticker', array(
		'labels'          => gameindo_core_cpt_labels( 'Live Ticker', 'Item Ticker' ),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'menu_icon'       => 'dashicons-megaphone',
		'menu_position'   => 26,
		'supports'        => array( 'title', 'page-attributes' ),
		'capability_type' => 'post',
	) );

	register_post_type( 'gi_topic', array(
		'labels'          => gameindo_core_cpt_labels( 'Topik Hangat', 'Topik' ),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'menu_icon'       => 'dashicons-tag',
		'menu_position'   => 27,
		'supports'        => array( 'title', 'page-attributes' ),
		'capability_type' => 'post',
	) );

	register_post_type( 'gi_match', array(
		'labels'          => gameindo_core_cpt_labels( 'Match Center', 'Match' ),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'menu_icon'       => 'dashicons-controller',
		'menu_position'   => 28,
		'supports'        => array( 'title', 'page-attributes' ),
		'capability_type' => 'post',
	) );

	register_post_type( 'gi_standing', array(
		'labels'          => gameindo_core_cpt_labels( 'Klasemen', 'Baris Klasemen' ),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'menu_icon'       => 'dashicons-list-view',
		'menu_position'   => 29,
		'supports'        => array( 'title', 'page-attributes' ),
		'capability_type' => 'post',
	) );
}
add_action( 'init', 'gameindo_core_register_cpts' );

/**
 * Build a labels array for a CPT.
 */
function gameindo_core_cpt_labels( $plural, $singular ) {
	return array(
		'name'               => $plural,
		'singular_name'      => $singular,
		'add_new'            => 'Tambah Baru',
		'add_new_item'       => 'Tambah ' . $singular,
		'edit_item'          => 'Edit ' . $singular,
		'new_item'           => $singular . ' Baru',
		'view_item'          => 'Lihat ' . $singular,
		'search_items'       => 'Cari ' . $plural,
		'not_found'          => 'Tidak ada ' . $plural,
		'not_found_in_trash' => 'Tidak ada ' . $plural . ' di sampah',
		'all_items'          => $plural,
		'menu_name'          => $plural,
	);
}

/**
 * Default ordering in admin lists: by menu_order (drag-to-sort) then title.
 */
function gameindo_core_admin_order( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}
	$pt = $query->get( 'post_type' );
	if ( in_array( $pt, array( 'gi_ticker', 'gi_topic', 'gi_match', 'gi_standing' ), true ) ) {
		$query->set( 'orderby', array( 'menu_order' => 'ASC', 'date' => 'DESC' ) );
	}
}
add_action( 'pre_get_posts', 'gameindo_core_admin_order' );
