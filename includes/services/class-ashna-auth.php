<?php
/**
 * Authentication, Unified Login/Registration, Password Fallback & AJAX Handlers
 *
 * @package Ashna_OTP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Auth {

	/**
	 * Singleton instance
	 *
	 * @var Ashna_Auth|null
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return Ashna_Auth
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
		add_action( 'wp_ajax_nopriv_ashna_request_otp', array( $this, 'ajax_request_otp' ) );
		add_action( 'wp_ajax_ashna_request_otp', array( $this, 'ajax_request_otp' ) );

		add_action( 'wp_ajax_nopriv_ashna_verify_otp', array( $this, 'ajax_verify_otp' ) );
		add_action( 'wp_ajax_ashna_verify_otp', array( $this, 'ajax_verify_otp' ) );

		add_action( 'wp_ajax_nopriv_ashna_password_login', array( $this, 'ajax_password_login' ) );
		add_action( 'wp_ajax_ashna_password_login', array( $this, 'ajax_password_login' ) );
	}

	/**
	 * Send clean JSON error response
	 *
	 * @param array $data Error payload.
	 */
	private function send_error( $data ) {
		if ( ob_get_length() ) {
			ob_clean();
		}
		wp_send_json_error( $data );
	}

	/**
	 * Send clean JSON success response
	 *
	 * @param array $data Success payload.
	 */
	private function send_success( $data ) {
		if ( ob_get_length() ) {
			ob_clean();
		}
		wp_send_json_success( $data );
	}

	/**
	 * Handle AJAX Request OTP
	 */
	public function ajax_request_otp() {
		check_ajax_referer( 'ashna_otp_nonce', 'nonce' );

		$raw_identifier = isset( $_POST['identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['identifier'] ) ) : '';
		if ( empty( $raw_identifier ) ) {
			$this->send_error( array( 'message' => 'لطفاً شماره موبایل یا ایمیل خود را وارد کنید.' ) );
		}

		// Verify Captcha if enabled
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$captcha_ok = Ashna_Security::verify_captcha( wp_unslash( $_POST ) );
		if ( is_wp_error( $captcha_ok ) ) {
			$this->send_error(
				array(
					'message'     => $captcha_ok->get_error_message(),
					'new_captcha' => 'math' === Ashna_Helper::get_option( 'captcha_type' ) ? Ashna_Security::generate_math_captcha() : null,
				)
			);
		}

		$parsed     = Ashna_Helper::parse_identifier( $raw_identifier );
		$type       = $parsed['type'];
		$identifier = $parsed['normalized'];
		$login_mode = Ashna_Helper::get_option( 'login_mode', 'phone_and_email' );

		if ( 'invalid' === $type ) {
			if ( 'phone_only' === $login_mode ) {
				$this->send_error( array( 'message' => 'شماره موبایل وارد شده معتبر نیست. (مثال: 09123456789)' ) );
			} elseif ( 'email_only' === $login_mode ) {
				$this->send_error( array( 'message' => 'آدرس ایمیل وارد شده معتبر نیست.' ) );
			} else {
				$this->send_error( array( 'message' => 'لطفاً یک شماره موبایل معتبر (مثل 09123456789) یا آدرس ایمیل صحیح وارد کنید.' ) );
			}
		}

		if ( 'phone_only' === $login_mode && 'phone' !== $type ) {
			$this->send_error( array( 'message' => 'ورود فقط با شماره موبایل امکان‌پذیر است.' ) );
		}

		if ( 'email_only' === $login_mode && 'email' !== $type ) {
			$this->send_error( array( 'message' => 'ورود فقط با آدرس ایمیل امکان‌پذیر است.' ) );
		}

		$existing_user = self::find_user( $identifier, $type );
		$auto_register = (bool) Ashna_Helper::get_option( 'auto_register', 1 );

		if ( ! $existing_user && ! $auto_register ) {
			$this->send_error( array( 'message' => 'حساب کاربری با این مشخصات یافت نشد.' ) );
		}

		$can_request = Ashna_Security::can_request_otp( $identifier );
		if ( is_wp_error( $can_request ) ) {
			$data = $can_request->get_error_data();
			$this->send_error(
				array(
					'message'   => $can_request->get_error_message(),
					'code'      => $can_request->get_error_code(),
					'remaining' => isset( $data['remaining'] ) ? (int) $data['remaining'] : 0,
				)
			);
		}

		$dispatch = Ashna_Gateway_Manager::dispatch_otp( $identifier, $type );
		if ( is_wp_error( $dispatch ) ) {
			$this->send_error(
				array(
					'message' => 'خطا در ارسال کد تایید: ' . $dispatch->get_error_message(),
				)
			);
		}

		$channel_labels = array(
			'sms'      => 'پیامک',
			'bale'     => 'پیام‌رسان بله',
			'sms+bale' => 'پیامک و پیام‌رسان بله',
			'email'    => 'ایمیل',
		);
		$channel_label  = isset( $channel_labels[ $dispatch['channel'] ] ) ? $channel_labels[ $dispatch['channel'] ] : 'پیامک';

		$is_new_user   = empty( $existing_user );
		$require_name  = $is_new_user ? Ashna_Helper::get_option( 'require_name_on_register', 'optional' ) : 'disabled';
		$require_email = ( $is_new_user && 'phone' === $type ) ? Ashna_Helper::get_option( 'require_email_on_register', 'disabled' ) : 'disabled';

		$response_data = array(
			'message'       => sprintf( 'کد تایید از طریق %s به %s ارسال شد.', $channel_label, Ashna_Helper::mask_identifier( $identifier ) ),
			'identifier'    => $identifier,
			'masked'        => Ashna_Helper::mask_identifier( $identifier ),
			'type'          => $type,
			'channel'       => $dispatch['channel'],
			'cooldown'      => absint( Ashna_Helper::get_option( 'resend_cooldown', 60 ) ),
			'expiry'        => absint( Ashna_Helper::get_option( 'otp_expiry', 120 ) ),
			'otp_length'    => absint( Ashna_Helper::get_option( 'otp_length', 5 ) ),
			'is_new_user'   => $is_new_user,
			'require_name'  => $require_name,
			'require_email' => $require_email,
		);

		if ( 'sandbox' === $dispatch['gateway'] && Ashna_Helper::get_option( 'show_debug_code_in_toast', 0 ) ) {
			$response_data['debug_code'] = $dispatch['code'];
		}

		$this->send_success( $response_data );
	}

	/**
	 * Handle AJAX Verify OTP & Unified Login / Auto-Register
	 */
	public function ajax_verify_otp() {
		check_ajax_referer( 'ashna_otp_nonce', 'nonce' );

		$raw_identifier = isset( $_POST['identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['identifier'] ) ) : '';
		$raw_code       = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		$redirect_to    = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
		$full_name      = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
		$extra_email    = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';

		$parsed     = Ashna_Helper::parse_identifier( $raw_identifier );
		$type       = $parsed['type'];
		$identifier = $parsed['normalized'];

		if ( 'invalid' === $type ) {
			$this->send_error( array( 'message' => 'شناسه کاربری نامعتبر است.' ) );
		}

		$user        = self::find_user( $identifier, $type );
		$is_new_user = false;

		if ( ! $user ) {
			if ( ! Ashna_Helper::get_option( 'auto_register', 1 ) ) {
				$this->send_error( array( 'message' => 'ثبت‌نام خودکار غیرفعال است.' ) );
			}

			$name_mode  = Ashna_Helper::get_option( 'require_name_on_register', 'optional' );
			$email_mode = Ashna_Helper::get_option( 'require_email_on_register', 'disabled' );

			if ( 'required' === $name_mode && empty( $full_name ) ) {
				$this->send_error( array( 'message' => 'لطفاً نام و نام خانوادگی خود را وارد کنید.' ) );
			}

			if ( 'phone' === $type && 'required' === $email_mode && ( empty( $extra_email ) || ! is_email( $extra_email ) ) ) {
				$this->send_error( array( 'message' => 'لطفاً یک آدرس ایمیل معتبر وارد کنید.' ) );
			}
		}

		$verified = Ashna_Security::verify_otp( $identifier, $raw_code );
		if ( is_wp_error( $verified ) ) {
			$this->send_error(
				array(
					'message' => $verified->get_error_message(),
					'code'    => $verified->get_error_code(),
				)
			);
		}

		if ( ! $user ) {
			$user = self::register_user(
				$identifier,
				$type,
				array(
					'full_name'  => $full_name,
					'user_email' => $extra_email,
				)
			);
			if ( is_wp_error( $user ) ) {
				$this->send_error( array( 'message' => $user->get_error_message() ) );
			}
			$is_new_user = true;
		} else {
			if ( 'phone' === $type ) {
				self::sync_user_phone_meta( $user->ID, $identifier );
			}
		}

		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user );
		Ashna_Compatibility::action( 'ashna_otp_user_authenticated', $user, $identifier, $type, $is_new_user );

		$final_redirect = self::resolve_redirect_url( $redirect_to, $user );

		$this->send_success(
			array(
				'message'     => $is_new_user ? 'ثبت‌نام و ورود شما با موفقیت انجام شد! در حال انتقال...' : 'ورود با موفقیت انجام شد! در حال انتقال...',
				'redirect_to' => $final_redirect,
				'user_id'     => $user->ID,
				'is_new_user' => $is_new_user,
			)
		);
	}

	/**
	 * Handle AJAX Password Login Fallback (with Brute-Force Protection)
	 */
	public function ajax_password_login() {
		check_ajax_referer( 'ashna_otp_nonce', 'nonce' );

		if ( ! Ashna_Helper::get_option( 'allow_password_login', 0 ) ) {
			$this->send_error( array( 'message' => 'ورود با رمز عبور غیرفعال است.' ) );
		}

		$raw_identifier = isset( $_POST['identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['identifier'] ) ) : '';
		$password       = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$redirect_to    = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';

		if ( empty( $raw_identifier ) || empty( $password ) ) {
			$this->send_error( array( 'message' => 'لطفاً شناسه کاربری و رمز عبور را وارد کنید.' ) );
		}

		$parsed     = Ashna_Helper::parse_identifier( $raw_identifier );
		$identifier = 'invalid' !== $parsed['type'] ? $parsed['normalized'] : $raw_identifier;

		$lockout = Ashna_Security::check_lockout( $identifier );
		if ( is_wp_error( $lockout ) ) {
			$this->send_error( array( 'message' => $lockout->get_error_message() ) );
		}

		$user = 'invalid' !== $parsed['type'] ? self::find_user( $identifier, $parsed['type'] ) : get_user_by( 'login', $identifier );
		if ( ! $user || ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
			Ashna_Security::record_failed_password_attempt( $identifier );
			$this->send_error( array( 'message' => 'نام کاربری/شماره یا رمز عبور اشتباه است.' ) );
		}

		Ashna_Security::clear_password_attempts( $identifier );

		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user );

		$this->send_success(
			array(
				'message'     => 'ورود با موفقیت انجام شد! در حال انتقال...',
				'redirect_to' => self::resolve_redirect_url( $redirect_to, $user ),
			)
		);
	}

	/**
	 * Find existing WordPress user by normalized phone or email
	 * Supports users registered via Ashna, Digits, WooCommerce, MihanPanel, or standard WP login
	 *
	 * @param string $identifier Normalized phone (09XXXXXXXXX) or email.
	 * @param string $type       'phone' or 'email'.
	 * @return WP_User|false
	 */
	public static function find_user( $identifier, $type = 'phone' ) {
		if ( 'email' === $type ) {
			return get_user_by( 'email', $identifier );
		}

		// 1. Fast check by ashna_phone meta
		$users = get_users(
			array(
				'meta_key'   => Ashna_Storage::key( 'ashna_phone' ),
				'meta_value' => $identifier,
				'number'     => 1,
			)
		);
		if ( ! empty( $users ) ) {
			return $users[0];
		}

		// 2. Check across WooCommerce, Digits, and other OTP plugin meta keys in all phone formats
		$intl_plus = Ashna_Helper::to_international_phone( $identifier, true );  // +989123456789
		$intl_raw  = Ashna_Helper::to_international_phone( $identifier, false ); // 989123456789
		$no_zero   = ltrim( $identifier, '0' );                                  // 9123456789

		$compat_users = get_users(
			array(
				'meta_query' => array(
					'relation' => 'OR',
					array(
						'key'     => 'billing_phone',
						'value'   => array( $identifier, $intl_plus, $intl_raw, $no_zero ),
						'compare' => 'IN',
					),
					array(
						'key'     => 'digits_phone',
						'value'   => array( $intl_plus, $intl_raw, $identifier, $no_zero ),
						'compare' => 'IN',
					),
					array(
						'key'     => 'digits_phone_no',
						'value'   => array( $no_zero, $identifier ),
						'compare' => 'IN',
					),
					array(
						'key'     => 'mobile',
						'value'   => array( $identifier, $intl_plus, $no_zero ),
						'compare' => 'IN',
					),
				),
				'number'     => 1,
			)
		);
		if ( ! empty( $compat_users ) ) {
			return $compat_users[0];
		}

		// 3. Check by user_login matching common phone formats (0912..., 98912..., +98912..., 912..., or prefixed)
		$login_candidates = array( $identifier, $intl_raw, $intl_plus, $no_zero, 'u_' . $identifier );
		$custom_prefix    = sanitize_key( Ashna_Helper::get_option( 'username_prefix', '' ) );
		if ( ! empty( $custom_prefix ) ) {
			$login_candidates[] = $custom_prefix . $identifier;
		}

		foreach ( array_unique( $login_candidates ) as $candidate_login ) {
			$user_by_login = get_user_by( 'login', $candidate_login );
			if ( $user_by_login ) {
				return $user_by_login;
			}
		}

		return false;
	}

	/**
	 * Synchronize user phone number across Ashna, WooCommerce, and Digits meta keys
	 * Ensures 100% forward & backward compatibility even if plugins are switched in the future
	 *
	 * @param int    $user_id    WordPress User ID.
	 * @param string $identifier Normalized phone (09XXXXXXXXX).
	 */
	public static function sync_user_phone_meta( $user_id, $identifier ) {
		$intl_plus = Ashna_Helper::to_international_phone( $identifier, true ); // +989123456789
		$no_zero   = ltrim( $identifier, '0' );                                 // 9123456789

		// 1. Ashna native meta
		update_user_meta( $user_id, Ashna_Storage::key( 'ashna_phone' ), $identifier );

		// 2. WooCommerce native billing_phone
		if ( ! get_user_meta( $user_id, 'billing_phone', true ) ) {
			update_user_meta( $user_id, 'billing_phone', $identifier );
		}

		// 3. Digits native meta keys (for seamless cross-plugin compatibility)
		if ( ! get_user_meta( $user_id, 'digits_phone', true ) ) {
			update_user_meta( $user_id, 'digits_phone', $intl_plus );
		}
		if ( ! get_user_meta( $user_id, 'digits_phone_no', true ) ) {
			update_user_meta( $user_id, 'digits_phone_no', $no_zero );
		}
		if ( ! get_user_meta( $user_id, 'digt_countrycode', true ) ) {
			update_user_meta( $user_id, 'digt_countrycode', '+98' );
		}
	}

	/**
	 * Automatically register a new user from verified phone or email
	 *
	 * @param string $identifier Normalized phone or email.
	 * @param string $type       'phone' or 'email'.
	 * @param array  $extra      Extra profile fields (full_name, user_email).
	 * @return WP_User|WP_Error
	 */
	public static function register_user( $identifier, $type = 'phone', $extra = array() ) {
		$prefix = sanitize_key( Ashna_Helper::get_option( 'username_prefix', '' ) );

		if ( 'email' === $type ) {
			$email_local = explode( '@', $identifier )[0];
			$base_login  = sanitize_user( $email_local, true );
			if ( empty( $base_login ) ) {
				$base_login = ( ! empty( $prefix ) ? $prefix : 'user_' ) . wp_rand( 10000, 99999 );
			}
			$user_login = $base_login;
			$suffix     = 1;
			while ( username_exists( $user_login ) ) {
				$user_login = $base_login . '_' . $suffix;
				++$suffix;
			}
			$user_email = $identifier;
		} else {
			$user_login = ! empty( $prefix ) ? ( $prefix . $identifier ) : $identifier;
			if ( username_exists( $user_login ) ) {
				$user_login = $identifier . '_' . wp_rand( 100, 999 );
			}
			$user_email = ! empty( $extra['user_email'] ) && is_email( $extra['user_email'] ) && ! email_exists( $extra['user_email'] )
				? $extra['user_email']
				: '';
		}

		$desired_role = Ashna_Helper::get_option( 'default_user_role', 'customer' );
		if ( 'customer' === $desired_role && ! wp_roles()->is_role( 'customer' ) ) {
			$desired_role = get_option( 'default_role', 'subscriber' );
		}

		$full_name    = ! empty( $extra['full_name'] ) ? trim( $extra['full_name'] ) : '';
		$first_name   = '';
		$last_name    = '';
		$display_name = 'phone' === $type ? $identifier : explode( '@', $identifier )[0];

		if ( ! empty( $full_name ) ) {
			$display_name = $full_name;
			$name_parts   = preg_split( '/\s+/', $full_name, 2 );
			$first_name   = $name_parts[0];
			$last_name    = isset( $name_parts[1] ) ? $name_parts[1] : '';
		}

		$userdata = array(
			'user_login'   => $user_login,
			'user_pass'    => wp_generate_password( 24, true, true ),
			'user_email'   => $user_email,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => $display_name,
			'role'         => $desired_role,
		);

		$user_id = wp_insert_user( $userdata );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		if ( 'phone' === $type ) {
			self::sync_user_phone_meta( $user_id, $identifier );
		}

		if ( ! empty( $first_name ) ) {
			update_user_meta( $user_id, 'billing_first_name', $first_name );
		}
		if ( ! empty( $last_name ) ) {
			update_user_meta( $user_id, 'billing_last_name', $last_name );
		}

		update_user_meta( $user_id, Ashna_Storage::key( 'ashna_registered_via_otp' ), 1 );

		Ashna_Compatibility::action( 'ashna_otp_user_registered', $user_id, $identifier, $type, $extra );

		return get_user_by( 'id', $user_id );
	}

	/**
	 * Resolve redirect URL after login (supports role-based & referer redirects)
	 *
	 * @param string  $requested_redirect Redirect passed from form.
	 * @param WP_User $user               Authenticated user.
	 * @return string
	 */
	private static function resolve_redirect_url( $requested_redirect, $user ) {
		$admin_redirect = trim( (string) Ashna_Helper::get_option( 'admin_redirect_url', '' ) );
		if ( ! empty( $admin_redirect ) && user_can( $user, 'manage_options' ) ) {
			return esc_url_raw( $admin_redirect );
		}

		if ( ! empty( $requested_redirect ) ) {
			return wp_validate_redirect( $requested_redirect, home_url( '/' ) );
		}

		$behavior            = Ashna_Helper::get_option( 'redirect_behavior', 'auto' );
		$configured_redirect = trim( (string) Ashna_Helper::get_option( 'custom_redirect_url', '' ) );
		if ( empty( $configured_redirect ) ) {
			$configured_redirect = trim( (string) Ashna_Helper::get_option( 'redirect_url', '' ) );
		}

		if ( 'custom' === $behavior && ! empty( $configured_redirect ) ) {
			$redirect = esc_url_raw( $configured_redirect );
		} elseif ( 'my_account' === $behavior || 'myaccount' === $behavior ) {
			$redirect = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : admin_url( 'profile.php' );
		} elseif ( 'home' === $behavior ) {
			$redirect = home_url( '/' );
		} elseif ( ( 'auto' === $behavior || 'referer' === $behavior ) && wp_get_referer() ) {
			$redirect = wp_validate_redirect( wp_get_referer(), home_url( '/' ) );
		} elseif ( function_exists( 'wc_get_page_permalink' ) ) {
			$redirect = wc_get_page_permalink( 'myaccount' );
		} else {
			$redirect = home_url( '/' );
		}

		return Ashna_Compatibility::filter( 'ashna_otp_login_redirect', $redirect, $user );
	}
}
