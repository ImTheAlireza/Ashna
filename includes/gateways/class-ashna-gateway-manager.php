<?php
/**
 * Gateway Manager, Failover Engine & Multi-Channel Dispatcher
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Gateway_Manager {

	/**
	 * Custom registered SMS gateways
	 *
	 * @var array<string, Ashna_Gateway_Interface>
	 */
	private static $custom_gateways = array();

	/**
	 * Register a custom SMS gateway driver programmatically
	 *
	 * @param Ashna_Gateway_Interface $gateway Gateway instance.
	 */
	public static function register_gateway( Ashna_Gateway_Interface $gateway ) {
		self::$custom_gateways[ $gateway->get_id() ] = $gateway;
	}

	/**
	 * Get all available SMS gateways
	 *
	 * @return array<string, Ashna_Gateway_Interface>
	 */
	public static function get_sms_gateways() {
		$gateways = array_merge(
			array(
				'sandbox'     => new Ashna_Gateway_Sandbox(),
				'smsir'       => new Ashna_Gateway_Smsir(),
				'kavenegar'   => new Ashna_Gateway_Kavenegar(),
				'melipayamak' => new Ashna_Gateway_Melipayamak(),
				'farazsms'    => new Ashna_Gateway_Farazsms(),
				'ippanel'     => new Ashna_Gateway_Ippanel(),
			),
			self::$custom_gateways
		);

		return apply_filters( 'ashna_otp_sms_gateways', $gateways );
	}

	/**
	 * Get active SMS gateway instance
	 *
	 * @return Ashna_Gateway_Interface
	 */
	public static function get_active_sms_gateway() {
		$gateways  = self::get_sms_gateways();
		$active_id = Ashna_Helper::get_option( 'active_sms_gateway', 'sandbox' );

		if ( isset( $gateways[ $active_id ] ) ) {
			return $gateways[ $active_id ];
		}

		return $gateways['sandbox'];
	}

	/**
	 * Get ordered list of configured backup SMS gateway instances (up to 3 priorities)
	 *
	 * @return array<int, Ashna_Gateway_Interface>
	 */
	public static function get_backup_sms_gateways() {
		$gateways  = self::get_sms_gateways();
		$active_id = Ashna_Helper::get_option( 'active_sms_gateway', 'sandbox' );

		$b1 = Ashna_Helper::get_option( 'backup_sms_gateway_1', Ashna_Helper::get_option( 'backup_sms_gateway', 'none' ) );
		$b2 = Ashna_Helper::get_option( 'backup_sms_gateway_2', 'none' );
		$b3 = Ashna_Helper::get_option( 'backup_sms_gateway_3', 'none' );

		$candidates = array( $b1, $b2, $b3 );
		$backups    = array();
		$seen       = array( $active_id => true );

		foreach ( $candidates as $gw_id ) {
			if ( ! empty( $gw_id ) && 'none' !== $gw_id && empty( $seen[ $gw_id ] ) && isset( $gateways[ $gw_id ] ) ) {
				$backups[]      = $gateways[ $gw_id ];
				$seen[ $gw_id ] = true;
			}
		}

		return $backups;
	}

	/**
	 * Send SMS via primary gateway with automatic failover chain across up to 3 backup gateways
	 *
	 * @param string $recipient         Normalized phone.
	 * @param string $code              OTP code.
	 * @param string $forced_gateway_id Optional specific gateway ID to test.
	 * @return array { result: true|WP_Error, gateway_id: string, message: string }
	 */
	public static function send_sms_with_failover( $recipient, $code, $forced_gateway_id = '' ) {
		$gateways = self::get_sms_gateways();

		if ( ! empty( $forced_gateway_id ) && isset( $gateways[ $forced_gateway_id ] ) ) {
			$gw  = $gateways[ $forced_gateway_id ];
			$res = $gw->send( $recipient, $code );
			return array(
				'result'     => $res,
				'gateway_id' => $gw->get_id(),
				'message'    => is_wp_error( $res ) ? $res->get_error_message() : 'ارسال موفق از طریق ' . $gw->get_title(),
			);
		}

		$primary_gw  = self::get_active_sms_gateway();
		$primary_res = $primary_gw->send( $recipient, $code );

		if ( ! is_wp_error( $primary_res ) ) {
			return array(
				'result'     => true,
				'gateway_id' => $primary_gw->get_id(),
				'message'    => 'ارسال موفق از طریق ' . $primary_gw->get_title(),
			);
		}

		// Try Backup Failover Chain (Priority 1 -> Priority 2 -> Priority 3)
		$backups         = self::get_backup_sms_gateways();
		$priority_labels = array( 'اول', 'دوم', 'سوم' );

		foreach ( $backups as $idx => $backup_gw ) {
			$backup_res = $backup_gw->send( $recipient, $code );
			if ( ! is_wp_error( $backup_res ) ) {
				$p_label = isset( $priority_labels[ $idx ] ) ? $priority_labels[ $idx ] : (string) ( $idx + 1 );
				return array(
					'result'     => true,
					'gateway_id' => $backup_gw->get_id(),
					'message'    => sprintf(
						'ارسال از طریق پشتیبان %s (%s) پس از خطای درگاه اصلی (%s)',
						$p_label,
						$backup_gw->get_title(),
						$primary_res->get_error_message()
					),
				);
			}
		}

		return array(
			'result'     => $primary_res,
			'gateway_id' => $primary_gw->get_id(),
			'message'    => $primary_res->get_error_message(),
		);
	}

	/**
	 * Dispatch OTP code to recipient and log result
	 *
	 * @param string $recipient         Normalized phone or email.
	 * @param string $identifier_type   'phone' or 'email'.
	 * @param string $forced_channel    Optional forced channel ('sms', 'bale', 'email').
	 * @param string $forced_gateway_id Optional forced SMS gateway ID for testing.
	 * @return array|WP_Error { code: string, channel: string, gateway: string, log_id: int }
	 */
	public static function dispatch_otp( $recipient, $identifier_type = 'phone', $forced_channel = '', $forced_gateway_id = '' ) {
		$length   = absint( Ashna_Helper::get_option( 'otp_length', 5 ) );
		$code     = Ashna_Helper::generate_otp_code( $length );
		$otp_hash = wp_hash_password( $code );

		$channel_used = 'sms';
		$gateway_used = 'sandbox';
		$response_msg = 'ارسال موفق';
		$send_result  = true;

		if ( 'email' === $identifier_type || 'email' === $forced_channel ) {
			$email_gw     = new Ashna_Gateway_Email();
			$channel_used = 'email';
			$gateway_used = $email_gw->get_id();
			$send_result  = $email_gw->send( $recipient, $code );
			if ( ! is_wp_error( $send_result ) ) {
				$response_msg = 'ارسال موفق به ایمیل';
			}
		} else {
			$delivery_strategy = ! empty( $forced_channel )
				? $forced_channel
				: Ashna_Helper::get_option( 'mobile_delivery_channel', 'sms' );

			$bale_gw = new Ashna_Gateway_Bale();

			switch ( $delivery_strategy ) {
				case 'bale':
					$channel_used = 'bale';
					$gateway_used = 'bale';
					$send_result  = $bale_gw->send( $recipient, $code );
					if ( ! is_wp_error( $send_result ) ) {
						$response_msg = 'ارسال موفق از طریق پیام‌رسان بله';
					}
					break;

				case 'bale_fallback_sms':
					$bale_res = $bale_gw->send( $recipient, $code );
					if ( ! is_wp_error( $bale_res ) ) {
						$channel_used = 'bale';
						$gateway_used = 'bale';
						$send_result  = true;
						$response_msg = 'ارسال موفق از طریق پیام‌رسان بله';
					} else {
						$sms_attempt  = self::send_sms_with_failover( $recipient, $code, $forced_gateway_id );
						$channel_used = 'sms';
						$gateway_used = $sms_attempt['gateway_id'];
						$send_result  = $sms_attempt['result'];
						if ( ! is_wp_error( $send_result ) ) {
							$response_msg = sprintf( 'ارسال از طریق پیامک (%s) پس از عدم موفقیت بله', $gateway_used );
						}
					}
					break;

				case 'sms_fallback_bale':
					$sms_attempt = self::send_sms_with_failover( $recipient, $code, $forced_gateway_id );
					if ( ! is_wp_error( $sms_attempt['result'] ) ) {
						$channel_used = 'sms';
						$gateway_used = $sms_attempt['gateway_id'];
						$send_result  = true;
						$response_msg = $sms_attempt['message'];
					} else {
						$channel_used = 'bale';
						$gateway_used = 'bale';
						$send_result  = $bale_gw->send( $recipient, $code );
						if ( ! is_wp_error( $send_result ) ) {
							$response_msg = 'ارسال از طریق بله پس از خطای پیامک: ' . $sms_attempt['message'];
						}
					}
					break;

				case 'both':
					$sms_attempt = self::send_sms_with_failover( $recipient, $code, $forced_gateway_id );
					$bale_res    = $bale_gw->send( $recipient, $code );
					if ( ! is_wp_error( $sms_attempt['result'] ) || ! is_wp_error( $bale_res ) ) {
						$channel_used = 'sms+bale';
						$gateway_used = $sms_attempt['gateway_id'] . '+bale';
						$send_result  = true;
						$response_msg = 'ارسال همزمان پیامک و بله';
					} else {
						$channel_used = 'sms+bale';
						$gateway_used = $sms_attempt['gateway_id'] . '+bale';
						$send_result  = $sms_attempt['result'];
					}
					break;

				case 'sms':
				default:
					$sms_attempt  = self::send_sms_with_failover( $recipient, $code, $forced_gateway_id );
					$channel_used = 'sms';
					$gateway_used = $sms_attempt['gateway_id'];
					$send_result  = $sms_attempt['result'];
					$response_msg = $sms_attempt['message'];
					break;
			}
		}

		$is_error = is_wp_error( $send_result );
		if ( $is_error ) {
			$response_msg = $send_result->get_error_message();
		}

		$log_id = Ashna_Logger::insert(
			array(
				'recipient'        => $recipient,
				'channel'          => $channel_used,
				'gateway'          => $gateway_used,
				'otp_code'         => $code,
				'otp_hash'         => $otp_hash,
				'status'           => $is_error ? 'failed' : 'sent',
				'response_message' => $response_msg,
			)
		);

		if ( $is_error ) {
			return $send_result;
		}

		do_action( 'ashna_otp_sent', $recipient, $code, $channel_used, $gateway_used, $log_id );

		return array(
			'code'     => $code,
			'channel'  => $channel_used,
			'gateway'  => $gateway_used,
			'log_id'   => $log_id,
			'response' => $response_msg,
		);
	}
}
