<?php
/**
 * Data accessors the theme consumes for the esports widgets. Each returns the
 * same shape the theme's JSON fixtures used, so the theme renders identically
 * whether the data comes from here (live, editable) or the bundled fallback.
 *
 * @package GameIndo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Live ticker items: [ { id, text, url } ].
 */
function gameindo_core_get_ticker() {
	$posts = get_posts( array(
		'post_type'      => 'gi_ticker',
		'post_status'    => 'publish',
		'posts_per_page' => 30,
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
	) );
	$out = array();
	foreach ( $posts as $p ) {
		$out[] = array(
			'id'   => $p->ID,
			'text' => get_the_title( $p ),
			'url'  => get_post_meta( $p->ID, '_gi_url', true ),
		);
	}
	return $out;
}

/**
 * Hot topics: [ { id, label, query } ].
 */
function gameindo_core_get_topics() {
	$posts = get_posts( array(
		'post_type'      => 'gi_topic',
		'post_status'    => 'publish',
		'posts_per_page' => 20,
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
	) );
	$out = array();
	foreach ( $posts as $p ) {
		$out[] = array(
			'id'    => $p->ID,
			'label' => get_the_title( $p ),
			'query' => get_post_meta( $p->ID, '_gi_query', true ),
		);
	}
	return $out;
}

/**
 * Match center: { competition, matches: [ { id, status, status_label,
 * team_a, score_a, team_b, score_b } ] }. Competition heading comes from the
 * first match's competition.
 */
function gameindo_core_get_matches() {
	$posts = get_posts( array(
		'post_type'      => 'gi_match',
		'post_status'    => 'publish',
		'posts_per_page' => 20,
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
	) );
	$matches = array();
	$competition = '';
	foreach ( $posts as $p ) {
		$comp = get_post_meta( $p->ID, '_gi_competition', true );
		if ( '' === $competition ) {
			$competition = $comp;
		}
		$sa = get_post_meta( $p->ID, '_gi_score_a', true );
		$sb = get_post_meta( $p->ID, '_gi_score_b', true );
		$matches[] = array(
			'id'           => $p->ID,
			'status'       => get_post_meta( $p->ID, '_gi_status', true ),
			'status_label' => get_post_meta( $p->ID, '_gi_status_label', true ),
			'team_a'       => get_post_meta( $p->ID, '_gi_team_a', true ),
			'score_a'      => ( '' === $sa ? null : (int) $sa ),
			'team_b'       => get_post_meta( $p->ID, '_gi_team_b', true ),
			'score_b'      => ( '' === $sb ? null : (int) $sb ),
		);
	}
	return array( 'competition' => $competition, 'matches' => $matches );
}

/**
 * Standings: { competition, season_label, rows: [ { rank, team, wl, pts,
 * top } ] }. Heading/season come from the first row; rows are sorted by rank.
 */
function gameindo_core_get_standings() {
	$posts = get_posts( array(
		'post_type'      => 'gi_standing',
		'post_status'    => 'publish',
		'posts_per_page' => 40,
		'meta_key'       => '_gi_rank',
		'orderby'        => 'meta_value_num',
		'order'          => 'ASC',
	) );
	$rows = array();
	$competition = '';
	$season = '';
	foreach ( $posts as $p ) {
		if ( '' === $competition ) {
			$competition = get_post_meta( $p->ID, '_gi_competition', true );
			$season      = get_post_meta( $p->ID, '_gi_season_label', true );
		}
		$rows[] = array(
			'rank' => (int) get_post_meta( $p->ID, '_gi_rank', true ),
			'team' => get_the_title( $p ),
			'wl'   => get_post_meta( $p->ID, '_gi_wl', true ),
			'pts'  => (int) get_post_meta( $p->ID, '_gi_pts', true ),
			'top'  => (bool) get_post_meta( $p->ID, '_gi_top', true ),
		);
	}
	return array( 'competition' => $competition, 'season_label' => $season, 'rows' => $rows );
}
