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

/**
 * Match-panel row. Mirrors templates.js `matchPanelRow()`.
 */
function gameindo_match_panel_row( $m ) {
	$score = ( null === $m['score_a'] || '' === $m['score_a'] || null === $m['score_b'] || '' === $m['score_b'] )
		? 'vs'
		: $m['score_a'] . ' — ' . $m['score_b'];
	$live  = ( isset( $m['status'] ) && 'live' === $m['status'] ) ? ' gi-matchpanel__status--live' : '';
	$html  = '<div class="gi-matchpanel__row">';
	$html .= '<span class="gi-matchpanel__team">' . esc_html( $m['team_a'] ) . '</span>';
	$html .= '<span class="gi-matchpanel__score">' . esc_html( $score ) . '</span>';
	$html .= '<span class="gi-matchpanel__team gi-matchpanel__team--right">' . esc_html( $m['team_b'] ) . '</span>';
	$html .= '<span class="gi-matchpanel__status' . $live . '">' . esc_html( $m['status_label'] ) . '</span>';
	$html .= '</div>';
	return $html;
}

/**
 * Mobile match card. Mirrors templates.js `mobileMatchCard()`.
 */
function gameindo_mobile_match_card( $m, $competition ) {
	$score = ( null === $m['score_a'] || '' === $m['score_a'] ) ? 'vs' : $m['score_a'] . ' — ' . $m['score_b'];
	$teams = $m['team_a'] . ' ' . $score . ' ' . $m['team_b'];
	$live  = ( isset( $m['status'] ) && 'live' === $m['status'] );
	$html  = '<div class="gi-mobile-matchcard' . ( $live ? ' gi-mobile-matchcard--live' : '' ) . '">';
	$html .= '<span class="gi-mobile-matchcard__status' . ( $live ? ' gi-mobile-matchcard__status--live' : '' ) . '">' . esc_html( $m['status_label'] ) . '</span>';
	$html .= '<span class="gi-mobile-matchcard__teams">' . esc_html( $teams ) . '</span>';
	$html .= '<span class="gi-mobile-matchcard__comp">' . esc_html( $competition ) . '</span>';
	$html .= '</div>';
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
 * Live ticker feed = editor-pinned items (Live Ticker CPT) merged with the
 * newest articles, ordered newest-first. Ordering by time is what makes this
 * a live feed: a pin published today leads the marquee, an article published
 * ten minutes ago overtakes a pin from last month, and nobody has to hand-write
 * a ticker entry for a new article. Items inside the freshness window get a
 * "Baru" badge. The bundled JSON fixture is only a last resort on an empty site.
 */
function gameindo_get_ticker() {
	$items = array();

	if ( function_exists( 'gameindo_core_get_ticker' ) ) {
		$pinned = array_slice( gameindo_core_get_ticker(), 0, (int) apply_filters( 'gameindo_ticker_pinned_max', 4 ) );
		foreach ( $pinned as $p ) {
			$items[] = array(
				'id'   => isset( $p['id'] ) ? $p['id'] : 0,
				'text' => isset( $p['text'] ) ? $p['text'] : '',
				'url'  => isset( $p['url'] ) ? $p['url'] : '',
				'time' => isset( $p['id'] ) ? (int) get_post_time( 'U', true, $p['id'] ) : 0,
				'kind' => 'pinned',
			);
		}
	}

	$article_urls = array();
	foreach ( gameindo_latest_ticker_items( (int) apply_filters( 'gameindo_ticker_latest_count', 8 ) ) as $a ) {
		$a['kind']                                   = 'article';
		$article_urls[ gameindo_ticker_url_key( $a['url'] ) ] = true;
		$items[]                                     = $a;
	}

	// Newest first; usort isn't stable, so the original position breaks ties.
	$order = array_keys( $items );
	usort( $order, function ( $a, $b ) use ( $items ) {
		if ( $items[ $a ]['time'] === $items[ $b ]['time'] ) {
			return $a - $b;
		}
		return $items[ $b ]['time'] - $items[ $a ]['time'];
	} );

	$seen_text    = array();
	$feed         = array();
	$fresh_before = time() - ( gameindo_fresh_hours() * HOUR_IN_SECONDS );

	foreach ( $order as $i ) {
		$item    = $items[ $i ];
		$by_text = strtolower( trim( (string) $item['text'] ) );
		if ( '' === $by_text || isset( $seen_text[ $by_text ] ) ) {
			continue;
		}
		// A pin that just links to an article already in the run is redundant.
		// URLs are only matched against article permalinks — two pins may well
		// point at the same category page and both still deserve a slot.
		if ( 'pinned' === $item['kind'] ) {
			$key = gameindo_ticker_url_key( $item['url'] );
			if ( '' !== $key && isset( $article_urls[ $key ] ) ) {
				continue;
			}
		}
		$seen_text[ $by_text ] = true;

		$item['badge'] = ( $item['time'] && $item['time'] >= $fresh_before ) ? 'Baru' : '';
		$feed[]        = $item;
	}

	if ( empty( $feed ) ) {
		return gameindo_fixture( 'ticker.json', array() );
	}
	return array_slice( $feed, 0, (int) apply_filters( 'gameindo_ticker_max', 12 ) );
}

/**
 * Normalized key for comparing ticker URLs (scheme/trailing slash/case).
 */
function gameindo_ticker_url_key( $url ) {
	$url = untrailingslashit( strtolower( trim( (string) $url ) ) );
	return preg_replace( '#^https?://#', '', $url );
}

function gameindo_get_topics() {
	if ( function_exists( 'gameindo_core_get_topics' ) ) {
		$data = gameindo_core_get_topics();
		if ( ! empty( $data ) ) {
			return $data;
		}
	}
	return gameindo_fixture( 'topics.json', array() );
}

/**
 * Tuning knobs for the "Terpopuler" ranking. Filterable so the mix can be
 * tweaked per site (or seasonally) without touching the theme.
 *
 * - window_days:      popularity is measured over a rolling window, the way
 *                     newsroom "most read" rails work — not all-time, so a
 *                     year-old hit can't squat the rail forever.
 * - half_life_hours:  a post's freshness score halves every N hours.
 * - weight_reads:     how much the editorial reads figure counts.
 * - weight_fresh:     how much recency counts. Fresh outweighs reads by
 *                     default so newly published articles surface fast.
 * - fresh_slots:      rows reserved for the newest article(s) — a hard
 *                     guarantee that what just went live is always visible.
 */
function gameindo_trending_config() {
	return apply_filters( 'gameindo_trending_config', array(
		'window_days'     => 30,
		'half_life_hours' => 36,
		'weight_reads'    => 0.45,
		'weight_fresh'    => 0.55,
		'fresh_slots'     => 1,
	) );
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
 * Trending score in 0..1 — a blend of normalized popularity and exponential
 * recency decay. $max_reads is the loudest reads figure in the pool being
 * ranked, so the popularity axis is relative to the current field.
 */
function gameindo_trending_score( $post_id, $max_reads = 0 ) {
	$cfg = gameindo_trending_config();

	$fresh = pow( 0.5, gameindo_post_age_hours( $post_id ) / max( 1, (float) $cfg['half_life_hours'] ) );

	// sqrt() compresses the popularity axis: one runaway article lifts the
	// whole field instead of flattening everything else to zero.
	$reads = gameindo_parse_reads( gameindo_meta( $post_id, 'reads' ) );
	$pop   = $max_reads > 0 ? sqrt( min( 1, $reads / $max_reads ) ) : 0.0;

	return ( (float) $cfg['weight_reads'] * $pop ) + ( (float) $cfg['weight_fresh'] * $fresh );
}

/**
 * Rank an already-fetched set of posts (objects or IDs) by trending score and
 * return the top $count IDs. The newest post(s) in the pool are guaranteed a
 * slot even if their score alone wouldn't earn one.
 */
function gameindo_rank_trending( $posts, $count = 3 ) {
	$ids = array();
	foreach ( $posts as $p ) {
		$ids[] = is_object( $p ) ? (int) $p->ID : (int) $p;
	}
	if ( empty( $ids ) ) {
		return array();
	}

	$max_reads = 0;
	foreach ( $ids as $id ) {
		$max_reads = max( $max_reads, gameindo_parse_reads( gameindo_meta( $id, 'reads' ) ) );
	}

	$scores = array();
	foreach ( $ids as $id ) {
		$scores[ $id ] = gameindo_trending_score( $id, $max_reads );
	}

	$ranked = $ids;
	usort( $ranked, function ( $a, $b ) use ( $scores ) {
		if ( $scores[ $a ] === $scores[ $b ] ) {
			return get_post_time( 'U', true, $b ) - get_post_time( 'U', true, $a );
		}
		return ( $scores[ $a ] < $scores[ $b ] ) ? 1 : -1;
	} );

	$top = array_slice( $ranked, 0, $count );

	// Reserve slots for the newest articles: swap out the weakest entries for
	// any brand-new post that didn't make the cut on score alone.
	$cfg     = gameindo_trending_config();
	$reserve = min( (int) $cfg['fresh_slots'], $count );
	if ( $reserve > 0 ) {
		$newest = $ids;
		usort( $newest, function ( $a, $b ) {
			return get_post_time( 'U', true, $b ) - get_post_time( 'U', true, $a );
		} );
		foreach ( array_slice( $newest, 0, $reserve ) as $fresh_id ) {
			if ( in_array( $fresh_id, $top, true ) ) {
				continue;
			}
			array_pop( $top );
			$top[] = $fresh_id;
			usort( $top, function ( $a, $b ) use ( $scores ) {
				if ( $scores[ $a ] === $scores[ $b ] ) {
					return get_post_time( 'U', true, $b ) - get_post_time( 'U', true, $a );
				}
				return ( $scores[ $a ] < $scores[ $b ] ) ? 1 : -1;
			} );
		}
	}

	return $top;
}

/**
 * Trending posts for the "Terpopuler" rails. Returns an array of post IDs.
 *
 * $args: category (slug), author (ID), exclude (IDs), pool (posts to score).
 * Posts without a reads figure are included — a new article is never filtered
 * out just because nobody has typed a popularity number for it yet.
 */
function gameindo_trending_posts( $count = 3, $args = array() ) {
	$cfg  = gameindo_trending_config();
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

	// The pool is memoized per request: the homepage asks for trending twice
	// (rail + mega-menu) and that shouldn't cost two round trips.
	static $pools = array();
	$key = md5( serialize( $query ) );

	if ( ! isset( $pools[ $key ] ) ) {
		$windowed = $query;
		$windowed['date_query'] = array( array( 'after' => (int) $cfg['window_days'] . ' days ago' ) );
		$pools[ $key ] = array( 'window' => get_posts( $windowed ), 'all' => null );
	}

	// Rolling window first; widen to all-time on quiet sites so the rail is
	// never short.
	$posts = $pools[ $key ]['window'];
	if ( count( $posts ) < $count ) {
		if ( null === $pools[ $key ]['all'] ) {
			$pools[ $key ]['all'] = get_posts( $query );
		}
		$posts = $pools[ $key ]['all'];
	}

	return gameindo_rank_trending( $posts, $count );
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
function gameindo_render_megamenu_columns() {
	$columns = array(
		'home'          => array( 'Rilis Baru', 'Review', 'Guide & Tips', 'Gim Mobile' ),
		'esports'       => array( 'MPL ID', 'Valorant', 'Free Fire', 'Jadwal & Klasemen' ),
		'streamer'      => array( 'Kreator Lokal', 'VTuber', 'Drama & Isu', 'Tips Streaming' ),
		'tech'          => array( 'PC & Komponen', 'Handheld', 'Smartphone', 'Rekomendasi' ),
		'entertainment' => array( 'Anime', 'Film & Series', 'Pop Culture', 'Event' ),
	);
	$pillars = gameindo_pillars();
	foreach ( $columns as $slug => $links ) {
		$term = get_category_by_slug( $slug );
		$url  = ( 'home' === $slug ) ? home_url( '/' ) : ( $term ? get_category_link( $term->term_id ) : home_url( '/category/' . $slug . '/' ) );
		echo '<div class="gi-megamenu__col" data-pillar="' . esc_attr( $slug ) . '">';
		echo '<span class="gi-megamenu__col-title">' . esc_html( $pillars[ $slug ] ) . '</span>';
		foreach ( $links as $label ) {
			echo '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</div>';
	}
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
