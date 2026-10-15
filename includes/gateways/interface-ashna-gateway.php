<?php
/**
 * Gateway Interface for Ashna
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Ashna_Gateway_Interface {

	/**
	 * Get gateway unique slug ID
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Get gateway Persian title
	 *
	 * @return string
	 */
	public function get_title();

	/**
	 * Send OTP code to recipient
	 *
	 * @param string $recipient Normalized phone number or email.
	 * @param string $code      Generated OTP code.
	 * @return true|WP_Error    True on success or WP_Error with failure details.
	 */
	public function send( $recipient, $code );
}
