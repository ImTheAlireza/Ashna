<?php
/**
 * WordPress-native login session shared by every Ashna sign-in path (OTP, password, passkey).
 *
 * Ashna does not keep its own session or token store (no parallel JWT that could drift
 * from WordPress). A verified sign-in becomes exactly the session wp-login.php creates:
 * auth cookies backed by WP_Session_Tokens, the current user switched in the same
 * request, `wp_login` fired and — when WooCommerce is active — the guest cart/session
 * moved to the customer the same way wc_set_customer_auth_cookie() does it. Roles,
 * capabilities, WooCommerce permissions and membership levels are therefore read from
 * the normal WordPress user, and a re-login replaces the previous session instead of
 * stacking up stale ones.
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Session {

	/**
	 * Nonce action used by the public login form.
	 */
	const NONCE_ACTION = 'ashna_otp_nonce';

	/**
	 * Register AJAX endpoints.
	 */
	public static function init() {
		add_action( 'wp_ajax_nopriv_ashna_refresh_session', array( __CLASS__, 'ajax_refresh' ) );
		add_action( 'wp_ajax_ashna_refresh_session', array( __CLASS__, 'ajax_refresh' ) );
	}

	/**
	 * Verify the form nonce of an Ashna AJAX request.
	 *
	 * A stale nonce is normal for a login form: the page may come from a page cache, the
	 * visitor may have signed in or out in another tab, or the session may have expired.
	 * Instead of WordPress's bare "-1", answer with a machine-readable error and a fresh
	 * nonce for the *current* session so the form can retry once on its own.
	 *
	 * @param string $field Request field holding the nonce.
	 * @return bool true when the nonce is valid (a refusal ends the request instead).
	 */
	public static function verify_request( $field = 'nonce' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- this is the nonce check.
		$nonce = isset( $_REQUEST[ $field ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $field ] ) ) : '';
		if ( '' !== $nonce && false !== wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return true;
		}

		self::send_json(
			false,
			array(
				'code'      => 'ashna_nonce_expired',
				'message'   => 'اعتبار صفحه به پایان رسیده بود و به‌روز شد. لطفاً دوباره تلاش کنید.',
				'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
				'logged_in' => is_user_logged_in(),
			),
			403
		);

		// wp_send_json() ends the request, so this is only reachable in a test harness or if
		// something replaced the wp_die handler. Fail closed: every caller stops on anything
		// other than a strict true.
		return false;
	}

	/**
	 * Sign a verified user in, the WordPress way.
	 *
	 * @param WP_User $user    Verified user.
	 * @param array   $context { method: otp|password|passkey, identifier, is_new_user, authenticated }.
	 * @return true|WP_Error
	 */
	public static function login( $user, array $context = array() ) {
		$context = wp_parse_args(
			$context,
			array(
				'method'        => 'otp',
				'identifier'    => '',
				'is_new_user'   => false,
				'authenticated' => false, // true when wp_authenticate() already ran the full filter chain.
			)
		);

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return new WP_Error( 'ashna_invalid_user', 'حساب کاربری معتبر نیست.' );
		}

		$gate = self::check_gates( $user, $context );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		// Replace — never stack — the session this browser already had (re-login or account switch).
		if ( is_user_logged_in() ) {
			wp_destroy_current_session();
		}
		wp_clear_auth_cookie();

		$remember = (bool) apply_filters( 'ashna_otp_remember_login', (bool) Ashna_Helper::get_option( 'session_remember', 1 ), $user, $context );

		add_action( 'set_logged_in_cookie', array( __CLASS__, 'expose_logged_in_cookie' ), 10, 1 );
		add_filter( 'auth_cookie_expiration', array( __CLASS__, 'session_length' ), 99, 3 );
		wp_set_auth_cookie( $user->ID, $remember, is_ssl() );
		remove_filter( 'auth_cookie_expiration', array( __CLASS__, 'session_length' ), 99 );
		remove_action( 'set_logged_in_cookie', array( __CLASS__, 'expose_logged_in_cookie' ), 10 );

		wp_set_current_user( $user->ID );

		// WooCommerce: move the guest cart/session to the customer now, as wc_set_customer_auth_cookie() does.
		self::migrate_woocommerce_session();

		do_action( 'wp_login', $user->user_login, $user );

		/**
		 * Fires after Ashna signed a user in (any method).
		 *
		 * @param WP_User $user    Signed-in user.
		 * @param array   $context Sign-in context.
		 */
		do_action( 'ashna_otp_logged_in', $user, $context );

		return true;
	}

	/**
	 * Pre-login checks that WordPress and other plugins expect every sign-in to pass.
	 *
	 * @param WP_User $user    User.
	 * @param array   $context Sign-in context.
	 * @return true|WP_Error
	 */
	public static function check_gates( WP_User $user, array $context = array() ) {
		if ( is_multisite() && is_user_spammy( $user ) ) {
			return new WP_Error( 'ashna_spammer', 'این حساب کاربری مسدود شده است.' );
		}

		if ( empty( $context['authenticated'] ) && Ashna_Helper::get_option( 'respect_login_gates', 1 ) ) {
			// The same pre-login check wp-login.php runs for every sign-in, so approval, ban
			// and lockout plugins keep the final word even when no password is involved.
			$checked = apply_filters( 'wp_authenticate_user', $user, '' );
			if ( is_wp_error( $checked ) ) {
				return self::readable_error( $checked );
			}
			if ( ! $checked instanceof WP_User ) {
				return new WP_Error( 'ashna_login_denied', 'ورود به این حساب امکان‌پذیر نیست.' );
			}
		}

		/**
		 * Lets integrations refuse a sign-in (membership approval, banned accounts, ...).
		 *
		 * @param true|WP_Error $allowed True to allow.
		 * @param WP_User       $user    User.
		 * @param array         $context Sign-in context.
		 */
		$allowed = apply_filters( 'ashna_otp_can_login', true, $user, $context );
		if ( is_wp_error( $allowed ) ) {
			return self::readable_error( $allowed );
		}
		if ( true !== $allowed ) {
			return new WP_Error( 'ashna_login_denied', 'ورود به این حساب امکان‌پذیر نیست.' );
		}

		return true;
	}

	/**
	 * Make the new logged-in cookie visible to the rest of this request, so nonces
	 * created after the sign-in (returned to the browser) belong to the new session.
	 *
	 * @param string $logged_in_cookie Cookie value.
	 */
	public static function expose_logged_in_cookie( $logged_in_cookie ) {
		$_COOKIE[ LOGGED_IN_COOKIE ] = $logged_in_cookie;
	}

	/**
	 * Session length for "remembered" sign-ins (filter: auth_cookie_expiration).
	 *
	 * @param int  $length   Default length in seconds.
	 * @param int  $user_id  User ID.
	 * @param bool $remember Whether the session is remembered.
	 * @return int
	 */
	public static function session_length( $length, $user_id = 0, $remember = false ) {
		$days = absint( Ashna_Helper::get_option( 'session_days', 14 ) );
		if ( $remember && $days > 0 ) {
			return min( 365, $days ) * DAY_IN_SECONDS;
		}
		return $length;
	}

	/**
	 * Move the WooCommerce guest session/cart to the signed-in customer.
	 */
	private static function migrate_woocommerce_session() {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		$wc = WC();
		if ( is_object( $wc ) && isset( $wc->session ) && is_object( $wc->session ) && is_callable( array( $wc->session, 'init_session_cookie' ) ) ) {
			$wc->session->init_session_cookie();
		}
	}

	/**
	 * Data the browser needs after a sign-in (nonce valid for the new session).
	 *
	 * @return array
	 */
	public static function client_state() {
		return array(
			'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
			'logged_in' => is_user_logged_in(),
		);
	}

	/**
	 * AJAX: hand the form a nonce (and a math captcha) for the current session.
	 * Used for pages served from a cache, restored from the back/forward cache, or after a
	 * sign-in/out in another tab. Read-only: the response cannot be read cross-site.
	 */
	public static function ajax_refresh() {
		nocache_headers();
		$data = self::client_state();
		if ( ! is_user_logged_in() && class_exists( 'Ashna_Security' ) && Ashna_Security::math_captcha_active() ) {
			// One single-use question per form on the page (inline form + popup + checkout box).
			$data['captchas'] = array( Ashna_Security::generate_math_captcha(), Ashna_Security::generate_math_captcha(), Ashna_Security::generate_math_captcha() );
			$data['captcha']  = $data['captchas'][0];
		}
		self::send_json( true, $data );
		return; // wp_send_json() ends the request: never continue past a response.
	}

	/**
	 * Where to send a user after signing in. One resolver for OTP, password and passkey.
	 *
	 * Order: a destination pinned for this kind of account (admins / per role) → the page
	 * the visitor asked for → Ashna's redirect setting; then, unless disabled, the same
	 * filters wp-login.php (`login_redirect`) and WooCommerce My Account
	 * (`woocommerce_login_redirect`, used by Dokan for its vendor dashboard) apply.
	 *
	 * @param WP_User $user      Signed-in user.
	 * @param string  $requested Redirect requested by the form/URL.
	 * @param array   $context   { is_new_user: bool, form: string (form context, e.g. member-ultimatemember) }.
	 * @return string
	 */
	public static function redirect_url( $user, $requested = '', $context = array() ) {
		$context   = wp_parse_args(
			$context,
			array(
				'is_new_user' => false,
				'form'        => '',
			)
		);
		$fallback  = home_url( '/' );
		$requested = is_string( $requested ) && '' !== trim( $requested ) ? wp_validate_redirect( trim( $requested ), '' ) : '';

		$pinned = self::pinned_redirect( $user );
		if ( '' !== $pinned ) {
			return wp_validate_redirect( (string) apply_filters( 'ashna_otp_login_redirect', $pinned, $user, $requested ), $fallback );
		}

		$redirect = '' !== $requested ? $requested : self::default_redirect();

		if ( Ashna_Helper::get_option( 'respect_plugin_redirects', 1 ) ) {
			$chosen = $requested;
			if ( '' === $requested ) {
				/**
				 * Destination an integration picks when nothing was requested (e.g. Ultimate Member's
				 * per-role "after login" setting on its login page).
				 *
				 * @param string  $redirect Current destination.
				 * @param WP_User $user     User.
				 * @param array   $context  Sign-in context.
				 */
				$filtered = (string) apply_filters( 'ashna_otp_default_redirect', $redirect, $user, $context );
				if ( $filtered !== $redirect ) {
					$redirect = $filtered;
					$chosen   = $filtered;
				}
			}

			// wp-login.php semantics: plugins (e.g. Paid Memberships Pro) pick a page when the
			// destination is still empty; a requested or integration-chosen page is passed on.
			$from_login = apply_filters( 'login_redirect', $chosen, $requested, $user );
			if ( is_string( $from_login ) && '' !== $from_login ) {
				$redirect = $from_login;
			}

			// WooCommerce semantics: sign-ups use the registration redirect (Dokan: vendor setup
			// wizard / dashboard), sign-ins the My Account login redirect (Dokan: vendor dashboard).
			if ( class_exists( 'WooCommerce' ) ) {
				if ( $context['is_new_user'] ) {
					$filtered = apply_filters( 'woocommerce_registration_redirect', $redirect );
					$redirect = is_string( $filtered ) && '' !== $filtered ? str_replace( array( '&#038;', '&amp;' ), '&', $filtered ) : $redirect;
				} else {
					$redirect = self::woocommerce_login_redirect( $redirect, $user, $requested );
				}
			}
		}

		$redirect = (string) apply_filters( 'ashna_otp_login_redirect', $redirect, $user, $requested );
		return wp_validate_redirect( $redirect, $fallback );
	}

	/**
	 * Apply `woocommerce_login_redirect` with the request context WooCommerce/Dokan expect:
	 * a requested destination is visible as $_GET['redirect_to'] (Dokan honours it).
	 *
	 * @param string  $redirect  Current destination.
	 * @param WP_User $user      User.
	 * @param string  $requested Requested destination.
	 * @return string
	 */
	private static function woocommerce_login_redirect( $redirect, $user, $requested ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- temporary request context only.
		$had_get = array_key_exists( 'redirect_to', $_GET );
		$old_get = $had_get ? $_GET['redirect_to'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( '' !== $requested ) {
			$_GET['redirect_to'] = $requested;
		} else {
			unset( $_GET['redirect_to'] );
		}

		try {
			$filtered = apply_filters( 'woocommerce_login_redirect', $redirect, $user );
		} finally {
			if ( $had_get ) {
				$_GET['redirect_to'] = $old_get;
			} else {
				unset( $_GET['redirect_to'] );
			}
		}
		// phpcs:enable

		if ( ! is_string( $filtered ) || '' === $filtered ) {
			return $redirect;
		}
		// Some handlers return esc_url() output; turn HTML entities back into a usable URL.
		return str_replace( array( '&#038;', '&amp;' ), '&', $filtered );
	}

	/**
	 * Destination pinned by the site owner for this kind of account.
	 *
	 * @param WP_User $user User.
	 * @return string
	 */
	private static function pinned_redirect( $user ) {
		$admin_redirect = trim( (string) Ashna_Helper::get_option( 'admin_redirect_url', '' ) );
		if ( '' !== $admin_redirect && user_can( $user, 'manage_options' ) ) {
			return esc_url_raw( $admin_redirect );
		}

		/**
		 * Per-role destinations (filled by the integrations module).
		 *
		 * @param string  $url  Pinned URL or ''.
		 * @param WP_User $user User.
		 */
		$pinned = apply_filters( 'ashna_otp_pinned_redirect', '', $user );
		return is_string( $pinned ) ? trim( $pinned ) : '';
	}

	/**
	 * Destination from Ashna's "after sign-in" setting.
	 *
	 * @return string
	 */
	private static function default_redirect() {
		$behavior   = Ashna_Helper::get_option( 'redirect_behavior', 'auto' );
		$custom     = trim( (string) Ashna_Helper::get_option( 'custom_redirect_url', '' ) );
		$custom     = '' !== $custom ? $custom : trim( (string) Ashna_Helper::get_option( 'redirect_url', '' ) );
		$my_account = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '';

		if ( 'custom' === $behavior && '' !== $custom ) {
			return esc_url_raw( $custom );
		}
		if ( 'my_account' === $behavior || 'myaccount' === $behavior ) {
			return '' !== $my_account ? $my_account : admin_url( 'profile.php' );
		}
		if ( 'home' === $behavior ) {
			return home_url( '/' );
		}

		$referer = wp_get_referer();
		if ( ( 'auto' === $behavior || 'referer' === $behavior ) && $referer && ! self::is_login_screen( $referer ) ) {
			return wp_validate_redirect( $referer, home_url( '/' ) );
		}

		return '' !== $my_account ? $my_account : home_url( '/' );
	}

	/**
	 * Whether a URL is a sign-in screen (going back there after signing in is pointless).
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private static function is_login_screen( $url ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( false !== strpos( $path, 'wp-login.php' ) ) {
			return true;
		}
		$page_id = absint( Ashna_Helper::get_option( 'standalone_page_id', 0 ) );
		if ( $page_id > 0 ) {
			$login_path = (string) wp_parse_url( (string) get_permalink( $page_id ), PHP_URL_PATH );
			if ( '' !== $login_path && '/' !== $login_path && untrailingslashit( $login_path ) === untrailingslashit( $path ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Plain-text error for the form (plugin messages may contain markup).
	 *
	 * @param WP_Error $error Error.
	 * @return WP_Error
	 */
	public static function readable_error( WP_Error $error ) {
		$message = trim( wp_strip_all_tags( $error->get_error_message() ) );
		$message = preg_replace( '/^(error|خطا)\s*:\s*/iu', '', $message );
		return new WP_Error( $error->get_error_code(), '' !== $message ? $message : 'ورود به این حساب امکان‌پذیر نیست.' );
	}

	/**
	 * Send a JSON response with a clean buffer.
	 *
	 * @param bool  $success Success flag.
	 * @param array $data    Payload.
	 * @param int   $status  HTTP status.
	 */
	public static function send_json( $success, $data, $status = null ) {
		if ( ob_get_length() ) {
			ob_clean();
		}
		if ( $success ) {
			wp_send_json_success( $data, $status );
		}
		wp_send_json_error( $data, $status );
		return; // wp_send_json() ends the request: never continue past a response.
	}
}
