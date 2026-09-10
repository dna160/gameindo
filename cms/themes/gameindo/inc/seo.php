<?php
/**
 * SEO — meta description, Open Graph / Twitter Card tags, canonical URL,
 * robots, and JSON-LD structured data (Organization, WebSite, Article).
 *
 * Everything here is server-rendered, no plugin required. It steps aside
 * automatically the moment an SEO plugin is active (see gameindo_seo_active())
 * — the goal is a site that's well-tagged today and never double-tags itself
 * once Yoast/RankMath/etc. is installed later.
 *
 * @package GameIndo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether this theme should render its own SEO tags. False the moment a
 * dedicated SEO plugin is active — those all emit the same tags (meta
 * description, OG, canonical, JSON-LD), and two competing copies of each is
 * worse than one, whichever one is "right". Detection is by the constant/
 * class each plugin defines on load, which is stable across their versions
 * (documented, hooked-into approach — not scraping their option tables).
 */
function gameindo_seo_active() {
	$other_plugin_active = defined( 'WPSEO_VERSION' )       // Yoast SEO
		|| defined( 'RANK_MATH_VERSION' )                    // Rank Math
		|| class_exists( 'RankMath' )
		|| defined( 'AIOSEO_VERSION' )                       // All in One SEO
		|| class_exists( 'SEOPress' );                       // SEOPress
	return ! (bool) apply_filters( 'gameindo_seo_disable', $other_plugin_active );
}

/**
 * A short, human meta description for the current page. Reuses the same
 * excerpt/trim logic cards already use, so there's no second content-summary
 * codepath to keep in sync with the first.
 */
function gameindo_seo_description() {
	// Works for both: gameindo_get_excerpt() prefers the manual excerpt field
	// when set and falls back to a trimmed post_content otherwise, for any
	// post type — the same logic single.php and page.php already render with.
	if ( is_singular( 'post' ) || is_page() ) {
		return gameindo_get_excerpt( get_the_ID(), 32 );
	}
	if ( is_search() ) {
		return sprintf( 'Hasil pencarian untuk "%s" di GameIndo.', get_search_query() );
	}
	if ( is_author() ) {
		$bio = get_the_author_meta( 'description' );
		return $bio ? wp_trim_words( wp_strip_all_tags( $bio ), 32, '…' )
			: sprintf( 'Artikel oleh %s di GameIndo.', get_the_author_meta( 'display_name' ) );
	}
	if ( is_category() || is_tag() || is_tax() ) {
		$obj  = get_queried_object();
		$desc = ( $obj && ! empty( $obj->description ) ) ? $obj->description : '';
		if ( $desc ) {
			return wp_trim_words( wp_strip_all_tags( $desc ), 32, '…' );
		}
		$name = ( $obj && isset( $obj->name ) ) ? $obj->name : single_cat_title( '', false );
		return sprintf( 'Berita dan artikel %s terbaru di GameIndo.', $name );
	}
	return get_bloginfo( 'description' ); // front page
}

/**
 * The share image for the current page: featured image when there is one,
 * otherwise the site logo. Returns array( url, width, height ) or null.
 *
 * A 1200×800 crop is close enough to the 1200×630 platforms ask for that
 * cropping further isn't worth a new image size; every social platform
 * letterboxes or center-crops slightly oversized images without complaint.
 * The wordmark logo is a poor substitute — it's a wide, short lockup, not a
 * social-card shape — but it is the only sitewide branded asset that exists,
 * so it beats the alternative (no image, which is what non-singular pages
 * would ship without it). Swapping in a purpose-made 1200×630 default share
 * image, if one gets designed later, is a one-line change to $fallback below.
 */
function gameindo_seo_image() {
	if ( is_singular( 'post' ) && has_post_thumbnail() ) {
		$src = wp_get_attachment_image_src( get_post_thumbnail_id(), 'gameindo-hero' );
		if ( $src ) {
			return array( $src[0], $src[1], $src[2] );
		}
	}
	$fallback = GAMEINDO_URI . '/assets/logo/gameindo-logo.png';
	return array( $fallback, 256, 38 );
}

/**
 * Canonical URL for the current page. Archives strip every query arg —
 * `?platform=`, `?game=`, search's `?s=` pagination artifacts — so a filter
 * chip is still crawlable and indexable in its own right (robots stays
 * index,follow there; see gameindo_seo_robots()) while every variant of one
 * archive canonicalizes to the same clean URL instead of splitting ranking
 * signal across a dozen near-duplicate query strings.
 */
function gameindo_seo_canonical() {
	if ( is_singular() ) {
		return get_permalink();
	}
	if ( is_search() ) {
		return home_url( '/?s=' . rawurlencode( get_search_query() ) );
	}
	if ( is_category() || is_tag() || is_tax() ) {
		return get_term_link( get_queried_object() );
	}
	if ( is_author() ) {
		return get_author_posts_url( get_queried_object_id() );
	}
	if ( is_front_page() ) {
		return home_url( '/' );
	}
	return home_url( add_query_arg( array(), $GLOBALS['wp']->request ?? '' ) );
}

/**
 * robots meta. Only internal search results are held back — dynamic,
 * query-dependent pages with no stable content of their own are the textbook
 * case for noindex,follow (stay out of the index, but keep crawling the links
 * on the page). Everything else the theme renders is real, stable content.
 */
function gameindo_seo_robots() {
	if ( is_search() ) {
		return 'noindex,follow';
	}
	return 'index,follow';
}

/**
 * The byline for the current page — a real person for an article, the site
 * itself everywhere else. Same value backs <meta name="author">, twitter's
 * byline convention, and (for articles) article:author.
 */
function gameindo_seo_author_name() {
	if ( is_singular( 'post' ) ) {
		return get_the_author_meta( 'display_name', get_post_field( 'post_author', get_the_ID() ) );
	}
	if ( is_author() ) {
		return get_the_author_meta( 'display_name', get_queried_object_id() );
	}
	return get_bloginfo( 'name' );
}

/**
 * Comma-separated topical keywords. Google and Bing have both ignored this
 * tag for ranking since ~2009 — it's included because some SEO checklists
 * and third-party audit tools still look for it, and a correct-but-inert
 * tag costs nothing. Sourced from real taxonomy (tags/subcategory/pillar),
 * never invented, so it can't drift out of sync with the actual content.
 */
function gameindo_seo_keywords() {
	if ( is_singular( 'post' ) ) {
		$id  = get_the_ID();
		$kws = array();
		$sub = gameindo_meta( $id, 'subcategory' );
		if ( $sub ) {
			$kws[] = $sub;
		}
		$kws[] = gameindo_pillar_name( gameindo_get_pillar( $id ) );
		foreach ( (array) get_the_tags( $id ) as $gi_tag ) {
			$kws[] = $gi_tag->name;
		}
		$kws[] = get_bloginfo( 'name' );
		return implode( ', ', array_unique( array_filter( $kws ) ) );
	}
	if ( is_category() || is_tag() || is_tax() ) {
		$obj  = get_queried_object();
		$name = ( $obj && isset( $obj->name ) ) ? $obj->name : single_cat_title( '', false );
		return implode( ', ', array_filter( array( $name, get_bloginfo( 'name' ) ) ) );
	}
	$kws = array_values( gameindo_nav_pillars() );
	$kws[] = get_bloginfo( 'name' );
	return implode( ', ', $kws );
}

/**
 * Print the description, OG, Twitter, canonical, and robots tags.
 */
function gameindo_seo_meta_tags() {
	if ( ! gameindo_seo_active() ) {
		return;
	}

	$description = gameindo_seo_description();
	$canonical   = gameindo_seo_canonical();
	$robots      = gameindo_seo_robots();
	list( $image, $img_w, $img_h ) = gameindo_seo_image();
	$is_article  = is_singular( 'post' );
	$title       = $is_article ? get_the_title() : wp_get_document_title();
	if ( is_front_page() ) {
		$title = get_bloginfo( 'name' ) . ' — ' . get_bloginfo( 'description' );
	}

	$author   = gameindo_seo_author_name();
	$keywords = gameindo_seo_keywords();
	$img_alt  = $is_article ? gameindo_image_alt( get_the_ID() ) : get_bloginfo( 'name' );

	echo "\n<!-- GameIndo SEO -->\n";
	echo '<meta name="description" content="' . esc_attr( $description ) . "\">\n";
	if ( $keywords ) {
		echo '<meta name="keywords" content="' . esc_attr( $keywords ) . "\">\n";
	}
	echo '<meta name="author" content="' . esc_attr( $author ) . "\">\n";
	echo '<meta name="publisher" content="' . esc_attr( get_bloginfo( 'name' ) ) . "\">\n";
	echo '<meta name="robots" content="' . esc_attr( $robots ) . "\">\n";
	echo '<link rel="canonical" href="' . esc_url( $canonical ) . "\">\n";

	echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . "\">\n";
	echo '<meta property="og:locale" content="id_ID">' . "\n";
	echo '<meta property="og:type" content="' . ( $is_article ? 'article' : 'website' ) . "\">\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . "\">\n";
	echo '<meta property="og:description" content="' . esc_attr( $description ) . "\">\n";
	echo '<meta property="og:url" content="' . esc_url( $canonical ) . "\">\n";
	echo '<meta property="og:image" content="' . esc_url( $image ) . "\">\n";
	echo '<meta property="og:image:width" content="' . (int) $img_w . "\">\n";
	echo '<meta property="og:image:height" content="' . (int) $img_h . "\">\n";
	echo '<meta property="og:image:alt" content="' . esc_attr( $img_alt ) . "\">\n";

	if ( $is_article ) {
		$gi_id = get_the_ID();
		echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c' ) ) . "\">\n";
		echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c' ) ) . "\">\n";
		echo '<meta property="article:author" content="' . esc_url( get_author_posts_url( get_post_field( 'post_author', $gi_id ) ) ) . "\">\n";
		echo '<meta property="article:publisher" content="' . esc_url( home_url( '/' ) ) . "\">\n";
		$pillar = gameindo_get_pillar( $gi_id );
		echo '<meta property="article:section" content="' . esc_attr( gameindo_pillar_name( $pillar ) ) . "\">\n";
		foreach ( (array) get_the_tags() as $gi_tag ) {
			echo '<meta property="article:tag" content="' . esc_attr( $gi_tag->name ) . "\">\n";
		}
	}

	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $title ) . "\">\n";
	echo '<meta name="twitter:description" content="' . esc_attr( $description ) . "\">\n";
	echo '<meta name="twitter:image" content="' . esc_url( $image ) . "\">\n";
	echo "<!-- /GameIndo SEO -->\n";
}
add_action( 'wp_head', 'gameindo_seo_meta_tags', 1 );

/**
 * JSON-LD: Organization + WebSite (every page — this is what tells Google
 * "GameIndo" is the publisher, and enables the sitelinks search box), plus
 * NewsArticle on top of that for a single post (headline, image, author,
 * dates, publisher — the fields Google's rich-result article validator
 * checks for).
 */
function gameindo_seo_json_ld() {
	if ( ! gameindo_seo_active() ) {
		return;
	}

	$logo = GAMEINDO_URI . '/assets/logo/gameindo-logo.png';
	$graph = array(
		array(
			'@type' => 'Organization',
			'@id'   => home_url( '/#organization' ),
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
			'logo'  => array( '@type' => 'ImageObject', 'url' => $logo ),
		),
		array(
			'@type'           => 'WebSite',
			'@id'             => home_url( '/#website' ),
			'name'            => get_bloginfo( 'name' ),
			'url'             => home_url( '/' ),
			'publisher'       => array( '@id' => home_url( '/#organization' ) ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			),
		),
	);

	if ( is_singular( 'post' ) ) {
		$id       = get_the_ID();
		list( $image, $img_w, $img_h ) = gameindo_seo_image();
		$graph[] = array(
			'@type'            => 'NewsArticle',
			'@id'              => get_permalink() . '#article',
			'mainEntityOfPage' => array( '@type' => 'WebPage', '@id' => get_permalink() ),
			'headline'         => get_the_title(),
			'description'      => gameindo_get_excerpt( $id, 32 ),
			'image'            => array( '@type' => 'ImageObject', 'url' => $image, 'width' => $img_w, 'height' => $img_h ),
			'datePublished'    => get_the_date( 'c' ),
			'dateModified'     => get_the_modified_date( 'c' ),
			'author'           => array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', get_post_field( 'post_author', $id ) ),
			),
			'publisher'        => array( '@id' => home_url( '/#organization' ) ),
			'articleSection'   => gameindo_pillar_name( gameindo_get_pillar( $id ) ),
		);
	}

	echo '<script type="application/ld+json">'
		. wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		. "</script>\n";
}
add_action( 'wp_head', 'gameindo_seo_json_ld', 2 );

/**
 * "Post Title — GameIndo" instead of WP's default "Post Title - GameIndo" —
 * matches the em dash used in headlines/titles everywhere else on the site.
 * Purely cosmetic; WP's own title/site-name ordering (title first, brand
 * last) already follows current best practice, so nothing else here changes.
 */
function gameindo_seo_title_separator( $sep ) {
	return gameindo_seo_active() ? '—' : $sep;
}
add_filter( 'document_title_separator', 'gameindo_seo_title_separator' );

/**
 * robots.txt: an explicit, deliberate allow for search engines and the major
 * AI/answer-engine crawlers — GameIndo wants to be crawled and cited by
 * them, not just left un-blocked by omission — on top of the one thing
 * every site should keep out, /wp-admin/. Deferred if an SEO plugin is
 * active (those all ship their own robots.txt editor — two rewrites of the
 * same file is worse than one) and if the site's own "discourage search
 * engines" setting is on ($public === false): that's a deliberate
 * site-owner choice this shouldn't override.
 *
 * Search results are intentionally NOT disallowed here even though they're
 * noindex — see gameindo_seo_robots(). Blocking a URL in robots.txt AND
 * relying on its meta robots tag to keep it out of the index is a classic
 * SEO foot-gun: a blocked page is never fetched, so Google never sees the
 * noindex tag and can still index the bare URL from links pointing to it,
 * usually with no snippet. Letting it be crawled is what makes the noindex
 * tag actually work.
 */
function gameindo_seo_robots_txt( $output, $public ) {
	if ( ! gameindo_seo_active() || ! $public ) {
		return $output;
	}

	$lines   = array( 'User-agent: *', 'Disallow: /wp-admin/', 'Allow: /wp-admin/admin-ajax.php', '' );
	$ai_bots = array(
		'GPTBot',
		'ChatGPT-User',
		'OAI-SearchBot',
		'Google-Extended',
		'ClaudeBot',
		'anthropic-ai',
		'PerplexityBot',
		'CCBot',
		'Applebot-Extended',
	);
	foreach ( $ai_bots as $bot ) {
		$lines[] = 'User-agent: ' . $bot;
		$lines[] = 'Allow: /';
		$lines[] = '';
	}
	$lines[] = 'Sitemap: ' . home_url( '/wp-sitemap.xml' );

	return implode( "\n", $lines ) . "\n";
}
add_filter( 'robots_txt', 'gameindo_seo_robots_txt', 10, 2 );

/**
 * /sitemap.xml is the URL every SEO tool, backlink checker, and habit
 * expects; WordPress core's own sitemap — on by default since WP 5.5, no
 * plugin needed, and already exactly what was asked for (new posts appear
 * automatically because it's query-driven rather than a cached file, it's
 * split by post type *and* taxonomy so pillars — which are categories —
 * get their own sub-sitemap, and <lastmod> reads straight from
 * post_modified_gmt so it changes the moment a post is edited) — lives at
 * /wp-sitemap.xml instead. This 301-redirects the conventional path to it,
 * so either URL works and crawlers that follow the redirect index the
 * canonical one. Deferred if an SEO plugin is active: those disable WP's
 * native sitemap and serve their own, often at this exact path, so forcing
 * this redirect on top of that would send crawlers to a sitemap the plugin
 * turned off.
 */
function gameindo_seo_sitemap_alias() {
	if ( ! gameindo_seo_active() ) {
		return;
	}
	$path = trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
	if ( 'sitemap.xml' === $path ) {
		wp_safe_redirect( home_url( '/wp-sitemap.xml' ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'gameindo_seo_sitemap_alias' );
