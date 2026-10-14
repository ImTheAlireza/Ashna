<?php
/**
 * Kavenegar SMS Gateway (Verify Lookup API)
 *
 * @package Ashna_OTP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Gateway_Kavenegar extends Ashna_Abstract_Gateway {

	/**
	 * Get gateway ID
	 *
	 * @return string
	 */
	public function get_id() {
		return 'kavenegar';
	}

	/**
	 * Get gateway title
	 *
	 * @return string
	 */
	public function get_title() {
		return 'کاوه‌نگار (Kavenegar)';
	}

	/**
	 * Send OTP via Kavenegar Verify Lookup API
	 *
	 * @param string $recipient Normalized phone number.
	 * @param string $code      OTP code.
	 * @return true|WP_Error
	 */
	public function send( $recipient, $code ) {
		$api_key  = trim( (string) Ashna_Helper::get_option( 'kavenegar_api_key' ) );
		$template = trim( (string) Ashna_Helper::get_option( 'kavenegar_template' ) );

		if ( empty( $api_key ) || empty( $template ) ) {
			return new WP_Error( 'kavenegar_config', 'کلید API یا نام الگوی اعتبارسنجی (Template) کاوه‌نگار تنظیم نشده است.' );
		}

		$url = sprintf( 'https://api.kavenegar.com/v1/%s/verify/lookup.json', rawurlencode( $api_key ) );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'body'    => array(
					'receptor' => Ashna_Helper::normalize_phone( $recipient ),
					'template' => $template,
					'token'    => (string) $code,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'kavenegar_http_error', 'خطای ارتباط با کاوه‌نگار: ' . $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $body['return']['status'] ) && 200 === (int) $body['return']['status'] ) {
			return true;
		}

		$error_msg = isset( $body['return']['message'] ) ? $body['return']['message'] : 'خطای نامشخص از وب‌سرویس کاوه‌نگار';
		return new WP_Error( 'kavenegar_api_error', $error_msg );
	}
}
