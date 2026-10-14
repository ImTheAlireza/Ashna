<?php
/**
 * Compatibility for published content and third-party integrations from Signa.
 * All newly rendered markup and public documentation use Ashna.
 *
 * @package Ashna_OTP
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Ashna_Compatibility {
	private static $registered = false;

	public static function register() {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;
		foreach ( array( 'VERSION', 'FILE', 'PATH', 'URL', 'BASENAME' ) as $suffix ) {
			$old = 'SIGNA_OTP_' . $suffix;
			if ( ! defined( $old ) ) {
				define( $old, constant( 'ASHNA_OTP_' . $suffix ) );
			}
		}
		// Resolve existing third-party class references without loading every service.
		spl_autoload_register( function ( $class ) {
			if ( 0 === strpos( $class, 'Signa_' ) ) {
				$new = 'Ashna_' . substr( $class, 6 );
				if ( class_exists( $new ) || interface_exists( $new ) ) {
					class_alias( $new, $class );
				}
			}
		} );
		add_action( 'init', array( __CLASS__, 'register_content_aliases' ), 20 );
		// Keep existing scheduled jobs; the activator reuses this same hook name.
		add_action( 'signa_otp_daily_cleanup', array( 'Ashna_Logger', 'run_scheduled_cleanup' ) );
	}

	public static function register_content_aliases() {
		global $shortcode_tags;
		foreach ( array( 'otp_login', 'otp_form', 'otp_button', 'passkey_manager' ) as $name ) {
			if ( isset( $shortcode_tags[ 'ashna_' . $name ] ) ) {
				add_shortcode( 'signa_' . $name, $shortcode_tags[ 'ashna_' . $name ] );
			}
		}
	}

	/** Translate only our namespaced selectors, not words like "signature". */
	public static function markup( $markup ) {
		return str_replace( array( 'signa-', 'signa_', 'signaOtpParams' ), array( 'ashna-', 'ashna_', 'ashnaOtpParams' ), $markup );
	}

	/** Dispatch new extension hooks and their legacy names once each. */
	public static function filter( $hook, $value, ...$args ) {
		$value = apply_filters( 'signa_' . substr( $hook, 6 ), $value, ...$args );
		return apply_filters( $hook, $value, ...$args );
	}

	public static function action( $hook, ...$args ) {
		do_action( $hook, ...$args );
		do_action( 'signa_' . substr( $hook, 6 ), ...$args );
	}
}

if ( ! function_exists( 'signa_otp' ) ) {
	function signa_otp() {
		return ashna_otp();
	}
}
