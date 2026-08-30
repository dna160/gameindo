<?php
/**
 * RAWG settings — one field, under the GameIndo menu.
 *
 * @package GameIndo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function gameindo_core_rawg_admin_menu() {
	add_submenu_page(
		'gameindo',
		__( 'RAWG — Rilis Mendatang', 'gameindo-core' ),
		__( 'RAWG', 'gameindo-core' ),
		'manage_options',
		'gameindo-rawg',
		'gameindo_core_rawg_admin_page'
	);
}
add_action( 'admin_menu', 'gameindo_core_rawg_admin_menu', 11 );

function gameindo_core_rawg_register_settings() {
	register_setting( 'gameindo_rawg', 'gameindo_rawg_key', array(
		'type'              => 'string',
		'sanitize_callback' => 'gameindo_core_rawg_sanitize_key',
		'default'           => '',
	) );
}
add_action( 'admin_init', 'gameindo_core_rawg_register_settings' );

/**
 * Saving a key invalidates every cached window — otherwise the panel would keep
 * showing nothing until the TTL expired, and it would look like the key failed.
 */
function gameindo_core_rawg_sanitize_key( $value ) {
	$value = trim( sanitize_text_field( (string) $value ) );
	if ( $value !== trim( (string) get_option( 'gameindo_rawg_key', '' ) ) ) {
		gameindo_core_rawg_flush();
	}
	return $value;
}

function gameindo_core_rawg_flush() {
	global $wpdb;
	// Covers the list windows and the per-game website lookups alike.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_gi_rawg_%' OR option_name LIKE '_transient_timeout_gi_rawg_%' OR option_name LIKE 'gi_rawg_%_at'" );
	delete_option( 'gameindo_rawg_last_error' );
}

function gameindo_core_rawg_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$key   = gameindo_core_rawg_key();
	$const = defined( 'GAMEINDO_RAWG_KEY' ) && GAMEINDO_RAWG_KEY;
	$err   = get_option( 'gameindo_rawg_last_error', array() );
	$ok    = (int) get_option( 'gameindo_rawg_last_ok', 0 );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'RAWG — Rilis Mendatang', 'gameindo-core' ); ?></h1>
		<p>Mengisi panel <strong>Rilis Mendatang</strong> di halaman Video Games: game yang akan rilis dalam beberapa bulan ke depan, diurutkan dari yang paling dekat. Ambil API key gratis di <a href="https://rawg.io/apidocs" target="_blank" rel="noopener noreferrer">rawg.io/apidocs</a>.</p>
		<p><strong>Tanpa key, panelnya tidak muncul</strong> dan halaman Video Games memakai panel <em>Terpopuler</em> seperti sebelumnya — jadi mengosongkan kolom ini aman.</p>

		<?php if ( $const ) : ?>
		<div class="notice notice-info inline"><p>Key sedang diambil dari konstanta <code>GAMEINDO_RAWG_KEY</code> di <code>wp-config.php</code>; kolom di bawah diabaikan.</p></div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'gameindo_rawg' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="gameindo_rawg_key">API key</label></th>
					<td>
						<input type="text" class="regular-text" id="gameindo_rawg_key" name="gameindo_rawg_key"
							autocomplete="off" value="<?php echo esc_attr( get_option( 'gameindo_rawg_key', '' ) ); ?>">
						<p class="description">Disimpan di database dan hanya dipakai di sisi server — tidak pernah dikirim ke browser pengunjung.</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<h2><?php esc_html_e( 'Status', 'gameindo-core' ); ?></h2>
		<table class="widefat striped" style="max-width:640px">
			<tbody>
				<tr><td>Key terisi</td><td><?php echo $key ? '<span style="color:#0f7a50">ya</span>' : '<span style="color:#b32d2e">belum</span>'; ?></td></tr>
				<tr><td>Pengambilan terakhir berhasil</td><td><?php echo $ok ? esc_html( wp_date( 'j M Y H:i', $ok ) ) : '—'; ?></td></tr>
				<tr><td>Galat terakhir</td><td><?php
					echo empty( $err['msg'] ) ? '—' : esc_html( wp_date( 'j M Y H:i', (int) $err['at'] ) . ' — ' . $err['msg'] );
				?></td></tr>
			</tbody>
		</table>
	</div>
	<?php
}
