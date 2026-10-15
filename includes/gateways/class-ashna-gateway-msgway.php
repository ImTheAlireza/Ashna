<?php
/**
 * Rahpayam (راه‌پیام, MsgWay) Gateway: SMS through api.msgway.com
 *
 * Rahpayam delivers template messages over SMS, voice and Iranian messengers
 * (Gap, Eitaa, iGap, Bale, Rubika) behind one API. This class is the SMS
 * route; Ashna_Gateway_Msgway_Messenger is the messenger route.
 *
 * API (doc.msgway.com/swagger.json, official SDKs): POST /send with headers
 * apiKey + accept-language and { method, mobile, templateID, code, provider }.
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Gateway_Msgway extends Ashna_Abstract_Gateway {

	/** Send endpoint. */
	const SEND_URL = 'https://api.msgway.com/send';

	/**
	 * Get gateway ID
	 *
	 * @return string
	 */
	public function get_id() {
		return 'msgway';
	}

	/**
	 * Get gateway title
	 *
	 * @return string
	 */
	public function get_title() {
		return 'راه‌پیام (MsgWay)';
	}

	/**
	 * Host checked by the background health probe.
	 *
	 * @return string
	 */
	public function get_probe_url() {
		return 'https://api.msgway.com/';
	}

	/**
	 * Send OTP as SMS through Rahpayam
	 *
	 * @param string $recipient Normalized phone number.
	 * @param string $code      OTP code.
	 * @return true|WP_Error
	 */
	public function send( $recipient, $code ) {
		return $this->request(
			'sms',
			absint( Ashna_Helper::get_option( 'msgway_sms_template_id' ) ),
			absint( Ashna_Helper::get_option( 'msgway_sms_line', 0 ) ),
			$recipient,
			$code
		);
	}

	/**
	 * Call POST /send.
	 *
	 * @param string $method      sms | messenger | smart.
	 * @param int    $template_id Approved template ID from the Rahpayam panel.
	 * @param int    $provider    Provider ID (0 = Rahpayam's default).
	 * @param string $recipient   Phone.
	 * @param string $code        OTP code (Ashna generates and verifies it).
	 * @return true|WP_Error
	 */
	protected function request( $method, $template_id, $provider, $recipient, $code ) {
		$api_key = trim( (string) Ashna_Helper::get_option( 'msgway_api_key' ) );
		if ( empty( $api_key ) || empty( $template_id ) ) {
			return new WP_Error( 'msgway_config', 'کلید API یا شناسه الگوی راه‌پیام تنظیم نشده است.' );
		}

		$payload = array(
			'method'     => $method,
			'mobile'     => Ashna_Helper::to_international_phone( $recipient, true ), // +98912xxxxxxx
			'templateID' => (int) $template_id,
			'code'       => (string) $code,
		);
		if ( $provider > 0 ) {
			$payload['provider'] = (int) $provider;
		}

		$response = $this->remote_post(
			self::SEND_URL,
			array(
				'headers' => array(
					'Content-Type'    => 'application/json',
					'accept-language' => 'fa',
					'apiKey'          => $api_key,
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'msgway_http_error', 'خطای ارتباط با راه‌پیام: ' . $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $body['status'] ) && 'success' === $body['status'] && ! empty( $body['referenceID'] ) ) {
			return true;
		}

		$err = isset( $body['error']['message'] ) && '' !== (string) $body['error']['message']
			? (string) $body['error']['message']
			: 'خطا در ارسال از طریق راه‌پیام (کد HTTP: ' . (int) wp_remote_retrieve_response_code( $response ) . ')';
		return new WP_Error( 'msgway_api_error', $err );
	}
}
