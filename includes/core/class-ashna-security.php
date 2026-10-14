<?php
/**
 * Security, Firewall, Captcha, Rate Limiting & OTP Verification Engine
 *
 * @package Ashna_OTP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Security {

	/**
	 * Check if a recipient or IP is whitelisted
	 *
	 * @param string $recipient Normalized phone or email.
	 * @param string $ip        Client IP.
	 * @return bool
	 */
	public static function is_whitelisted( $recipient, $ip = '' ) {
		if ( empty( $ip ) ) {
			$ip = Ashna_Helper::get_client_ip();
		}
		$whitelist = Ashna_Helper::parse_list( Ashna_Helper::get_option( 'whitelisted_identifiers', '' ) );
		if ( empty( $whitelist ) ) {
			return false;
		}
		return in_array( $recipient, $whitelist, true ) || in_array( $ip, $whitelist, true );
	}

	/**
	 * Check if recipient or IP is blacklisted in Firewall
	 *
	 * @param string $recipient Normalized phone or email.
	 * @param string $ip        Client IP.
	 * @return true|WP_Error
	 */
	public static function check_blacklist( $recipient, $ip = '' ) {
		if ( empty( $ip ) ) {
			$ip = Ashna_Helper::get_client_ip();
		}

		// Check IP Blacklist
		$blocked_ips = Ashna_Helper::parse_list( Ashna_Helper::get_option( 'blocked_ips', '' ) );
		foreach ( $blocked_ips as $rule_ip ) {
			if ( $ip === $rule_ip || ( strpos( $rule_ip, '*' ) !== false && fnmatch( $rule_ip, $ip ) ) ) {
				return new WP_Error( 'ashna_blocked_ip', 'دسترسی آی‌پی شما توسط دیوار آتش سایت مسدود شده است.' );
			}
		}

		// Check Phone / Identifier Blacklist
		$blocked_phones = Ashna_Helper::parse_list( Ashna_Helper::get_option( 'blocked_phones', '' ) );
		foreach ( $blocked_phones as $rule ) {
			if ( $recipient === $rule || ( strpos( $rule, '*' ) !== false && fnmatch( $rule, $recipient ) ) || strpos( $recipient, $rule ) === 0 ) {
				return new WP_Error( 'ashna_blocked_recipient', 'امکان ارسال کد به این شماره/شناسه وجود ندارد (مسدود شده).' );
			}
		}

		return true;
	}

	/**
	 * Generate a stateless HMAC Math Captcha challenge
	 *
	 * @return array { question: string, token: string }
	 */
	public static function generate_math_captcha() {
		$a      = wp_rand( 2, 9 );
		$b      = wp_rand( 1, 9 );
		$answer = (string) ( $a + $b );
		$ts     = time();
		$sig    = hash_hmac( 'sha256', $answer . '|' . $ts, wp_salt( 'auth' ) );

		return array(
			'question' => sprintf( 'حاصل جمع %d + %d چند می‌شود؟', $a, $b ),
			'token'    => $ts . '.' . $sig,
		);
	}

	/**
	 * Verify Captcha (Math / Arcaptcha / reCAPTCHA v3 / Cloudflare Turnstile)
	 *
	 * @param array $post_data Submitted POST data.
	 * @return true|WP_Error
	 */
	public static function verify_captcha( $post_data ) {
		$captcha_type = Ashna_Helper::get_option( 'captcha_type', 'none' );
		if ( 'none' === $captcha_type || empty( $captcha_type ) ) {
			return true;
		}

		// 1. Internal Math Captcha
		if ( 'math' === $captcha_type ) {
			$answer = isset( $post_data['captcha_answer'] ) ? Ashna_Helper::convert_digits( sanitize_text_field( $post_data['captcha_answer'] ) ) : '';
			$token  = isset( $post_data['captcha_token'] ) ? sanitize_text_field( $post_data['captcha_token'] ) : '';

			if ( '' === $answer || empty( $token ) ) {
				return new WP_Error( 'ashna_captcha_empty', 'لطفاً به سوال امنیتی (کپچا) پاسخ دهید.' );
			}

			if ( strpos( $token, '.' ) === false ) {
				return new WP_Error( 'ashna_captcha_invalid', 'توکن امنیتی کپچا نامعتبر است.' );
			}

			list( $ts, $sig ) = explode( '.', $token, 2 );
			if ( time() - (int) $ts > 900 ) {
				return new WP_Error( 'ashna_captcha_expired', 'سوال امنیتی منقضی شده است. لطفاً دوباره تلاش کنید.' );
			}

			$expected_sig = hash_hmac( 'sha256', $answer . '|' . $ts, wp_salt( 'auth' ) );
			if ( ! hash_equals( $expected_sig, $sig ) ) {
				return new WP_Error( 'ashna_captcha_wrong', 'پاسخ سوال امنیتی (کپچا) اشتباه است.' );
			}

			return true;
		}

		// 2. Arcaptcha (آرکپچا - arcaptcha.ir)
		$site_key   = trim( (string) Ashna_Helper::get_option( 'captcha_site_key', '' ) );
		$secret_key = trim( (string) Ashna_Helper::get_option( 'captcha_secret_key', '' ) );
		$user_token = isset( $post_data['captcha_token'] ) ? sanitize_text_field( $post_data['captcha_token'] ) : '';
		if ( empty( $user_token ) && isset( $post_data['arcaptcha-token'] ) ) {
			$user_token = sanitize_text_field( $post_data['arcaptcha-token'] );
		}

		if ( empty( $secret_key ) ) {
			return true; // Skip if admin hasn't entered secret key yet
		}

		if ( empty( $user_token ) ) {
			return new WP_Error( 'ashna_captcha_missing', 'لطفاً تیک امنیتی کپچا (من ربات نیستم) را تکمیل کنید.' );
		}

		if ( 'arcaptcha' === $captcha_type ) {
			$response = wp_remote_post(
				'https://api.arcaptcha.ir/arcaptcha/api/verify',
				array(
					'timeout' => 10,
					'headers' => array(
						'Content-Type' => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'challenge_id' => $user_token,
							'site_key'     => $site_key,
							'secret_key'   => $secret_key,
						)
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return true;
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( empty( $body['success'] ) ) {
				return new WP_Error( 'ashna_arcaptcha_failed', 'تایید امنیتی آرکپچا ناموفق بود. لطفاً دوباره تلاش کنید.' );
			}

			return true;
		}

		// 3. Google reCAPTCHA v3 or Cloudflare Turnstile
		$verify_url = 'turnstile' === $captcha_type
			? 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
			: 'https://www.google.com/recaptcha/api/siteverify';

		$response = wp_remote_post(
			$verify_url,
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $secret_key,
					'response' => $user_token,
					'remoteip' => Ashna_Helper::get_client_ip(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return true;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['success'] ) ) {
			return new WP_Error( 'ashna_captcha_failed', 'تایید امنیتی ضد ربات ناموفق بود.' );
		}

		return true;
	}

	/**
	 * Check if a recipient or IP can request a new OTP
	 *
	 * @param string $recipient Normalized phone or email.
	 * @return true|WP_Error
	 */
	public static function can_request_otp( $recipient ) {
		global $wpdb;

		$ip = Ashna_Helper::get_client_ip();

		$blacklist = self::check_blacklist( $recipient, $ip );
		if ( is_wp_error( $blacklist ) ) {
			return $blacklist;
		}

		if ( self::is_whitelisted( $recipient, $ip ) ) {
			return true;
		}

		$lockout_status = self::check_lockout( $recipient, $ip );
		if ( is_wp_error( $lockout_status ) ) {
			return $lockout_status;
		}

		// 1. Check resend cooldown
		$remaining_cooldown = self::get_resend_remaining( $recipient );
		if ( $remaining_cooldown > 0 ) {
			return new WP_Error(
				'ashna_cooldown',
				sprintf( 'لطفاً %d ثانیه دیگر برای درخواست مجدد کد صبر کنید.', $remaining_cooldown ),
				array( 'remaining' => $remaining_cooldown )
			);
		}

		$table        = Ashna_Logger::table_name();
		$one_hour_ago = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - HOUR_IN_SECONDS );

		// 2. Check hourly limit per recipient
		$max_recipient = max( 1, absint( Ashna_Helper::get_option( 'max_requests_per_hour', 5 ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$recipient_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE recipient = %s AND created_at >= %s",
				$recipient,
				$one_hour_ago
			)
		);

		if ( $recipient_count >= $max_recipient ) {
			return new WP_Error(
				'ashna_rate_limit_recipient',
				'تعداد درخواست‌های کد برای این شماره/ایمیل بیش از حد مجاز در یک ساعت گذشته است. لطفاً کمی بعد تلاش کنید.'
			);
		}

		// 3. Check hourly limit per IP
		$max_ip = max( 2, absint( Ashna_Helper::get_option( 'max_ip_requests_per_hour', 15 ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ip_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE ip_address = %s AND created_at >= %s",
				$ip,
				$one_hour_ago
			)
		);

		if ( $ip_count >= $max_ip ) {
			return new WP_Error(
				'ashna_rate_limit_ip',
				'تعداد درخواست‌های ارسالی از آی‌پی شما بیش از حد مجاز است. لطفاً یک ساعت دیگر تلاش کنید.'
			);
		}

		return true;
	}

	/**
	 * Calculate remaining seconds before a new OTP can be requested
	 *
	 * @param string $recipient Normalized phone or email.
	 * @return int Remaining seconds (0 if allowed now).
	 */
	public static function get_resend_remaining( $recipient ) {
		$latest = Ashna_Logger::get_latest_record( $recipient );
		if ( ! $latest || 'failed' === $latest->status ) {
			return 0;
		}

		$cooldown = absint( Ashna_Helper::get_option( 'resend_cooldown', 60 ) );
		$created  = strtotime( $latest->created_at );
		$now      = strtotime( current_time( 'mysql' ) );
		$elapsed  = $now - $created;

		if ( $elapsed < $cooldown ) {
			return max( 0, $cooldown - $elapsed );
		}

		return 0;
	}

	/**
	 * Check if recipient or IP is temporarily locked out due to brute-force attempts
	 *
	 * @param string $recipient Recipient.
	 * @param string $ip        IP address.
	 * @return true|WP_Error
	 */
	public static function check_lockout( $recipient, $ip = '' ) {
		if ( empty( $ip ) ) {
			$ip = Ashna_Helper::get_client_ip();
		}

		if ( self::is_whitelisted( $recipient, $ip ) ) {
			return true;
		}

		$recipient_lock = get_transient( Ashna_Storage::key( 'ashna_lock_' ) . md5( $recipient ) );
		$ip_lock        = get_transient( Ashna_Storage::key( 'ashna_lock_ip_' ) . md5( $ip ) );

		if ( $recipient_lock || $ip_lock ) {
			return new WP_Error(
				'ashna_locked_out',
				'به دلیل تلاش‌های ناموفق متعدد، دسترسی شما به صورت موقت مسدود شده است. لطفاً کمی بعد تلاش کنید.'
			);
		}

		return true;
	}

	/**
	 * Record a failed password attempt and trigger lockout if threshold reached
	 *
	 * @param string $identifier User identifier.
	 */
	public static function record_failed_password_attempt( $identifier ) {
		$key          = Ashna_Storage::key( 'ashna_pw_try_' ) . md5( $identifier );
		$attempts     = (int) get_transient( $key ) + 1;
		$max_attempts = max( 2, absint( Ashna_Helper::get_option( 'max_verify_attempts', 5 ) ) );

		set_transient( $key, $attempts, 15 * MINUTE_IN_SECONDS );

		if ( $attempts >= $max_attempts ) {
			delete_transient( $key );
			self::trigger_lockout( $identifier );
		}
	}

	/**
	 * Clear failed password attempts on successful login
	 *
	 * @param string $identifier User identifier.
	 */
	public static function clear_password_attempts( $identifier ) {
		delete_transient( Ashna_Storage::key( 'ashna_pw_try_' ) . md5( $identifier ) );
	}

	/**
	 * Lock out a recipient and IP temporarily and register in active lockouts list
	 *
	 * @param string $recipient Recipient.
	 */
	public static function trigger_lockout( $recipient ) {
		$duration = max( 60, absint( Ashna_Helper::get_option( 'lockout_duration', 900 ) ) );
		$ip       = Ashna_Helper::get_client_ip();

		set_transient( Ashna_Storage::key( 'ashna_lock_' ) . md5( $recipient ), 1, $duration );
		set_transient( Ashna_Storage::key( 'ashna_lock_ip_' ) . md5( $ip ), 1, $duration );

		$lockouts = get_option( Ashna_Storage::key( 'ashna_active_lockouts' ), array() );
		if ( ! is_array( $lockouts ) ) {
			$lockouts = array();
		}

		$now_ts                 = time();
		$lockouts[ $recipient ] = array(
			'target'     => $recipient,
			'ip'         => $ip,
			'locked_at'  => current_time( 'mysql' ),
			'expires_ts' => $now_ts + $duration,
		);

		update_option( Ashna_Storage::key( 'ashna_active_lockouts' ), $lockouts, false );
	}

	/**
	 * Get all currently active lockouts for Admin Firewall table
	 *
	 * @return array
	 */
	public static function get_active_lockouts() {
		$lockouts = get_option( Ashna_Storage::key( 'ashna_active_lockouts' ), array() );
		if ( ! is_array( $lockouts ) || empty( $lockouts ) ) {
			return array();
		}

		$now     = time();
		$active  = array();
		$changed = false;

		foreach ( $lockouts as $key => $info ) {
			if ( isset( $info['expires_ts'] ) && $info['expires_ts'] > $now ) {
				$info['remaining_sec'] = $info['expires_ts'] - $now;
				$active[ $key ]        = $info;
			} else {
				$changed = true;
			}
		}

		if ( $changed ) {
			update_option( Ashna_Storage::key( 'ashna_active_lockouts' ), $active, false );
		}

		return $active;
	}

	/**
	 * Unlock a specific target and its associated IP immediately
	 *
	 * @param string $target Recipient or key.
	 * @return bool
	 */
	public static function unlock_target( $target ) {
		$lockouts = get_option( Ashna_Storage::key( 'ashna_active_lockouts' ), array() );
		if ( ! is_array( $lockouts ) ) {
			$lockouts = array();
		}

		delete_transient( Ashna_Storage::key( 'ashna_lock_' ) . md5( $target ) );
		delete_transient( Ashna_Storage::key( 'ashna_pw_try_' ) . md5( $target ) );
		if ( isset( $lockouts[ $target ]['ip'] ) ) {
			delete_transient( Ashna_Storage::key( 'ashna_lock_ip_' ) . md5( $lockouts[ $target ]['ip'] ) );
		}

		unset( $lockouts[ $target ] );
		update_option( Ashna_Storage::key( 'ashna_active_lockouts' ), $lockouts, false );
		return true;
	}

	/**
	 * Verify submitted OTP code for a recipient
	 *
	 * @param string $recipient Normalized phone or email.
	 * @param string $code      Submitted OTP code.
	 * @return true|WP_Error
	 */
	public static function verify_otp( $recipient, $code ) {
		$code = Ashna_Helper::convert_digits( $code );
		$code = preg_replace( '/[^0-9]/', '', $code );

		if ( empty( $code ) ) {
			return new WP_Error( 'ashna_empty_code', 'لطفاً کد تایید را وارد کنید.' );
		}

		$lockout = self::check_lockout( $recipient );
		if ( is_wp_error( $lockout ) ) {
			return $lockout;
		}

		$record = Ashna_Logger::get_latest_record( $recipient );
		if ( ! $record ) {
			return new WP_Error( 'ashna_no_otp', 'کد فعالی برای این شماره/ایمیل یافت نشد. لطفاً مجدداً درخواست کد دهید.' );
		}

		if ( 'verified' === $record->status ) {
			return new WP_Error( 'ashna_already_used', 'این کد قبلاً استفاده شده است. لطفاً کد جدید دریافت کنید.' );
		}

		if ( 'sent' !== $record->status ) {
			return new WP_Error( 'ashna_invalid_state', 'کد تایید منقضی یا نامعتبر شده است. لطفاً مجدداً درخواست کد دهید.' );
		}

		$now        = strtotime( current_time( 'mysql' ) );
		$expires_at = strtotime( $record->expires_at );

		if ( $now > $expires_at ) {
			Ashna_Logger::update( $record->id, array( 'status' => 'expired' ) );
			return new WP_Error( 'ashna_expired_code', 'مهلت استفاده از این کد به پایان رسیده است. لطفاً کد جدید دریافت کنید.' );
		}

		$max_attempts = max( 1, absint( Ashna_Helper::get_option( 'max_verify_attempts', 5 ) ) );
		if ( (int) $record->attempts >= $max_attempts ) {
			Ashna_Logger::update( $record->id, array( 'status' => 'expired' ) );
			self::trigger_lockout( $recipient );
			return new WP_Error( 'ashna_max_attempts', 'تعداد تلاش‌های مجاز برای این کد به پایان رسید. لطفاً کمی بعد کد جدید دریافت کنید.' );
		}

		$is_valid = wp_check_password( $code, $record->otp_hash );

		if ( ! $is_valid ) {
			$new_attempts = (int) $record->attempts + 1;
			$update_data  = array( 'attempts' => $new_attempts );

			if ( $new_attempts >= $max_attempts ) {
				$update_data['status'] = 'expired';
				self::trigger_lockout( $recipient );
			}

			Ashna_Logger::update( $record->id, $update_data );

			$remaining_tries = max( 0, $max_attempts - $new_attempts );
			if ( 0 === $remaining_tries ) {
				return new WP_Error( 'ashna_max_attempts', 'کد وارد شده اشتباه است. به دلیل تلاش بیش از حد، کد باطل شد.' );
			}

			return new WP_Error(
				'ashna_wrong_code',
				sprintf( 'کد وارد شده صحیح نیست. (%d تلاش باقی‌مانده)', $remaining_tries )
			);
		}

		// Valid OTP! Mark record as verified
		Ashna_Logger::update(
			$record->id,
			array(
				'status'      => 'verified',
				'verified_at' => current_time( 'mysql' ),
				'otp_code'    => '****',
			)
		);

		return true;
	}
}
