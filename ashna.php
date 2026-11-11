<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Ashna_License_Bootstrap {

	const LICENSE_SHA1 = 'a425656c5fe2bfe0aee0122e3f6fab35badcfa8e';

	private static $status = 'pending';

	public static function get_status() {
		return self::$status;
	}

	public static function run() {
		if ( 'pending' !== self::$status ) {
			return;
		}
		self::$status = 'checking';

		$rtl_license_class = 'RTL_License_c1595a7238d64f3a';

		if ( ! class_exists( $rtl_license_class, false ) ) {
			self::fail( 'license_class_missing', 'فایل لایسنس، کلاس مورد انتظار را تعریف نکرده است. نسخهٔ فایل لایسنس را بررسی کنید.' );
			return;
		}

		try {
			$rtl_license_reflection = new ReflectionClass( $rtl_license_class );
			$rtl_license_file       = $rtl_license_reflection->getFileName();
			$rtl_expected_file      = __DIR__ . DIRECTORY_SEPARATOR . $rtl_license_class . '.php';

			if ( ! is_string( $rtl_license_file ) || ! is_file( $rtl_license_file ) ) {
				self::fail( 'license_file_missing', 'فایل لایسنس راست‌چین موجود یا قابل خواندن نیست. فایل معتبر RTL_License_c1595a7238d64f3a.php را کنار index.php قرار دهید.' );
				return;
			}

			if ( realpath( $rtl_license_file ) !== realpath( $rtl_expected_file ) ) {
				self::fail( 'license_class_conflict', 'کلاس هم‌نام لایسنس از فایل دیگری بارگذاری شده است. فعال‌بودن هم‌زمان نسخه‌های دیگر آشنا را بررسی کنید.' );
				return;
			}

			$rtl_license_hash = @sha1_file( $rtl_license_file );
			if ( ! is_string( $rtl_license_hash ) || ! hash_equals( self::LICENSE_SHA1, $rtl_license_hash ) ) {
				self::fail(
					'license_hash_mismatch',
					'هش فایل لایسنس با نسخهٔ مورد انتظار افزونه مطابقت ندارد. از فایل اصلی صادرشده برای همین محصول و همین نسخه استفاده کنید.',
					sprintf( 'expected %s, got %s', self::LICENSE_SHA1, is_string( $rtl_license_hash ) ? $rtl_license_hash : 'unreadable' )
				);
				return;
			}

			$rtl_license = new $rtl_license_class();

			if ( ! method_exists( $rtl_license, 'isActive' ) || ! is_callable( array( $rtl_license, 'isActive' ) ) ) {
				self::fail( 'license_method_invalid', 'متد عمومی isActive در کلاس لایسنس در دسترس نیست. فایل لایسنس با این نسخه سازگار نیست.' );
				return;
			}

			if ( true !== $rtl_license->{'isActive'}() ) {
				self::fail( 'license_inactive', 'لایسنس محصول فعال نیست. آن را از طریق افزونهٔ مدیریت راست‌چین فعال کنید.' );
				return;
			}
		} catch ( Throwable $rtl_license_error ) {
			$rtl_license_diagnostic = sprintf( '%s @ %s:%d', get_class( $rtl_license_error ), basename( $rtl_license_error->getFile() ), $rtl_license_error->getLine() );
			self::fail( 'license_runtime_error', 'اجرای فایل یا بررسی لایسنس با خطا مواجه شد. سازگاری فایل لایسنس، PHP و افزونهٔ مدیریت راست‌چین را بررسی کنید.', $rtl_license_diagnostic );
			return;
		}

		add_action(
			'before_woocommerce_init',
			static function () {
				if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ASHNA_OTP_FILE, true );
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', ASHNA_OTP_FILE, true );
				}
			}
		);

		require_once ASHNA_OTP_PATH . 'includes/class-ashna-autoloader.php';
		Ashna_Autoloader::register();

		if ( ! function_exists( 'ashna_otp' ) ) {
			function ashna_otp() {
				return Ashna_Plugin::instance();
			}
		}

		ashna_otp();
		self::$status = 'active';
	}

	private static function fail( $code, $message, $diagnostic = '' ) {
		self::$status = $code;

		if ( $diagnostic && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Ashna] ' . $code . ': ' . $diagnostic );
		}

		$notice = static function () use ( $code, $message, $diagnostic ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			?>
			<div class="notice notice-error" dir="rtl">
				<p><strong>افزونهٔ آشنا فعال نیست:</strong> <?php echo esc_html( $message ); ?></p>
				<p><code dir="ltr"><?php echo esc_html( $code ); ?></code><?php if ( $diagnostic ) : ?> — <code dir="ltr"><?php echo esc_html( $diagnostic ); ?></code><?php endif; ?></p>
			</div>
			<?php
		};
		add_action( 'admin_notices', $notice );
		add_action( 'network_admin_notices', $notice );
	}
}

if ( did_action( 'plugins_loaded' ) ) {
	Ashna_License_Bootstrap::run();
} else {
	add_action( 'plugins_loaded', array( 'Ashna_License_Bootstrap', 'run' ), 20 );
}
