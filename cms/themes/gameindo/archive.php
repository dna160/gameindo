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
?>

<main>
  <section class="gi-masthead">
    <div class="gi-masthead__inner">
      <h1 class="gi-masthead__title"><?php echo esc_html( $gi_name ); ?></h1>
      <?php if ( $gi_desc ) : ?><p class="gi-masthead__desc"><?php echo esc_html( $gi_desc ); ?></p><?php endif; ?>
      <?php if ( $gi_is_esports ) : ?>
      <div class="gi-filters">
        <span class="gi-filter" aria-current="true">Semua</span>
        <a class="gi-filter" href="<?php echo esc_url( home_url( '/?s=MPL+ID' ) ); ?>">MPL ID</a>
        <a class="gi-filter" href="<?php echo esc_url( home_url( '/?s=M-Series' ) ); ?>">M-Series</a>
        <a class="gi-filter" href="<?php echo esc_url( home_url( '/?s=Valorant' ) ); ?>">Valorant</a>
        <a class="gi-filter" href="<?php echo esc_url( home_url( '/?s=Free+Fire' ) ); ?>">Free Fire</a>
        <a class="gi-filter" href="<?php echo esc_url( home_url( '/?s=PUBG+Mobile' ) ); ?>">PUBG Mobile</a>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <div class="gi-container" style="padding-top:28px">
    <div class="gi-grid-2" style="grid-template-columns:2fr 1fr;align-items:start">
      <div id="gi-esports-feature"><?php
        if ( $gi_feature ) {
	        $gi_fsub = gameindo_meta( $gi_feature->ID, 'subcategory' );
	        echo gameindo_feature( $gi_feature, array( 'pill_label' => $gi_fsub ? $gi_fsub : $gi_name ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
      ?></div>

      <?php
      $gi_standings = $gi_is_esports ? gameindo_get_standings() : null;
      if ( $gi_is_esports && ! empty( $gi_standings['rows'] ) ) : ?>
      <div class="gi-night-panel">
        <div class="gi-night-panel__head">
          <span class="gi-night-panel__head-title" id="gi-standings-title"><?php echo esc_html( $gi_standings['competition'] ); ?></span>
          <span class="gi-night-panel__head-meta" id="gi-standings-meta"><?php echo esc_html( $gi_standings['season_label'] ); ?></span>
        </div>
        <div class="gi-standings">
          <div class="gi-standings__head"><span>#</span><span>Tim</span><span>M–K</span><span>Poin</span></div>
          <div id="gi-standings-rows"><?php
            foreach ( $gi_standings['rows'] as $gi_row ) {
	            echo gameindo_standings_row( $gi_row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
          ?></div>
        </div>
        <a class="gi-night-panel__cta" href="<?php echo esc_url( gameindo_pillar_url( 'esports' ) ); ?>">Klasemen Lengkap →</a>
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
