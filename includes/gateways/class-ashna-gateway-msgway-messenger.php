<?php
/**
 * Rahpayam (راه‌پیام) Messenger Gateway: Iranian messengers via api.msgway.com
 *
 * Modes:
 * - smart:  Rahpayam picks the route among the channels enabled in its panel
 *           (Gap, Eitaa, iGap, Bale, Rubika; optionally SMS) with its own failover.
 * - gap / igap: one messenger (documented provider IDs 2 and 8).
 * - custom: any other messenger, by the provider ID shown in the Rahpayam panel.
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Gateway_Msgway_Messenger extends Ashna_Gateway_Msgway {

	/**
	 * Documented messenger provider IDs.
	 *
	 * @var array<string, int>
	 */
	const PROVIDERS = array(
		'gap'  => 2,
		'igap' => 8,
	);

	/**
	 * Get gateway ID
	 *
	 * @return string
	 */
	public function get_id() {
		return 'msgway_messenger';
	}

	/**
	 * Get gateway title
	 *
	 * @return string
	 */
	public function get_title() {
		return 'پیام‌رسان‌های ایرانی (راه‌پیام)';
	}

	/**
	 * Delivery channel.
	 *
	 * @return string
	 */
	public function get_channel() {
		return 'messenger';
	}

	/**
	 * Visitor-facing name of the route, e.g. for "the code was sent via …".
	 *
	 * @return string
	 */
	public static function route_label() {
		$mode = Ashna_Helper::get_option( 'msgway_messenger_mode', 'smart' );
		if ( 'gap' === $mode ) {
			return 'پیام‌رسان گپ';
		}
		if ( 'igap' === $mode ) {
			return 'پیام‌رسان آی‌گپ';
		}
		return 'پیام‌رسان‌های داخلی';
	}

	/**
	 * Send OTP through Iranian messengers
	 *
	 * @param string $recipient Normalized phone number.
	 * @param string $code      OTP code.
	 * @return true|WP_Error
	 */
	public function send( $recipient, $code ) {
		$mode        = Ashna_Helper::get_option( 'msgway_messenger_mode', 'smart' );
		$template_id = absint( Ashna_Helper::get_option( 'msgway_messenger_template_id' ) );

		if ( 'smart' === $mode ) {
			return $this->request( 'smart', $template_id, 0, $recipient, $code );
		}

		if ( isset( self::PROVIDERS[ $mode ] ) ) {
			$provider = self::PROVIDERS[ $mode ];
		} else {
			$provider = absint( Ashna_Helper::get_option( 'msgway_messenger_provider', 0 ) );
			if ( empty( $provider ) ) {
				return new WP_Error( 'msgway_config', 'شناسه پیام‌رسان (provider) راه‌پیام وارد نشده است.' );
			}
		}

		return $this->request( 'messenger', $template_id, $provider, $recipient, $code );
	}
}
