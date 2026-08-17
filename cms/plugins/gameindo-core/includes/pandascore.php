<?php
/**
 * PandaScore — live esports schedule for the match widgets.
 *
 * Replaces the hand-maintained gi_match CPT as the source for the homepage
 * "Jadwal Match" panel and the esports page schedule. Six games are covered;
 * each has its own documented endpoint prefix on the API, so no guessing at
 * videogame ids is needed:
 *
 *   GET https://api.pandascore.co/{prefix}/matches/running
 *   GET https://api.pandascore.co/{prefix}/matches/upcoming
 *
 * Everything is fetched server-side — the token must never reach the browser.
 * Responses are cached in transients and served stale-while-revalidate, so a
 * visitor never waits on PandaScore: a stale panel renders instantly and the
 * refresh happens on cron in the background.
 *
 * @package GameIndo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GAMEINDO_PS_BASE', 'https://api.pandascore.co' );

/**
 * The six games GameIndo covers, in display order. Keys are our own stable
 * slugs (used in URLs as ?game=…), 'path' is PandaScore's endpoint prefix.
 * Note CS2 still lives under the legacy /csgo/ prefix on the API.
 */
function gameindo_core_pandascore_games() {
	return array(
		'mlbb'     => array( 'label' => 'ML:BB',     'name' => 'Mobile Legends: Bang Bang', 'path' => 'mlbb' ),
		'csgo'     => array( 'label' => 'CS:GO',     'name' => 'Counter-Strike 2',          'path' => 'csgo' ),
		'valorant' => array( 'label' => 'Valorant',  'name' => 'Valorant',                  'path' => 'valorant' ),
		'lol'      => array( 'label' => 'LoL',       'name' => 'League of Legends',         'path' => 'lol' ),
		'dota2'    => array( 'label' => 'DotA 2',    'name' => 'Dota 2',                    'path' => 'dota2' ),
		'ow'       => array( 'label' => 'Overwatch', 'name' => 'Overwatch',                 'path' => 'ow' ),
	);
}

/**
 * API token. A wp-config.php constant wins over the wp-admin setting, so a
 * production key can stay out of the database entirely.
 */
function gameindo_core_pandascore_token() {
	if ( defined( 'GAMEINDO_PANDASCORE_TOKEN' ) && GAMEINDO_PANDASCORE_TOKEN ) {
		return trim( (string) GAMEINDO_PANDASCORE_TOKEN );
	}
	return trim( (string) get_option( 'gameindo_pandascore_token', '' ) );
}

function gameindo_core_pandascore_enabled() {
	return '' !== gameindo_core_pandascore_token();
}

/**
 * How long each feed stays fresh. Live scores move minute to minute; the
 * upcoming list barely changes within an hour.
 */
function gameindo_core_pandascore_ttl( $type ) {
	$ttl = ( 'running' === $type ) ? 3 * MINUTE_IN_SECONDS : 15 * MINUTE_IN_SECONDS;
	return (int) apply_filters( 'gameindo_pandascore_ttl', $ttl, $type );
}

/**
 * One GET against the API. Auth goes in the Authorization header so the token
 * never lands in an access log or a proxy cache; PandaScore also accepts it as
 * a ?token= query parameter, which we retry with once if the header is
 * rejected (older gateway configurations only understand the query form).
 */
function gameindo_core_pandascore_request( $path, $query = array() ) {
	$token = gameindo_core_pandascore_token();
	if ( '' === $token ) {
		return new WP_Error( 'gi_ps_no_token', __( 'Token PandaScore belum diisi.', 'gameindo-core' ) );
	}

	$url  = add_query_arg( $query, GAMEINDO_PS_BASE . '/' . ltrim( $path, '/' ) );
	$args = array(
		'timeout'    => 8,
		'user-agent' => 'GameIndo/' . GAMEINDO_CORE_VERSION . '; ' . home_url( '/' ),
		'headers'    => array(
			'Authorization' => 'Bearer ' . $token,
			'Accept'        => 'application/json',
		),
	);

	$res = wp_remote_get( $url, $args );
	if ( ! is_wp_error( $res ) && 401 === (int) wp_remote_retrieve_response_code( $res ) ) {
		unset( $args['headers']['Authorization'] );
		$res = wp_remote_get( add_query_arg( 'token', $token, $url ), $args );
	}

	if ( is_wp_error( $res ) ) {
		return $res;
	}

	$code = (int) wp_remote_retrieve_response_code( $res );
	if ( 200 !== $code ) {
		return new WP_Error(
			'gi_ps_http_' . $code,
			/* translators: %d: HTTP status code returned by PandaScore. */
			sprintf( __( 'PandaScore menjawab HTTP %d.', 'gameindo-core' ), $code )
		);
	}

	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( ! is_array( $body ) ) {
		return new WP_Error( 'gi_ps_bad_json', __( 'Balasan PandaScore tidak bisa dibaca.', 'gameindo-core' ) );
	}
	return $body;
}

/**
 * Flatten one PandaScore match into the shape the templates render. Returns
 * null for anything unusable (no opponents, unknown game).
 */
function gameindo_core_pandascore_normalize( $raw, $game ) {
	$games = gameindo_core_pandascore_games();
	if ( ! isset( $games[ $game ] ) || empty( $raw['id'] ) ) {
		return null;
	}

	// Opponents are Teams for these six games, but the payload keeps the
	// generic { type, opponent } wrapper — and stays empty while a bracket
	// slot is still undecided.
	$sides = array();
	foreach ( (array) $raw['opponents'] as $side ) {
		if ( empty( $side['opponent'] ) ) {
			continue;
		}
		$o       = $side['opponent'];
		$sides[] = array(
			'id'    => isset( $o['id'] ) ? (int) $o['id'] : 0,
			'short' => ! empty( $o['acronym'] ) ? $o['acronym'] : ( isset( $o['name'] ) ? $o['name'] : 'TBD' ),
			'name'  => isset( $o['name'] ) ? $o['name'] : 'TBD',
			'image' => isset( $o['image_url'] ) ? $o['image_url'] : '',
		);
	}
	if ( count( $sides ) < 2 ) {
		return null;
	}

	// Scores arrive keyed by team id, and are a meaningless 0–0 until a match
	// actually starts — keep them null so the row renders "vs".
	$status = isset( $raw['status'] ) ? $raw['status'] : 'not_started';
	$scores = array( null, null );
	if ( in_array( $status, array( 'running', 'finished' ), true ) && ! empty( $raw['results'] ) ) {
		foreach ( (array) $raw['results'] as $r ) {
			foreach ( $sides as $i => $side ) {
				if ( isset( $r['team_id'] ) && (int) $r['team_id'] === $side['id'] ) {
					$scores[ $i ] = (int) $r['score'];
				}
			}
		}
	}

	$league = isset( $raw['league']['name'] ) ? $raw['league']['name'] : '';
	$serie  = '';
	if ( ! empty( $raw['serie']['full_name'] ) ) {
		$serie = $raw['serie']['full_name'];
	} elseif ( ! empty( $raw['serie']['name'] ) ) {
		$serie = $raw['serie']['name'];
	}
	$stage = isset( $raw['tournament']['name'] ) ? $raw['tournament']['name'] : '';
	$tier  = isset( $raw['tournament']['tier'] ) ? strtolower( (string) $raw['tournament']['tier'] ) : '';

	$begin = '';
	if ( ! empty( $raw['begin_at'] ) ) {
		$begin = $raw['begin_at'];
	} elseif ( ! empty( $raw['scheduled_at'] ) ) {
		$begin = $raw['scheduled_at'];
	}
	$begin_ts = $begin ? strtotime( $begin ) : 0;

	// Pick the one stream to link out to. A match often carries a dozen
	// co-streams in different languages; readers want the official broadcast,
	// in Indonesian when the tournament has one (MPL does), else English.
	// Scored rather than "last one wins" so the choice is deterministic.
	$stream      = '';
	$stream_lang = '';
	$best        = -1;
	foreach ( (array) $raw['streams_list'] as $s ) {
		if ( empty( $s['raw_url'] ) ) {
			continue;
		}
		$lang     = isset( $s['language'] ) ? $s['language'] : '';
		$official = ! empty( $s['official'] );
		$score    = 0;
		if ( $official && 'id' === $lang ) {
			$score = 5;
		} elseif ( $official && 'en' === $lang ) {
			$score = 4;
		} elseif ( $official ) {
			$score = 3;
		} elseif ( 'id' === $lang ) {
			$score = 2;
		} elseif ( ! empty( $s['main'] ) ) {
			$score = 1;
		}
		if ( $score > $best ) {
			$best        = $score;
			$stream      = $s['raw_url'];
			$stream_lang = $lang;
		}
	}

	$competition = trim( $league . ' ' . $serie );

	return array(
		'id'          => (int) $raw['id'],
		'game'        => $game,
		'game_label'  => $games[ $game ]['label'],
		'status'      => $status,
		'begin_ts'    => $begin_ts,
		'team_a'      => $sides[0]['short'],
		'team_a_name' => $sides[0]['name'],
		'team_b'      => $sides[1]['short'],
		'team_b_name' => $sides[1]['name'],
		'score_a'     => $scores[0],
		'score_b'     => $scores[1],
		'league'      => $league,
		'serie'       => $serie,
		'stage'       => $stage,
		'tier'        => $tier,
		'competition' => '' !== $competition ? $competition : $games[ $game ]['label'],
		'best_of'     => isset( $raw['number_of_games'] ) ? (int) $raw['number_of_games'] : 0,
		'stream_url'  => $stream,
		'stream_lang' => $stream_lang,
	);
}

/**
 * Fetch one feed ({game}, running|upcoming) and cache it. Stored with a long
 * transient expiry on purpose: the TTL above decides when data is *stale*,
 * while the cache itself is kept much longer so an API outage degrades to
 * yesterday's schedule instead of an empty panel.
 */
function gameindo_core_pandascore_refresh( $game, $type ) {
	$games = gameindo_core_pandascore_games();
	if ( ! isset( $games[ $game ] ) ) {
		return array();
	}

	// Back off after a failure so a down API isn't hammered once per pageview.
	if ( get_transient( 'gi_ps_err_' . $game . '_' . $type ) ) {
		$cached = get_transient( 'gi_ps_' . $game . '_' . $type );
		return isset( $cached['matches'] ) ? $cached['matches'] : array();
	}

	$query = array(
		'per_page' => ( 'running' === $type ) ? 10 : 25,
		'sort'     => 'begin_at',
	);
	if ( 'upcoming' === $type ) {
		// Undecided bracket slots would render as "TBD vs TBD" — skip them.
		$query['filter[opponents_filled]'] = 'true';
	}

	$body = gameindo_core_pandascore_request( $games[ $game ]['path'] . '/matches/' . $type, $query );

	if ( is_wp_error( $body ) ) {
		set_transient( 'gi_ps_err_' . $game . '_' . $type, $body->get_error_message(), 5 * MINUTE_IN_SECONDS );
		update_option( 'gameindo_pandascore_last_error', array(
			'time'    => time(),
			'feed'    => $game . '/' . $type,
			'message' => $body->get_error_message(),
		), false );
		$cached = get_transient( 'gi_ps_' . $game . '_' . $type );
		return isset( $cached['matches'] ) ? $cached['matches'] : array();
	}

	$matches = array();
	foreach ( $body as $raw ) {
		$m = gameindo_core_pandascore_normalize( $raw, $game );
		if ( $m ) {
			$matches[] = $m;
		}
	}

	set_transient(
		'gi_ps_' . $game . '_' . $type,
		array( 'fetched_at' => time(), 'matches' => $matches ),
		DAY_IN_SECONDS
	);
	delete_transient( 'gi_ps_err_' . $game . '_' . $type );
	update_option( 'gameindo_pandascore_last_ok', time(), false );

	return $matches;
}

/**
 * Read one feed. Fresh cache is returned as-is; stale cache is returned
 * immediately and refreshed on cron. Only a completely cold cache blocks the
 * request, and then only within a small per-pageview budget so the first hit
 * after activation can't stack six games' worth of round trips.
 */
function gameindo_core_pandascore_feed( $game, $type ) {
	if ( ! gameindo_core_pandascore_enabled() ) {
		return array();
	}

	$cached = get_transient( 'gi_ps_' . $game . '_' . $type );

	if ( is_array( $cached ) && isset( $cached['fetched_at'] ) ) {
		if ( ( time() - (int) $cached['fetched_at'] ) >= gameindo_core_pandascore_ttl( $type ) ) {
			gameindo_core_pandascore_queue_refresh();
		}
		return isset( $cached['matches'] ) ? $cached['matches'] : array();
	}

	static $budget = null;
	if ( null === $budget ) {
		$budget = (int) apply_filters( 'gameindo_pandascore_sync_budget', 4 );
	}
	if ( $budget < 1 ) {
		gameindo_core_pandascore_queue_refresh();
		return array();
	}
	$budget--;

	return gameindo_core_pandascore_refresh( $game, $type );
}

/**
 * Ask cron to top the caches up on the next request. Cheap and idempotent —
 * WP keeps a single pending instance of the hook.
 */
function gameindo_core_pandascore_queue_refresh() {
	if ( ! wp_next_scheduled( 'gameindo_pandascore_warm' ) ) {
		wp_schedule_single_event( time() + 30, 'gameindo_pandascore_warm' );
	}
}

/**
 * Cron worker: refresh every feed whose TTL has run out.
 */
function gameindo_core_pandascore_warm() {
	if ( ! gameindo_core_pandascore_enabled() ) {
		return;
	}
	foreach ( array_keys( gameindo_core_pandascore_games() ) as $game ) {
		foreach ( array( 'running', 'upcoming' ) as $type ) {
			$cached = get_transient( 'gi_ps_' . $game . '_' . $type );
			$age    = ( is_array( $cached ) && isset( $cached['fetched_at'] ) )
				? time() - (int) $cached['fetched_at']
				: PHP_INT_MAX;
			if ( $age >= gameindo_core_pandascore_ttl( $type ) ) {
				gameindo_core_pandascore_refresh( $game, $type );
			}
		}
	}
}
add_action( 'gameindo_pandascore_warm', 'gameindo_core_pandascore_warm' );

/**
 * Keep the caches warm even on a quiet site, so the first visitor of the day
 * still gets a current panel.
 */
function gameindo_core_pandascore_schedule_cron() {
	if ( ! wp_next_scheduled( 'gameindo_pandascore_cron' ) ) {
		wp_schedule_event( time() + 60, 'gameindo_five_minutes', 'gameindo_pandascore_cron' );
	}
}
add_action( 'gameindo_pandascore_cron', 'gameindo_core_pandascore_warm' );

function gameindo_core_pandascore_cron_interval( $schedules ) {
	$schedules['gameindo_five_minutes'] = array(
		'interval' => 5 * MINUTE_IN_SECONDS,
		'display'  => __( 'Setiap 5 menit (GameIndo)', 'gameindo-core' ),
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'gameindo_core_pandascore_cron_interval' );
add_action( 'init', 'gameindo_core_pandascore_schedule_cron' );

/**
 * Drop every cached feed — used by the settings screen and whenever the token
 * changes, so a new key takes effect immediately.
 */
function gameindo_core_pandascore_flush() {
	foreach ( array_keys( gameindo_core_pandascore_games() ) as $game ) {
		foreach ( array( 'running', 'upcoming' ) as $type ) {
			delete_transient( 'gi_ps_' . $game . '_' . $type );
			delete_transient( 'gi_ps_err_' . $game . '_' . $type );
		}
	}
	delete_option( 'gameindo_pandascore_last_error' );
}

/**
 * Sort weight for a tournament tier — S is the majors (The International), then
 * A (MPL, LEC, VCT main), down to D (open qualifiers). Anything unrecognised or
 * missing sorts last. Used for ranking only, never to hide matches: the
 * Overwatch World Cup ships as tier C and CS2 routinely has no A/B events at
 * all, so a tier floor would quietly empty whole games.
 */
function gameindo_core_tier_rank( $tier ) {
	$order = array( 's' => 0, 'a' => 1, 'b' => 2, 'c' => 3, 'd' => 4 );
	$tier  = strtolower( (string) $tier );
	return isset( $order[ $tier ] ) ? $order[ $tier ] : 5;
}

/**
 * The public accessor the theme consumes.
 *
 * Returns matches for one game key, or for every game when $game is 'all',
 * ordered live-first and then by kickoff time. $args:
 *   - limit    (int)  hard cap on rows returned; 0 = no cap.
 *   - priority (string) game key to float to the top within each matchday.
 *   - rank_by_tier (bool) rank each matchday by tournament tier before time.
 *     For a short curated panel this is what stops four slots filling with
 *     tier-D European qualifiers while VCT and the LEC are on the same day.
 *     Leave it off for a full schedule list, where readers expect the day to
 *     run in clock order.
 *   - tier_floor (string) tier below which a match is *demoted* — it drops
 *     behind everything at or above the floor, whatever its status. Demotion
 *     rather than exclusion is deliberate: on a quiet day the panel still
 *     fills instead of going blank.
 *   - max_per_game (int) soft cap on rows from any one game, so a short panel
 *     shows a spread of titles rather than four fixtures from whichever league
 *     happens to have a busy night. Also a soft rule: the cap is relaxed to
 *     reach `limit` if there isn't enough variety to fill the panel.
 *
 * Shape per row matches gameindo_core_pandascore_normalize().
 */
function gameindo_core_get_schedule( $game = 'all', $args = array() ) {
	$args = wp_parse_args( $args, array(
		'limit'        => 0,
		'priority'     => '',
		'rank_by_tier' => false,
		'tier_floor'   => '',
		'max_per_game' => 0,
	) );

	$games = gameindo_core_pandascore_games();
	$keys  = ( 'all' === $game || ! isset( $games[ $game ] ) ) ? array_keys( $games ) : array( $game );

	// Running is collected first so a match that briefly appears in both feeds
	// keeps the copy that carries a live score.
	$rows = array();
	foreach ( $keys as $key ) {
		foreach ( array( 'running', 'upcoming' ) as $type ) {
			foreach ( gameindo_core_pandascore_feed( $key, $type ) as $m ) {
				if ( ! isset( $rows[ $m['id'] ] ) ) {
					$rows[ $m['id'] ] = $m;
				}
			}
		}
	}
	$rows  = array_values( $rows );
	$floor = '' !== $args['tier_floor'] ? gameindo_core_tier_rank( $args['tier_floor'] ) : PHP_INT_MAX;

	usort( $rows, function ( $a, $b ) use ( $args, $floor ) {
		$ta = gameindo_core_tier_rank( $a['tier'] );
		$tb = gameindo_core_tier_rank( $b['tier'] );

		// 1. Prestige outranks everything, including being live. A closed
		// qualifier happening right now is still a closed qualifier, and must
		// not take a homepage slot from a match that readers actually follow.
		$fa = ( $ta <= $floor ) ? 0 : 1;
		$fb = ( $tb <= $floor ) ? 0 : 1;
		if ( $fa !== $fb ) {
			return $fa - $fb;
		}

		// 2. Then what's on air now.
		$la = ( 'running' === $a['status'] ) ? 0 : 1;
		$lb = ( 'running' === $b['status'] ) ? 0 : 1;
		if ( $la !== $lb ) {
			return $la - $lb;
		}

		// 3. Then matchday — a league playing four days from now must never
		// push today's fixtures out of a panel titled "Jadwal".
		$da = $a['begin_ts'] ? (int) wp_date( 'Ymd', $a['begin_ts'] ) : PHP_INT_MAX;
		$db = $b['begin_ts'] ? (int) wp_date( 'Ymd', $b['begin_ts'] ) : PHP_INT_MAX;
		if ( $da !== $db ) {
			return $da - $db;
		}

		// 4. Then the priority game, 5. tier, 6. kickoff. Tier also breaks ties
		// on identical kickoff times even when it isn't a ranking key — with a
		// dozen matches sharing a slot, the bigger tournament should lead.
		if ( $args['priority'] ) {
			$pa = ( $a['game'] === $args['priority'] ) ? 0 : 1;
			$pb = ( $b['game'] === $args['priority'] ) ? 0 : 1;
			if ( $pa !== $pb ) {
				return $pa - $pb;
			}
		}
		if ( $args['rank_by_tier'] && $ta !== $tb ) {
			return $ta - $tb;
		}
		if ( $a['begin_ts'] !== $b['begin_ts'] ) {
			return $a['begin_ts'] - $b['begin_ts'];
		}
		return $ta - $tb;
	} );

	// Spread the panel across titles. Overflow is held back rather than dropped
	// and then used to top the panel up, so capping never leaves empty slots.
	if ( $args['max_per_game'] > 0 && $args['limit'] > 0 ) {
		$picked = array();
		$spill  = array();
		$counts = array();
		foreach ( $rows as $m ) {
			$g    = $m['game'];
			$seen = isset( $counts[ $g ] ) ? $counts[ $g ] : 0;
			if ( $seen < (int) $args['max_per_game'] && count( $picked ) < (int) $args['limit'] ) {
				$picked[]     = $m;
				$counts[ $g ] = $seen + 1;
			} else {
				$spill[] = $m;
			}
		}
		foreach ( $spill as $m ) {
			if ( count( $picked ) >= (int) $args['limit'] ) {
				break;
			}
			$picked[] = $m;
		}
		$rows = $picked;
	}

	if ( $args['limit'] > 0 ) {
		$rows = array_slice( $rows, 0, (int) $args['limit'] );
	}
	return $rows;
}
