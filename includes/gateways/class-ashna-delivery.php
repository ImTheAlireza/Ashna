<?php
/** Scoped HTTP budgets and recoverable gateway circuits. No parallel/billable retries. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Ashna_Delivery {
	private static $deadline = null;
	private static $attempt_deadline = null;
	private static $last_http_code = 0;

	public static function limit( $key, $default, $min, $max ) {
		return max( $min, min( $max, (int) Ashna_Helper::get_option( $key, $default ) ) );
	}

	/** The owner must end the scope in finally; nested SMS/Bale share one budget. */
	public static function begin() {
		if ( null !== self::$deadline ) { return false; }
		self::$deadline = microtime( true ) + self::limit( 'delivery_total_timeout', 9, 3, 30 );
		add_filter( 'http_request_args', array( __CLASS__, 'http_args' ), PHP_INT_MAX, 2 );
		add_filter( 'pre_http_request', array( __CLASS__, 'pre_http' ), PHP_INT_MAX, 3 );
		add_filter( 'http_response', array( __CLASS__, 'http_response' ), PHP_INT_MAX, 3 );
		return true;
	}

	public static function end( $owner ) {
		if ( ! $owner ) { return; }
		remove_filter( 'http_request_args', array( __CLASS__, 'http_args' ), PHP_INT_MAX );
		remove_filter( 'pre_http_request', array( __CLASS__, 'pre_http' ), PHP_INT_MAX );
		remove_filter( 'http_response', array( __CLASS__, 'http_response' ), PHP_INT_MAX );
		self::$deadline = self::$attempt_deadline = null;
	}

	private static function remaining() {
		$deadline = null !== self::$attempt_deadline ? min( self::$deadline, self::$attempt_deadline ) : self::$deadline;
		return $deadline - microtime( true );
	}

	public static function http_args( $args, $url ) {
		if ( null !== self::$attempt_deadline ) {
			$args['timeout'] = max( 0.01, min( isset( $args['timeout'] ) ? (float) $args['timeout'] : 15, self::remaining() ) );
			// Never let a redirect consume an additional full transport timeout.
			$args['redirection'] = 0;
		}
		return $args;
	}

	public static function pre_http( $pre, $args, $url ) {
		return null !== self::$attempt_deadline && self::remaining() <= 0.05 ? self::budget_error() : $pre;
	}

	public static function http_response( $response, $args, $url ) {
		if ( null !== self::$attempt_deadline ) { self::$last_http_code = (int) wp_remote_retrieve_response_code( $response ); }
		return $response;
	}

	private static function unhealthy( $error, $full_attempt ) {
		$code = $error->get_error_code();
		$data = $error->get_error_data();
		return ( self::$last_http_code >= 500 || 429 === self::$last_http_code )
			|| ( $full_attempt && ( 'http_request_failed' === $code || false !== strpos( $code, '_http_error' ) ) )
			|| ( is_array( $data ) && ! empty( $data['ashna_retryable'] ) );
	}

	private static function budget_error() {
		return new WP_Error( 'ashna_delivery_budget', 'زمان مجاز بررسی مسیرهای ارسال تمام شد. ارسال ممکن است در سرویس پیامک انجام شده باشد؛ کمی صبر کنید و از درخواست‌های پیاپی خودداری کنید.' );
	}

	/** Configuration changes isolate health state; never persist credentials or recipients. */
	private static function key( $id ) {
		return 'ashna_gw_' . substr( hash_hmac( 'sha256', $id . serialize( Ashna_Helper::get_settings() ), wp_salt( 'auth' ) ), 0, 32 );
	}

	/** Compare-delete avoids deleting another request's recovered half-open lease. */
	private static function release( $key, $token ) {
		global $wpdb;
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, $token ) );
		if ( $deleted ) { wp_cache_delete( $key, 'options' ); }
	}

	private static function probe( $key ) {
		$old = get_option( $key, '' );
		if ( $old && (int) $old <= time() ) { self::release( $key, $old ); }
		$token = ( time() + 45 ) . ':' . wp_generate_uuid4();
		return add_option( $key, $token, '', false ) ? $token : false;
	}

	/** Execute exactly one driver's send; supports built-in and registered WP HTTP drivers. */
	public static function attempt( $gateway, $recipient, $code, $forced = false ) {
		$owner = self::begin();
		$previous = self::$attempt_deadline;
		$lease = false;
		$key = self::key( $gateway->get_id() );
		$health = get_transient( $key );
		$health = is_array( $health ) ? $health : array( 'failures' => 0, 'until' => 0 );
		try {
			if ( self::remaining() <= 0.05 ) { return self::budget_error(); }
			if ( ! $forced && $health['until'] > time() ) {
				return new WP_Error( 'ashna_gateway_open', 'این مسیر ارسال موقتاً از زنجیره کنار گذاشته شده است.' );
			}
			if ( ! $forced && $health['until'] > 0 ) {
				$lease = self::probe( $key . '_probe' );
				if ( ! $lease ) { return new WP_Error( 'ashna_gateway_open', 'بررسی بازیابی این مسیر در حال انجام است.' ); }
			}
			$attempt_seconds = self::limit( 'delivery_attempt_timeout', 4, 1, 15 );
			$full_attempt = self::remaining() >= $attempt_seconds - 0.05;
			self::$last_http_code = 0;
			self::$attempt_deadline = min( null !== $previous ? $previous : self::$deadline, microtime( true ) + $attempt_seconds );
			try {
				$result = $gateway->send( $recipient, $code );
			} catch ( Throwable $error ) {
				// Never expose driver exception messages (they may contain credentials).
				$result = new WP_Error( 'ashna_gateway_exception', 'اجرای درگاه ارسال با خطا مواجه شد.', array( 'ashna_retryable' => true ) );
			}
			if ( true !== $result && ! is_wp_error( $result ) ) {
				$result = new WP_Error( 'ashna_gateway_invalid_result', 'پاسخ درگاه ارسال معتبر نیست.' );
			}
			if ( true === $result ) {
				delete_transient( $key );
			} elseif ( 'ashna_delivery_budget' !== $result->get_error_code() && self::unhealthy( $result, $full_attempt ) ) {
				++$health['failures'];
				if ( $lease || $health['failures'] >= self::limit( 'delivery_failure_threshold', 2, 1, 10 ) ) {
					$health['until'] = time() + self::limit( 'delivery_circuit_seconds', 60, 10, 600 );
				}
				set_transient( $key, $health, max( 120, self::limit( 'delivery_circuit_seconds', 60, 10, 600 ) + 120 ) );
			}
			return $result;
		} finally {
			if ( $lease ) { self::release( $key . '_probe', $lease ); }
			self::$attempt_deadline = $previous;
			self::end( $owner );
		}
	}
}
