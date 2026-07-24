<?php
/**
 * Create the GameIndo author accounts and print their IDs.
 * Run via wp-cli from the WordPress root:
 *   wp eval-file /path/to/cms/import/create-authors.php
 *
 * Idempotent: an author that already exists (by login) is left as-is and its
 * existing ID is reported. Passwords are randomly generated — reset from
 * wp-admin (Users → user → Set New Password) if the author needs to log in.
 */

if ( ! defined( 'WP_CLI' ) ) {
	echo "Jalankan lewat wp-cli:  wp eval-file cms/import/create-authors.php\n";
	return;
}

$authors = array(
	array( 'gani-fighter',      'gani.fighter@gameindo.com',      'Gani Fighter' ),
	array( 'valentino-poppins', 'valentino.poppins@gameindo.com', 'Valentino Poppins' ),
	array( 'kanata-reyes',      'kanata.reyes@gameindo.com',      'Kanata Reyes' ),
	array( 'bunted-cargo',      'bunted.cargo@gameindo.com',      'Bunted Cargo' ),
	array( 'basudin-kt',        'basudin.kt@gameindo.com',        'Basudin KT' ),
);

WP_CLI::log( '== GameIndo — buat author ==' );

$rows = array();
foreach ( $authors as $a ) {
	list( $login, $email, $name ) = $a;

	$existing = get_user_by( 'login', $login );
	if ( $existing ) {
		$rows[] = array( $name, $existing->ID, $login, 'SUDAH ADA' );
		continue;
	}

	// Split display name into first/last for the profile fields.
	$parts = preg_split( '/\s+/', trim( $name ) );
	$first = array_shift( $parts );
	$last  = implode( ' ', $parts );

	$id = wp_insert_user( array(
		'user_login'   => $login,
		'user_email'   => $email,
		'user_pass'    => wp_generate_password( 18 ),
		'display_name' => $name,
		'nickname'     => $name,
		'first_name'   => $first,
		'last_name'    => $last,
		'role'         => 'author',
	) );

	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( $name . ': ' . $id->get_error_message() );
		continue;
	}
	$rows[] = array( $name, $id, $login, 'DIBUAT' );
}

// Print a clean, copy-back-friendly table of IDs.
WP_CLI::log( '' );
WP_CLI::log( sprintf( '%-20s | %-4s | %-18s | %s', 'Nama', 'ID', 'Username', 'Status' ) );
WP_CLI::log( str_repeat( '-', 60 ) );
foreach ( $rows as $r ) {
	WP_CLI::log( sprintf( '%-20s | %-4d | %-18s | %s', $r[0], $r[1], $r[2], $r[3] ) );
}
WP_CLI::success( 'Selesai — salin kolom ID di atas.' );
