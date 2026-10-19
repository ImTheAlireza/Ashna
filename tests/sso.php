<?php
/**
 * Social sign-in (Google / Telegram): the security properties, not the happy path only.
 *
 * Runs inside a disposable full WordPress with Ashna active (tests/run-full-wp.py sso).
 * The provider is simulated end to end with a real RSA keypair and signed ID tokens, so the
 * verifier, the account rules and the state machine are exercised without any network access.
 */

require_once __DIR__ . '/lib/wp-harness.php';

global $wpdb;

/**
 * Fake the provider's HTTP surface.
 */
class Ashna_SSO_Mock {

	/** @var string Current ID token handed back by the token endpoint. */
	public static $id_token = '';

	/** @var array Current JWKS. */
	public static $jwks = array();

	/** @var int Token endpoint calls. */
	public static $token_calls = 0;

	public static function filter( $pre, $url, $args ) {
		unset( $args );
		if ( false !== strpos( $url, 'openid-configuration' ) ) {
			$provider = false !== strpos( $url, 'telegram' ) ? 'telegram' : 'google';
			return array(
				'issuer'                 => 'google' === $provider ? 'https://accounts.google.com' : 'https://oauth.telegram.org',
				'authorization_endpoint' => 'https://auth.' . $provider . '.test/authorize',
				'token_endpoint'         => 'https://auth.' . $provider . '.test/token',
				'jwks_uri'               => 'https://auth.' . $provider . '.test/jwks.json',
			);
		}
		if ( false !== strpos( $url, '/jwks.json' ) ) {
			return array( 'keys' => self::$jwks );
		}
		if ( false !== strpos( $url, '/token' ) ) {
			++self::$token_calls;
			return array( 'id_token' => self::$id_token, 'access_token' => 'not-used' );
		}
		return $pre;
	}
}

add_filter( 'ashna_sso_http_response', array( 'Ashna_SSO_Mock', 'filter' ), 10, 3 );

/**
 * A throw-away RSA keypair for signing test tokens.
 */
function ashna_sso_keypair() {
	$options = array(
		'private_key_bits' => 2048,
		'private_key_type' => OPENSSL_KEYTYPE_RSA,
	);

	// php-wasm's OpenSSL cannot read its default config file; naming one explicitly fixes key
	// generation (signature verification itself needs no config). Real PHP ignores this list.
	$key = false;
	foreach ( array( '/etc/ssl/openssl.cnf', '/usr/lib/ssl/openssl.cnf', '/tmp/ashna-openssl.cnf', '/dev/null' ) as $config ) {
		if ( ! file_exists( $config ) && '/tmp/ashna-openssl.cnf' !== $config ) {
			continue;
		}
		if ( '/tmp/ashna-openssl.cnf' === $config && ! file_exists( $config ) ) {
			file_put_contents( $config, "[req]\ndistinguished_name = req_distinguished_name\n[req_distinguished_name]\n" ); // phpcs:ignore
		}
		$key = @openssl_pkey_new( $options + array( 'config' => $config ) ); // phpcs:ignore
		if ( $key ) {
			break;
		}
	}
	if ( ! $key ) {
		throw new RuntimeException( 'openssl_pkey_new() failed: ' . openssl_error_string() );
	}

	$details = openssl_pkey_get_details( $key );
	$jwk     = array(
		'kty' => 'RSA',
		'use' => 'sig',
		'alg' => 'RS256',
		'kid' => 'test-key-1',
		'n'   => rtrim( strtr( base64_encode( $details['rsa']['n'] ), '+/', '-_' ), '=' ), // phpcs:ignore
		'e'   => rtrim( strtr( base64_encode( $details['rsa']['e'] ), '+/', '-_' ), '=' ), // phpcs:ignore
	);
	return array( 'private' => $key, 'jwk' => $jwk );
}

/**
 * Build a signed ID token.
 *
 * @param resource|\OpenSSLAsymmetricKey $key      Private key.
 * @param array                          $claims   Claims.
 * @param string                         $alg      Header algorithm (to test the refusals).
 * @param string                         $kid      Key id.
 * @param string                         $force_sig Signature to force (for HS256/none tests).
 */
function ashna_sso_token( $key, array $claims, $alg = 'RS256', $kid = 'test-key-1', $force_sig = null ) {
	$b64 = static function ( $raw ) {
		return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' ); // phpcs:ignore
	};

	$header  = array( 'alg' => $alg, 'typ' => 'JWT' );
	if ( '' !== $kid ) {
		$header['kid'] = $kid;
	}
	$input = $b64( wp_json_encode( $header ) ) . '.' . $b64( wp_json_encode( $claims ) );

	if ( null !== $force_sig ) {
		return $input . '.' . $force_sig;
	}

	openssl_sign( $input, $signature, $key, OPENSSL_ALGO_SHA256 );
	return $input . '.' . $b64( $signature );
}


/**
 * Start a flow and mint a token whose nonce matches the stored state.
 *
 * @param string $provider Provider id.
 * @param mixed  $key      Private key.
 * @param array  $claims   Claims to override.
 * @return array|WP_Error begin() result.
 */
function ashna_sso_begin_with_token( $provider, $key, array $claims = array() ) {
	$begun = Ashna_SSO::begin( $provider, '' );
	if ( is_wp_error( $begun ) ) {
		return $begun;
	}
	$stored = get_transient( Ashna_SSO::state_key( $begun['state'] ) );
	if ( ! is_array( $stored ) ) {
		throw new RuntimeException( 'state not stored' );
	}
	$claims['nonce']          = $stored['nonce'];
	Ashna_SSO_Mock::$id_token = ashna_sso_token( $key, array_merge( ashna_sso_claims(), $claims ) );
	return $begun;
}

/**
 * Base claims for a Google sign-in.
 */
function ashna_sso_claims( array $over = array() ) {
	return array_merge(
		array(
			'iss'            => 'https://accounts.google.com',
			'aud'            => 'google-client-id',
			'sub'            => 'google-subject-0001',
			'iat'            => time(),
			'exp'            => time() + 600,
			'nonce'          => 'the-nonce',
			'email'          => 'sso.tester@example.com',
			'email_verified' => true,
			'name'           => 'کاربر آزمایشی',
			'picture'        => 'https://example.com/a.png',
		),
		$over
	);
}

/**
 * Provider settings + a clean state for one scenario.
 */
function ashna_sso_ready( array $over = array() ) {
	ashna_t_settings(
		array_merge(
			array(
				'sso_google_enabled'         => 1,
				'sso_google_client_id'       => 'google-client-id',
				'sso_google_client_secret'   => 'google-client-secret',
				'sso_telegram_enabled'       => 1,
				'sso_telegram_client_id'     => '1234567890',
				'sso_telegram_client_secret' => 'telegram-client-secret',
				'sso_show_on_forms'          => 1,
				'sso_ask_phone'              => 1,
				'auto_register'              => 1,
			),
			$over
		)
	);

	// Discovery / JWKS caches must not leak between scenarios.
	foreach ( array( 'google', 'telegram' ) as $provider ) {
		delete_transient( 'ashna_sso_disc_' . $provider );
	}
	delete_transient( 'ashna_sso_jwks_' . md5( 'https://auth.google.test/jwks.json' ) );
	delete_transient( 'ashna_sso_jwks_' . md5( 'https://auth.telegram.test/jwks.json' ) );
	delete_transient( 'ashna_sso_cb_' . md5( Ashna_Helper::get_client_ip() ) );
}

/** Remove anything a scenario created. */
function ashna_sso_cleanup() {
	global $wpdb;
	$wpdb->query( 'DELETE FROM ' . Ashna_SSO::table_name() ); // phpcs:ignore
	foreach ( get_users( array( 'search' => 'sso.tester*', 'search_columns' => array( 'user_email', 'user_login' ) ) ) as $user ) {
		wp_delete_user( $user->ID );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( array( 'tg_user*', 'sso_existing*', 'sso_other*', 'sso_admin*', 'sso_no_phone*', 'sso_owner*', 'sso_phone_user*', 'same.person*', 'brand.new*', 'state-test*', 'link-ok*', 'no-auto*', 'card.user*' ) as $pattern ) {
		foreach ( get_users( array( 'search' => $pattern, 'search_columns' => array( 'user_login' ) ) ) as $user ) {
			wp_delete_user( $user->ID );
		}
	}
}

$ashna_sso_keys = ashna_sso_keypair();
Ashna_SSO_Mock::$jwks     = array( $ashna_sso_keys['jwk'] );
Ashna_SSO_Mock::$id_token = '';

// ---------------------------------------------------------------------------
// 1. The verifier itself: every classic JWT bypass must be refused.
// ---------------------------------------------------------------------------
scenario(
	'ID token verification refuses every classic bypass',
	function () use ( $ashna_sso_keys ) {
		$keys  = array( $ashna_sso_keys['jwk'] );
		$args  = array(
			'iss'   => 'https://accounts.google.com',
			'aud'   => 'google-client-id',
			'nonce' => 'the-nonce',
		);
		$token = ashna_sso_token( $ashna_sso_keys['private'], ashna_sso_claims() );

		$ok = Ashna_JWT::verify( $token, $keys, $args );
		check( 'a genuine RS256 token verifies', is_array( $ok ) && 'google-subject-0001' === $ok['sub'], is_wp_error( $ok ) ? $ok->get_error_code() : $ok );

		// Tamper with the payload: signature no longer matches.
		$parts    = explode( '.', $token );
		$payload  = json_decode( Ashna_JWT::b64url_decode( $parts[1] ), true );
		$payload['sub'] = 'attacker';
		$tampered = $parts[0] . '.' . rtrim( strtr( base64_encode( wp_json_encode( $payload ) ), '+/', '-_' ), '=' ) . '.' . $parts[2];
		$res      = Ashna_JWT::verify( $tampered, $keys, $args );
		check( 'a tampered payload is refused', is_wp_error( $res ) && 'ashna_jwt_signature' === $res->get_error_code() );

		$none = ashna_sso_token( $ashna_sso_keys['private'], ashna_sso_claims(), 'none', 'test-key-1', 'AAAA' );
		$res  = Ashna_JWT::verify( $none, $keys, $args );
		check( 'alg=none is refused', is_wp_error( $res ) && 'ashna_jwt_alg' === $res->get_error_code() );

		// Algorithm confusion: sign with HMAC using the client secret as the key.
		$header    = rtrim( strtr( base64_encode( wp_json_encode( array( 'alg' => 'HS256', 'typ' => 'JWT', 'kid' => 'test-key-1' ) ) ), '+/', '-_' ), '=' );
		$body      = rtrim( strtr( base64_encode( wp_json_encode( ashna_sso_claims() ) ), '+/', '-_' ), '=' );
		$hs_sig    = rtrim( strtr( base64_encode( hash_hmac( 'sha256', $header . '.' . $body, 'google-client-secret', true ) ), '+/', '-_' ), '=' );
		$res       = Ashna_JWT::verify( $header . '.' . $body . '.' . $hs_sig, $keys, $args );
		check( 'alg=HS256 is refused (no algorithm confusion)', is_wp_error( $res ) && 'ashna_jwt_alg' === $res->get_error_code() );

		$wrong_aud = ashna_sso_token( $ashna_sso_keys['private'], ashna_sso_claims( array( 'aud' => 'someone-else' ) ) );
		$res       = Ashna_JWT::verify( $wrong_aud, $keys, $args );
		check( 'a token issued for another client is refused', is_wp_error( $res ) && 'ashna_jwt_aud' === $res->get_error_code() );

		$wrong_iss = ashna_sso_token( $ashna_sso_keys['private'], ashna_sso_claims( array( 'iss' => 'https://evil.test' ) ) );
		$res       = Ashna_JWT::verify( $wrong_iss, $keys, $args );
		check( 'a token from another issuer is refused', is_wp_error( $res ) && 'ashna_jwt_iss' === $res->get_error_code() );

		$expired = ashna_sso_token( $ashna_sso_keys['private'], ashna_sso_claims( array( 'exp' => time() - 3600 ) ) );
		$res     = Ashna_JWT::verify( $expired, $keys, $args );
		check( 'an expired token is refused', is_wp_error( $res ) && 'ashna_jwt_expired' === $res->get_error_code() );

		$wrong_nonce = ashna_sso_token( $ashna_sso_keys['private'], ashna_sso_claims( array( 'nonce' => 'another' ) ) );
		$res         = Ashna_JWT::verify( $wrong_nonce, $keys, $args );
		check( 'a token with another nonce is refused (replay)', is_wp_error( $res ) && 'ashna_jwt_nonce' === $res->get_error_code() );

		$unknown_kid = ashna_sso_token( $ashna_sso_keys['private'], ashna_sso_claims(), 'RS256', 'rotated-key' );
		$res         = Ashna_JWT::verify( $unknown_kid, $keys, $args );
		check( 'an unknown key id asks the caller to refetch', is_wp_error( $res ) && 'ashna_jwt_kid' === $res->get_error_code() );

		$no_kid = ashna_sso_token( $ashna_sso_keys['private'], ashna_sso_claims(), 'RS256', '' );
		$res    = Ashna_JWT::verify( $no_kid, $keys, $args );
		check( 'a token without kid still verifies when only one RSA key exists', is_array( $res ) );
	}
);

// ---------------------------------------------------------------------------
// 2. begin(): state, PKCE, nonce, and what a visitor cannot influence.
// ---------------------------------------------------------------------------
scenario(
	'Starting a sign-in builds a safe authorization request',
	function () {
		ashna_sso_ready();

		$same_site = home_url( '/thanks/' );
		$begun     = Ashna_SSO::begin( 'google', $same_site );
		check( 'begin() succeeds for a configured provider', is_array( $begun ), is_wp_error( $begun ) ? $begun->get_error_code() : '' );
		if ( ! is_array( $begun ) ) {
			return;
		}

		$url  = $begun['authorization_url'];
		$args = array();
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $args );

		check( 'authorization URL points at the discovered endpoint', 0 === strpos( $url, 'https://auth.google.test/authorize?' ), $url );
		check( 'client_id is sent', isset( $args['client_id'] ) && 'google-client-id' === $args['client_id'] );
		check( 'response_type is code', isset( $args['response_type'] ) && 'code' === $args['response_type'] );
		check( 'scope asks only for what is needed', isset( $args['scope'] ) && 'openid email profile' === $args['scope'], $args['scope'] ?? '' );
		check( 'state is 64 hex characters', isset( $args['state'] ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $args['state'] ) );
		check( 'nonce is present', ! empty( $args['nonce'] ) );
		check( 'PKCE method is S256', isset( $args['code_challenge_method'] ) && 'S256' === $args['code_challenge_method'] );
		check( 'redirect_uri is this site, not a caller-supplied URL', isset( $args['redirect_uri'] ) && 0 === strpos( $args['redirect_uri'], home_url() ), $args['redirect_uri'] ?? '' );

		// Google refuses a redirect URI with a query string; Telegram wants the exact value registered.
		$clean_uri = home_url( '/ashna-sso/callback/' );
		check( 'redirect_uri is a clean path without a query string', isset( $args['redirect_uri'] ) && false === strpos( $args['redirect_uri'], '?' ) && $clean_uri === $args['redirect_uri'], $args['redirect_uri'] ?? '' );
		check( 'both providers return to the very same address', $clean_uri === Ashna_SSO::redirect_uri( 'telegram' ) && $clean_uri === Ashna_SSO::redirect_uri( 'google' ) );
		check( 'the state names its provider without being consumed', isset( $begun['state'] ) && 'google' === Ashna_SSO::provider_for_state( $begun['state'] ) );
		check( 'an unknown state names no provider', '' === Ashna_SSO::provider_for_state( 'deadbeef' ) );

		$stored = get_transient( Ashna_SSO::state_key( $begun['state'] ) );
		check( 'state is stored server-side', is_array( $stored ) && $begun['state'] === $args['state'] );

		if ( is_array( $stored ) ) {
			$expected = rtrim( strtr( base64_encode( hash( 'sha256', $stored['verifier'], true ) ), '+/', '-_' ), '=' ); // phpcs:ignore
			check( 'PKCE challenge is the S256 of the stored verifier', isset( $args['code_challenge'] ) && hash_equals( $expected, $args['code_challenge'] ) );
			check( 'nonce in the URL is the stored one', $args['nonce'] === $stored['nonce'] );
			check( 'same-site redirect target is kept', $same_site === $stored['redirect'], $stored['redirect'] );
		}

		// A login button must not become an open redirect.
		$off = Ashna_SSO::begin( 'google', 'https://evil.example/steal' );
		$off_stored = is_array( $off ) ? get_transient( Ashna_SSO::state_key( $off['state'] ) ) : array();
		check( 'an off-site redirect target is dropped', is_array( $off_stored ) && '' === $off_stored['redirect'] );

		$disabled = ashna_t_settings( array( 'sso_google_enabled' => 0 ) );
		unset( $disabled );
		$res = Ashna_SSO::begin( 'google', '' );
		check( 'a disabled provider cannot start a flow', is_wp_error( $res ) && 'ashna_sso_disabled' === $res->get_error_code() );

		ashna_t_settings(
			array(
				'sso_google_enabled'       => 1,
				'sso_google_client_secret' => '',
			)
		);
		$res = Ashna_SSO::begin( 'google', '' );
		check( 'a provider without a secret cannot start a flow', is_wp_error( $res ) && 'ashna_sso_not_ready' === $res->get_error_code() );

		ashna_sso_ready();
	}
);

// ---------------------------------------------------------------------------
// 2b. The clean callback path is recognised before WordPress resolves the query.
// ---------------------------------------------------------------------------
scenario(
	'The callback path is matched without a query string',
	function () {
		$method = new ReflectionMethod( 'Ashna_SSO', 'is_callback_request' );
		$method->setAccessible( true );

		$saved = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null;
		$base  = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );

		$_SERVER['REQUEST_URI'] = $base . '/ashna-sso/callback/?code=abc&state=' . str_repeat( 'a', 64 );
		check( 'the clean callback path matches', $method->invoke( null ) );

		$_SERVER['REQUEST_URI'] = $base . '/ashna-sso/callback';
		check( 'a missing trailing slash still matches', $method->invoke( null ) );

		$_SERVER['REQUEST_URI'] = $base . '/ashna-sso/callback/extra';
		check( 'a longer path is not the callback', ! $method->invoke( null ) );

		$_SERVER['REQUEST_URI'] = $base . '/ashna-sso/start/?provider=google';
		check( 'the start link is not mistaken for a callback', ! $method->invoke( null ) );

		$_SERVER['REQUEST_URI'] = $base . '/some-page/';
		check( 'an ordinary page is not the callback', ! $method->invoke( null ) );

		$_SERVER['REQUEST_URI'] = $base . '/?ashna_sso=callback&provider=google';
		check( 'the query route still exists for debugging', ! $method->invoke( null ) );

		if ( null === $saved ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $saved;
		}
	}
);

// ---------------------------------------------------------------------------
// 3. complete(): the account rules.
// ---------------------------------------------------------------------------
scenario(
	'A new Google account is created, then reused by its subject',
	function () use ( $ashna_sso_keys ) {
		ashna_t_settings( array( 'sso_ask_phone' => 1 ) );
		ashna_sso_cleanup();
		$begun = ashna_sso_begin_with_token( 'google', $ashna_sso_keys['private'] );
		$res   = Ashna_SSO::complete( 'google', $begun['state'], 'the-code', $begun['bind'] );

		check( 'the first sign-in creates an account', is_array( $res ) && ! empty( $res['is_new'] ), is_wp_error( $res ) ? $res->get_error_code() : '' );
		if ( ! is_array( $res ) ) {
			return;
		}

		$user = $res['user'];
		check( 'the account carries the verified email', 'sso.tester@example.com' === $user->user_email );
		check( 'the identity row was written', (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . Ashna_SSO::table_name() ) === 1 ); // phpcs:ignore

		$needs_phone = get_user_meta( $user->ID, 'ashna_sso_needs_phone', true );
		check( 'a Google account without a phone is asked once for one', '1' === (string) $needs_phone );

		// Second sign-in, same subject, brand new state.
		$begun2 = ashna_sso_begin_with_token( 'google', $ashna_sso_keys['private'] );
		$res2   = Ashna_SSO::complete( 'google', $begun2['state'], 'another-code', $begun2['bind'] );
		check( 'a returning subject signs into the same account', is_array( $res2 ) && (int) $res2['user']->ID === (int) $user->ID && false === $res2['is_new'] );
	}
);

scenario(
	'One-time state, browser binding and tampering are enforced',
	function () use ( $ashna_sso_keys ) {
		ashna_sso_ready();
		$begun = ashna_sso_begin_with_token( 'google', $ashna_sso_keys['private'], array( 'sub' => 'state-test-sub' ) );

		// Wrong browser.
		$res = Ashna_SSO::complete( 'google', $begun['state'], 'code', 'not-the-binding-cookie' );
		check( 'a callback from another browser is refused', is_wp_error( $res ) && 'ashna_sso_browser' === $res->get_error_code() );

		// The state was consumed by the failed attempt: a replay cannot succeed either.
		$res = Ashna_SSO::complete( 'google', $begun['state'], 'code', $begun['bind'] );
		check( 'a state value is single-use', is_wp_error( $res ) && 'ashna_sso_state' === $res->get_error_code() );

		$begun = Ashna_SSO::begin( 'google', '' );
		$res   = Ashna_SSO::complete( 'google', 'deadbeef', 'code', $begun['bind'] );
		check( 'an unknown state is refused', is_wp_error( $res ) && 'ashna_sso_state' === $res->get_error_code() );

		$begun = Ashna_SSO::begin( 'google', '' );
		set_transient(
			Ashna_SSO::state_key( $begun['state'] ),
			array(
				'provider' => 'google',
				'verifier' => 'x',
				'nonce'    => 'y',
				'redirect' => '',
				'created'  => time() - 3600,
			),
			HOUR_IN_SECONDS
		);
		$res = Ashna_SSO::complete( 'google', $begun['state'], 'code', $begun['bind'] );
		check( 'an expired state is refused', is_wp_error( $res ) && 'ashna_sso_state' === $res->get_error_code() );

		// A token that fails verification never reaches account resolution.
		$begun                    = ashna_sso_begin_with_token( 'google', $ashna_sso_keys['private'], array( 'exp' => time() - 3600 ) );
		$res                      = Ashna_SSO::complete( 'google', $begun['state'], 'code', $begun['bind'] );
		check( 'an invalid ID token stops the sign-in', is_wp_error( $res ) && 'ashna_sso_token' === $res->get_error_code() );
	}
);

scenario(
	'Linking never happens without proof of ownership',
	function () use ( $ashna_sso_keys ) {
		ashna_sso_ready();
		ashna_sso_cleanup();

		$target = make_user( 'sso_existing', 'subscriber', array(), 'pass-12345' );
		wp_update_user( array( 'ID' => $target->ID, 'user_email' => 'sso.existing@example.com' ) );

		// 1. Verified email -> link.
		$begun = ashna_sso_begin_with_token(
			'google',
			$ashna_sso_keys['private'],
			array( 'sub' => 'link-ok', 'email' => 'sso.existing@example.com', 'email_verified' => true )
		);
		$res   = Ashna_SSO::complete( 'google', $begun['state'], 'code', $begun['bind'] );
		check( 'a verified email links to the existing account', is_array( $res ) && (int) $res['user']->ID === (int) $target->ID && false === $res['is_new'], is_wp_error( $res ) ? $res->get_error_code() : '' );

		// 2. Unverified email -> refuse, no link, no account.
		$other = make_user( 'sso_other', 'subscriber', array(), 'pass-12345' );
		wp_update_user( array( 'ID' => $other->ID, 'user_email' => 'sso.other@example.com' ) );
		$before = (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . Ashna_SSO::table_name() ); // phpcs:ignore

		$begun = ashna_sso_begin_with_token(
			'google',
			$ashna_sso_keys['private'],
			array( 'sub' => 'link-bad', 'email' => 'sso.other@example.com', 'email_verified' => false )
		);
		$res   = Ashna_SSO::complete( 'google', $begun['state'], 'code', $begun['bind'] );
		check( 'an unverified email cannot take over an account', is_wp_error( $res ) && 'ashna_sso_unverified' === $res->get_error_code() );
		check( 'the refused attempt wrote no identity row', (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . Ashna_SSO::table_name() ) === $before ); // phpcs:ignore
		check( 'the target account was not modified', (string) get_userdata( $other->ID )->user_email === 'sso.other@example.com' );

		// 3. Staff accounts are password-only.
		$admin = make_user( 'sso_admin', 'administrator', array(), 'pass-12345' );
		wp_update_user( array( 'ID' => $admin->ID, 'user_email' => 'sso.admin@example.com' ) );
		$begun = ashna_sso_begin_with_token(
			'google',
			$ashna_sso_keys['private'],
			array( 'sub' => 'link-admin', 'email' => 'sso.admin@example.com', 'email_verified' => true )
		);
		$res   = Ashna_SSO::complete( 'google', $begun['state'], 'code', $begun['bind'] );
		check( 'an administrator cannot sign in through Google', is_wp_error( $res ) && 'ashna_sso_staff' === $res->get_error_code() );

		// 4. auto_register off: no account is invented.
		ashna_t_settings( array( 'auto_register' => 0 ) );
		$begun = ashna_sso_begin_with_token(
			'google',
			$ashna_sso_keys['private'],
			array( 'sub' => 'no-auto', 'email' => 'brand.new@example.com', 'email_verified' => true )
		);
		$res   = Ashna_SSO::complete( 'google', $begun['state'], 'code', $begun['bind'] );
		check( 'with auto-registration off no account is created', is_wp_error( $res ) && 'ashna_sso_registration' === $res->get_error_code() );

		ashna_t_settings( array( 'auto_register' => 1 ) );
	}
);

scenario(
	'Telegram: verified phone links, and a second provider can share one account',
	function () use ( $ashna_sso_keys ) {
		ashna_sso_ready();
		ashna_sso_cleanup();

		$phone = '09121234567';
		$user  = make_user( 'sso_phone_user', 'subscriber', array( 'ashna_phone' => $phone ), 'pass-12345' );

		$begun  = Ashna_SSO::begin( 'telegram', '' );
		$stored = get_transient( Ashna_SSO::state_key( $begun['state'] ) );
		Ashna_SSO_Mock::$id_token = ashna_sso_token(
			$ashna_sso_keys['private'],
			array(
				'iss'          => 'https://oauth.telegram.org',
				'aud'          => '1234567890',
				'sub'          => 'tg-subject-1',
				'iat'          => time(),
				'exp'          => time() + 600,
				'nonce'        => $stored['nonce'],
				'name'         => 'کاربر تلگرام',
				'phone_number' => '+989121234567',
			)
		);
		$res = Ashna_SSO::complete( 'telegram', $begun['state'], 'code', $begun['bind'] );

		check( 'the verified Telegram phone finds the existing account', is_array( $res ) && (int) $res['user']->ID === (int) $user->ID, is_wp_error( $res ) ? $res->get_error_code() : '' );
		check( 'the Telegram identity is stored', 1 === (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . Ashna_SSO::table_name() . " WHERE provider = 'telegram'" ) ); // phpcs:ignore

		// The same person can add Google to the same account (two identities, one user).
		$begun = ashna_sso_begin_with_token(
			'google',
			$ashna_sso_keys['private'],
			array( 'sub' => 'google-of-same-user', 'email' => 'same.person@example.com', 'email_verified' => true )
		);
		$res2  = Ashna_SSO::complete( 'google', $begun['state'], 'code', $begun['bind'] );
		check( 'Google creates its own account when the email is new', is_array( $res2 ) && ! empty( $res2['is_new'] ), is_wp_error( $res2 ) ? $res2->get_error_code() : $res2 );

		// The provider subject stays bound to its first user even if the email changes.
		$begun = ashna_sso_begin_with_token(
			'google',
			$ashna_sso_keys['private'],
			array( 'sub' => 'google-of-same-user', 'email' => 'changed.later@example.com', 'email_verified' => true )
		);
		$res3  = Ashna_SSO::complete( 'google', $begun['state'], 'code', $begun['bind'] );
		check( 'a subject never migrates to another account', is_array( $res3 ) && (int) $res3['user']->ID === (int) $res2['user']->ID );

		// Declining the phone scope must still leave a usable account, and a unique username.
		$begun  = Ashna_SSO::begin( 'telegram', '' );
		$stored = get_transient( Ashna_SSO::state_key( $begun['state'] ) );
		Ashna_SSO_Mock::$id_token = ashna_sso_token(
			$ashna_sso_keys['private'],
			array(
				'iss'   => 'https://oauth.telegram.org',
				'aud'   => '1234567890',
				'sub'   => 'tg-subject-phone-declined',
				'iat'   => time(),
				'exp'   => time() + 600,
				'nonce' => $stored['nonce'],
				'name'  => 'کاربر بدون شماره',
			)
		);
		$res = Ashna_SSO::complete( 'telegram', $begun['state'], 'code', $begun['bind'] );
		track_user_id( is_array( $res ) ? $res['user']->ID : 0 );
		check( 'Telegram without a phone still creates a working account', is_array( $res ) && ! empty( $res['is_new'] ), is_wp_error( $res ) ? $res->get_error_code() : '' );
		check( 'the profile-only account has no made-up email', is_array( $res ) && '' === (string) $res['user']->user_email );
		check( 'the profile-only account has no phone yet', is_array( $res ) && '' === (string) get_user_meta( $res['user']->ID, 'ashna_phone', true ) );
		check( 'the profile-only account keeps the provider name', is_array( $res ) && 'کاربر بدون شماره' === $res['user']->display_name );
		check( 'the profile-only account is asked for a phone', is_array( $res ) && '1' === (string) get_user_meta( $res['user']->ID, 'ashna_sso_needs_phone', true ) );
		check( 'its username is unique and readable', is_array( $res ) && (bool) get_user_by( 'login', $res['user']->user_login ) && false !== strpos( $res['user']->user_login, 'telegram_' ) );

		// The same subject signs back into the same profile-only account.
		$again  = Ashna_SSO::begin( 'telegram', '' );
		$stored = get_transient( Ashna_SSO::state_key( $again['state'] ) );
		Ashna_SSO_Mock::$id_token = ashna_sso_token(
			$ashna_sso_keys['private'],
			array(
				'iss'   => 'https://oauth.telegram.org',
				'aud'   => '1234567890',
				'sub'   => 'tg-subject-phone-declined',
				'iat'   => time(),
				'exp'   => time() + 600,
				'nonce' => $stored['nonce'],
				'name'  => 'کاربر بدون شماره',
			)
		);
		$back = Ashna_SSO::complete( 'telegram', $again['state'], 'code', $again['bind'] );
		check( 'the same subject reuses the profile-only account', is_array( $back ) && (int) $back['user']->ID === (int) $res['user']->ID );
	}
);

scenario(
	'The callback route has its own rate ceiling',
	function () {
		ashna_sso_ready();
		delete_transient( 'ashna_sso_cb_' . md5( Ashna_Helper::get_client_ip() ) );
		$codes = array();
		for ( $i = 0; $i < 102; $i++ ) {
			$res     = Ashna_SSO::complete( 'google', 'deadbeef', 'c', 'b' );
			$codes[] = is_wp_error( $res ) ? $res->get_error_code() : 'ok';
		}
		check( 'a flood of callback attempts is stopped', in_array( 'ashna_sso_rate', $codes, true ) );
		delete_transient( 'ashna_sso_cb_' . md5( Ashna_Helper::get_client_ip() ) );
	}
);

// ---------------------------------------------------------------------------
// 4. Phone link endpoints (the recovery path for social accounts).
// ---------------------------------------------------------------------------
function ashna_sso_as_user( $user_id ) {
	if ( ! is_int( $user_id ) ) {
		throw new InvalidArgumentException( 'ashna_sso_as_user() takes a user id, not ' . gettype( $user_id ) );
	}
	wp_set_current_user( $user_id );
	$_POST    = array();
	$_REQUEST = array();
}

scenario(
	'Phone linking is authenticated, nonce-checked and rate-limited',
	function () {
		ashna_sso_ready();
		ashna_sso_cleanup();

		$user = make_user( 'sso_no_phone', 'customer', array( 'ashna_sso_needs_phone' => '1' ), 'pass-12345' );

		// Guest: the handler is only registered for signed-in requests, and refuses anyway.
		wp_set_current_user( 0 );
		$_POST = wp_slash( array( 'action' => 'ashna_sso_phone_start', 'phone' => '09990000001', 'nonce' => wp_create_nonce( 'ashna_sso_phone' ) ) );
		ob_start();
		try {
			do_action( 'wp_ajax_ashna_sso_phone_start' );
		} catch ( Throwable $e ) {
			unset( $e );
		}
		$out = ob_get_clean();
		$json = json_decode( $out, true );
		check( 'a guest cannot start a phone link', is_array( $json ) && empty( $json['success'] ) );

		// Signed in, wrong nonce.
		ashna_sso_as_user( $user->ID );
		$ajax_nonce = wp_create_nonce( 'ashna_sso_phone' );
		$_POST = wp_slash( array( 'action' => 'ashna_sso_phone_start', 'phone' => '09990000002', 'nonce' => 'forged' ) );
		ob_start();
		try {
			do_action( 'wp_ajax_ashna_sso_phone_start' );
		} catch ( Throwable $e ) {
			unset( $e );
		}
		$json = json_decode( ob_get_clean(), true );
		check( 'a forged nonce cannot start a phone link', is_array( $json ) && empty( $json['success'] ) );

		// A fresh number each run: the per-number cooldown is real and must not leak across suites.
		$phone_link = '0999' . wp_rand( 1000000, 9999999 );

		// Signed in, valid nonce.
		$_POST = wp_slash( array( 'action' => 'ashna_sso_phone_start', 'phone' => $phone_link, 'nonce' => $ajax_nonce ) );
		ob_start();
		try {
			do_action( 'wp_ajax_ashna_sso_phone_start' );
		} catch ( Throwable $e ) {
			unset( $e );
		}
		$json = json_decode( ob_get_clean(), true );
		check( 'a valid request sends a code through the normal pipeline', is_array( $json ) && ! empty( $json['success'] ), $json );
		$code = isset( Ashna_T::$codes[ $phone_link ] ) ? Ashna_T::$codes[ $phone_link ] : '';

		// Wrong code.
		$_POST = wp_slash( array( 'action' => 'ashna_sso_phone_verify', 'phone' => $phone_link, 'code' => '000000', 'nonce' => $ajax_nonce ) );
		ob_start();
		try {
			do_action( 'wp_ajax_ashna_sso_phone_verify' );
		} catch ( Throwable $e ) {
			unset( $e );
		}
		$json = json_decode( ob_get_clean(), true );
		check( 'a wrong code does not link a phone', is_array( $json ) && empty( $json['success'] ) );

		// Correct code.
		$_POST = wp_slash( array( 'action' => 'ashna_sso_phone_verify', 'phone' => $phone_link, 'code' => $code, 'nonce' => $ajax_nonce ) );
		ob_start();
		try {
			do_action( 'wp_ajax_ashna_sso_phone_verify' );
		} catch ( Throwable $e ) {
			unset( $e );
		}
		$json = json_decode( ob_get_clean(), true );
		check( 'the verified phone is stored on the account', is_array( $json ) && ! empty( $json['success'] ), $json );
		check( 'ashna_phone meta is written', $phone_link === (string) get_user_meta( $user->ID, 'ashna_phone', true ), get_user_meta( $user->ID, 'ashna_phone', true ) );
		check( 'the prompt flag is cleared', '' === (string) get_user_meta( $user, 'ashna_sso_needs_phone', true ) );

		// A number that belongs to somebody else.
		$other = make_user( 'sso_owner', 'subscriber', array( 'ashna_phone' => '09991230002' ), 'pass-12345' );
		$_POST = wp_slash( array( 'action' => 'ashna_sso_phone_start', 'phone' => '09991230002', 'nonce' => $ajax_nonce ) );
		ob_start();
		try {
			do_action( 'wp_ajax_ashna_sso_phone_start' );
		} catch ( Throwable $e ) {
			unset( $e );
		}
		$json = json_decode( ob_get_clean(), true );
		check( 'a phone owned by another account is refused', is_array( $json ) && empty( $json['success'] ) );

		wp_delete_user( $other );
		ashna_sso_as_user( 0 );
	}
);

// ---------------------------------------------------------------------------
// 5. The "add your phone" card appears only when it can help.
// ---------------------------------------------------------------------------
scenario(
	'The "add your phone" card is narrow and self-cleaning',
	function () use ( $ashna_sso_keys ) {
		ashna_sso_ready();
		ashna_sso_cleanup();

		// Guests never see it.
		wp_set_current_user( 0 );
		ob_start();
		Ashna_SSO::render_phone_prompt();
		$html = ob_get_clean();
		check( 'a guest never sees the card', '' === trim( $html ) );

		$user = make_user( 'sso_no_phone', 'customer', array(), 'pass-12345' );
		ashna_sso_as_user( $user->ID );
		ob_start();
		Ashna_SSO::render_phone_prompt();
		$html = ob_get_clean();
		check( 'an account without the flag sees nothing', '' === trim( $html ) );

		update_user_meta( $user->ID, 'ashna_sso_needs_phone', '1' );
		ob_start();
		Ashna_SSO::render_phone_prompt();
		$html = ob_get_clean();
		check( 'the card is printed for a social account with no phone', false !== strpos( $html, 'id="ashna-sso-phone"' ) );
		foreach ( array( 'start', 'dismiss', 'send', 'verify' ) as $action ) {
			check( 'the card offers the ' . $action . ' action', false !== strpos( $html, 'data-ashna-sso="' . $action . '"' ) );
		}
		check( 'the phone card script is enqueued', wp_script_is( 'ashna-sso', 'enqueued' ) );
		$localized = (string) wp_scripts()->get_data( 'ashna-sso', 'data' );
		check( 'the script receives its own nonce and endpoint', false !== strpos( $localized, 'ashnaSSO' ) && false !== strpos( $localized, 'nonce' ) && false !== strpos( $localized, 'admin-ajax.php' ) );
		check( 'the card CSS is loaded with it', wp_style_is( 'ashna-otp-frontend', 'enqueued' ) );

		// Once a phone exists the flag is stale: nothing is printed and the flag is dropped.
		update_user_meta( $user->ID, 'ashna_phone', '09121234567' );
		ob_start();
		Ashna_SSO::render_phone_prompt();
		$html = ob_get_clean();
		check( 'an account with a phone sees nothing', '' === trim( $html ) );
		check( 'the stale flag is removed', '' === (string) get_user_meta( $user->ID, 'ashna_sso_needs_phone', true ) );

		// The site owner can switch the whole prompt off.
		update_user_meta( $user->ID, 'ashna_phone', '' );
		update_user_meta( $user->ID, 'ashna_sso_needs_phone', '1' );
		ashna_t_settings( array( 'sso_ask_phone' => 0 ) );
		ob_start();
		Ashna_SSO::render_phone_prompt();
		$html = ob_get_clean();
		check( 'turning ask_phone off hides the card', '' === trim( $html ) );
		ashna_t_settings( array( 'sso_ask_phone' => 1 ) );

		// Dismissal is authenticated, nonce-checked and permanent for that user.
		ashna_sso_as_user( $user->ID );
		$_POST = wp_slash( array( 'action' => 'ashna_sso_phone_dismiss', 'nonce' => 'forged' ) );
		ob_start();
		try {
			do_action( 'wp_ajax_ashna_sso_phone_dismiss' );
		} catch ( Throwable $e ) {
			unset( $e );
		}
		$json = json_decode( ob_get_clean(), true );
		check( 'a forged nonce cannot dismiss the card', is_array( $json ) && empty( $json['success'] ) );
		check( 'the flag survives the forged attempt', '1' === (string) get_user_meta( $user->ID, 'ashna_sso_needs_phone', true ) );

		$_POST = wp_slash( array( 'action' => 'ashna_sso_phone_dismiss', 'nonce' => wp_create_nonce( 'ashna_sso_phone' ) ) );
		ob_start();
		try {
			do_action( 'wp_ajax_ashna_sso_phone_dismiss' );
		} catch ( Throwable $e ) {
			unset( $e );
		}
		$json = json_decode( ob_get_clean(), true );
		check( 'a valid dismiss succeeds', is_array( $json ) && ! empty( $json['success'] ) );
		check( 'the flag is cleared after dismissal', '' === (string) get_user_meta( $user->ID, 'ashna_sso_needs_phone', true ) );

		// A social account that dismisses the card must not be asked again on its next sign-in.
		$begun = ashna_sso_begin_with_token(
			'google',
			$ashna_sso_keys['private'],
			array( 'sub' => 'card-sub', 'email' => 'card.user@example.com', 'email_verified' => true )
		);
		$res       = Ashna_SSO::complete( 'google', $begun['state'], 'code', $begun['bind'] );
		$card_user = is_array( $res ) ? $res['user'] : null;
		track_user_id( $card_user ? $card_user->ID : 0 );
		check( 'a new social account is asked for a phone', $card_user && '1' === (string) get_user_meta( $card_user->ID, 'ashna_sso_needs_phone', true ), is_wp_error( $res ) ? $res->get_error_code() : '' );

		ashna_sso_as_user( $card_user->ID );
		$_POST = wp_slash( array( 'action' => 'ashna_sso_phone_dismiss', 'nonce' => wp_create_nonce( 'ashna_sso_phone' ) ) );
		ob_start();
		try {
			do_action( 'wp_ajax_ashna_sso_phone_dismiss' );
		} catch ( Throwable $e ) {
			unset( $e );
		}
		$json = json_decode( ob_get_clean(), true );
		check( 'the card can be dismissed', is_array( $json ) && ! empty( $json['success'] ) );
		check( 'dismissal leaves a dated tombstone', (int) get_user_meta( $card_user->ID, 'ashna_sso_phone_dismissed', true ) > 0 );

		$begun = ashna_sso_begin_with_token(
			'google',
			$ashna_sso_keys['private'],
			array( 'sub' => 'card-sub', 'email' => 'card.user@example.com', 'email_verified' => true )
		);
		Ashna_SSO::complete( 'google', $begun['state'], 'code', $begun['bind'] );
		check( 'a dismissed card stays hidden on the next sign-in', '' === (string) get_user_meta( $card_user->ID, 'ashna_sso_needs_phone', true ) );

		// "Later" is not "never": after a month the account may be asked again.
		update_user_meta( $card_user->ID, 'ashna_sso_phone_dismissed', time() - ( 31 * DAY_IN_SECONDS ) );
		$begun = ashna_sso_begin_with_token(
			'google',
			$ashna_sso_keys['private'],
			array( 'sub' => 'card-sub', 'email' => 'card.user@example.com', 'email_verified' => true )
		);
		Ashna_SSO::complete( 'google', $begun['state'], 'code', $begun['bind'] );
		check( 'the card may return after a month', '1' === (string) get_user_meta( $card_user->ID, 'ashna_sso_needs_phone', true ) );

		wp_set_current_user( 0 );
	}
);

// ---------------------------------------------------------------------------
// 6. Settings and rendering.
// ---------------------------------------------------------------------------
scenario(
	'Secrets never come back to the browser, and the buttons follow the setting',
	function () {
		ashna_sso_ready();

		$admin = Ashna_Admin::instance();
		$ref   = new ReflectionMethod( 'Ashna_Admin', 'sanitize_settings_payload' );
		$ref->setAccessible( true );

		$clean = $ref->invoke(
			$admin,
			array(
				'sso_google_enabled'        => '1',
				'sso_google_client_id'      => 'google-client-id',
				'sso_google_client_secret'  => '',
				'sso_telegram_client_secret' => '',
			)
		);
		check( 'an empty secret field keeps the stored secret', 'google-client-secret' === $clean['sso_google_client_secret'], $clean['sso_google_client_secret'] );
		check( 'the Telegram secret is preserved too', 'telegram-client-secret' === $clean['sso_telegram_client_secret'] );

		$clean2 = $ref->invoke(
			$admin,
			array(
				'sso_google_client_secret' => 'rotated-secret',
			)
		);
		check( 'a new secret replaces the old one', 'rotated-secret' === $clean2['sso_google_client_secret'] );

		ashna_sso_ready();

		$form = Ashna_Frontend::get_login_form_html( array( 'context' => 'test' ) );
		check( 'the form shows a Google button when configured', false !== strpos( $form, 'ashna-sso-google' ) );
		check( 'the form shows a Telegram button when configured', false !== strpos( $form, 'ashna-sso-telegram' ) );
		check( 'the buttons point at the start route with the provider', false !== strpos( $form, 'ashna_sso=start&#038;provider=google' ) || false !== strpos( $form, 'ashna_sso=start&provider=google' ) );
		check( 'no client secret leaks into the HTML', false === strpos( $form, 'google-client-secret' ) && false === strpos( $form, 'telegram-client-secret' ) );

		// The admin screen localizes its script data too; the secrets must not ride along.
		wp_set_current_user( 1 );
		Ashna_Admin::instance()->enqueue_admin_assets( 'toplevel_page_ashna-otp' );
		$admin_data = (string) wp_scripts()->get_data( 'ashna-otp-admin-core', 'data' );
		check( 'the admin script data carries no secret', false === strpos( $admin_data, 'google-client-secret' ) && false === strpos( $admin_data, 'telegram-client-secret' ) && false === strpos( $admin_data, 'client_secret' ), substr( $admin_data, 0, 120 ) );
		check( 'the admin script still gets its nonce and endpoint', false !== strpos( $admin_data, 'ashnaAdminParams' ) && false !== strpos( $admin_data, 'admin-ajax.php' ) );
		wp_set_current_user( 0 );

		ashna_t_settings( array( 'sso_show_on_forms' => 0 ) );
		$hidden = Ashna_Frontend::get_login_form_html( array( 'context' => 'test' ) );
		check( 'turning the buttons off removes them', false === strpos( $hidden, 'ashna-sso-google' ) );

		ashna_t_settings( array( 'sso_google_enabled' => 0, 'sso_show_on_forms' => 1 ) );
		$one = Ashna_Frontend::get_login_form_html( array( 'context' => 'test' ) );
		check( 'only the enabled provider is shown', false === strpos( $one, 'ashna-sso-google' ) && false !== strpos( $one, 'ashna-sso-telegram' ) );

		ashna_sso_ready();
	}
);

scenario(
	'The identity table exists and the schema version moved with it',
	function () {
		global $wpdb;
		$table = Ashna_SSO::table_name();
		check( 'the SSO table exists', $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) );
		check( 'DB version is 2.6.0', '2.6.0' === Ashna_Activator::DB_VERSION );
		check( 'the activation stamp matches', Ashna_Activator::DB_VERSION === get_option( 'ashna_otp_db_version' ) );

		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" ); // phpcs:ignore
		foreach ( array( 'provider', 'subject', 'user_id', 'email', 'phone', 'created_at', 'last_login' ) as $column ) {
			check( 'column ' . $column . ' exists', in_array( $column, $columns, true ), $columns );
		}
	}
);

ashna_sso_cleanup();
ashna_t_finish( 'sso' );
