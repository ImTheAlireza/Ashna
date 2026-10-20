<?php
/**
 * Melipayamak SMS Gateway (Console API: shared service line / pattern)
 *
 * Primary: console.melipayamak.com API key (the key travels in the URL, no
 * password is sent). Settings that only have the old username/password still
 * work through the legacy REST endpoint.
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
	 * Host checked by the background health probe.
	 *
	 * @return string
	 */
	public function get_probe_url() {
		return '' !== trim( (string) Ashna_Helper::get_option( 'melipayamak_api_key' ) ) ? 'https://console.melipayamak.com/' : 'https://rest.payamak-panel.com/';
	}

	/**
	 * Send OTP via Melipayamak (console API, or legacy REST for old settings)
	 *
	 * @param string $recipient Normalized phone number.
	 * @param string $code      OTP code.
	 * @return true|WP_Error
	 */
	public function send( $recipient, $code ) {
		$api_key = trim( (string) Ashna_Helper::get_option( 'melipayamak_api_key' ) );
		$body_id = absint( Ashna_Helper::get_option( 'melipayamak_body_id' ) );

		if ( empty( $body_id ) ) {
			return new WP_Error( 'melipayamak_config', 'کد متن خدماتی یا پترن (bodyId) ملی‌پیامک تنظیم نشده است.' );
		}

		if ( '' !== $api_key ) {
			return $this->send_via_console( $api_key, $body_id, $recipient, $code );
		}

		$username = trim( (string) Ashna_Helper::get_option( 'melipayamak_username' ) );
		$password = trim( (string) Ashna_Helper::get_option( 'melipayamak_password' ) );
		if ( '' !== $username && '' !== $password ) {
			return $this->send_via_legacy_rest( $username, $password, $body_id, $recipient, $code );
		}

		return new WP_Error( 'melipayamak_config', 'کلید API کنسول ملی‌پیامک تنظیم نشده است.' );
	}

	/**
	 * Console API: POST /api/send/shared/{key} with { bodyId, to, args }.
	 *
	 * @param string $api_key   Console API key.
	 * @param int    $body_id   Approved pattern ID.
	 * @param string $recipient Phone.
	 * @param string $code      OTP code.
	 * @return true|WP_Error
	 */
	private function send_via_console( $api_key, $body_id, $recipient, $code ) {
		$response = $this->remote_post(
			'https://console.melipayamak.com/api/send/shared/' . rawurlencode( $api_key ),
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'bodyId' => $body_id,
						'to'     => Ashna_Helper::normalize_phone( $recipient ),
						'args'   => array( (string) $code ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'melipayamak_http_error', 'خطای ارتباط با ملی‌پیامک: ' . $response->get_error_message() );
		}

		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$rec_id = isset( $body['recId'] ) ? $body['recId'] : 0;
		if ( is_numeric( $rec_id ) && (float) $rec_id > 0 ) {
			return true;
		}

		$err = isset( $body['status'] ) && is_string( $body['status'] ) && '' !== $body['status']
			? $body['status']
			: 'خطای وب‌سرویس ملی‌پیامک (کد HTTP: ' . (int) wp_remote_retrieve_response_code( $response ) . ')';
		return new WP_Error( 'melipayamak_api_error', $err );
	}

	/**
	 * Legacy REST (rest.payamak-panel.com BaseServiceNumber) for old settings.
	 *
	 * @param string $username  Panel username.
	 * @param string $password  Panel password or API key.
	 * @param int    $body_id   Approved pattern ID.
	 * @param string $recipient Phone.
	 * @param string $code      OTP code.
	 * @return true|WP_Error
	 */
	private function send_via_legacy_rest( $username, $password, $body_id, $recipient, $code ) {
		$response = $this->remote_post(
			'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber',
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'username' => $username,
						'password' => $password,
						'text'     => (string) $code,
						'to'       => Ashna_Helper::normalize_phone( $recipient ),
						'bodyId'   => $body_id,
					)
				),
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
