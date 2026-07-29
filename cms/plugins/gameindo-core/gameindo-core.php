<?php
/**
 * Plugin Name:       GameIndo Core
 * Plugin URI:        https://gameindo.com
 * Description:        Content model for the GameIndo theme — article meta (pillar, subcategory, read time, featured/spotlight, reads), author profile fields, and the editable esports widgets (live ticker, hot topics, match center, standings). All manageable from wp-admin.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            GameIndo
 * License:           GPL-2.0-or-later
 * Text Domain:       gameindo-core
 *
 * @package GameIndo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GAMEINDO_CORE_VERSION', '1.0.0' );
define( 'GAMEINDO_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'GAMEINDO_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once GAMEINDO_CORE_DIR . 'includes/cpt.php';
require_once GAMEINDO_CORE_DIR . 'includes/post-meta.php';
require_once GAMEINDO_CORE_DIR . 'includes/user-meta.php';
require_once GAMEINDO_CORE_DIR . 'includes/esports-meta.php';
require_once GAMEINDO_CORE_DIR . 'includes/helpers.php';
require_once GAMEINDO_CORE_DIR . 'includes/rest.php';

/**
 * On activation: register CPTs then flush rewrite rules, and make sure the
 * five pillar categories exist with the exact slugs the theme/CSS expect.
 */
function gameindo_core_activate() {
	gameindo_core_register_cpts();
	gameindo_core_ensure_pillars();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'gameindo_core_activate' );

function gameindo_core_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'gameindo_core_deactivate' );

/**
 * Ensure the five pillar categories exist (idempotent). Slugs are fixed;
 * names can be edited freely in wp-admin afterwards.
 */
function gameindo_core_ensure_pillars() {
	$pillars = array(
		'home'          => 'Video Game',
		'esports'       => 'Esports',
		'streamer'      => 'Streamer',
		'tech'          => 'Tech',
		'entertainment' => 'Entertainment',
	);
	foreach ( $pillars as $slug => $name ) {
		if ( ! term_exists( $slug, 'category' ) ) {
			wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
		}
	}
}

/**
 * Small admin grouping menu so the esports widgets are easy to find.
 */
function gameindo_core_admin_menu() {
	add_menu_page(
		__( 'GameIndo', 'gameindo-core' ),
		__( 'GameIndo', 'gameindo-core' ),
		'edit_posts',
		'gameindo',
		'gameindo_core_dashboard_page',
		'dashicons-games',
		25
	);
}
add_action( 'admin_menu', 'gameindo_core_admin_menu' );

function gameindo_core_dashboard_page() {
	echo '<div class="wrap"><h1>GameIndo Core</h1>';
	echo '<p>Kelola konten dinamis GameIndo dari sini:</p><ul style="list-style:disc;margin-left:20px">';
	echo '<li><a href="' . esc_url( admin_url( 'edit.php?post_type=gi_ticker' ) ) . '">Live Ticker</a> — <em>tidak lagi dipakai.</em> Teks berjalan di atas header kini terisi otomatis dari 12 artikel terbaru, jadi item di menu ini tidak muncul di situs.</li>';
	echo '<li><a href="' . esc_url( admin_url( 'edit.php?post_type=gi_topic' ) ) . '">Topik Hangat</a> — chip topik di bawah header home.</li>';
	echo '<li><a href="' . esc_url( admin_url( 'edit.php?post_type=gi_match' ) ) . '">Match Center</a> — jadwal & skor pertandingan.</li>';
	echo '<li><a href="' . esc_url( admin_url( 'edit.php?post_type=gi_standing' ) ) . '">Klasemen</a> — baris klasemen tim.</li>';
	echo '</ul>';
	echo '<p>Artikel biasa dikelola di menu <strong>Pos</strong>; setiap artikel punya panel <em>GameIndo — Meta Artikel</em> untuk pilar, subkategori, unggulan, dsb.</p>';
	echo '</div>';
}
