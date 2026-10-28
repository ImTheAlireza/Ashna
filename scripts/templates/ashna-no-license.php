<?php
/**
 * Plugin Name:       آشنا | ورود و ثبت‌نام با کد یکبارمصرف
 * Plugin URI:        https://parsena.ir/ashna
 * Description:       پلاگین جامع ورود و ثبت‌نام یکپارچه با کد یکبارمصرف (OTP)، ورود با گوگل و تلگرام و ورود بیومتریک بدون رمز (Passkey / WebAuthn) از طریق پیامک، پیام‌رسان بله و ایمیل همراه با یکپارچگی کامل ووکامرس و ویجت اختصاصی المنتور.
 * Version:           3.1.4
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            پارسنا
 * Author URI:        https://parsena.ir
 * Text Domain:       ashna-otp
 * Domain Path:       /languages
 * License:           GPL v2 or later
 *
 * Ashna unencoded, license-free build bootstrap.
 * Generated only for the separate no-license ZIP; the licensed source is unchanged.
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ASHNA_OTP_VERSION' ) ) {
	define( 'ASHNA_OTP_VERSION', '3.1.4' );
}
if ( ! defined( 'ASHNA_OTP_FILE' ) ) {
	define( 'ASHNA_OTP_FILE', __FILE__ );
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

// Keep WooCommerce compatibility declarations identical to the licensed build.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ASHNA_OTP_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', ASHNA_OTP_FILE, true );
		}
	}
);

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
