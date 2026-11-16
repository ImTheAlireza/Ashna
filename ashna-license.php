<?php
/**
 * Ashna license Loader — never submitted to RTL-Theme's protection service.
 *
 * RTL-Theme's rules are explicit: the main plugin file (the one with the
 * `Plugin Name` header) and the file that holds the license Loader must stay
 * readable and must not be protected; what gets protected is the product code
 * itself. The layout therefore is:
 *
 *     ashna.php              plain text: header + constants + require of this file
 *     ashna-license.php      THIS FILE: license Loader, diagnostics, notices
 *     ashna-core.php         protected: boots the product (the file to send)
 *     RTL_License_<hash>.php the license file RTL-Theme issued for this product
 *
 * The loader itself contains no product code: it verifies the license and only
 * then requires ashna-core.php. When the license is missing or inactive the
 * gate returns before that require, so no service, menu, shortcode, REST route,
 * widget or front-end asset of the plugin is ever registered.
 *
 * Paths: RTL-Theme's protection service rejects include/require without an
 * explicit base, so this file never uses a bare path either.
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Ashna_License_Bootstrap {

	/** RTL-Theme license class name for this product, read from the issued file name. */
	const RTL_LICENSE_CLASS = 'RTL_License_c1595a7238d64f3a';

	/** sha1 of the issued RTL_License file. One changed byte locks the plugin. */
	const RTL_LICENSE_HASH = '321791ab0231890e4569d17c52e64ed93d2eb42d';

	/** Smart Manager screen is registered under the Ashna parent menu. */
	const MENU_SLUG = 'ashna-otp';

	/** No service is booted unless the verifier explicitly returns true. */
	private static $status = 'pending';

	/** License object, only kept when isActive() returned true. */
	private static $license = null;

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

		// RTL-Theme requires the Smart Manager plugin ("مدیریت هوشمند راست‌چین")
		// to be installed and active: it is what validates the license and shows
		// the product state in the dashboard.
		if ( ! self::rtl_manager_active() ) {
			self::fail( 'license_manager_missing' );
			return;
		}

		// ----------------------------------------------------------------- Start RTL License
		// The block below is RTL-Theme's documented check, same order and same
		// conditions: file name -> sha1 -> require -> class_exists/method_exists
		// -> isActive() must return strictly true. The extra branches only
		// explain *why* the check failed; they never widen it.
		$rtlLicenseClassName = self::RTL_LICENSE_CLASS;
		$rtlLicenseFilePath  = __DIR__ . DIRECTORY_SEPARATOR . $rtlLicenseClassName . '.php';
		$rtlLicenseFileHash  = @sha1_file( $rtlLicenseFilePath );

		if ( ! is_file( $rtlLicenseFilePath ) || ! is_readable( $rtlLicenseFilePath ) ) {
			self::fail( 'license_file_missing' );
			return;
		}

		// An ionCube-encoded license cannot be parsed without the Loader: its
		// plain-text stub prints a raw error and exit(199)s the whole request.
		// Detect that before require so a missing Loader is a contained failure.
		$rtlLicenseHead = @file_get_contents( $rtlLicenseFilePath, false, null, 0, 512 );
		if ( is_string( $rtlLicenseHead )
			&& preg_match( '/<\?php\s*\/\/(?:ICB0|[0-9A-F]{5})\b/i', $rtlLicenseHead )
			&& ! extension_loaded( 'ionCube Loader' ) ) {
			self::fail( 'license_loader_missing' );
			return;
		}

		if ( self::RTL_LICENSE_HASH !== $rtlLicenseFileHash ) {
			self::fail( 'license_hash_mismatch' );
			return;
		}

		try {
			// Check before require: the same class may already have been loaded.
			// Accept only a class originating from this exact verified file.
			if ( class_exists( $rtlLicenseClassName, false ) ) {
				$reflection = new ReflectionClass( $rtlLicenseClassName );
				$class_file = $reflection->getFileName();
				if ( ! $class_file || realpath( $class_file ) !== realpath( $rtlLicenseFilePath ) ) {
					self::fail( 'license_class_conflict', '', 'class loaded from ' . ( $class_file ? $class_file : 'unknown' ) );
					return;
				}
			} else {
				require_once $rtlLicenseFilePath;
			}

			if ( ! class_exists( $rtlLicenseClassName, false ) ) {
				self::fail( 'license_class_missing' );
				return;
			}

			$rtlLicenseClass = new $rtlLicenseClassName();
			if ( ! method_exists( $rtlLicenseClass, 'isActive' ) || ! is_callable( array( $rtlLicenseClass, 'isActive' ) ) ) {
				self::fail( 'license_method_invalid' );
				return;
			}

			if ( true !== $rtlLicenseClass->{'isActive'}() ) {
				self::fail( 'license_inactive' );
				return;
			}

			self::$license = $rtlLicenseClass;
		} catch ( Throwable $error ) {
			// Do not print an exception message or trace: it may contain license
			// secrets. The type, filename and line are enough to locate the failure.
			self::fail(
				'license_runtime_error',
				'',
				sprintf( '%s @ %s:%d', get_class( $error ), basename( $error->getFile() ), $error->getLine() )
			);
			return;
		}
		// ------------------------------------------------------------------- End RTL License

		// Product is Active Now, Enable Pro Features.
		// The product lives in ashna-core.php, the file RTL-Theme protects.
		$ashna_core = __DIR__ . DIRECTORY_SEPARATOR . 'ashna-core.php';
		if ( ! is_file( $ashna_core ) || ! is_readable( $ashna_core ) ) {
			self::fail( 'license_core_missing' );
			return;
		}

		// Keep core errors outside the license catch: do not mislabel failures
		// in plugin services as licensing errors or silently swallow them.
		require_once $ashna_core;

		self::register_license_menu();
		self::$status = 'active';
	}

	/**
	 * Is RTL-Theme's Smart Manager plugin installed and active?
	 *
	 * Class probes cover the current builds; the active_plugins scan keeps the
	 * answer independent of plugin load order and folder name.
	 */
	private static function rtl_manager_active() {
		if ( class_exists( 'RtlRequired' ) || class_exists( 'RTL\\RSM\\Core\\ServiceContainer' ) ) {
			return true;
		}

		$plugins = (array) get_option( 'active_plugins', array() );
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			$plugins = array_merge( $plugins, array_keys( (array) get_option( 'active_sitewide_plugins', array() ) ) );
		}

		foreach ( $plugins as $plugin ) {
			if ( is_string( $plugin ) && preg_match( '/rtl[-_]?(care|smart)|care-?unit/i', $plugin ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add the manager's own "مدیریت لایسنس" screen under the Ashna menu.
	 * Only inside the active branch, so a locked plugin registers no menu hook.
	 */
	private static function register_license_menu() {
		if ( ! is_object( self::$license ) || ! method_exists( self::$license, 'admin_menu' ) ) {
			return;
		}
		// The activation sandbox must not attach an admin_menu callback.
		if ( defined( 'WP_SANDBOX_SCRAPING' ) && WP_SANDBOX_SCRAPING ) {
			return;
		}

		add_action(
			'admin_menu',
			static function () {
				try {
					$method = new ReflectionMethod( self::$license, 'admin_menu' );
					if ( $method->getNumberOfRequiredParameters() > 0 ) {
						self::$license->admin_menu( self::MENU_SLUG );
					} else {
						self::$license->admin_menu();
					}
				} catch ( Throwable $error ) {
					unset( $error ); // The manager owns this screen; never break the admin.
				}
			},
			99
		);
	}

	/**
	 * Fail closed with a capability-restricted, escaped notice.
	 *
	 * @param string $code       Machine-readable status.
	 * @param string $message    Persian explanation for administrators.
	 * @param string $diagnostic Optional technical hint, only logged in WP_DEBUG.
	 */
	private static function fail( $code, $message = '', $diagnostic = '' ) {
		self::$status = $code;

		if ( '' === $message ) {
			$messages = self::messages();
			$message  = isset( $messages[ $code ] ) ? $messages[ $code ] : '';
		}

		if ( $diagnostic && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Ashna] ' . $code . ': ' . $diagnostic ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		// The plugin row always gets a way to reach the license screen.
		if ( defined( 'ASHNA_OTP_BASENAME' ) ) {
			add_filter( 'plugin_action_links_' . ASHNA_OTP_BASENAME, array( __CLASS__, 'locked_action_links' ) );
		}

		if ( 'license_manager_missing' === $code ) {
			add_action( 'admin_notices', array( __CLASS__, 'manager_notice' ) );
			add_action( 'network_admin_notices', array( __CLASS__, 'manager_notice' ) );
			return;
		}

		// With the manager active, an inactive license is already reported by the
		// manager itself; a second notice is a known RTL-Theme review rejection.
		if ( 'license_inactive' === $code && self::rtl_manager_active() ) {
			return;
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

	/**
	 * Persian explanation per status code, shown in the admin notice.
	 *
	 * @return array<string,string>
	 */
	private static function messages() {
		return array(
			'license_manager_missing' => 'افزونهٔ «مدیریت لایسنس راست‌چین» روی این سایت نصب یا فعال نیست.',
			'license_loader_missing'  => 'ionCube Loader روی PHP وب‌سایت فعال نیست؛ با پشتیبانی هاست تماس بگیرید.',
			'license_file_missing'    => 'فایل لایسنس محصول در پوشهٔ افزونه پیدا نشد. بستهٔ نصب را کامل از حالت فشرده خارج کنید.',
			'license_hash_mismatch'   => 'فایل لایسنس با فایل اصلی صادرشده برای این محصول یکسان نیست.',
			'license_class_conflict'  => 'کلاس لایسنس از فایل دیگری بارگذاری شده است. نسخهٔ تکراری محصول را حذف کنید.',
			'license_class_missing'   => 'فایل لایسنس کلاس مورد انتظار را تعریف نکرد.',
			'license_method_invalid'  => 'فایل لایسنس تابع فعال‌سازی معتبر ندارد.',
			'license_inactive'        => 'لایسنس این محصول برای این دامنه فعال نیست.',
			'license_runtime_error'   => 'اجرای فایل لایسنس با خطا متوقف شد.',
			'license_core_missing'    => 'فایل اصلی محصول (ashna-core.php) در پوشهٔ افزونه پیدا نشد.',
			'license_gate_missing'    => 'فایل ashna-license.php در پوشهٔ افزونه موجود نیست.',
		);
	}

	/** RTL-Theme's approved sentence for a missing Smart Manager plugin. */
	public static function manager_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="notice notice-warning" dir="rtl">
			<p>
				محصول <strong>آشنا</strong> برای فعال‌سازی نیاز به افزونه <strong>«مدیریت لایسنس راست‌چین»</strong> دارد که در حال حاضر بر روی سایت شما نصب/فعال نشده است.
				جهت راهنمایی بیشتر به <a href="https://www.rtl-theme.com/rcu-install-manual/" target="_blank" rel="noopener noreferrer">این صفحه</a> مراجعه نمایید.
			</p>
		</div>
		<?php
	}

	/**
	 * "فعال‌سازی لایسنس" in the plugin row while the gate is closed.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public static function locked_action_links( $links ) {
		$manager_missing = ! self::rtl_manager_active();

		$label = $manager_missing ? 'نصب مدیریت لایسنس' : 'فعال‌سازی لایسنس';
		$url   = $manager_missing ? 'https://www.rtl-theme.com/rtl-smart-management/' : self::manager_page_url();

		$links['ashna_license'] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( $url ),
			esc_html( $label )
		);

		return $links;
	}

	/** Best-effort URL of the Smart Manager license screen for the plugin row link. */
	private static function manager_page_url() {
		global $menu;

		foreach ( (array) $menu as $item ) {
			if ( ! empty( $item[2] ) && preg_match( '/rtl|rcu|rsm|smart-manage|care-?unit/i', (string) $item[2] ) ) {
				return admin_url( 'admin.php?page=' . rawurlencode( (string) $item[2] ) );
			}
		}

		return admin_url( 'plugins.php' );
	}
}

if ( did_action( 'plugins_loaded' ) ) {
	Ashna_License_Bootstrap::run();
} else {
	add_action( 'plugins_loaded', array( 'Ashna_License_Bootstrap', 'run' ), 20 );
}
