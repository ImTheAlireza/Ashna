<?php
/**
 * FarazSMS Gateway (Iran Payamak REST API: api.iranpayamak.com, pattern send)
 *
 * Current FarazSMS web service (docs.farazsms.com): Api-Key header, pattern
 * sends are instant and never queued, which suits OTP.
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Gateway_Farazsms extends Ashna_Abstract_Gateway {

	/** Pattern (template) send endpoint. */
	const PATTERN_URL = 'https://api.iranpayamak.com/ws/v1/sms/pattern';

	/**
	 * Get gateway ID
	 *
	 * @return string
	 */
	public function get_id() {
		return 'farazsms';
	}

	/**
	 * Get gateway title
	 *
	 * @return string
	 */
	public function get_title() {
		return 'فراز اس‌ام‌اس (FarazSMS)';
	}

	/**
	 * Host checked by the background health probe.
	 *
	 * @return string
	 */
	public function get_probe_url() {
		return 'https://api.iranpayamak.com/';
	}

	/**
	 * Send OTP via the FarazSMS pattern web service
	 *
	 * @param string $recipient Normalized phone number.
	 * @param string $code      OTP code.
	 * @return true|WP_Error
	 */
	public function send( $recipient, $code ) {
		$api_key      = trim( (string) Ashna_Helper::get_option( 'farazsms_api_key' ) );
		$pattern_code = trim( (string) Ashna_Helper::get_option( 'farazsms_pattern_code' ) );
		$param_name   = trim( (string) Ashna_Helper::get_option( 'farazsms_param_name', 'code' ) );
		$line_number  = trim( (string) Ashna_Helper::get_option( 'farazsms_from_number', '' ) );

		if ( empty( $api_key ) || empty( $pattern_code ) ) {
			return new WP_Error( 'farazsms_config', 'کلید دسترسی (Api-Key) یا کد پترن فراز اس‌ام‌اس تنظیم نشده است.' );
		}

		$payload = array(
			'code'          => $pattern_code,
			'attributes'    => array(
				'' !== $param_name ? $param_name : 'code' => (string) $code,
			),
			'recipient'     => Ashna_Helper::normalize_phone( $recipient ),
			'number_format' => 'english',
		);
		if ( '' !== $line_number ) {
			$payload['line_number'] = $line_number;
		}

		$response = $this->remote_post(
			self::PATTERN_URL,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
					'Api-Key'      => $api_key,
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'farazsms_http_error', 'خطای ارتباط با فراز اس‌ام‌اس: ' . $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $body['status'] ) && 'success' === $body['status'] ) {
			return true;
		}

		$err = isset( $body['message'] ) && is_string( $body['message'] ) ? $body['message'] : 'خطا در ارسال پیامک پترن فراز اس‌ام‌اس (کد HTTP: ' . (int) wp_remote_retrieve_response_code( $response ) . ')';
		return new WP_Error( 'farazsms_api_error', $err );
	}
}
