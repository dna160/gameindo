<?php
/**
 * Category / pillar archive — port of esports.html, generalized to every
 * pillar. Esports additionally shows the live schedule panel; Video Games
 * draws its feed from more than one category (see gameindo_video_games_pool()).
 *
 * @package GameIndo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$gi_obj  = get_queried_object();
$gi_slug = ( $gi_obj && isset( $gi_obj->slug ) ) ? $gi_obj->slug : '';

// This template also serves tag and date archives, where the slug is not a
// pillar. Those get the pillar chrome but their own posts — the pillar branches
// below must not claim them.
$gi_is_pillar      = is_category() && array_key_exists( $gi_slug, gameindo_pillars() );
$gi_pillar         = $gi_is_pillar ? gameindo_canonical_pillar( $gi_slug ) : 'video-games';
$gi_name           = ( $gi_obj && isset( $gi_obj->name ) ) ? $gi_obj->name : gameindo_pillar_name( $gi_pillar );
$gi_desc           = ( $gi_obj && ! empty( $gi_obj->description ) ) ? $gi_obj->description : ( $gi_is_pillar ? gameindo_pillar_description( $gi_pillar ) : '' );
$gi_is_esports     = ( $gi_is_pillar && 'esports' === $gi_pillar );
$gi_is_video_games = ( $gi_is_pillar && 'video-games' === $gi_pillar );

// Video Games platform chips. Like the esports ?game= chips they narrow the
// page itself, and they always link back to the canonical Video Games URL so
// the legacy /category/home/ address never becomes a second filterable page.
$gi_platforms = gameindo_game_platforms();
$gi_platform  = $gi_is_video_games ? gameindo_current_platform() : 'all';
$gi_vg_url    = gameindo_pillar_url( 'video-games' );

// The pillar's articles: feature = the lead, rest = grid.
if ( $gi_is_video_games ) {
	$gi_posts = gameindo_video_games_posts( array( 'platform' => $gi_platform, 'limit' => 60 ) );
	$gi_lead  = gameindo_video_games_lead( $gi_posts );
	if ( null === $gi_lead ) {
		$gi_feature = null;
	} else {
		$gi_feature = $gi_posts[ $gi_lead ];
		array_splice( $gi_posts, $gi_lead, 1 );
	}
} elseif ( $gi_is_pillar ) {
	$gi_posts = get_posts( array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 60,
		'category_name'  => $gi_slug,
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );
	$gi_feature = ! empty( $gi_posts ) ? array_shift( $gi_posts ) : null;
} else {
	// Tag / date archive: the main query already holds the right posts. Asking
	// for a category by this slug would match no term and render an empty page.
	global $wp_query;
	$gi_posts   = $wp_query->posts;
	$gi_feature = ! empty( $gi_posts ) ? array_shift( $gi_posts ) : null;
}
$gi_initial   = 6; // grid items visible before "Muat Lebih Banyak"
$gi_card_args = $gi_is_video_games ? array( 'pillar' => 'video-games' ) : array();

// Esports schedule panel. The ?game= chip filters the panel only — the article
// feed below stays the full Esports pillar. The panel scrolls internally, so a
// busy matchday can carry more fixtures than would ever fit on screen.
$gi_games    = gameindo_esports_games();
$gi_game     = $gi_is_esports ? gameindo_current_game() : 'all';
$gi_schedule = $gi_is_esports ? gameindo_get_schedule( $gi_game, array( 'limit' => 20 ) ) : array();
$gi_comps    = gameindo_schedule_competitions( $gi_schedule );
$gi_base_url = gameindo_pillar_url( 'esports' );
?>

<main>
  <section class="gi-masthead">
    <div class="gi-masthead__inner">
      <h1 class="gi-masthead__title"><?php echo esc_html( $gi_name ); ?></h1>
      <?php if ( $gi_desc ) : ?><p class="gi-masthead__desc"><?php echo esc_html( $gi_desc ); ?></p><?php endif; ?>
      <?php if ( $gi_is_esports ) : ?>
      <div class="gi-filters">
        <a class="gi-filter" href="<?php echo esc_url( $gi_base_url . '#jadwal' ); ?>"<?php echo ( 'all' === $gi_game ) ? ' aria-current="true"' : ''; ?>>Semua</a>
        <?php foreach ( $gi_games as $gi_key => $gi_conf ) : ?>
        <a class="gi-filter" href="<?php echo esc_url( add_query_arg( 'game', $gi_key, $gi_base_url ) . '#jadwal' ); ?>"<?php echo ( $gi_key === $gi_game ) ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $gi_conf['label'] ); ?></a>
        <?php endforeach; ?>
      </div>
      <?php elseif ( $gi_is_video_games ) : ?>
      <div class="gi-filters">
        <a class="gi-filter" href="<?php echo esc_url( $gi_vg_url ); ?>"<?php echo ( 'all' === $gi_platform ) ? ' aria-current="true"' : ''; ?>>Semua</a>
        <?php foreach ( $gi_platforms as $gi_key => $gi_conf ) : ?>
        <a class="gi-filter" href="<?php echo esc_url( add_query_arg( 'platform', $gi_key, $gi_vg_url ) ); ?>"<?php echo ( $gi_key === $gi_platform ) ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $gi_conf['label'] ); ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <div class="gi-container" style="padding-top:28px">
    <div class="gi-pillar-layout">
      <div id="gi-esports-feature"><?php
        if ( $gi_feature ) {
	        $gi_fsub = gameindo_meta( $gi_feature->ID, 'subcategory' );
	        echo gameindo_feature( $gi_feature, array_merge( $gi_card_args, array( 'pill_label' => $gi_fsub ? $gi_fsub : $gi_name ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } elseif ( 'all' !== $gi_platform ) {
	        printf(
		        '<p class="gi-empty">Belum ada artikel %s di pilar ini. <a href="%s">Lihat semua Video Games →</a></p>',
		        esc_html( $gi_platforms[ $gi_platform ]['label'] ),
		        esc_url( $gi_vg_url )
	        );
        }
      ?></div>

      <?php if ( $gi_is_esports ) : ?>
      <div class="gi-night-panel gi-night-panel--schedule" id="jadwal" style="scroll-margin-top:80px">
        <div class="gi-night-panel__head">
          <span class="gi-night-panel__head-title"><?php echo esc_html( gameindo_schedule_title( $gi_game ) ); ?></span>
          <span class="gi-night-panel__head-meta"><?php
            echo esc_html( 'all' === $gi_game ? 'Semua Game' : $gi_games[ $gi_game ]['name'] );
          ?></span>
        </div>

        <?php if ( ! empty( $gi_comps ) ) : ?>
        <p class="gi-schedule__note">
          <span>Turnamen:</span> <?php echo esc_html( implode( ' · ', $gi_comps ) ); ?>
        </p>
        <?php endif; ?>

        <?php if ( ! empty( $gi_schedule ) ) :
	        /* The list scrolls inside the panel rather than running the page down
	           past the article feed. tabindex makes the scroller reachable by
	           keyboard, which a plain overflow container is not. */ ?>
        <div class="gi-schedule" tabindex="0" role="region" aria-label="Jadwal pertandingan, gulir untuk melihat semua"><?php
          $gi_day = '';
          foreach ( $gi_schedule as $gi_m ) {
	          $gi_this = $gi_m['begin_ts'] ? wp_date( 'Ymd', (int) $gi_m['begin_ts'] ) : 'tbd';
	          if ( $gi_this !== $gi_day ) {
		          $gi_day = $gi_this;
		          echo '<div class="gi-schedule__day">' . esc_html( gameindo_match_day_label( (int) $gi_m['begin_ts'] ) ) . '</div>';
	          }
	          echo gameindo_schedule_row( $gi_m, 'all' === $gi_game ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
          }
        ?></div>
        <?php else : ?>
        <p class="gi-schedule__empty">
          <?php if ( 'all' === $gi_game ) : ?>
            Jadwal pertandingan belum tersedia. Coba lagi sebentar lagi.
          <?php else : ?>
            Belum ada jadwal <?php echo esc_html( $gi_games[ $gi_game ]['label'] ); ?> dalam waktu dekat.
          <?php endif; ?>
        </p>
        <?php endif; ?>

        <?php if ( 'all' !== $gi_game ) : ?>
        <a class="gi-night-panel__cta" href="<?php echo esc_url( $gi_base_url . '#jadwal' ); ?>">Semua Jadwal →</a>
        <?php endif; ?>
      </div>
      <?php else :
	      // Non-esports pillars: a "Terpopuler" leaderboard panel to fill the
	      // same slot the esports schedule occupies. Same reads + recency blend
	      // as the homepage rail, scoped to this pillar; the [data-pillar]
	      // scope colours it per pillar. Video Games ranks its own cross-category
	      // pool, since its articles are not all in one term.
	      $gi_pop = $gi_is_video_games
		      ? gameindo_rank_recent_popular( gameindo_video_games_posts( array( 'platform' => $gi_platform, 'limit' => 40 ) ), 5 )
		      : gameindo_trending_posts( 5, array( 'category' => $gi_slug ) );
	      if ( ! empty( $gi_pop ) ) : ?>
      <div class="gi-night-panel">
        <div class="gi-night-panel__head">
          <span class="gi-night-panel__head-title">Terpopuler</span>
          <span class="gi-night-panel__head-meta"><?php echo esc_html( mb_strtoupper( $gi_name ) ); ?></span>
        </div>
        <div class="gi-night-panel__list"><?php
          $gi_ri = 0;
          foreach ( $gi_pop as $gi_pp ) {
	          $gi_ri++;
	          echo gameindo_rank_row( $gi_pp, $gi_ri, array( 'thumb' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
          }
        ?></div>
        <a class="gi-night-panel__cta" href="#gi-pillar-latest">Artikel Terbaru ↓</a>
      </div>
      <?php endif; endif; ?>
    </div>
  </div>

  <div class="gi-container" id="gi-pillar-latest" style="padding-top:36px;padding-bottom:40px;scroll-margin-top:80px">
    <div class="gi-section-head">
      <div class="gi-section-head__main">
        <span class="gi-section-head__tick" aria-hidden="true"></span>
        <div><h2 class="gi-section-head__title">Terbaru di <?php echo esc_html( $gi_name ); ?></h2></div>
      </div>
    </div>

    <?php // Skipped when the feature slot already carries the "no X articles" note. ?>
    <?php if ( empty( $gi_posts ) && ( $gi_feature || 'all' === $gi_platform ) ) : ?>
    <p class="gi-empty" style="margin-top:20px">Belum ada artikel lain di sini.</p>
    <?php endif; ?>

    <div class="gi-grid-3" style="margin-top:20px" id="gi-esports-grid">
      <?php
      $gi_i = 0;
      foreach ( $gi_posts as $gi_gp ) {
	      $gi_sub    = gameindo_meta( $gi_gp->ID, 'subcategory' );
	      $gi_hidden = ( $gi_i >= $gi_initial ) ? 'gi-is-hidden' : '';
	      echo gameindo_card( $gi_gp, array_merge( $gi_card_args, array( 'variant' => 'md', 'pill_label' => $gi_sub ? $gi_sub : $gi_name, 'extra_class' => $gi_hidden ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	      $gi_i++;
      }
      ?>
    </div>

    <?php if ( count( $gi_posts ) > $gi_initial ) : ?>
    <div style="display:flex;justify-content:center;padding-top:28px">
      <button class="gi-btn gi-btn--secondary gi-btn--lg" type="button" id="gi-esports-more" data-load-more data-step="3">Muat Lebih Banyak</button>
    </div>
    <?php endif; ?>
  </div>
</main>

<?php
get_footer();
