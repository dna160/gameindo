<?php
/**
 * Google Tag Manager settings — one field, under the GameIndo menu. GA4 (and
 * any other tag: Meta Pixel, Google Ads conversion, …) is configured inside
 * the GTM container itself once the snippet is live, not here — that's the
 * whole point of going through GTM instead of hard-coding GA4 directly.
 *
 * @package GameIndo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function gameindo_core_analytics_admin_menu() {
	add_submenu_page(
		'gameindo',
		__( 'Google Tag Manager', 'gameindo-core' ),
		__( 'Analytics', 'gameindo-core' ),
		'manage_options',
		'gameindo-analytics',
		'gameindo_core_analytics_admin_page'
	);
}
add_action( 'admin_menu', 'gameindo_core_analytics_admin_menu', 11 );

function gameindo_core_analytics_register_settings() {
	register_setting(
		'gameindo_analytics',
		'gameindo_gtm_id',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'gameindo_core_analytics_sanitize_id',
			'default'           => '',
		)
	);
}
add_action( 'admin_init', 'gameindo_core_analytics_register_settings' );

function gameindo_core_analytics_sanitize_id( $value ) {
	$value = strtoupper( trim( sanitize_text_field( (string) $value ) ) );
	if ( '' === $value ) {
		return '';
	}
	if ( ! preg_match( '/^GTM-[A-Z0-9]+$/', $value ) ) {
		add_settings_error(
			'gameindo_gtm_id',
			'gameindo_gtm_id_invalid',
			__( 'Format tidak dikenali — Container ID GTM selalu diawali "GTM-", contoh: GTM-ABC1234. Perubahan tidak disimpan.', 'gameindo-core' )
		);
		return get_option( 'gameindo_gtm_id', '' );
	}
	return $value;
}

function gameindo_core_analytics_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$id    = gameindo_core_gtm_id();
	$const = defined( 'GAMEINDO_GTM_ID' ) && GAMEINDO_GTM_ID;
	$live  = $id && function_exists( 'gameindo_analytics_active' ) && gameindo_analytics_active();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Google Tag Manager', 'gameindo-core' ); ?></h1>
		<p>Container ID GTM dipasang otomatis di setiap halaman situs (di
		<code>&lt;head&gt;</code>, dan langsung setelah <code>&lt;body&gt;</code>
		untuk pengunjung yang mematikan JavaScript). <strong>GA4, Meta Pixel, dan
		tag lain diatur di dalam dashboard GTM</strong> di
		<a href="https://tagmanager.google.com" target="_blank" rel="noopener noreferrer">tagmanager.google.com</a>
		— bukan di sini, dan tidak perlu kode tambahan atau upload tema ulang
		setiap kali menambah tag baru.</p>

		<h2>Belum punya akun GTM / GA4?</h2>
		<ol style="max-width:760px">
			<li>Buka <a href="https://tagmanager.google.com" target="_blank" rel="noopener noreferrer">tagmanager.google.com</a>, masuk dengan akun Google.</li>
			<li><strong>Buat Akun</strong> → nama akun bebas (mis. "GameIndo") → Target platform <strong>Web</strong> → nama container <code>gameindo.com</code> → <strong>Create</strong>, setujui persyaratannya.</li>
			<li>Muncul Container ID berformat <code>GTM-XXXXXXX</code> di pojok kanan atas — itu yang dimasukkan ke kolom di bawah.</li>
			<li>Buka <a href="https://analytics.google.com" target="_blank" rel="noopener noreferrer">analytics.google.com</a> → <strong>Admin → Buat properti</strong> → buat properti GA4 untuk gameindo.com → di bagian <strong>Aliran data → Web</strong>, salin <strong>Measurement ID</strong>-nya (format <code>G-XXXXXXXXXX</code>).</li>
			<li>Kembali ke dashboard GTM → <strong>Tag → Baru</strong> → tipe tag <strong>Google Analytics: Konfigurasi GA4</strong> → isi Measurement ID dari langkah sebelumnya → Pemicu: <strong>All Pages</strong> → beri nama tag → <strong>Simpan</strong>.</li>
			<li>Klik <strong>Submit → Publish</strong> di kanan atas GTM supaya tag-nya aktif di situs. Setiap kali menambah atau mengubah tag di GTM, ulangi langkah <strong>Submit → Publish</strong> ini — mengedit di dashboard saja belum langsung tayang.</li>
		</ol>

		<?php if ( $const ) : ?>
		<div class="notice notice-info inline"><p>Container ID sedang diambil dari konstanta <code>GAMEINDO_GTM_ID</code> di <code>wp-config.php</code>; kolom di bawah diabaikan.</p></div>
		<?php endif; ?>

		<?php settings_errors( 'gameindo_gtm_id' ); ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'gameindo_analytics' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="gameindo_gtm_id">Container ID</label></th>
					<td>
						<input type="text" class="regular-text" id="gameindo_gtm_id" name="gameindo_gtm_id"
							placeholder="GTM-XXXXXXX" autocomplete="off"
							value="<?php echo esc_attr( get_option( 'gameindo_gtm_id', '' ) ); ?>">
						<p class="description">Kosongkan untuk mematikan GTM di situs.</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<h2><?php esc_html_e( 'Status', 'gameindo-core' ); ?></h2>
		<table class="widefat striped" style="max-width:640px">
			<tbody>
				<tr><td>Container ID terisi</td><td><?php echo $id ? '<span style="color:#0f7a50">ya — ' . esc_html( $id ) . '</span>' : '<span style="color:#b32d2e">belum</span>'; ?></td></tr>
				<tr><td>Terpasang di situs</td><td><?php echo $live ? '<span style="color:#0f7a50">ya</span>' : '<span style="color:#b32d2e">tidak</span>'; ?></td></tr>
				<?php if ( $id && ! $live ) : ?>
				<tr><td colspan="2">Container ID sudah terisi tapi belum tayang — biasanya karena plugin analytics lain (Site Kit, GTM4WP, MonsterInsights, dsb.) sedang aktif dan tema sengaja mengalah supaya tidak ada tag GTM dobel. Nonaktifkan salah satunya kalau memang cuma mau satu jalur.</td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}
