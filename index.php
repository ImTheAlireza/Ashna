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

$ashna_license_class = 'RTL_License_c1595a7238d64f3a';
$ashna_license_file  = __DIR__ . DIRECTORY_SEPARATOR . $ashna_license_class . '.php';

if ( is_file( $ashna_license_file ) && is_readable( $ashna_license_file ) ) {
	$ashna_license_head = @file_get_contents( $ashna_license_file, false, null, 0, 512 );

	if ( is_string( $ashna_license_head ) && preg_match( '/<\?php\s*\/\/(?:ICB0|[0-9A-F]{5})\b/i', $ashna_license_head ) && ! extension_loaded( 'ionCube Loader' ) ) {
		$ashna_license_notice = static function () {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			echo '<div class="notice notice-error" dir="rtl"><p><strong>افزونهٔ آشنا فعال نیست:</strong> فایل لایسنس ionCube است و ionCube Loader روی این نسخه از PHP وب فعال نیست. لودر را روی همان PHP وب فعال کنید.</p><p><code dir="ltr">license_loader_missing</code></p></div>';
		};
		add_action( 'admin_notices', $ashna_license_notice );
		add_action( 'network_admin_notices', $ashna_license_notice );
		return;
	}

	if ( ! class_exists( $ashna_license_class, false ) ) {
		try {
			require_once $ashna_license_file;
		} catch ( Throwable $ashna_license_error ) {
			$ashna_license_notice = static function () {
				if ( ! current_user_can( 'manage_options' ) ) {
					return;
				}
				echo '<div class="notice notice-error" dir="rtl"><p><strong>افزونهٔ آشنا فعال نیست:</strong> اجرای فایل لایسنس با خطا مواجه شد. سازگاری فایل لایسنس و افزونهٔ مدیریت راست‌چین را بررسی کنید.</p><p><code dir="ltr">license_runtime_error</code></p></div>';
			};
			add_action( 'admin_notices', $ashna_license_notice );
			add_action( 'network_admin_notices', $ashna_license_notice );
			return;
		}
	}
}

require_once __DIR__ . '/ashna.php';
