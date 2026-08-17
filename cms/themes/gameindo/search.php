<?php
/**
 * Search results — port of search.html. Renders all matches and lets the
 * pillar chips + "Muat Lebih Banyak" filter/reveal client-side, matching the
 * original behaviour.
 *
 * @package GameIndo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$gi_query = get_search_query();
if ( '' === $gi_query && isset( $_GET['q'] ) ) {
	$gi_query = sanitize_text_field( wp_unslash( $_GET['q'] ) );
}

$gi_results = $gi_query ? get_posts( array(
	'post_type'      => 'post',
	'post_status'    => 'publish',
	'posts_per_page' => 60,
	's'              => $gi_query,
	'orderby'        => 'date',
	'order'          => 'DESC',
) ) : array();

$gi_initial = 5;
?>

<main>
  <div class="gi-searchbar-wrap">
    <div class="gi-searchbar-inner">
      <form class="gi-searchbar" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--ink-4)" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="search" name="s" value="<?php echo esc_attr( $gi_query ); ?>" aria-label="Cari artikel" placeholder="Cari artikel…">
        <span class="gi-searchbar__esc">ESC</span>
      </form>
      <div class="gi-search-filters" id="gi-search-filters">
        <span class="gi-filter" data-pillar="" aria-current="true">Semua</span>
        <?php foreach ( gameindo_pillars() as $gi_slug => $gi_name ) : ?>
        <span class="gi-filter" data-pillar="<?php echo esc_attr( $gi_slug ); ?>"><?php echo esc_html( $gi_name ); ?></span>
        <?php endforeach; ?>
        <span class="gi-search-count" id="gi-search-count"><?php echo esc_html( count( $gi_results ) ); ?> hasil</span>
      </div>
    </div>
  </div>

  <div class="gi-container" style="padding-top:30px;padding-bottom:40px">
    <div class="gi-rail-layout">
      <div>
        <div class="gi-result-list" id="gi-search-results">
          <?php
          if ( $gi_results ) {
	          $gi_i = 0;
	          $gi_total = count( $gi_results );
	          foreach ( $gi_results as $gi_rp ) {
		          $gi_hidden = ( $gi_i >= $gi_initial ) ? 'gi-is-hidden' : '';
		          echo gameindo_card( $gi_rp, array( 'variant' => 'h', 'extra_class' => $gi_hidden ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		          if ( $gi_i < $gi_total - 1 ) {
			          echo '<div class="gi-result-divider' . ( $gi_i >= $gi_initial - 1 ? ' gi-is-hidden' : '' ) . '"></div>';
		          }
		          $gi_i++;
	          }
          } elseif ( $gi_query ) {
	          echo '<p style="color:var(--ink-4);font-size:14px;padding:24px 0">Tidak ada hasil untuk pencarian ini.</p>';
          } else {
	          echo '<p style="color:var(--ink-4);font-size:14px;padding:24px 0">Ketik kata kunci untuk mencari artikel.</p>';
          }
          ?>
        </div>
        <?php if ( count( $gi_results ) > $gi_initial ) : ?>
        <div style="display:flex;justify-content:center;padding-top:28px">
          <button class="gi-btn gi-btn--secondary gi-btn--lg" type="button" id="gi-search-more" data-load-more data-step="5" data-with-dividers>Muat Lebih Banyak</button>
        </div>
        <?php endif; ?>
      </div>

      <aside style="display:flex;flex-direction:column;gap:24px">
        <div style="display:flex;flex-direction:column;gap:10px">
          <span style="font-family:var(--font-condensed);font-weight:700;font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-4)">Pencarian Populer</span>
          <div class="gi-tag-cloud" id="gi-search-tags">
            <?php
            $gi_tags = get_tags( array( 'orderby' => 'count', 'order' => 'DESC', 'number' => 6 ) );
            foreach ( $gi_tags as $gi_t ) {
	            echo gameindo_tag_chip( $gi_t->name, get_tag_link( $gi_t->term_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            ?>
          </div>
        </div>
        <div>
          <div class="gi-section-head" style="border-bottom:2px solid var(--ink);padding-bottom:10px">
            <div class="gi-section-head__main"><span class="gi-section-head__tick" aria-hidden="true"></span><div><h2 class="gi-section-head__title">Terpopuler</h2></div></div>
          </div>
          <div id="gi-search-popular">
            <?php
            $gi_pop = gameindo_trending_posts( 3 );
            $gi_pi  = 0;
            foreach ( $gi_pop as $gi_pid ) {
	            $gi_pi++;
	            echo gameindo_rank_row( $gi_pid, $gi_pi ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            ?>
          </div>
        </div>
      </aside>
    </div>
  </div>
</main>

<?php
get_footer();
