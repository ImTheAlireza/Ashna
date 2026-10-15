<?php
/**
 * TEST ONLY — shared load-test accounts. Used by `tests/load/ashna-load-fixtures.php` (the live
 * HTTP sandbox) and by `tests/load/load-multiproc.py` (real PHP processes against the CLI site),
 * so both instruments see exactly the same crowd.
 *
 * Account i: login `loaduser{i}`, phone `0999` + 7 digits of i, password `Load-Pass-1!`.
 * Inserted directly (one reusable password hash per batch) because the point of the test is the
 * sign-in path, not user creation; the rows are identical to what wp_insert_user() writes.
 *
 * @param int $count  How many accounts.
 * @param int $offset First index.
 * @return array { created: int, from: int, to: int, seconds: float }
 */
function ashna_load_seed_users( $count, $offset = 0 ) {
	global $wpdb;
	$started = microtime( true );
	$hash    = wp_hash_password( 'Load-Pass-1!' );
	$caps    = maybe_serialize( array( 'subscriber' => true ) );
	$now     = current_time( 'mysql' );
	$created = 0;

	for ( $i = $offset; $i < $offset + $count; $i++ ) {
		$login = 'loaduser' . $i;
		// phpcs:disable WordPress.DB
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE user_login = %s", $login ) ) ) {
			continue;
		}
		$wpdb->insert(
			$wpdb->users,
			array(
				'user_login'      => $login,
				'user_pass'       => $hash,
				'user_nicename'   => $login,
				'user_email'      => $login . '@load.test',
				'user_registered' => $now,
				'user_status'     => 0,
				'display_name'    => $login,
			)
		);
		$id = (int) $wpdb->insert_id;
		if ( ! $id ) {
			continue;
		}
		foreach ( array(
			'wp_capabilities' => $caps,
			'wp_user_level'   => '0',
			'ashna_phone'     => ashna_load_phone( $i ),
			'session_tokens'  => maybe_serialize( array() ),
		) as $key => $value ) {
			$wpdb->insert( $wpdb->usermeta, array( 'user_id' => $id, 'meta_key' => $key, 'meta_value' => $value ) );
		}
		// phpcs:enable WordPress.DB
		++$created;
	}
	wp_cache_flush();

	return array(
		'created' => $created,
		'from'    => $offset,
		'to'      => max( $offset, $offset + $count - 1 ),
		'seconds' => round( microtime( true ) - $started, 2 ),
	);
}

/**
 * Phone number of load account $index.
 *
 * @param int $index Account index.
 * @return string
 */
function ashna_load_phone( $index ) {
	return '0999' . str_pad( (string) ( $index % 10000000 ), 7, '0', STR_PAD_LEFT );
}

/**
 * Remove every account the load test created (and whatever Ashna auto-registered for those phones).
 *
 * @return array { deleted: int }
 */
function ashna_load_purge_users() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/user.php';
	// phpcs:ignore WordPress.DB
	$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->users} WHERE user_login LIKE 'loaduser%' OR user_login LIKE '0999%'" );
	foreach ( $ids as $id ) {
		wp_delete_user( (int) $id );
	}
	wp_cache_flush();
	return array( 'deleted' => count( $ids ) );
}

/** CLI entry: php seed-users.php <count> [offset] | php seed-users.php --purge */
if ( 'cli' === PHP_SAPI && isset( $argv[1] ) ) {
	if ( ! defined( 'ABSPATH' ) ) {
		echo "Load WordPress first (see tests/load/load-multiproc.py).\n";
		exit( 1 );
	}
	if ( '--purge' === $argv[1] ) {
		echo wp_json_encode( ashna_load_purge_users() ), "\n";
		exit( 0 );
	}
	$count  = (int) $argv[1];
	$offset = isset( $argv[2] ) ? (int) $argv[2] : 0;
	echo wp_json_encode( ashna_load_seed_users( $count, $offset ) ), "\n";
}
