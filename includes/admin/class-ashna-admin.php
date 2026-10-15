<?php
/**
 * Admin Controller, AJAX Settings Saver, Live Tester, Firewall Manager & CSV Exporter (v2.0)
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Admin {

	/**
	 * Singleton instance
	 *
	 * @var Ashna_Admin|null
	 */
	private static $instance = null;

	/**
	 * Output-buffer level before Ashna's admin-AJAX buffer was opened (null outside those requests).
	 *
	 * @var int|null
	 */
	private static $ajax_buffer_level = null;

	/**
	 * Track if settings were already saved during the current request
	 *
	 * @var bool
	 */
	private $settings_saved_in_request = false;

	/**
	 * Get instance
	 *
	 * @return Ashna_Admin
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		if ( wp_doing_ajax() && isset( $_REQUEST['action'] ) && strpos( sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ), 'ashna_admin_' ) === 0 ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// Catch stray notices other plugins print during our AJAX requests (removed before replying).
			self::$ajax_buffer_level = ob_get_level();
			ob_start();
		}

		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// AJAX Endpoints
		add_action( 'wp_ajax_ashna_admin_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_ashna_admin_test_gateway', array( $this, 'ajax_test_gateway' ) );
		add_action( 'wp_ajax_ashna_admin_unlock_target', array( $this, 'ajax_unlock_target' ) );
		add_action( 'wp_ajax_ashna_admin_import_settings', array( $this, 'ajax_import_settings' ) );
		add_action( 'wp_ajax_ashna_admin_reset_settings', array( $this, 'ajax_reset_settings' ) );
		add_action( 'wp_ajax_ashna_admin_reset_gateway_health', array( $this, 'ajax_reset_gateway_health' ) );
		add_action( 'wp_ajax_ashna_admin_end_attack_mode', array( $this, 'ajax_end_attack_mode' ) );
		add_action( 'wp_ajax_ashna_admin_trust_proxy', array( $this, 'ajax_trust_proxy' ) );
		add_action( 'wp_ajax_ashna_admin_fetch_arvan_ranges', array( $this, 'ajax_fetch_arvan_ranges' ) );
		add_action( 'admin_notices', array( $this, 'proxy_hint_notice' ) );
	}

	/**
	 * Warn when proxy headers arrive from an untrusted peer: until the CDN is trusted, every
	 * visitor behind it shares one IP and reaches the per-IP limits sooner.
	 */
	public function proxy_hint_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && ! in_array( $screen->id, array( 'dashboard', 'plugins', 'toplevel_page_ashna-otp' ), true ) ) {
			return;
		}
		$hint = Ashna_IP::untrusted_proxy_hint();
		if ( ! $hint ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p><strong>آشنا:</strong> %s <a href="%s">%s</a></p></div>',
			esc_html( sprintf( 'درخواست‌های ورود با هدر CDN/پراکسی از آی‌پی %s می‌رسند که در فهرست پراکسی‌های مورد اعتماد نیست؛ تا اضافه نشود، کاربران پشت آن یک آی‌پی حساب می‌شوند.', $hint['remote'] ) ),
			esc_url( admin_url( 'admin.php?page=ashna-otp#security_firewall' ) ),
			'بررسی در «امنیت و ضد اسپم»'
		);
	}

	/**
	 * AJAX Handler: end attack mode now.
	 */
	public function ajax_end_attack_mode() {
		// Shape first: core's wp_verify_nonce() casts its argument, and an array casts with a PHP
		// warning on PHP 8. Short-circuit evaluation keeps that from ever reaching core.
		if ( ! isset( $_REQUEST['nonce'] ) || ! is_scalar( $_REQUEST['nonce'] ) || false === check_ajax_referer( 'ashna_admin_nonce', 'nonce', false ) ) {
			// Same refusal WordPress itself sends, and fail closed: wp_die() ends the request, so
			// the return below is only reached if something replaced the wp_die handler.
			wp_die( -1, 403 );
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->clean_output_buffers();
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}
		Ashna_Rate_Limiter::end_attack_mode();
		$this->clean_output_buffers();
		wp_send_json_success( array( 'message' => 'حالت حمله پایان یافت؛ فرم به حالت عادی برگشت.' ) );
	}

	/**
	 * AJAX Handler: add a proxy/CDN address to the trusted list.
	 */
	public function ajax_trust_proxy() {
		// Shape first: core's wp_verify_nonce() casts its argument, and an array casts with a PHP
		// warning on PHP 8. Short-circuit evaluation keeps that from ever reaching core.
		if ( ! isset( $_REQUEST['nonce'] ) || ! is_scalar( $_REQUEST['nonce'] ) || false === check_ajax_referer( 'ashna_admin_nonce', 'nonce', false ) ) {
			// Same refusal WordPress itself sends, and fail closed: wp_die() ends the request, so
			// the return below is only reached if something replaced the wp_die handler.
			wp_die( -1, 403 );
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->clean_output_buffers();
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}
		$ip = isset( $_POST['ip'] ) ? Ashna_IP::valid( sanitize_text_field( wp_unslash( $_POST['ip'] ) ) ) : '';
		if ( '' === $ip ) {
			$this->clean_output_buffers();
			wp_send_json_error( array( 'message' => 'آی‌پی نامعتبر است.' ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}
		$settings = Ashna_Helper::get_settings();
		$list     = array_filter( array_map( 'trim', preg_split( '/[\s,]+/', (string) $settings['trusted_proxies'] ) ) );
		$list[]   = $ip;
		$settings['trusted_proxies'] = implode( "\n", array_unique( $list ) );
		Ashna_Helper::save_settings( $settings );
		delete_option( 'ashna_otp_proxy_hint' );

		$this->clean_output_buffers();
		wp_send_json_success(
			array(
				'message' => 'آی‌پی به فهرست پراکسی‌های مورد اعتماد اضافه شد.',
				'list'    => $settings['trusted_proxies'],
			)
		);
	}

	/**
	 * AJAX Handler: download ArvanCloud's published edge ranges (admin action only).
	 */
	public function ajax_fetch_arvan_ranges() {
		// Shape first: core's wp_verify_nonce() casts its argument, and an array casts with a PHP
		// warning on PHP 8. Short-circuit evaluation keeps that from ever reaching core.
		if ( ! isset( $_REQUEST['nonce'] ) || ! is_scalar( $_REQUEST['nonce'] ) || false === check_ajax_referer( 'ashna_admin_nonce', 'nonce', false ) ) {
			// Same refusal WordPress itself sends, and fail closed: wp_die() ends the request, so
			// the return below is only reached if something replaced the wp_die handler.
			wp_die( -1, 403 );
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->clean_output_buffers();
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}
		$response = wp_remote_get( 'https://www.arvancloud.ir/fa/ips.txt', array( 'timeout' => 10 ) );
		$ranges   = array();
		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			foreach ( preg_split( '/[\s,]+/', (string) wp_remote_retrieve_body( $response ) ) as $range ) {
				$range = trim( $range );
				if ( '' !== $range && Ashna_IP::valid_range( $range ) ) {
					$ranges[] = $range;
				}
			}
		}
		$this->clean_output_buffers();
		if ( empty( $ranges ) ) {
			wp_send_json_error( array( 'message' => 'دریافت فهرست ابرآروان ممکن نشد؛ رنج‌ها را از arvancloud.ir/fa/ips.txt دستی اضافه کنید.' ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}
		wp_send_json_success(
			array(
				'message' => sprintf( '%d رنج ابرآروان دریافت شد؛ برای اعمال، تنظیمات را ذخیره کنید.', count( $ranges ) ),
				'ranges'  => array_values( array_unique( $ranges ) ),
			)
		);
	}

	/**
	 * Register WordPress Admin Menus
	 */
	public function register_menus() {
		add_menu_page(
			'آشنا | ورود و ثبت‌نام یکبارمصرف',
			'ورود پیامکی (آشنا)',
			'manage_options',
			'ashna-otp',
			array( $this, 'render_settings_page' ),
			$this->get_menu_icon(),
			58
		);

		add_submenu_page(
			'ashna-otp',
			'داشبورد و تنظیمات',
			'داشبورد و تنظیمات',
			'manage_options',
			'ashna-otp',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'ashna-otp',
			'لاگ کدهای ارسالی',
			'لاگ کدهای ارسالی',
			'manage_options',
			'ashna-otp-logs',
			array( $this, 'render_logs_page' )
		);
	}

	/**
	 * Monochrome Ashna shield for the admin menu.
	 *
	 * WordPress repaints base64 SVG menu icons with the user's admin color
	 * scheme, so the icon matches every scheme and its hover/current states.
	 *
	 * @return string Data URI accepted by add_menu_page().
	 */
	private function get_menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="280 280 464 464">'
			. '<path fill="#a7aaad" d="M491 310 C504 302 519 302 534 310 C578 332 627 350 674 363 C689 367 697 378 697 394 V436 C699 533 679 602 622 661 Q620 664 617 662 L603 654 Q598 652 598 645 V424 C598 412 591 404 579 404 H463 C450 404 443 412 443 424 V640 Q443 645 439 647 L408 663 Q406 664 404 662 C351 608 327 541 327 440 V394 C327 378 334 368 350 363 C403 348 451 330 491 310 Z"/>'
			. '<path fill="#a7aaad" d="M575 423 Q581 421 581 428 V649 Q581 657 588 661 L603 672 Q608 675 603 679 C575 699 540 720 513 720 C487 720 453 701 423 679 Q419 676 423 674 L461 654 Q468 651 468 643 V469 Q468 457 480 453 L575 423 Z"/>'
			. '</svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Documented add_menu_page() icon format.
	}

	/**
	 * Enqueue Admin CSS, Fonts, Media Uploader & JS on Ashna pages
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( strpos( (string) $hook, 'ashna-otp' ) === false && strpos( $current_page, 'ashna-otp' ) === false ) {
			return;
		}

		if ( function_exists( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
		}

		wp_enqueue_style(
			'ashna-fonts',
			ASHNA_OTP_URL . 'assets/css/fonts.css',
			array(),
			ASHNA_OTP_VERSION
		);

		$css_file = ASHNA_OTP_PATH . 'assets/css/admin.css';
		$js_file  = ASHNA_OTP_PATH . 'assets/js/admin.js';

		// Inject CSS & JS directly from disk via WordPress inline APIs to bypass aggressive browser/CDN/LiteSpeed static file caches
		wp_register_style( 'ashna-otp-admin-core', false, array( 'ashna-fonts' ), ASHNA_OTP_VERSION );
		wp_enqueue_style( 'ashna-otp-admin-core' );
		if ( file_exists( $css_file ) ) {
			wp_add_inline_style( 'ashna-otp-admin-core', (string) file_get_contents( $css_file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}

		wp_register_script( 'ashna-otp-admin-core', false, array( 'jquery' ), ASHNA_OTP_VERSION, true );
		wp_enqueue_script( 'ashna-otp-admin-core' );
		wp_localize_script(
			'ashna-otp-admin-core',
			'ashnaAdminParams',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ashna_admin_nonce' ),
				'settings' => Ashna_Helper::get_settings(),
			)
		);
		if ( file_exists( $js_file ) ) {
			wp_add_inline_script( 'ashna-otp-admin-core', (string) file_get_contents( $js_file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
	}

	/**
	 * Sanitize settings array
	 *
	 * @param array $raw Raw input settings array.
	 * @return array
	 */
	private function sanitize_settings_payload( $raw ) {
		$defaults = Ashna_Helper::default_settings();
		$clean    = array();

		$checkbox_keys = array(
			'auto_register',
			'allow_password_login',
			'enable_passkey',
			'passkey_prompt_after_otp',
			'passkey_wc_myaccount',
			'show_terms_checkbox',
			'send_welcome_message',
			'show_debug_code_in_toast',
			'wc_replace_myaccount',
			'wc_checkout_otp_box',
			'wp_login_integration',
			'enable_global_modal',
			'modal_mobile_sheet',
			'canvas_show_back_link',
			'glassmorphism',
			'otp_auto_submit',
			'error_shake_effect',
			'trust_proxy_headers',
			'delete_data_on_uninstall',
			'gateway_adaptive',
			'gateway_probe',
			'session_remember',
			'respect_login_gates',
			'respect_plugin_redirects',
			'login_url_to_ashna',
			'replace_member_forms',
			'dokan_vendor_signup',
		);

		$int_keys = array(
			'otp_length',
			'otp_expiry',
			'resend_cooldown',
			'border_radius',
			'form_max_width',
			'standalone_page_id',
			'card_bg_opacity',
			'backdrop_blur',
			'card_padding',
			'title_font_size',
			'subtitle_font_size',
			'btn_font_size',
			'input_height',
			'digit_box_size',
			'digit_box_gap',
			'max_requests_per_hour',
			'max_ip_requests_per_hour',
			'max_verify_attempts',
			'lockout_duration',
			'log_retention_days',
			'gateway_timeout',
			'gateway_cooldown',
			'msgway_sms_line',
			'msgway_messenger_provider',
			'session_days',
			'max_requests_per_day',
			'max_ip_requests_per_minute',
			'max_subnet_requests_per_minute',
			'attack_mode_threshold',
			'attack_mode_minutes',
			'daily_send_cap',
		);

		// Integer settings where 0 means "off".
		$zero_ok_keys = array( 'standalone_page_id', 'max_subnet_requests_per_minute', 'attack_mode_threshold', 'daily_send_cap' );

		$textarea_keys = array(
			'bale_message_template',
			'email_body_text',
			'welcome_message_text',
			'split_features',
			'custom_css',
			'blocked_phones',
			'blocked_ips',
			'whitelisted_identifiers',
			'trusted_proxies',
		);

		$url_keys = array(
			'redirect_url',
			'custom_redirect_url',
			'admin_redirect_url',
			'terms_url',
			'logo_url',
			'split_image_url',
			'canvas_bg_image',
		);

		// Destinations a visitor is sent to after signing in must stay on this site: an imported
		// file (or a typo) cannot turn the login form into an open redirect.
		$redirect_url_keys = array( 'redirect_url', 'custom_redirect_url', 'admin_redirect_url' );

		foreach ( $defaults as $key => $default_val ) {
			if ( 'role_redirects' === $key ) {
				// role => URL; unknown roles and empty URLs are dropped.
				$clean[ $key ] = array();
				$roles         = function_exists( 'wp_roles' ) ? wp_roles()->get_names() : array();
				if ( isset( $raw[ $key ] ) && is_array( $raw[ $key ] ) ) {
					foreach ( $raw[ $key ] as $role => $url ) {
						$role = sanitize_key( (string) $role );
						$url  = is_scalar( $url ) ? esc_url_raw( trim( (string) $url ) ) : '';
						$url  = '' !== $url ? wp_validate_redirect( $url, '' ) : '';
						if ( '' !== $url && isset( $roles[ $role ] ) ) {
							$clean[ $key ][ $role ] = $url;
						}
					}
				}
				continue;
			}
			if ( isset( $raw[ $key ] ) && ! is_scalar( $raw[ $key ] ) ) {
				unset( $raw[ $key ] ); // A list/object where one value belongs (tampered request): keep the default.
			}
			if ( in_array( $key, $checkbox_keys, true ) ) {
				$clean[ $key ] = ! empty( $raw[ $key ] ) ? 1 : 0;
			} elseif ( in_array( $key, $int_keys, true ) ) {
				$val = isset( $raw[ $key ] ) ? absint( Ashna_Helper::convert_digits( $raw[ $key ] ) ) : $default_val;
				if ( 'border_radius' === $key || 'backdrop_blur' === $key ) {
					$clean[ $key ] = min( 32, $val );
				} elseif ( 'card_bg_opacity' === $key ) {
					$clean[ $key ] = max( 20, min( 100, $val > 0 ? $val : 85 ) );
				} elseif ( 'card_padding' === $key ) {
					$clean[ $key ] = max( 16, min( 56, $val > 0 ? $val : 32 ) );
				} elseif ( in_array( $key, $zero_ok_keys, true ) ) {
					$clean[ $key ] = $val;
				} else {
					$clean[ $key ] = $val > 0 ? $val : $default_val;
				}
			} elseif ( in_array( $key, $textarea_keys, true ) ) {
				$clean[ $key ] = isset( $raw[ $key ] ) ? sanitize_textarea_field( $raw[ $key ] ) : $default_val;
			} elseif ( in_array( $key, $url_keys, true ) ) {
				$clean[ $key ] = isset( $raw[ $key ] ) ? esc_url_raw( trim( $raw[ $key ] ) ) : '';
			} else {
				$clean[ $key ] = isset( $raw[ $key ] ) ? sanitize_text_field( $raw[ $key ] ) : $default_val;
			}
		}

		foreach ( $redirect_url_keys as $redirect_key ) {
			if ( '' !== (string) $clean[ $redirect_key ] ) {
				$clean[ $redirect_key ] = wp_validate_redirect( (string) $clean[ $redirect_key ], '' );
			}
		}

		if ( isset( $raw['custom_redirect_url'] ) ) {
			$clean['redirect_url'] = $clean['custom_redirect_url'];
		} elseif ( ! empty( $clean['redirect_url'] ) && empty( $clean['custom_redirect_url'] ) ) {
			$clean['custom_redirect_url'] = $clean['redirect_url'];
		}

		$clean['otp_length']       = max( 4, min( 8, $clean['otp_length'] ) );
		$clean['form_max_width']   = max( 320, min( 640, $clean['form_max_width'] ) );
		$clean['gateway_timeout']  = max( 2, min( 15, $clean['gateway_timeout'] ) );
		$clean['gateway_cooldown'] = max( 1, min( 60, $clean['gateway_cooldown'] ) );
		$clean['session_days']     = max( 1, min( 365, $clean['session_days'] ) );
		$clean['attack_mode_minutes'] = max( 5, min( 1440, $clean['attack_mode_minutes'] ) );

		// Trusted proxies: keep valid IPs/CIDRs only, one per line.
		$proxies = array();
		foreach ( preg_split( '/[\s,]+/', (string) $clean['trusted_proxies'] ) as $range ) {
			$range = trim( $range );
			if ( '' !== $range && Ashna_IP::valid_range( $range ) ) {
				$proxies[] = $range;
			}
		}
		$clean['trusted_proxies'] = implode( "\n", array_unique( $proxies ) );
		if ( ! in_array( $clean['default_user_role'], array( 'customer', 'subscriber' ), true ) ) {
			$clean['default_user_role'] = Ashna_Auth::safe_registration_role( $clean['default_user_role'] );
		}

		return $clean;
	}

	/**
	 * Clean all active PHP output buffers before emitting JSON
	 */
	private function clean_output_buffers() {
		if ( null !== self::$ajax_buffer_level ) {
			// Drop only what was printed after our own buffer started; outer buffers (compression,
			// caching, the server's own) belong to someone else and stay untouched.
			while ( ob_get_level() > self::$ajax_buffer_level + 1 ) {
				ob_end_clean();
			}
			if ( ob_get_level() === self::$ajax_buffer_level + 1 && ob_get_length() ) {
				ob_clean();
			}
			return;
		}
		// No buffer of ours: drop stray output from this request only if there is any.
		if ( ob_get_level() > 0 && ob_get_length() ) {
			ob_clean();
		}
	}

	/**
	 * Verify admin settings request nonce (accepts either AJAX nonce or form nonce)
	 *
	 * @return bool
	 */
	private function verify_settings_nonce() {
		if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ashna_admin_nonce' ) ) {
			return true;
		}
		if ( isset( $_POST['ashna_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ashna_settings_nonce'] ) ), 'ashna_save_settings_action' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Handle form submissions (Fallback POST Save, Clear Logs, Export CSV)
	 */
	public function handle_actions() {
		if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// 1. Classic Form POST Save Settings
		if ( isset( $_POST['ashna_save_settings'] ) && $this->verify_settings_nonce() ) {
			$raw   = isset( $_POST['ashna'] ) && is_array( $_POST['ashna'] ) ? wp_unslash( $_POST['ashna'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$clean = $this->sanitize_settings_payload( $raw );

			Ashna_Helper::save_settings( $clean );
			Ashna_Failover::reset_circuits(); // Retry sidelined gateways right after a settings change.
			$this->settings_saved_in_request = true;

			if ( ! headers_sent() ) {
				wp_safe_redirect( add_query_arg( 'settings-updated', 'true', admin_url( 'admin.php?page=ashna-otp' ) ) );
				exit;
			}
			$_GET['settings-updated'] = 'true';
		}

		// 2. Clear Logs
		if ( isset( $_POST['ashna_clear_logs'] ) && check_admin_referer( 'ashna_clear_logs_action', 'ashna_logs_nonce' ) ) {
			$clear_type = isset( $_POST['clear_type'] ) ? sanitize_text_field( wp_unslash( $_POST['clear_type'] ) ) : 'old';
			Ashna_Logger::clear_logs( 'all' === $clear_type );

			wp_safe_redirect( add_query_arg( 'logs-cleared', 'true', admin_url( 'admin.php?page=ashna-otp-logs' ) ) );
			exit;
		}

		// 3. Export CSV Logs
		if ( isset( $_GET['ashna_export_logs_csv'] ) && check_admin_referer( 'ashna_export_csv_action', 'nonce' ) ) {
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=ashna-otp-logs-' . gmdate( 'Y-m-d' ) . '.csv' );

			$out = fopen( 'php://output', 'w' );
			self::write_logs_csv( $out );
			fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output stream, not a file.
			exit;
		}
	}

	/**
	 * Write the latest 2000 log rows as CSV (UTF-8 BOM for Excel).
	 *
	 * @param resource $out Writable stream.
	 */
	public static function write_logs_csv( $out ) {
		$logs_data = Ashna_Logger::get_logs(
			array(
				'per_page' => 2000,
				'paged'    => 1,
			)
		);
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		// Plain RFC 4180 quoting (empty $escape): PHP 8.4 deprecates relying on the old "\\" default.
		fputcsv( $out, array( 'ID', 'Recipient', 'Channel', 'Gateway', 'OTP Code', 'Status', 'Attempts', 'IP Address', 'Response', 'Created At', 'Verified At' ), ',', '"', '' );
		foreach ( $logs_data['items'] as $row ) {
			fputcsv(
				$out,
				array_map(
					array( __CLASS__, 'csv_cell' ),
					array( $row->id, $row->recipient, $row->channel, $row->gateway, $row->otp_code, $row->status, $row->attempts, $row->ip_address, $row->response_message, $row->created_at, $row->verified_at )
				),
				',',
				'"',
				''
			);
		}
	}

	/**
	 * Neutralize spreadsheet formulas: a cell starting with = + - @ (or tab/CR) is prefixed
	 * with an apostrophe, so e.g. an e-mail like "=1+1@x.co" is shown as text in Excel.
	 *
	 * @param mixed $value Cell value.
	 * @return string
	 */
	public static function csv_cell( $value ) {
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}
		return $value;
	}

	/**
	 * AJAX Handler: Live Save Settings without page reload
	 */
	public function ajax_save_settings() {
		if ( ! current_user_can( 'manage_options' ) || ! $this->verify_settings_nonce() ) {
			$this->clean_output_buffers();
			wp_send_json_error( array( 'message' => 'نشست امنیتی منقضی شده است؛ لطفاً صفحه را رفرش کنید.' ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}

		$raw   = isset( $_POST['ashna'] ) && is_array( $_POST['ashna'] ) ? wp_unslash( $_POST['ashna'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$clean = $this->sanitize_settings_payload( $raw );

		Ashna_Helper::save_settings( $clean );
		Ashna_Failover::reset_circuits(); // Retry sidelined gateways right after a settings change.

		$this->clean_output_buffers();

		wp_send_json_success(
			array(
				'message'  => 'تنظیمات با موفقیت ذخیره شد!',
				'settings' => $clean,
			)
		);
	}

	/**
	 * AJAX Handler: Test Gateway from Admin Panel
	 */
	public function ajax_test_gateway() {
		// Shape first: core's wp_verify_nonce() casts its argument, and an array casts with a PHP
		// warning on PHP 8. Short-circuit evaluation keeps that from ever reaching core.
		if ( ! isset( $_REQUEST['nonce'] ) || ! is_scalar( $_REQUEST['nonce'] ) || false === check_ajax_referer( 'ashna_admin_nonce', 'nonce', false ) ) {
			// Same refusal WordPress itself sends, and fail closed: wp_die() ends the request, so
			// the return below is only reached if something replaced the wp_die handler.
			wp_die( -1, 403 );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->clean_output_buffers();
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}

		$recipient  = isset( $_POST['recipient'] ) ? sanitize_text_field( wp_unslash( $_POST['recipient'] ) ) : '';
		$channel    = isset( $_POST['channel'] ) ? sanitize_text_field( wp_unslash( $_POST['channel'] ) ) : 'sms';
		$gateway_id = isset( $_POST['gateway_id'] ) ? sanitize_text_field( wp_unslash( $_POST['gateway_id'] ) ) : '';

		if ( empty( $recipient ) ) {
			$this->clean_output_buffers();
			wp_send_json_error( array( 'message' => 'لطفاً شماره موبایل یا ایمیل تست را وارد کنید.' ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}

		$parsed = Ashna_Helper::parse_identifier( $recipient );
		if ( 'invalid' === $parsed['type'] ) {
			$this->clean_output_buffers();
			wp_send_json_error( array( 'message' => 'شماره موبایل یا آدرس ایمیل وارد شده معتبر نیست.' ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}

		$result = Ashna_Gateway_Manager::dispatch_otp( $parsed['normalized'], $parsed['type'], $channel, $gateway_id );
		$this->clean_output_buffers();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => 'خطا در ارسال تست: ' . $result->get_error_message(),
				)
			);
			return; // wp_send_json() ends the request: never continue past a response.
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					'ارسال تست موفق! کد تولیدشده: %s | درگاه: %s | زمان: %s ثانیه | پاسخ: %s',
					$result['code'],
					$result['gateway'],
					number_format( $result['elapsed_ms'] / 1000, 1 ),
					$result['response']
				),
			)
		);
	}

	/**
	 * AJAX Handler: Clear gateway health data and bring sidelined gateways back
	 */
	public function ajax_reset_gateway_health() {
		// Shape first: core's wp_verify_nonce() casts its argument, and an array casts with a PHP
		// warning on PHP 8. Short-circuit evaluation keeps that from ever reaching core.
		if ( ! isset( $_REQUEST['nonce'] ) || ! is_scalar( $_REQUEST['nonce'] ) || false === check_ajax_referer( 'ashna_admin_nonce', 'nonce', false ) ) {
			// Same refusal WordPress itself sends, and fail closed: wp_die() ends the request, so
			// the return below is only reached if something replaced the wp_die handler.
			wp_die( -1, 403 );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->clean_output_buffers();
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}

		Ashna_Failover::reset_all();

		$this->clean_output_buffers();
		wp_send_json_success( array( 'message' => 'وضعیت درگاه‌ها بازنشانی شد؛ همه درگاه‌ها دوباره در مسیر ارسال قرار گرفتند.' ) );
	}

	/**
	 * AJAX Handler: Unlock a locked-out phone or IP immediately
	 */
	public function ajax_unlock_target() {
		// Shape first: core's wp_verify_nonce() casts its argument, and an array casts with a PHP
		// warning on PHP 8. Short-circuit evaluation keeps that from ever reaching core.
		if ( ! isset( $_REQUEST['nonce'] ) || ! is_scalar( $_REQUEST['nonce'] ) || false === check_ajax_referer( 'ashna_admin_nonce', 'nonce', false ) ) {
			// Same refusal WordPress itself sends, and fail closed: wp_die() ends the request, so
			// the return below is only reached if something replaced the wp_die handler.
			wp_die( -1, 403 );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->clean_output_buffers();
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}

		$target = isset( $_POST['target'] ) ? sanitize_text_field( wp_unslash( $_POST['target'] ) ) : '';
		if ( empty( $target ) ) {
			$this->clean_output_buffers();
			wp_send_json_error( array( 'message' => 'شناسه نامعتبر است.' ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}

		Ashna_Security::unlock_target( $target );
		$this->clean_output_buffers();
		wp_send_json_success( array( 'message' => 'رفع مسدودی با موفقیت انجام شد.' ) );
	}

	/**
	 * AJAX Handler: Import JSON Settings
	 */
	public function ajax_import_settings() {
		// Shape first: core's wp_verify_nonce() casts its argument, and an array casts with a PHP
		// warning on PHP 8. Short-circuit evaluation keeps that from ever reaching core.
		if ( ! isset( $_REQUEST['nonce'] ) || ! is_scalar( $_REQUEST['nonce'] ) || false === check_ajax_referer( 'ashna_admin_nonce', 'nonce', false ) ) {
			// Same refusal WordPress itself sends, and fail closed: wp_die() ends the request, so
			// the return below is only reached if something replaced the wp_die handler.
			wp_die( -1, 403 );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->clean_output_buffers();
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}

		$json_raw = isset( $_POST['json_data'] ) ? Ashna_Helper::as_string( wp_unslash( $_POST['json_data'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$decoded  = json_decode( $json_raw, true );

		if ( ! is_array( $decoded ) ) {
			$this->clean_output_buffers();
			wp_send_json_error( array( 'message' => 'فرمت فایل یا متن JSON معتبر نیست.' ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}

		// A file from an older version (or a partial one) lacks newer keys. On/off settings that
		// are missing must keep their defaults instead of being read as "unchecked" (= off).
		$clean = $this->sanitize_settings_payload( array_merge( Ashna_Helper::default_settings(), $decoded ) );
		Ashna_Helper::save_settings( $clean );
		Ashna_Failover::reset_circuits();

		$this->clean_output_buffers();
		wp_send_json_success( array( 'message' => 'تنظیمات با موفقیت درون‌ریزی شد! در حال بارگذاری مجدد...' ) );
	}

	/**
	 * AJAX Handler: Reset Settings to Factory Defaults
	 */
	public function ajax_reset_settings() {
		// Shape first: core's wp_verify_nonce() casts its argument, and an array casts with a PHP
		// warning on PHP 8. Short-circuit evaluation keeps that from ever reaching core.
		if ( ! isset( $_REQUEST['nonce'] ) || ! is_scalar( $_REQUEST['nonce'] ) || false === check_ajax_referer( 'ashna_admin_nonce', 'nonce', false ) ) {
			// Same refusal WordPress itself sends, and fail closed: wp_die() ends the request, so
			// the return below is only reached if something replaced the wp_die handler.
			wp_die( -1, 403 );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->clean_output_buffers();
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}

		Ashna_Helper::save_settings( Ashna_Helper::default_settings() );
		Ashna_Failover::reset_circuits(); // Retry sidelined gateways right after a settings change.

		$this->clean_output_buffers();
		wp_send_json_success( array( 'message' => 'تمام تنظیمات به حالت پیش‌فرض بازنشانی شد!' ) );
	}

	/**
	 * Render Settings Page
	 */
	public function render_settings_page() {
		if ( ! $this->settings_saved_in_request && isset( $_POST['ashna_save_settings'] ) && current_user_can( 'manage_options' ) && $this->verify_settings_nonce() ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$raw   = isset( $_POST['ashna'] ) && is_array( $_POST['ashna'] ) ? wp_unslash( $_POST['ashna'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$clean = $this->sanitize_settings_payload( $raw );

			Ashna_Helper::save_settings( $clean );
			Ashna_Failover::reset_circuits(); // Retry sidelined gateways right after a settings change.
			$this->settings_saved_in_request = true;
			$_GET['settings-updated']        = 'true';
		}

		$settings        = Ashna_Helper::get_settings();
		$sms_gateways    = Ashna_Gateway_Manager::get_sms_gateways();
		$stats           = Ashna_Logger::get_stats();
		$chart_data      = Ashna_Logger::get_daily_chart_data( 7 );
		$recent_logs     = Ashna_Logger::get_logs( array( 'per_page' => 6, 'paged' => 1 ) )['items'];
		$active_lockouts = Ashna_Security::get_active_lockouts();

		include ASHNA_OTP_PATH . 'includes/admin/views/settings-page.php';
	}

	/**
	 * Render Logs Page
	 */
	public function render_logs_page() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$paged   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$status  = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$channel = isset( $_GET['channel'] ) ? sanitize_text_field( wp_unslash( $_GET['channel'] ) ) : '';
		// phpcs:enable

		$per_page  = 20;
		$logs_data = Ashna_Logger::get_logs(
			array(
				'per_page' => $per_page,
				'paged'    => $paged,
				'search'   => $search,
				'status'   => $status,
				'channel'  => $channel,
			)
		);
		$stats     = Ashna_Logger::get_stats();

		include ASHNA_OTP_PATH . 'includes/admin/views/logs-page.php';
	}
}
