<?php
/**
 * Sandbox / Test Gateway (For local testing without SMS credits)
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Gateway_Sandbox extends Ashna_Abstract_Gateway {

	/**
	 * Get gateway ID
	 *
	 * @return string
	 */
	public function get_id() {
		return 'sandbox';
	}

	/**
	 * Get gateway title
	 *
	 * @return string
	 */
	public function get_title() {
		return 'حالت تست / آزمایشی (ثبت در لاگ)';
	}

	/**
	 * Simulate sending OTP
	 *
	 * @param string $recipient Recipient phone.
	 * @param string $code      OTP code.
	 * @return true|WP_Error
	 */
	public function send( $recipient, $code ) {
		return true;
	}
}
