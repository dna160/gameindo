<?php
/**
 * Site footer + mobile drawer.
 *
 * @package GameIndo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gi_logo_url = GAMEINDO_URI . '/assets/logo/gameindo-logo.png';
if ( has_custom_logo() ) {
	$gi_logo_src = wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' );
	if ( $gi_logo_src ) {
		$gi_logo_url = $gi_logo_src;
	}
}
$gi_year = wp_date( 'Y' );
?>

<footer class="gi-footer" data-pillar="home">
  <div class="gi-footer__bar">
    <img src="<?php echo esc_url( $gi_logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
    <span class="gi-footer__tag"><?php echo esc_html( get_bloginfo( 'description' ) ); ?></span>
    <nav class="gi-footer__pillars" aria-label="Pilar (footer)">
      <?php gameindo_flat_menu( 'footer' ); ?>
    </nav>
    <span class="gi-footer__copy">© <?php echo esc_html( $gi_year . ' ' . mb_strtoupper( get_bloginfo( 'name' ) ) ); ?></span>
  </div>
</footer>

<div class="gi-drawer" id="gi-drawer">
  <div class="gi-drawer__scrim" data-drawer-close></div>
  <div class="gi-drawer__panel">
    <div class="gi-drawer__head">
      <img src="<?php echo esc_url( $gi_logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
      <button class="gi-icon-btn" type="button" data-drawer-close aria-label="Tutup menu">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <nav class="gi-drawer__links" aria-label="Pilar utama (mobile)">
      <?php
      if ( has_nav_menu( 'drawer' ) ) {
	      gameindo_flat_menu( 'drawer' );
      } else {
	      gameindo_pillar_nav_fallback();
	      echo '<a href="' . esc_url( home_url( '/?s=' ) ) . '">Cari</a>';
      }
      ?>
    </nav>
  </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
