<?php
/**
 * Security, Firewall, Captcha, Rate Limiting & OTP Verification Engine
 *
 * @package Ashna
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
	 * Captcha the form must pass right now: the configured one, or the built-in math captcha
	 * when attack mode is on and nothing (usable) is configured.
	 *
	 * @return string none|math|arcaptcha|recaptcha_v3|turnstile
	 */
	public static function effective_captcha_type() {
		$type = (string) Ashna_Helper::get_option( 'captcha_type', 'none' );
		if ( in_array( $type, array( 'arcaptcha', 'recaptcha_v3', 'turnstile' ), true ) && '' === trim( (string) Ashna_Helper::get_option( 'captcha_secret_key', '' ) ) ) {
			$type = 'none'; // Not configured yet: behaves like no captcha.
		}
		if ( ( '' === $type || 'none' === $type ) && class_exists( 'Ashna_Rate_Limiter' ) && Ashna_Rate_Limiter::attack_mode_active() ) {
			return 'math';
		}
		return '' === $type ? 'none' : $type;
	}

	/**
	 * Whether the built-in math captcha is in use (configured or forced by attack mode).
	 *
	 * @return bool
	 */
	public static function math_captcha_active() {
		return 'math' === self::effective_captcha_type();
	}

	/**
	 * Generate a stateless HMAC Math Captcha challenge (single use, valid for one hour)
	 *
	 * @return array { question: string, token: string }
	 */
	public static function generate_math_captcha() {
		$a      = wp_rand( 11, 49 );
		$b      = wp_rand( 2, 9 );
		$answer = (string) ( $a + $b );
		$ts     = time();
		$rand   = bin2hex( random_bytes( 6 ) ); // two visitors in the same second must never share a token
		$sig    = hash_hmac( 'sha256', $answer . '|' . $ts . '|' . $rand, wp_salt( 'auth' ) );

		return array(
			'question' => sprintf( 'حاصل جمع %d + %d چند می‌شود؟', $a, $b ),
			'token'    => $ts . '.' . $rand . '.' . $sig,
		);
	}

	/**
	 * Mark a captcha token as used. Atomic: of two parallel requests with the same token,
	 * exactly one gets true.
	 *
	 * @param string $sig Token signature.
	 * @return bool True the first time.
	 */
	private static function consume_captcha_token( $sig ) {
		global $wpdb;
		$name = 'ashna_otp_cap_' . substr( preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $sig ) ), 0, 40 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, (string) ( time() + 2 * HOUR_IN_SECONDS ) ) );
		return 1 === (int) $inserted;
	}

	/**
	 * Delete used-captcha markers older than their token lifetime (daily cron).
	 */
	public static function purge_used_captcha_tokens() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %s", $wpdb->esc_like( 'ashna_otp_cap_' ) . '%', (string) time() ) );
	}

	/**
	 * Count a wrong/missing captcha for this IP; 10 within 10 minutes lock the IP.
	 */
	private static function captcha_strike() {
		$ip  = Ashna_Helper::get_client_ip();
		$key = 'ashna_capfail_' . md5( $ip );
		$n   = (int) get_transient( $key ) + 1;
		set_transient( $key, $n, 10 * MINUTE_IN_SECONDS );
		if ( $n >= 10 ) {
			delete_transient( $key );
			self::trigger_ip_lockout( $ip );
		}
	}

	/**
	 * Ticket that lets the same visitor (same IP) ask for a resend for the same number
	 * without solving the captcha again. Resends still obey every rate limit.
	 *
	 * @param string $recipient Normalized recipient.
	 * @return string
	 */
	public static function resend_ticket( $recipient ) {
		$ts = time();
		return $ts . '.' . hash_hmac( 'sha256', 'resend|' . $recipient . '|' . Ashna_Helper::get_client_ip() . '|' . $ts, wp_salt( 'nonce' ) );
	}

	/**
	 * Whether a resend ticket is valid for this recipient and visitor (15 minutes).
	 *
	 * @param string $ticket    Ticket.
	 * @param string $recipient Normalized recipient.
	 * @return bool
	 */
	public static function valid_resend_ticket( $ticket, $recipient ) {
		if ( ! is_string( $ticket ) || false === strpos( $ticket, '.' ) ) {
			return false;
		}
		list( $ts, $sig ) = explode( '.', $ticket, 2 );
		$ts               = (int) $ts;
		if ( $ts <= 0 || time() - $ts > 15 * MINUTE_IN_SECONDS || $ts > time() + MINUTE_IN_SECONDS ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', 'resend|' . $recipient . '|' . Ashna_Helper::get_client_ip() . '|' . $ts, wp_salt( 'nonce' ) );
		return hash_equals( $expected, (string) $sig );
	}

	/**
	 * Verify Captcha (Math / Arcaptcha / reCAPTCHA v3 / Cloudflare Turnstile)
	 *
	 * @param array $post_data Submitted POST data.
	 * @return true|WP_Error
	 */
	public static function verify_captcha( $post_data ) {
		$result = self::check_captcha( $post_data );
		if ( is_wp_error( $result ) ) {
			self::captcha_strike();
		}
		return $result;
	}

	/**
	 * Captcha check without side effects on failure.
	 *
	 * @param array $post_data Submitted POST data.
	 * @return true|WP_Error
	 */
	private static function check_captcha( $post_data ) {
		$captcha_type = self::effective_captcha_type();
		if ( 'none' === $captcha_type ) {
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

			$parts = explode( '.', $token );
			if ( 3 === count( $parts ) ) {
				list( $ts, $rand, $sig ) = $parts;
				$signed                  = $answer . '|' . $ts . '|' . $rand;
			} else {
				// Tokens issued before the per-token random part (cached pages, at most one hour old).
				list( $ts, $sig ) = explode( '.', $token, 2 );
				$signed           = $answer . '|' . $ts;
			}
			if ( time() - (int) $ts > HOUR_IN_SECONDS ) {
				return new WP_Error( 'ashna_captcha_expired', 'سوال امنیتی منقضی شده است. لطفاً دوباره تلاش کنید.' );
			}

			// Single use, right or wrong: a solved (or brute-forced) token cannot be replayed.
			if ( ! self::consume_captcha_token( $sig ) ) {
				return new WP_Error( 'ashna_captcha_used', 'این سوال امنیتی قبلاً استفاده شده است. لطفاً به سوال جدید پاسخ دهید.' );
			}

			$expected_sig = hash_hmac( 'sha256', $signed, wp_salt( 'auth' ) );
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
	 * Read-only check whether a recipient/IP may request a code now (backward-compatible API;
	 * the request flow itself reserves its slot atomically with Ashna_Rate_Limiter::reserve()).
	 *
	 * @param string $recipient Normalized phone or email.
	 * @return true|WP_Error
	 */
	public static function can_request_otp( $recipient ) {
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
		return Ashna_Rate_Limiter::evaluate( $recipient, $ip, Ashna_IP::subnet( $ip ), 0 );
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
		$created  = Ashna_Helper::local_ts( $latest->created_at );
		$now      = Ashna_Helper::local_now();
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

		$recipient_lock = get_transient( 'ashna_lock_' . md5( $recipient ) );
		$ip_lock        = get_transient( 'ashna_lock_ip_' . md5( $ip ) );

		if ( $recipient_lock || $ip_lock ) {
			return new WP_Error(
				'ashna_locked_out',
				'به دلیل تلاش‌های ناموفق متعدد، دسترسی شما به صورت موقت مسدود شده است. لطفاً کمی بعد تلاش کنید.'
			);
		}

		return true;
	}

	/**
	 * Lock key shared by every way of naming one account (phone, e-mail, username in any letter
	 * case), so switching identifiers does not reset the failed-password counter.
	 *
	 * @param WP_User $user Account.
	 * @return string
	 */
	public static function account_lock_key( $user ) {
		return 'user:' . strtolower( $user->user_login );
	}

	/**
	 * Record a failed password attempt and lock out when a threshold is reached.
	 *
	 * Counted three ways: the identifier typed, each account it may belong to, and the client IP
	 * (one address guessing across many accounts is locked after 4× the per-account limit).
	 *
	 * @param string   $identifier   Normalized phone/email or username.
	 * @param string[] $account_keys account_lock_key() of the matching accounts.
	 */
	public static function record_failed_password_attempt( $identifier, $account_keys = array() ) {
		$max_attempts = max( 2, absint( Ashna_Helper::get_option( 'max_verify_attempts', 5 ) ) );
		$targets      = array_unique( array_merge( array( (string) $identifier ), array_map( 'strval', (array) $account_keys ) ) );

		foreach ( $targets as $target ) {
			$key      = 'ashna_pw_try_' . md5( $target );
			$attempts = (int) get_transient( $key ) + 1;
			set_transient( $key, $attempts, 15 * MINUTE_IN_SECONDS );
			if ( $attempts >= $max_attempts ) {
				delete_transient( $key );
				self::trigger_lockout( $target );
			}
		}

		$ip = Ashna_Helper::get_client_ip();
		if ( '' === $ip || self::is_whitelisted( (string) $identifier, $ip ) ) {
			return;
		}
		$ip_key      = 'ashna_pw_try_ip_' . md5( $ip );
		$ip_attempts = (int) get_transient( $ip_key ) + 1;
		set_transient( $ip_key, $ip_attempts, 15 * MINUTE_IN_SECONDS );
		if ( $ip_attempts >= $max_attempts * 4 ) {
			delete_transient( $ip_key );
			self::trigger_ip_lockout( $ip );
		}
	}

	/**
	 * Clear failed password attempts on successful login
	 *
	 * @param string   $identifier   User identifier.
	 * @param string[] $account_keys account_lock_key() of the signed-in account.
	 */
	public static function clear_password_attempts( $identifier, $account_keys = array() ) {
		foreach ( array_merge( array( (string) $identifier ), (array) $account_keys ) as $target ) {
			delete_transient( 'ashna_pw_try_' . md5( $target ) );
		}
	}

	/**
	 * Lock out a recipient and IP temporarily and register in active lockouts list
	 *
	 * @param string $recipient Recipient.
	 */
	public static function trigger_lockout( $recipient ) {
		$duration = max( 60, absint( Ashna_Helper::get_option( 'lockout_duration', 900 ) ) );
		$ip       = Ashna_Helper::get_client_ip();

		set_transient( 'ashna_lock_' . md5( $recipient ), 1, $duration );
		set_transient( 'ashna_lock_ip_' . md5( $ip ), 1, $duration );

		self::remember_lockout(
			$recipient,
			array(
				'target'     => $recipient,
				'ip'         => $ip,
				'locked_at'  => current_time( 'mysql' ),
				'expires_ts' => time() + $duration,
			)
		);
	}

	/**
	 * Add an entry to the admin's lockout list (the transients do the blocking; this list only
	 * feeds the Security tab). Kept to the newest 500 entries so an attack cannot bloat it.
	 *
	 * @param string $key   Target key.
	 * @param array  $entry Lockout details.
	 */
	private static function remember_lockout( $key, array $entry ) {
		$lockouts = get_option( 'ashna_active_lockouts', array() );
		if ( ! is_array( $lockouts ) ) {
			$lockouts = array();
		}
		unset( $lockouts[ $key ] );
		$lockouts[ $key ] = $entry;
		if ( count( $lockouts ) > 500 ) {
			$now      = time();
			$lockouts = array_filter(
				$lockouts,
				static function ( $info ) use ( $now ) {
					return isset( $info['expires_ts'] ) && $info['expires_ts'] > $now;
				}
			);
			$lockouts = array_slice( $lockouts, -500, null, true );
		}
		update_option( 'ashna_active_lockouts', $lockouts, false );
	}

	/**
	 * Lock out an IP (e.g. repeated wrong captcha answers).
	 *
	 * @param string $ip IP.
	 */
	public static function trigger_ip_lockout( $ip ) {
		$duration = max( 60, absint( Ashna_Helper::get_option( 'lockout_duration', 900 ) ) );
		set_transient( 'ashna_lock_ip_' . md5( $ip ), 1, $duration );

		self::remember_lockout(
			$ip,
			array(
				'target'     => $ip,
				'ip'         => $ip,
				'locked_at'  => current_time( 'mysql' ),
				'expires_ts' => time() + $duration,
			)
		);
	}

	/**
	 * Get all currently active lockouts for Admin Firewall table
	 *
	 * @return array
	 */
	public static function get_active_lockouts() {
		$lockouts = get_option( 'ashna_active_lockouts', array() );
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
			update_option( 'ashna_active_lockouts', $active, false );
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
		$lockouts = get_option( 'ashna_active_lockouts', array() );
		if ( ! is_array( $lockouts ) ) {
			$lockouts = array();
		}

		delete_transient( 'ashna_lock_' . md5( $target ) );
		delete_transient( 'ashna_pw_try_' . md5( $target ) );
		if ( isset( $lockouts[ $target ]['ip'] ) ) {
			delete_transient( 'ashna_lock_ip_' . md5( $lockouts[ $target ]['ip'] ) );
		}

		unset( $lockouts[ $target ] );
		update_option( 'ashna_active_lockouts', $lockouts, false );
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

		$now        = Ashna_Helper::local_now();
		$expires_at = Ashna_Helper::local_ts( $record->expires_at );
		$expiry_sec = absint( Ashna_Helper::get_option( 'otp_expiry', 120 ) );

		// A code that would stay valid longer than the expiry setting was issued before the clock
		// went back (DST ended, or the site timezone was changed): treat it as expired too.
		if ( $now > $expires_at || $expires_at - $now > $expiry_sec + 5 ) {
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
