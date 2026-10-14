<?php
/**
 * Uninstall Ashna OTP Plugin
 *
 * @package Ashna_OTP
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/core/class-ashna-storage.php';
wp_clear_scheduled_hook( Ashna_Storage::key( 'ashna_otp_daily_cleanup' ) );
wp_clear_scheduled_hook( 'ashna_otp_daily_cleanup' );

$settings = get_option( Ashna_Storage::key( 'ashna_otp_settings' ), array() );

if ( ! empty( $settings['delete_data_on_uninstall'] ) ) {
	global $wpdb;

	$logs_table = $wpdb->prefix . Ashna_Storage::key( 'ashna_otp_logs' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$logs_table}" );

	delete_option( Ashna_Storage::key( 'ashna_otp_settings' ) );
	delete_option( Ashna_Storage::key( 'ashna_otp_db_version' ) );
	delete_option( Ashna_Storage::key( 'ashna_active_lockouts' ) );
	delete_transient( Ashna_Storage::key( 'ashna_bale_safir_token' ) );
	delete_transient( Ashna_Storage::key( 'ashna_admin_stats_cache' ) );
	delete_transient( Ashna_Storage::key( 'ashna_admin_chart_cache' ) );
}
