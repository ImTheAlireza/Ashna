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
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the plugin's own table.
	$wpdb->query( "DROP TABLE IF EXISTS {$logs_table}" );

	// Social sign-in identities (Google / Telegram). The user accounts themselves are never touched.
	$sso_table = $wpdb->prefix . 'ashna_sso_identities';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the plugin's own table.
	$wpdb->query( "DROP TABLE IF EXISTS {$sso_table}" );

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
	delete_option( 'ashna_otp_digits_meta' ); // Ashna_Auth::DIGITS_META_OPTION.
	delete_transient( 'ashna_otp_notified_attack' );
	delete_transient( 'ashna_otp_notified_daily_cap' );
	// Cached OIDC discovery documents and signing keys.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_ashna_sso_' ) . '%', $wpdb->esc_like( '_transient_timeout_ashna_sso_' ) . '%' ) );
	// Single-use captcha markers.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'ashna_otp_cap_' ) . '%' ) );
}
