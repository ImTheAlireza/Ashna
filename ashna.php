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

final class Ashna_License_Bootstrap {

	/** No service is booted unless the verifier explicitly returns true. */
	private static $status = 'pending';

	/** Read-only diagnostic status; does not re-run the verifier. */
	public static function get_status() {
		return self::$status;
	}

	/**
	 * Run after plugin files and WordPress pluggable functions are available.
	 * During the activation sandbox plugins_loaded has already fired, so this
	 * method is called immediately to register activation hooks in time.
	 */
	public static function run() {
		if ( 'pending' !== self::$status ) {
			return;
		}
		self::$status = 'checking';

		$rtlLicenseClassName = 'RTL_License_c1595a7238d64f3a';
		$rtlLicenseFilePath = __DIR__ . DIRECTORY_SEPARATOR . 'RTL_License_c1595a7238d64f3a.php';

		if ( ! is_file( $rtlLicenseFilePath ) || ! is_readable( $rtlLicenseFilePath ) ) {
			self::fail( 'license_file_missing', 'فایل لایسنس راست‌چین موجود یا قابل خواندن نیست. فایل معتبر RTL_License_c1595a7238d64f3a.php را کنار ashna.php قرار دهید.' );
			return;
		}

		try {
			// Refuse a stale or modified license before executing any of its code.
			$rtlLicenseFileHash = @sha1_file( $rtlLicenseFilePath );
			if ( '3fac0395eb82fa65036a1e9aabcf0aac9c2bcedc' !== $rtlLicenseFileHash ) {
				self::fail( 'license_hash_mismatch', 'هش فایل لایسنس با نسخهٔ مورد انتظار افزونه مطابقت ندارد. از فایل اصلی متناظر با همین نسخه استفاده کنید.' );
				return;
			}

			// An ionCube-encoded license can exit the request if the Loader is absent.
			// Detect it before inclusion so the administrator receives a safe notice.
			$rtlLicenseHead = @file_get_contents( $rtlLicenseFilePath, false, null, 0, 512 );
			if ( is_string( $rtlLicenseHead )
				&& preg_match( '/<\?php\s*\/\/(?:ICB0|[0-9A-F]{5})\b/i', $rtlLicenseHead )
				&& ! extension_loaded( 'ionCube Loader' ) ) {
				self::fail( 'license_loader_missing', 'فایل لایسنس ionCube است و ionCube Loader روی این نسخه از PHP وب فعال نیست. لودر را روی همان PHP وب فعال کنید و صفحه را دوباره بارگذاری کنید.' );
				return;
			}

			// Avoid redeclaration or accepting an unrelated class with the same name.
			if ( class_exists( $rtlLicenseClassName, false ) ) {
				$reflection = new ReflectionClass( $rtlLicenseClassName );
				$class_file = $reflection->getFileName();
				if ( ! $class_file || realpath( $class_file ) !== realpath( $rtlLicenseFilePath ) ) {
					self::fail( 'license_class_conflict', 'کلاس هم‌نام لایسنس از فایل دیگری بارگذاری شده است. فعال‌بودن هم‌زمان نسخه‌های دیگر آشنا را بررسی کنید.' );
					return;
				}
			}

			// RTL loader: this entry file must remain unprotected.
			if ( is_file( $rtlLicenseFilePath ) ) {
				require_once $rtlLicenseFilePath;
			}

			if ( ! class_exists( $rtlLicenseClassName, false ) ) {
				self::fail( 'license_class_missing', 'فایل لایسنس، کلاس مورد انتظار را تعریف نکرده است. نسخهٔ فایل لایسنس را بررسی کنید.' );
				return;
			}

			// Verify the origin even when the license file delegates class definition.
			$reflection = new ReflectionClass( $rtlLicenseClassName );
			$class_file = $reflection->getFileName();
			if ( ! $class_file || realpath( $class_file ) !== realpath( $rtlLicenseFilePath ) ) {
				self::fail( 'license_class_conflict', 'کلاس هم‌نام لایسنس از فایل دیگری بارگذاری شده است. فعال‌بودن هم‌زمان نسخه‌های دیگر آشنا را بررسی کنید.' );
				return;
			}
			if ( ! method_exists( $rtlLicenseClassName, 'isActive' ) || ! ( new ReflectionMethod( $rtlLicenseClassName, 'isActive' ) )->isPublic() ) {
				self::fail( 'license_method_invalid', 'متد عمومی isActive در کلاس لایسنس در دسترس نیست. فایل لایسنس با این نسخه سازگار نیست.' );
				return;
			}

			// Load the protectable verifier only after the official license is loaded.
			$guard_path = ASHNA_OTP_PATH . 'includes/class-ashna-license-guard.php';
			if ( ! is_file( $guard_path ) || ! is_readable( $guard_path ) ) {
				self::fail( 'license_runtime_error', 'فایل بررسی لایسنس افزونه موجود یا قابل خواندن نیست. فایل‌های کامل این نسخه را دوباره نصب کنید.' );
				return;
			}
			// The guard may be encoded even when the vendor license file is not.
			$guard_head = @file_get_contents( $guard_path, false, null, 0, 512 );
			if ( is_string( $guard_head )
				&& preg_match( '/<\?php\s*\/\/(?:ICB0|[0-9A-F]{5})\b/i', $guard_head )
				&& ! extension_loaded( 'ionCube Loader' ) ) {
				self::fail( 'license_loader_missing', 'فایل محافظت‌شدهٔ آشنا به ionCube Loader سازگار روی PHP وب نیاز دارد. لودر را فعال کنید و صفحه را دوباره بارگذاری کنید.' );
				return;
			}
			require_once $guard_path;
			$licensed_boot = Ashna_License_Guard::get_boot_callback();
			if ( ! is_callable( $licensed_boot ) ) {
				self::fail( 'license_inactive', 'لایسنس محصول فعال نیست یا بررسی فایل آن ناموفق بود. آن را از طریق افزونهٔ مدیریت راست‌چین فعال کنید.' );
				return;
			}
		} catch ( Throwable $error ) {
			// Do not print exception messages or traces, which may contain secrets.
			$diagnostic = sprintf( '%s @ %s:%d', get_class( $error ), basename( $error->getFile() ), $error->getLine() );
			self::fail( 'license_runtime_error', 'اجرای فایل یا بررسی لایسنس با خطا مواجه شد. سازگاری فایل لایسنس، PHP و افزونهٔ مدیریت راست‌چین را بررسی کنید.', $diagnostic );
			return;
		}

		// Execute the protected boot callback outside the license catch so errors in
		// plugin services are never hidden or mislabeled as licensing failures.
		if ( false === $licensed_boot() ) {
			self::fail( 'license_inactive', 'بررسی مستقل هستهٔ محافظت‌شده ناموفق بود؛ فعال‌بودن لایسنس و فایل‌های همین نسخه را بررسی کنید.' );
			return;
		}
		self::$status = 'active';
	}

	/** Fail closed with a capability-restricted, escaped diagnostic notice. */
	private static function fail( $code, $message, $diagnostic = '' ) {
		self::$status = $code;
		if ( $diagnostic && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Ashna] ' . $code . ': ' . $diagnostic ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
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
