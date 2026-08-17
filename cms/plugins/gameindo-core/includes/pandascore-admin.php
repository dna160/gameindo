<?php
/**
 * wp-admin screen for the PandaScore connection: token, cache status, and the
 * two maintenance actions (test the key, drop the caches).
 *
 * @package GameIndo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function gameindo_core_pandascore_settings_menu() {
	add_submenu_page(
		'gameindo',
		__( 'PandaScore', 'gameindo-core' ),
		__( 'PandaScore', 'gameindo-core' ),
		'manage_options',
		'gameindo-pandascore',
		'gameindo_core_pandascore_settings_page'
	);
}
add_action( 'admin_menu', 'gameindo_core_pandascore_settings_menu' );

function gameindo_core_pandascore_register_settings() {
	register_setting( 'gameindo_pandascore', 'gameindo_pandascore_token', array(
		'type'              => 'string',
		'sanitize_callback' => 'gameindo_core_pandascore_sanitize_token',
		'default'           => '',
	) );
}
add_action( 'admin_init', 'gameindo_core_pandascore_register_settings' );

/**
 * A new key invalidates everything cached under the old one, then queues a warm
 * so the caches fill before the next visitor arrives — a cold front page can
 * only fetch a couple of feeds within its budget, and would otherwise render
 * one game's fixtures while missing a match happening today in another.
 *
 * The warm is queued rather than run here on purpose: twelve sequential HTTP
 * calls inside a settings save is how you hit max_execution_time on shared
 * hosting. The "Bersihkan cache & ambil ulang" button is the synchronous path,
 * where waiting is the point.
 */
function gameindo_core_pandascore_sanitize_token( $value ) {
	$value = trim( sanitize_text_field( $value ) );
	if ( $value === trim( (string) get_option( 'gameindo_pandascore_token', '' ) ) ) {
		return $value;
	}

	gameindo_core_pandascore_flush();
	if ( '' !== $value ) {
		gameindo_core_pandascore_queue_refresh();
	}

	return $value;
}

/**
 * Maintenance actions. Both bounce back to the settings screen with a notice.
 */
function gameindo_core_pandascore_admin_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Tidak diizinkan.', 'gameindo-core' ) );
	}
	check_admin_referer( 'gameindo_pandascore_action' );

	$action = isset( $_POST['gi_action'] ) ? sanitize_key( wp_unslash( $_POST['gi_action'] ) ) : '';
	$notice = '';

	if ( 'flush' === $action ) {
		gameindo_core_pandascore_flush();
		gameindo_core_pandascore_warm();
		$notice = 'flushed';
	} elseif ( 'test' === $action ) {
		$res    = gameindo_core_pandascore_request( 'videogames', array( 'per_page' => 1 ) );
		$notice = is_wp_error( $res ) ? 'failed' : 'ok';
		if ( is_wp_error( $res ) ) {
			set_transient( 'gi_ps_test_message', $res->get_error_message(), MINUTE_IN_SECONDS );
		}
	}

	wp_safe_redirect( add_query_arg( 'gi_notice', $notice, admin_url( 'admin.php?page=gameindo-pandascore' ) ) );
	exit;
}
add_action( 'admin_post_gameindo_pandascore_action', 'gameindo_core_pandascore_admin_action' );

function gameindo_core_pandascore_settings_page() {
	$by_constant = defined( 'GAMEINDO_PANDASCORE_TOKEN' ) && GAMEINDO_PANDASCORE_TOKEN;
	$enabled     = gameindo_core_pandascore_enabled();
	$notice      = isset( $_GET['gi_notice'] ) ? sanitize_key( wp_unslash( $_GET['gi_notice'] ) ) : '';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'PandaScore — Jadwal Match', 'gameindo-core' ); ?></h1>

		<?php if ( 'ok' === $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Koneksi berhasil. Token valid.', 'gameindo-core' ); ?></p></div>
		<?php elseif ( 'failed' === $notice ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php echo esc_html( get_transient( 'gi_ps_test_message' ) ? get_transient( 'gi_ps_test_message' ) : __( 'Koneksi gagal.', 'gameindo-core' ) ); ?></p></div>
		<?php elseif ( 'flushed' === $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Cache dibersihkan dan jadwal diambil ulang.', 'gameindo-core' ); ?></p></div>
		<?php endif; ?>

		<p style="max-width:720px">
			<?php esc_html_e( 'Jadwal pertandingan di homepage dan halaman Esports diambil otomatis dari PandaScore untuk enam game: ML:BB, CS:GO, Valorant, LoL, DotA 2, dan Overwatch. Kalau token kosong atau API sedang bermasalah, situs otomatis kembali memakai data manual di menu Match Center.', 'gameindo-core' ); ?>
		</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'gameindo_pandascore' ); ?>
			<table class="form-table" role="presentation"><tbody>
				<tr>
					<th scope="row"><label for="gameindo_pandascore_token"><?php esc_html_e( 'Token API', 'gameindo-core' ); ?></label></th>
					<td>
						<?php if ( $by_constant ) : ?>
							<p><strong><?php esc_html_e( 'Token diatur lewat wp-config.php', 'gameindo-core' ); ?></strong> — <code>GAMEINDO_PANDASCORE_TOKEN</code>.
							<?php esc_html_e( 'Konstanta itu selalu menang, jadi kolom di bawah tidak dipakai.', 'gameindo-core' ); ?></p>
						<?php endif; ?>
						<input type="password" class="regular-text" id="gameindo_pandascore_token"
							name="gameindo_pandascore_token" autocomplete="off"
							value="<?php echo esc_attr( get_option( 'gameindo_pandascore_token', '' ) ); ?>">
						<p class="description">
							<?php esc_html_e( 'Ambil dari dasbor PandaScore. Cara paling aman: simpan di wp-config.php sebagai', 'gameindo-core' ); ?>
							<code>define( 'GAMEINDO_PANDASCORE_TOKEN', '…' );</code>
							<?php esc_html_e( 'supaya tidak ikut tersimpan di database.', 'gameindo-core' ); ?>
						</p>
					</td>
				</tr>
			</tbody></table>
			<?php submit_button(); ?>
		</form>

		<h2><?php esc_html_e( 'Status', 'gameindo-core' ); ?></h2>
		<?php
		$last_error = get_option( 'gameindo_pandascore_last_error', array() );
		$last_ok    = (int) get_option( 'gameindo_pandascore_last_ok', 0 );
		?>
		<p>
			<?php if ( $enabled ) : ?>
				<span style="color:#0a7c2f;font-weight:600">● <?php esc_html_e( 'Aktif', 'gameindo-core' ); ?></span>
			<?php else : ?>
				<span style="color:#b32d2e;font-weight:600">● <?php esc_html_e( 'Tidak aktif — token belum diisi', 'gameindo-core' ); ?></span>
			<?php endif; ?>
			<?php if ( $last_ok ) : ?>
				&nbsp;·&nbsp;<?php
				/* translators: %s: human-readable time difference. */
				printf( esc_html__( 'pengambilan terakhir berhasil %s lalu', 'gameindo-core' ), esc_html( human_time_diff( $last_ok ) ) );
				?>
			<?php endif; ?>
		</p>
		<?php if ( ! empty( $last_error['message'] ) ) : ?>
			<p style="color:#b32d2e">
				<?php
				/* translators: 1: feed name, 2: error message, 3: time difference. */
				printf(
					esc_html__( 'Error terakhir pada %1$s: %2$s (%3$s lalu)', 'gameindo-core' ),
					esc_html( $last_error['feed'] ),
					esc_html( $last_error['message'] ),
					esc_html( human_time_diff( (int) $last_error['time'] ) )
				);
				?>
			</p>
		<?php endif; ?>

		<table class="widefat striped" style="max-width:720px">
			<thead><tr>
				<th><?php esc_html_e( 'Game', 'gameindo-core' ); ?></th>
				<th><?php esc_html_e( 'Live', 'gameindo-core' ); ?></th>
				<th><?php esc_html_e( 'Akan datang', 'gameindo-core' ); ?></th>
				<th><?php esc_html_e( 'Diperbarui', 'gameindo-core' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( gameindo_core_pandascore_games() as $key => $conf ) :
				$run  = get_transient( 'gi_ps_' . $key . '_running' );
				$up   = get_transient( 'gi_ps_' . $key . '_upcoming' );
				$when = isset( $up['fetched_at'] ) ? (int) $up['fetched_at'] : 0;
				?>
				<tr>
					<td><strong><?php echo esc_html( $conf['label'] ); ?></strong> <span style="color:#777"><?php echo esc_html( $conf['name'] ); ?></span></td>
					<td><?php echo isset( $run['matches'] ) ? count( $run['matches'] ) : '—'; ?></td>
					<td><?php echo isset( $up['matches'] ) ? count( $up['matches'] ) : '—'; ?></td>
					<td><?php
						echo $when
							/* translators: %s: human-readable time difference. */
							? esc_html( sprintf( __( '%s lalu', 'gameindo-core' ), human_time_diff( $when ) ) )
							: esc_html__( 'belum pernah', 'gameindo-core' );
					?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<p style="margin-top:16px;display:flex;gap:8px">
			<?php foreach ( array(
				'test'  => __( 'Tes koneksi', 'gameindo-core' ),
				'flush' => __( 'Bersihkan cache & ambil ulang', 'gameindo-core' ),
			) as $act => $label ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'gameindo_pandascore_action' ); ?>
					<input type="hidden" name="action" value="gameindo_pandascore_action">
					<input type="hidden" name="gi_action" value="<?php echo esc_attr( $act ); ?>">
					<button type="submit" class="button"><?php echo esc_html( $label ); ?></button>
				</form>
			<?php endforeach; ?>
		</p>
	</div>
	<?php
}
