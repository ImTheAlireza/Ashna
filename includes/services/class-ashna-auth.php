<?php
/**
 * Authentication, Unified Login/Registration, Password Fallback & AJAX Handlers
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Missing -- every AJAX handler below calls Ashna_Session::verify_request() first.
class Ashna_Auth {

	/**
	 * Option holding whether Digits phone keys can exist in this database ('yes'/'no').
	 *
	 * Written once by Ashna_Activator::ensure_digits_meta_flag() (autoloaded, so reading it is
	 * free) and never downgraded.
	 */
	const DIGITS_META_OPTION = 'ashna_otp_digits_meta';

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
		return; // wp_send_json() ends the request: never continue past a response.
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
	 *
	 * Order matters for SMS-bombing protection: cheap checks first (nonce, honeypot, captcha),
	 * then the account lookup, then an atomic reservation of the send slot (per-minute/hour/day
	 * limits per number, IP and network, daily credit guard) and only then the gateway call.
	 */
	public function ajax_request_otp() {
		if ( true !== Ashna_Session::verify_request() ) {
			return;
		}

		$raw_identifier = isset( $_POST['identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['identifier'] ) ) : '';
		$parsed         = Ashna_Helper::parse_identifier( $raw_identifier );
		$type           = $parsed['type'];
		$identifier     = $parsed['normalized'];
		$ip             = Ashna_Helper::get_client_ip();

		// Honeypot: a field people never see. Bots that fill every input get a normal-looking
		// answer, but nothing is sent and nothing is stored.
		if ( ! empty( $_POST['ashna_website'] ) ) {
			Ashna_Rate_Limiter::record_block( 'ashna_honeypot', 'invalid' !== $type ? $identifier : '', $ip );
			$this->send_success(
				array(
					'message'       => 'کد تایید ارسال شد.',
					'identifier'    => $identifier,
					'masked'        => Ashna_Helper::mask_identifier( $identifier ),
					'type'          => $type,
					'channel'       => 'sms',
					'cooldown'      => absint( Ashna_Helper::get_option( 'resend_cooldown', 60 ) ),
					'expiry'        => absint( Ashna_Helper::get_option( 'otp_expiry', 120 ) ),
					'otp_length'    => absint( Ashna_Helper::get_option( 'otp_length', 5 ) ),
					'is_new_user'   => false,
					'require_name'  => 'disabled',
					'require_email' => 'disabled',
				)
			);
		}

		if ( empty( $raw_identifier ) ) {
			$this->send_error( array( 'message' => 'لطفاً شماره موبایل یا ایمیل خود را وارد کنید.' ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}

		// Captcha: the configured one, or the math captcha while attack mode is on. A resend
		// ticket from this visitor's previous successful request for the same number replaces it.
		$resend_ticket = isset( $_POST['resend_token'] ) ? sanitize_text_field( wp_unslash( $_POST['resend_token'] ) ) : '';
		$has_ticket    = 'invalid' !== $type && Ashna_Security::valid_resend_ticket( $resend_ticket, $identifier );
		if ( Ashna_Rate_Limiter::captcha_required() && ! $has_ticket ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$captcha_ok = Ashna_Security::verify_captcha( wp_unslash( $_POST ) );
			if ( is_wp_error( $captcha_ok ) ) {
				Ashna_Rate_Limiter::record_block( $captcha_ok->get_error_code(), 'invalid' !== $type ? $identifier : '', $ip );
				$this->send_error(
					array(
						'message'     => $captcha_ok->get_error_message(),
						'code'        => $captcha_ok->get_error_code(),
						'new_captcha' => self::next_math_captcha(),
					)
				);
				return; // wp_send_json() ends the request: never continue past a response.
			}
		}

		$login_mode = Ashna_Helper::get_option( 'login_mode', 'phone_and_email' );

		if ( 'invalid' === $type ) {
			if ( 'phone_only' === $login_mode ) {
				$message = 'شماره موبایل وارد شده معتبر نیست. (مثال: 09123456789)';
			} elseif ( 'email_only' === $login_mode ) {
				$message = 'آدرس ایمیل وارد شده معتبر نیست.';
			} else {
				$message = 'لطفاً یک شماره موبایل معتبر (مثل 09123456789) یا آدرس ایمیل صحیح وارد کنید.';
			}
			$this->send_error( array( 'message' => $message, 'new_captcha' => self::next_math_captcha() ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}

		if ( 'phone_only' === $login_mode && 'phone' !== $type ) {
			$this->send_error( array( 'message' => 'ورود فقط با شماره موبایل امکان‌پذیر است.', 'new_captcha' => self::next_math_captcha() ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}

		if ( 'email_only' === $login_mode && 'email' !== $type ) {
			$this->send_error( array( 'message' => 'ورود فقط با آدرس ایمیل امکان‌پذیر است.', 'new_captcha' => self::next_math_captcha() ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}

		$account = self::resolve_account( $identifier, $type );
		if ( $account['error'] ) {
			$this->send_error(
				array(
					'message'     => $account['error']->get_error_message(),
					'code'        => $account['error']->get_error_code(),
					'new_captcha' => self::next_math_captcha(),
				)
			);
			return; // wp_send_json() ends the request: never continue past a response.
		}
		$existing_user = $account['user'];
		$auto_register = (bool) Ashna_Helper::get_option( 'auto_register', 1 );

		if ( ! $existing_user && ! $auto_register ) {
			$this->send_error( array( 'message' => 'حساب کاربری با این مشخصات یافت نشد.', 'new_captcha' => self::next_math_captcha() ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}

		$reservation = Ashna_Rate_Limiter::reserve( $identifier, $ip );
		if ( is_wp_error( $reservation ) ) {
			$data = $reservation->get_error_data();
			$this->send_error(
				array(
					'message'     => $reservation->get_error_message(),
					'code'        => $reservation->get_error_code(),
					'remaining'   => isset( $data['remaining'] ) ? (int) $data['remaining'] : 0,
					'new_captcha' => self::next_math_captcha(),
				)
			);
			return; // wp_send_json() ends the request: never continue past a response.
		}

		$dispatch = Ashna_Gateway_Manager::dispatch_otp( $identifier, $type, '', '', $reservation );
		if ( is_wp_error( $dispatch ) ) {
			// The provider's reason ("credit too low", "invalid API key", "check SMTP" ...) is kept in
			// the logs; visitors get a plain message, administrators and debug mode see the detail.
			$show_detail = current_user_can( 'manage_options' ) || Ashna_Helper::get_option( 'show_debug_code_in_toast', 0 );
			$this->send_error(
				array(
					'message'     => $show_detail
						? 'خطا در ارسال کد تایید: ' . $dispatch->get_error_message()
						: ( 'email' === $type
							? 'ارسال ایمیل کد تایید در حال حاضر ممکن نشد. لطفاً چند دقیقه دیگر دوباره تلاش کنید.'
							: 'ارسال کد تایید در حال حاضر ممکن نشد. لطفاً چند دقیقه دیگر دوباره تلاش کنید.' ),
					'code'        => 'ashna_send_failed',
					'new_captcha' => self::next_math_captcha(),
				)
			);
			return; // wp_send_json() ends the request: never continue past a response.
		}

		$channel_labels = array(
			'sms'       => 'پیامک',
			'bale'      => 'پیام‌رسان بله',
			'sms+bale'  => 'پیامک و پیام‌رسان بله',
			'email'     => 'ایمیل',
			'messenger' => Ashna_Gateway_Msgway_Messenger::route_label(),
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

		// Resends of this number from this visitor skip the captcha (limits still apply).
		$response_data['resend_token'] = Ashna_Security::resend_ticket( $identifier );
		$response_data['new_captcha']  = self::next_math_captcha();

		$this->send_success( $response_data );
	}

	/**
	 * A fresh math captcha when it is in use (single-use tokens must be replaced after every request).
	 *
	 * @return array|null
	 */
	private static function next_math_captcha() {
		return Ashna_Security::math_captcha_active() ? Ashna_Security::generate_math_captcha() : null;
	}

	/**
	 * Handle AJAX Verify OTP & Unified Login / Auto-Register
	 */
	public function ajax_verify_otp() {
		if ( true !== Ashna_Session::verify_request() ) {
			return;
		}

		$raw_identifier = isset( $_POST['identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['identifier'] ) ) : '';
		$raw_code       = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		$redirect_to    = isset( $_POST['redirect_to'] ) ? esc_url_raw( Ashna_Helper::as_string( wp_unslash( $_POST['redirect_to'] ) ) ) : '';
		$full_name      = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
		$extra_email    = isset( $_POST['user_email'] ) ? sanitize_email( Ashna_Helper::as_string( wp_unslash( $_POST['user_email'] ) ) ) : '';
		$form_context   = isset( $_POST['context'] ) ? sanitize_key( Ashna_Helper::as_string( wp_unslash( $_POST['context'] ) ) ) : '';

		$parsed     = Ashna_Helper::parse_identifier( $raw_identifier );
		$type       = $parsed['type'];
		$identifier = $parsed['normalized'];

		if ( 'invalid' === $type ) {
			$this->send_error( array( 'message' => 'شناسه کاربری نامعتبر است.' ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}

		$account = self::resolve_account( $identifier, $type );
		if ( $account['error'] ) {
			$this->send_error(
				array(
					'message' => $account['error']->get_error_message(),
					'code'    => $account['error']->get_error_code(),
				)
			);
			return; // wp_send_json() ends the request: never continue past a response.
		}
		$user        = $account['user'];
		$is_new_user = false;

		/**
		 * Extra sign-up data from the form (e.g. Dokan vendor fields). Only used for new accounts.
		 *
		 * @param array  $extra      Data passed to register_user().
		 * @param array  $post       Raw request (unslashed).
		 * @param string $identifier Normalized phone/email.
		 * @param string $type       phone|email.
		 */
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- integrations sanitize their own fields.
		$registration = (array) apply_filters( 'ashna_otp_registration_data', array( 'full_name' => $full_name, 'user_email' => $extra_email ), wp_unslash( $_POST ), $identifier, $type );

		if ( ! $user ) {
			if ( ! Ashna_Helper::get_option( 'auto_register', 1 ) ) {
				$this->send_error( array( 'message' => 'ثبت‌نام خودکار غیرفعال است.' ) );
				return; // wp_send_json() ends the request: never continue past a response.
			}

			$name_mode  = Ashna_Helper::get_option( 'require_name_on_register', 'optional' );
			$email_mode = Ashna_Helper::get_option( 'require_email_on_register', 'disabled' );

			if ( 'required' === $name_mode && empty( $registration['full_name'] ) ) {
				$this->send_error( array( 'message' => 'لطفاً نام و نام خانوادگی خود را وارد کنید.' ) );
				return; // wp_send_json() ends the request: never continue past a response.
			}

			if ( 'phone' === $type && 'required' === $email_mode && ( empty( $extra_email ) || ! is_email( $extra_email ) ) ) {
				$this->send_error( array( 'message' => 'لطفاً یک آدرس ایمیل معتبر وارد کنید.' ) );
				return; // wp_send_json() ends the request: never continue past a response.
			}
		}

		if ( ! $user ) {
			/**
			 * Validate sign-up data BEFORE the code is checked, so a mistake does not burn the code.
			 *
			 * @param null|WP_Error $error        Error to stop the sign-up.
			 * @param array         $registration Sign-up data.
			 * @param string        $identifier   Normalized phone/email.
			 * @param string        $type         phone|email.
			 */
			$registration_error = apply_filters( 'ashna_otp_registration_errors', null, $registration, $identifier, $type );
			if ( is_wp_error( $registration_error ) ) {
				$this->send_error(
					array(
						'message' => $registration_error->get_error_message(),
						'code'    => $registration_error->get_error_code(),
						'field'   => (string) $registration_error->get_error_data(),
					)
				);
				return; // wp_send_json() ends the request: never continue past a response.
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
			return; // wp_send_json() ends the request: never continue past a response.
		}

		if ( ! $user ) {
			$user = self::register_user( $identifier, $type, $registration );
			if ( is_wp_error( $user ) ) {
				$this->send_error( array( 'message' => $user->get_error_message() ) );
				return; // wp_send_json() ends the request: never continue past a response.
			}
			$is_new_user = true;
		} else {
			if ( 'phone' === $type ) {
				self::sync_user_phone_meta( $user->ID, $identifier );
			}
		}

		$logged_in = Ashna_Session::login(
			$user,
			array(
				'method'      => 'otp',
				'identifier'  => $identifier,
				'is_new_user' => $is_new_user,
				'form'        => $form_context,
			)
		);
		if ( is_wp_error( $logged_in ) ) {
			$this->send_error(
				array(
					'message' => $logged_in->get_error_message(),
					'code'    => $logged_in->get_error_code(),
				)
			);
			return; // wp_send_json() ends the request: never continue past a response.
		}
		do_action( 'ashna_otp_user_authenticated', $user, $identifier, $type, $is_new_user );

		$this->send_success(
			self::login_response(
				$user,
				$redirect_to,
				$is_new_user ? 'ثبت‌نام و ورود شما با موفقیت انجام شد! در حال انتقال...' : 'ورود با موفقیت انجام شد! در حال انتقال...',
				array( 'is_new_user' => $is_new_user ),
				array(
					'is_new_user' => $is_new_user,
					'form'        => $form_context,
				)
			)
		);
	}

	/**
	 * Success payload shared by every sign-in path.
	 *
	 * The nonce belongs to the new session, so follow-up requests on the same page (for
	 * example registering a passkey right after the OTP step) are accepted.
	 *
	 * @param WP_User $user        Signed-in user.
	 * @param string  $redirect_to Requested redirect.
	 * @param string  $message     Message.
	 * @param array   $extra       Extra fields.
	 * @param array   $context     Redirect context { is_new_user, form }.
	 * @return array
	 */
	public static function login_response( $user, $redirect_to, $message, $extra = array(), $context = array() ) {
		$redirect = Ashna_Session::redirect_url( $user, $redirect_to, $context );
		$state    = Ashna_Session::client_state();

		$payload = array_merge(
			array(
				'message'     => $message,
				'redirect_to' => $redirect,
				'redirect'    => $redirect,
				'user_id'     => $user->ID,
				'nonce'       => $state['nonce'],
				'logged_in'   => $state['logged_in'],
			),
			$extra
		);

		/**
		 * Filters the sign-in response (e.g. to add a token for a headless front end).
		 *
		 * @param array   $payload Response data.
		 * @param WP_User $user    Signed-in user.
		 */
		return apply_filters( 'ashna_otp_login_response', $payload, $user );
	}

	/**
	 * Handle AJAX Password Login Fallback (with Brute-Force Protection)
	 */
	public function ajax_password_login() {
		if ( true !== Ashna_Session::verify_request() ) {
			return;
		}

		$form_context = isset( $_POST['context'] ) ? sanitize_key( Ashna_Helper::as_string( wp_unslash( $_POST['context'] ) ) ) : '';
		// With password sign-in switched off, the wp-login.php takeover still lets staff accounts use
		// their password (they cannot use an unverified phone, and the classic form is gone), so an
		// administrator is never locked out. Customers stay code-only.
		$password_enabled = (bool) Ashna_Helper::get_option( 'allow_password_login', 0 );
		$staff_only       = ! $password_enabled && 'wp-login' === $form_context && self::login_takeover_active();
		if ( ! $password_enabled && ! $staff_only ) {
			$this->send_error( array( 'message' => 'ورود با رمز عبور غیرفعال است.' ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}

		$raw_identifier = isset( $_POST['identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['identifier'] ) ) : '';
		// Not unslashed on purpose: WordPress hashes and checks passwords exactly as posted (slashed)
		// in wp-login.php, profile edits, password resets and WooCommerce, so "p'ss" is stored as the
		// hash of "p\'ss". Unslashing here would lock out everyone with ' " or \ in their password.
		$password = isset( $_POST['password'] ) && is_string( $_POST['password'] ) ? $_POST['password'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$redirect_to    = isset( $_POST['redirect_to'] ) ? esc_url_raw( Ashna_Helper::as_string( wp_unslash( $_POST['redirect_to'] ) ) ) : '';

		if ( empty( $raw_identifier ) || empty( $password ) ) {
			$this->send_error( array( 'message' => 'لطفاً شناسه کاربری و رمز عبور را وارد کنید.' ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}

		$parsed     = Ashna_Helper::parse_identifier( $raw_identifier );
		$identifier = 'invalid' !== $parsed['type'] ? $parsed['normalized'] : $raw_identifier;

		$lockout = Ashna_Security::check_lockout( $identifier );
		if ( is_wp_error( $lockout ) ) {
			$this->send_error( array( 'message' => $lockout->get_error_message() ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}

		// Locate the account, then let WordPress authenticate it through the full `authenticate`
		// filter chain (2FA, login limiters, membership approval ...) exactly like wp-login.php.
		// Accounts locked by earlier failures (under any of their identifiers) are skipped.
		$candidates   = array();
		$account_keys = array();
		$locked_error = null;
		foreach ( self::password_candidates( $identifier, $parsed['type'] ) as $candidate ) {
			if ( $staff_only && ! self::is_staff_account( $candidate ) ) {
				continue;
			}
			$account_key = Ashna_Security::account_lock_key( $candidate );
			$locked      = Ashna_Security::check_lockout( $account_key );
			if ( is_wp_error( $locked ) ) {
				$locked_error = $locked;
				continue;
			}
			$candidates[]   = $candidate;
			$account_keys[] = $account_key;
		}
		if ( empty( $candidates ) && $locked_error ) {
			$this->send_error( array( 'message' => $locked_error->get_error_message() ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}

		$user = null;
		foreach ( $candidates as $candidate ) {
			if ( wp_check_password( $password, $candidate->user_pass, $candidate->ID ) ) {
				$user = $candidate;
				break;
			}
		}

		$generic = $staff_only
			? 'اطلاعات ورود صحیح نیست. ورود با رمز عبور در این صفحه فقط برای مدیران سایت است؛ مشتریان لطفاً با کد یکبارمصرف وارد شوند.'
			: 'نام کاربری/شماره یا رمز عبور اشتباه است.';
		if ( ! $user ) {
			Ashna_Security::record_failed_password_attempt( $identifier, $account_keys );
			do_action( 'wp_login_failed', $raw_identifier, new WP_Error( 'incorrect_password', $generic ) );
			$this->send_error( array( 'message' => $generic ) );
			return; // wp_send_json() ends the request: never continue past a response.
		}

		$authenticated = wp_authenticate( $user->user_login, $password );
		if ( is_wp_error( $authenticated ) ) {
			$credential_errors = array( 'incorrect_password', 'invalid_username', 'invalid_email', 'empty_password', 'empty_username' );
			if ( in_array( $authenticated->get_error_code(), $credential_errors, true ) ) {
				Ashna_Security::record_failed_password_attempt( $identifier, array( Ashna_Security::account_lock_key( $user ) ) );
				$this->send_error( array( 'message' => $generic ) );
				return; // wp_send_json() ends the request: never continue past a response.
			}
			$readable = Ashna_Session::readable_error( $authenticated );
			$this->send_error(
				array(
					'message' => $readable->get_error_message(),
					'code'    => $readable->get_error_code(),
				)
			);
			return; // wp_send_json() ends the request: never continue past a response.
		}

		Ashna_Security::clear_password_attempts( $identifier, array( Ashna_Security::account_lock_key( $authenticated ) ) );

		$logged_in = Ashna_Session::login(
			$authenticated,
			array(
				'method'        => 'password',
				'identifier'    => $identifier,
				'authenticated' => true,
				'form'          => $form_context,
			)
		);
		if ( is_wp_error( $logged_in ) ) {
			$this->send_error(
				array(
					'message' => $logged_in->get_error_message(),
					'code'    => $logged_in->get_error_code(),
				)
			);
			return; // wp_send_json() ends the request: never continue past a response.
		}

		$this->send_success( self::login_response( $authenticated, $redirect_to, 'ورود با موفقیت انجام شد! در حال انتقال...', array(), array( 'form' => $form_context ) ) );
	}

	/**
	 * Accounts a password sign-in may belong to (a phone can match more than one account).
	 *
	 * @param string $identifier Normalized phone/email or raw username.
	 * @param string $type       phone|email|invalid.
	 * @return WP_User[]
	 */
	private static function password_candidates( $identifier, $type ) {
		if ( 'email' === $type ) {
			$user = get_user_by( 'email', $identifier );
			if ( ! $user ) {
				$user = get_user_by( 'login', $identifier );
			}
			return $user ? array( $user ) : array();
		}
		if ( 'phone' !== $type ) {
			$user = get_user_by( 'login', $identifier );
			return $user ? array( $user ) : array();
		}

		$ids = array();
		foreach ( self::verified_phone_lookups( $identifier ) as $meta_query ) {
			$ids = array_merge( $ids, self::user_ids_by_meta( $meta_query, 5 ) );
		}
		$ids = array_merge( $ids, self::unverified_phone_candidates( $identifier ) );

		$ids = array_slice( array_values( array_unique( array_map( 'absint', $ids ) ) ), 0, 5 );
		if ( ! empty( $ids ) ) {
			if ( function_exists( 'cache_users' ) ) {
				cache_users( $ids );
			}
			if ( function_exists( 'update_meta_cache' ) ) {
				update_meta_cache( 'user', $ids );
			}
		}

		$users = array();
		foreach ( $ids as $id ) {
			$user = get_user_by( 'id', $id );
			if ( $user ) {
				$users[] = $user;
			}
		}
		return $users;
	}

	/**
	 * Find an existing WordPress user by normalized phone or email (backward-compatible API).
	 * Supports users registered via Ashna, Digits, WooCommerce, MihanPanel, or standard WP login.
	 *
	 * @param string $identifier Normalized phone (09XXXXXXXXX) or email.
	 * @param string $type       'phone' or 'email'.
	 * @return WP_User|false False when no account — or no single safe account — matches.
	 */
	public static function find_user( $identifier, $type = 'phone' ) {
		$account = self::resolve_account( $identifier, $type );
		return $account['user'] ? $account['user'] : false;
	}

	/**
	 * Resolve the account that owns a phone/email, without guessing.
	 *
	 * 1. Phones verified by an OTP sign-in (Ashna, then Digits) are trusted.
	 * 2. Unverified profile data (WooCommerce billing phone, legacy "mobile" field, phone-like
	 *    usernames) is used only for ordinary accounts that have no other verified phone.
	 *    Staff accounts (admins, shop managers, editors ...) are never opened through
	 *    unverified data: one mistyped billing phone must not hand out admin access.
	 * 3. When more than one account matches, nobody is signed in and nothing is created.
	 *
	 * @param string $identifier Normalized phone or email.
	 * @param string $type       'phone' or 'email'.
	 * @return array { user: WP_User|null, error: WP_Error|null }
	 */
	public static function resolve_account( $identifier, $type = 'phone' ) {
		$none = array(
			'user'  => null,
			'error' => null,
		);

		if ( 'email' === $type ) {
			$user = get_user_by( 'email', $identifier );
			return $user ? array( 'user' => $user, 'error' => null ) : $none;
		}
		if ( 'phone' !== $type || '' === (string) $identifier ) {
			return $none;
		}

		// 1. Verified phones.
		foreach ( self::verified_phone_lookups( $identifier ) as $meta_query ) {
			$ids = self::user_ids_by_meta( $meta_query, 2 );
			if ( 1 === count( $ids ) ) {
				$user = get_user_by( 'id', $ids[0] );
				if ( $user ) {
					return array( 'user' => $user, 'error' => null );
				}
			} elseif ( count( $ids ) > 1 ) {
				return array( 'user' => null, 'error' => self::ambiguous_error() );
			}
		}

		// 2. Unverified profile data.
		/**
		 * Extra candidate user IDs for a phone (integrations, custom profile fields).
		 *
		 * @param int[]  $ids        Candidate IDs.
		 * @param string $identifier Normalized phone (09XXXXXXXXX).
		 */
		$ids = apply_filters( 'ashna_otp_phone_candidates', self::unverified_phone_candidates( $identifier ), $identifier );
		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );

		// One query for the user rows and one for all their meta, instead of a query per user
		// and up to three per meta key further down (is_staff_account/has_other_verified_phone).
		if ( ! empty( $ids ) ) {
			if ( function_exists( 'cache_users' ) ) {
				cache_users( $ids );
			}
			if ( function_exists( 'update_meta_cache' ) ) {
				update_meta_cache( 'user', $ids );
			}
		}

		$eligible  = array();
		$staff_hit = false;
		foreach ( $ids as $id ) {
			$user = get_user_by( 'id', $id );
			if ( ! $user ) {
				continue;
			}
			if ( self::is_staff_account( $user ) ) {
				$staff_hit = true;
				continue;
			}
			if ( self::has_other_verified_phone( $user->ID, $identifier ) ) {
				continue;
			}
			$eligible[] = $user;
		}

		if ( 1 === count( $eligible ) ) {
			return array( 'user' => $eligible[0], 'error' => null );
		}
		if ( count( $eligible ) > 1 ) {
			return array( 'user' => null, 'error' => self::ambiguous_error() );
		}
		if ( $staff_hit ) {
			return array(
				'user'  => null,
				'error' => new WP_Error( 'ashna_staff_phone_unverified', 'ورود با کد یکبارمصرف برای این شماره فعال نیست. لطفاً با رمز عبور وارد شوید یا با پشتیبانی سایت تماس بگیرید.' ),
			);
		}

		return $none;
	}

	/**
	 * Whether Ashna currently replaces wp-login.php (setting on, no recovery constant).
	 *
	 * @return bool
	 */
	public static function login_takeover_active() {
		if ( defined( 'ASHNA_DISABLE_LOGIN_TAKEOVER' ) && ASHNA_DISABLE_LOGIN_TAKEOVER ) {
			return false;
		}
		return (bool) Ashna_Helper::get_option( 'wp_login_integration', 0 );
	}

	/**
	 * Whether an account has staff capabilities (never opened through unverified phone data).
	 *
	 * @param WP_User $user User.
	 * @return bool
	 */
	public static function is_staff_account( $user ) {
		$is_staff = false;
		foreach ( array( 'manage_options', 'edit_users', 'promote_users', 'activate_plugins', 'manage_woocommerce', 'edit_others_posts' ) as $cap ) {
			if ( user_can( $user, $cap ) ) {
				$is_staff = true;
				break;
			}
		}
		return (bool) apply_filters( 'ashna_otp_is_staff_account', $is_staff, $user );
	}

	/**
	 * Meta queries for phones verified by an OTP sign-in (Ashna first, then Digits).
	 *
	 * @param string $identifier Normalized phone.
	 * @return array[]
	 */
	private static function verified_phone_lookups( $identifier ) {
		$lookups = array(
			array(
				array(
					'key'   => 'ashna_phone',
					'value' => $identifier,
				),
			),
		);

		// Digits keeps the phone under its own keys; on a site that never used Digits those rows
		// cannot exist, so the meta query (and the whole new-phone lookup behind it) is skipped.
		if ( ! self::digits_meta_reachable() ) {
			return $lookups;
		}

		$intl_plus = Ashna_Helper::to_international_phone( $identifier, true );
		$intl_raw  = Ashna_Helper::to_international_phone( $identifier, false );
		$no_zero   = ltrim( $identifier, '0' );

		$lookups[] = array(
			'relation' => 'OR',
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
		);

		return $lookups;
	}

	/**
	 * Whether the Digits phone keys may exist in this database.
	 *
	 * True while Digits is active, and afterwards true for any site where the one-time
	 * activation check (Ashna_Activator::ensure_digits_meta_flag()) found Digits rows — so an
	 * account whose phone only lives in Digits meta is never lost. While the flag is unknown
	 * (an install that has not run the check yet) the keys are treated as present, which is
	 * exactly the pre-change behaviour.
	 *
	 * @return bool
	 */
	private static function digits_meta_reachable() {
		if ( class_exists( 'Digits' ) ) {
			if ( 'yes' !== get_option( self::DIGITS_META_OPTION ) ) {
				// Digits is here now: remember it, so the keys stay reachable if it is removed.
				update_option( self::DIGITS_META_OPTION, 'yes', true );
			}
			return true;
		}

		$flag = get_option( self::DIGITS_META_OPTION, null );
		return null === $flag || 'yes' === $flag;
	}

	/**
	 * User IDs for several candidate logins in ONE query.
	 *
	 * Same matching as a get_user_by( 'login', … ) loop (the candidate is sanitized the same way
	 * and the user object caches are filled the same way), but a new phone number costs one query
	 * instead of one per candidate — and the result order follows the candidate order, as before.
	 *
	 * @param string[] $candidate_logins Candidate logins, most likely first.
	 * @return int[]
	 */
	private static function user_ids_by_logins( array $candidate_logins ) {
		global $wpdb;

		$logins = array();
		foreach ( array_unique( $candidate_logins ) as $candidate_login ) {
			$sanitized = sanitize_user( (string) $candidate_login );
			if ( '' !== $sanitized ) {
				$logins[ $sanitized ] = true;
			}
		}
		if ( empty( $logins ) ) {
			return array();
		}

		$placeholders = implode( ', ', array_fill( 0, count( $logins ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->users} WHERE user_login IN ( {$placeholders} )",
				array_keys( $logins )
			)
		);

		$by_login = array();
		foreach ( (array) $rows as $row ) {
			if ( function_exists( 'update_user_caches' ) ) {
				update_user_caches( $row );
			}
			$by_login[ (string) $row->user_login ] = (int) $row->ID;
		}

		$ids = array();
		foreach ( array_unique( $candidate_logins ) as $candidate_login ) {
			$sanitized = sanitize_user( (string) $candidate_login );
			if ( '' !== $sanitized && isset( $by_login[ $sanitized ] ) ) {
				$ids[] = $by_login[ $sanitized ];
			}
		}

		return $ids;
	}

	/**
	 * Candidate IDs from unverified profile data.
	 *
	 * @param string $identifier Normalized phone.
	 * @return int[]
	 */
	private static function unverified_phone_candidates( $identifier ) {
		$intl_plus = Ashna_Helper::to_international_phone( $identifier, true );
		$intl_raw  = Ashna_Helper::to_international_phone( $identifier, false );
		$no_zero   = ltrim( $identifier, '0' );

		$ids = self::user_ids_by_meta(
			array(
				'relation' => 'OR',
				array(
					'key'     => 'billing_phone',
					'value'   => array( $identifier, $intl_plus, $intl_raw, $no_zero ),
					'compare' => 'IN',
				),
				array(
					'key'     => 'mobile',
					'value'   => array( $identifier, $intl_plus, $no_zero ),
					'compare' => 'IN',
				),
			),
			5
		);

		$login_candidates = array( $identifier, $intl_raw, $intl_plus, $no_zero, 'u_' . $identifier );
		$custom_prefix    = sanitize_key( Ashna_Helper::get_option( 'username_prefix', '' ) );
		if ( ! empty( $custom_prefix ) ) {
			$login_candidates[] = $custom_prefix . $identifier;
		}
		$ids = array_merge( $ids, self::user_ids_by_logins( $login_candidates ) );

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Whether the account already has a different verified phone.
	 *
	 * @param int    $user_id    User ID.
	 * @param string $identifier Normalized phone.
	 * @return bool
	 */
	private static function has_other_verified_phone( $user_id, $identifier ) {
		$verified = (string) get_user_meta( $user_id, 'ashna_phone', true );
		if ( '' !== $verified ) {
			return Ashna_Helper::normalize_phone( $verified ) !== $identifier;
		}
		$digits = (string) get_user_meta( $user_id, 'digits_phone', true );
		if ( '' !== $digits ) {
			return Ashna_Helper::normalize_phone( $digits ) !== $identifier;
		}
		return false;
	}

	/**
	 * User IDs matching a meta query.
	 *
	 * @param array $meta_query Meta query.
	 * @param int   $limit      Max IDs.
	 * @return int[]
	 */
	private static function user_ids_by_meta( $meta_query, $limit = 2 ) {
		$ids = get_users(
			array(
				'meta_query'  => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'number'      => $limit,
				'fields'      => 'ID',
				'count_total' => false,
			)
		);
		return array_values( array_map( 'absint', (array) $ids ) );
	}

	/**
	 * Error for a phone shared by several accounts.
	 *
	 * @return WP_Error
	 */
	private static function ambiguous_error() {
		return new WP_Error( 'ashna_ambiguous_account', 'این شماره به بیش از یک حساب کاربری متصل است. لطفاً با ایمیل یا رمز عبور وارد شوید یا با پشتیبانی سایت تماس بگیرید.' );
	}

	/**
	 * Synchronize user phone number across Ashna, WooCommerce, and Digits meta keys
	 * Ensures 100% forward & backward compatibility even if plugins are switched in the future
	 *
	 * @param int    $user_id    WordPress User ID.
	 * @param string $identifier Normalized phone (09XXXXXXXXX).
	 */
	public static function sync_user_phone_meta( $user_id, $identifier ) {
		// 1. Ashna native meta
		update_user_meta( $user_id, 'ashna_phone', $identifier );

		// 2. WooCommerce native billing_phone
		if ( ! get_user_meta( $user_id, 'billing_phone', true ) ) {
			update_user_meta( $user_id, 'billing_phone', $identifier );
		}

		// 3. Digits native meta keys (for seamless cross-plugin compatibility). Skipped on sites
		// where those keys can never be read back (no Digits, none in the database) — that is six
		// meta reads/writes less per registration, with no account finding phones any differently.
		if ( ! self::digits_meta_reachable() ) {
			return;
		}

		$intl_plus = Ashna_Helper::to_international_phone( $identifier, true ); // +989123456789
		$no_zero   = ltrim( $identifier, '0' );                                 // 9123456789

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
		/**
		 * Fires before Ashna creates an account (integrations prepare their own sign-up here).
		 *
		 * @param string $identifier Normalized phone/email.
		 * @param string $type       phone|email.
		 * @param array  $extra      Sign-up data.
		 */
		do_action( 'ashna_otp_before_register_user', $identifier, $type, $extra );
		try {
			$result = self::create_account( $identifier, $type, $extra );
		} finally {
			/**
			 * Fires after the account creation attempt, also when it failed.
			 *
			 * @param WP_User|WP_Error $result     New user or error.
			 * @param string           $identifier Normalized phone/email.
			 * @param string           $type       phone|email.
			 * @param array            $extra      Sign-up data.
			 */
			do_action( 'ashna_otp_after_register_user', isset( $result ) ? $result : new WP_Error( 'ashna_register_failed', '' ), $identifier, $type, $extra );
		}
		return $result;
	}

	/**
	 * Create the account (see register_user()).
	 *
	 * @param string $identifier Normalized phone or email.
	 * @param string $type       'phone' or 'email'.
	 * @param array  $extra      Extra profile fields.
	 * @return WP_User|WP_Error
	 */
	private static function create_account( $identifier, $type, $extra ) {
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

		$desired_role = self::safe_registration_role( Ashna_Helper::get_option( 'default_user_role', 'customer' ) );

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

		$woocommerce = class_exists( 'WooCommerce' );
		if ( $woocommerce ) {
			// Same customer pipeline as WooCommerce's own registration (wc_create_new_customer()).
			$userdata         = (array) apply_filters( 'woocommerce_new_customer_data', $userdata );
			$userdata['role'] = self::safe_registration_role( isset( $userdata['role'] ) ? $userdata['role'] : $desired_role );
		}

		// wp_insert_user() unslashes every field it stores (it expects request-style input), so slash
		// once; otherwise a backslash typed in a name silently disappears.
		$user_id = wp_insert_user( wp_slash( $userdata ) );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		if ( 'phone' === $type ) {
			self::sync_user_phone_meta( $user_id, $identifier );
		}

		if ( ! empty( $first_name ) ) {
			update_user_meta( $user_id, 'billing_first_name', wp_slash( $first_name ) );
		}
		if ( ! empty( $last_name ) ) {
			update_user_meta( $user_id, 'billing_last_name', wp_slash( $last_name ) );
		}

		update_user_meta( $user_id, 'ashna_registered_via_otp', 1 );

		if ( $woocommerce ) {
			// Lets WooCommerce (new-account e-mail) and its extensions treat the account as a customer sign-up.
			do_action( 'woocommerce_created_customer', $user_id, $userdata, true );
		}

		do_action( 'ashna_otp_user_registered', $user_id, $identifier, $type, $extra );

		return get_user_by( 'id', $user_id );
	}

	/**
	 * Role for accounts created by Ashna. Roles with staff capabilities are refused even if
	 * selected by mistake (or injected through a settings import).
	 *
	 * @param string $role Requested role.
	 * @return string
	 */
	public static function safe_registration_role( $role ) {
		$roles = wp_roles();
		$role  = sanitize_key( (string) $role );
		if ( 'customer' === $role && ! $roles->is_role( 'customer' ) ) {
			$role = sanitize_key( (string) get_option( 'default_role', 'subscriber' ) );
		}

		$role = (string) apply_filters( 'ashna_otp_registration_role', $role );

		$dangerous = array( 'manage_options', 'edit_users', 'create_users', 'promote_users', 'delete_users', 'activate_plugins', 'install_plugins', 'edit_plugins', 'edit_theme_options', 'manage_woocommerce', 'edit_others_posts', 'unfiltered_html', 'manage_network' );
		$role_obj  = $roles->get_role( $role );
		$is_safe   = (bool) $role_obj;
		if ( $role_obj ) {
			foreach ( $dangerous as $cap ) {
				if ( $role_obj->has_cap( $cap ) ) {
					$is_safe = false;
					break;
				}
			}
		}

		if ( $is_safe ) {
			return $role;
		}
		return $roles->is_role( 'customer' ) ? 'customer' : 'subscriber';
	}
}
// phpcs:enable WordPress.Security.NonceVerification.Missing
