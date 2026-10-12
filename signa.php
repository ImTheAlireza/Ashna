<?php
/**
 * Signa OTP Bootstrap & RTL-Theme License Gatekeeper
 *
 * This file can be encoded with ionCube / SourceGuardian while index.php
 * holds the plain WordPress plugin header.
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

$signa_rtl_is_active = false;

// --------------------------------------------------------------------------------------------------- Start RTL License
$rtlLicenseClassName  = 'RTL_License_c1595a7238d64f3a';
$rtlLicenseFilePath   = __DIR__ . DIRECTORY_SEPARATOR . $rtlLicenseClassName . '.php';
$rtlLicenseFileHash   = @sha1_file($rtlLicenseFilePath);

if ( $rtlLicenseFileHash === 'f82cd5809e54f6ac8224fcd0e6f7eea62b33a720' && file_exists($rtlLicenseFilePath) ) {
	require_once $rtlLicenseFilePath;

	if ( class_exists($rtlLicenseClassName) && method_exists($rtlLicenseClassName, 'isActive') ) {
		$rtlLicenseClass = new $rtlLicenseClassName();

		if ( $rtlLicenseClass->{'isActive'}() === true ) {
			// Product is Active Now, Enable Pro Features
			$signa_rtl_is_active = true;
		}
	}
}
// ----------------------------------------------------------------------------------------------------- End RTL License

if ( true === $signa_rtl_is_active ) {
	require_once SIGNA_OTP_PATH . 'includes/class-signa-autoloader.php';
	Signa_Autoloader::register();

	if ( ! function_exists( 'signa_otp' ) ) {
		/**
		 * Return main instance of Signa_Plugin
		 *
		 * @return Signa_Plugin
		 */
		function signa_otp() {
			return Signa_Plugin::instance();
		}
	}

	signa_otp();
} else {
	/**
	 * Display admin notice when RTL-Theme license is not active.
	 * No menus, shortcodes, forms, or hooks are registered until activated.
	 */
	$signa_inactive_notice = static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="notice notice-error" style="border-right-color:#dc2626;padding:12px 16px;direction:rtl;text-align:right;font-family:Tahoma,sans-serif;">
			<p style="margin:0;font-size:13.5px;line-height:1.8;">
				<strong style="color:#b91c1c;">افزونه ورود و ثبت‌نام سیگنا (Signa OTP) فعال نیست:</strong>
				برای فعال‌سازی منوها، شورت‌کدها و فرم‌های ورود، لطفاً لایسنس محصول را از طریق
				<strong>«افزونه مدیریت راست‌چین»</strong> در پیشخوان وردپرس فعال نمایید.
			</p>
		</div>
		<?php
	};

	add_action( 'admin_notices', $signa_inactive_notice );
	add_action( 'network_admin_notices', $signa_inactive_notice );
}
