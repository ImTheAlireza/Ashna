<?php
/**
 * Plugin Activator, Deactivator & Database Schema Manager
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Activator {

	/**
	 * Database schema version
	 */
	const DB_VERSION = '2.6.0';

	/**
	 * Run on plugin activation
	 */
	public static function activate() {
		self::create_tables();

		if ( false === get_option( 'ashna_otp_settings' ) ) {
			update_option( 'ashna_otp_settings', Ashna_Helper::default_settings() );
		}

		if ( ! wp_next_scheduled( 'ashna_otp_daily_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ashna_otp_daily_cleanup' );
		}
		Ashna_Failover::ensure_probe_scheduled();
		self::ensure_attack_mode_option();
		self::ensure_digits_meta_flag();

		update_option( 'ashna_otp_db_version', self::DB_VERSION );
	}

	/**
	 * Store the "attack mode is off" state once.
	 *
	 * The option is read on every front-end page view and every OTP request, so it is autoloaded
	 * and always present: a missing autoloaded option still costs one options-table query per
	 * request, while an existing one travels with the already-loaded alloptions array.
	 */
	public static function ensure_attack_mode_option() {
		if ( false === get_option( Ashna_Rate_Limiter::ATTACK_OPTION ) ) {
			add_option(
				Ashna_Rate_Limiter::ATTACK_OPTION,
				array(
					'since' => 0,
					'until' => 0,
					'peak'  => 0,
				),
				'',
				true
			);
		}
	}

	/**
	 * Decide once whether the Digits phone keys can exist in this database.
	 *
	 * Digits stores the phone under `digits_phone`, `digits_phone_no` and `digt_countrycode`; Ashna
	 * reads and writes those keys for cross-plugin compatibility. Their existence is a property of
	 * the site (has Digits ever been used here?), not of a request, so it is answered once and then
	 * read for free from the autoloaded options. The answer is never downgraded: a site that
	 * deactivates Digits after using it keeps the keys reachable for its accounts.
	 */
	public static function ensure_digits_meta_flag() {
		if ( null !== get_option( Ashna_Auth::DIGITS_META_OPTION, null ) ) {
			return;
		}

		global $wpdb;

		$present = class_exists( 'Digits' );
		if ( ! $present ) {
			$keys    = array( 'digits_phone', 'digits_phone_no', 'digt_countrycode' );
			$holders = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one-off activation check, %s placeholders are prepared.
			$present = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->usermeta} WHERE meta_key IN ({$holders}) LIMIT 1", $keys ) );
		}

		add_option( Ashna_Auth::DIGITS_META_OPTION, $present ? 'yes' : 'no', '', true );
	}

	/**
	 * Run on plugin deactivation
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'ashna_otp_daily_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'ashna_otp_daily_cleanup' );
		}
		Ashna_Failover::unschedule_probe();
	}

	/**
	 * Check and run DB upgrade & cron registration if needed
	 */
	public static function maybe_upgrade() {
		$installed_version = get_option( 'ashna_otp_db_version' );
		if ( self::DB_VERSION !== $installed_version ) {
			self::create_tables();
			if ( false === get_option( 'ashna_otp_settings' ) ) {
				update_option( 'ashna_otp_settings', Ashna_Helper::default_settings() );
			}
			if ( ! wp_next_scheduled( 'ashna_otp_daily_cleanup' ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ashna_otp_daily_cleanup' );
			}
			self::ensure_attack_mode_option();
			self::ensure_digits_meta_flag();
			update_option( 'ashna_otp_db_version', self::DB_VERSION );
		}
	}

	/**
	 * Create custom database tables
	 */
	public static function create_tables() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'ashna_otp_logs';
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
			ip_subnet varchar(64) NOT NULL DEFAULT '',
			response_message text NULL,
			expires_at datetime NOT NULL,
			verified_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY recipient_idx (recipient),
			KEY ip_address_idx (ip_address),
			KEY ip_subnet_idx (ip_subnet,created_at),
			KEY status_idx (status),
			KEY created_at_idx (created_at),
			KEY recipient_time_idx (recipient,created_at,status,channel),
			KEY ip_time_idx (ip_address,created_at,channel),
			KEY created_at_status_channel_idx (created_at,status,channel)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		self::create_sso_table();
	}

	/**
	 * Identity table for the social sign-in providers (Google, Telegram).
	 *
	 * One row per (provider, subject). The unique key is what makes a concurrent double callback
	 * harmless: the second insert loses the race and is simply rejected.
	 */
	public static function create_sso_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'ashna_sso_identities';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			provider varchar(20) NOT NULL,
			subject varchar(191) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			email varchar(191) NOT NULL DEFAULT '',
			phone varchar(32) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			last_login datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY provider_subject (provider,subject),
			KEY user_idx (user_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
