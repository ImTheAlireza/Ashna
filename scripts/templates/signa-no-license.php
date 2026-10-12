<?php
/**
 * Signa OTP unencoded, license-free build bootstrap.
 * Generated only for the separate no-license ZIP; the licensed source is unchanged.
 *
 * @package Signa_OTP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SIGNA_OTP_VERSION' ) ) {
	define( 'SIGNA_OTP_VERSION', '2.7.7' );
}
if ( ! defined( 'SIGNA_OTP_FILE' ) ) {
	define( 'SIGNA_OTP_FILE', __DIR__ . DIRECTORY_SEPARATOR . 'index.php' );
}
if ( ! defined( 'SIGNA_OTP_PATH' ) ) {
	define( 'SIGNA_OTP_PATH', plugin_dir_path( SIGNA_OTP_FILE ) );
}
if ( ! defined( 'SIGNA_OTP_URL' ) ) {
	define( 'SIGNA_OTP_URL', plugin_dir_url( SIGNA_OTP_FILE ) );
}
if ( ! defined( 'SIGNA_OTP_BASENAME' ) ) {
	define( 'SIGNA_OTP_BASENAME', plugin_basename( SIGNA_OTP_FILE ) );
}

require_once SIGNA_OTP_PATH . 'includes/class-signa-autoloader.php';
Signa_Autoloader::register();

if ( ! function_exists( 'signa_otp' ) ) {
	/**
	 * Return the plugin kernel for this license-free build.
	 *
	 * @return Signa_Plugin
	 */
	function signa_otp() {
		return Signa_Plugin::instance();
	}
}

signa_otp();
