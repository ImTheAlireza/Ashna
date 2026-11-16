<?php
/**
 * Plugin Name:       آشنا | ورود و ثبت‌نام با کد یکبارمصرف
 * Plugin URI:        https://parsena.ir/ashna
 * Description:       پلاگین جامع ورود و ثبت‌نام یکپارچه با کد یکبارمصرف (OTP)، ورود با گوگل و تلگرام و ورود بیومتریک بدون رمز (Passkey / WebAuthn) از طریق پیامک، پیام‌رسان بله و ایمیل همراه با یکپارچگی کامل ووکامرس و ویجت اختصاصی المنتور.
 * Version:           3.1.4
 * Requires at least: 5.8
 * Requires PHP:      8.2
 * Author:            پارسنا
 * Author URI:        https://parsena.ir
 * Text Domain:       ashna-otp
 * Domain Path:       /languages
 * License:           GPL v2 or later
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
// This only declares compatibility; it registers no product feature and grants no capability.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ASHNA_OTP_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', ASHNA_OTP_FILE, true );
		}
	}
);

/*
 * File layout, following RTL-Theme's protection rules:
 *
 *   ashna.php              (this file) header + constants — never protected
 *   ashna-license.php      license Loader + diagnostics   — never protected
 *   ashna-core.php         boots the product             <- the only file sent
 *   RTL_License_<hash>.php the license file RTL-Theme issued for this product
 *
 * WordPress reads the `Plugin Name` header from this exact file, so it must
 * stay plain text; RTL-Theme also excludes the main file and the Loader file
 * from protection. Sending ashna.php — or this file only — to the encoder
 * would hide the plugin from the Plugins screen.
 */
$ashna_license_gate = __DIR__ . DIRECTORY_SEPARATOR . 'ashna-license.php';

if ( is_file( $ashna_license_gate ) ) {
	require_once $ashna_license_gate;
} else {
	// The gate is the plugin. If it is gone, stay inert and say so, rather than
	// loading a single service or showing a white screen.
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			?>
			<div class="notice notice-error" dir="rtl">
				<p><strong>افزونهٔ آشنا فعال نیست:</strong> فایل <code dir="ltr">ashna-license.php</code> در پوشهٔ افزونه موجود نیست. بستهٔ نصب را دوباره از حالت فشرده خارج کنید.</p>
				<p><code dir="ltr">license_gate_missing</code></p>
			</div>
			<?php
		}
	);
}
