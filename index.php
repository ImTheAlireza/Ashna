<?php
/**
 * Plugin Name:       Signa - ورود و ثبت‌نام با کد یکبارمصرف (OTP)
 * Description:       پلاگین جامع ورود و ثبت‌نام یکپارچه با کد یکبارمصرف (OTP) و ورود بیومتریک بدون رمز (Passkey / WebAuthn) از طریق پیامک، پیام‌رسان بله و ایمیل همراه با یکپارچگی کامل ووکامرس و ویجت اختصاصی المنتور.
 * Version:           2.7.7
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Signa Team
 * Text Domain:       signa-otp
 * Domain Path:       /languages
 * License:           GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIGNA_OTP_VERSION', '2.7.7' );
define( 'SIGNA_OTP_FILE', __FILE__ );
define( 'SIGNA_OTP_PATH', plugin_dir_path( __FILE__ ) );
define( 'SIGNA_OTP_URL', plugin_dir_url( __FILE__ ) );
define( 'SIGNA_OTP_BASENAME', plugin_basename( __FILE__ ) );

require_once SIGNA_OTP_PATH . 'signa.php';
