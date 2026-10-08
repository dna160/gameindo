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

// Hero side: upcoming game releases (RAWG) lead when there's data to show —
// same opt-in, degrade-gracefully contract as the Video Games pillar page's
// panel (gameindo_upcoming_games() returns an empty array without a RAWG
// key, or if the API is down). Falls back to the old spotlight-trending
// cards when there's nothing to show, so an unconfigured key never produces
// an empty box.
$gi_releases = gameindo_upcoming_games( array( 'limit' => 6 ) );

// Hero side trending: spotlight from other pillars (2), else first two others.
// Only actually used when $gi_releases is empty — see above.
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

// Hero slider: editor-picked "featured" post leads (if it's actually a
// gaming post), then the next latest gaming articles fill the rest. The
// hero is the single most prominent slot on the homepage, so it uses the
// stricter gameindo_is_hero_gaming_post() (pillar-implied, or a named
// platform/franchise) rather than gameindo_is_about_games() — a Tech or
// Entertainment post that only name-drops "gaming" in passing is enough to
// pass the looser check Latest News uses, but reads as off-topic leading
// the hero. Falls back to the unfiltered newest posts when curation finds
// nothing at all, so the hero is never empty during a dry spell.
$gi_hero_slides = array();
foreach ( $gi_all as $gi_p ) {
	if ( count( $gi_hero_slides ) >= 5 ) {
		break;
	}
	if ( gameindo_is_hero_gaming_post( $gi_p->ID ) ) {
		$gi_hero_slides[] = $gi_p;
	}
}
if ( $gi_featured && gameindo_is_hero_gaming_post( $gi_featured->ID ) ) {
	$gi_hero_slides = array_values( array_filter(
		$gi_hero_slides,
		function ( $gi_p ) use ( $gi_featured ) {
			return $gi_p->ID !== $gi_featured->ID;
		}
	) );
	array_unshift( $gi_hero_slides, $gi_featured );
	$gi_hero_slides = array_slice( $gi_hero_slides, 0, 5 );
}
if ( empty( $gi_hero_slides ) ) {
	if ( $gi_featured ) {
		$gi_hero_slides[] = $gi_featured;
	}
	foreach ( $gi_all as $gi_p ) {
		if ( count( $gi_hero_slides ) >= 5 ) {
			break;
		}
		if ( $gi_featured && $gi_p->ID === $gi_featured->ID ) {
			continue;
		}
		$gi_hero_slides[] = $gi_p;
	}
}
// The hero section's accent color follows whichever post actually leads the
// slider now, not the (possibly filtered-out) editor "featured" pick.
$gi_hero_lead_pillar = ! empty( $gi_hero_slides ) ? gameindo_get_pillar( $gi_hero_slides[0]->ID ) : $gi_featured_pillar;

// Latest grid: posts excluding hero slides + hero trending, first 4.
$gi_exclude = array();
foreach ( $gi_hero_slides as $gi_p ) {
	$gi_exclude[] = $gi_p->ID;
}
foreach ( $gi_hero_trending as $gi_p ) {
	$gi_exclude[] = $gi_p->ID;
}

// Small news strip under the hero slider, filling the column down to the
// side panel's height (see .gi-hero__more — it's a flex:1 cell, so however
// tall the release/trending panel ends up, this grows to meet it instead of
// leaving empty space below the slider). Excluded from every other rail
// below so the same article never appears twice on the homepage. Same
// strict curation as the hero slider above (gameindo_is_hero_gaming_post(),
// not the looser gameindo_is_about_games() Latest News uses) — this strip
// sits right under it, so it holds to the same bar.
$gi_hero_more = array();
foreach ( $gi_all as $gi_p ) {
	if ( ! in_array( $gi_p->ID, $gi_exclude, true ) && gameindo_is_hero_gaming_post( $gi_p->ID ) ) {
		$gi_hero_more[] = $gi_p;
	}
	if ( count( $gi_hero_more ) >= 3 ) {
		break;
	}
}
foreach ( $gi_hero_more as $gi_p ) {
	$gi_exclude[] = $gi_p->ID;
}

// "Latest News" only shows articles that are actually about games — the
// homepage is a gaming site's front door, and a Samsung laptop or an AI
// partnership announcement filed under Tech shouldn't lead it even when it's
// the newest post. gameindo_is_about_games() is a best-effort heuristic
// (title/excerpt/tags/subcategory keyword match, see inc/template-helpers.php)
// — this is a homepage-only curation choice. The pillar's own archive page
// (archive.php) is untouched and still shows everything filed there,
// unfiltered, same as before.
$gi_latest = array();
foreach ( $gi_all as $gi_p ) {
	if ( ! in_array( $gi_p->ID, $gi_exclude, true ) && gameindo_is_about_games( $gi_p->ID ) ) {
		$gi_latest[] = $gi_p;
	}
	if ( count( $gi_latest ) >= 4 ) {
		break;
	}
}

// Match panel, four slots. Marquee tournaments come first — being live is not
// on its own a reason to occupy the homepage, or a closed qualifier nobody
// follows would outrank MPL and the LEC. Within the marquee set: live matches,
// then the nearest matchday, with ML:BB floated up so MPL leads whenever it
// plays. Lower-tier fixtures still fill any slot left over.
$gi_matches = gameindo_get_schedule( 'all', array(
	'limit'        => 4,
	'priority'     => 'mlbb',
	'rank_by_tier' => true,
	'tier_floor'   => gameindo_prestige_floor(),
	'max_per_game' => 2,
) );

// Panel meta reads as the competition when the rows all come from one, which
// is the common case (an MPL matchday), and stays generic when they don't.
$gi_match_comps = gameindo_schedule_competitions( $gi_matches, 2 );
$gi_match_meta  = ( 1 === count( $gi_match_comps ) ) ? $gi_match_comps[0] : 'Jadwal Terdekat';
?>

<main>
  <section class="gi-hero" data-pillar="<?php echo esc_attr( $gi_hero_lead_pillar ? $gi_hero_lead_pillar : 'home' ); ?>">
    <div class="gi-hero__grid">
      <div class="gi-hero__main">
        <div class="gi-hero-slider" id="gi-hero-slider" data-autoplay="6000">
          <div class="gi-hero-slider__track" id="gi-hero-slider-track"><?php
            // Only the first slide is the LCP image; the rest sit off-screen in
            // the carousel until a reader clicks through, so they load lazily
            // instead of competing with it for bandwidth.
            $gi_slide_i = 0;
            foreach ( $gi_hero_slides as $gi_sp ) {
	            echo gameindo_feature( $gi_sp, array( 'eager' => 0 === $gi_slide_i ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	            $gi_slide_i++;
            }
          ?></div>
          <?php if ( count( $gi_hero_slides ) > 1 ) : ?>
          <div class="gi-hero-slider__dots" id="gi-hero-slider-dots"></div>
          <button type="button" class="gi-hero-slider__arrow gi-hero-slider__arrow--prev" data-slider-prev aria-label="Slide sebelumnya">‹</button>
          <button type="button" class="gi-hero-slider__arrow gi-hero-slider__arrow--next" data-slider-next aria-label="Slide berikutnya">›</button>
          <?php endif; ?>
        </div>
        <?php if ( ! empty( $gi_hero_more ) ) : ?>
        <div class="gi-hero__more" id="gi-hero-more"><?php
          foreach ( $gi_hero_more as $gi_p ) {
	          echo gameindo_card( $gi_p, array( 'variant' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
          }
        ?></div>
        <?php endif; ?>
      </div>
      <div class="gi-hero__side" id="gi-hero-side">
        <?php if ( ! empty( $gi_releases ) ) : ?>
        <div class="gi-night-panel gi-night-panel--release">
          <div class="gi-night-panel__head">
            <span class="gi-night-panel__head-title">Rilis Mendatang</span>
            <span class="gi-night-panel__head-meta">Semua Platform</span>
          </div>
          <div class="gi-release-list"><?php
            foreach ( $gi_releases as $gi_rg ) {
	            echo gameindo_release_row( $gi_rg ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
          ?></div>
          <span class="gi-release__source">Data rilis: RAWG</span>
        </div>
        <?php else : ?>
        <div class="gi-hero__trending" id="gi-hero-trending"><?php
          foreach ( $gi_hero_trending as $gi_p ) {
	          echo gameindo_card( $gi_p, array( 'variant' => 'h' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
          }
        ?></div>
        <?php endif; ?>
        <?php if ( ! empty( $gi_matches ) ) : ?>
        <div class="gi-matchpanel" data-pillar="esports">
          <div class="gi-matchpanel__head">
            <span class="gi-matchpanel__title">Jadwal Match</span>
            <span class="gi-matchpanel__meta" id="gi-matchpanel-meta"><?php echo esc_html( $gi_match_meta ); ?></span>
          </div>
          <div class="gi-matchpanel__rows" id="gi-matchpanel-rows"><?php
            foreach ( $gi_matches as $gi_m ) {
	            echo gameindo_match_panel_row( $gi_m ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
          ?></div>
          <a class="gi-matchpanel__cta" href="<?php echo esc_url( gameindo_pillar_url( 'esports' ) ); ?>">Lebih Detail →</a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <?php if ( ! empty( $gi_releases ) ) : ?>
  <div class="gi-mobile-releases" data-pillar="video-games">
    <div class="gi-mobile-releases__inner">
      <div class="gi-mobile-releases__head">
        <span class="gi-mobile-releases__tick" aria-hidden="true"></span>
        <span class="gi-mobile-releases__title">Rilis Mendatang</span>
      </div>
      <div class="gi-mobile-releases__row" id="gi-mobile-releases-row"><?php
        foreach ( $gi_releases as $gi_rg ) {
	        echo gameindo_mobile_release_card( $gi_rg ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
      ?></div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ( ! empty( $gi_matches ) ) :
	// Mobile match strip: the same two leading rows the side panel opens with.
	$gi_mobile = array_slice( $gi_matches, 0, 2 );

	// Only claim "hari ini" when the lead match really is today.
	$gi_lead_ts    = (int) $gi_mobile[0]['begin_ts'];
	$gi_mobile_ttl = ( $gi_lead_ts && wp_date( 'Ymd', $gi_lead_ts ) === wp_date( 'Ymd' ) ) || 'running' === $gi_mobile[0]['status']
		? 'Match Hari Ini'
		: 'Match Terdekat'; ?>
  <div class="gi-mobile-matches" data-pillar="esports">
    <div class="gi-mobile-matches__inner">
      <div class="gi-mobile-matches__head">
        <span class="gi-mobile-matches__tick" aria-hidden="true"></span>
        <span class="gi-mobile-matches__title"><?php echo esc_html( $gi_mobile_ttl ); ?></span>
      </div>
      <div class="gi-mobile-matches__row" id="gi-mobile-matches-row"><?php
        foreach ( $gi_mobile as $gi_m ) {
	        echo gameindo_mobile_match_card( $gi_m ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
      foreach ( gameindo_nav_pillars() as $gi_slug => $gi_name ) {
	      if ( 'video-games' === $gi_slug ) {
		      // Not a term count: the pillar spans its own category, the legacy
		      // "Video Game" one, and console coverage filed elsewhere.
		      $gi_count = count( gameindo_video_games_pool() );
	      } else {
		      $gi_term  = get_category_by_slug( $gi_slug );
		      $gi_count = $gi_term ? (int) $gi_term->count : 0;
	      }
	      echo gameindo_pillar_tile( $gi_slug, $gi_name, $gi_count, gameindo_pillar_url( $gi_slug ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
      }
    ?></div>
  </div>

  <div id="gi-pillar-bands"><?php
    // Video Games leads: it is the site's core beat, and the band order is what
    // a reader scrolling past the hero meets first.
    $gi_band_order = array( 'video-games', 'esports', 'streamer', 'tech', 'entertainment' );
    $gi_bi = 0;
    foreach ( $gi_band_order as $gi_slug ) {
	    if ( 'video-games' === $gi_slug ) {
		    $gi_band_posts = gameindo_video_games_posts( array( 'limit' => 4 ) );
	    } else {
		    // Same homepage-only "really about games" curation as Latest News
		    // (see gameindo_is_about_games()) — Esports passes everything by
		    // pillar alone, but Streamer/Tech/Entertainment mix in non-gaming
		    // coverage, so fetch more than 4 candidates up front or the band
		    // could come up short once the filter runs. The pillar's own
		    // archive page is untouched and still shows every post filed
		    // there, unfiltered.
		    $gi_candidates = get_posts( array(
			    'post_type'      => 'post',
			    'post_status'    => 'publish',
			    'posts_per_page' => 20,
			    'category_name'  => $gi_slug,
			    'orderby'        => 'date',
			    'order'          => 'DESC',
		    ) );
		    $gi_band_posts = array();
		    $gi_band_ids   = array();
		    foreach ( $gi_candidates as $gi_cp ) {
			    if ( gameindo_is_about_games( $gi_cp->ID ) ) {
				    $gi_band_posts[] = $gi_cp;
				    $gi_band_ids[]   = $gi_cp->ID;
			    }
			    if ( count( $gi_band_posts ) >= 4 ) {
				    break;
			    }
		    }
		    // A pillar with little gaming-angled coverage (Streamer, often) would
		    // otherwise show a visibly half-empty band next to the others. The
		    // curation is a homepage *preference*, not a hard rule the way it is
		    // for Latest News — once gaming candidates run out, fill the rest of
		    // the slot with the pillar's other latest posts so the band always
		    // reads as a normal 4-card row, gaming articles still leading it.
		    if ( count( $gi_band_posts ) < 4 ) {
			    foreach ( $gi_candidates as $gi_cp ) {
				    if ( count( $gi_band_posts ) >= 4 ) {
					    break;
				    }
				    if ( in_array( $gi_cp->ID, $gi_band_ids, true ) ) {
					    continue;
				    }
				    $gi_band_posts[] = $gi_cp;
			    }
		    }
	    }
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
		    $gi_sub  = gameindo_meta( $gi_bp->ID, 'subcategory' );
		    $gi_args = array( 'variant' => 'sm', 'pill_label' => $gi_sub ? $gi_sub : $gi_name );
		    if ( 'video-games' === $gi_slug ) {
			    // A Switch 2 piece filed under Tech still reads as Video Games here.
			    $gi_args['pillar'] = $gi_slug;
		    }
		    echo gameindo_card( $gi_bp, $gi_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	    }
	    echo '</div></div></section>';
	    $gi_bi++;
    }
  ?></div>
</main>

<?php
get_footer();
