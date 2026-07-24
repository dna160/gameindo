<?php
/**
 * Single post — port of article.html.
 *
 * @package GameIndo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$gi_id     = get_the_ID();
	$gi_pillar = gameindo_get_pillar( $gi_id );
	$gi_author = get_the_author_meta( 'display_name', get_post_field( 'post_author', $gi_id ) );
	$gi_author_url = get_author_posts_url( get_post_field( 'post_author', $gi_id ) );
	$gi_read   = gameindo_read_time( $gi_id );
	$gi_caption = has_post_thumbnail() ? wp_get_attachment_caption( get_post_thumbnail_id( $gi_id ) ) : '';
	?>

<main>
  <article data-pillar="<?php echo esc_attr( $gi_pillar ); ?>">
    <div class="gi-article-head">
      <div class="gi-article-head__kicker">
        <span class="gi-pill" id="gi-article-pill"><?php echo esc_html( gameindo_pillar_name( $gi_pillar ) ); ?></span>
        <span class="gi-article-head__kicker-meta" id="gi-article-kicker-meta"><?php echo esc_html( mb_strtoupper( gameindo_date( $gi_id ) ) ); ?></span>
      </div>
      <h1 class="gi-article-head__title" id="gi-article-title"><?php the_title(); ?></h1>
      <p class="gi-article-head__dek" id="gi-article-dek"><?php echo esc_html( gameindo_get_excerpt( $gi_id, 40 ) ); ?></p>
      <div class="gi-article-head__bar">
        <div class="gi-byline gi-byline--lg">
          <span class="gi-byline__avatar"><?php echo get_avatar( get_post_field( 'post_author', $gi_id ), 96 ); ?></span>
          <div class="gi-byline__meta">
            <a class="gi-byline__author" id="gi-article-author" href="<?php echo esc_url( $gi_author_url ); ?>" style="text-decoration:none"><?php echo esc_html( $gi_author ); ?></a>
            <span class="gi-byline__sub" id="gi-article-sub"><span aria-hidden="true">·</span><span><?php echo esc_html( gameindo_date( $gi_id ) ); ?></span><?php echo $gi_read ? '<span aria-hidden="true">·</span><span>' . esc_html( $gi_read ) . '</span>' : ''; ?></span>
          </div>
        </div>
        <div class="gi-article-head__actions">
          <button class="gi-icon-btn gi-icon-btn--outline" type="button" aria-label="Bagikan" data-share>
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 10.6 6.8-4.2M8.6 13.4l6.8 4.2"/></svg>
          </button>
          <button class="gi-icon-btn gi-icon-btn--outline" type="button" aria-label="Simpan">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
          </button>
          <button class="gi-icon-btn gi-icon-btn--outline" type="button" aria-label="Salin tautan" data-copy-link>
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></svg>
          </button>
        </div>
      </div>
    </div>

    <?php if ( has_post_thumbnail() ) : ?>
    <figure class="gi-article-media" id="gi-article-media">
      <?php the_post_thumbnail( 'gameindo-hero', array( 'alt' => gameindo_image_alt( $gi_id ) ) ); ?>
      <?php if ( $gi_caption ) : ?><figcaption><?php echo esc_html( $gi_caption ); ?></figcaption><?php endif; ?>
    </figure>
    <?php endif; ?>

    <div class="gi-article-body">
      <div id="gi-article-body"><?php the_content(); ?></div>
      <?php
      $gi_tags = get_the_tags();
      if ( $gi_tags ) : ?>
      <div class="gi-article-tags" id="gi-article-tags"><?php
        foreach ( $gi_tags as $gi_tag ) {
	        echo gameindo_tag_chip( $gi_tag->name, get_tag_link( $gi_tag->term_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
      ?></div>
      <?php endif; ?>
    </div>
  </article>

  <?php
  $gi_related = get_posts( array(
	  'post_type'      => 'post',
	  'post_status'    => 'publish',
	  'posts_per_page' => 3,
	  'category_name'  => $gi_pillar,
	  'post__not_in'   => array( $gi_id ),
	  'orderby'        => 'date',
	  'order'          => 'DESC',
  ) );
  if ( $gi_related ) : ?>
  <section class="gi-related">
    <div class="gi-related__inner">
      <div class="gi-section-head">
        <div class="gi-section-head__main">
          <span class="gi-section-head__tick" aria-hidden="true"></span>
          <div><h2 class="gi-section-head__title">Baca Juga</h2></div>
        </div>
      </div>
      <div class="gi-grid-3" style="margin-top:20px" id="gi-related-grid"><?php
        foreach ( $gi_related as $gi_rp ) {
	        echo gameindo_card( $gi_rp, array( 'variant' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
      ?></div>
    </div>
  </section>
  <?php endif; ?>
</main>

<?php
endwhile;
get_footer();
