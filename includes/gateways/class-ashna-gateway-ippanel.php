<?php
/**
 * IPPanel SMS Gateway (Edge API v1, REST API v1, & Legacy Pattern API)
 *
 * @package Ashna_OTP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Gateway_Ippanel extends Ashna_Abstract_Gateway {

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
	 * Send OTP via IPPanel
	 *
	 * @param string $recipient Normalized phone number.
	 * @param string $code      OTP code.
	 * @return true|WP_Error
	 */
	public function send( $recipient, $code ) {
		$auth_type    = Ashna_Helper::get_option( 'ippanel_auth_type', 'edge' );
		$api_key      = trim( (string) Ashna_Helper::get_option( 'ippanel_api_key' ) );
		$username     = trim( (string) Ashna_Helper::get_option( 'ippanel_username' ) );
		$password     = trim( (string) Ashna_Helper::get_option( 'ippanel_password' ) );
		$from_number  = trim( (string) Ashna_Helper::get_option( 'ippanel_from_number', '+983000505' ) );
		$pattern_code = trim( (string) Ashna_Helper::get_option( 'ippanel_pattern_code' ) );
		$param_name   = trim( (string) Ashna_Helper::get_option( 'ippanel_param_name', 'code' ) );

		if ( empty( $pattern_code ) ) {
			return new WP_Error( 'ippanel_config', 'کد پترن (Pattern Code) درگاه آی‌پی‌پنل تنظیم نشده است.' );
		}

		if ( empty( $param_name ) ) {
			$param_name = 'code';
		}

		// Method 1: IPPanel Edge API (New Official Endpoint)
		if ( 'edge' === $auth_type ) {
			if ( empty( $api_key ) ) {
				return new WP_Error( 'ippanel_config', 'توکن/کلید دسترسی (API Key) آی‌پی‌پنل وارد نشده است.' );
			}

			$payload = array(
				'sending_type' => 'pattern',
				'from_number'  => $from_number,
				'code'         => $pattern_code,
				'recipients'   => array( Ashna_Helper::to_international_phone( $recipient, true ) ),
				'params'       => array(
					$param_name => (string) $code,
				),
			);

			$response = wp_remote_post(
				'https://edge.ippanel.com/v1/api/send',
				array(
					'timeout' => 15,
					'headers' => array(
						'Content-Type'  => 'application/json',
						'Authorization' => $api_key,
					),
					'body'    => wp_json_encode( $payload ),
				)
			);

			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'ippanel_http_error', 'خطای ارتباط با Edge IPPanel: ' . $response->get_error_message() );
			}

			$body      = json_decode( wp_remote_retrieve_body( $response ), true );
			$http_code = wp_remote_retrieve_response_code( $response );

			if ( ( 200 === $http_code || 201 === $http_code ) && ( ! empty( $body['meta']['status'] ) || isset( $body['data'] ) ) ) {
				return true;
			}

			$err = isset( $body['meta']['message'] ) ? $body['meta']['message'] : 'خطا در ارسال پیامک از طریق آی‌پی‌پنل Edge';
			return new WP_Error( 'ippanel_api_error', $err );
		}

		// Method 2: Classic API Key (api2.ippanel.com)
		if ( 'apikey' === $auth_type ) {
			if ( empty( $api_key ) ) {
				return new WP_Error( 'ippanel_config', 'کلید API آی‌پی‌پنل وارد نشده است.' );
			}

			$payload = array(
				'code'      => $pattern_code,
				'sender'    => $from_number,
				'recipient' => Ashna_Helper::normalize_phone( $recipient ),
				'variable'  => array(
					$param_name => (string) $code,
				),
			);

			$response = wp_remote_post(
				'https://api2.ippanel.com/api/v1/sms/pattern/normal/send',
				array(
					'timeout' => 15,
					'headers' => array(
						'Content-Type' => 'application/json',
						'apikey'       => $api_key,
					),
					'body'    => wp_json_encode( $payload ),
				)
			);

			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'ippanel_http_error', 'خطای ارتباط با آی‌پی‌پنل: ' . $response->get_error_message() );
			}

			$body      = json_decode( wp_remote_retrieve_body( $response ), true );
			$http_code = wp_remote_retrieve_response_code( $response );

			if ( ( 200 === $http_code || 201 === $http_code ) && ( isset( $body['status'] ) && 'OK' === $body['status'] || isset( $body['data']['message_id'] ) ) ) {
				return true;
			}

			$err = isset( $body['error_message'] ) ? $body['error_message'] : 'خطا در ارسال پیامک پترن آی‌پی‌پنل';
			return new WP_Error( 'ippanel_api_error', $err );
		}

		// Method 3: Username & Password
		if ( empty( $username ) || empty( $password ) ) {
			return new WP_Error( 'ippanel_config', 'نام کاربری و رمز عبور آی‌پی‌پنل وارد نشده است.' );
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

		$response = wp_remote_post( $url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ippanel_http_error', 'خطای ارتباط با آی‌پی‌پنل: ' . $response->get_error_message() );
		}

		$raw_body = trim( wp_remote_retrieve_body( $response ) );
		if ( is_numeric( $raw_body ) && (float) $raw_body > 0 ) {
			return true;
		}

		return new WP_Error( 'ippanel_api_error', 'پاسخ خطا از آی‌پی‌پنل: ' . $raw_body );
	}
}
