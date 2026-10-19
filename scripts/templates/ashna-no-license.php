<?php
/**
 * Ashna unencoded, license-free build bootstrap.
 * Generated only for the separate no-license ZIP; the licensed source is unchanged.
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ASHNA_OTP_VERSION' ) ) {
	define( 'ASHNA_OTP_VERSION', '3.1.0' );
}
if ( ! defined( 'ASHNA_OTP_FILE' ) ) {
	define( 'ASHNA_OTP_FILE', __DIR__ . DIRECTORY_SEPARATOR . 'index.php' );
}
if ( ! defined( 'ASHNA_OTP_PATH' ) ) {
	define( 'ASHNA_OTP_PATH', plugin_dir_path( ASHNA_OTP_FILE ) );
}
if ( ! defined( 'ASHNA_OTP_URL' ) ) {
	define( 'ASHNA_OTP_URL', plugin_dir_url( ASHNA_OTP_FILE ) );
}
if ( ! defined( 'ASHNA_OTP_BASENAME' ) ) {
	define( 'ASHNA_OTP_BASENAME', plugin_basename( ASHNA_OTP_FILE ) );
}

require_once ASHNA_OTP_PATH . 'includes/class-ashna-autoloader.php';
Ashna_Autoloader::register();

if ( ! function_exists( 'ashna_otp' ) ) {
	/**
	 * Return the plugin kernel for this license-free build.
	 *
	 * @return Ashna_Plugin
	 */
	function ashna_otp() {
		return Ashna_Plugin::instance();
	}
}

ashna_otp();
