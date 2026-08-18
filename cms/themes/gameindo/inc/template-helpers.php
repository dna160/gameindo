<?php
/**
 * Template helpers — PHP ports of the original js/templates.js render
 * functions, producing byte-for-byte the same markup/classes so the CSS
 * design system applies unchanged. Every builder returns an HTML string.
 *
 * @package GameIndo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve a post's pillar slug from its categories (falling back to the
 * _gi_pillar meta hint, then 'home'). Pillars are the five fixed category
 * slugs — see gameindo_pillars().
 */
function gameindo_get_pillar( $post_id ) {
	$pillars = gameindo_pillars();
	$cats    = get_the_category( $post_id );
	if ( $cats ) {
		foreach ( $cats as $cat ) {
			if ( array_key_exists( $cat->slug, $pillars ) ) {
				return $cat->slug;
			}
		}
	}
	$hint = get_post_meta( $post_id, '_gi_pillar', true );
	if ( $hint && array_key_exists( $hint, $pillars ) ) {
		return $hint;
	}
	return 'home';
}

/**
 * URL for a pillar: the site root for 'home', the category archive otherwise.
 */
function gameindo_pillar_url( $slug ) {
	if ( 'home' === $slug ) {
		return home_url( '/' );
	}
	$term = get_category_by_slug( $slug );
	return $term ? get_category_link( $term->term_id ) : home_url( '/category/' . $slug . '/' );
}

/**
 * Human label for a pillar slug.
 */
function gameindo_pillar_name( $slug ) {
	$pillars = gameindo_pillars();
	return isset( $pillars[ $slug ] ) ? $pillars[ $slug ] : ucfirst( $slug );
}

/**
 * Read a GameIndo post meta value (keys are stored as _gi_<key>).
 */
function gameindo_meta( $post_id, $key, $default = '' ) {
	$v = get_post_meta( $post_id, '_gi_' . $key, true );
	return ( '' === $v || null === $v ) ? $default : $v;
}

/**
 * Estimated read time, e.g. "4 min read". Uses the explicit _gi_read_time
 * meta when set, otherwise derives it from the content word count.
 */
function gameindo_read_time( $post_id ) {
	$explicit = gameindo_meta( $post_id, 'read_time' );
	if ( $explicit ) {
		return $explicit;
	}
	$content = get_post_field( 'post_content', $post_id );
	$words   = str_word_count( wp_strip_all_tags( $content ) );
	$mins    = max( 1, (int) ceil( $words / 200 ) );
	return $mins . ' min read';
}

/**
 * Featured-image <img> URL at a given size, with a pillar-colored placeholder
 * fallback so cards never render an empty media slot.
 */
function gameindo_image_url( $post_id, $size = 'gameindo-card' ) {
	$url = get_the_post_thumbnail_url( $post_id, $size );
	if ( $url ) {
		return $url;
	}
	return GAMEINDO_URI . '/assets/samples/ph-neutral-1.png';
}

function gameindo_image_alt( $post_id ) {
	$thumb_id = get_post_thumbnail_id( $post_id );
	$alt      = $thumb_id ? get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) : '';
	return $alt ? $alt : get_the_title( $post_id );
}

/**
 * Localized short date, e.g. "2 Jul 2026" (WP id_ID locale supplies the
 * Jan/Feb/…/Des abbreviations that match the original design).
 */
function gameindo_date( $post_id ) {
	return get_the_date( 'j M Y', $post_id );
}

/**
 * Relative publish time for recent posts ("2 jam lalu"), falling back to the
 * plain date once a post is older than a week.
 */
function gameindo_time_ago( $post_id ) {
	$published = (int) get_post_time( 'U', true, $post_id );
	if ( ! $published || ( time() - $published ) > WEEK_IN_SECONDS ) {
		return gameindo_date( $post_id );
	}
	/* translators: %s: human-readable time difference, e.g. "2 jam". */
	return sprintf( __( '%s lalu', 'gameindo' ), human_time_diff( $published, time() ) );
}

/**
 * Card. $args: variant 'md'|'sm'|'h', pill_label, show_author (bool).
 * Mirrors templates.js `card()`.
 */
function gameindo_card( $post, $args = array() ) {
	$post_id = is_object( $post ) ? $post->ID : (int) $post;
	$variant = isset( $args['variant'] ) ? $args['variant'] : 'md';
	$pillar  = gameindo_get_pillar( $post_id );
	$pill    = isset( $args['pill_label'] ) ? $args['pill_label'] : gameindo_pillar_name( $pillar );

	$cls      = 'gi-card' . ( 'sm' === $variant ? ' gi-card--sm' : ( 'h' === $variant ? ' gi-card--h' : '' ) );
	if ( ! empty( $args['extra_class'] ) ) {
		$cls .= ' ' . $args['extra_class'];
	}
	$pill_cls = 'h' === $variant ? 'gi-pill gi-card__pill' : 'gi-pill gi-pill--sm gi-card__pill';
	$show_ex  = 'sm' !== $variant;
	$show_au  = ( ! isset( $args['show_author'] ) || $args['show_author'] ) && 'sm' !== $variant;

	$author = get_the_author_meta( 'display_name', get_post_field( 'post_author', $post_id ) );
	$meta   = ( $show_au ? '<span class="gi-card__author">' . esc_html( $author ) . '</span><span aria-hidden="true">·</span>' : '' )
		. '<span>' . esc_html( gameindo_date( $post_id ) ) . '</span>';

	$data_attrs = '';
	if ( ! empty( $args['attrs'] ) && is_array( $args['attrs'] ) ) {
		foreach ( $args['attrs'] as $k => $v ) {
			$data_attrs .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
		}
	}

	$html  = '<a class="' . esc_attr( $cls ) . '" data-pillar="' . esc_attr( $pillar ) . '"' . $data_attrs . ' href="' . esc_url( get_permalink( $post_id ) ) . '">';
	$html .= '<div class="gi-card__media"><img src="' . esc_url( gameindo_image_url( $post_id ) ) . '" alt="' . esc_attr( gameindo_image_alt( $post_id ) ) . '" loading="lazy">';
	$html .= '<span class="' . esc_attr( $pill_cls ) . '">' . esc_html( $pill ) . '</span></div>';
	$html .= '<div class="gi-card__body"><h3 class="gi-card__title">' . esc_html( get_the_title( $post_id ) ) . '</h3>';
	if ( $show_ex ) {
		$html .= '<p class="gi-card__excerpt">' . esc_html( gameindo_get_excerpt( $post_id ) ) . '</p>';
	}
	$html .= '<div class="gi-card__meta">' . $meta . '</div></div>';
	$html .= '</a>';
	return $html;
}

/**
 * A short plain-text excerpt for cards/features.
 */
function gameindo_get_excerpt( $post_id, $words = 24 ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}
	if ( ! empty( $post->post_excerpt ) ) {
		return wp_strip_all_tags( $post->post_excerpt );
	}
	return wp_trim_words( wp_strip_all_tags( $post->post_content ), $words, '…' );
}

/**
 * Hero / pillar feature block. $args: sm (bool), pill_label.
 * Mirrors templates.js `feature()`.
 */
function gameindo_feature( $post, $args = array() ) {
	$post_id = is_object( $post ) ? $post->ID : (int) $post;
	$pillar  = gameindo_get_pillar( $post_id );
	$pill    = isset( $args['pill_label'] ) ? $args['pill_label'] : gameindo_pillar_name( $pillar );
	$sm      = ! empty( $args['sm'] );
	$author  = get_the_author_meta( 'display_name', get_post_field( 'post_author', $post_id ) );

	$html  = '<a class="gi-feature' . ( $sm ? ' gi-feature--sm' : '' ) . '" data-pillar="' . esc_attr( $pillar ) . '" href="' . esc_url( get_permalink( $post_id ) ) . '">';
	$html .= '<img src="' . esc_url( gameindo_image_url( $post_id, 'gameindo-hero' ) ) . '" alt="' . esc_attr( gameindo_image_alt( $post_id ) ) . '">';
	$html .= '<span class="gi-feature__bar" aria-hidden="true"></span>';
	$html .= '<div class="gi-feature__content">';
	$html .= '<span class="gi-pill">' . esc_html( $pill ) . '</span>';
	$html .= '<h2 class="gi-feature__title">' . esc_html( get_the_title( $post_id ) ) . '</h2>';
	$html .= '<p class="gi-feature__excerpt">' . esc_html( gameindo_get_excerpt( $post_id ) ) . '</p>';
	$html .= '<div class="gi-feature__meta"><b>' . esc_html( $author ) . '</b><span aria-hidden="true">·</span><span>' . esc_html( gameindo_date( $post_id ) ) . '</span></div>';
	$html .= '</div></a>';
	return $html;
}

/**
 * Numbered rank row (Terpopuler rails). $args: thumb (bool).
 * Mirrors templates.js `rankRow()`.
 */
function gameindo_rank_row( $post, $index, $args = array() ) {
	$post_id = is_object( $post ) ? $post->ID : (int) $post;
	$pillar  = gameindo_get_pillar( $post_id );
	$thumb   = ! empty( $args['thumb'] );
	$num     = strlen( (string) $index ) < 2 ? '0' . $index : (string) $index;
	$reads   = gameindo_meta( $post_id, 'reads' );
	$sub     = gameindo_meta( $post_id, 'subcategory' );
	$cat     = $sub ? $sub : gameindo_pillar_name( $pillar );

	$thumb_html = $thumb
		? '<span class="gi-rank__thumb"><img src="' . esc_url( gameindo_image_url( $post_id, 'gameindo-thumb' ) ) . '" alt="" loading="lazy"></span>'
		: '';

	// A just-published article has no reads figure yet — show when it landed
	// instead of a dead "— dibaca", and flag it so readers spot what's new.
	$badge = gameindo_is_fresh( $post_id ) ? '<span class="gi-rank__badge">Baru</span>' : '';
	$meta  = $reads ? $reads . ' dibaca' : gameindo_time_ago( $post_id );

	$html  = '<a class="gi-rank' . ( $thumb ? '' : ' gi-rank--no-thumb' ) . '" data-pillar="' . esc_attr( $pillar ) . '" href="' . esc_url( get_permalink( $post_id ) ) . '">';
	$html .= '<span class="gi-rank__num">' . esc_html( $num ) . '</span>';
	$html .= $thumb_html;
	$html .= '<span><span class="gi-rank__category">' . esc_html( $cat ) . $badge . '</span>';
	$html .= '<span class="gi-rank__title">' . esc_html( get_the_title( $post_id ) ) . '</span>';
	$html .= '<span class="gi-rank__meta">' . esc_html( $meta ) . '</span></span>';
	$html .= '</a>';
	return $html;
}

/**
 * Pillar tile (homepage grid). Mirrors templates.js `pillarTile()`.
 */
function gameindo_pillar_tile( $slug, $name, $count, $href ) {
	$html  = '<a data-pillar="' . esc_attr( $slug ) . '" href="' . esc_url( $href ) . '">';
	$html .= '<span class="gi-pillar-tiles__name">' . esc_html( $name ) . '</span>';
	$html .= '<span class="gi-pillar-tiles__count">' . esc_html( number_format_i18n( $count ) ) . ' artikel →</span>';
	$html .= '</a>';
	return $html;
}

/**
 * Hashtag chip. Mirrors templates.js `tag()`.
 */
function gameindo_tag_chip( $text, $href ) {
	return '<a class="gi-tag" href="' . esc_url( $href ) . '">#' . esc_html( mb_strtoupper( $text ) ) . '</a>';
}

/**
 * Ticker item. Mirrors templates.js `tickerItem()`.
 */
function gameindo_ticker_item( $item ) {
	$url   = ! empty( $item['url'] ) ? $item['url'] : '#';
	$badge = ! empty( $item['badge'] )
		? '<span class="gi-ticker__badge">' . esc_html( $item['badge'] ) . '</span>'
		: '';
	return '<a class="gi-ticker__item" href="' . esc_url( $url ) . '">' . $badge . esc_html( $item['text'] ) . '</a>';
}

/**
 * Standings row. Mirrors templates.js `standingsRow()`.
 */
function gameindo_standings_row( $row ) {
	$top   = ! empty( $row['top'] );
	$html  = '<div class="gi-standings__row' . ( $top ? ' gi-standings__row--top' : '' ) . '">';
	$html .= '<span class="gi-standings__rank">' . esc_html( $row['rank'] ) . '</span>';
	$html .= '<span class="gi-standings__team">' . esc_html( $row['team'] ) . '</span>';
	$html .= '<span class="gi-standings__wl">' . esc_html( $row['wl'] ) . '</span>';
	$html .= '<span class="gi-standings__pts">' . esc_html( $row['pts'] ) . '</span>';
	$html .= '</div>';
	return $html;
}

/* ============================================================
   MATCH SCHEDULE — live from PandaScore via GameIndo Core
   ============================================================ */

/**
 * The six games the schedule covers, in display order. Mirrors the plugin's
 * registry; kept here as a fallback so the filter chips still render (and the
 * page doesn't fatal) when the plugin is inactive.
 */
function gameindo_esports_games() {
	if ( function_exists( 'gameindo_core_pandascore_games' ) ) {
		return gameindo_core_pandascore_games();
	}
	return array(
		'mlbb'     => array( 'label' => 'ML:BB',     'name' => 'Mobile Legends: Bang Bang' ),
		'csgo'     => array( 'label' => 'CS:GO',     'name' => 'Counter-Strike 2' ),
		'valorant' => array( 'label' => 'Valorant',  'name' => 'Valorant' ),
		'lol'      => array( 'label' => 'LoL',       'name' => 'League of Legends' ),
		'dota2'    => array( 'label' => 'DotA 2',    'name' => 'Dota 2' ),
		'ow'       => array( 'label' => 'Overwatch', 'name' => 'Overwatch' ),
	);
}

/**
 * Which game the esports page is filtered to, from ?game=… — 'all' unless the
 * value is one of the six keys.
 */
function gameindo_current_game() {
	$g = isset( $_GET['game'] ) ? sanitize_key( wp_unslash( $_GET['game'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return array_key_exists( $g, gameindo_esports_games() ) ? $g : 'all';
}

/**
 * Lowest tournament tier that still counts as a marquee event for the homepage
 * panel. Matches below it get demoted behind everything else — never hidden.
 *
 * 'c' is the line that matters in practice: it keeps the Overwatch World Cup,
 * the KeSPA Cup and the ESL Challenger League alongside MPL, the LEC, VCT and
 * The International, while pushing back open and closed qualifiers (NODWIN,
 * Exort Fiesta, CCT qualifiers) that would otherwise take homepage slots purely
 * for being live. Raise it to 'b' for majors only, or 'd'/'' to rank purely by
 * time again.
 */
function gameindo_prestige_floor() {
	return (string) apply_filters( 'gameindo_prestige_tier_floor', 'c' );
}

/**
 * Sort weight for a tournament tier (S is biggest, unknown sorts last). Uses
 * the plugin's table when it's active, with the same order inlined so the
 * theme still ranks correctly on its own.
 */
function gameindo_tier_rank( $tier ) {
	if ( function_exists( 'gameindo_core_tier_rank' ) ) {
		return gameindo_core_tier_rank( $tier );
	}
	$order = array( 's' => 0, 'a' => 1, 'b' => 2, 'c' => 3, 'd' => 4 );
	$tier  = strtolower( (string) $tier );
	return isset( $order[ $tier ] ) ? $order[ $tier ] : 5;
}

/**
 * Human label for the panel heading: "Jadwal" or "Jadwal ML:BB".
 */
function gameindo_schedule_title( $game ) {
	$games = gameindo_esports_games();
	return isset( $games[ $game ] ) ? 'Jadwal ' . $games[ $game ]['label'] : 'Jadwal';
}

/**
 * The schedule rows. Live matches first, then upcoming by matchday.
 *
 * Prefers PandaScore (live, six games). When there's no token or the API is
 * unreachable, falls back to the hand-maintained gi_match entries so the panel
 * degrades to the old editable behaviour instead of vanishing.
 *
 * $args: limit (int), priority (game key floated up within each matchday).
 */
function gameindo_get_schedule( $game = 'all', $args = array() ) {
	if ( function_exists( 'gameindo_core_get_schedule' ) ) {
		$rows = gameindo_core_get_schedule( $game, $args );
		if ( ! empty( $rows ) ) {
			return $rows;
		}
	}

	// Fallback: the manual Match Center rows carry their own status label and
	// no kickoff timestamp, so they render as-is.
	if ( 'all' !== $game ) {
		return array();
	}
	$legacy = gameindo_get_matches();
	$rows   = array();
	foreach ( (array) $legacy['matches'] as $m ) {
		$rows[] = array(
			'id'           => isset( $m['id'] ) ? (int) $m['id'] : 0,
			'game'         => '',
			'game_label'   => '',
			'status'       => isset( $m['status'] ) && 'live' === $m['status'] ? 'running' : 'not_started',
			'status_label' => isset( $m['status_label'] ) ? $m['status_label'] : '',
			'begin_ts'     => 0,
			'team_a'       => $m['team_a'],
			'team_a_name'  => $m['team_a'],
			'team_b'       => $m['team_b'],
			'team_b_name'  => $m['team_b'],
			'score_a'      => ( '' === $m['score_a'] ) ? null : $m['score_a'],
			'score_b'      => ( '' === $m['score_b'] ) ? null : $m['score_b'],
			'league'       => isset( $legacy['competition'] ) ? $legacy['competition'] : '',
			'serie'        => '',
			'stage'        => '',
			'tier'         => '',
			'competition'  => isset( $legacy['competition'] ) ? $legacy['competition'] : '',
			'best_of'      => 0,
			'stream_url'   => '',
			'stream_lang'  => '',
		);
	}
	if ( ! empty( $args['limit'] ) ) {
		$rows = array_slice( $rows, 0, (int) $args['limit'] );
	}
	return $rows;
}

/**
 * Short kickoff label for a row: "● LIVE", "19:30" today, "Bsk 19:30",
 * "Sab 19:30" this week, "21 Agu" beyond it. Times are rendered in the site
 * timezone, so an Indonesian site shows WIB.
 */
function gameindo_match_time_label( $m ) {
	if ( ! empty( $m['status_label'] ) ) {
		return $m['status_label'];
	}
	if ( isset( $m['status'] ) && 'running' === $m['status'] ) {
		return '● LIVE';
	}
	$ts = isset( $m['begin_ts'] ) ? (int) $m['begin_ts'] : 0;
	if ( ! $ts ) {
		return 'TBD';
	}

	$day  = wp_date( 'Ymd', $ts );
	$time = wp_date( 'H:i', $ts );
	if ( wp_date( 'Ymd' ) === $day ) {
		return $time;
	}
	if ( wp_date( 'Ymd', time() + DAY_IN_SECONDS ) === $day ) {
		return 'Bsk ' . $time;
	}
	if ( ( $ts - time() ) < 6 * DAY_IN_SECONDS ) {
		return wp_date( 'D', $ts ) . ' ' . $time;
	}
	return wp_date( 'j M', $ts );
}

/**
 * Day heading for the grouped schedule list: "Hari ini · Sab, 17 Agu".
 */
function gameindo_match_day_label( $ts ) {
	if ( ! $ts ) {
		return 'Jadwal menyusul';
	}
	$day  = wp_date( 'Ymd', $ts );
	$date = wp_date( 'D, j M', $ts );
	if ( wp_date( 'Ymd' ) === $day ) {
		return 'Hari ini · ' . $date;
	}
	if ( wp_date( 'Ymd', time() + DAY_IN_SECONDS ) === $day ) {
		return 'Besok · ' . $date;
	}
	return $date;
}

/**
 * Score for a row, or "vs" while it hasn't started.
 */
function gameindo_match_score( $m ) {
	if ( null === $m['score_a'] || '' === $m['score_a'] || null === $m['score_b'] || '' === $m['score_b'] ) {
		return 'vs';
	}
	return $m['score_a'] . ' — ' . $m['score_b'];
}

/**
 * The competition line: "ML:BB · MPL Indonesia". The game prefix is dropped
 * when the panel is already filtered to a single game.
 */
function gameindo_match_competition( $m, $with_game = true ) {
	$parts = array();
	if ( $with_game && ! empty( $m['game_label'] ) ) {
		$parts[] = $m['game_label'];
	}
	if ( ! empty( $m['league'] ) ) {
		$parts[] = $m['league'];
	} elseif ( ! empty( $m['competition'] ) ) {
		$parts[] = $m['competition'];
	}
	return implode( ' · ', $parts );
}

/**
 * Distinct competitions present in a set of rows — used to spell out which
 * tournaments a game's schedule actually covers (MPL Indonesia vs MPL
 * Philippines vs a one-off invitational), which is otherwise invisible.
 */
function gameindo_schedule_competitions( $rows, $max = 4 ) {
	$names = array();
	foreach ( $rows as $m ) {
		$name = ! empty( $m['league'] ) ? $m['league'] : $m['competition'];
		if ( $name && ! in_array( $name, $names, true ) ) {
			$names[] = $name;
		}
	}
	if ( count( $names ) > $max ) {
		$rest  = count( $names ) - $max;
		$names = array_slice( $names, 0, $max );
		/* translators: %d: number of further competitions. */
		$names[] = sprintf( _n( '+%d lainnya', '+%d lainnya', $rest, 'gameindo' ), $rest );
	}
	return $names;
}

/**
 * Name the broadcaster behind a stream URL so the watch button says where the
 * click leads. Roughly 90% of matches carry one, mostly Twitch and YouTube.
 * Anything unrecognised falls back to a neutral "Live".
 */
function gameindo_stream_platform( $url ) {
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	if ( '' === $host ) {
		return '';
	}
	$known = array(
		'youtube'   => 'YouTube',
		'youtu.be'  => 'YouTube',
		'twitch'    => 'Twitch',
		'kick.com'  => 'Kick',
		'sooplive'  => 'SOOP',
		'afreeca'   => 'SOOP',
		'trovo'     => 'Trovo',
		'huya'      => 'Huya',
		'douyu'     => 'Douyu',
		'nimo'      => 'Nimo TV',
		'facebook'  => 'Facebook',
		'bilibili'  => 'Bilibili',
		'vk.com'    => 'VK',
	);
	foreach ( $known as $needle => $label ) {
		if ( false !== strpos( $host, $needle ) ) {
			return $label;
		}
	}
	return 'Live';
}

/**
 * The watch-now button for a row with an official broadcast. Indonesian-
 * language streams are flagged, since an MPL ID broadcast in Indonesian is a
 * materially better click for this audience than the English world feed.
 */
function gameindo_stream_button( $m, $compact = false ) {
	if ( empty( $m['stream_url'] ) ) {
		return '';
	}
	$platform = gameindo_stream_platform( $m['stream_url'] );
	$live     = ( isset( $m['status'] ) && 'running' === $m['status'] );
	$label    = $compact ? $platform : ( $live ? 'Tonton' : $platform );
	if ( ! $compact && ! empty( $m['stream_lang'] ) && 'id' === $m['stream_lang'] ) {
		$label .= ' ID';
	}
	return '<span class="gi-watch' . ( $live ? ' gi-watch--live' : '' ) . '">'
		. '<span class="gi-watch__glyph" aria-hidden="true">▶</span>'
		. '<span class="gi-watch__label">' . esc_html( $label ) . '</span></span>';
}

/**
 * Accessible label for a row that links out to a broadcast.
 */
function gameindo_stream_aria( $m ) {
	$who = $m['team_a'] . ' vs ' . $m['team_b'];
	$at  = gameindo_stream_platform( $m['stream_url'] );
	return ( isset( $m['status'] ) && 'running' === $m['status'] )
		? sprintf( 'Tonton %s yang sedang berlangsung di %s', $who, $at )
		: sprintf( 'Buka siaran resmi %s di %s', $who, $at );
}

/**
 * Match-panel row (homepage hero side panel). Two lines: the fixture, then the
 * competition and kickoff time. Rows with an official broadcast link straight
 * out to it, so a live match is one click from the homepage.
 */
function gameindo_match_panel_row( $m ) {
	$is_live = ( isset( $m['status'] ) && 'running' === $m['status'] );
	$live    = $is_live ? ' gi-matchpanel__status--live' : '';
	$comp    = gameindo_match_competition( $m );
	$stream  = ! empty( $m['stream_url'] );

	$open = $stream
		? '<a class="gi-matchpanel__row gi-matchpanel__row--link' . ( $is_live ? ' gi-matchpanel__row--live' : '' ) . '"'
			. ' href="' . esc_url( $m['stream_url'] ) . '" target="_blank" rel="noopener noreferrer"'
			. ' aria-label="' . esc_attr( gameindo_stream_aria( $m ) ) . '">'
		: '<div class="gi-matchpanel__row">';

	$html  = $open;
	$html .= '<span class="gi-matchpanel__main">';
	$html .= '<span class="gi-matchpanel__team">' . esc_html( $m['team_a'] ) . '</span>';
	$html .= '<span class="gi-matchpanel__score">' . esc_html( gameindo_match_score( $m ) ) . '</span>';
	$html .= '<span class="gi-matchpanel__team gi-matchpanel__team--right">' . esc_html( $m['team_b'] ) . '</span>';
	$html .= '</span>';
	$html .= '<span class="gi-matchpanel__sub">';
	$html .= '<span class="gi-matchpanel__comp">' . esc_html( $comp ) . '</span>';
	$html .= '<span class="gi-matchpanel__status' . $live . '">'
		. ( $stream ? gameindo_stream_button( $m, true ) : '' )
		. esc_html( gameindo_match_time_label( $m ) ) . '</span>';
	$html .= '</span>';
	$html .= $stream ? '</a>' : '</div>';
	return $html;
}

/**
 * Mobile match card (the strip below the hero).
 */
function gameindo_mobile_match_card( $m ) {
	$live   = ( isset( $m['status'] ) && 'running' === $m['status'] );
	$teams  = $m['team_a'] . ' ' . gameindo_match_score( $m ) . ' ' . $m['team_b'];
	$stream = ! empty( $m['stream_url'] );
	$cls    = 'gi-mobile-matchcard' . ( $live ? ' gi-mobile-matchcard--live' : '' ) . ( $stream ? ' gi-mobile-matchcard--link' : '' );

	$html  = $stream
		? '<a class="' . esc_attr( $cls ) . '" href="' . esc_url( $m['stream_url'] ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr( gameindo_stream_aria( $m ) ) . '">'
		: '<div class="' . esc_attr( $cls ) . '">';
	$html .= '<span class="gi-mobile-matchcard__status' . ( $live ? ' gi-mobile-matchcard__status--live' : '' ) . '">'
		. ( $stream ? gameindo_stream_button( $m, true ) : '' )
		. esc_html( gameindo_match_time_label( $m ) ) . '</span>';
	$html .= '<span class="gi-mobile-matchcard__teams">' . esc_html( $teams ) . '</span>';
	$html .= '<span class="gi-mobile-matchcard__comp">' . esc_html( gameindo_match_competition( $m ) ) . '</span>';
	$html .= $stream ? '</a>' : '</div>';
	return $html;
}

/**
 * Schedule row for the esports page panel — roomier than the homepage row, so
 * it names the tournament stage and links to the official stream when there is
 * one. $with_game hides the redundant game chip on a filtered panel.
 */
function gameindo_schedule_row( $m, $with_game = true ) {
	$live = ( isset( $m['status'] ) && 'running' === $m['status'] );
	$comp = gameindo_match_competition( $m, $with_game );
	if ( ! empty( $m['serie'] ) ) {
		$comp .= ' ' . $m['serie'];
	}
	if ( ! empty( $m['stage'] ) ) {
		$comp .= ' · ' . $m['stage'];
	}

	$bo     = ! empty( $m['best_of'] ) ? 'BO' . (int) $m['best_of'] : '';
	$stream = ! empty( $m['stream_url'] );
	$watch  = $stream ? gameindo_stream_button( $m ) : '';

	$open = $stream
		? '<a class="gi-schedule__row gi-schedule__row--link' . ( $live ? ' gi-schedule__row--live' : '' ) . '"'
			. ' href="' . esc_url( $m['stream_url'] ) . '" target="_blank" rel="noopener noreferrer"'
			. ' aria-label="' . esc_attr( gameindo_stream_aria( $m ) ) . '">'
		: '<div class="gi-schedule__row' . ( $live ? ' gi-schedule__row--live' : '' ) . '">';

	$html  = $open;
	$html .= '<span class="gi-schedule__time">' . esc_html( gameindo_match_time_label( $m ) ) . '</span>';
	$html .= '<span class="gi-schedule__body">';
	$html .= '<span class="gi-schedule__teams">' . esc_html( $m['team_a'] ) . ' <b>' . esc_html( gameindo_match_score( $m ) ) . '</b> ' . esc_html( $m['team_b'] ) . '</span>';
	$html .= '<span class="gi-schedule__comp">' . esc_html( $comp ) . '</span>';
	$html .= '</span>';
	$html .= '<span class="gi-schedule__meta">';
	$html .= $watch;
	$html .= $bo ? '<span class="gi-schedule__bo">' . esc_html( $bo ) . '</span>' : '';
	$html .= '</span>';
	$html .= $stream ? '</a>' : '</div>';
	return $html;
}

/**
 * Data accessors for the esports widgets. Prefer the GameIndo Core plugin
 * (live, editable in wp-admin); fall back to the bundled JSON fixtures so the
 * theme still renders if the plugin is inactive.
 */
function gameindo_get_matches() {
	if ( function_exists( 'gameindo_core_get_matches' ) ) {
		$data = gameindo_core_get_matches();
		if ( ! empty( $data['matches'] ) ) {
			return $data;
		}
	}
	return gameindo_fixture( 'matches.json', array( 'competition' => '', 'matches' => array() ) );
}

function gameindo_get_standings() {
	if ( function_exists( 'gameindo_core_get_standings' ) ) {
		$data = gameindo_core_get_standings();
		if ( ! empty( $data['rows'] ) ) {
			return $data;
		}
	}
	return gameindo_fixture( 'standings.json', array( 'competition' => '', 'season_label' => '', 'rows' => array() ) );
}

/**
 * Ticker items built from the newest published articles, each carrying its
 * publish timestamp so the feed can be ordered chronologically.
 */
function gameindo_latest_ticker_items( $count = 8 ) {
	$posts = get_posts( array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => (int) $count,
		'orderby'             => 'date',
		'order'               => 'DESC',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
	) );

	$out = array();
	foreach ( $posts as $p ) {
		$out[] = array(
			'id'   => $p->ID,
			'text' => get_the_title( $p ),
			'url'  => get_permalink( $p ),
			'time' => (int) get_post_time( 'U', true, $p->ID ),
		);
	}
	return $out;
}

/**
 * Live ticker feed: the newest published articles, newest first. Nothing else
 * — no hand-written entries, no fixtures. Whatever went live most recently
 * leads the marquee, and items inside the freshness window get a "Baru" badge.
 *
 * The Live Ticker CPT is deliberately no longer rendered here (see
 * gameindo_core_get_ticker()); the header is an automatic feed of articles.
 * Returns an empty array on a site with no posts, which hides the bar.
 */
function gameindo_get_ticker() {
	$items        = gameindo_latest_ticker_items( (int) apply_filters( 'gameindo_ticker_max', 12 ) );
	$fresh_before = time() - ( gameindo_fresh_hours() * HOUR_IN_SECONDS );

	foreach ( $items as $i => $item ) {
		$items[ $i ]['badge'] = ( $item['time'] && $item['time'] >= $fresh_before ) ? 'Baru' : '';
	}

	return $items;
}

/**
 * Chip label for a competition. Some league names mean nothing on their own —
 * PandaScore calls the Overwatch World Cup just "World Cup", which on an
 * Indonesian site reads as football. Those get their game prefixed; names that
 * already identify themselves (LEC, VCT, The International) are left alone.
 */
function gameindo_league_chip_label( $m ) {
	$league = isset( $m['league'] ) ? trim( $m['league'] ) : '';
	$label  = isset( $m['game_label'] ) ? $m['game_label'] : '';
	if ( '' === $league || '' === $label ) {
		return $league;
	}
	$generic = array( 'world cup', 'champions', 'masters', 'major', 'open', 'invitational', 'pro league', 'super league', 'challengers' );
	$needle  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $league ) : strtolower( $league );
	return in_array( $needle, $generic, true ) ? $label . ' ' . $league : $league;
}

/**
 * Chip label for a team. Full names read better as a topic than acronyms
 * ("Team Liquid ID" over "TLID"), but not when they run long enough to shove
 * every other chip off the row.
 */
function gameindo_team_chip_label( $m, $side ) {
	$name  = isset( $m[ 'team_' . $side . '_name' ] ) ? trim( $m[ 'team_' . $side . '_name' ] ) : '';
	$short = isset( $m[ 'team_' . $side ] ) ? trim( $m[ 'team_' . $side ] ) : '';
	if ( '' === $name || 0 === strcasecmp( $name, 'TBD' ) ) {
		return 0 === strcasecmp( $short, 'TBD' ) ? '' : $short;
	}
	$len = function_exists( 'mb_strlen' ) ? mb_strlen( $name ) : strlen( $name );
	return ( $len <= 20 || '' === $short ) ? $name : $short;
}

/**
 * Is this tag a subject people discuss, or just how the piece was written?
 *
 * "Review", "Guide" and "Tips" describe a format, not a topic — as a chip in a
 * row headed "Topik Hangat" they promise a story and deliver a genre. A tag
 * used only once is filtered out for a related reason: it links to an archive
 * of a single article, which is a dead end rather than a topic. Both lists are
 * filterable; the site's own vocabulary is the thing that will change.
 */
function gameindo_is_topic_tag( $term ) {
	$min = (int) apply_filters( 'gameindo_hot_topics_tag_min_posts', 2 );
	if ( isset( $term->count ) && (int) $term->count < $min ) {
		return false;
	}
	$skip = (array) apply_filters( 'gameindo_hot_topics_tag_blocklist', array(
		'review', 'guide', 'tips', 'panduan', 'berita', 'news', 'artikel', 'update', 'opini',
	) );
	$name = function_exists( 'mb_strtolower' ) ? mb_strtolower( $term->name ) : strtolower( $term->name );
	return ! in_array( trim( $name ), $skip, true );
}

/**
 * "Topik Hangat" chips — what is actually current, assembled rather than typed.
 *
 * Sources, most immediate first:
 *   1. Editor picks (the gi_topic CPT), so a breaking story can be pinned
 *      before any of this notices it.
 *   2. Teams on air right now — the fastest-moving signal the site has, and
 *      the one readers recognise instantly (RRQ, ONIC, T1).
 *   3. Competitions live or starting within a few days.
 *   4. Tags across recent articles, falling back to the site's most-used tags
 *      when the newest posts carry none.
 *
 * Anything live is pinned; everything else goes into a pool that the row
 * rotates through, so a reader coming back an hour later sees a different set
 * rather than the same eight chips frozen in place.
 *
 * Every chip lands on real content: esports chips open the schedule filtered to
 * that game, tag chips open the tag archive, editor chips run their own search.
 */
function gameindo_hot_topics( $limit = 8 ) {
	$cached = get_transient( 'gi_hot_topics' );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$pinned = array();
	$pool   = array();
	$seen   = array();

	$push = function ( &$bucket, $label, $url, $live = false ) use ( &$seen ) {
		$label = trim( wp_strip_all_tags( (string) $label ) );
		if ( '' === $label ) {
			return false;
		}
		$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $label ) : strtolower( $label );
		if ( isset( $seen[ $key ] ) ) {
			return false;
		}
		$seen[ $key ] = true;
		$bucket[]     = array( 'label' => $label, 'url' => $url, 'live' => (bool) $live );
		return true;
	};

	// 1. Editor picks. Capped rather than unlimited: nobody goes back to unpin
	// a topic, and a full row of them is just the hand-typed bar that went
	// stale in the first place. Leaving room keeps something current on screen.
	$editor_max = (int) apply_filters( 'gameindo_hot_topics_editor_max', 3 );
	$picked     = 0;
	foreach ( (array) gameindo_get_topics() as $t ) {
		if ( $picked >= $editor_max ) {
			break;
		}
		if ( empty( $t['label'] ) ) {
			continue;
		}
		$q = ! empty( $t['query'] ) ? $t['query'] : $t['label'];
		if ( $push( $pinned, $t['label'], home_url( '/?s=' . rawurlencode( $q ) ) ) ) {
			$picked++;
		}
	}

	// 2. Esports worth naming. Tier is a filter here, not merely a ranking as
	// it is on the schedule panel: that panel must list every fixture, while
	// this row is a curation surface, and a chip reading "Exort Fiesta" claims
	// people are talking about something they are not. Fewer chips on a quiet
	// day is the honest outcome — tags fill the gap.
	$esports_url = gameindo_pillar_url( 'esports' );
	$deadline    = time() + (int) apply_filters( 'gameindo_hot_topics_days', 5 ) * DAY_IN_SECONDS;
	$floor       = gameindo_tier_rank( gameindo_prestige_floor() );

	$relevant = array();
	foreach ( gameindo_get_schedule( 'all', array( 'rank_by_tier' => true, 'tier_floor' => gameindo_prestige_floor() ) ) as $m ) {
		if ( empty( $m['league'] ) ) {
			continue;
		}
		if ( gameindo_tier_rank( isset( $m['tier'] ) ? $m['tier'] : '' ) > $floor ) {
			continue;
		}
		$live = ( isset( $m['status'] ) && 'running' === $m['status'] );
		if ( ! $live && ( ! $m['begin_ts'] || $m['begin_ts'] > $deadline ) ) {
			continue;
		}
		$m['is_live'] = $live;
		$relevant[]   = $m;
	}

	$game_url = function ( $game ) use ( $esports_url ) {
		return $game ? add_query_arg( 'game', $game, $esports_url ) . '#jadwal' : $esports_url;
	};

	// 2a. Teams currently playing.
	$team_max = (int) apply_filters( 'gameindo_hot_topics_team_max', 2 );
	$teams    = 0;
	foreach ( $relevant as $m ) {
		if ( $teams >= $team_max ) {
			break;
		}
		if ( ! $m['is_live'] ) {
			continue;
		}
		foreach ( array( 'a', 'b' ) as $side ) {
			if ( $teams >= $team_max ) {
				break;
			}
			if ( $push( $pinned, gameindo_team_chip_label( $m, $side ), $game_url( $m['game'] ), true ) ) {
				$teams++;
			}
		}
	}

	// 2b. Competitions, one game at a time before repeating any, so four chips
	// mean four titles rather than four LoL leagues on a busy LoL night.
	$esports_max = (int) apply_filters( 'gameindo_hot_topics_esports_max', 4 );
	$per_game    = array();
	$added       = 0;
	foreach ( array( 1, $esports_max ) as $cap ) {
		foreach ( $relevant as $m ) {
			if ( $added >= $esports_max ) {
				break 2;
			}
			$game = isset( $m['game'] ) ? $m['game'] : '';
			$used = isset( $per_game[ $game ] ) ? $per_game[ $game ] : 0;
			if ( $used >= $cap ) {
				continue;
			}
			$ok = $m['is_live']
				? $push( $pinned, gameindo_league_chip_label( $m ), $game_url( $game ), true )
				: $push( $pool, gameindo_league_chip_label( $m ), $game_url( $game ) );
			if ( $ok ) {
				$per_game[ $game ] = $used + 1;
				$added++;
			}
		}
	}

	// 3. Tags across recent coverage — recency-weighted, so a tag has to be in
	// current output to appear at all.
	$recent = get_posts( array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => (int) apply_filters( 'gameindo_hot_topics_pool', 40 ),
		'orderby'             => 'date',
		'order'               => 'DESC',
		'fields'              => 'ids',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
	) );

	$tally = array();
	foreach ( $recent as $pid ) {
		$terms = get_the_terms( $pid, 'post_tag' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			continue;
		}
		foreach ( $terms as $term ) {
			if ( ! isset( $tally[ $term->term_id ] ) ) {
				$tally[ $term->term_id ] = array( 'term' => $term, 'n' => 0 );
			}
			$tally[ $term->term_id ]['n']++;
		}
	}
	uasort( $tally, function ( $a, $b ) {
		return $b['n'] - $a['n'];
	} );
	foreach ( $tally as $row ) {
		if ( ! gameindo_is_topic_tag( $row['term'] ) ) {
			continue;
		}
		$link = get_term_link( $row['term'] );
		if ( ! is_wp_error( $link ) ) {
			$push( $pool, $row['term']->name, $link );
		}
	}

	// Nothing tagged in the recent pool — an archive imported in bulk looks
	// exactly like this — so fall back to the site's most-used tags. Pulling a
	// generous number keeps the rotation from cycling through the same few.
	$popular = get_terms( array(
		'taxonomy'   => 'post_tag',
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => (int) apply_filters( 'gameindo_hot_topics_tag_pool', 20 ),
		'hide_empty' => true,
	) );
	if ( ! is_wp_error( $popular ) ) {
		foreach ( $popular as $term ) {
			if ( ! gameindo_is_topic_tag( $term ) ) {
				continue;
			}
			$link = get_term_link( $term );
			if ( ! is_wp_error( $link ) ) {
				$push( $pool, $term->name, $link );
			}
		}
	}

	// Assemble: pinned first, then a window over the pool that advances with
	// the clock. Without this the row freezes on the same chips between match
	// days; with it, a reader coming back later sees a different slice.
	$period = max( MINUTE_IN_SECONDS, (int) apply_filters( 'gameindo_hot_topics_rotate', 10 * MINUTE_IN_SECONDS ) );
	$out    = array_slice( $pinned, 0, $limit );
	$slots  = $limit - count( $out );
	$total  = count( $pool );

	if ( $slots > 0 && $total > 0 ) {
		$offset = (int) floor( time() / $period ) % $total;
		for ( $i = 0; $i < min( $slots, $total ); $i++ ) {
			$out[] = $pool[ ( $offset + $i ) % $total ];
		}
	}

	// Cache for exactly one rotation step, so the transient never outlives the
	// window it was built for.
	set_transient( 'gi_hot_topics', $out, $period );
	return $out;
}

/**
 * Editor-pinned topics, from the CPT only.
 *
 * The assets/data/topics.json fixture is deliberately no longer consulted. It
 * ships demo chips (GTA 6, "MPL ID S16"), and with the row now assembled from
 * live data an empty CPT must mean "nothing pinned" — otherwise clearing the
 * topics in wp-admin silently resurrects the demo set from disk, and the row
 * can never be emptied at all.
 */
function gameindo_get_topics() {
	if ( function_exists( 'gameindo_core_get_topics' ) ) {
		return (array) gameindo_core_get_topics();
	}
	return array();
}

/**
 * How many days back the "Terpopuler" rails look. Only articles published
 * inside this window compete, so the rail is always about the current news
 * cycle and an old article can never squat it.
 */
function gameindo_popular_window_days() {
	return (int) apply_filters( 'gameindo_popular_window_days', 7 );
}

/**
 * How recent a post has to be to count as "baru" (badge on cards/rails).
 */
function gameindo_fresh_hours() {
	return (int) apply_filters( 'gameindo_fresh_hours', 48 );
}

function gameindo_post_age_hours( $post_id ) {
	$published = (int) get_post_time( 'U', true, $post_id );
	if ( ! $published ) {
		return PHP_INT_MAX;
	}
	return max( 0, ( time() - $published ) / HOUR_IN_SECONDS );
}

function gameindo_is_fresh( $post_id ) {
	return gameindo_post_age_hours( $post_id ) <= gameindo_fresh_hours();
}

/**
 * Rank posts (objects or IDs) for the "Terpopuler" rails: most-read first,
 * newest first whenever the reads figures tie. Since an article with no reads
 * figure counts as zero, a site that never fills that field in gets a rail
 * ordered purely newest-first — which is the intended behaviour, not a
 * degenerate case. Returns post IDs.
 */
function gameindo_rank_popular( $posts, $count = 3 ) {
	$ids = array();
	foreach ( $posts as $p ) {
		$ids[] = is_object( $p ) ? (int) $p->ID : (int) $p;
	}
	if ( empty( $ids ) ) {
		return array();
	}

	$reads = array();
	$times = array();
	foreach ( $ids as $id ) {
		$reads[ $id ] = gameindo_parse_reads( gameindo_meta( $id, 'reads' ) );
		$times[ $id ] = (int) get_post_time( 'U', true, $id );
	}

	usort( $ids, function ( $a, $b ) use ( $reads, $times ) {
		if ( $reads[ $a ] !== $reads[ $b ] ) {
			return $reads[ $b ] - $reads[ $a ];
		}
		return $times[ $b ] - $times[ $a ];
	} );

	return array_slice( $ids, 0, $count );
}

/**
 * Posts for the "Terpopuler" rails: the most-read articles of the last seven
 * days, newest first when reads tie. Returns an array of post IDs.
 *
 * $args: category (slug), author (ID), exclude (IDs), pool (posts to rank).
 * Posts without a reads figure are included — a new article is never filtered
 * out just because nobody has typed a popularity number for it yet.
 */
function gameindo_trending_posts( $count = 3, $args = array() ) {
	$args = wp_parse_args( $args, array(
		'category' => '',
		'author'   => 0,
		'exclude'  => array(),
		'pool'     => 60,
	) );

	$query = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => (int) $args['pool'],
		'orderby'             => 'date',
		'order'               => 'DESC',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
	);
	if ( $args['category'] ) {
		$query['category_name'] = $args['category'];
	}
	if ( $args['author'] ) {
		$query['author'] = (int) $args['author'];
	}
	if ( ! empty( $args['exclude'] ) ) {
		$query['post__not_in'] = array_map( 'intval', (array) $args['exclude'] );
	}

	// The pool is memoized per request: the homepage asks for this twice
	// (rail + mega-menu) and that shouldn't cost two round trips.
	static $pools = array();
	$key = md5( serialize( $query ) );

	if ( ! isset( $pools[ $key ] ) ) {
		$windowed = $query;
		$windowed['date_query'] = array( array( 'after' => gameindo_popular_window_days() . ' days ago' ) );
		$pools[ $key ] = array( 'window' => get_posts( $windowed ), 'all' => null );
	}

	$top = gameindo_rank_popular( $pools[ $key ]['window'], $count );

	// Quiet week: top the rail up with the newest articles from outside the
	// window, rather than re-ranking the whole archive by reads. Re-ranking is
	// what used to let a months-old article with a big reads figure squat the
	// top of the rail forever.
	if ( count( $top ) < $count ) {
		if ( null === $pools[ $key ]['all'] ) {
			$pools[ $key ]['all'] = get_posts( $query ); // already newest-first
		}
		foreach ( $pools[ $key ]['all'] as $p ) {
			if ( count( $top ) >= $count ) {
				break;
			}
			if ( ! in_array( (int) $p->ID, $top, true ) ) {
				$top[] = (int) $p->ID;
			}
		}
	}

	return $top;
}

/**
 * Parse a reads figure into a comparable integer. Understands the Indonesian
 * shorthand editors actually type: "128 rb" -> 128000, "1,2 jt" -> 1200000,
 * "9.500" -> 9500, "12k" -> 12000.
 */
function gameindo_parse_reads( $str ) {
	$s = strtolower( trim( (string) $str ) );
	if ( '' === $s || ! preg_match( '/([0-9][0-9.,]*)\s*(rb|ribu|k|jt|juta|m)?/', $s, $m ) ) {
		return 0;
	}

	$num  = $m[1];
	$unit = isset( $m[2] ) ? $m[2] : '';
	$mult = 1;
	if ( in_array( $unit, array( 'rb', 'ribu', 'k' ), true ) ) {
		$mult = 1000;
	} elseif ( in_array( $unit, array( 'jt', 'juta', 'm' ), true ) ) {
		$mult = 1000000;
	}

	if ( false !== strpos( $num, '.' ) && false !== strpos( $num, ',' ) ) {
		// "1.234,5" — dot groups thousands, comma is the decimal mark.
		$num = str_replace( ',', '.', str_replace( '.', '', $num ) );
	} elseif ( false !== strpos( $num, ',' ) ) {
		$num = preg_match( '/,\d{3}$/', $num ) ? str_replace( ',', '', $num ) : str_replace( ',', '.', $num );
	} elseif ( preg_match( '/\.\d{3}$/', $num ) ) {
		$num = str_replace( '.', '', $num );
	}

	return (int) round( (float) $num * $mult );
}

/**
 * Mega-menu columns. Column titles link to the pillar category; the sub-links
 * are the site's editorial IA (kept as fixed chrome, per the design), each
 * pointing at its pillar archive.
 */
/**
 * Shorten a headline to fit a menu column without breaking a word in half.
 */
function gameindo_shorten( $text, $max = 42 ) {
	$text = trim( wp_strip_all_tags( (string) $text ) );
	$len  = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
	if ( $len <= $max ) {
		return $text;
	}
	$cut = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
	$sp  = strrpos( $cut, ' ' );
	if ( $sp && $sp > $max * 0.6 ) {
		$cut = substr( $cut, 0, $sp );
	}
	return rtrim( $cut, " ,.;:-" ) . '…';
}

/**
 * Mega-menu entries for one pillar — what that section is actually covering,
 * rather than a fixed list of labels.
 *
 * The old column was four hard-coded strings ("Rilis Baru", "Review", …) that
 * every one of them linked to the same pillar archive, so four different links
 * went to one destination. Now, per pillar:
 *   1. Esports leads with competitions that are live or imminent.
 *   2. Then tags actually used by that pillar's recent articles.
 *   3. Then recent headlines, which is what fills the column in practice —
 *      three of the five pillars currently have no tagged posts at all, so a
 *      purely tag-driven menu would render empty columns.
 *
 * Every entry is a distinct destination: a schedule view, a tag archive, or
 * the article itself.
 */
function gameindo_megamenu_column( $slug, $max = 4 ) {
	$out  = array();
	$seen = array();

	$push = function ( $label, $url ) use ( &$out, &$seen, $max ) {
		$label = trim( wp_strip_all_tags( (string) $label ) );
		$key   = function_exists( 'mb_strtolower' ) ? mb_strtolower( $label ) : strtolower( $label );
		if ( '' === $label || isset( $seen[ $key ] ) || count( $out ) >= $max ) {
			return false;
		}
		$seen[ $key ] = true;
		$out[]        = array( 'label' => $label, 'url' => $url );
		return true;
	};

	if ( 'esports' === $slug ) {
		$esports_url = gameindo_pillar_url( 'esports' );
		$floor       = gameindo_tier_rank( gameindo_prestige_floor() );
		$deadline    = time() + 5 * DAY_IN_SECONDS;
		$taken       = 0;
		foreach ( gameindo_get_schedule( 'all', array( 'rank_by_tier' => true, 'tier_floor' => gameindo_prestige_floor() ) ) as $m ) {
			if ( $taken >= 2 ) {
				break;
			}
			if ( empty( $m['league'] ) || gameindo_tier_rank( isset( $m['tier'] ) ? $m['tier'] : '' ) > $floor ) {
				continue;
			}
			$live = ( isset( $m['status'] ) && 'running' === $m['status'] );
			if ( ! $live && ( ! $m['begin_ts'] || $m['begin_ts'] > $deadline ) ) {
				continue;
			}
			$url = ! empty( $m['game'] ) ? add_query_arg( 'game', $m['game'], $esports_url ) . '#jadwal' : $esports_url;
			if ( $push( gameindo_league_chip_label( $m ), $url ) ) {
				$taken++;
			}
		}
	}

	$posts = get_posts( array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => 60,
		'category_name'       => $slug,
		'orderby'             => 'date',
		'order'               => 'DESC',
		'fields'              => 'ids',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
	) );

	$tally = array();
	foreach ( $posts as $pid ) {
		$terms = get_the_terms( $pid, 'post_tag' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			continue;
		}
		foreach ( $terms as $term ) {
			if ( ! gameindo_is_topic_tag( $term ) ) {
				continue;
			}
			if ( ! isset( $tally[ $term->term_id ] ) ) {
				$tally[ $term->term_id ] = array( 'term' => $term, 'n' => 0 );
			}
			$tally[ $term->term_id ]['n']++;
		}
	}
	uasort( $tally, function ( $a, $b ) {
		return $b['n'] - $a['n'];
	} );
	foreach ( $tally as $row ) {
		$link = get_term_link( $row['term'] );
		if ( ! is_wp_error( $link ) ) {
			$push( $row['term']->name, $link );
		}
	}

	foreach ( $posts as $pid ) {
		if ( count( $out ) >= $max ) {
			break;
		}
		$push( gameindo_shorten( get_the_title( $pid ) ), get_permalink( $pid ) );
	}

	return $out;
}

/**
 * Render the five pillar columns. Cached as markup: this now runs on every
 * page rather than only the homepage, and rebuilding it costs a query per
 * pillar.
 */
function gameindo_render_megamenu_columns() {
	$html = get_transient( 'gi_megamenu_cols' );

	if ( false === $html ) {
		ob_start();
		foreach ( gameindo_pillars() as $slug => $name ) {
			$url = gameindo_pillar_url( $slug );
			echo '<div class="gi-megamenu__col" data-pillar="' . esc_attr( $slug ) . '">';
			echo '<a class="gi-megamenu__col-title" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>';
			foreach ( gameindo_megamenu_column( $slug ) as $item ) {
				echo '<a href="' . esc_url( $item['url'] ) . '" title="' . esc_attr( $item['label'] ) . '">' . esc_html( $item['label'] ) . '</a>';
			}
			echo '<a class="gi-megamenu__col-all" href="' . esc_url( $url ) . '">Semua ' . esc_html( $name ) . ' →</a>';
			echo '</div>';
		}
		$html = ob_get_clean();
		set_transient( 'gi_megamenu_cols', $html, 10 * MINUTE_IN_SECONDS );
	}

	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Load a bundled JSON fixture shipped with the theme (assets/data/*.json).
 */
function gameindo_fixture( $file, $fallback = array() ) {
	$path = GAMEINDO_DIR . '/assets/data/' . $file;
	if ( ! file_exists( $path ) ) {
		return $fallback;
	}
	$json = json_decode( file_get_contents( $path ), true );
	return null === $json ? $fallback : $json;
}
