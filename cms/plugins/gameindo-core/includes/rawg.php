<?php
/**
 * RAWG — upcoming game releases for the Video Games pillar.
 *
 * One endpoint does the whole job:
 *
 *   GET https://api.rawg.io/api/games
 *       ?key=…&dates=<today>,<today+N days>&ordering=released&page_size=…
 *
 * `dates` is an inclusive range and `ordering=released` sorts by release date,
 * so a window starting today is exactly "what is coming out next". Optional
 * `platforms=<ids>` narrows it to the platforms this pillar covers.
 *
 * The key never reaches the browser — every request is server-side, cached in a
 * transient and served stale-while-revalidate, the same shape the PandaScore
 * schedule uses. No key configured means no panel: the caller falls back.
 *
 * @package GameIndo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GAMEINDO_RAWG_BASE', 'https://api.rawg.io/api' );

/**
 * RAWG platform ids for the four platforms the Video Games pillar filters by.
 * Ids are stable in RAWG's database; the labels come from the theme.
 */
function gameindo_core_rawg_platforms() {
	return apply_filters( 'gameindo_rawg_platforms', array(
		'ps5'    => 187,
		'pc'     => 4,
		'xbox'   => 1,   // Xbox One; Series X/S is 186, added below
		'switch' => 7,
	) );
}

/**
 * API key. A wp-config.php constant wins over the wp-admin setting, so a
 * production key can stay out of the database entirely.
 */
function gameindo_core_rawg_key() {
	if ( defined( 'GAMEINDO_RAWG_KEY' ) && GAMEINDO_RAWG_KEY ) {
		return trim( (string) GAMEINDO_RAWG_KEY );
	}
	return trim( (string) get_option( 'gameindo_rawg_key', '' ) );
}

function gameindo_core_rawg_enabled() {
	return '' !== gameindo_core_rawg_key();
}

/**
 * How long a fetched window stays fresh. Release dates move by weeks, not
 * minutes, so this is deliberately long; the transient itself lives far longer
 * so an API outage means yesterday's list rather than an empty panel.
 */
function gameindo_core_rawg_ttl() {
	return (int) apply_filters( 'gameindo_rawg_ttl', 6 * HOUR_IN_SECONDS );
}

/**
 * One GET against RAWG. Returns the decoded body or WP_Error.
 */
function gameindo_core_rawg_request( $path, $query = array() ) {
	$key = gameindo_core_rawg_key();
	if ( '' === $key ) {
		return new WP_Error( 'gi_rawg_no_key', __( 'API key RAWG belum diisi.', 'gameindo-core' ) );
	}

	$query['key'] = $key;
	$url = add_query_arg( $query, GAMEINDO_RAWG_BASE . '/' . ltrim( $path, '/' ) );

	$res = wp_remote_get( $url, array(
		'timeout'    => 8,
		'user-agent' => 'GameIndo/' . GAMEINDO_CORE_VERSION . '; ' . home_url( '/' ),
		'headers'    => array( 'Accept' => 'application/json' ),
	) );
	if ( is_wp_error( $res ) ) {
		return $res;
	}

	$code = (int) wp_remote_retrieve_response_code( $res );
	if ( 200 !== $code ) {
		return new WP_Error( 'gi_rawg_http', sprintf( 'RAWG menjawab HTTP %d', $code ) );
	}

	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( ! is_array( $body ) ) {
		return new WP_Error( 'gi_rawg_json', 'Balasan RAWG bukan JSON yang valid.' );
	}
	return $body;
}

/**
 * Normalize one RAWG game into the flat row the theme renders. Every field is
 * read defensively: RAWG omits keys on sparse records, and a missing cover or
 * platform list must not take the whole panel down.
 */
function gameindo_core_rawg_row( $game ) {
	if ( empty( $game['name'] ) ) {
		return null;
	}

	$platforms = array();
	foreach ( (array) ( $game['parent_platforms'] ?? $game['platforms'] ?? array() ) as $entry ) {
		$name = $entry['platform']['name'] ?? ( $entry['name'] ?? '' );
		if ( $name ) {
			$platforms[] = $name;
		}
	}

	$released = isset( $game['released'] ) ? (string) $game['released'] : '';
	$ts       = $released ? strtotime( $released . ' 00:00:00' ) : 0;

	return array(
		'id'        => isset( $game['id'] ) ? (int) $game['id'] : 0,
		'name'      => (string) $game['name'],
		'slug'      => isset( $game['slug'] ) ? (string) $game['slug'] : '',
		'released'  => $released,
		'ts'        => $ts ? (int) $ts : 0,
		'tba'       => ! empty( $game['tba'] ) || ! $ts,
		'image'     => isset( $game['background_image'] ) ? (string) $game['background_image'] : '',
		'platforms' => array_slice( array_unique( $platforms ), 0, 4 ),
	);
}

/**
 * Upcoming releases, soonest first.
 *
 * $args: limit (int), days (how far ahead to look), platform (a key from
 * gameindo_core_rawg_platforms(), or 'all').
 *
 * Served stale-while-revalidate: a cached list renders immediately and the
 * refresh happens on the next request past the TTL, so a visitor never waits on
 * RAWG and an outage degrades to the last good list.
 */
function gameindo_core_get_upcoming_games( $args = array() ) {
	$args = wp_parse_args( $args, array( 'limit' => 6, 'days' => 120, 'platform' => 'all' ) );

	if ( ! gameindo_core_rawg_enabled() ) {
		return array();
	}

	$platform = (string) $args['platform'];
	$cache_id = 'gi_rawg_' . md5( $platform . '|' . (int) $args['days'] );
	$cached   = get_transient( $cache_id );
	$fresh_by = (int) get_option( $cache_id . '_at', 0 ) + gameindo_core_rawg_ttl();

	if ( is_array( $cached ) && time() < $fresh_by ) {
		return array_slice( $cached, 0, (int) $args['limit'] );
	}

	// A recent failure must not make every page view retry a dead endpoint.
	if ( get_transient( $cache_id . '_err' ) ) {
		return is_array( $cached ) ? array_slice( $cached, 0, (int) $args['limit'] ) : array();
	}

	$query = array(
		'dates'     => wp_date( 'Y-m-d' ) . ',' . wp_date( 'Y-m-d', time() + (int) $args['days'] * DAY_IN_SECONDS ),
		'ordering'  => 'released',
		'page_size' => min( 40, max( 6, (int) $args['limit'] * 3 ) ),
	);

	$ids = gameindo_core_rawg_platforms();
	if ( isset( $ids[ $platform ] ) ) {
		$query['platforms'] = ( 'xbox' === $platform ) ? $ids['xbox'] . ',186' : (string) $ids[ $platform ];
	}

	$body = gameindo_core_rawg_request( 'games', $query );
	if ( is_wp_error( $body ) ) {
		set_transient( $cache_id . '_err', $body->get_error_message(), 15 * MINUTE_IN_SECONDS );
		update_option( 'gameindo_rawg_last_error', array( 'at' => time(), 'msg' => $body->get_error_message() ), false );
		return is_array( $cached ) ? array_slice( $cached, 0, (int) $args['limit'] ) : array();
	}

	$rows = array();
	foreach ( (array) ( $body['results'] ?? array() ) as $game ) {
		$row = gameindo_core_rawg_row( $game );
		if ( $row ) {
			$rows[] = $row;
		}
	}

	// RAWG's own ordering is authoritative, but a row with no date sorts last.
	usort( $rows, function ( $a, $b ) {
		if ( ! $a['ts'] || ! $b['ts'] ) {
			return $a['ts'] ? -1 : ( $b['ts'] ? 1 : 0 );
		}
		return $a['ts'] - $b['ts'];
	} );

	set_transient( $cache_id, $rows, DAY_IN_SECONDS );
	update_option( $cache_id . '_at', time(), false );
	update_option( 'gameindo_rawg_last_ok', time(), false );

	return array_slice( $rows, 0, (int) $args['limit'] );
}
