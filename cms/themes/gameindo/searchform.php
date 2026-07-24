<?php
/**
 * Search form, styled like the original gi-searchbar.
 *
 * @package GameIndo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form class="gi-searchbar" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--ink-4)" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
  <input type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" aria-label="Cari artikel" placeholder="Cari artikel…">
</form>
