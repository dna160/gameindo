<?php
/**
 * Article meta box: the GameIndo-specific fields on standard Posts.
 *
 * @package GameIndo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the meta box.
 */
function gameindo_core_add_post_metabox() {
	add_meta_box(
		'gameindo_article_meta',
		__( 'GameIndo — Meta Artikel', 'gameindo-core' ),
		'gameindo_core_render_post_metabox',
		'post',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes', 'gameindo_core_add_post_metabox' );

/**
 * Render the meta box UI.
 */
function gameindo_core_render_post_metabox( $post ) {
	wp_nonce_field( 'gameindo_article_meta', 'gameindo_article_meta_nonce' );

	$pillars = array(
		''              => '— Ikuti kategori —',
		'home'          => 'Video Game',
		'esports'       => 'Esports',
		'streamer'      => 'Streamer',
		'tech'          => 'Tech',
		'entertainment' => 'Entertainment',
	);
	$pillar      = get_post_meta( $post->ID, '_gi_pillar', true );
	$subcategory = get_post_meta( $post->ID, '_gi_subcategory', true );
	$read_time   = get_post_meta( $post->ID, '_gi_read_time', true );
	$reads       = get_post_meta( $post->ID, '_gi_reads', true );
	$featured    = get_post_meta( $post->ID, '_gi_featured', true );
	$spotlight   = get_post_meta( $post->ID, '_gi_spotlight', true );

	echo '<p><label for="gi_pillar"><strong>Pilar</strong></label><br>';
	echo '<select name="gi_pillar" id="gi_pillar" style="width:100%">';
	foreach ( $pillars as $val => $label ) {
		echo '<option value="' . esc_attr( $val ) . '"' . selected( $pillar, $val, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select>';
	echo '<span style="color:#666;font-size:11px">Biasanya cukup lewat kategori. Isi ini untuk memaksa pilar tertentu.</span></p>';

	echo '<p><label for="gi_subcategory"><strong>Subkategori (label pill)</strong></label><br>';
	echo '<input type="text" name="gi_subcategory" id="gi_subcategory" value="' . esc_attr( $subcategory ) . '" style="width:100%" placeholder="mis. MPL ID, Hardware, Anime"></p>';

	echo '<p><label for="gi_read_time"><strong>Waktu baca</strong></label><br>';
	echo '<input type="text" name="gi_read_time" id="gi_read_time" value="' . esc_attr( $read_time ) . '" style="width:100%" placeholder="mis. 4 min read (kosong = otomatis)"></p>';

	echo '<p><label for="gi_reads"><strong>Jumlah dibaca (popularitas)</strong></label><br>';
	echo '<input type="text" name="gi_reads" id="gi_reads" value="' . esc_attr( $reads ) . '" style="width:100%" placeholder="mis. 128 rb (boleh dikosongkan)">';
	echo '<span class="description">Opsional. Rail <em>Terpopuler</em> menggabungkan angka ini dengan tanggal terbit, jadi artikel baru tetap muncul walau kolom ini kosong.</span></p>';

	echo '<p><label><input type="checkbox" name="gi_featured" value="1"' . checked( $featured, '1', false ) . '> <strong>Featured</strong> (hero utama home)</label></p>';
	echo '<p><label><input type="checkbox" name="gi_spotlight" value="1"' . checked( $spotlight, '1', false ) . '> <strong>Spotlight</strong> (rail trending home)</label></p>';
}

/**
 * Save handler.
 */
function gameindo_core_save_post_meta( $post_id ) {
	if ( ! isset( $_POST['gameindo_article_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gameindo_article_meta_nonce'] ) ), 'gameindo_article_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$text_fields = array( 'gi_pillar', 'gi_subcategory', 'gi_read_time', 'gi_reads' );
	foreach ( $text_fields as $field ) {
		$key = '_' . $field;
		if ( isset( $_POST[ $field ] ) ) {
			update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
		}
	}

	// Checkboxes.
	update_post_meta( $post_id, '_gi_featured', isset( $_POST['gi_featured'] ) ? '1' : '' );
	update_post_meta( $post_id, '_gi_spotlight', isset( $_POST['gi_spotlight'] ) ? '1' : '' );

	// Keep a numeric mirror of reads for potential sorting.
	if ( isset( $_POST['gi_reads'] ) ) {
		$reads = sanitize_text_field( wp_unslash( $_POST['gi_reads'] ) );
		$num   = preg_match( '/\d+/', $reads, $m ) ? (int) $m[0] : 0;
		update_post_meta( $post_id, '_gi_reads_num', $num );
	}
}
add_action( 'save_post_post', 'gameindo_core_save_post_meta' );

/**
 * Expose the article meta on the REST API so a headless frontend (or the
 * bundled cms-client.js fallback) can read them under `gi_meta`.
 */
function gameindo_core_register_post_rest_field() {
	register_rest_field( 'post', 'gi_meta', array(
		'get_callback' => function ( $post ) {
			$id = $post['id'];
			return array(
				'pillar'      => get_post_meta( $id, '_gi_pillar', true ),
				'subcategory' => get_post_meta( $id, '_gi_subcategory', true ),
				'read_time'   => get_post_meta( $id, '_gi_read_time', true ),
				'reads'       => get_post_meta( $id, '_gi_reads', true ),
				'featured'    => (bool) get_post_meta( $id, '_gi_featured', true ),
				'spotlight'   => (bool) get_post_meta( $id, '_gi_spotlight', true ),
				'tags'        => wp_get_post_tags( $id, array( 'fields' => 'names' ) ),
			);
		},
		'schema'       => null,
	) );
}
add_action( 'rest_api_init', 'gameindo_core_register_post_rest_field' );
