<?php
/**
 * Core Plugin Kernel & Lifecycle Manager
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Ashna_Plugin {

	/**
	 * Singleton instance
	 *
	 * @var Ashna_Plugin|null
	 */
	private static $instance = null;

	/** Prevent duplicate service hooks when bootstrapping after plugins_loaded. */
	private $services_initialized = false;

	/**
	 * Get singleton instance
	 *
	 * @return Ashna_Plugin
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Register lifecycle and initialization hooks
	 */
	private function init_hooks() {
		register_activation_hook( ASHNA_OTP_FILE, array( 'Ashna_Activator', 'activate' ) );
		register_deactivation_hook( ASHNA_OTP_FILE, array( 'Ashna_Activator', 'deactivate' ) );

		add_action( 'ashna_otp_daily_cleanup', array( 'Ashna_Logger', 'run_scheduled_cleanup' ) );
		add_filter( 'cron_schedules', array( 'Ashna_Failover', 'register_cron_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- 5-minute gateway health probe, background only.
		add_action( 'ashna_otp_gateway_probe', array( 'Ashna_Failover', 'run_probe' ) );
		add_filter( 'plugin_action_links_' . ASHNA_OTP_BASENAME, array( $this, 'plugin_action_links' ) );

		if ( did_action( 'plugins_loaded' ) ) {
			// Licensed boot happens after plugins_loaded. Do not attach a callback
			// to an already-fired priority. During activation, only register the
			// lifecycle hooks; WordPress will invoke activate() after inclusion.
			if ( ! defined( 'WP_SANDBOX_SCRAPING' ) || ! WP_SANDBOX_SCRAPING ) {
				$this->on_plugins_loaded();
			}
		} else {
			add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
		}
	}

	/**
	 * Boot services when WordPress plugins are loaded
	 */
	public function on_plugins_loaded() {
		if ( $this->services_initialized ) {
			return;
		}
		$this->services_initialized = true;

		Ashna_Activator::maybe_upgrade();
		Ashna_Failover::ensure_probe_scheduled();

		Ashna_Session::init();
		Ashna_Auth::instance();
		new Ashna_Passkey();
		Ashna_Frontend::instance();
		Ashna_WooCommerce::instance();

		if ( did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' ) ) {
			Ashna_Elementor::instance();
		} else {
			add_action(
				'elementor/loaded',
				function () {
					Ashna_Elementor::instance();
				}
			);
		}

		if ( is_admin() ) {
			Ashna_Admin::instance();
		}
	}

	/**
	 * Add quick action links to the WordPress Plugins page
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=ashna-otp' ) ) . '" style="font-weight:700;color:#2563eb;">تنظیمات</a>';
		$logs_link     = '<a href="' . esc_url( admin_url( 'admin.php?page=ashna-otp-logs' ) ) . '">لاگ کدها</a>';
		array_unshift( $links, $settings_link, $logs_link );
		return $links;
	}
}
