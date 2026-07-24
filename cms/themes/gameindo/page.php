<?php
/**
 * Static page — port of the article layout, without the news chrome.
 *
 * @package GameIndo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	?>
<main>
  <article data-pillar="home">
    <div class="gi-article-head">
      <h1 class="gi-article-head__title"><?php the_title(); ?></h1>
    </div>
    <?php if ( has_post_thumbnail() ) : ?>
    <figure class="gi-article-media"><?php the_post_thumbnail( 'gameindo-hero' ); ?></figure>
    <?php endif; ?>
    <div class="gi-article-body">
      <div class="gi-page-content"><?php the_content(); ?></div>
      <?php
      wp_link_pages( array(
	      'before' => '<div class="gi-page-links">' . __( 'Halaman:', 'gameindo' ),
	      'after'  => '</div>',
      ) );
      ?>
    </div>
  </article>
</main>
	<?php
endwhile;
get_footer();
