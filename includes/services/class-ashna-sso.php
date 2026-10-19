<?php
/**
 * Social sign-in (OpenID Connect) for Google and Telegram.
 *
 * Design: docs/sso-design-FA.md. Two rules drive everything here:
 *   1. An account is only ever linked to a social identity after the provider proves the
 *      identity (a verified e-mail claim, a verified phone claim, or the opaque `sub` that was
 *      already bound to that account). A typed-in value never links anything.
 *   2. Every sign-in goes through Ashna_Session::login(), so the session, the 2FA/ban gates and
 *      the WooCommerce/Dokan redirects behave exactly like the OTP path.
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_SSO {

	/**
	 * Query flag that starts and finishes the flow.
	 */
	const ROUTE = 'ashna_sso';

	/**
	 * Cookie that binds a callback to the browser that started it (login-CSRF protection).
	 */
	const BIND_COOKIE = 'ashna_sso_bind';

	/**
	 * How long a started sign-in stays valid, in seconds.
	 */
	const STATE_TTL = 600;

	/**
	 * Path (relative to the site root) the provider returns to. It carries no query string on
	 * purpose: Google rejects a redirect URI that has one, and Telegram requires the exact value
	 * to be registered. The provider is read back from the server-side state instead.
	 */
	const CALLBACK_PATH = 'ashna-sso/callback';

	/**
	 * Wire the routes and the admin-ajax endpoints.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'handle_route' ), 1 );
		add_action( 'wp_ajax_ashna_sso_phone_start', array( __CLASS__, 'ajax_phone_start' ) );
		add_action( 'wp_ajax_ashna_sso_phone_verify', array( __CLASS__, 'ajax_phone_verify' ) );
		add_action( 'wp_ajax_ashna_sso_phone_dismiss', array( __CLASS__, 'ajax_phone_dismiss' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_phone_prompt' ), 5 );
	}

	/**
	 * Table that maps a provider identity to a WordPress user.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'ashna_sso_identities';
	}

	/**
	 * Supported providers.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function providers() {
		return array(
			'google'   => array(
				'label'    => 'گوگل',
				'icon'     => 'google',
				'discovery' => 'https://accounts.google.com/.well-known/openid-configuration',
				'issuer'   => 'https://accounts.google.com',
				'scopes'   => 'openid email profile',
				'email'    => true,
				'phone'    => false,
			),
			'telegram' => array(
				'label'    => 'تلگرام',
				'icon'     => 'telegram',
				'discovery' => 'https://oauth.telegram.org/.well-known/openid-configuration',
				'issuer'   => 'https://oauth.telegram.org',
				'scopes'   => 'openid profile phone',
				'email'    => false,
				'phone'    => true,
			),
		);
	}

	/**
	 * Settings key holding one value of a provider.
	 *
	 * @param string $provider Provider id.
	 * @param string $field    enabled|client_id|client_secret.
	 * @return string
	 */
	private static function setting_key( $provider, $field ) {
		return 'sso_' . $provider . '_' . $field;
	}

	/**
	 * A single provider setting.
	 *
	 * @param string $provider Provider id.
	 * @param string $field    enabled|client_id|client_secret.
	 * @param mixed  $default  Fallback.
	 * @return mixed
	 */
	public static function setting( $provider, $field, $default = '' ) {
		return Ashna_Helper::get_option( self::setting_key( $provider, $field ), $default );
	}

	/**
	 * Is the provider switched on and fully configured?
	 *
	 * @param string $provider Provider id.
	 * @return bool
	 */
	public static function is_ready( $provider ) {
		$providers = self::providers();
		if ( ! isset( $providers[ $provider ] ) ) {
			return false;
		}
		if ( ! Ashna_Helper::get_option( self::setting_key( $provider, 'enabled' ), 0 ) ) {
			return false;
		}
		$id     = trim( (string) self::setting( $provider, 'client_id' ) );
		$secret = (string) self::setting( $provider, 'client_secret' );

		return '' !== $id && '' !== $secret;
	}

	/**
	 * Providers a visitor can actually use right now.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function enabled_providers() {
		$out = array();
		foreach ( self::providers() as $id => $provider ) {
			if ( self::is_ready( $id ) ) {
				$out[ $id ] = $provider;
			}
		}
		return $out;
	}

	/**
	 * The callback URL to register at the provider (also shown in the settings screen).
	 *
	 * @param string $provider Provider id.
	 * @return string
	 */
	public static function redirect_uri( $provider = '' ) {
		unset( $provider ); // One address for both providers: less to register, less to mistype.
		return home_url( '/' . self::CALLBACK_PATH . '/' );
	}

	/**
	 * Is this request the provider returning to the callback path?
	 *
	 * Checked before WordPress resolves the query, so the flow works with any permalink
	 * structure and never reaches the 404 template.
	 *
	 * @return bool
	 */
	private static function is_callback_request() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( '' === $uri || false === strpos( $uri, 'ashna-sso' ) ) {
			return false;
		}

		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$path = '/' . trim( $path, '/' );
		$home = '/' . trim( $home, '/' );

		if ( '/' !== $home ) {
			if ( 0 !== strpos( $path, $home ) ) {
				return false;
			}
			$path = substr( $path, strlen( $home ) );
		}

		return '/' . trim( $path, '/' ) === '/' . self::CALLBACK_PATH;
	}

	/**
	 * Which provider a state belongs to, without consuming the state.
	 *
	 * @param string $state State from the query string.
	 * @return string Provider id, or '' when the state is unknown.
	 */
	public static function provider_for_state( $state ) {
		$state = preg_replace( '/[^a-f0-9]/', '', (string) $state );
		if ( '' === $state || 64 !== strlen( $state ) ) {
			return '';
		}
		$stored = get_transient( self::state_key( $state ) );
		return ( is_array( $stored ) && ! empty( $stored['provider'] ) ) ? (string) $stored['provider'] : '';
	}

	/**
	 * Where a login button points. Cheap on purpose: no HTTP, no database write. The state is
	 * created on the `start` request only.
	 *
	 * @param string $provider    Provider id.
	 * @param string $redirect_to Destination after a successful sign-in.
	 * @return string
	 */
	public static function start_url( $provider, $redirect_to = '' ) {
		$args = array(
			self::ROUTE => 'start',
			'provider'  => $provider,
		);
		if ( '' !== $redirect_to ) {
			$args['redirect_to'] = $redirect_to;
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * Human message for an error code (never leaks provider internals).
	 *
	 * @param string $code Error code.
	 * @return string
	 */
	public static function message_for( $code ) {
		$messages = array(
			'ashna_sso_disabled'    => 'این روش ورود در حال حاضر فعال نیست.',
			'ashna_sso_not_ready'   => 'ورود با این سرویس هنوز در تنظیمات کامل نشده است.',
			'ashna_sso_state'       => 'زمان ورود به پایان رسید یا نشست مرورگر عوض شد. لطفاً دوباره تلاش کنید.',
			'ashna_sso_browser'     => 'این ورود از مرورگر دیگری آغاز شده بود. لطفاً از همان مرورگر ادامه دهید.',
			'ashna_sso_denied'      => 'ورود در صفحهٔ سرویس لغو شد.',
			'ashna_sso_network'     => 'ارتباط با سرویس ورود برقرار نشد. چند لحظه بعد دوباره تلاش کنید.',
			'ashna_sso_token'       => 'پاسخ سرویس ورود معتبر نبود. لطفاً دوباره تلاش کنید.',
			'ashna_sso_email'       => 'سرویس ورود ایمیلی برنگرداند که قابل استفاده باشد.',
			'ashna_sso_unverified'  => 'ایمیل حساب شما در سرویس ورود تأیید نشده است؛ برای امنیت، ورود انجام نشد.',
			'ashna_sso_phone_taken' => 'این شماره روی حساب دیگری ثبت شده است. اول با همان شماره و کد پیامکی وارد شوید؛ از دفعهٔ بعد، ورود با تلگرام به همان حساب وصل می‌شود.',
			'ashna_sso_staff'       => 'حساب‌های مدیریتی فقط با رمز عبور وارد می‌شوند.',
			'ashna_sso_blocked'     => 'ورود این حساب در حال حاضر مجاز نیست.',
			'ashna_sso_rate'        => 'تلاش‌های ورود بیش از حد بود. چند دقیقه بعد دوباره امتحان کنید.',
			'ashna_sso_registration' => 'ساخت حساب جدید در این سایت غیرفعال است. با همان حساب قبلی وارد شوید.',
			'ashna_sso_unknown'     => 'ورود با این سرویس انجام نشد. لطفاً دوباره تلاش کنید.',
		);

		return isset( $messages[ $code ] ) ? $messages[ $code ] : 'ورود با این سرویس انجام نشد.';
	}

	/**
	 * The error currently being displayed (from the query string), already validated.
	 *
	 * @return string Empty when there is none.
	 */
	public static function current_error() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display of a known code.
		$code = isset( $_GET['ashna_sso_error'] ) ? sanitize_key( wp_unslash( $_GET['ashna_sso_error'] ) ) : '';
		if ( '' === $code || 0 !== strpos( $code, 'ashna_sso_' ) ) {
			return '';
		}
		return self::message_for( $code );
	}

	/**
	 * Route `?ashna_sso=start|callback`.
	 */
	public static function handle_route() {
		// A provider redirect must never be served from a page cache, and neither must the
		// request that creates the single-use state.
		if ( self::is_callback_request() ) {
			self::disable_cache();
			self::callback( '' );
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the OAuth state parameter is the nonce of this route.
		if ( ! isset( $_GET[ self::ROUTE ] ) ) {
			return;
		}
		$step     = sanitize_key( wp_unslash( $_GET[ self::ROUTE ] ) );
		$provider = isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( $_GET['provider'] ) ) : '';
		// phpcs:enable

		if ( 'start' === $step ) {
			self::disable_cache();
			self::start( $provider );
			return;
		}
		if ( 'callback' === $step ) {
			self::disable_cache();
			self::callback( $provider );
		}
	}

	/**
	 * Ask WordPress, the browser and page caches not to store this response.
	 */
	private static function disable_cache() {
		nocache_headers();
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
	}

	/**
	 * Begin a sign-in: create state + PKCE + nonce, then hand over to the provider.
	 *
	 * @param string $provider Provider id.
	 */
	private static function start( $provider ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- destination of a login button, validated in begin().
		$redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';

		$begun = self::begin( $provider, $redirect_to );
		if ( is_wp_error( $begun ) ) {
			self::fail( $begun->get_error_code(), '' );
		}

		wp_redirect( $begun['authorization_url'] ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- fixed, validated provider endpoint.
		exit;
	}

	/**
	 * Create the state, PKCE verifier and nonce for a provider, and store them.
	 *
	 * Split out from the route so the whole flow can be exercised without an HTTP redirect.
	 *
	 * @param string $provider    Provider id.
	 * @param string $redirect_to Requested destination (already raw, validated here).
	 * @return array|WP_Error { authorization_url, state, verifier, nonce, bind }
	 */
	public static function begin( $provider, $redirect_to = '' ) {
		$providers = self::providers();
		if ( ! isset( $providers[ $provider ] ) || ! Ashna_Helper::get_option( self::setting_key( $provider, 'enabled' ), 0 ) ) {
			return new WP_Error( 'ashna_sso_disabled' );
		}
		if ( ! self::is_ready( $provider ) ) {
			return new WP_Error( 'ashna_sso_not_ready' );
		}

		$endpoints = self::endpoints( $provider );
		if ( is_wp_error( $endpoints ) ) {
			return new WP_Error( 'ashna_sso_network' );
		}

		$redirect_to = $redirect_to ? wp_validate_redirect( $redirect_to, '' ) : '';

		$state    = bin2hex( random_bytes( 32 ) );
		$verifier = self::random_string( 64 );
		$nonce    = self::random_string( 32 );
		$bind     = self::bind_value( $state );

		set_transient(
			self::state_key( $state ),
			array(
				'provider' => $provider,
				'verifier' => $verifier,
				'nonce'    => $nonce,
				'redirect' => $redirect_to,
				'created'  => time(),
			),
			self::STATE_TTL
		);

		self::set_bind_cookie( $bind );

		$extra_params = ( 'google' === $provider ) ? array( 'prompt' => 'select_account' ) : array();

		$authorization_url = add_query_arg(
			$extra_params + array(
				'client_id'             => trim( (string) self::setting( $provider, 'client_id' ) ),
				'redirect_uri'          => self::redirect_uri( $provider ),
				'response_type'         => 'code',
				'scope'                 => $providers[ $provider ]['scopes'],
				'state'                 => $state,
				'nonce'                 => $nonce,
				'code_challenge'        => self::base64url( hash( 'sha256', $verifier, true ) ),
				'code_challenge_method' => 'S256',
			),
			$endpoints['authorization_endpoint']
		);

		return array(
			'authorization_url' => $authorization_url,
			'state'             => $state,
			'verifier'          => $verifier,
			'nonce'             => $nonce,
			'bind'              => $bind,
		);
	}

	/**
	 * Finish a sign-in: validate state, exchange the code, verify the ID token, resolve the user.
	 *
	 * @param string $provider Provider id.
	 */
	private static function callback( $provider ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- state is verified inside complete().
		$state = isset( $_GET['state'] ) ? (string) wp_unslash( $_GET['state'] ) : '';
		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$error = isset( $_GET['error'] ) ? sanitize_key( wp_unslash( $_GET['error'] ) ) : '';
		// phpcs:enable
		$bind = isset( $_COOKIE[ self::BIND_COOKIE ] ) ? (string) $_COOKIE[ self::BIND_COOKIE ] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared with hash_equals().

		if ( '' === $provider ) {
			$provider = self::provider_for_state( $state );
			if ( '' === $provider ) {
				// Unknown or expired state: that is an expired sign-in, not a configuration problem.
				self::fail( 'ashna_sso_state', '' );
			}
		}

		if ( '' !== $error ) {
			self::fail( 'ashna_sso_denied', '' );
		}

		$result = self::complete( $provider, $state, $code, $bind );
		if ( is_wp_error( $result ) ) {
			self::fail( $result->get_error_code(), (string) $result->get_error_data() );
		}

		$user   = $result['user'];
		$is_new = ! empty( $result['is_new'] );

		$logged_in = Ashna_Session::login(
			$user,
			array(
				'method'      => 'sso',
				'identifier'  => $result['identifier'],
				'is_new_user' => $is_new,
				'form'        => 'sso',
			)
		);
		if ( is_wp_error( $logged_in ) ) {
			self::fail( 'ashna_sso_blocked', (string) $result['redirect'] );
		}

		do_action( 'ashna_sso_login', $user, $provider, $is_new, $result['claims'] );

		$destination = Ashna_Session::redirect_url( $user, (string) $result['redirect'], array( 'is_new_user' => $is_new, 'form' => 'sso' ) );
		wp_safe_redirect( $destination );
		exit;
	}

	/**
	 * The whole callback as one testable step (no redirect, no exit).
	 *
	 * @param string $provider Provider id.
	 * @param string $state    State from the query string.
	 * @param string $code     Authorization code.
	 * @param string $bind     Binding cookie value.
	 * @return array|WP_Error { user, is_new, redirect, claims, identifier }
	 */
	public static function complete( $provider, $state, $code, $bind ) {
		$providers = self::providers();
		if ( ! isset( $providers[ $provider ] ) || ! self::is_ready( $provider ) ) {
			return new WP_Error( 'ashna_sso_disabled' );
		}

		// The callback is a public endpoint; keep it from becoming a probing or flooding tool.
		if ( ! self::allow_callback() ) {
			return new WP_Error( 'ashna_sso_rate' );
		}

		$state = preg_replace( '/[^a-f0-9]/', '', (string) $state );
		if ( '' === $state || strlen( $state ) !== 64 || '' === (string) $code ) {
			return new WP_Error( 'ashna_sso_state' );
		}

		$stored = get_transient( self::state_key( $state ) );
		delete_transient( self::state_key( $state ) ); // Single use, whatever happens next.
		self::set_bind_cookie( '', -1 );

		if ( ! is_array( $stored ) || empty( $stored['provider'] ) || $stored['provider'] !== $provider ) {
			return new WP_Error( 'ashna_sso_state' );
		}
		if ( empty( $stored['created'] ) || ( time() - (int) $stored['created'] ) > self::STATE_TTL ) {
			return new WP_Error( 'ashna_sso_state' );
		}

		$redirect_to = isset( $stored['redirect'] ) ? (string) $stored['redirect'] : '';

		// The callback must arrive in the same browser that started the flow.
		if ( '' === (string) $bind || ! hash_equals( self::bind_value( $state ), (string) $bind ) ) {
			return new WP_Error( 'ashna_sso_browser', '', $redirect_to );
		}

		$endpoints = self::endpoints( $provider );
		if ( is_wp_error( $endpoints ) ) {
			return new WP_Error( 'ashna_sso_network', '', $redirect_to );
		}

		$tokens = self::exchange_code( $provider, $code, (string) $stored['verifier'], $endpoints );
		if ( is_wp_error( $tokens ) ) {
			return new WP_Error( $tokens->get_error_code(), '', $redirect_to );
		}

		$id_token = isset( $tokens['id_token'] ) ? (string) $tokens['id_token'] : '';
		if ( '' === $id_token ) {
			return new WP_Error( 'ashna_sso_token', '', $redirect_to );
		}

		$claims = self::verify_id_token( $provider, $id_token, (string) $stored['nonce'], $endpoints );
		if ( is_wp_error( $claims ) ) {
			return new WP_Error( 'ashna_sso_token', '', $redirect_to );
		}

		$resolved = self::resolve_user( $provider, $claims );
		if ( is_wp_error( $resolved ) ) {
			return new WP_Error( $resolved->get_error_code(), '', $redirect_to );
		}

		return array(
			'user'       => $resolved['user'],
			'is_new'     => ! empty( $resolved['is_new'] ),
			'redirect'   => $redirect_to,
			'claims'     => $claims,
			'identifier' => $provider . ':' . (string) $claims['sub'],
		);
	}

	/**
	 * Redirect back to the site with a short error code, never with provider details.
	 *
	 * @param string $code        Error code.
	 * @param string $redirect_to Where to return (already validated when it came from the state).
	 */
	private static function fail( $code, $redirect_to = '' ) {
		// Only ever return to this site: the referer of a callback is the provider's own page.
		$target = $redirect_to ? wp_validate_redirect( $redirect_to, '' ) : '';
		$target = $target ? $target : home_url( '/' );
		$target = remove_query_arg( array( 'ashna_sso', 'code', 'state', 'provider', 'error' ), $target );
		$target = add_query_arg( array( 'ashna_sso_error' => $code ), $target );

		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Simple per-IP ceiling on the callback route.
	 *
	 * @return bool
	 */
	private static function allow_callback() {
		$ip  = Ashna_Helper::get_client_ip();
		$key = 'ashna_sso_cb_' . md5( $ip );
		$hit = (int) get_transient( $key );
		// Generous on purpose: many real visitors can share one CGNAT address, and a single
		// callback costs a transient read, not an SMS. It only has to stop a flood.
		if ( $hit >= 100 ) {
			return false;
		}
		set_transient( $key, $hit + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Provider endpoints, discovered once and cached.
	 *
	 * @param string $provider Provider id.
	 * @param bool   $refresh  Force a fresh discovery document.
	 * @return array|WP_Error {authorization_endpoint, token_endpoint, jwks_uri}
	 */
	public static function endpoints( $provider, $refresh = false ) {
		$providers = self::providers();
		if ( ! isset( $providers[ $provider ] ) ) {
			return new WP_Error( 'ashna_sso_unknown' );
		}

		$cache_key = 'ashna_sso_disc_' . $provider;
		if ( ! $refresh ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) && ! empty( $cached['authorization_endpoint'] ) && ! empty( $cached['token_endpoint'] ) ) {
				return $cached;
			}
		}

		$document = self::http_get( $providers[ $provider ]['discovery'], 10 );
		if ( is_wp_error( $document ) || ! is_array( $document ) ) {
			return new WP_Error( 'ashna_sso_network' );
		}

		$needed = array( 'authorization_endpoint', 'token_endpoint' );
		foreach ( $needed as $field ) {
			if ( empty( $document[ $field ] ) || ! is_string( $document[ $field ] ) ) {
				return new WP_Error( 'ashna_sso_network' );
			}
		}
		if ( empty( $document['jwks_uri'] ) ) {
			return new WP_Error( 'ashna_sso_network' );
		}

		$endpoints = array(
			'authorization_endpoint' => $document['authorization_endpoint'],
			'token_endpoint'         => $document['token_endpoint'],
			'jwks_uri'               => $document['jwks_uri'],
		);
		set_transient( $cache_key, $endpoints, 12 * HOUR_IN_SECONDS );

		return $endpoints;
	}

	/**
	 * Exchange an authorization code for tokens.
	 *
	 * @param string $provider  Provider id.
	 * @param string $code      Authorization code.
	 * @param string $verifier  PKCE verifier.
	 * @param array  $endpoints Discovery result.
	 * @return array|WP_Error Token response.
	 */
	private static function exchange_code( $provider, $code, $verifier, array $endpoints ) {
		$body = array(
			'grant_type'    => 'authorization_code',
			'code'          => $code,
			'redirect_uri'  => self::redirect_uri( $provider ),
			'client_id'     => trim( (string) self::setting( $provider, 'client_id' ) ),
			'code_verifier' => $verifier,
		);

		$response = self::http_post(
			$endpoints['token_endpoint'],
			array_merge( $body, array( 'client_secret' => (string) self::setting( $provider, 'client_secret' ) ) ),
			array()
		);

		// Some providers only accept HTTP Basic client authentication; retry once on a clear
		// "invalid_client" answer. The secret never lands in a URL or a log in either case.
		if ( is_array( $response ) && isset( $response['error'] ) && 'invalid_client' === $response['error'] ) {
			$basic     = trim( (string) self::setting( $provider, 'client_id' ) ) . ':' . (string) self::setting( $provider, 'client_secret' );
			$response  = self::http_post( $endpoints['token_endpoint'], $body, array( 'Authorization' => 'Basic ' . base64_encode( $basic ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic credentials, not obfuscation.
		}

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ashna_sso_network' );
		}
		if ( ! is_array( $response ) || empty( $response['id_token'] ) ) {
			return new WP_Error( 'ashna_sso_token' );
		}

		return $response;
	}

	/**
	 * Verify the ID token against the provider's published keys.
	 *
	 * @param string $provider  Provider id.
	 * @param string $id_token  Compact JWS.
	 * @param string $nonce     Nonce from the state.
	 * @param array  $endpoints Discovery result.
	 * @return array|WP_Error Claims.
	 */
	private static function verify_id_token( $provider, $id_token, $nonce, array $endpoints ) {
		$providers = self::providers();
		$args      = array(
			'iss'   => $providers[ $provider ]['issuer'],
			'aud'   => trim( (string) self::setting( $provider, 'client_id' ) ),
			'nonce' => $nonce,
		);

		$keys = self::jwks( $endpoints['jwks_uri'], false );
		if ( is_wp_error( $keys ) ) {
			return $keys;
		}

		$claims = Ashna_JWT::verify( $id_token, $keys, $args );
		if ( is_wp_error( $claims ) && 'ashna_jwt_kid' === $claims->get_error_code() ) {
			// Key rotation: fetch the JWKS again, once.
			$keys   = self::jwks( $endpoints['jwks_uri'], true );
			$claims = is_wp_error( $keys ) ? $keys : Ashna_JWT::verify( $id_token, $keys, $args );
		}

		return $claims;
	}

	/**
	 * Load (and cache) a JWKS document.
	 *
	 * @param string $url     JWKS URL.
	 * @param bool   $refresh Force a fetch.
	 * @return array|WP_Error List of keys.
	 */
	private static function jwks( $url, $refresh = false ) {
		$cache_key = 'ashna_sso_jwks_' . md5( $url );
		if ( ! $refresh ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) && ! empty( $cached ) ) {
				return $cached;
			}
		}

		$document = self::http_get( $url, 10 );
		if ( is_wp_error( $document ) || ! is_array( $document ) || empty( $document['keys'] ) || ! is_array( $document['keys'] ) ) {
			return new WP_Error( 'ashna_sso_network' );
		}

		set_transient( $cache_key, $document['keys'], 6 * HOUR_IN_SECONDS );

		return $document['keys'];
	}

	/**
	 * Turn verified claims into a signed-in WordPress user.
	 *
	 * @param string $provider Provider id.
	 * @param array  $claims   Verified ID-token claims.
	 * @return array|WP_Error {user: WP_User, is_new: bool}
	 */
	private static function resolve_user( $provider, array $claims ) {
		$subject = (string) $claims['sub'];

		// 1. A known identity always wins: the provider's opaque subject is the durable key.
		$identity = self::identity_by_subject( $provider, $subject );
		if ( $identity ) {
			$user = get_user_by( 'id', (int) $identity->user_id );
			if ( $user ) {
				self::touch_identity( (int) $identity->id );
				self::remember_profile( $user, $provider, $claims );
				return array( 'user' => $user, 'is_new' => false );
			}
			self::delete_identity( (int) $identity->id ); // The account is gone; drop the stale row.
		}

		$email    = '';
		$verified = false;

		if ( ! empty( $claims['email'] ) && is_string( $claims['email'] ) && is_email( $claims['email'] ) ) {
			$email = $claims['email'];
			// Google sends a boolean; some providers send "true". Only a positive value counts.
			$verified = isset( $claims['email_verified'] ) && filter_var( $claims['email_verified'], FILTER_VALIDATE_BOOLEAN );
		}

		// 2. Link to an existing account only when the provider proved the address.
		if ( '' !== $email ) {
			$existing = get_user_by( 'email', $email );
			if ( $existing ) {
				if ( ! $verified ) {
					return new WP_Error( 'ashna_sso_unverified' );
				}
				if ( Ashna_Auth::is_staff_account( $existing ) ) {
					return new WP_Error( 'ashna_sso_staff' );
				}
				$linked = self::link_identity( $provider, $subject, (int) $existing->ID, $email );
				if ( is_wp_error( $linked ) ) {
					return $linked;
				}
				self::remember_profile( $existing, $provider, $claims );
				return array( 'user' => $existing, 'is_new' => false );
			}
		}

		// 3. Telegram proves the phone number; that is as strong as an OTP to it.
		$phone = self::verified_phone( $provider, $claims );
		if ( is_wp_error( $phone ) ) {
			return $phone;
		}
		if ( '' !== $phone ) {
			$existing = Ashna_Auth::find_user( $phone, 'phone' );
			if ( $existing && $existing->exists() ) {
				if ( Ashna_Auth::is_staff_account( $existing ) ) {
					return new WP_Error( 'ashna_sso_staff' );
				}
				$linked = self::link_identity( $provider, $subject, (int) $existing->ID, '', $phone );
				if ( is_wp_error( $linked ) ) {
					return $linked;
				}
				self::remember_profile( $existing, $provider, $claims );
				return array( 'user' => $existing, 'is_new' => false );
			}
		}

		// 4. Nothing matched: create the account, with whatever proof we actually have.
		if ( ! Ashna_Helper::get_option( 'auto_register', 1 ) ) {
			return new WP_Error( 'ashna_sso_registration' );
		}

		$name = isset( $claims['name'] ) && is_string( $claims['name'] ) ? sanitize_text_field( $claims['name'] ) : '';
		if ( '' === $name && ! empty( $claims['given_name'] ) ) {
			$name = sanitize_text_field( (string) $claims['given_name'] );
		}

		if ( '' !== $email && $verified ) {
			$user = Ashna_Auth::register_user( $email, 'email', array( 'full_name' => $name ) );
		} elseif ( '' !== $phone ) {
			$user = Ashna_Auth::register_user( $phone, 'phone', array( 'full_name' => $name ) );
		} else {
			$user = self::create_profile_only_user( $provider, $subject, $name );
		}

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		if ( '' !== $phone ) {
			Ashna_Auth::sync_user_phone_meta( (int) $user->ID, $phone );
		}

		$linked = self::link_identity( $provider, $subject, (int) $user->ID, $email, $phone );
		if ( is_wp_error( $linked ) ) {
			// The brand-new account exists only to carry this identity. If the identity could not
			// be stored (a parallel request won the race), deleting it is the only outcome that
			// leaves neither an orphan account nor a second account on the next sign-in.
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( (int) $user->ID );
			return $linked;
		}

		self::remember_profile( $user, $provider, $claims );

		return array( 'user' => $user, 'is_new' => true );
	}

	/**
	 * The phone the provider itself has verified, or '' when none was shared.
	 *
	 * @param string $provider Provider id.
	 * @param array  $claims   Claims.
	 * @return string|WP_Error Normalized local phone.
	 */
	private static function verified_phone( $provider, array $claims ) {
		$providers = self::providers();
		if ( empty( $providers[ $provider ]['phone'] ) ) {
			return '';
		}
		if ( empty( $claims['phone_number'] ) || ! is_string( $claims['phone_number'] ) ) {
			return '';
		}
		if ( isset( $claims['phone_number_verified'] ) && ! filter_var( $claims['phone_number_verified'], FILTER_VALIDATE_BOOLEAN ) ) {
			return new WP_Error( 'ashna_sso_unverified' );
		}

		$phone = Ashna_Helper::normalize_phone( $claims['phone_number'] );
		if ( ! Ashna_Helper::is_valid_phone( $phone ) ) {
			// A number outside the Iranian mobile range cannot identify an Ashna account.
			return '';
		}

		return $phone;
	}

	/**
	 * Create an account for a provider that shared neither an e-mail nor a phone.
	 *
	 * @param string $provider Provider id.
	 * @param string $subject  Opaque subject.
	 * @param string $name     Display name.
	 * @return WP_User|WP_Error
	 */
	private static function create_profile_only_user( $provider, $subject, $name ) {
		$base = sanitize_user( $provider . '_' . substr( $subject, 0, 20 ), true );
		if ( '' === $base ) {
			$base = $provider . '_user';
		}
		$login = $base;
		$i     = 1;
		while ( username_exists( $login ) ) {
			$login = $base . '_' . $i;
			++$i;
		}

		$user_id = wp_insert_user(
			wp_slash(
				array(
					'user_login'   => $login,
					'user_pass'    => wp_generate_password( 32, true, true ),
					'display_name' => '' !== $name ? $name : $login,
					'first_name'   => '' !== $name ? $name : '',
					'role'         => Ashna_Auth::safe_registration_role( Ashna_Helper::get_option( 'default_user_role', 'customer' ) ),
				)
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$user = get_user_by( 'id', (int) $user_id );

		return $user ? $user : new WP_Error( 'ashna_sso_registration' );
	}

	/**
	 * Store the profile bits the provider gave us (name, avatar) without ever overwriting a value
	 * the visitor edited by hand.
	 *
	 * @param WP_User $user     User.
	 * @param string  $provider Provider id.
	 * @param array   $claims   Claims.
	 */
	private static function remember_profile( $user, $provider, array $claims ) {
		update_user_meta( $user->ID, 'ashna_sso_provider', $provider );

		if ( ! empty( $claims['picture'] ) && is_string( $claims['picture'] ) && ! get_user_meta( $user->ID, 'ashna_sso_avatar', true ) ) {
			update_user_meta( $user->ID, 'ashna_sso_avatar', esc_url_raw( $claims['picture'] ) );
		}
		if ( ! empty( $claims['preferred_username'] ) && ! get_user_meta( $user->ID, 'ashna_sso_username', true ) ) {
			update_user_meta( $user->ID, 'ashna_sso_username', sanitize_text_field( (string) $claims['preferred_username'] ) );
		}

		// Ask once. "Later" is honoured for a month, then the card may come back if the account
		// still has no phone to recover it with.
		$dismissed = (int) get_user_meta( $user->ID, 'ashna_sso_phone_dismissed', true );
		$muted     = $dismissed > 0 && ( time() - $dismissed ) < 30 * DAY_IN_SECONDS;

		if ( ! get_user_meta( $user->ID, 'ashna_phone', true ) && Ashna_Helper::get_option( 'sso_ask_phone', 1 ) && ! $muted ) {
			update_user_meta( $user->ID, 'ashna_sso_needs_phone', '1' );
		} else {
			delete_user_meta( $user->ID, 'ashna_sso_needs_phone' );
		}
	}

	// ---------------------------------------------------------------------
	// Identity storage
	// ---------------------------------------------------------------------

	/**
	 * Row for a provider subject.
	 *
	 * @param string $provider Provider id.
	 * @param string $subject  Subject.
	 * @return object|null
	 */
	private static function identity_by_subject( $provider, $subject ) {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE provider = %s AND subject = %s LIMIT 1", $provider, $subject ) );
	}

	/**
	 * Bind a provider subject to a user (idempotent, never rebinds to another user).
	 *
	 * @param string $provider Provider id.
	 * @param string $subject  Subject.
	 * @param int    $user_id  User id.
	 * @param string $email    Verified e-mail, if any.
	 * @param string $phone    Verified phone, if any.
	 * @return true|WP_Error
	 */
	private static function link_identity( $provider, $subject, $user_id, $email = '', $phone = '' ) {
		global $wpdb;

		$existing = self::identity_by_subject( $provider, $subject );
		if ( $existing ) {
			// Already bound: only the same user may use it. A different user id here means an
			// account was deleted and its id reused — refuse rather than silently rebind.
			return (int) $existing->user_id === (int) $user_id
				? true
				: new WP_Error( 'ashna_sso_blocked' );
		}

		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ok = $wpdb->insert(
			$table,
			array(
				'provider'   => $provider,
				'subject'    => $subject,
				'user_id'    => (int) $user_id,
				'email'      => $email,
				'phone'      => $phone,
				'created_at' => current_time( 'mysql', true ),
				'last_login' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false === $ok ) {
			// A parallel request won the race; accept it only if it bound the same user.
			$again = self::identity_by_subject( $provider, $subject );
			return ( $again && (int) $again->user_id === (int) $user_id ) ? true : new WP_Error( 'ashna_sso_blocked' );
		}

		update_user_meta( (int) $user_id, 'ashna_sso_' . $provider . '_sub', $subject );

		return true;
	}

	/**
	 * Update the "last seen" stamp.
	 *
	 * @param int $id Row id.
	 */
	private static function touch_identity( $id ) {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $table, array( 'last_login' => current_time( 'mysql', true ) ), array( 'id' => (int) $id ), array( '%s' ), array( '%d' ) );
	}

	/**
	 * Remove a row whose user no longer exists.
	 *
	 * @param int $id Row id.
	 */
	private static function delete_identity( $id ) {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );
	}

	// ---------------------------------------------------------------------
	// Link a phone to a signed-in social account
	// ---------------------------------------------------------------------

	/**
	 * Show the "add your phone" card to social accounts that have no phone yet.
	 */
	public static function render_phone_prompt() {
		if ( ! is_user_logged_in() || ! Ashna_Helper::get_option( 'sso_ask_phone', 1 ) ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( '1' !== (string) get_user_meta( $user_id, 'ashna_sso_needs_phone', true ) ) {
			return;
		}
		if ( '' !== (string) get_user_meta( $user_id, 'ashna_phone', true ) ) {
			delete_user_meta( $user_id, 'ashna_sso_needs_phone' );
			return;
		}

		// The card can be printed on a page where the frontend bundle never ran (a shortcode-only
		// theme, a cached page the card is injected into). Registering the handles here keeps the
		// card styled in that case without adding a second stylesheet to normal requests.
		if ( ! wp_style_is( 'ashna-otp-frontend', 'registered' ) ) {
			wp_register_style( 'ashna-fonts', ASHNA_OTP_URL . 'assets/css/fonts.css', array(), ASHNA_OTP_VERSION );
			$css_ver = ASHNA_OTP_VERSION . '.' . ( file_exists( ASHNA_OTP_PATH . 'assets/css/frontend.css' ) ? filemtime( ASHNA_OTP_PATH . 'assets/css/frontend.css' ) : '1' );
			wp_register_style( 'ashna-otp-frontend', ASHNA_OTP_URL . 'assets/css/frontend.css', array( 'ashna-fonts' ), $css_ver );
		}
		wp_enqueue_style( 'ashna-otp-frontend' );

		$js_ver = ASHNA_OTP_VERSION . '.' . ( file_exists( ASHNA_OTP_PATH . 'assets/js/sso.js' ) ? filemtime( ASHNA_OTP_PATH . 'assets/js/sso.js' ) : '1' );
		wp_enqueue_script( 'ashna-sso', ASHNA_OTP_URL . 'assets/js/sso.js', array(), $js_ver, true );
		wp_localize_script(
			'ashna-sso',
			'ashnaSSO',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ashna_sso_phone' ),
				'i18n'    => array(
					'sending' => 'در حال ارسال کد…',
					'verifying' => 'در حال بررسی…',
					'error'   => 'انجام نشد؛ دوباره تلاش کنید.',
					'wrong'   => 'کد وارد شده صحیح نیست.',
					'sent'    => 'کد تأیید به شمارهٔ شما پیامک شد.',
					'added'   => 'شمارهٔ شما ثبت شد. از این پس می‌توانید با کد پیامکی هم وارد شوید.',
				),
			)
		);

		include ASHNA_OTP_PATH . 'templates/sso-phone-prompt.php';
	}

	/**
	 * Step 1 of the phone link: send a code through the normal, rate-limited pipeline.
	 */
	public static function ajax_phone_start() {
		if ( ! self::verify_request() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in verify_request().
		$raw   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$phone = Ashna_Helper::normalize_phone( $raw );

		if ( ! Ashna_Helper::is_valid_phone( $phone ) ) {
			wp_send_json_error( array( 'message' => 'شمارهٔ موبایل معتبر نیست.' ) );
			return;
		}

		$owner = Ashna_Auth::find_user( $phone, 'phone' );
		if ( $owner && $owner->exists() && (int) $owner->ID !== get_current_user_id() ) {
			wp_send_json_error( array( 'message' => self::message_for( 'ashna_sso_phone_taken' ) ) );
			return;
		}

		$ip          = Ashna_Helper::get_client_ip();
		$reservation = Ashna_Rate_Limiter::reserve( $phone, $ip );
		if ( is_wp_error( $reservation ) ) {
			wp_send_json_error(
				array(
					'message' => $reservation->get_error_message(),
					'code'    => $reservation->get_error_code(),
				)
			);
			return;
		}

		$dispatch = Ashna_Gateway_Manager::dispatch_otp( $phone, 'phone', '', '', $reservation );
		if ( is_wp_error( $dispatch ) ) {
			wp_send_json_error( array( 'message' => 'ارسال کد در حال حاضر ممکن نشد. چند دقیقه بعد دوباره تلاش کنید.' ) );
			return;
		}

		wp_send_json_success(
			array(
				'message' => 'کد تأیید به شمارهٔ شما پیامک شد.',
				'phone'   => $phone,
				'masked'  => substr( $phone, 0, 4 ) . '***' . substr( $phone, -4 ),
			)
		);
	}

	/**
	 * Step 2 of the phone link: verify the code and store the number.
	 */
	public static function ajax_phone_verify() {
		if ( ! self::verify_request() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in verify_request().
		$raw   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$code  = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		$phone = Ashna_Helper::normalize_phone( $raw );

		if ( ! Ashna_Helper::is_valid_phone( $phone ) ) {
			wp_send_json_error( array( 'message' => 'شمارهٔ موبایل معتبر نیست.' ) );
			return;
		}

		$owner = Ashna_Auth::find_user( $phone, 'phone' );
		if ( $owner && $owner->exists() && (int) $owner->ID !== get_current_user_id() ) {
			wp_send_json_error( array( 'message' => self::message_for( 'ashna_sso_phone_taken' ) ) );
			return;
		}

		$verified = Ashna_Security::verify_otp( $phone, $code );
		if ( is_wp_error( $verified ) ) {
			wp_send_json_error(
				array(
					'message' => $verified->get_error_message(),
					'code'    => $verified->get_error_code(),
				)
			);
			return;
		}

		$user_id = get_current_user_id();
		Ashna_Auth::sync_user_phone_meta( $user_id, $phone );
		update_user_meta( $user_id, 'ashna_phone_verified', time() );
		delete_user_meta( $user_id, 'ashna_sso_needs_phone' );
		delete_user_meta( $user_id, 'ashna_sso_phone_dismissed' );

		do_action( 'ashna_sso_phone_linked', $user_id, $phone );

		wp_send_json_success(
			array(
				'message' => 'شمارهٔ شما ثبت و تأیید شد. از این پس می‌توانید با کد پیامکی هم وارد شوید.',
			)
		);
	}

	/**
	 * "Later": stop asking this account for a number for a while.
	 */
	public static function ajax_phone_dismiss() {
		if ( ! self::verify_request() ) {
			return;
		}
		$user_id = get_current_user_id();
		delete_user_meta( $user_id, 'ashna_sso_needs_phone' );
		update_user_meta( $user_id, 'ashna_sso_phone_dismissed', time() );
		wp_send_json_success( array( 'message' => 'باشه، بعداً.' ) );
	}

	/**
	 * Shared guard for the three phone endpoints: signed-in, nonce, POST.
	 */
	private static function verify_request() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'ابتدا وارد شوید.' ), 403 );
			return false; // wp_send_json ends the request in production; fail closed everywhere else.
		}

		$nonce = isset( $_POST['nonce'] ) && is_scalar( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ashna_sso_phone' ) ) {
			wp_send_json_error( array( 'message' => 'اعتبار صفحه به پایان رسیده بود؛ صفحه را تازه کنید.' ), 403 );
			return false;
		}

		return true;
	}

	// ---------------------------------------------------------------------
	// HTTP + helpers
	// ---------------------------------------------------------------------

	/**
	 * GET JSON, with a filter so tests (and a future self-hosted provider) can answer instead.
	 *
	 * @param string $url     URL.
	 * @param int    $timeout Seconds.
	 * @return array|WP_Error
	 */
	private static function http_get( $url, $timeout ) {
		$override = apply_filters( 'ashna_sso_http_response', null, $url, array( 'method' => 'GET' ) );
		if ( null !== $override ) {
			return $override;
		}

		$response = wp_remote_get( $url, array( 'timeout' => $timeout, 'redirection' => 2 ) );
		return self::parse_http( $response );
	}

	/**
	 * POST form data, with the same filter hook.
	 *
	 * @param string $url     URL.
	 * @param array  $body    Form fields.
	 * @param array  $headers Extra headers.
	 * @return array|WP_Error
	 */
	private static function http_post( $url, array $body, array $headers ) {
		$override = apply_filters( 'ashna_sso_http_response', null, $url, array( 'method' => 'POST', 'body' => $body, 'headers' => $headers ) );
		if ( null !== $override ) {
			return $override;
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 10,
				'headers' => $headers,
				'body'    => $body,
			)
		);
		return self::parse_http( $response );
	}

	/**
	 * Decode a wp_remote_* response.
	 *
	 * @param array|WP_Error $response Response.
	 * @return array|WP_Error
	 */
	private static function parse_http( $response ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ashna_sso_network' );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'ashna_sso_network' );
		}
		if ( $code < 200 || $code >= 300 ) {
			$error = isset( $body['error'] ) ? sanitize_key( (string) $body['error'] ) : 'error';
			return array( 'error' => $error );
		}
		return $body;
	}

	/**
	 * Transient key for a state value.
	 *
	 * @param string $state State.
	 * @return string
	 */
	public static function state_key( $state ) {
		return 'ashna_sso_st_' . substr( hash( 'sha256', $state ), 0, 40 );
	}

	/**
	 * Cookie value that binds a callback to this browser.
	 *
	 * @param string $state State.
	 * @return string
	 */
	private static function bind_value( $state ) {
		return hash_hmac( 'sha256', $state, wp_salt( 'auth' ) );
	}

	/**
	 * Write (or clear) the binding cookie.
	 *
	 * @param string $value   Value.
	 * @param int    $expires Expiry; negative clears.
	 */
	private static function set_bind_cookie( $value, $expires = 0 ) {
		if ( headers_sent() ) {
			return;
		}
		$options = array(
			'expires'  => $expires < 0 ? time() - HOUR_IN_SECONDS : time() + self::STATE_TTL,
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'domain'   => COOKIE_DOMAIN,
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		);
		setcookie( self::BIND_COOKIE, (string) $value, $options );
	}

	/**
	 * Random URL-safe string.
	 *
	 * @param int $bytes Entropy.
	 * @return string
	 */
	private static function random_string( $bytes ) {
		return self::base64url( random_bytes( $bytes ) );
	}

	/**
	 * Base64url encode.
	 *
	 * @param string $bytes Raw bytes.
	 * @return string
	 */
	private static function base64url( $bytes ) {
		return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- PKCE encoding, not obfuscation.
	}
}
