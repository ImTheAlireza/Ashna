<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Ashna_License_Bootstrap {

	const LICENSE_SHA1 = 'a425656c5fe2bfe0aee0122e3f6fab35badcfa8e';

	private static $status = 'pending';

	private static $authorized = false;

	public static function get_status() {
		return self::$status;
	}

	public static function run() {
		if ( 'pending' !== self::$status ) {
			return;
		}
		self::$status = 'checking';

		$ashna_license_callable = self::is_method_callable();
		if ( false === $ashna_license_callable ) {
			self::fail( 'license_method_invalid', 'متد عمومی isActive در کلاس لایسنس در دسترس نیست. فایل لایسنس با این نسخه سازگار نیست.' );
			return;
		}

		try {
// --------------------------------------------------------------------------------------------------- Start RTL License

$rtlLicenseClassName = 'RTL_License_c1595a7238d64f3a';

if (
    class_exists($rtlLicenseClassName, false)
    && method_exists($rtlLicenseClassName, 'isActive')
) {
    try {
        $rtlLicenseReflection = new \ReflectionClass($rtlLicenseClassName);
        $rtlLicenseFilePath   = $rtlLicenseReflection->getFileName();

        if (is_string($rtlLicenseFilePath) && is_file($rtlLicenseFilePath)) {
            $rtlLicenseFileHash = @sha1_file($rtlLicenseFilePath);

            if (
                is_string($rtlLicenseFileHash)
                && hash_equals('a425656c5fe2bfe0aee0122e3f6fab35badcfa8e', $rtlLicenseFileHash)
            ) {
                $rtlLicenseClass = new $rtlLicenseClassName();

                if ($rtlLicenseClass->{'isActive'}() === true) {
                    // لایسنس فعال است؛ کد امکانات لایسنس‌دار را اینجا قرار دهید.
                    self::$authorized = true;
                }
            }
        }
    } catch (\ReflectionException $e) {
        // بررسی لایسنس ناموفق بود.
    }
}

// ----------------------------------------------------------------------------------------------------- End RTL License
		} catch ( \Throwable $rtl_license_error ) {
			$rtl_license_diagnostic = sprintf( '%s @ %s:%d', get_class( $rtl_license_error ), basename( $rtl_license_error->getFile() ), $rtl_license_error->getLine() );
			self::fail( 'license_runtime_error', 'اجرای فایل یا بررسی لایسنس با خطا مواجه شد. سازگاری فایل لایسنس، PHP و افزونهٔ مدیریت راست‌چین را بررسی کنید.', $rtl_license_diagnostic );
			return;
		}

		if ( ! self::$authorized ) {
			$ashna_license_failure = self::diagnose();
			self::fail( $ashna_license_failure[0], $ashna_license_failure[1], isset( $ashna_license_failure[2] ) ? $ashna_license_failure[2] : '' );
			return;
		}

		self::boot();
		self::$status = 'active';
	}

	private static function is_method_callable() {
		$ashna_license_class = 'RTL_License_c1595a7238d64f3a';

		if ( ! class_exists( $ashna_license_class, false ) || ! method_exists( $ashna_license_class, 'isActive' ) ) {
			return true;
		}

		try {
			$ashna_license_method = new \ReflectionMethod( $ashna_license_class, 'isActive' );
		} catch ( \ReflectionException $ashna_license_error ) {
			return false;
		}

		return $ashna_license_method->isPublic();
	}

	private static function diagnose() {
		$ashna_license_class    = 'RTL_License_c1595a7238d64f3a';
		$ashna_license_expected = __DIR__ . DIRECTORY_SEPARATOR . $ashna_license_class . '.php';

		if ( ! is_file( $ashna_license_expected ) || ! is_readable( $ashna_license_expected ) ) {
			return array( 'license_file_missing', 'فایل لایسنس راست‌چین موجود یا قابل خواندن نیست. فایل معتبر RTL_License_c1595a7238d64f3a.php را کنار index.php قرار دهید.' );
		}

		if ( ! class_exists( $ashna_license_class, false ) ) {
			return array( 'license_class_missing', 'فایل لایسنس، کلاس مورد انتظار را تعریف نکرده است. نسخهٔ فایل لایسنس را بررسی کنید.' );
		}

		try {
			$ashna_license_reflection = new \ReflectionClass( $ashna_license_class );
			$ashna_license_file       = $ashna_license_reflection->getFileName();
		} catch ( \ReflectionException $ashna_license_error ) {
			return array( 'license_file_missing', 'فایل لایسنس راست‌چین موجود یا قابل خواندن نیست. فایل معتبر RTL_License_c1595a7238d64f3a.php را کنار index.php قرار دهید.' );
		}

		if ( ! is_string( $ashna_license_file ) || ! is_file( $ashna_license_file ) ) {
			return array( 'license_file_missing', 'فایل لایسنس راست‌چین موجود یا قابل خواندن نیست. فایل معتبر RTL_License_c1595a7238d64f3a.php را کنار index.php قرار دهید.' );
		}

		if ( realpath( $ashna_license_file ) !== realpath( $ashna_license_expected ) ) {
			return array( 'license_class_conflict', 'کلاس هم‌نام لایسنس از فایل دیگری بارگذاری شده است. فعال‌بودن هم‌زمان نسخه‌های دیگر آشنا را بررسی کنید.' );
		}

		$ashna_license_hash = @sha1_file( $ashna_license_file );

		if ( ! is_string( $ashna_license_hash ) || ! hash_equals( self::LICENSE_SHA1, $ashna_license_hash ) ) {
			return array(
				'license_hash_mismatch',
				'هش فایل لایسنس با نسخهٔ مورد انتظار افزونه مطابقت ندارد. از فایل اصلی صادرشده برای همین محصول و همین نسخه استفاده کنید.',
				sprintf( 'expected %s, got %s', self::LICENSE_SHA1, is_string( $ashna_license_hash ) ? $ashna_license_hash : 'unreadable' ),
			);
		}

		if ( ! method_exists( $ashna_license_class, 'isActive' ) || ! self::is_method_callable() ) {
			return array( 'license_method_invalid', 'متد عمومی isActive در کلاس لایسنس در دسترس نیست. فایل لایسنس با این نسخه سازگار نیست.' );
		}

		return array( 'license_inactive', 'لایسنس محصول فعال نیست. آن را از طریق افزونهٔ مدیریت راست‌چین فعال کنید.' );
	}

	private static function boot() {
		add_action(
			'before_woocommerce_init',
			static function () {
				if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ASHNA_OTP_FILE, true );
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', ASHNA_OTP_FILE, true );
				}
			}
		);

		require_once __DIR__ . '/includes/class-ashna-autoloader.php';
		Ashna_Autoloader::register();

		if ( ! function_exists( 'ashna_otp' ) ) {
			function ashna_otp() {
				return Ashna_Plugin::instance();
			}
		}

		ashna_otp();
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
