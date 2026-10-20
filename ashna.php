<?php
/**
 * Ashna Bootstrap & RTL-Theme License Gatekeeper.
 *
 * Keep the WordPress plugin header in the unencoded index.php file.
 * License verification stays in this file so it can be encoded as one unit.
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ASHNA_OTP_VERSION' ) ) {
	define( 'ASHNA_OTP_VERSION', '3.1.3' );
}
if ( ! defined( 'ASHNA_OTP_FILE' ) ) {
	define( 'ASHNA_OTP_FILE', __DIR__ . DIRECTORY_SEPARATOR . 'index.php' );
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

		// --------------------------------------------------------------------------------------------------- Start RTL License
		$rtlLicenseClassName = 'RTL_License_c1595a7238d64f3a';
		$rtlLicenseFilePath  = __DIR__ . DIRECTORY_SEPARATOR . $rtlLicenseClassName . '.php';

		if ( ! is_file( $rtlLicenseFilePath ) || ! is_readable( $rtlLicenseFilePath ) ) {
			self::fail( 'license_file_missing', 'فایل لایسنس راست‌چین موجود یا قابل خواندن نیست. فایل معتبر RTL_License_c1595a7238d64f3a.php را کنار ashna.php قرار دهید.' );
			return;
		}

		try {
			$rtlLicenseFileHash = @sha1_file( $rtlLicenseFilePath );
			if ( '4f106eb02a251f2a6951ca3f530596590081002d' !== $rtlLicenseFileHash ) {
				self::fail( 'license_hash_mismatch', 'هش فایل لایسنس با نسخهٔ مورد انتظار افزونه مطابقت ندارد. از فایل اصلی متناظر با همین نسخه استفاده کنید.' );
				return;
			}

			// An ionCube-encoded license cannot be parsed without the Loader: its
			// plain-text stub prints a raw error and exit(199)s the whole request.
			// Detect that here so a missing Loader is an explained, contained failure.
			$rtlLicenseHead = @file_get_contents( $rtlLicenseFilePath, false, null, 0, 512 );
			if ( is_string( $rtlLicenseHead )
				&& preg_match( '/<\?php\s*\/\/(?:ICB0|[0-9A-F]{5})\b/i', $rtlLicenseHead )
				&& ! extension_loaded( 'ionCube Loader' ) ) {
				self::fail( 'license_loader_missing', 'فایل لایسنس ionCube است و ionCube Loader روی این نسخه از PHP وب فعال نیست. لودر را روی همان PHP وب فعال کنید و صفحه را دوباره بارگذاری کنید.' );
				return;
			}

			// Check before require: the same class may already have been loaded.
			// Accept only a class originating from this exact verified file.
			if ( class_exists( $rtlLicenseClassName, false ) ) {
				$reflection = new ReflectionClass( $rtlLicenseClassName );
				$class_file = $reflection->getFileName();
				if ( ! $class_file || realpath( $class_file ) !== realpath( $rtlLicenseFilePath ) ) {
					self::fail( 'license_class_conflict', 'کلاس هم‌نام لایسنس از فایل دیگری بارگذاری شده است. فعال‌بودن هم‌زمان نسخه‌های دیگر آشنا را بررسی کنید.' );
					return;
				}
			} else {
				require_once $rtlLicenseFilePath;
			}

			if ( ! class_exists( $rtlLicenseClassName, false ) ) {
				self::fail( 'license_class_missing', 'فایل لایسنس، کلاس مورد انتظار را تعریف نکرده است. نسخهٔ فایل لایسنس را بررسی کنید.' );
				return;
			}

			$rtlLicenseClass = new $rtlLicenseClassName();
			if ( ! method_exists( $rtlLicenseClass, 'isActive' ) || ! is_callable( array( $rtlLicenseClass, 'isActive' ) ) ) {
				self::fail( 'license_method_invalid', 'متد عمومی isActive در کلاس لایسنس در دسترس نیست. فایل لایسنس با این نسخه سازگار نیست.' );
				return;
			}

			if ( true !== $rtlLicenseClass->{'isActive'}() ) {
				self::fail( 'license_inactive', 'لایسنس محصول فعال نیست. آن را از طریق افزونهٔ مدیریت راست‌چین فعال کنید.' );
				return;
			}
		} catch ( Throwable $error ) {
			// Do not print an exception message or trace: it may contain license
			// secrets. The type, filename and line are enough to locate the failure.
			$diagnostic = sprintf( '%s @ %s:%d', get_class( $error ), basename( $error->getFile() ), $error->getLine() );
			self::fail( 'license_runtime_error', 'اجرای فایل یا بررسی لایسنس با خطا مواجه شد. سازگاری فایل لایسنس، PHP و افزونهٔ مدیریت راست‌چین را بررسی کنید.', $diagnostic );
			return;
		}
		// ----------------------------------------------------------------------------------------------------- End RTL License

		// Keep core errors outside the license catch: do not mislabel failures
		// in plugin services as licensing errors or silently swallow them.
		require_once ASHNA_OTP_PATH . 'includes/class-ashna-autoloader.php';
		Ashna_Autoloader::register();

		if ( ! function_exists( 'ashna_otp' ) ) {
			/** @return Ashna_Plugin */
			function ashna_otp() {
				return Ashna_Plugin::instance();
			}
		}

		ashna_otp();
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
