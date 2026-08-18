<?php
/**
 * Site header: live ticker, top bar, pillar nav, hot-topics + mega menu.
 *
 * @package GameIndo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gi_is_front   = is_front_page();
$gi_show_ticker = $gi_is_front || is_category();
$gi_logo_url   = GAMEINDO_URI . '/assets/logo/gameindo-logo.png';
if ( has_custom_logo() ) {
	$gi_logo_id  = get_theme_mod( 'custom_logo' );
	$gi_logo_src = wp_get_attachment_image_url( $gi_logo_id, 'full' );
	if ( $gi_logo_src ) {
		$gi_logo_url = $gi_logo_src;
	}
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?><?php echo gameindo_body_pillar_attr(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
<?php wp_body_open(); ?>

<?php if ( $gi_show_ticker ) :
	$gi_ticker = gameindo_get_ticker();
	if ( ! empty( $gi_ticker ) ) : ?>
<div class="gi-ticker" role="region" aria-label="Live feed" data-speed="<?php echo esc_attr( apply_filters( 'gameindo_ticker_speed', 45 ) ); ?>">
  <div class="gi-ticker__label"><span class="gi-ticker__dot" aria-hidden="true"></span>Live Feed</div>
  <div class="gi-ticker__viewport">
    <div class="gi-ticker__track" id="gi-ticker-track">
	<?php
	// Rendered twice for the seamless 50% marquee loop (as the original JS did).
	$gi_ticker_html = '';
	foreach ( $gi_ticker as $gi_item ) {
		$gi_ticker_html .= gameindo_ticker_item( $gi_item );
	}
	echo $gi_ticker_html . $gi_ticker_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
    </div>
  </div>
</div>
<?php endif; endif; ?>

<header class="gi-header<?php echo $gi_is_front ? ' gi-header--night' : ''; ?>">
  <div class="gi-header__bar">
    <button class="gi-icon-btn gi-nav-toggle" type="button" data-drawer-open aria-label="Buka menu" aria-expanded="false" aria-controls="gi-drawer">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
    </button>
    <a class="gi-header__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"><img src="<?php echo esc_url( $gi_logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"></a>
    <nav class="gi-header__nav-wrap" aria-label="Pilar utama">
      <div class="gi-pillarnav">
        <?php gameindo_pillar_nav(); ?>
      </div>
    </nav>
    <div class="gi-header__actions">
      <a class="gi-icon-btn" href="<?php echo esc_url( home_url( '/?s=' ) ); ?>" aria-label="Cari"<?php echo is_search() ? ' aria-current="page"' : ''; ?>>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
      </a>
      <button class="gi-icon-btn" type="button" id="gi-megamenu-toggle" aria-label="Menu" aria-expanded="false" aria-controls="gi-megamenu">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><rect x="3" y="4" width="7" height="7" rx="1"/><rect x="14" y="4" width="7" height="7" rx="1"/><rect x="3" y="13" width="7" height="7" rx="1"/><rect x="14" y="13" width="7" height="7" rx="1"/></svg>
      </button>
    </div>
  </div>

  <?php if ( $gi_is_front ) :
	$gi_topics = gameindo_hot_topics( 8 );
	if ( ! empty( $gi_topics ) ) : ?>
  <div class="gi-hottopics">
    <div class="gi-hottopics__row" id="gi-hottopics-row">
      <span class="gi-hottopics__label">Topik Hangat</span>
      <?php foreach ( $gi_topics as $gi_topic ) : ?>
        <a class="gi-hottopics__item<?php echo $gi_topic['live'] ? ' gi-hottopics__item--live' : ''; ?>" href="<?php echo esc_url( $gi_topic['url'] ); ?>"><?php echo esc_html( $gi_topic['label'] ); ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
  <div class="gi-mobile-pillars">
    <?php gameindo_pillar_nav_fallback(); // mobile row: same links, class differs via CSS scope ?>
  </div>
  <?php endif; ?>

  <?php // Inside <header> on purpose: the header is position:sticky, and while
  // this panel sat outside it the menu stayed anchored to the top of the
  // document — scroll down, press the button, and nothing appeared on screen. ?>
  <div class="gi-megamenu" id="gi-megamenu">
    <div class="gi-megamenu__grid">
      <?php gameindo_render_megamenu_columns(); ?>
      <div class="gi-megamenu__trending">
        <span class="gi-megamenu__trending-label">Trending Sekarang</span>
        <div class="gi-megamenu__trending-list" id="gi-megamenu-trending">
          <?php foreach ( gameindo_trending_posts( 4 ) as $gi_tp ) : ?>
            <a href="<?php echo esc_url( get_permalink( $gi_tp ) ); ?>">
              <span class="gi-megamenu__trending-pillar"><?php echo esc_html( gameindo_pillar_name( gameindo_get_pillar( $gi_tp ) ) ); ?></span>
              <span class="gi-megamenu__trending-title"><?php echo esc_html( get_the_title( $gi_tp ) ); ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</header>
