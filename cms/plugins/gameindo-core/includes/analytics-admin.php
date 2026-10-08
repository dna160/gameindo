<?php
/**
 * Analytics settings — Google Tag Manager Container ID, and an optional
 * direct GA4 Measurement ID for sites that want GA4 live without first
 * configuring a GA4 Configuration tag inside GTM's own dashboard.
 *
 * @package GameIndo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function gameindo_core_analytics_admin_menu() {
	add_submenu_page(
		'gameindo',
		__( 'Analytics & Search Console', 'gameindo-core' ),
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
			'sanitize_callback' => 'gameindo_core_analytics_sanitize_gtm_id',
			'default'           => '',
		)
	);
	register_setting(
		'gameindo_analytics',
		'gameindo_ga4_id',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'gameindo_core_analytics_sanitize_ga4_id',
			'default'           => '',
		)
	);
	register_setting(
		'gameindo_analytics',
		'gameindo_gsc_verification',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'gameindo_core_analytics_sanitize_gsc',
			'default'           => '',
		)
	);
}
add_action( 'admin_init', 'gameindo_core_analytics_register_settings' );

function gameindo_core_analytics_sanitize_gtm_id( $value ) {
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

function gameindo_core_analytics_sanitize_ga4_id( $value ) {
	$value = strtoupper( trim( sanitize_text_field( (string) $value ) ) );
	if ( '' === $value ) {
		return '';
	}
	if ( ! preg_match( '/^G-[A-Z0-9]+$/', $value ) ) {
		add_settings_error(
			'gameindo_ga4_id',
			'gameindo_ga4_id_invalid',
			__( 'Format tidak dikenali — Measurement ID GA4 selalu diawali "G-", contoh: G-ABCDE12345. Perubahan tidak disimpan.', 'gameindo-core' )
		);
		return get_option( 'gameindo_ga4_id', '' );
	}
	return $value;
}

/**
 * Accepts either the bare token or the full "google-site-verification=TOKEN"
 * string Search Console shows when you pick the "Domain name provider"/DNS
 * flow by mistake instead of the HTML-tag flow — either way, only the token
 * is ever stored.
 */
function gameindo_core_analytics_sanitize_gsc( $value ) {
	$value = trim( sanitize_text_field( (string) $value ) );
	if ( '' === $value ) {
		return '';
	}
	if ( 0 === stripos( $value, 'google-site-verification=' ) ) {
		$value = substr( $value, strlen( 'google-site-verification=' ) );
	}
	$value = trim( $value );
	if ( ! preg_match( '/^[A-Za-z0-9_-]{10,128}$/', $value ) ) {
		add_settings_error(
			'gameindo_gsc_verification',
			'gameindo_gsc_verification_invalid',
			__( 'Format tidak dikenali — tempel persis kode dari Search Console (metode "Tag HTML"), boleh dengan atau tanpa awalan "google-site-verification=". Perubahan tidak disimpan.', 'gameindo-core' )
		);
		return get_option( 'gameindo_gsc_verification', '' );
	}
	return $value;
}

function gameindo_core_analytics_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$gtm_id     = gameindo_core_gtm_id();
	$gtm_const  = defined( 'GAMEINDO_GTM_ID' ) && GAMEINDO_GTM_ID;
	$ga4_id     = gameindo_core_ga4_id();
	$ga4_const  = defined( 'GAMEINDO_GA4_ID' ) && GAMEINDO_GA4_ID;
	$gsc_code   = gameindo_core_gsc_verification();
	$gsc_const  = defined( 'GAMEINDO_GSC_VERIFICATION' ) && GAMEINDO_GSC_VERIFICATION;
	$gtm_live   = function_exists( 'gameindo_gtm_active' ) && gameindo_gtm_active();
	$ga4_live   = function_exists( 'gameindo_ga4_active' ) && gameindo_ga4_active();
	$deferring  = ( $gtm_id && ! $gtm_live ) || ( $ga4_id && ! $ga4_live );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Analytics & Search Console', 'gameindo-core' ); ?></h1>
		<p>Dua kolom independen — isi salah satu atau keduanya, tergantung cara
		Anda ingin mengukur situs:</p>
		<ul style="list-style:disc;margin-left:20px;max-width:760px">
			<li><strong>Container ID GTM</strong> — memasang Google Tag Manager. Tag
			apa pun (GA4, Meta Pixel, dll.) lalu diatur di dashboard
			<a href="https://tagmanager.google.com" target="_blank" rel="noopener noreferrer">tagmanager.google.com</a>,
			tanpa perlu upload tema ulang tiap kali menambah tag baru.</li>
			<li><strong>Measurement ID GA4</strong> — memasang GA4 langsung
			(<code>gtag.js</code> resmi Google), tanpa perlu menyentuh dashboard GTM
			sama sekali. Paling cepat kalau Anda cuma butuh GA4 dan belum mau
			repot mengatur tag di GTM.</li>
		</ul>
		<div class="notice notice-warning inline" style="max-width:760px">
			<p><strong>Jangan isi keduanya untuk GA4 yang sama</strong> — kalau
			Measurement ID GA4 di bawah sudah terisi, dan <em>nanti</em> Anda juga
			menambahkan tag <strong>GA4 Configuration</strong> di dalam GTM untuk
			properti GA4 yang sama, setiap pageview/event akan tercatat <strong>dua
			kali</strong>. Pilih satu jalur untuk satu properti GA4: langsung lewat
			kolom di bawah, atau lewat tag di dalam GTM — bukan keduanya.</p>
		</div>

		<h2>Belum punya akun GTM / GA4?</h2>
		<ol style="max-width:760px">
			<li>Buka <a href="https://tagmanager.google.com" target="_blank" rel="noopener noreferrer">tagmanager.google.com</a>, masuk dengan akun Google → <strong>Buat Akun</strong> → nama akun bebas (mis. "GameIndo") → Target platform <strong>Web</strong> → nama container <code>gameindo.com</code> → <strong>Create</strong>, setujui persyaratannya. Container ID (<code>GTM-XXXXXXX</code>) muncul di pojok kanan atas.</li>
			<li>Buka <a href="https://analytics.google.com" target="_blank" rel="noopener noreferrer">analytics.google.com</a> → <strong>Admin → Buat properti</strong> → buat properti GA4 untuk gameindo.com → di bagian <strong>Aliran data → Web</strong>, salin <strong>Measurement ID</strong>-nya (<code>G-XXXXXXXXXX</code>).</li>
			<li><strong>Jalur cepat (langsung, tanpa GTM dashboard):</strong> tempel Measurement ID itu ke kolom GA4 di bawah — selesai, GA4 langsung aktif begitu tema/plugin diunggah.</li>
			<li><strong>Jalur lewat GTM (kalau berencana menambah tag lain juga):</strong> tempel Container ID ke kolom GTM di bawah, lalu di dashboard GTM: <strong>Tag → Baru</strong> → tipe <strong>Google Analytics: Konfigurasi GA4</strong> → isi Measurement ID dari langkah 2 → Pemicu <strong>All Pages</strong> → <strong>Simpan</strong> → <strong>Submit → Publish</strong> di kanan atas (wajib, tanpa ini tag belum tayang). Kalau memilih jalur ini, biarkan kolom GA4 di bawah kosong.</li>
		</ol>

		<h2>Google Search Console</h2>
		<p style="max-width:760px">Membuktikan kepemilikan domain ke Google, supaya Anda bisa memantau performa
		pencarian, submit sitemap, dan lihat error crawl untuk gameindo.com. Belum
		punya? Buka <a href="https://search.google.com/search-console" target="_blank" rel="noopener noreferrer">search.google.com/search-console</a>
		→ <strong>Tambahkan properti</strong> → pilih jenis <strong>Awalan URL</strong> (bukan "Domain") →
		masukkan <code>https://gameindo.com</code> → pilih metode verifikasi
		<strong>Tag HTML</strong> → salin nilai di dalam <code>content="…"</code> →
		tempel ke kolom di bawah → kembali ke Search Console, klik <strong>Verifikasi</strong>.</p>

		<?php if ( $gtm_const || $ga4_const || $gsc_const ) : ?>
		<div class="notice notice-info inline">
			<p>
			<?php if ( $gtm_const ) : ?>Container ID GTM sedang diambil dari konstanta <code>GAMEINDO_GTM_ID</code> di <code>wp-config.php</code>; kolomnya di bawah diabaikan.<br><?php endif; ?>
			<?php if ( $ga4_const ) : ?>Measurement ID GA4 sedang diambil dari konstanta <code>GAMEINDO_GA4_ID</code> di <code>wp-config.php</code>; kolomnya di bawah diabaikan.<br><?php endif; ?>
			<?php if ( $gsc_const ) : ?>Kode Search Console sedang diambil dari konstanta <code>GAMEINDO_GSC_VERIFICATION</code> di <code>wp-config.php</code>; kolomnya di bawah diabaikan.<?php endif; ?>
			</p>
		</div>
		<?php endif; ?>

		<?php settings_errors( 'gameindo_gtm_id' ); ?>
		<?php settings_errors( 'gameindo_ga4_id' ); ?>
		<?php settings_errors( 'gameindo_gsc_verification' ); ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'gameindo_analytics' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="gameindo_gtm_id">Container ID (GTM)</label></th>
					<td>
						<input type="text" class="regular-text" id="gameindo_gtm_id" name="gameindo_gtm_id"
							placeholder="GTM-XXXXXXX" autocomplete="off"
							value="<?php echo esc_attr( get_option( 'gameindo_gtm_id', '' ) ); ?>">
						<p class="description">Kosongkan untuk mematikan GTM di situs.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="gameindo_ga4_id">Measurement ID (GA4)</label></th>
					<td>
						<input type="text" class="regular-text" id="gameindo_ga4_id" name="gameindo_ga4_id"
							placeholder="G-XXXXXXXXXX" autocomplete="off"
							value="<?php echo esc_attr( get_option( 'gameindo_ga4_id', '' ) ); ?>">
						<p class="description">Kosongkan kalau GA4 sudah/akan diatur sebagai tag di dalam GTM sebagai gantinya.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="gameindo_gsc_verification">Kode verifikasi Search Console</label></th>
					<td>
						<input type="text" class="regular-text" id="gameindo_gsc_verification" name="gameindo_gsc_verification"
							placeholder="eSMZ9QbvCsmzq8RQiedBd4if82KgOhxL1lMFSfjkzXU" autocomplete="off"
							value="<?php echo esc_attr( get_option( 'gameindo_gsc_verification', '' ) ); ?>">
						<p class="description">Dari metode verifikasi "Tag HTML" di Search Console — boleh tempel dengan atau tanpa awalan <code>google-site-verification=</code>, keduanya diterima.</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<h2><?php esc_html_e( 'Status', 'gameindo-core' ); ?></h2>
		<table class="widefat striped" style="max-width:640px">
			<tbody>
				<tr><td>Container ID GTM terisi</td><td><?php echo $gtm_id ? '<span style="color:#0f7a50">ya — ' . esc_html( $gtm_id ) . '</span>' : '<span style="color:#b32d2e">belum</span>'; ?></td></tr>
				<tr><td>GTM terpasang di situs</td><td><?php echo $gtm_live ? '<span style="color:#0f7a50">ya</span>' : '<span style="color:#b32d2e">tidak</span>'; ?></td></tr>
				<tr><td>Measurement ID GA4 terisi</td><td><?php echo $ga4_id ? '<span style="color:#0f7a50">ya — ' . esc_html( $ga4_id ) . '</span>' : '<span style="color:#b32d2e">belum</span>'; ?></td></tr>
				<tr><td>GA4 langsung terpasang di situs</td><td><?php echo $ga4_live ? '<span style="color:#0f7a50">ya</span>' : '<span style="color:#b32d2e">tidak</span>'; ?></td></tr>
				<tr><td>Kode Search Console terisi</td><td><?php echo $gsc_code ? '<span style="color:#0f7a50">ya</span>' : '<span style="color:#b32d2e">belum</span>'; ?></td></tr>
				<?php if ( $deferring ) : ?>
				<tr><td colspan="2">ID sudah terisi tapi belum tayang — biasanya karena plugin analytics lain (Site Kit, GTM4WP, MonsterInsights, dsb.) sedang aktif dan tema sengaja mengalah supaya tidak ada tag dobel. Nonaktifkan salah satunya kalau memang cuma mau satu jalur.</td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}
