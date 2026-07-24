<?php
/**
 * Generic fallback listing — used for tag archives, date archives, the blog
 * posts index, and anything without a more specific template.
 *
 * @package GameIndo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$gi_title = get_the_archive_title();
if ( is_home() && ! is_front_page() ) {
	$gi_title = single_post_title( '', false );
	if ( '' === $gi_title ) {
		$gi_title = __( 'Artikel Terbaru', 'gameindo' );
	}
}
if ( is_search() ) {
	$gi_title = sprintf( __( 'Hasil pencarian: %s', 'gameindo' ), get_search_query() );
}
?>

<main>
  <section class="gi-masthead">
    <div class="gi-masthead__inner">
      <h1 class="gi-masthead__title"><?php echo wp_kses_post( $gi_title ); ?></h1>
      <?php
      $gi_desc = get_the_archive_description();
      if ( $gi_desc ) {
	      echo '<p class="gi-masthead__desc">' . wp_kses_post( $gi_desc ) . '</p>';
      }
      ?>
    </div>
  </section>

  <div class="gi-container" style="padding-top:32px;padding-bottom:40px">
    <?php if ( have_posts() ) : ?>
    <div class="gi-grid-3">
      <?php
      while ( have_posts() ) :
	      the_post();
	      echo gameindo_card( get_the_ID(), array( 'variant' => 'md' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
      endwhile;
      ?>
    </div>
    <div class="gi-pagination" style="padding-top:32px">
      <?php
      the_posts_pagination( array(
	      'mid_size'  => 2,
	      'prev_text' => '← Sebelumnya',
	      'next_text' => 'Berikutnya →',
      ) );
      ?>
    </div>
    <?php else : ?>
    <p style="color:var(--ink-4);font-size:15px;padding:40px 0">Belum ada artikel di sini.</p>
    <?php endif; ?>
  </div>
</main>

<?php
get_footer();
