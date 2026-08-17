<?php
/**
 * Author archive — port of author.html.
 *
 * @package GameIndo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$gi_author_id = get_queried_object_id();
$gi_name  = get_the_author_meta( 'display_name', $gi_author_id );
$gi_bio   = get_the_author_meta( 'description', $gi_author_id );
$gi_role  = get_user_meta( $gi_author_id, 'gi_role', true );
$gi_role  = $gi_role ? $gi_role : 'Kontributor';
$gi_articles = get_user_meta( $gi_author_id, 'gi_articles_count', true );
$gi_since    = get_user_meta( $gi_author_id, 'gi_since_year', true );
$gi_reads    = get_user_meta( $gi_author_id, 'gi_monthly_reads', true );

// Initials for the avatar chip.
$gi_parts = preg_split( '/\s+/', trim( $gi_name ) );
$gi_initials = '';
foreach ( array_slice( $gi_parts, 0, 2 ) as $gi_w ) {
	$gi_initials .= mb_substr( $gi_w, 0, 1 );
}
$gi_initials = mb_strtoupper( $gi_initials );

// If no explicit count set, fall back to the real published count.
if ( '' === $gi_articles ) {
	$gi_articles = (int) count_user_posts( $gi_author_id, 'post', true );
}

$gi_author_posts = get_posts( array(
	'post_type'      => 'post',
	'post_status'    => 'publish',
	'posts_per_page' => 60,
	'author'         => $gi_author_id,
	'orderby'        => 'date',
	'order'          => 'DESC',
) );
?>

<main>
  <section class="gi-author-head">
    <div class="gi-author-head__inner">
      <span class="gi-author-avatar" id="gi-author-avatar"><?php echo esc_html( $gi_initials ); ?></span>
      <div class="gi-author-info">
        <span class="gi-author-info__role" id="gi-author-role"><?php echo esc_html( $gi_role ); ?></span>
        <h1 class="gi-author-info__name" id="gi-author-name"><?php echo esc_html( $gi_name ); ?></h1>
        <p class="gi-author-info__bio" id="gi-author-bio"><?php echo esc_html( $gi_bio ); ?></p>
        <div class="gi-author-stats" id="gi-author-stats">
          <span><b><?php echo esc_html( number_format_i18n( (int) $gi_articles ) ); ?></b> ARTIKEL</span>
          <?php if ( $gi_since ) : ?><span>SEJAK <b><?php echo esc_html( $gi_since ); ?></b></span><?php endif; ?>
          <?php if ( $gi_reads ) : ?><span><b><?php echo esc_html( $gi_reads ); ?></b> DIBACA / BULAN</span><?php endif; ?>
        </div>
      </div>
      <button class="gi-btn gi-btn--primary" type="button">Ikuti</button>
    </div>
  </section>

  <nav class="gi-tabs" aria-label="Arsip artikel" id="gi-author-tabs">
    <button class="gi-tab" type="button" data-sort="latest" aria-current="true">Terbaru</button>
    <button class="gi-tab" type="button" data-sort="popular">Terpopuler</button>
    <button class="gi-tab" type="button" data-sort="series">Liputan Series</button>
  </nav>

  <div class="gi-container" style="padding-top:26px;padding-bottom:40px">
    <div class="gi-rail-layout">
      <div class="gi-grid-2" id="gi-author-grid">
        <?php
        if ( $gi_author_posts ) {
	        foreach ( $gi_author_posts as $gi_ap ) {
		        $gi_sub   = gameindo_meta( $gi_ap->ID, 'subcategory' );
		        $gi_rd    = gameindo_parse_reads( gameindo_meta( $gi_ap->ID, 'reads' ) );
		        // Cards are emitted newest-first; the "Terpopuler" tab sorts by
		        // data-reads and JS sorts are stable, so equal reads keep that
		        // newest-first order — the same rule the rails use.
		        echo gameindo_card( $gi_ap, array(
			        'variant'     => 'md',
			        'show_author' => false,
			        'attrs'       => array( 'data-reads' => $gi_rd, 'data-sub' => $gi_sub ),
		        ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	        }
        } else {
	        echo '<p style="color:var(--ink-4);font-size:14px">Belum ada artikel.</p>';
        }
        ?>
      </div>
      <aside>
        <div class="gi-section-head" style="border-bottom:2px solid var(--ink);padding-bottom:10px">
          <div class="gi-section-head__main"><span class="gi-section-head__tick" aria-hidden="true"></span><div><h2 class="gi-section-head__title" id="gi-author-popular-title">Terpopuler dari <?php echo esc_html( explode( ' ', $gi_name )[0] ); ?></h2></div></div>
        </div>
        <div id="gi-author-popular">
          <?php
          // Most-read first, newest first when reads tie — so this author's
          // newest piece isn't held back by having no reads figure yet.
          $gi_pop = gameindo_rank_popular( $gi_author_posts, 3 );
          $gi_pi = 0;
          foreach ( $gi_pop as $gi_pp ) {
	          $gi_pi++;
	          echo gameindo_rank_row( $gi_pp, $gi_pi ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
          }
          ?>
        </div>
      </aside>
    </div>
  </div>
</main>

<?php
get_footer();
