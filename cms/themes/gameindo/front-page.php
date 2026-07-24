<?php
/**
 * Front page — server-rendered port of the original index.html home layout.
 *
 * @package GameIndo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

// ---- Gather content (one query, then compose like the old home.js) --------
$gi_all = get_posts( array(
	'post_type'      => 'post',
	'post_status'    => 'publish',
	'posts_per_page' => 100,
	'orderby'        => 'date',
	'order'          => 'DESC',
) );

$gi_featured = null;
$gi_spotlight = array();
foreach ( $gi_all as $gi_p ) {
	if ( ! $gi_featured && gameindo_meta( $gi_p->ID, 'featured' ) ) {
		$gi_featured = $gi_p;
	}
	if ( gameindo_meta( $gi_p->ID, 'spotlight' ) ) {
		$gi_spotlight[] = $gi_p;
	}
}
if ( ! $gi_featured && ! empty( $gi_all ) ) {
	$gi_featured = $gi_all[0];
}
$gi_featured_pillar = $gi_featured ? gameindo_get_pillar( $gi_featured->ID ) : '';

// Hero side trending: spotlight from other pillars (2), else first two others.
$gi_hero_trending = array();
foreach ( $gi_spotlight as $gi_p ) {
	if ( gameindo_get_pillar( $gi_p->ID ) !== $gi_featured_pillar ) {
		$gi_hero_trending[] = $gi_p;
	}
	if ( count( $gi_hero_trending ) >= 2 ) {
		break;
	}
}
if ( count( $gi_hero_trending ) < 2 ) {
	$gi_hero_trending = array();
	foreach ( $gi_all as $gi_p ) {
		if ( ! $gi_featured || $gi_p->ID !== $gi_featured->ID ) {
			$gi_hero_trending[] = $gi_p;
		}
		if ( count( $gi_hero_trending ) >= 2 ) {
			break;
		}
	}
}

// Latest grid: posts excluding featured + hero trending, first 4.
$gi_exclude = array();
if ( $gi_featured ) {
	$gi_exclude[] = $gi_featured->ID;
}
foreach ( $gi_hero_trending as $gi_p ) {
	$gi_exclude[] = $gi_p->ID;
}
$gi_latest = array();
foreach ( $gi_all as $gi_p ) {
	if ( ! in_array( $gi_p->ID, $gi_exclude, true ) ) {
		$gi_latest[] = $gi_p;
	}
	if ( count( $gi_latest ) >= 4 ) {
		break;
	}
}

$gi_matches = gameindo_get_matches();
?>

<main>
  <section class="gi-hero" data-pillar="<?php echo esc_attr( $gi_featured_pillar ? $gi_featured_pillar : 'home' ); ?>">
    <div class="gi-hero__grid">
      <div id="gi-hero-feature"><?php
        if ( $gi_featured ) {
	        echo gameindo_feature( $gi_featured ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
      ?></div>
      <div class="gi-hero__side" id="gi-hero-side">
        <div id="gi-hero-trending"><?php
          foreach ( $gi_hero_trending as $gi_p ) {
	          echo gameindo_card( $gi_p, array( 'variant' => 'h' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
          }
        ?></div>
        <?php if ( ! empty( $gi_matches['matches'] ) ) : ?>
        <div class="gi-matchpanel" data-pillar="esports">
          <div class="gi-matchpanel__head">
            <span class="gi-matchpanel__title">Jadwal Match</span>
            <span class="gi-matchpanel__meta" id="gi-matchpanel-meta"><?php echo esc_html( $gi_matches['competition'] ); ?></span>
          </div>
          <div class="gi-matchpanel__rows" id="gi-matchpanel-rows"><?php
            foreach ( $gi_matches['matches'] as $gi_m ) {
	            echo gameindo_match_panel_row( $gi_m ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
          ?></div>
          <a class="gi-matchpanel__cta" href="<?php echo esc_url( gameindo_pillar_url( 'esports' ) ); ?>">Lihat Klasemen →</a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <?php if ( ! empty( $gi_matches['matches'] ) ) :
	// Mobile match strip: first two non-scheduled, else first two.
	$gi_mobile = array();
	foreach ( $gi_matches['matches'] as $gi_m ) {
		if ( isset( $gi_m['status'] ) && 'scheduled' !== $gi_m['status'] ) {
			$gi_mobile[] = $gi_m;
		}
		if ( count( $gi_mobile ) >= 2 ) {
			break;
		}
	}
	if ( empty( $gi_mobile ) ) {
		$gi_mobile = array_slice( $gi_matches['matches'], 0, 2 );
	} ?>
  <div class="gi-mobile-matches" data-pillar="esports">
    <div class="gi-mobile-matches__inner">
      <div class="gi-mobile-matches__head">
        <span class="gi-mobile-matches__tick" aria-hidden="true"></span>
        <span class="gi-mobile-matches__title">Match Hari Ini</span>
      </div>
      <div class="gi-mobile-matches__row" id="gi-mobile-matches-row"><?php
        foreach ( $gi_mobile as $gi_m ) {
	        echo gameindo_mobile_match_card( $gi_m, $gi_matches['competition'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
      ?></div>
    </div>
  </div>
  <?php endif; ?>

  <div class="gi-container" style="padding-top:32px;padding-bottom:8px">
    <div class="gi-latest-layout">
      <div>
        <div class="gi-section-head" data-pillar="home">
          <div class="gi-section-head__main">
            <span class="gi-section-head__tick" aria-hidden="true"></span>
            <div>
              <span class="gi-section-head__eyebrow">Baru tayang</span>
              <h2 class="gi-section-head__title">Latest News</h2>
            </div>
          </div>
          <a class="gi-section-head__link" href="<?php echo esc_url( home_url( '/?s=' ) ); ?>">View More <span aria-hidden="true">→</span></a>
        </div>
        <div class="gi-grid-2" style="margin-top:20px" id="gi-latest-grid"><?php
          foreach ( $gi_latest as $gi_p ) {
	          echo gameindo_card( $gi_p, array( 'variant' => 'md' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
          }
        ?></div>
      </div>
      <aside style="display:flex;flex-direction:column;gap:24px">
        <div>
          <div class="gi-section-head" style="border-bottom:2px solid var(--ink);padding-bottom:10px">
            <div class="gi-section-head__main"><span class="gi-section-head__tick" aria-hidden="true"></span><div><h2 class="gi-section-head__title">Terpopuler</h2></div></div>
          </div>
          <div id="gi-terpopuler-rail"><?php
            $gi_top = gameindo_trending_posts( 5 );
            $gi_i   = 0;
            foreach ( $gi_top as $gi_tid ) {
	            $gi_i++;
	            echo gameindo_rank_row( $gi_tid, $gi_i, array( 'thumb' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
          ?></div>
        </div>
        <div class="gi-newsletter">
          <span class="gi-newsletter__title">Level Up Inbox Kamu</span>
          <p class="gi-newsletter__desc">Rangkuman berita gaming terpenting, tiap pagi. Gratis, tanpa spam.</p>
          <form class="gi-newsletter__form" id="gi-newsletter-form">
            <input type="email" name="email" placeholder="email kamu…" required aria-label="Alamat email">
            <button class="gi-btn gi-btn--primary" type="submit">Daftar</button>
          </form>
          <p class="gi-newsletter__note" id="gi-newsletter-note"></p>
        </div>
      </aside>
    </div>
  </div>

  <div class="gi-container gi-grid-5 gi-pillar-tiles" id="pillars" style="padding-top:24px;padding-bottom:40px">
    <div id="gi-pillar-tiles" style="display:contents"><?php
      foreach ( gameindo_pillars() as $gi_slug => $gi_name ) {
	      $gi_term  = get_category_by_slug( $gi_slug );
	      $gi_count = $gi_term ? (int) $gi_term->count : 0;
	      echo gameindo_pillar_tile( $gi_slug, $gi_name, $gi_count, gameindo_pillar_url( $gi_slug ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
      }
    ?></div>
  </div>

  <div id="gi-pillar-bands"><?php
    $gi_band_order = array( 'esports', 'home', 'streamer', 'tech', 'entertainment' );
    $gi_bi = 0;
    foreach ( $gi_band_order as $gi_slug ) {
	    $gi_band_posts = get_posts( array(
		    'post_type'      => 'post',
		    'post_status'    => 'publish',
		    'posts_per_page' => 4,
		    'category_name'  => $gi_slug,
		    'orderby'        => 'date',
		    'order'          => 'DESC',
	    ) );
	    if ( empty( $gi_band_posts ) ) {
		    continue;
	    }
	    $gi_name = gameindo_pillar_name( $gi_slug );
	    $gi_alt  = ( $gi_bi % 2 === 1 ) ? ' gi-pillarband--alt' : '';
	    echo '<section class="gi-pillarband' . esc_attr( $gi_alt ) . '" data-pillar="' . esc_attr( $gi_slug ) . '" id="pillar-' . esc_attr( $gi_slug ) . '">';
	    echo '<div class="gi-pillarband__inner">';
	    echo '<div class="gi-section-head"><div class="gi-section-head__main"><span class="gi-section-head__tick" aria-hidden="true"></span>';
	    echo '<div><span class="gi-section-head__eyebrow">Pillar</span><h2 class="gi-section-head__title">' . esc_html( $gi_name ) . '</h2></div></div>';
	    echo '<a class="gi-section-head__link" href="' . esc_url( gameindo_pillar_url( $gi_slug ) ) . '">View More <span aria-hidden="true">→</span></a></div>';
	    echo '<div class="gi-grid-4" style="margin-top:20px">';
	    foreach ( $gi_band_posts as $gi_bp ) {
		    $gi_sub = gameindo_meta( $gi_bp->ID, 'subcategory' );
		    echo gameindo_card( $gi_bp, array( 'variant' => 'sm', 'pill_label' => $gi_sub ? $gi_sub : $gi_name ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	    }
	    echo '</div></div></section>';
	    $gi_bi++;
    }
  ?></div>
</main>

<?php
get_footer();
