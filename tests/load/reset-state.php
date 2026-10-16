<?php
/**
 * TEST ONLY — clear the sandbox state between load phases (CLI, inside the site).
 *
 * usage: php reset-state.php [--keep-logs]
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Load WordPress first (see tests/load/load-multiproc.py).\n";
	exit( 1 );
}

global $wpdb;
$keep_logs = in_array( '--keep-logs', (array) ( $argv ?? array() ), true ); // $argv is set when this file is a CLI script; it is optional when another script requires it.

$before = array(
	'logs'     => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Ashna_Logger::table_name() ), // phpcs:ignore WordPress.DB
	'lockouts' => count( Ashna_Security::get_active_lockouts() ),
	'attack'   => Ashna_Rate_Limiter::attack_mode_active(),
);

if ( ! $keep_logs ) {
	$wpdb->query( 'DELETE FROM ' . Ashna_Logger::table_name() ); // phpcs:ignore WordPress.DB
}
foreach ( array_keys( Ashna_Security::get_active_lockouts() ) as $target ) {
	Ashna_Security::unlock_target( $target );
}
// phpcs:disable WordPress.DB
// Both halves of every transient are removed: leaving `_transient_timeout_*` rows behind without
// their value row is exactly the state that makes a later set_transient() keep a passed timeout
// (see Ashna_Security::write_lock_transient()), so the reset must not create it.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_ashna\\_%' OR option_name LIKE '\\_transient\\_timeout\\_ashna\\_%' OR option_name LIKE 'ashna\\_lock%' OR option_name LIKE 'ashna\\_otp\\_cap\\_%' OR option_name LIKE 'ashna\\_capfail\\_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ashna\\_otp\\_notified%'" );
Ashna_Rate_Limiter::end_attack_mode(); // keeps the option row, exactly like the admin button
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name = 'ashna_otp_block_stats'" );
// phpcs:enable
Ashna_Rate_Limiter::end_attack_mode();
wp_cache_flush();

echo wp_json_encode(
	array(
		'before'  => $before,
		'after'   => array( 'logs' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Ashna_Logger::table_name() ) ), // phpcs:ignore WordPress.DB
		'db_size' => (int) ( @filesize( $wpdb->dbh ? ( defined( 'DB_DIR' ) ? DB_DIR . DB_FILE : '' ) : '' ) ?: 0 ),
	)
), "\n";
