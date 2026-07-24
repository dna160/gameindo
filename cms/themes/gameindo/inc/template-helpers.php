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

	$html  = '<a class="gi-rank' . ( $thumb ? '' : ' gi-rank--no-thumb' ) . '" data-pillar="' . esc_attr( $pillar ) . '" href="' . esc_url( get_permalink( $post_id ) ) . '">';
	$html .= '<span class="gi-rank__num">' . esc_html( $num ) . '</span>';
	$html .= $thumb_html;
	$html .= '<span><span class="gi-rank__category">' . esc_html( $cat ) . '</span>';
	$html .= '<span class="gi-rank__title">' . esc_html( get_the_title( $post_id ) ) . '</span>';
	$html .= '<span class="gi-rank__meta">' . esc_html( $reads ? $reads : '—' ) . ' dibaca</span></span>';
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
	$url  = ! empty( $item['url'] ) ? $item['url'] : '#';
	return '<a class="gi-ticker__item" href="' . esc_url( $url ) . '">' . esc_html( $item['text'] ) . '</a>';
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

function gameindo_get_ticker() {
	if ( function_exists( 'gameindo_core_get_ticker' ) ) {
		$data = gameindo_core_get_ticker();
		if ( ! empty( $data ) ) {
			return $data;
		}
	}
	return gameindo_fixture( 'ticker.json', array() );
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
 * Trending posts, ranked by the _gi_reads popularity string (parsed like the
 * original parseInt()). Falls back to most-recent when no reads are set.
 * Returns an array of post IDs.
 */
function gameindo_trending_posts( $count = 3 ) {
	$q = new WP_Query( array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 60,
		'meta_key'       => '_gi_reads',
		'meta_compare'   => 'EXISTS',
		'no_found_rows'  => true,
		'fields'         => 'ids',
	) );
	$ids = $q->posts;

	if ( empty( $ids ) ) {
		$recent = new WP_Query( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $count,
			'no_found_rows'  => true,
			'fields'         => 'ids',
		) );
		return $recent->posts;
	}

	usort( $ids, function ( $a, $b ) {
		return gameindo_parse_reads( get_post_meta( $b, '_gi_reads', true ) )
			 - gameindo_parse_reads( get_post_meta( $a, '_gi_reads', true ) );
	} );

	return array_slice( $ids, 0, $count );
}

/**
 * Parse the leading integer out of a reads string ("128 rb" -> 128).
 */
function gameindo_parse_reads( $str ) {
	if ( preg_match( '/\d+/', (string) $str, $m ) ) {
		return (int) $m[0];
	}
	return 0;
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
