<?php
/**
 * Admin Controller, AJAX Settings Saver, Live Tester, Firewall Manager & CSV Exporter (v2.0)
 *
 * @package Ashna_OTP
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
			@ob_start();
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
	}

	/**
	 * Register WordPress Admin Menus
	 */
	public function register_menus() {
		add_menu_page(
			'آشنا — ورود و ثبت‌نام',
			'آشنا',
			'manage_options',
			'ashna-otp',
			array( $this, 'render_settings_page' ),
			ASHNA_OTP_URL . 'assets/images/ashna-menu-icon.png',
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
			'ashna-vazirmatn-font',
			'https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css',
			array(),
			'33.003'
		);

		wp_enqueue_style(
			'ashna-fonts',
			ASHNA_OTP_URL . 'assets/css/fonts.css',
			array( 'ashna-vazirmatn-font' ),
			ASHNA_OTP_VERSION
		);

		$css_file = ASHNA_OTP_PATH . 'assets/css/admin.css';
		$js_file  = ASHNA_OTP_PATH . 'assets/js/admin.js';

		// Inject CSS & JS directly from disk via WordPress inline APIs to bypass aggressive browser/CDN/LiteSpeed static file caches
		wp_register_style( 'ashna-otp-admin-core', false, array( 'ashna-vazirmatn-font', 'ashna-fonts' ), ASHNA_OTP_VERSION );
		wp_enqueue_style( 'ashna-otp-admin-core' );
		if ( file_exists( $css_file ) ) {
			wp_add_inline_style( 'ashna-otp-admin-core', (string) file_get_contents( $css_file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}

		wp_enqueue_script( 'ashna-compatibility', ASHNA_OTP_URL . 'assets/js/compatibility.js', array(), ASHNA_OTP_VERSION, true );

		wp_register_script( 'ashna-otp-admin-core', false, array( 'jquery', 'ashna-compatibility' ), ASHNA_OTP_VERSION, true );
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
		);

		$textarea_keys = array(
			'bale_message_template',
			'email_body_text',
			'welcome_message_text',
			'split_features',
			'custom_css',
			'blocked_phones',
			'blocked_ips',
			'whitelisted_identifiers',
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

		foreach ( $defaults as $key => $default_val ) {
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
				} elseif ( 'standalone_page_id' === $key ) {
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

		if ( isset( $raw['custom_redirect_url'] ) ) {
			$clean['redirect_url'] = $clean['custom_redirect_url'];
		} elseif ( ! empty( $clean['redirect_url'] ) && empty( $clean['custom_redirect_url'] ) ) {
			$clean['custom_redirect_url'] = $clean['redirect_url'];
		}

		$clean['otp_length']     = max( 4, min( 8, $clean['otp_length'] ) );
		$clean['form_max_width'] = max( 320, min( 640, $clean['form_max_width'] ) );

		return $clean;
	}

	/**
	 * Clean all active PHP output buffers before emitting JSON
	 */
	private function clean_output_buffers() {
		while ( ob_get_level() > 0 ) {
			@ob_end_clean();
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
			delete_transient( Ashna_Storage::key( 'ashna_bale_safir_token' ) );
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
			$logs_data = Ashna_Logger::get_logs( array( 'per_page' => 2000, 'paged' => 1 ) );
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=ashna-otp-logs-' . gmdate( 'Y-m-d' ) . '.csv' );

			$out = fopen( 'php://output', 'w' );
			// UTF-8 BOM for Excel Persian support
			fwrite( $out, "\xEF\xBB\xBF" );
			fputcsv( $out, array( 'ID', 'Recipient', 'Channel', 'Gateway', 'OTP Code', 'Status', 'Attempts', 'IP Address', 'Response', 'Created At', 'Verified At' ) );

			foreach ( $logs_data['items'] as $row ) {
				fputcsv(
					$out,
					array(
						$row->id,
						$row->recipient,
						$row->channel,
						$row->gateway,
						$row->otp_code,
						$row->status,
						$row->attempts,
						$row->ip_address,
						$row->response_message,
						$row->created_at,
						$row->verified_at,
					)
				);
			}
			fclose( $out );
			exit;
		}
	}

	/**
	 * AJAX Handler: Live Save Settings without page reload
	 */
	public function ajax_save_settings() {
		if ( ! current_user_can( 'manage_options' ) || ! $this->verify_settings_nonce() ) {
			$this->clean_output_buffers();
			wp_send_json_error( array( 'message' => 'نشست امنیتی منقضی شده است؛ لطفاً صفحه را رفرش کنید.' ) );
		}

		$raw   = isset( $_POST['ashna'] ) && is_array( $_POST['ashna'] ) ? wp_unslash( $_POST['ashna'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$clean = $this->sanitize_settings_payload( $raw );

		Ashna_Helper::save_settings( $clean );
		delete_transient( Ashna_Storage::key( 'ashna_bale_safir_token' ) );

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
		check_ajax_referer( 'ashna_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->clean_output_buffers();
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		$recipient  = isset( $_POST['recipient'] ) ? sanitize_text_field( wp_unslash( $_POST['recipient'] ) ) : '';
		$channel    = isset( $_POST['channel'] ) ? sanitize_text_field( wp_unslash( $_POST['channel'] ) ) : 'sms';
		$gateway_id = isset( $_POST['gateway_id'] ) ? sanitize_text_field( wp_unslash( $_POST['gateway_id'] ) ) : '';

		if ( empty( $recipient ) ) {
			$this->clean_output_buffers();
			wp_send_json_error( array( 'message' => 'لطفاً شماره موبایل یا ایمیل تست را وارد کنید.' ) );
		}

		$parsed = Ashna_Helper::parse_identifier( $recipient );
		if ( 'invalid' === $parsed['type'] ) {
			$this->clean_output_buffers();
			wp_send_json_error( array( 'message' => 'شماره موبایل یا آدرس ایمیل وارد شده معتبر نیست.' ) );
		}

		$result = Ashna_Gateway_Manager::dispatch_otp( $parsed['normalized'], $parsed['type'], $channel, $gateway_id );
		$this->clean_output_buffers();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => 'خطا در ارسال تست: ' . $result->get_error_message(),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					'ارسال تست موفق! کد تولیدشده: %s | درگاه: %s | پاسخ: %s',
					$result['code'],
					$result['gateway'],
					$result['response']
				),
			)
		);
	}

	/**
	 * AJAX Handler: Unlock a locked-out phone or IP immediately
	 */
	public function ajax_unlock_target() {
		check_ajax_referer( 'ashna_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->clean_output_buffers();
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		$target = isset( $_POST['target'] ) ? sanitize_text_field( wp_unslash( $_POST['target'] ) ) : '';
		if ( empty( $target ) ) {
			$this->clean_output_buffers();
			wp_send_json_error( array( 'message' => 'شناسه نامعتبر است.' ) );
		}

		Ashna_Security::unlock_target( $target );
		$this->clean_output_buffers();
		wp_send_json_success( array( 'message' => 'رفع مسدودی با موفقیت انجام شد.' ) );
	}

	/**
	 * AJAX Handler: Import JSON Settings
	 */
	public function ajax_import_settings() {
		check_ajax_referer( 'ashna_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->clean_output_buffers();
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		$json_raw = isset( $_POST['json_data'] ) ? wp_unslash( $_POST['json_data'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$decoded  = json_decode( $json_raw, true );

		if ( ! is_array( $decoded ) ) {
			$this->clean_output_buffers();
			wp_send_json_error( array( 'message' => 'فرمت فایل یا متن JSON معتبر نیست.' ) );
		}

		$clean = $this->sanitize_settings_payload( $decoded );
		Ashna_Helper::save_settings( $clean );

		$this->clean_output_buffers();
		wp_send_json_success( array( 'message' => 'تنظیمات با موفقیت درون‌ریزی شد! در حال بارگذاری مجدد...' ) );
	}

	/**
	 * AJAX Handler: Reset Settings to Factory Defaults
	 */
	public function ajax_reset_settings() {
		check_ajax_referer( 'ashna_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->clean_output_buffers();
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		Ashna_Helper::save_settings( Ashna_Helper::default_settings() );
		delete_transient( Ashna_Storage::key( 'ashna_bale_safir_token' ) );

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
			delete_transient( Ashna_Storage::key( 'ashna_bale_safir_token' ) );
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
