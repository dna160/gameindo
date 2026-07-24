<?php
/**
 * Meta boxes for the esports widget CPTs (ticker, topic, match, standing).
 * Config-driven: one schema per post type, one generic renderer + saver.
 *
 * @package GameIndo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field schema per CPT. Each field: key => [ label, type, options|placeholder ].
 * type: text | number | select | checkbox.
 */
function gameindo_core_widget_schema() {
	return array(
		'gi_ticker' => array(
			'title_label' => 'Teks ticker',
			'fields'      => array(
				'url' => array( 'Tautan (URL tujuan)', 'text', 'https://…' ),
			),
		),
		'gi_topic'  => array(
			'title_label' => 'Label topik',
			'fields'      => array(
				'query' => array( 'Kata kunci pencarian', 'text', 'mis. MPL ID S16' ),
			),
		),
		'gi_match'  => array(
			'title_label' => 'Judul internal (mis. ONIC vs BTR)',
			'fields'      => array(
				'competition'  => array( 'Kompetisi', 'text', 'mis. MPL ID S16' ),
				'status'       => array( 'Status', 'select', array( 'scheduled' => 'Terjadwal', 'live' => 'Live', 'finished' => 'Selesai' ) ),
				'status_label' => array( 'Label status', 'text', 'mis. ● LIVE / SELESAI / 19:30' ),
				'team_a'       => array( 'Tim A', 'text', '' ),
				'score_a'      => array( 'Skor A', 'number', 'kosong = belum main' ),
				'team_b'       => array( 'Tim B', 'text', '' ),
				'score_b'      => array( 'Skor B', 'number', 'kosong = belum main' ),
			),
		),
		'gi_standing' => array(
			'title_label' => 'Nama tim',
			'fields'      => array(
				'competition'  => array( 'Kompetisi', 'text', 'mis. Klasemen MPL ID' ),
				'season_label' => array( 'Label musim', 'text', 'mis. S16 · PEKAN 8' ),
				'rank'         => array( 'Peringkat', 'number', '' ),
				'wl'           => array( 'Menang–Kalah', 'text', 'mis. 11–2' ),
				'pts'          => array( 'Poin', 'number', '' ),
				'top'          => array( 'Sorot (baris teratas)', 'checkbox', '' ),
			),
		),
	);
}

/**
 * Register a meta box for each widget CPT.
 */
function gameindo_core_add_widget_metaboxes() {
	foreach ( gameindo_core_widget_schema() as $pt => $conf ) {
		add_meta_box(
			$pt . '_meta',
			__( 'Detail', 'gameindo-core' ),
			'gameindo_core_render_widget_metabox',
			$pt,
			'normal',
			'high'
		);
	}
}
add_action( 'add_meta_boxes', 'gameindo_core_add_widget_metaboxes' );

/**
 * Generic renderer.
 */
function gameindo_core_render_widget_metabox( $post ) {
	$schema = gameindo_core_widget_schema();
	if ( ! isset( $schema[ $post->post_type ] ) ) {
		return;
	}
	$conf = $schema[ $post->post_type ];
	wp_nonce_field( 'gameindo_widget_meta', 'gameindo_widget_meta_nonce' );

	echo '<p style="color:#666">Judul di atas = <strong>' . esc_html( $conf['title_label'] ) . '</strong>.</p>';
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( $conf['fields'] as $key => $field ) {
		list( $label, $type, $extra ) = array( $field[0], $field[1], isset( $field[2] ) ? $field[2] : '' );
		$val = get_post_meta( $post->ID, '_gi_' . $key, true );
		echo '<tr><th style="width:180px"><label for="gi_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		if ( 'select' === $type ) {
			echo '<select name="gi_' . esc_attr( $key ) . '" id="gi_' . esc_attr( $key ) . '">';
			foreach ( (array) $extra as $ov => $ol ) {
				echo '<option value="' . esc_attr( $ov ) . '"' . selected( $val, $ov, false ) . '>' . esc_html( $ol ) . '</option>';
			}
			echo '</select>';
		} elseif ( 'checkbox' === $type ) {
			echo '<label><input type="checkbox" name="gi_' . esc_attr( $key ) . '" value="1"' . checked( $val, '1', false ) . '> Ya</label>';
		} else {
			$input_type = ( 'number' === $type ) ? 'number' : 'text';
			echo '<input type="' . esc_attr( $input_type ) . '" name="gi_' . esc_attr( $key ) . '" id="gi_' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '" class="regular-text" placeholder="' . esc_attr( $extra ) . '">';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
}

/**
 * Generic saver.
 */
function gameindo_core_save_widget_meta( $post_id, $post ) {
	$schema = gameindo_core_widget_schema();
	if ( ! isset( $schema[ $post->post_type ] ) ) {
		return;
	}
	if ( ! isset( $_POST['gameindo_widget_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gameindo_widget_meta_nonce'] ) ), 'gameindo_widget_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	foreach ( $schema[ $post->post_type ]['fields'] as $key => $field ) {
		$type    = $field[1];
		$name    = 'gi_' . $key;
		$meta_key = '_gi_' . $key;
		if ( 'checkbox' === $type ) {
			update_post_meta( $post_id, $meta_key, isset( $_POST[ $name ] ) ? '1' : '' );
		} elseif ( 'number' === $type ) {
			if ( isset( $_POST[ $name ] ) && '' !== $_POST[ $name ] ) {
				update_post_meta( $post_id, $meta_key, (int) $_POST[ $name ] );
			} else {
				delete_post_meta( $post_id, $meta_key );
			}
		} else {
			if ( isset( $_POST[ $name ] ) ) {
				update_post_meta( $post_id, $meta_key, sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) );
			}
		}
	}
}
add_action( 'save_post', 'gameindo_core_save_widget_meta', 10, 2 );

/**
 * Show the key columns in the CPT admin lists for quick scanning.
 */
function gameindo_core_widget_columns( $columns ) {
	$screen = get_current_screen();
	if ( ! $screen ) {
		return $columns;
	}
	if ( 'gi_match' === $screen->post_type ) {
		$columns['gi_teams'] = 'Match';
		$columns['gi_status'] = 'Status';
	} elseif ( 'gi_standing' === $screen->post_type ) {
		$columns['gi_rank'] = 'Rank';
		$columns['gi_pts']  = 'Poin';
	}
	return $columns;
}

function gameindo_core_widget_column_content( $column, $post_id ) {
	if ( 'gi_teams' === $column ) {
		echo esc_html( get_post_meta( $post_id, '_gi_team_a', true ) . ' vs ' . get_post_meta( $post_id, '_gi_team_b', true ) );
	} elseif ( 'gi_status' === $column ) {
		echo esc_html( get_post_meta( $post_id, '_gi_status', true ) );
	} elseif ( 'gi_rank' === $column ) {
		echo esc_html( get_post_meta( $post_id, '_gi_rank', true ) );
	} elseif ( 'gi_pts' === $column ) {
		echo esc_html( get_post_meta( $post_id, '_gi_pts', true ) );
	}
}
add_action( 'manage_gi_match_posts_custom_column', 'gameindo_core_widget_column_content', 10, 2 );
add_action( 'manage_gi_standing_posts_custom_column', 'gameindo_core_widget_column_content', 10, 2 );
add_filter( 'manage_gi_match_posts_columns', 'gameindo_core_widget_columns' );
add_filter( 'manage_gi_standing_posts_columns', 'gameindo_core_widget_columns' );
