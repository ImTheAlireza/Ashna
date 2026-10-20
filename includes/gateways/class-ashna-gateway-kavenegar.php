<?php
/**
 * Kavenegar SMS Gateway (Verify Lookup API)
 *
 * @package Ashna
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
	 * Host checked by the background health probe.
	 *
	 * @return string
	 */
	public function get_probe_url() {
		return 'https://api.kavenegar.com/';
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

		$response = $this->remote_post(
			$url,
			array(
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
		$status    = isset( $body['return']['status'] ) ? (int) $body['return']['status'] : 0;
		$type      = self::error_type( $status );

		return new WP_Error( 'kavenegar_api_error', $error_msg, $type ? array( 'ashna_error_type' => $type ) : null );
	}

	/**
	 * Failover type for documented Kavenegar status codes.
	 *
	 * @param int $status Value of return.status.
	 * @return string Empty when the generic HTTP classification should apply.
	 */
	private static function error_type( $status ) {
		$map = array(
			411 => 'input',   // Invalid receptor.
			401 => 'account', // Account disabled.
			416 => 'account', // Server IP not allowed.
			418 => 'account', // Insufficient credit.
			501 => 'account', // Demo account: owner's number only.
			409 => 'server',  // Server busy, retry later.
			451 => 'server',  // Too many calls in a short time.
		);
		return isset( $map[ $status ] ) ? $map[ $status ] : '';
	}
}
