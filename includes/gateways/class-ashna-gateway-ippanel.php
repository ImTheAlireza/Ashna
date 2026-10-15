<?php
/**
 * IPPanel SMS Gateway (Edge API v1: edge.ippanel.com)
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Gateway_Ippanel extends Ashna_Abstract_Gateway {

	/** Edge send endpoint. */
	const SEND_URL = 'https://edge.ippanel.com/v1/api/send';

	/**
	 * Get gateway ID
	 *
	 * @return string
	 */
	public function get_id() {
		return 'ippanel';
	}

	/**
	 * Get gateway title
	 *
	 * @return string
	 */
	public function get_title() {
		return 'آی‌پی‌پنل (IPPanel)';
	}

	/**
	 * Host checked by the background health probe.
	 *
	 * @return string
	 */
	public function get_probe_url() {
		return 'https://edge.ippanel.com/';
	}

	/**
	 * Send OTP via the IPPanel Edge pattern API
	 *
	 * @param string $recipient Normalized phone number.
	 * @param string $code      OTP code.
	 * @return true|WP_Error
	 */
	public function send( $recipient, $code ) {
		$api_key      = trim( (string) Ashna_Helper::get_option( 'ippanel_api_key' ) );
		$from_number  = trim( (string) Ashna_Helper::get_option( 'ippanel_from_number', '+983000505' ) );
		$pattern_code = trim( (string) Ashna_Helper::get_option( 'ippanel_pattern_code' ) );
		$param_name   = trim( (string) Ashna_Helper::get_option( 'ippanel_param_name', 'code' ) );

		if ( empty( $api_key ) || empty( $pattern_code ) ) {
			return new WP_Error( 'ippanel_config', 'توکن دسترسی (API Key) یا کد پترن آی‌پی‌پنل تنظیم نشده است.' );
		}

		$payload = array(
			'sending_type' => 'pattern',
			'from_number'  => '' !== $from_number ? $from_number : '+983000505',
			'code'         => $pattern_code,
			'recipients'   => array( Ashna_Helper::to_international_phone( $recipient, true ) ),
			'params'       => array(
				'' !== $param_name ? $param_name : 'code' => (string) $code,
			),
		);

		$response = $this->remote_post(
			self::SEND_URL,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => $api_key,
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ippanel_http_error', 'خطای ارتباط با آی‌پی‌پنل: ' . $response->get_error_message() );
		}

		$body      = json_decode( wp_remote_retrieve_body( $response ), true );
		$http_code = (int) wp_remote_retrieve_response_code( $response );

		if ( ( 200 === $http_code || 201 === $http_code ) && ! empty( $body['meta']['status'] ) ) {
			return true;
		}

		$err = isset( $body['meta']['message'] ) ? $body['meta']['message'] : 'خطا در ارسال پیامک از طریق آی‌پی‌پنل (کد HTTP: ' . $http_code . ')';
		return new WP_Error( 'ippanel_api_error', $err );
	}
}
