<?php
/**
 * GameIndo content importer — run via wp-cli:
 *   wp eval-file /import/import.php
 *
 * Seeds the local WordPress install from the original static fixtures:
 * pillar categories, authors, media, 46 posts (with GameIndo meta + tags),
 * the esports widgets (ticker/topics/matches/standings), nav menus, and a
 * few static pages. Idempotent by slug/title so re-running is safe.
 */

if ( ! defined( 'WP_CLI' ) ) {
	echo "Run through wp-cli: wp eval-file /import/import.php\n";
	return;
}

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

define( 'GI_DATA', '/var/www/html/wp-content/gameindo-data' );
define( 'GI_ASSETS', '/var/www/html/wp-content/gameindo-assets' );

function gi_json( $file ) {
	return json_decode( file_get_contents( GI_DATA . '/' . $file ), true );
}

/**
 * Find an existing post/page by exact title (replacement for the deprecated
 * get_page_by_title()).
 */
function gi_find_by_title( $title, $type ) {
	$q = get_posts( array( 'post_type' => $type, 'title' => $title, 'posts_per_page' => 1, 'post_status' => 'any', 'fields' => 'ids' ) );
	return $q ? $q[0] : 0;
}

/**
 * Map a legacy static URL from the fixtures to the matching WordPress URL:
 *   index.html                    -> site home
 *   esports.html                  -> esports category archive
 *   article.html?slug=<slug>      -> that post's permalink
 * Anything already absolute is returned unchanged.
 */
function gi_normalize_url( $url ) {
	if ( '' === $url ) {
		return home_url( '/' );
	}
	if ( preg_match( '#^https?://#', $url ) ) {
		return $url;
	}
	if ( 0 === strpos( $url, 'article.html' ) ) {
		$slug = '';
		if ( preg_match( '/slug=([^&]+)/', $url, $m ) ) {
			$slug = urldecode( $m[1] );
		}
		$post = $slug ? get_page_by_path( $slug, OBJECT, 'post' ) : null;
		return $post ? get_permalink( $post ) : home_url( '/' );
	}
	if ( 0 === strpos( $url, 'esports.html' ) ) {
		$term = get_category_by_slug( 'esports' );
		return $term ? get_category_link( $term->term_id ) : home_url( '/' );
	}
	if ( 0 === strpos( $url, 'index.html' ) ) {
		return home_url( '/' );
	}
	return home_url( '/' . ltrim( $url, '/' ) );
}

WP_CLI::log( '== GameIndo import ==' );

/* -------------------------------------------------- 1. Pillar categories */
$pillars = array(
	'home'          => 'Video Game',
	'esports'       => 'Esports',
	'streamer'      => 'Streamer',
	'tech'          => 'Tech',
	'entertainment' => 'Entertainment',
);
$pillar_term = array();
foreach ( $pillars as $slug => $name ) {
	$term = term_exists( $slug, 'category' );
	if ( ! $term ) {
		$term = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
	}
	$pillar_term[ $slug ] = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
	// Add a description used by the pillar masthead.
	$descs = array(
		'esports'       => 'Berita kompetitif MLBB, Valorant, Free Fire, dan skena esports Indonesia — jadwal, hasil, klasemen, dan transfer roster.',
		'home'          => 'Rilis baru, review, dan panduan video game lintas platform.',
		'streamer'      => 'Kreator konten, VTuber, dan dinamika dunia streaming Indonesia.',
		'tech'          => 'PC, komponen, handheld, dan gadget untuk gaming.',
		'entertainment' => 'Anime, film, dan budaya pop yang beririsan dengan dunia game.',
	);
	if ( isset( $descs[ $slug ] ) ) {
		wp_update_term( $pillar_term[ $slug ], 'category', array( 'description' => $descs[ $slug ] ) );
	}
}
WP_CLI::log( 'Pillar categories ready.' );

/* -------------------------------------------------- 2. Authors */
$authors     = gi_json( 'authors.json' );
$author_user = array(); // slug => user_id
foreach ( $authors as $a ) {
	$user = get_user_by( 'login', $a['slug'] );
	if ( ! $user ) {
		$uid = wp_insert_user( array(
			'user_login'   => $a['slug'],
			'user_pass'    => wp_generate_password( 20 ),
			'display_name' => $a['name'],
			'nickname'     => $a['name'],
			'first_name'   => $a['name'],
			'description'  => isset( $a['description'] ) ? $a['description'] : '',
			'role'         => 'author',
		) );
	} else {
		$uid = $user->ID;
		wp_update_user( array( 'ID' => $uid, 'display_name' => $a['name'], 'description' => isset( $a['description'] ) ? $a['description'] : '' ) );
	}
	if ( is_wp_error( $uid ) ) {
		WP_CLI::warning( 'Author ' . $a['slug'] . ': ' . $uid->get_error_message() );
		continue;
	}
	$author_user[ $a['slug'] ] = $uid;
	$p = isset( $a['gi_profile'] ) ? $a['gi_profile'] : array();
	update_user_meta( $uid, 'gi_role', isset( $p['role'] ) ? $p['role'] : '' );
	update_user_meta( $uid, 'gi_articles_count', isset( $p['articles_count'] ) ? $p['articles_count'] : '' );
	update_user_meta( $uid, 'gi_since_year', isset( $p['since_year'] ) ? $p['since_year'] : '' );
	update_user_meta( $uid, 'gi_monthly_reads', isset( $p['monthly_reads'] ) ? $p['monthly_reads'] : '' );
}
WP_CLI::log( 'Authors ready: ' . count( $author_user ) );

/* -------------------------------------------------- 3. Media importer */
function gi_import_image( $source_url, $alt, $caption ) {
	static $media_cache = array();
	if ( ! $source_url ) {
		return 0;
	}
	$base = basename( $source_url );
	if ( isset( $media_cache[ $base ] ) ) {
		return $media_cache[ $base ];
	}
	// Look under assets/samples and assets/logo.
	$candidates = array( GI_ASSETS . '/samples/' . $base, GI_ASSETS . '/logo/' . $base, GI_ASSETS . '/' . $base );
	$path = '';
	foreach ( $candidates as $c ) {
		if ( file_exists( $c ) ) { $path = $c; break; }
	}
	if ( ! $path ) {
		return 0;
	}
	// Reuse if an attachment with this filename already exists.
	$existing = get_posts( array( 'post_type' => 'attachment', 'name' => sanitize_title( pathinfo( $base, PATHINFO_FILENAME ) ), 'posts_per_page' => 1, 'post_status' => 'inherit', 'fields' => 'ids' ) );
	if ( $existing ) {
		$media_cache[ $base ] = $existing[0];
		return $existing[0];
	}

	$upload = wp_upload_bits( $base, null, file_get_contents( $path ) );
	if ( ! empty( $upload['error'] ) ) {
		return 0;
	}
	$filetype = wp_check_filetype( $upload['file'], null );
	$attach_id = wp_insert_attachment( array(
		'guid'           => $upload['url'],
		'post_mime_type' => $filetype['type'],
		'post_title'     => $alt ? $alt : pathinfo( $base, PATHINFO_FILENAME ),
		'post_excerpt'   => $caption ? $caption : '',
		'post_content'   => '',
		'post_status'    => 'inherit',
	), $upload['file'] );
	$meta = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
	wp_update_attachment_metadata( $attach_id, $meta );
	if ( $alt ) {
		update_post_meta( $attach_id, '_wp_attachment_image_alt', $alt );
	}
	$media_cache[ $base ] = $attach_id;
	return $attach_id;
}

/* -------------------------------------------------- 4. Posts */
$posts = gi_json( 'posts.json' );
$created = 0;
foreach ( $posts as $p ) {
	$slug = $p['slug'];
	if ( get_page_by_path( $slug, OBJECT, 'post' ) ) {
		continue; // already imported
	}
	$emb    = isset( $p['_embedded'] ) ? $p['_embedded'] : array();
	$author = ( isset( $emb['author'][0]['slug'] ) && isset( $author_user[ $emb['author'][0]['slug'] ] ) )
		? $author_user[ $emb['author'][0]['slug'] ] : 0;
	$meta   = isset( $p['gi_meta'] ) ? $p['gi_meta'] : array();
	$pillar = isset( $meta['pillar'] ) ? $meta['pillar'] : 'home';

	$date = str_replace( 'T', ' ', $p['date'] );
	$postarr = array(
		'post_title'   => $p['title']['rendered'],
		'post_name'    => $slug,
		'post_content' => $p['content']['rendered'],
		'post_excerpt' => wp_strip_all_tags( $p['excerpt']['rendered'] ),
		'post_status'  => 'publish',
		'post_type'    => 'post',
		'post_author'  => $author,
		'post_date'    => $date,
		'post_date_gmt'=> get_gmt_from_date( $date ),
		'post_category'=> isset( $pillar_term[ $pillar ] ) ? array( $pillar_term[ $pillar ] ) : array(),
	);
	$post_id = wp_insert_post( $postarr, true );
	if ( is_wp_error( $post_id ) ) {
		WP_CLI::warning( 'Post ' . $slug . ': ' . $post_id->get_error_message() );
		continue;
	}

	// Tags.
	if ( ! empty( $meta['tags'] ) ) {
		wp_set_post_tags( $post_id, $meta['tags'], false );
	}

	// GameIndo meta.
	update_post_meta( $post_id, '_gi_pillar', $pillar );
	update_post_meta( $post_id, '_gi_subcategory', isset( $meta['subcategory'] ) ? $meta['subcategory'] : '' );
	update_post_meta( $post_id, '_gi_read_time', isset( $meta['read_time'] ) ? $meta['read_time'] : '' );
	update_post_meta( $post_id, '_gi_featured', ! empty( $meta['featured'] ) ? '1' : '' );
	update_post_meta( $post_id, '_gi_spotlight', ! empty( $meta['spotlight'] ) ? '1' : '' );
	$reads = isset( $meta['reads'] ) && $meta['reads'] ? $meta['reads'] : '';
	update_post_meta( $post_id, '_gi_reads', $reads );
	update_post_meta( $post_id, '_gi_reads_num', ( $reads && preg_match( '/\d+/', $reads, $m ) ) ? (int) $m[0] : 0 );

	// Featured image.
	if ( isset( $emb['wp:featuredmedia'][0] ) ) {
		$fm = $emb['wp:featuredmedia'][0];
		$cap = isset( $fm['caption']['rendered'] ) ? wp_strip_all_tags( $fm['caption']['rendered'] ) : '';
		$att = gi_import_image( isset( $fm['source_url'] ) ? $fm['source_url'] : '', isset( $fm['alt_text'] ) ? $fm['alt_text'] : '', $cap );
		if ( $att ) {
			set_post_thumbnail( $post_id, $att );
		}
	}
	$created++;
}
WP_CLI::log( 'Posts imported: ' . $created );

/* -------------------------------------------------- 5. Esports widgets */
function gi_seed_cpt( $type, $items, $title_cb, $meta_cb ) {
	if ( empty( $items ) ) {
		return 0;
	}
	$existing = get_posts( array( 'post_type' => $type, 'posts_per_page' => 1, 'post_status' => 'any', 'fields' => 'ids' ) );
	if ( $existing ) {
		return 0; // already seeded
	}
	$order = 0;
	foreach ( $items as $it ) {
		$order++;
		$pid = wp_insert_post( array(
			'post_type'   => $type,
			'post_status' => 'publish',
			'post_title'  => call_user_func( $title_cb, $it ),
			'menu_order'  => $order,
		) );
		if ( is_wp_error( $pid ) ) {
			continue;
		}
		foreach ( call_user_func( $meta_cb, $it ) as $k => $v ) {
			update_post_meta( $pid, $k, $v );
		}
	}
	return count( $items );
}

$n = gi_seed_cpt( 'gi_ticker', gi_json( 'ticker.json' ),
	function ( $it ) { return $it['text']; },
	function ( $it ) { return array( '_gi_url' => gi_normalize_url( isset( $it['url'] ) ? $it['url'] : '' ) ); }
);
WP_CLI::log( "Ticker items: $n" );

$n = gi_seed_cpt( 'gi_topic', gi_json( 'topics.json' ),
	function ( $it ) { return $it['label']; },
	function ( $it ) { return array( '_gi_query' => isset( $it['query'] ) ? $it['query'] : '' ); }
);
WP_CLI::log( "Topics: $n" );

$matches_data = gi_json( 'matches.json' );
$comp = $matches_data['competition'];
$n = gi_seed_cpt( 'gi_match', $matches_data['matches'],
	function ( $it ) { return $it['team_a'] . ' vs ' . $it['team_b']; },
	function ( $it ) use ( $comp ) {
		return array(
			'_gi_competition'  => $comp,
			'_gi_status'       => $it['status'],
			'_gi_status_label' => $it['status_label'],
			'_gi_team_a'       => $it['team_a'],
			'_gi_score_a'      => is_null( $it['score_a'] ) ? '' : $it['score_a'],
			'_gi_team_b'       => $it['team_b'],
			'_gi_score_b'      => is_null( $it['score_b'] ) ? '' : $it['score_b'],
		);
	}
);
WP_CLI::log( "Matches: $n" );

$st = gi_json( 'standings.json' );
$n = gi_seed_cpt( 'gi_standing', $st['rows'],
	function ( $it ) { return $it['team']; },
	function ( $it ) use ( $st ) {
		return array(
			'_gi_competition'  => $st['competition'],
			'_gi_season_label' => $st['season_label'],
			'_gi_rank'         => $it['rank'],
			'_gi_wl'           => $it['wl'],
			'_gi_pts'          => $it['pts'],
			'_gi_top'          => ! empty( $it['top'] ) ? '1' : '',
		);
	}
);
WP_CLI::log( "Standings: $n" );

/* -------------------------------------------------- 6. Nav menus */
function gi_build_menu( $name, $location, $items ) {
	$menu = wp_get_nav_menu_object( $name );
	if ( ! $menu ) {
		$menu_id = wp_create_nav_menu( $name );
	} else {
		$menu_id = $menu->term_id;
		// Clear existing items for a clean rebuild.
		foreach ( wp_get_nav_menu_items( $menu_id ) as $mi ) {
			wp_delete_post( $mi->ID, true );
		}
	}
	foreach ( $items as $it ) {
		wp_update_nav_menu_item( $menu_id, 0, $it );
	}
	$locations = get_theme_mod( 'nav_menu_locations' );
	$locations = is_array( $locations ) ? $locations : array();
	$locations[ $location ] = $menu_id;
	set_theme_mod( 'nav_menu_locations', $locations );
	return $menu_id;
}

$cat = function ( $slug ) use ( $pillar_term ) {
	return array(
		'menu-item-title'     => null,
		'menu-item-object'    => 'category',
		'menu-item-object-id' => $pillar_term[ $slug ],
		'menu-item-type'      => 'taxonomy',
		'menu-item-status'    => 'publish',
	);
};
$link = function ( $title, $url ) {
	return array(
		'menu-item-title'  => $title,
		'menu-item-url'    => $url,
		'menu-item-type'   => 'custom',
		'menu-item-status' => 'publish',
	);
};

gi_build_menu( 'Pilar Utama', 'primary', array(
	$link( 'Home', home_url( '/' ) ),
	$cat( 'esports' ),
	$cat( 'streamer' ),
	$cat( 'tech' ),
	$cat( 'entertainment' ),
) );

// Footer uses Video Game (home pillar) + the rest.
gi_build_menu( 'Footer', 'footer', array(
	array_merge( $cat( 'home' ), array( 'menu-item-title' => 'Video Game' ) ),
	$cat( 'esports' ),
	$cat( 'streamer' ),
	$cat( 'tech' ),
	$cat( 'entertainment' ),
) );

gi_build_menu( 'Menu Mobile', 'drawer', array(
	$link( 'Home', home_url( '/' ) ),
	$cat( 'esports' ),
	$cat( 'streamer' ),
	$cat( 'tech' ),
	$cat( 'entertainment' ),
	$link( 'Cari', home_url( '/?s=' ) ),
) );
WP_CLI::log( 'Menus built (primary, footer, drawer).' );

/* -------------------------------------------------- 7. Static pages */
$pages = array(
	'Tentang Kami'      => '<p>GameIndo adalah media gaming dan budaya pop Indonesia sejak 2001. Kami meliput video game, esports, streamer, teknologi, dan hiburan — ringkas, tajam, langsung ke intinya.</p>',
	'Kontak'            => '<p>Punya tip berita atau kerja sama? Hubungi kami di <a href="mailto:redaksi@gameindo.com">redaksi@gameindo.com</a>.</p>',
	'Pedoman Media Siber' => '<p>GameIndo tunduk pada Pedoman Media Siber Dewan Pers dalam setiap pemberitaan.</p>',
	'Kebijakan Privasi' => '<p>Kebijakan privasi GameIndo menjelaskan bagaimana kami mengelola data pengunjung.</p>',
);
foreach ( $pages as $title => $content ) {
	if ( gi_find_by_title( $title, 'page' ) ) {
		continue;
	}
	wp_insert_post( array(
		'post_title'   => $title,
		'post_content' => $content,
		'post_status'  => 'publish',
		'post_type'    => 'page',
	) );
}
WP_CLI::log( 'Static pages created.' );

/* -------------------------------------------------- 8. Cleanup defaults */
$hello = get_page_by_path( 'hello-world', OBJECT, 'post' );
if ( $hello ) {
	wp_delete_post( $hello->ID, true );
}
$sample = get_page_by_path( 'sample-page', OBJECT, 'page' );
if ( $sample ) {
	wp_delete_post( $sample->ID, true );
}

WP_CLI::success( 'GameIndo import complete.' );
