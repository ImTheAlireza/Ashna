<?php
/**
 * Uninstall Ashna Plugin
 *
 * @package Ashna
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_clear_scheduled_hook( 'ashna_otp_daily_cleanup' );
wp_clear_scheduled_hook( 'ashna_otp_gateway_probe' );

$settings = get_option( 'ashna_otp_settings', array() );

if ( ! empty( $settings['delete_data_on_uninstall'] ) ) {
	global $wpdb;

	$logs_table = $wpdb->prefix . 'ashna_otp_logs';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$logs_table}" );

	delete_option( 'ashna_otp_settings' );
	delete_option( 'ashna_otp_db_version' );
	delete_option( 'ashna_active_lockouts' );
	delete_option( 'ashna_otp_gateway_health' );
	delete_transient( 'ashna_bale_safir_token' );
	delete_transient( 'ashna_admin_stats_cache' );
	delete_transient( 'ashna_admin_chart_cache' );
	delete_option( 'ashna_otp_attack_mode' );
	delete_option( 'ashna_otp_block_stats' );
	delete_option( 'ashna_otp_proxy_hint' );
	delete_transient( 'ashna_otp_notified_attack' );
	delete_transient( 'ashna_otp_notified_daily_cap' );
	// Single-use captcha markers.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'ashna_otp_cap_' ) . '%' ) );
}
