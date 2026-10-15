<?php
/**
 * Abstract Base Gateway Class for Ashna
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Ashna_Abstract_Gateway implements Ashna_Gateway_Interface {

	/**
	 * Helper to read a setting option
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Default fallback.
	 * @return mixed
	 */
	protected function get_setting( $key, $default = '' ) {
		return Ashna_Helper::get_option( $key, $default );
	}

	/**
	 * Send a JSON POST HTTP request
	 *
	 * @param string $url     Endpoint URL.
	 * @param array  $payload Array to JSON-encode.
	 * @param array  $headers Optional additional headers.
	 * @return array|WP_Error { code: int, body: array|null, raw: string }
	 */
	protected function post_json( $url, $payload, $headers = array() ) {
		$default_headers = array(
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		);

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array_merge( $default_headers, $headers ),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$raw = wp_remote_retrieve_body( $response );
		return array(
			'code' => (int) wp_remote_retrieve_response_code( $response ),
			'body' => json_decode( $raw, true ),
			'raw'  => $raw,
		);
	}
}
