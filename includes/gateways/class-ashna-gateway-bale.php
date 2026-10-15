<?php
/**
 * Bale Messenger OTP Gateway (Safir Official OTP API + Bale Bot API)
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Gateway_Bale extends Ashna_Abstract_Gateway {

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
	 * Send OTP via Bale Messenger (Safir OTP API or Bot API)
	 *
	 * @param string $recipient Normalized phone number or chat_id.
	 * @param string $code      OTP code.
	 * @return true|WP_Error
	 */
	public function send( $recipient, $code ) {
		$mode = Ashna_Helper::get_option( 'bale_mode', 'safir' );

		if ( 'bot' === $mode ) {
			return $this->send_via_bot( $recipient, $code );
		}

		return $this->send_via_safir( $recipient, $code );
	}

	/**
	 * Send OTP using Bale Safir v2 Official OTP API
	 *
	 * @param string $recipient Normalized phone number.
	 * @param string $code      OTP code.
	 * @return true|WP_Error
	 */
	private function send_via_safir( $recipient, $code ) {
		$token = $this->get_safir_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$intl_phone = Ashna_Helper::to_international_phone( $recipient, false ); // 98912xxxxxxx

		$payload = array(
			'phone' => $intl_phone,
			'otp'   => (int) $code,
		);

		$response = wp_remote_post(
			'https://safir.bale.ai/api/v2/send_otp',
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $token,
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'bale_safir_http_error', 'خطای ارتباط با وب‌سرویس سفیر بله: ' . $response->get_error_message() );
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$body      = json_decode( wp_remote_retrieve_body( $response ), true );

		// If token expired (401), clear transient and retry once
		if ( 401 === $http_code ) {
			delete_transient( 'ashna_bale_safir_token' );
			$token = $this->get_safir_access_token();
			if ( ! is_wp_error( $token ) ) {
				$response  = wp_remote_post(
					'https://safir.bale.ai/api/v2/send_otp',
					array(
						'timeout' => 15,
						'headers' => array(
							'Content-Type'  => 'application/json',
							'Authorization' => 'Bearer ' . $token,
						),
						'body'    => wp_json_encode( $payload ),
					)
				);
				$http_code = wp_remote_retrieve_response_code( $response );
				$body      = json_decode( wp_remote_retrieve_body( $response ), true );
			}
		}

		if ( 200 === $http_code ) {
			return true;
		}

		$error_msg = isset( $body['message'] ) ? $body['message'] : ( isset( $body['error'] ) ? $body['error'] : 'خطا در ارسال کد از طریق سفیر بله (کد HTTP: ' . $http_code . ')' );
		return new WP_Error( 'bale_safir_api_error', $error_msg );
	}

	/**
	 * Retrieve OAuth2 Access Token from Bale Safir API
	 *
	 * @return string|WP_Error
	 */
	private function get_safir_access_token() {
		$cached = get_transient( 'ashna_bale_safir_token' );
		if ( ! empty( $cached ) ) {
			return $cached;
		}

		$client_id     = trim( (string) Ashna_Helper::get_option( 'bale_client_id' ) );
		$client_secret = trim( (string) Ashna_Helper::get_option( 'bale_client_secret' ) );

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return new WP_Error( 'bale_safir_config', 'شناسه کاربری (Client ID) و رمز عبور (Client Secret) سفیر بله تنظیم نشده است.' );
		}

		$response = wp_remote_post(
			'https://safir.bale.ai/api/v2/auth/token',
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'grant_type'    => 'client_credentials',
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'scope'         => 'read',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'bale_token_http_error', 'خطا در دریافت توکن سفیر بله: ' . $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! empty( $body['access_token'] ) ) {
			$expires_in = isset( $body['expires_in'] ) ? max( 60, (int) $body['expires_in'] - 60 ) : 3500;
			set_transient( 'ashna_bale_safir_token', $body['access_token'], $expires_in );
			return $body['access_token'];
		}

		$err = isset( $body['error_description'] ) ? $body['error_description'] : 'عدم امکان احراز هویت در وب‌سرویس سفیر بله';
		return new WP_Error( 'bale_token_error', $err );
	}

	/**
	 * Send OTP via Bale Bot API (tapi.bale.ai)
	 *
	 * @param string $recipient Normalized phone number or chat_id.
	 * @param string $code      OTP code.
	 * @return true|WP_Error
	 */
	private function send_via_bot( $recipient, $code ) {
		$bot_token = trim( (string) Ashna_Helper::get_option( 'bale_bot_token' ) );
		if ( empty( $bot_token ) ) {
			return new WP_Error( 'bale_bot_config', 'توکن ربات بله (Bot Token) در تنظیمات وارد نشده است.' );
		}

		// Resolve chat_id from user_meta if recipient is a phone number
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

		$url = sprintf( 'https://tapi.bale.ai/bot%s/sendMessage', rawurlencode( $bot_token ) );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
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
				'meta_key'   => 'ashna_phone',
				'meta_value' => $phone,
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
