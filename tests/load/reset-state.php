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
$keep_logs = in_array( '--keep-logs', (array) $argv, true );

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
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_ashna\\_%' OR option_name LIKE 'ashna\\_lock%' OR option_name LIKE 'ashna\\_otp\\_cap\\_%' OR option_name LIKE 'ashna\\_capfail\\_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ashna\\_otp\\_notified%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name IN ( 'ashna_otp_attack_mode', 'ashna_otp_block_stats' )" );
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
