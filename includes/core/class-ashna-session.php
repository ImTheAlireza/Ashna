<?php
/**
 * Finalize a verified identity with WordPress sessions, never a separate JWT.
 *
 * @package Ashna_OTP
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Ashna_Session {
	/**
	 * Create the native WP session after OTP/password/Passkey verification.
	 *
	 * Account-policy filters may deny access, but cannot substitute another user.
	 * Existing roles/capabilities are never replaced here. Third-party passwordless
	 * policies can use ashna_authenticate_user ($user, $method) to require extra steps.
	 *
	 * @param WP_User $user     Verified account.
	 * @param string  $method   otp, password or passkey.
	 * @param string  $password Actual password only for the password fallback.
	 * @return array|WP_Error Fresh authenticated nonce, or a denied login.
	 */
	public static function login( $user, $method, $password = '' ) {
		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return new WP_Error( 'ashna_invalid_user', 'حساب کاربری معتبر نیست.' );
		}

		$checked = apply_filters( 'wp_authenticate_user', $user, $password );
		if ( ! is_wp_error( $checked ) && function_exists( 'wp_authenticate_spam_check' ) ) {
			$checked = wp_authenticate_spam_check( $checked );
		}
		if ( ! is_wp_error( $checked ) ) {
			$checked = Ashna_Compatibility::filter( 'ashna_authenticate_user', $checked, $method );
		}
		if ( is_wp_error( $checked ) ) {
			return $checked;
		}
		if ( ! $checked instanceof WP_User || (int) $checked->ID !== (int) $user->ID ) {
			return new WP_Error( 'ashna_invalid_user', 'تأیید حساب کاربری نامعتبر است.' );
		}
		if ( headers_sent() ) {
			return new WP_Error( 'ashna_session_headers_sent', 'امکان ایجاد نشست امن وجود ندارد. لطفاً دوباره تلاش کنید.' );
		}

		$previous_user_id = get_current_user_id();
		$logged_in_cookie = '';
		$token            = '';
		$sent             = false;
		$capture          = static function ( $cookie, $expire, $expiration, $id, $scheme, $session_token ) use ( $user, &$logged_in_cookie, &$token ) {
			if ( (int) $id === (int) $user->ID ) {
				$logged_in_cookie = $cookie;
				$token            = $session_token;
			}
		};
		$observe = static function ( $send ) use ( &$sent ) {
			$sent = (bool) $send;
			return $send;
		};

		nocache_headers();
		add_action( 'set_logged_in_cookie', $capture, PHP_INT_MAX, 6 );
		add_filter( 'send_auth_cookies', $observe, PHP_INT_MAX );
		try {
			wp_set_current_user( $user->ID );
			// wp_set_auth_cookie creates a fresh WP_Session_Tokens entry and applies
			// core expiration, HTTPS and HttpOnly rules. Never build cookies ourselves.
			wp_set_auth_cookie( $user->ID, true, is_ssl() );
		} finally {
			remove_action( 'set_logged_in_cookie', $capture, PHP_INT_MAX );
			remove_filter( 'send_auth_cookies', $observe, PHP_INT_MAX );
		}
		if ( ! $sent || '' === $logged_in_cookie || '' === $token ) {
			if ( '' !== $token ) {
				WP_Session_Tokens::get_instance( $user->ID )->destroy( $token );
			}
			wp_set_current_user( $previous_user_id );
			return new WP_Error( 'ashna_session_cookie_blocked', 'ایجاد کوکی ورود مجاز نیست. تنظیمات امنیتی سایت را بررسی کنید.' );
		}

		// setcookie() changes the response, not $_COOKIE in this request. Core nonce
		// generation must see the newly issued token, not the anonymous/old cookie.
		$_COOKIE[ LOGGED_IN_COOKIE ] = $logged_in_cookie;

		// Match WooCommerce's own wc_set_customer_auth_cookie() session step. Let
		// its public API migrate the guest cart; never edit its session table directly.
		$woocommerce = function_exists( 'WC' ) ? WC() : null;
		if ( is_object( $woocommerce ) && isset( $woocommerce->session ) && is_callable( array( $woocommerce->session, 'init_session_cookie' ) ) ) {
			$woocommerce->session->init_session_cookie();
		}

		// WC's wp_login handler marks the saved cart for merging on the next request.
		do_action( 'wp_login', $user->user_login, $user );

		return array( 'nonce' => wp_create_nonce( 'ashna_otp_nonce' ) );
	}
}
