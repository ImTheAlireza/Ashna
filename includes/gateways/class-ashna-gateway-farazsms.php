<?php
/**
 * FarazSMS Gateway (Pattern Send - API Key & Username/Password Support)
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Gateway_Farazsms extends Ashna_Abstract_Gateway {

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
	 * Send OTP via FarazSMS Pattern API
	 *
	 * @param string $recipient Normalized phone number.
	 * @param string $code      OTP code.
	 * @return true|WP_Error
	 */
	public function send( $recipient, $code ) {
		$auth_type    = Ashna_Helper::get_option( 'farazsms_auth_type', 'apikey' );
		$api_key      = trim( (string) Ashna_Helper::get_option( 'farazsms_api_key' ) );
		$username     = trim( (string) Ashna_Helper::get_option( 'farazsms_username' ) );
		$password     = trim( (string) Ashna_Helper::get_option( 'farazsms_password' ) );
		$from_number  = trim( (string) Ashna_Helper::get_option( 'farazsms_from_number', '+983000505' ) );
		$pattern_code = trim( (string) Ashna_Helper::get_option( 'farazsms_pattern_code' ) );
		$param_name   = trim( (string) Ashna_Helper::get_option( 'farazsms_param_name', 'verification-code' ) );

		if ( empty( $pattern_code ) ) {
			return new WP_Error( 'farazsms_config', 'کد پترن (Pattern Code) درگاه فراز اس‌ام‌اس تنظیم نشده است.' );
		}

		if ( empty( $param_name ) ) {
			$param_name = 'verification-code';
		}

		// Method 1: API Key (REST API v1)
		if ( 'apikey' === $auth_type ) {
			if ( empty( $api_key ) ) {
				return new WP_Error( 'farazsms_config', 'کلید دسترسی (API Key) فراز اس‌ام‌اس وارد نشده است.' );
			}

			$payload = array(
				'code'      => $pattern_code,
				'sender'    => $from_number,
				'recipient' => Ashna_Helper::normalize_phone( $recipient ),
				'variable'  => array(
					$param_name => (string) $code,
				),
			);

			$response = $this->remote_post(
				'https://api2.ippanel.com/api/v1/sms/pattern/normal/send',
				array(
					'headers' => array(
						'Content-Type' => 'application/json',
						'apikey'       => $api_key,
					),
					'body'    => wp_json_encode( $payload ),
				)
			);

			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'farazsms_http_error', 'خطای ارتباط با فراز اس‌ام‌اس: ' . $response->get_error_message() );
			}

			$body      = json_decode( wp_remote_retrieve_body( $response ), true );
			$http_code = wp_remote_retrieve_response_code( $response );

			if ( ( 200 === $http_code || 201 === $http_code ) && ( isset( $body['status'] ) && 'OK' === $body['status'] || isset( $body['data']['message_id'] ) ) ) {
				return true;
			}

			$err = isset( $body['error_message'] ) ? $body['error_message'] : ( isset( $body['message'] ) ? $body['message'] : 'خطا در ارسال پیامک پترن فراز اس‌ام‌اس' );
			return new WP_Error( 'farazsms_api_error', $err );
		}

		// Method 2: Username & Password pattern endpoint
		if ( empty( $username ) || empty( $password ) ) {
			return new WP_Error( 'farazsms_config', 'نام کاربری و رمز عبور فراز اس‌ام‌اس تنظیم نشده است.' );
		}

		$input_data = array(
			$param_name => (string) $code,
		);

		$url = add_query_arg(
			array(
				'username'     => rawurlencode( $username ),
				'password'     => rawurlencode( $password ),
				'from'         => rawurlencode( $from_number ),
				'to'           => wp_json_encode( array( Ashna_Helper::normalize_phone( $recipient ) ) ),
				'input_data'   => rawurlencode( wp_json_encode( $input_data ) ),
				'pattern_code' => rawurlencode( $pattern_code ),
			),
			'https://ippanel.com/patterns/pattern'
		);

		$response = $this->remote_post( $url );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'farazsms_http_error', 'خطای ارتباط با فراز اس‌ام‌اس: ' . $response->get_error_message() );
		}

		$raw_body = trim( wp_remote_retrieve_body( $response ) );
		if ( is_numeric( $raw_body ) && (float) $raw_body > 0 ) {
			return true;
		}

		return new WP_Error( 'farazsms_api_error', 'پاسخ خطا از فراز اس‌ام‌اس: ' . $raw_body );
	}
}
