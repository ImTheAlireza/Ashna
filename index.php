<?php
/**
 * Plugin Name:       آشنا | ورود و ثبت‌نام با کد یکبارمصرف
 * Plugin URI:        https://parsena.ir/ashna
 * Description:       پلاگین جامع ورود و ثبت‌نام یکپارچه با کد یکبارمصرف (OTP) و ورود بیومتریک بدون رمز (Passkey / WebAuthn) از طریق پیامک، پیام‌رسان بله و ایمیل همراه با یکپارچگی کامل ووکامرس و ویجت اختصاصی المنتور.
 * Version:           3.0.1
 * Requires at least: 5.8
 * Requires PHP:      8.2
 * Author:            پارسنا
 * Author URI:        https://parsena.ir
 * Text Domain:       ashna-otp
 * Domain Path:       /languages
 * License:           GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ASHNA_OTP_VERSION' ) ) {
	define( 'ASHNA_OTP_VERSION', '3.0.1' );
}
if ( ! defined( 'ASHNA_OTP_FILE' ) ) {
	define( 'ASHNA_OTP_FILE', __FILE__ );
}
if ( ! defined( 'ASHNA_OTP_PATH' ) ) {
	define( 'ASHNA_OTP_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'ASHNA_OTP_URL' ) ) {
	define( 'ASHNA_OTP_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'ASHNA_OTP_BASENAME' ) ) {
	define( 'ASHNA_OTP_BASENAME', plugin_basename( __FILE__ ) );
}

// WooCommerce feature compatibility: Ashna never reads or writes orders (safe with HPOS), and its
// checkout sign-in box is rendered on the block checkout as well as the classic one.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ASHNA_OTP_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', ASHNA_OTP_FILE, true );
		}
	}
);

require_once ASHNA_OTP_PATH . 'ashna.php';
