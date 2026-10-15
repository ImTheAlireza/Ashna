<?php
/**
 * Melipayamak SMS Gateway (BaseServiceNumber / Shared Pattern API)
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Gateway_Melipayamak extends Ashna_Abstract_Gateway {

	/**
	 * Get gateway ID
	 *
	 * @return string
	 */
	public function get_id() {
		return 'melipayamak';
	}

	/**
	 * Get gateway title
	 *
	 * @return string
	 */
	public function get_title() {
		return 'ملی‌پیامک (Melipayamak)';
	}

	/**
	 * Send OTP via Melipayamak BaseServiceNumber REST API
	 *
	 * @param string $recipient Normalized phone number.
	 * @param string $code      OTP code.
	 * @return true|WP_Error
	 */
	public function send( $recipient, $code ) {
		$username = trim( (string) Ashna_Helper::get_option( 'melipayamak_username' ) );
		$password = trim( (string) Ashna_Helper::get_option( 'melipayamak_password' ) );
		$body_id  = absint( Ashna_Helper::get_option( 'melipayamak_body_id' ) );

		if ( empty( $username ) || empty( $password ) || empty( $body_id ) ) {
			return new WP_Error( 'melipayamak_config', 'نام کاربری، رمز عبور (یا API Key) و کد متن خدماتی (bodyId) ملی‌پیامک تنظیم نشده است.' );
		}

		$payload = array(
			'username' => $username,
			'password' => $password,
			'text'     => (string) $code,
			'to'       => Ashna_Helper::normalize_phone( $recipient ),
			'bodyId'   => $body_id,
		);

		$response = $this->remote_post(
			'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber',
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'melipayamak_http_error', 'خطای ارتباط با ملی‌پیامک: ' . $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $body['RetStatus'] ) && 1 === (int) $body['RetStatus'] ) {
			return true;
		}

		$error_msg = isset( $body['StrRetStatus'] ) ? $body['StrRetStatus'] : 'خطای وب‌سرویس ملی‌پیامک (کد: ' . ( isset( $body['Value'] ) ? $body['Value'] : 'نامشخص' ) . ')';
		return new WP_Error( 'melipayamak_api_error', $error_msg );
	}
}
