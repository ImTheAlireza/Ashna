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
 * Ashna unencoded, license-free build entry file.
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
	define( 'ASHNA_OTP_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'ASHNA_OTP_URL' ) ) {
	define( 'ASHNA_OTP_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'ASHNA_OTP_BASENAME' ) ) {
	define( 'ASHNA_OTP_BASENAME', plugin_basename( __FILE__ ) );
}

require_once __DIR__ . '/ashna.php';
