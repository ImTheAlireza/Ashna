<?php
/**
 * Plugin Name: Ashna Activation Diagnostics (Temporary)
 * Description: گزارش خصوصی و موقت محیط PHP و مراحل فعال‌سازی آشنا؛ بدون تغییر لایسنس.
 * Version: 1.2.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Ashna_Activation_Diagnostics {
	const META_PREFIX = '_ashna_activation_diagnostics_';
	private static $report = null;
	private static $user_id = 0;
	private static $reserve = '';
	private static $capture_message = false;
	private static $message_saved = false;

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'observe' ), 1 );
	}

	private static function allowed() {
		return current_user_can( 'manage_options' ) && current_user_can( 'activate_plugins' );
	}

	public static function environment() {
		global $wp_version;
		return array(
			'php_version' => PHP_VERSION,
			'php_sapi' => PHP_SAPI,
			'architecture_bits' => PHP_INT_SIZE * 8,
			'thread_safety' => PHP_ZTS ? 'TS' : 'NTS',
			'ioncube_version' => function_exists( 'ioncube_loader_version' ) ? ioncube_loader_version() : 'NOT LOADED',
			'wordpress_version' => isset( $wp_version ) ? $wp_version : 'unknown',
			'memory_limit' => ini_get( 'memory_limit' ),
			'wp_debug' => defined( 'WP_DEBUG' ) && WP_DEBUG,
		);
	}

	/** Passive observer: never includes, activates or modifies the target plugin. */
	public static function observe() {
		global $pagenow;
		if ( 'plugins.php' !== $pagenow || ! self::allowed() ) {
			return;
		}
		$action = isset( $_GET['action'] ) && is_string( $_GET['action'] ) ? wp_unslash( $_GET['action'] ) : '';
		$plugin = isset( $_GET['plugin'] ) && is_string( $_GET['plugin'] ) ? wp_unslash( $_GET['plugin'] ) : '';
		$nonce = isset( $_GET['_wpnonce'] ) && is_string( $_GET['_wpnonce'] ) ? wp_unslash( $_GET['_wpnonce'] ) : '';
		if ( ! in_array( $action, array( 'activate', 'error_scrape' ), true ) || ! self::is_target( $plugin ) ) {
			return;
		}
		$nonce_action = 'activate' === $action ? 'activate-plugin_' . $plugin : 'plugin-activation-error_' . $plugin;
		if ( ! current_user_can( 'activate_plugin', $plugin ) || ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			return;
		}

		self::$user_id = get_current_user_id();
		if ( 'activate' === $action ) {
			$until = (int) get_user_meta( self::$user_id, self::META_PREFIX . 'capture_until', true );
			self::$capture_message = $until > time();
			// Consume consent before inclusion; a later request cannot reuse it.
			if ( $until ) {
				delete_user_meta( self::$user_id, self::META_PREFIX . 'capture_until' );
			}
		}
		self::$reserve = str_repeat( 'x', 65536 );
		self::$report = array(
			'tool_version' => '1.2.0',
			'attempt_id' => wp_generate_uuid4(),
			'time_utc' => gmdate( 'c' ),
			'request_kind' => $action,
			'plugin' => $plugin,
			'environment' => self::environment(),
			'files' => self::files( $plugin ),
			'phase' => 'before_plugin_include',
		);
		// Keep an initial checkpoint even if the process is killed without shutdown.
		self::save( 'checkpoint' );
		register_shutdown_function( array( __CLASS__, 'shutdown' ) );
		add_filter( 'wp_php_error_message', array( __CLASS__, 'fatal_screen' ), 1, 2 );
		add_action( 'activate_plugin', array( __CLASS__, 'bootstrap_returned' ), -PHP_INT_MAX, 1 );
		add_action( 'activate_' . $plugin, array( __CLASS__, 'activation_entered' ), -PHP_INT_MAX );
		add_action( 'activate_' . $plugin, array( __CLASS__, 'activation_returned' ), PHP_INT_MAX );
		add_action( 'activated_plugin', array( __CLASS__, 'activated' ), PHP_INT_MAX, 1 );
	}

	public static function is_target( $plugin ) {
		// Deliberately limited to the distributed Ashna entry points, no traversal.
		return is_string( $plugin ) && 1 === preg_match( '~\Aashna(?:-otp)?/index\.php\z~i', $plugin );
	}

	private static function files( $plugin ) {
		$dir = WP_PLUGIN_DIR . '/' . dirname( $plugin );
		$license = $dir . '/RTL_License_c1595a7238d64f3a.php';
		$readable = is_file( $license ) && is_readable( $license );
		$result = array(
			'license_present' => is_file( $license ),
			'license_readable' => $readable,
			'license_matches_expected_sha1' => $readable ? ( '4f106eb02a251f2a6951ca3f530596590081002d' === @sha1_file( $license ) ) : null,
		);
		foreach ( array( 'index.php', 'ashna.php' ) as $name ) {
			$file = $dir . '/' . $name;
			$result[ $name . '_sha256' ] = is_readable( $file ) ? @hash_file( 'sha256', $file ) : null;
		}
		return $result;
	}

	public static function bootstrap_returned( $plugin ) {
		if ( $plugin === self::$report['plugin'] ) {
			self::phase( 'plugin_include_returned' );
		}
	}
	public static function activation_entered() { self::phase( 'activation_callbacks_entered' ); }
	public static function activation_returned() { self::phase( 'activation_callbacks_returned' ); }
	public static function activated( $plugin ) {
		if ( $plugin === self::$report['plugin'] ) {
			self::phase( 'wordpress_activated_plugin_hook' );
		}
	}
	private static function phase( $phase ) {
		self::$report['phase'] = $phase;
		self::save( 'checkpoint' );
	}

	/** No raw exception text, stack trace, server paths, keys or request data. */
	public static function safe_error( $error ) {
		if ( ! is_array( $error ) ) {
			return null;
		}
		$message = isset( $error['message'] ) ? $error['message'] : '';
		$category = 'other';
		foreach ( array(
			'ioncube' => 'ioncube',
			'allowed memory size' => 'memory_limit',
			'maximum execution time' => 'execution_time_limit',
			'cannot redeclare' => 'duplicate_declaration',
			'cannot declare class' => 'duplicate_declaration',
			'failed opening required' => 'required_file_missing',
			'call to undefined function' => 'undefined_function',
			'syntax error' => 'syntax_error',
			'uncaught' => 'uncaught_error_or_exception',
		) as $needle => $label ) {
			if ( false !== stripos( $message, $needle ) ) {
				$category = $label;
				break;
			}
		}
		$type = isset( $error['type'] ) ? (int) $error['type'] : 0;
		$result = array(
			'type' => $type,
			'is_fatal' => in_array( $type, array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ), true ),
			'category' => $category,
			'file' => isset( $error['file'] ) ? basename( str_replace( '\\', '/', $error['file'] ) ) : '',
			'line' => isset( $error['line'] ) ? (int) $error['line'] : 0,
		);
		if ( 'ioncube' === $category ) {
			$result['ioncube_details'] = self::ioncube_details( $message );
		}
		return $result;
	}

	/**
	 * Extract only allowlisted reason codes and bounded version numbers.
	 * Never return the original Loader message (it may contain paths/license data).
	 * These are recognized message patterns, not a complete compatibility test.
	 */
	public static function ioncube_details( $message ) {
		$details = array(
			'reason' => 'unrecognized_loader_error',
			'mentions_expected_license_file' => false !== stripos( $message, 'RTL_License_c1595a7238d64f3a.php' ),
		);
		$patterns = array(
			'php_version_incompatible' => '~cannot (?:run|be run) (?:under|with|on) PHP~i',
			'loader_update_required' => '~requires\s+(?:the\s+)?ionCube\s+(?:PHP\s+)?Loader\s+(?:version\s+)?[0-9]|(?:newer|later) version of the ionCube Loader~i',
			'encoding_not_decodable' => '~cannot be decoded by this version of the ionCube Loader~i',
			'encoded_file_corrupt' => '~(?:file|code)[^\r\n]{0,300}\b(?:corrupt|corrupted|damaged)\b~i',
			'encoded_file_expired' => '~(?:file|script)[^\r\n]{0,300}\b(?:has expired|is expired)\b~i',
			'license_expired' => '~licen[sc]e[^\r\n]{0,300}\b(?:has expired|is expired)\b~i',
			'host_restriction' => '~not (?:licensed|authori[sz]ed) (?:to run|for use)|not allowed to run on this~i',
			'loader_not_installed' => '~ionCube Loader for PHP needs to be installed~i',
		);
		foreach ( $patterns as $reason => $pattern ) {
			if ( preg_match( $pattern, $message ) ) {
				$details['reason'] = $reason;
				break;
			}
		}
		$version = '([0-9]{1,2}\.[0-9]{1,2}(?:\.[0-9]{1,3})?)(?![0-9]|\.[0-9])';
		$versions = array(
			'encoded_for_php' => '~Encoder for PHP\s+(?:version\s+)?' . $version . '~i',
			'rejected_runtime_php' => '~cannot (?:run|be run) (?:under|with|on) PHP\s+' . $version . '~i',
			'required_loader_version' => '~requires\s+(?:the\s+)?ionCube\s+(?:PHP\s+)?Loader\s+(?:version\s+)?' . $version . '~i',
		);
		foreach ( $versions as $key => $pattern ) {
			if ( preg_match( $pattern, $message, $matches ) ) {
				$details[ $key ] = $matches[1];
			}
		}
		if ( preg_match( '~encoded as type\s*\[([0-9]{1,3})/([0-9]{1,3})\]~i', $message, $matches ) ) {
			// Preserve the numeric type without guessing how it maps to PHP versions.
			$details['encoding_type'] = $matches[1] . '/' . $matches[2];
		}
		return $details;
	}

	private static function save( $event, $error = null ) {
		if ( null === self::$report ) {
			return;
		}
		self::$report['capture_event'] = $event;
		self::$report['bootstrap_status'] = 'class_not_loaded';
		if ( class_exists( 'Ashna_License_Bootstrap', false ) && is_callable( array( 'Ashna_License_Bootstrap', 'get_status' ) ) ) {
			try {
				$status = Ashna_License_Bootstrap::get_status();
				$known = array( 'pending', 'checking', 'active', 'license_file_missing', 'license_hash_mismatch', 'license_class_conflict', 'license_class_missing', 'license_method_invalid', 'license_inactive', 'license_runtime_error', 'license_loader_missing' );
				self::$report['bootstrap_status'] = in_array( $status, $known, true ) ? $status : 'unknown_status';
			} catch ( Throwable $ignored ) {
				self::$report['bootstrap_status'] = 'status_unavailable';
			}
		}
		$root = str_replace( '\\', '/', WP_PLUGIN_DIR . '/' . dirname( self::$report['plugin'] ) ) . '/';
		$included = array();
		foreach ( get_included_files() as $file ) {
			$file = str_replace( '\\', '/', $file );
			if ( 0 === strpos( $file, $root ) ) {
				$included[] = substr( $file, strlen( $root ) );
			}
		}
		self::$report['included_ashna_files'] = array_slice( $included, -30 );
		self::$report['peak_memory_bytes'] = memory_get_peak_usage( true );
		if ( is_array( $error ) ) {
			self::$report['last_php_error'] = self::safe_error( $error );
			self::capture_loader_message( $error, self::$report['last_php_error'] );
		}
		update_user_meta( self::$user_id, self::META_PREFIX . self::$report['request_kind'], self::$report );
	}

	/** Explicit, one-attempt consent only. Never part of the shareable JSON. */
	private static function capture_loader_message( $error, $safe_error ) {
		if ( ! self::$capture_message || self::$message_saved || ! $safe_error['is_fatal'] || 'ioncube' !== $safe_error['category'] ) {
			return;
		}
		self::$message_saved = true;
		$message = str_replace( '\\', '/', (string) $error['message'] );
		// Best-effort removal of known absolute roots, not a secret scrubber.
		$roots = array( WP_PLUGIN_DIR => '[plugins]', ABSPATH => '[site]' );
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$roots[ WP_CONTENT_DIR ] = '[content]';
		}
		foreach ( $roots as $root => $label ) {
			$root = rtrim( str_replace( '\\', '/', $root ), '/' ) . '/';
			if ( strlen( $root ) > 1 ) {
				$message = str_replace( $root, $label . '/', $message );
			}
		}
		update_user_meta( self::$user_id, self::META_PREFIX . 'loader_message', array(
			'attempt_id' => self::$report['attempt_id'],
			'time_utc' => self::$report['time_utc'],
			'message' => substr( $message, 0, 12000 ),
			'truncated' => strlen( $message ) > 12000,
		) );
	}

	public static function fatal_screen( $message, $error ) {
		self::$reserve = '';
		self::save( 'wordpress_fatal_handler', $error );
		return $message;
	}
	public static function shutdown() {
		$error = error_get_last();
		self::$reserve = '';
		self::save( 'shutdown', $error );
	}

	public static function menu() {
		add_management_page( 'عیب‌یابی فعال‌سازی آشنا', 'عیب‌یابی آشنا', 'manage_options', 'ashna-activation-diagnostics', array( __CLASS__, 'page' ) );
	}

	public static function page() {
		if ( ! self::allowed() ) {
			wp_die( 'دسترسی مجاز نیست.' );
		}
		if ( isset( $_POST['ashna_diag_clear'] ) ) {
			check_admin_referer( 'ashna_diag_clear' );
			foreach ( array( 'activate', 'error_scrape', 'capture_until', 'loader_message' ) as $kind ) {
				delete_user_meta( get_current_user_id(), self::META_PREFIX . $kind );
			}
		} elseif ( isset( $_POST['ashna_diag_arm'] ) ) {
			check_admin_referer( 'ashna_diag_arm' );
			if ( ! isset( $_POST['ashna_diag_consent'] ) || '1' !== $_POST['ashna_diag_consent'] ) {
				wp_die( 'ثبت متن خطا نیازمند تأیید شماست.' );
			}
			delete_user_meta( get_current_user_id(), self::META_PREFIX . 'loader_message' );
			update_user_meta( get_current_user_id(), self::META_PREFIX . 'capture_until', time() + 600 );
		}
		$armed = (int) get_user_meta( get_current_user_id(), self::META_PREFIX . 'capture_until', true ) > time();
		$private_message = get_user_meta( get_current_user_id(), self::META_PREFIX . 'loader_message', true );
		nocache_headers();
		$data = array( 'current_environment' => self::environment() );
		foreach ( array( 'activate', 'error_scrape' ) as $kind ) {
			$data[ $kind ] = get_user_meta( get_current_user_id(), self::META_PREFIX . $kind, true );
		}
		?>
		<div class="wrap" dir="rtl">
			<h1>عیب‌یابی موقت فعال‌سازی آشنا</h1>
			<p>این ابزار لایسنس و فایل‌های آشنا را تغییر نمی‌دهد و خودش آشنا را فعال نمی‌کند. اطلاعاتی به سرویس خارجی ارسال نمی‌شود.</p>
			<p>از صفحهٔ افزونه‌ها، فقط یک بار روی «فعال‌سازی» خود آشنا کلیک کنید (نه فعال‌سازی گروهی). سپس به این صفحه برگردید و گزارش را کپی کنید.</p>
			<p>گزارش ساختاریافته فقط برای مدیرِ انجام‌دهندهٔ آزمایش نمایش داده می‌شود و متن اصلی خطا را ندارد. ثبت متن خطای Loader فقط با تأیید جداگانهٔ شما در بخش زیر انجام می‌شود.</p>
			<p><strong>محدودیت:</strong> توقف بدون اجرای shutdown، خطا پیش از شروع ثبت، و بعضی خطاهای Loader ممکن است جزئیات کامل نداشته باشند. «مرحلهٔ آخر» به‌تنهایی علت قطعی نیست. این ابزار روی درخواست‌های عادی فروشگاه نظارت نمی‌کند.</p>
			<textarea readonly dir="ltr" style="width:100%;min-height:420px;font-family:monospace" aria-label="Diagnostic report"><?php echo esc_textarea( wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></textarea>
			<h2>ثبت یک‌بارهٔ متن خطای ionCube</h2>
			<p>اگر دلیل خطا unrecognized_loader_error است، با تأیید زیر می‌توانید متن خطای Fatal مربوط به ionCube را در تلاش بعدی ثبت کنید. این مجوز ۱۰ دقیقه اعتبار دارد و با یک تلاش فعال‌سازی مصرف می‌شود.</p>
			<p><strong>حریم خصوصی:</strong> مسیر ریشهٔ شناخته‌شدهٔ سایت تا حد امکان جایگزین می‌شود، اما متن ممکن است همچنان حاوی مسیرها یا اطلاعات حساس باشد. پیام در دیتابیس سایت، جدا از گزارش بالا و فقط برای حساب شما در این صفحه نگهداری می‌شود؛ مدیر دیتابیس هم دسترسی دارد. قبل از ارسال، کلید، توکن، ایمیل و مسیر خصوصی را بپوشانید. سپس «پاک‌کردن گزارش‌های من» را بزنید یا ابزار را حذف کنید.</p>
			<?php if ( $armed ) : ?>
				<p><strong>ثبت یک‌باره آماده است؛ اکنون از فهرست افزونه‌ها یک بار آشنا را فعال کنید.</strong></p>
			<?php endif; ?>
			<form method="post">
				<?php wp_nonce_field( 'ashna_diag_arm' ); ?>
				<label><input type="checkbox" name="ashna_diag_consent" value="1" required> با ثبت خصوصی متن خطای Loader در تلاش بعدی موافقم.</label>
				<p><button class="button" name="ashna_diag_arm" value="1">آماده‌سازی ثبت یک‌باره</button></p>
			</form>
			<?php if ( is_array( $private_message ) && isset( $private_message['message'] ) ) : ?>
				<h3>متن خطای Loader — قبل از ارسال، اطلاعات حساس را بپوشانید</h3>
				<p dir="ltr"><?php echo esc_html( $private_message['time_utc'] . ' / attempt: ' . $private_message['attempt_id'] ); ?></p>
				<textarea readonly dir="ltr" style="width:100%;min-height:160px;font-family:monospace" aria-label="Private Loader error"><?php echo esc_textarea( $private_message['message'] ); ?></textarea>
				<?php if ( ! empty( $private_message['truncated'] ) ) : ?><p>پیام طولانی بود؛ فقط بخش ابتدایی ذخیره شده است.</p><?php endif; ?>
			<?php endif; ?>
			<form method="post">
				<?php wp_nonce_field( 'ashna_diag_clear' ); ?>
				<p><button class="button" name="ashna_diag_clear" value="1">پاک‌کردن گزارش‌های من</button></p>
			</form>
			<p>پس از بررسی، این افزونهٔ عیب‌یابی را غیرفعال و حذف کنید؛ حذف آن گزارش‌های این ابزار را پاک می‌کند، نه تنظیمات آشنا.</p>
		</div>
		<?php
	}
}

Ashna_Activation_Diagnostics::register();
