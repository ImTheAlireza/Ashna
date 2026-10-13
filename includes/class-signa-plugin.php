<?php
/**
 * Core Plugin Kernel & Lifecycle Manager
 *
 * @package Signa_OTP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Signa_Plugin {

	/**
	 * Singleton instance
	 *
	 * @var Signa_Plugin|null
	 */
	private static $instance = null;

	/** Prevent duplicate service hooks when bootstrapping after plugins_loaded. */
	private $services_initialized = false;

	/**
	 * Get singleton instance
	 *
	 * @return Signa_Plugin
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
		register_activation_hook( SIGNA_OTP_FILE, array( 'Signa_Activator', 'activate' ) );
		register_deactivation_hook( SIGNA_OTP_FILE, array( 'Signa_Activator', 'deactivate' ) );

		add_action( 'signa_otp_daily_cleanup', array( 'Signa_Logger', 'run_scheduled_cleanup' ) );
		add_filter( 'plugin_action_links_' . SIGNA_OTP_BASENAME, array( $this, 'plugin_action_links' ) );

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

		Signa_Activator::maybe_upgrade();

		Signa_Auth::instance();
		new Signa_Passkey();
		Signa_Frontend::instance();
		Signa_WooCommerce::instance();

		if ( did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' ) ) {
			Signa_Elementor::instance();
		} else {
			add_action(
				'elementor/loaded',
				function () {
					Signa_Elementor::instance();
				}
			);
		}

		if ( is_admin() ) {
			Signa_Admin::instance();
		}
	}

	/**
	 * Add quick action links to the WordPress Plugins page
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=signa-otp' ) ) . '" style="font-weight:700;color:#2563eb;">تنظیمات</a>';
		$logs_link     = '<a href="' . esc_url( admin_url( 'admin.php?page=signa-otp-logs' ) ) . '">لاگ کدها</a>';
		array_unshift( $links, $settings_link, $logs_link );
		return $links;
	}
}
