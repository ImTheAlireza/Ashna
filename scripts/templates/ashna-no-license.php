<?php
/**
 * Ashna kernel bootstrap for the license-free build.
 *
 * Loaded by index.php; contains no license check.
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
