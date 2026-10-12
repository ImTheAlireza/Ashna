<?php
/**
 * Plugin Activator, Deactivator & Database Schema Manager
 *
 * @package Signa_OTP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Signa_Activator {

	/**
	 * Database schema version
	 */
	const DB_VERSION = '2.3.0';

	/**
	 * Run on plugin activation
	 */
	public static function activate() {
		self::create_tables();

		if ( false === get_option( 'signa_otp_settings' ) ) {
			update_option( 'signa_otp_settings', Signa_Helper::default_settings() );
		}

		if ( ! wp_next_scheduled( 'signa_otp_daily_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'signa_otp_daily_cleanup' );
		}

		update_option( 'signa_otp_db_version', self::DB_VERSION );
	}

	/**
	 * Run on plugin deactivation
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'signa_otp_daily_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'signa_otp_daily_cleanup' );
		}
	}

	/**
	 * Check and run DB upgrade & cron registration if needed
	 */
	public static function maybe_upgrade() {
		$installed_version = get_option( 'signa_otp_db_version' );
		if ( self::DB_VERSION !== $installed_version ) {
			self::create_tables();
			if ( false === get_option( 'signa_otp_settings' ) ) {
				update_option( 'signa_otp_settings', Signa_Helper::default_settings() );
			}
			if ( ! wp_next_scheduled( 'signa_otp_daily_cleanup' ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'signa_otp_daily_cleanup' );
			}
			update_option( 'signa_otp_db_version', self::DB_VERSION );
		}
	}

	/**
	 * Create custom database tables
	 */
	public static function create_tables() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'signa_otp_logs';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			recipient varchar(191) NOT NULL,
			channel varchar(32) NOT NULL DEFAULT 'sms',
			gateway varchar(50) NOT NULL DEFAULT 'sandbox',
			otp_code varchar(20) NOT NULL DEFAULT '',
			otp_hash varchar(255) NOT NULL DEFAULT '',
			status varchar(32) NOT NULL DEFAULT 'sent',
			attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			ip_address varchar(64) NOT NULL DEFAULT '',
			response_message text NULL,
			expires_at datetime NOT NULL,
			verified_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY recipient_idx (recipient),
			KEY ip_address_idx (ip_address),
			KEY status_idx (status),
			KEY created_at_idx (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
