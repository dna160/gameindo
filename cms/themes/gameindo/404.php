<?php
/**
 * 404 — not found.
 *
 * @package GameIndo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main>
  <div class="gi-container" style="padding:80px 0;text-align:center">
    <h1 class="gi-masthead__title" style="font-size:clamp(40px,8vw,88px)">404</h1>
    <p style="color:var(--ink-3);font-size:16px;margin-top:8px">Halaman yang kamu cari tidak ditemukan.</p>
    <p style="margin-top:20px"><a class="gi-btn gi-btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">← Kembali ke beranda</a></p>
    <div style="max-width:520px;margin:32px auto 0">
      <?php get_search_form(); ?>
    </div>
  </div>
</main>
<?php
get_footer();
