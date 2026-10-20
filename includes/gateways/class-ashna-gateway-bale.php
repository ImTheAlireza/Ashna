<?php
/**
 * Bale Messenger OTP Gateway (Safir v3 OTP API + Bale Bot API)
 *
 * Safir v3 (docs.bale.ai/safir): one request per code, authenticated with the
 * organisation's api-access-key; the code is delivered by Bale's official
 * verification bot with your bot's name and a copy button.
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Gateway_Bale extends Ashna_Abstract_Gateway {

	/** Safir v3 message endpoint. */
	const SAFIR_URL = 'https://safir.bale.ai/api/v3/send_message';

	/**
	 * Get gateway ID
	 *
	 * @return string
	 */
	public function get_id() {
		return 'bale';
	}

	/**
	 * Get gateway title
	 *
	 * @return string
	 */
	public function get_title() {
		return 'پیام‌رسان بله (Bale)';
	}

	/**
	 * Delivery channel.
	 *
	 * @return string
	 */
	public function get_channel() {
		return 'bale';
	}

	/**
	 * Host checked by the background health probe.
	 *
	 * @return string
	 */
	public function get_probe_url() {
		return 'bot' === Ashna_Helper::get_option( 'bale_mode', 'safir' ) ? 'https://tapi.bale.ai/' : 'https://safir.bale.ai/';
	}

	/**
	 * Send OTP via Bale Messenger (Safir v3 or Bot API)
	 *
	 * @param string $recipient Normalized phone number.
	 * @param string $code      OTP code.
	 * @return true|WP_Error
	 */
	public function send( $recipient, $code ) {
		if ( 'bot' === Ashna_Helper::get_option( 'bale_mode', 'safir' ) ) {
			return $this->send_via_bot( $recipient, $code );
		}

		return $this->send_via_safir( $recipient, $code );
	}

	/**
	 * Send OTP using Safir v3 (single request, no token exchange).
	 *
	 * @param string $recipient Normalized phone number.
	 * @param string $code      OTP code.
	 * @return true|WP_Error
	 */
	private function send_via_safir( $recipient, $code ) {
		$api_key = trim( (string) Ashna_Helper::get_option( 'bale_api_key' ) );
		$bot_id  = absint( Ashna_Helper::get_option( 'bale_bot_id' ) );

		if ( empty( $api_key ) || empty( $bot_id ) ) {
			return new WP_Error( 'bale_safir_config', 'کلید دسترسی (api-access-key) یا شناسه عددی بازوی سفیر بله تنظیم نشده است.' );
		}

		$payload = array(
			// Idempotency key: Safir never delivers the same request_id twice.
			'request_id'   => wp_generate_uuid4(),
			'bot_id'       => $bot_id,
			'phone_number' => Ashna_Helper::to_international_phone( $recipient, false ), // 98912xxxxxxx
			'message_data' => array(
				'otp_message' => array(
					'otp' => (string) $code,
				),
			),
		);

		$response = $this->remote_post(
			self::SAFIR_URL,
			array(
				'headers' => array(
					'Content-Type'   => 'application/json',
					'api-access-key' => $api_key,
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'bale_safir_http_error', 'خطای ارتباط با وب‌سرویس سفیر بله: ' . $response->get_error_message() );
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		$body      = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $http_code >= 200 && $http_code < 300 && ! empty( $body['message_id'] ) && empty( $body['error_data'] ) ) {
			return true;
		}

		$error_msg = isset( $body['message'] ) ? (string) $body['message'] : 'خطا در ارسال کد از طریق سفیر بله (کد HTTP: ' . $http_code . ')';
		$type      = '';
		$error     = isset( $body['error_data'][0] ) ? $body['error_data'][0] : ( isset( $body['error'] ) && is_array( $body['error'] ) ? $body['error'] : null );
		if ( is_array( $error ) ) {
			if ( isset( $error['code'] ) ) {
				$type = self::safir_error_type( (int) $error['code'] );
			}
			if ( ! empty( $error['description'] ) ) {
				$error_msg = (string) $error['description'];
			}
		}

		return new WP_Error( 'bale_safir_api_error', $error_msg, $type ? array( 'ashna_error_type' => $type ) : null );
	}

	/**
	 * Failover type for documented Safir error codes (docs.bale.ai/safir).
	 *
	 * "Not a Bale user" stays a plain provider error: it falls back to SMS
	 * without sidelining Bale for everyone else.
	 *
	 * @param int $code ErrorInfo code.
	 * @return string Empty when the generic HTTP classification should apply.
	 */
	private static function safir_error_type( $code ) {
		$map = array(
			2  => 'server',  // InternalServerError.
			3  => 'server',  // RateLimitExceeded.
			4  => 'api',     // InvalidInput.
			8  => 'input',   // InvalidPhone.
			17 => 'api',     // NotBaleUser.
			20 => 'account', // PaymentRequired.
			21 => 'account', // MaximumContactLimitReached.
		);
		return isset( $map[ $code ] ) ? $map[ $code ] : '';
	}

	/**
	 * Send OTP via Bale Bot API (tapi.bale.ai)
	 *
	 * @param string $recipient Normalized phone number.
	 * @param string $code      OTP code.
	 * @return true|WP_Error
	 */
	private function send_via_bot( $recipient, $code ) {
		$bot_token = trim( (string) Ashna_Helper::get_option( 'bale_bot_token' ) );
		if ( empty( $bot_token ) ) {
			return new WP_Error( 'bale_bot_config', 'توکن ربات بله (Bot Token) در تنظیمات وارد نشده است.' );
		}

		// The bot can only message users who started it; their chat_id is stored on the profile.
		$chat_id = $this->resolve_bale_chat_id( $recipient );
		if ( empty( $chat_id ) ) {
			return new WP_Error( 'bale_no_chat_id', 'شناسه چت بله (Chat ID) برای این شماره یافت نشد.' );
		}

		$template = Ashna_Helper::get_option(
			'bale_message_template',
			"کد تایید ورود شما به {site_name}:\n*{code}*\nاین کد تا {expiry} ثانیه معتبر است."
		);

		$message = str_replace(
			array( '{code}', '{site_name}', '{expiry}' ),
			array( $code, get_bloginfo( 'name' ), Ashna_Helper::get_option( 'otp_expiry', 120 ) ),
			$template
		);

		$response = $this->remote_post(
			sprintf( 'https://tapi.bale.ai/bot%s/sendMessage', rawurlencode( $bot_token ) ),
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'chat_id' => $chat_id,
						'text'    => $message,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'bale_bot_http_error', 'خطای ارتباط با ربات بله: ' . $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! empty( $body['ok'] ) ) {
			return true;
		}

		$err = isset( $body['description'] ) ? $body['description'] : 'خطا در ارسال پیام از طریق ربات بله';
		return new WP_Error( 'bale_bot_api_error', $err );
	}

	/**
	 * Resolve Bale chat_id for a phone number from user meta
	 *
	 * @param string $phone Normalized phone number.
	 * @return string
	 */
	private function resolve_bale_chat_id( $phone ) {
		$users = get_users(
			array(
				'meta_key'   => 'ashna_phone', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- single lookup when sending via the Bale bot.
				'meta_value' => $phone, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 1,
				'fields'     => 'ID',
			)
		);

		if ( ! empty( $users ) ) {
			$chat_id = get_user_meta( $users[0], 'ashna_bale_chat_id', true );
			if ( ! empty( $chat_id ) ) {
				return $chat_id;
			}
		}

		return '';
	}
}
