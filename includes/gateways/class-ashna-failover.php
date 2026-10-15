<?php
/**
 * Fast Failover Policy: per-call timeouts, a total time budget, a circuit
 * breaker that sidelines failing gateways, error classification and health stats.
 *
 * Why: a gateway that hangs used to block the visitor for 15 seconds per HTTP
 * call (up to a minute across the chain). Now the first visitor waits at most
 * one short timeout, and later visitors skip the failing gateway entirely.
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Ashna_Failover {

	/** Option holding per-gateway health (not autoloaded). */
	const OPTION = 'ashna_otp_gateway_health';

	/** Longest cooldown after repeated failures, in seconds. */
	const MAX_COOLDOWN = 1800;

	/** Time limit for the trial call after a cooldown, and for last-resort calls. */
	const TRIAL_TIMEOUT = 3.0;

	/** Limit for the connect phase (DNS + TCP + TLS) of each call. */
	const CONNECT_TIMEOUT = 3.0;

	/** A new gateway attempt needs at least this much of the budget left. */
	const MIN_ATTEMPT = 1.0;

	/**
	 * Error types that mean the gateway itself is down or unusable. Only these
	 * sideline a gateway; recipient-specific errors (e.g. "no Bale account")
	 * must never take a healthy gateway out of service for everyone.
	 *
	 * @var string[]
	 */
	const OUTAGE_TYPES = array( 'timeout', 'connect', 'network', 'server', 'auth', 'account' );

	/**
	 * Absolute microtime() deadline of the dispatch in progress.
	 *
	 * @var float|null
	 */
	private static $deadline = null;

	/**
	 * Absolute microtime() deadline of the gateway attempt in progress.
	 *
	 * @var float|null
	 */
	private static $attempt_deadline = null;

	/**
	 * Per-request copy of the stored health data.
	 *
	 * @var array|null
	 */
	private static $health = null;

	/* ---------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------- */

	/**
	 * Maximum seconds to wait for one gateway (setting, 2–15).
	 *
	 * @return float
	 */
	public static function gateway_timeout() {
		$seconds = (float) Ashna_Helper::get_option( 'gateway_timeout', 5 );
		return max( 2.0, min( 15.0, $seconds > 0 ? $seconds : 5.0 ) );
	}

	/**
	 * Base cooldown for a failing gateway, in seconds (setting in minutes, 1–60).
	 *
	 * @return int
	 */
	public static function cooldown() {
		$minutes = absint( Ashna_Helper::get_option( 'gateway_cooldown', 5 ) );
		return max( 1, min( 60, $minutes > 0 ? $minutes : 5 ) ) * 60;
	}

	/**
	 * Total seconds one OTP dispatch may spend across the whole chain.
	 *
	 * @return float
	 */
	public static function total_budget() {
		$budget = max( 8.0, min( 20.0, self::gateway_timeout() * 3 ) );

		/**
		 * Filters the total time budget (seconds) for delivering one OTP.
		 *
		 * @param float $budget Default: 3 × gateway timeout, clamped to 8–20 seconds.
		 */
		return max( 2.0, (float) apply_filters( 'ashna_otp_failover_budget', $budget ) );
	}

	/* ---------------------------------------------------------------------
	 * Time budget for the running dispatch
	 * ------------------------------------------------------------------- */

	/**
	 * Start the time budget of one OTP dispatch.
	 */
	public static function begin() {
		self::$deadline         = microtime( true ) + self::total_budget();
		self::$attempt_deadline = null;
	}

	/**
	 * Clear the dispatch budget.
	 */
	public static function end() {
		self::$deadline         = null;
		self::$attempt_deadline = null;
	}

	/**
	 * Seconds left in the dispatch budget, or null outside a dispatch.
	 *
	 * @return float|null
	 */
	public static function remaining() {
		return null === self::$deadline ? null : self::$deadline - microtime( true );
	}

	/**
	 * Whether there is enough budget left to start another gateway.
	 *
	 * @return bool
	 */
	public static function has_time_for_attempt() {
		$remaining = self::remaining();
		return null === $remaining || $remaining >= self::MIN_ATTEMPT;
	}

	/**
	 * Give the next gateway attempt its own time slot. All HTTP calls the
	 * gateway makes (e.g. Bale: token + send + retry) share this slot.
	 *
	 * @param float $seconds Attempt time limit.
	 */
	public static function start_attempt( $seconds ) {
		$seconds   = (float) $seconds;
		$remaining = self::remaining();
		if ( null !== $remaining ) {
			$seconds = min( $seconds, $remaining );
		}
		self::$attempt_deadline = microtime( true ) + max( 0.5, $seconds );
	}

	/**
	 * Close the attempt time slot.
	 */
	public static function end_attempt() {
		self::$attempt_deadline = null;
	}

	/**
	 * Timeouts for the next HTTP call made by a gateway.
	 *
	 * @return array{timeout: float, connect: float}|WP_Error WP_Error when no time is left.
	 */
	public static function http_limits() {
		$now   = microtime( true );
		$limit = null !== self::$attempt_deadline ? self::$attempt_deadline - $now : self::gateway_timeout();
		$type  = 'timeout';

		if ( null !== self::$deadline && self::$deadline - $now < $limit ) {
			$limit = self::$deadline - $now;
			$type  = 'budget';
		}

		if ( $limit < 0.25 ) {
			return new WP_Error(
				'ashna_time_exhausted',
				'budget' === $type ? 'مهلت ارسال کد به پایان رسید.' : 'درگاه در مهلت تعیین‌شده پاسخ نداد.',
				array( 'ashna_error_type' => $type )
			);
		}

		$limit = max( 0.5, $limit );
		return array(
			'timeout' => round( $limit, 2 ),
			'connect' => round( min( self::CONNECT_TIMEOUT, $limit ), 2 ),
		);
	}

	/* ---------------------------------------------------------------------
	 * Circuit breaker & health stats
	 * ------------------------------------------------------------------- */

	/**
	 * Empty health record.
	 *
	 * @return array
	 */
	private static function blank() {
		return array(
			'open_until' => 0,  // Unix time; > 0 while the gateway is sidelined.
			'opens'      => 0,  // Consecutive openings (cooldown backoff exponent).
			'streak'     => 0,  // Consecutive failed attempts.
			'ok'         => 0,
			'fail'       => 0,
			'last_ok'    => 0,
			'last_fail'  => 0,
			'last_ms'    => 0,
			'avg_ms'     => 0,
			'last_type'  => '',
			'last_error' => '',
		);
	}

	/**
	 * All stored health records.
	 *
	 * @return array<string, array>
	 */
	private static function load() {
		if ( null === self::$health ) {
			$stored       = get_option( self::OPTION, array() );
			self::$health = is_array( $stored ) ? $stored : array();
		}
		return self::$health;
	}

	/**
	 * Store one gateway record.
	 *
	 * @param string $id  Gateway ID.
	 * @param array  $row Health record.
	 */
	private static function put( $id, $row ) {
		self::load();
		self::$health[ $id ] = $row;
		update_option( self::OPTION, self::$health, false );
	}

	/**
	 * Health record of one gateway.
	 *
	 * @param string $id Gateway ID.
	 * @return array
	 */
	public static function get( $id ) {
		$all = self::load();
		return isset( $all[ $id ] ) && is_array( $all[ $id ] ) ? array_merge( self::blank(), $all[ $id ] ) : self::blank();
	}

	/**
	 * Circuit state: 'closed' (normal), 'open' (skip) or 'trial' (cooldown over; one quick try).
	 *
	 * @param string $id Gateway ID.
	 * @return string
	 */
	public static function state( $id ) {
		$row = self::get( $id );
		if ( empty( $row['open_until'] ) ) {
			return 'closed';
		}
		return (int) $row['open_until'] > time() ? 'open' : 'trial';
	}

	/**
	 * Reserve the trial call so concurrent visitors keep skipping the gateway meanwhile.
	 *
	 * @param string $id Gateway ID.
	 */
	public static function claim_trial( $id ) {
		$row               = self::get( $id );
		$row['open_until'] = time() + (int) ceil( self::TRIAL_TIMEOUT ) + 10;
		self::put( $id, $row );
	}

	/**
	 * Record a successful delivery: closes the circuit.
	 *
	 * @param string $id Gateway ID.
	 * @param int    $ms Duration in milliseconds.
	 */
	public static function record_success( $id, $ms ) {
		$row               = self::get( $id );
		$ms                = max( 0, (int) $ms );
		$row['open_until'] = 0;
		$row['opens']      = 0;
		$row['streak']     = 0;
		$row['ok']         = (int) $row['ok'] + 1;
		$row['last_ok']    = time();
		$row['last_ms']    = $ms;
		$row['avg_ms']     = (int) $row['avg_ms'] > 0 ? (int) round( $row['avg_ms'] * 0.7 + $ms * 0.3 ) : $ms;
		self::put( $id, $row );
	}

	/**
	 * Record a failed attempt; opens the circuit for outage-type errors.
	 *
	 * @param string $id      Gateway ID.
	 * @param string $type    Error type from classify().
	 * @param string $message Error message.
	 * @param int    $ms      Duration in milliseconds.
	 * @return bool Whether the circuit is open now.
	 */
	public static function record_failure( $id, $type, $message, $ms ) {
		$row               = self::get( $id );
		$row['streak']     = (int) $row['streak'] + 1;
		$row['fail']       = (int) $row['fail'] + 1;
		$row['last_fail']  = time();
		$row['last_ms']    = max( 0, (int) $ms );
		$row['last_type']  = (string) $type;
		$row['last_error'] = self::shorten( $message );

		$opened = false;
		if ( self::opens_circuit( $type ) ) {
			$row['opens']      = min( 10, (int) $row['opens'] + 1 );
			$cooldown          = min( self::cooldown() * pow( 2, $row['opens'] - 1 ), max( self::cooldown(), self::MAX_COOLDOWN ) );
			$row['open_until'] = time() + (int) $cooldown;
			$opened            = true;
		}

		self::put( $id, $row );
		return $opened || ( ! empty( $row['open_until'] ) && (int) $row['open_until'] > time() );
	}

	/**
	 * Whether a failure should sideline the gateway (outage-type errors only).
	 *
	 * @param string $type Error type.
	 * @return bool
	 */
	public static function opens_circuit( $type ) {
		return in_array( $type, self::OUTAGE_TYPES, true );
	}

	/**
	 * Close every circuit (e.g. after the admin changes gateway settings). Stats are kept.
	 */
	public static function reset_circuits() {
		self::load();
		if ( empty( self::$health ) ) {
			return;
		}
		foreach ( self::$health as $id => $row ) {
			if ( is_array( $row ) ) {
				$row['open_until']   = 0;
				$row['opens']        = 0;
				$row['streak']       = 0;
				self::$health[ $id ] = $row;
			}
		}
		update_option( self::OPTION, self::$health, false );
	}

	/**
	 * Forget all health data.
	 */
	public static function reset_all() {
		self::$health = array();
		delete_option( self::OPTION );
	}

	/* ---------------------------------------------------------------------
	 * Error classification
	 * ------------------------------------------------------------------- */

	/**
	 * Classify a gateway error.
	 *
	 * Types: timeout, connect, network, server, auth, account (outages: the
	 * gateway is sidelined), api (provider rejected this request), config
	 * (missing settings, nothing was sent), input (the number itself was
	 * rejected: other gateways of the same channel are not tried), budget.
	 *
	 * Every type except input moves on to the next gateway.
	 *
	 * Gateways may declare the type explicitly with
	 * `new WP_Error( $code, $message, array( 'ashna_error_type' => 'input' ) )`.
	 *
	 * @param WP_Error   $error Gateway error.
	 * @param array|null $trace Transport trace of Ashna_Abstract_Gateway, or null for custom gateways.
	 * @return string
	 */
	public static function classify( $error, $trace ) {
		$data = $error->get_error_data();
		if ( is_array( $data ) && ! empty( $data['ashna_error_type'] ) ) {
			return sanitize_key( $data['ashna_error_type'] );
		}

		if ( is_array( $trace ) && ! empty( $trace['exhausted'] ) ) {
			return 'budget' === $trace['exhausted'] ? 'budget' : 'timeout';
		}

		$last = is_array( $trace ) && ! empty( $trace['last'] ) ? $trace['last'] : null;
		if ( null === $last ) {
			// Built-in gateway that made no HTTP call: settings are missing.
			// Custom gateway without a trace: infer from the message.
			return is_array( $trace ) ? 'config' : self::classify_transport_message( $error->get_error_message(), 'api' );
		}

		if ( '' !== (string) $last['error'] ) {
			return self::classify_transport_message( $last['error'], 'network' );
		}

		$status = (int) $last['code'];
		if ( $status >= 500 || 429 === $status || 408 === $status ) {
			return 'server';
		}
		if ( 401 === $status || 403 === $status ) {
			return 'auth';
		}
		return 'api';
	}

	/**
	 * Classify a transport (cURL / streams) error message.
	 *
	 * @param string $message  Error message.
	 * @param string $fallback Type when nothing matches.
	 * @return string
	 */
	private static function classify_transport_message( $message, $fallback ) {
		$text = strtolower( (string) $message );

		if ( preg_match( '/curl error (\d+)/', $text, $match ) ) {
			$curl = (int) $match[1];
			if ( 28 === $curl ) {
				return 'timeout';
			}
			if ( in_array( $curl, array( 5, 6, 7, 35, 52, 55, 56, 60, 77 ), true ) ) {
				return 'connect';
			}
			return 'network';
		}

		foreach ( array( 'timed out', 'timeout' ) as $needle ) {
			if ( false !== strpos( $text, $needle ) ) {
				return 'timeout';
			}
		}
		foreach ( array( 'could not resolve', 'resolve host', 'failed to connect', 'connection refused', "couldn't connect", 'network is unreachable', 'no route to host', 'connection reset' ) as $needle ) {
			if ( false !== strpos( $text, $needle ) ) {
				return 'connect';
			}
		}
		return $fallback;
	}

	/* ---------------------------------------------------------------------
	 * Human-readable output
	 * ------------------------------------------------------------------- */

	/**
	 * Persian label for an error type.
	 *
	 * @param string $type Error type.
	 * @return string
	 */
	public static function type_label( $type ) {
		$labels = array(
			'timeout' => 'پاسخ نداد (اتمام مهلت)',
			'connect' => 'در دسترس نبود',
			'network' => 'خطای شبکه',
			'server'  => 'خطای سرور درگاه',
			'auth'    => 'کلید یا دسترسی نامعتبر',
			'account' => 'مشکل حساب درگاه (اعتبار یا محدودیت)',
			'api'     => 'درخواست را رد کرد',
			'config'  => 'تنظیمات ناقص',
			'input'   => 'شماره را نپذیرفت',
			'budget'  => 'مهلت کل ارسال تمام شد',
		);
		return isset( $labels[ $type ] ) ? $labels[ $type ] : 'خطا';
	}

	/**
	 * Persian relative time ("۵ دقیقه پیش"-style, Latin digits like the rest of the panel).
	 * Not tied to the site language, so English-locale sites do not get mixed text.
	 *
	 * @param int $timestamp Unix time.
	 * @return string
	 */
	public static function ago( $timestamp ) {
		$diff = max( 0, time() - (int) $timestamp );
		if ( $diff < 60 ) {
			return 'چند ثانیه پیش';
		}
		if ( $diff < 3600 ) {
			return sprintf( '%d دقیقه پیش', (int) floor( $diff / 60 ) );
		}
		if ( $diff < 86400 ) {
			return sprintf( '%d ساعت پیش', (int) floor( $diff / 3600 ) );
		}
		return sprintf( '%d روز پیش', (int) floor( $diff / 86400 ) );
	}

	/**
	 * One-line trace of a delivery, e.g. for the logs.
	 *
	 * @param array $steps Steps recorded by Ashna_Gateway_Manager::send_via_chain().
	 * @return string
	 */
	public static function describe( array $steps ) {
		$parts = array();
		foreach ( $steps as $step ) {
			$title = isset( $step['title'] ) ? $step['title'] : $step['id'];
			$secs  = isset( $step['ms'] ) ? sprintf( '%.1fs', $step['ms'] / 1000 ) : '';

			switch ( $step['outcome'] ) {
				case 'ok':
					$parts[] = sprintf( '%s: موفق (%s)', $title, $secs );
					break;
				case 'skip':
					$parts[] = 'input' === ( isset( $step['type'] ) ? $step['type'] : '' )
						? sprintf( '%s: امتحان نشد، چون شماره رد شده بود', $title )
						: sprintf( '%s: رد شد، چون موقتاً کنار گذاشته شده', $title );
					break;
				case 'budget':
					$parts[] = sprintf( '%s: امتحان نشد (مهلت کل تمام شد)', $title );
					break;
				default:
					$detail = in_array( $step['type'], array( 'api', 'config', 'input' ), true ) && ! empty( $step['message'] )
						? ' – ' . self::shorten( $step['message'], 90 )
						: '';
					$parts[] = sprintf( '%s: %s (%s)%s', $title, self::type_label( $step['type'] ), $secs, $detail );
			}
		}
		// Numbered steps joined with «؛»: glyphs every Persian font has (arrows are often missing).
		$digits = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		foreach ( $parts as $i => $part ) {
			$parts[ $i ] = str_replace( range( 0, 9 ), $digits, (string) ( $i + 1 ) ) . ') ' . $part;
		}
		return implode( '؛ ', $parts );
	}

	/**
	 * Shorten an error message for storage and display.
	 *
	 * @param string $message Message.
	 * @param int    $length  Max characters.
	 * @return string
	 */
	private static function shorten( $message, $length = 160 ) {
		$message = wp_strip_all_tags( (string) $message );
		$message = trim( (string) preg_replace( '/\s+/u', ' ', $message ) );
		return wp_html_excerpt( $message, $length, '…' );
	}
}
