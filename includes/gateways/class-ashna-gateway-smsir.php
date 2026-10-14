<?php
/**
 * SMS.ir Gateway (REST API v1 - Verify / UltraFastSend)
 *
 * @package Ashna_OTP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Gateway_Smsir extends Ashna_Abstract_Gateway {

	/**
	 * Get gateway ID
	 *
	 * @return string
	 */
	public function get_id() {
		return 'smsir';
	}

	/**
	 * Get gateway title
	 *
	 * @return string
	 */
	public function get_title() {
		return 'اس‌ام‌اس دات آی‌آر (SMS.ir)';
	}

	/**
	 * Send OTP via SMS.ir Verify API v1
	 *
	 * @param string $recipient Normalized phone number.
	 * @param string $code      OTP code.
	 * @return true|WP_Error
	 */
	public function send( $recipient, $code ) {
		$api_key     = trim( (string) Ashna_Helper::get_option( 'smsir_api_key' ) );
		$template_id = absint( Ashna_Helper::get_option( 'smsir_template_id' ) );
		$param_name  = trim( (string) Ashna_Helper::get_option( 'smsir_param_name', 'CODE' ) );

		if ( empty( $api_key ) || empty( $template_id ) ) {
			return new WP_Error( 'smsir_config', 'کلید وب‌سرویس (API Key) یا شناسه قالب (Template ID) درگاه SMS.ir تنظیم نشده است.' );
		}

		if ( empty( $param_name ) ) {
			$param_name = 'CODE';
		}

		$payload = array(
			'mobile'     => Ashna_Helper::normalize_phone( $recipient ),
			'templateId' => $template_id,
			'parameters' => array(
				array(
					'name'  => $param_name,
					'value' => (string) $code,
				),
			),
		);

		$response = wp_remote_post(
			'https://api.sms.ir/v1/send/verify',
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
					'x-api-key'    => $api_key,
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'smsir_http_error', 'خطای ارتباط با SMS.ir: ' . $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code_http = wp_remote_retrieve_response_code( $response );

		if ( 200 === $code_http && isset( $body['status'] ) && 1 === (int) $body['status'] ) {
			return true;
		}

		$error_msg = isset( $body['message'] ) ? $body['message'] : 'خطای نامشخص از وب‌سرویس SMS.ir (کد HTTP: ' . $code_http . ')';
		return new WP_Error( 'smsir_api_error', $error_msg );
	}
}
