<?php
/**
 * Abstract Base Gateway Class for Ashna
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Ashna_Abstract_Gateway implements Ashna_Gateway_Interface {

	/**
	 * Transport trace of the current send(): number of HTTP calls and the last call.
	 *
	 * @var array|null
	 */
	private static $trace = null;

	/**
	 * Whether the cURL connect-timeout hook is registered.
	 *
	 * @var bool
	 */
	private static $curl_hook_added = false;

	/**
	 * Start a fresh transport trace (called before each gateway attempt).
	 */
	public static function reset_trace() {
		self::$trace = array(
			'calls'     => 0,
			'last'      => null,
			'exhausted' => '',
		);
	}

	/**
	 * Transport trace of the last attempt.
	 *
	 * @return array|null
	 */
	public static function get_trace() {
		return self::$trace;
	}

	/**
	 * Delivery channel of this gateway (sms | bale | messenger | email).
	 * Used to keep a rejected number from cascading inside the same channel.
	 *
	 * @return string
	 */
	public function get_channel() {
		return 'sms';
	}

	/**
	 * Base URL checked by the background health probe (a HEAD request; any
	 * HTTP answer means reachable). Empty: the gateway is not probed.
	 *
	 * @return string
	 */
	public function get_probe_url() {
		return '';
	}

	/**
	 * Helper to read a setting option
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Default fallback.
	 * @return mixed
	 */
	protected function get_setting( $key, $default = '' ) {
		return Ashna_Helper::get_option( $key, $default );
	}

	/**
	 * POST through the WordPress HTTP API with fast-failover time limits.
	 *
	 * The timeout comes from Ashna_Failover (short per-gateway limit, capped by
	 * the remaining dispatch budget), so a hung provider cannot stall the visitor.
	 *
	 * @param string $url  Endpoint URL.
	 * @param array  $args wp_remote_post() arguments (any 'timeout' is replaced).
	 * @return array|WP_Error
	 */
	protected function remote_post( $url, $args = array() ) {
		if ( null === self::$trace ) {
			self::reset_trace();
		}

		$limits = Ashna_Failover::http_limits();
		if ( is_wp_error( $limits ) ) {
			$data                     = $limits->get_error_data();
			self::$trace['exhausted'] = isset( $data['ashna_error_type'] ) ? $data['ashna_error_type'] : 'timeout';
			return $limits;
		}

		self::ensure_curl_hook();

		$args                          = is_array( $args ) ? $args : array();
		$args['timeout']               = $limits['timeout'];
		$args['ashna_connect_timeout'] = $limits['connect'];

		$started  = microtime( true );
		$response = wp_remote_post( $url, $args );

		++self::$trace['calls'];
		self::$trace['last'] = array(
			'host'  => (string) wp_parse_url( $url, PHP_URL_HOST ), // Host only: some URLs embed API keys.
			'ms'    => (int) round( ( microtime( true ) - $started ) * 1000 ),
			'code'  => is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response ),
			'error' => is_wp_error( $response ) ? $response->get_error_message() : '',
		);

		return $response;
	}

	/**
	 * Register the connect-timeout hook once.
	 */
	public static function ensure_curl_hook() {
		if ( ! self::$curl_hook_added ) {
			add_action( 'http_api_curl', array( __CLASS__, 'apply_connect_timeout' ), 10, 3 );
			self::$curl_hook_added = true;
		}
	}

	/**
	 * Limit the connect phase (DNS + TCP + TLS) of Ashna's own requests, so an
	 * unreachable provider fails within seconds. WordPress only passes the total
	 * timeout to cURL; other requests on the site are not touched.
	 *
	 * @param resource|CurlHandle $handle cURL handle.
	 * @param array               $args   Parsed wp_remote_*() arguments.
	 * @param string              $url    Request URL.
	 */
	public static function apply_connect_timeout( $handle, $args, $url ) {
		if ( empty( $args['ashna_connect_timeout'] ) || ! defined( 'CURLOPT_CONNECTTIMEOUT_MS' ) ) {
			return;
		}
		$ms = max( 500, (int) round( (float) $args['ashna_connect_timeout'] * 1000 ) );
		curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT_MS, $ms ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- Only reachable from the http_api_curl hook.
	}

	/**
	 * Send a JSON POST HTTP request
	 *
	 * @param string $url     Endpoint URL.
	 * @param array  $payload Array to JSON-encode.
	 * @param array  $headers Optional additional headers.
	 * @return array|WP_Error { code: int, body: array|null, raw: string }
	 */
	protected function post_json( $url, $payload, $headers = array() ) {
		$default_headers = array(
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		);

		$response = $this->remote_post(
			$url,
			array(
				'headers' => array_merge( $default_headers, $headers ),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$raw = wp_remote_retrieve_body( $response );
		return array(
			'code' => (int) wp_remote_retrieve_response_code( $response ),
			'body' => json_decode( $raw, true ),
			'raw'  => $raw,
		);
	}
}
