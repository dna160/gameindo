<?php
/**
 * Category / pillar archive — port of esports.html, generalized to every
 * pillar. The esports pillar additionally shows the live standings panel.
 *
 * @package GameIndo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$gi_obj    = get_queried_object();
$gi_slug   = ( $gi_obj && isset( $gi_obj->slug ) ) ? $gi_obj->slug : '';
$gi_pillar = array_key_exists( $gi_slug, gameindo_pillars() ) ? $gi_slug : 'home';
$gi_name   = ( $gi_obj && isset( $gi_obj->name ) ) ? $gi_obj->name : gameindo_pillar_name( $gi_pillar );
$gi_desc   = ( $gi_obj && ! empty( $gi_obj->description ) ) ? $gi_obj->description : '';
$gi_is_esports = ( 'esports' === $gi_pillar );

// All posts in this pillar (feature = newest, rest = grid).
$gi_posts = get_posts( array(
	'post_type'      => 'post',
	'post_status'    => 'publish',
	'posts_per_page' => 60,
	'category_name'  => $gi_slug,
	'orderby'        => 'date',
	'order'          => 'DESC',
) );
$gi_feature = ! empty( $gi_posts ) ? array_shift( $gi_posts ) : null;
$gi_initial = 6; // grid items visible before "Muat Lebih Banyak"

// Esports schedule panel. The ?game= chip filters the panel only — the article
// feed below stays the full Esports pillar.
$gi_games    = gameindo_esports_games();
$gi_game     = $gi_is_esports ? gameindo_current_game() : 'all';
$gi_schedule = $gi_is_esports ? gameindo_get_schedule( $gi_game, array( 'limit' => 12 ) ) : array();
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
      <?php endif; ?>
    </div>
  </section>

  <div class="gi-container" style="padding-top:28px">
    <div class="gi-pillar-layout">
      <div id="gi-esports-feature"><?php
        if ( $gi_feature ) {
	        $gi_fsub = gameindo_meta( $gi_feature->ID, 'subcategory' );
	        echo gameindo_feature( $gi_feature, array( 'pill_label' => $gi_fsub ? $gi_fsub : $gi_name ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
      ?></div>

      <?php if ( $gi_is_esports ) : ?>
      <div class="gi-night-panel" id="jadwal" style="scroll-margin-top:80px">
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

        <?php if ( ! empty( $gi_schedule ) ) : ?>
        <div class="gi-schedule"><?php
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
	      // same slot the esports standings occupy. Same reads + recency blend
	      // as the homepage rail, scoped to this pillar; the [data-pillar]
	      // scope colours it per pillar.
	      $gi_pop = gameindo_trending_posts( 5, array( 'category' => $gi_slug ) );
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

    <div class="gi-grid-3" style="margin-top:20px" id="gi-esports-grid">
      <?php
      $gi_i = 0;
      foreach ( $gi_posts as $gi_gp ) {
	      $gi_sub    = gameindo_meta( $gi_gp->ID, 'subcategory' );
	      $gi_hidden = ( $gi_i >= $gi_initial ) ? 'gi-is-hidden' : '';
	      echo gameindo_card( $gi_gp, array( 'variant' => 'md', 'pill_label' => $gi_sub ? $gi_sub : $gi_name, 'extra_class' => $gi_hidden ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
