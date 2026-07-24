<?php
/**
 * Author profile fields shown on the GameIndo author masthead.
 *
 * @package GameIndo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The extra profile fields and their labels.
 */
function gameindo_core_profile_fields() {
	return array(
		'gi_role'          => array( 'Peran / Jabatan', 'mis. Editor Esports' ),
		'gi_articles_count'=> array( 'Jumlah Artikel (tampilan)', 'mis. 1248 — kosong = hitung otomatis' ),
		'gi_since_year'    => array( 'Sejak Tahun', 'mis. 2019' ),
		'gi_monthly_reads' => array( 'Dibaca / Bulan (tampilan)', 'mis. 4,2 JT' ),
	);
}

/**
 * Render the fields on the user profile screen.
 */
function gameindo_core_render_profile_fields( $user ) {
	echo '<h2>GameIndo — Profil Penulis</h2>';
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( gameindo_core_profile_fields() as $key => $meta ) {
		$val = get_user_meta( $user->ID, $key, true );
		echo '<tr><th><label for="' . esc_attr( $key ) . '">' . esc_html( $meta[0] ) . '</label></th><td>';
		echo '<input type="text" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '" class="regular-text">';
		echo '<p class="description">' . esc_html( $meta[1] ) . '</p>';
		echo '</td></tr>';
	}
	echo '</tbody></table>';
}
add_action( 'show_user_profile', 'gameindo_core_render_profile_fields' );
add_action( 'edit_user_profile', 'gameindo_core_render_profile_fields' );

/**
 * Save the fields.
 */
function gameindo_core_save_profile_fields( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}
	foreach ( array_keys( gameindo_core_profile_fields() ) as $key ) {
		if ( isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WP core verifies the profile-update nonce before this hook.
			update_user_meta( $user_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
		}
	}
}
add_action( 'personal_options_update', 'gameindo_core_save_profile_fields' );
add_action( 'edit_user_profile_update', 'gameindo_core_save_profile_fields' );

/**
 * Expose profile fields on the REST users endpoint under `gi_profile`.
 */
function gameindo_core_register_user_rest_field() {
	register_rest_field( 'user', 'gi_profile', array(
		'get_callback' => function ( $user ) {
			$id = $user['id'];
			return array(
				'role'          => get_user_meta( $id, 'gi_role', true ),
				'articles_count'=> (int) get_user_meta( $id, 'gi_articles_count', true ),
				'since_year'    => get_user_meta( $id, 'gi_since_year', true ),
				'monthly_reads' => get_user_meta( $id, 'gi_monthly_reads', true ),
			);
		},
		'schema'       => null,
	) );
}
add_action( 'rest_api_init', 'gameindo_core_register_user_rest_field' );
