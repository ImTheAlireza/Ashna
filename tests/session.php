<?php
/**
 * QC 1 — session after an OTP sign-in must match WordPress core and WooCommerce.
 *
 * Runs inside a disposable full WordPress with Ashna active (tests/run-full-wp.py session):
 * real auth cookies, WP_Session_Tokens, nonces, users and roles. WooCommerce, Dokan and
 * Paid Memberships Pro are reproduced by tests/fixtures/third-party.php.
 */

require_once __DIR__ . '/lib/wp-harness.php';
require_once __DIR__ . '/fixtures/third-party.php';

$ip = '203.0.113.7';
$_SERVER['REMOTE_ADDR'] = $ip;
ashna_t_settings(
	array(
		'active_sms_gateway'       => 'sandbox',
		'mobile_delivery_channel'  => 'sms',
		'login_mode'               => 'phone_and_email',
		'auto_register'            => 1,
		'whitelisted_identifiers'  => $ip, // session tests are not about rate limits
		'captcha_type'             => 'none',
		'require_name_on_register' => 'optional',
		'redirect_behavior'        => 'home',
		'respect_login_gates'      => 1,
		'respect_plugin_redirects' => 1,
		'session_remember'         => 1,
		'session_days'             => 14,
		'admin_redirect_url'       => '',
		'enable_passkey'           => 1,
	)
);

/** Full OTP sign-in as the current browser; returns the verify response. */
function otp_sign_in( $phone, array $extra = array() ) {
	list( , $code ) = request_code( $phone );
	$res = ajax(
		'ashna_verify_otp',
		array_merge(
			array(
				'identifier' => $phone,
				'code'       => (string) $code,
				'nonce'      => wp_create_nonce( 'ashna_otp_nonce' ),
			),
			$extra
		)
	);
	track_user_id( data_of( $res, 'user_id' ) );
	return $res;
}

$state = array();

scenario(
	'New visitor signs up with OTP: native WordPress session in the same request',
	function () use ( &$state ) {
		browser_guest();
		Ashna_T::$logins = array();
		$phone           = random_phone();
		$res             = otp_sign_in( $phone );
		$uid             = (int) data_of( $res, 'user_id' );
		$user            = get_user_by( 'id', $uid );

		check( 'verify succeeded', ok_json( $res ), $res['raw'] );
		check( 'current user switched in the same request', get_current_user_id() === $uid );
		check( 'wp_login fired exactly once for that user', array( $uid ) === Ashna_T::$logins, Ashna_T::$logins );
		check( 'auth + logged-in cookies issued for the user', isset( Ashna_T::$cookies['auth'], Ashna_T::$cookies['logged_in'] ) && Ashna_T::$cookies['auth']['user_id'] === $uid );
		check( 'remembered session (14 days)', abs( Ashna_T::$cookies['auth']['expiration'] - ( time() + 14 * DAY_IN_SECONDS ) ) < 60 );
		check( 'one WP_Session_Tokens session stored', 1 === count( WP_Session_Tokens::get_instance( $uid )->get_all() ) );
		check( 'role is a normal account role (no WooCommerce → subscriber)', $user && in_array( 'subscriber', $user->roles, true ), $user ? $user->roles : null );
		check( 'phone stored as verified', $user && get_user_meta( $uid, 'ashna_phone', true ) === $phone );
		check( 'response nonce is valid for the NEW session', 1 === wp_verify_nonce( data_of( $res, 'nonce' ), 'ashna_otp_nonce' ) );
		check( 'response reports logged_in', true === data_of( $res, 'logged_in' ) );

		$state['phone']       = $phone;
		$state['uid']         = $uid;
		$state['nonce']       = data_of( $res, 'nonce' );
		$state['token']       = Ashna_T::$cookies['logged_in']['token'];
		$state['guest_nonce'] = null;
	}
);

scenario(
	'Passkey enrolment right after OTP (same page, no reload) is accepted',
	function () use ( &$state ) {
		browser_signed_in();
		check( 'next request is authenticated by the cookies', get_current_user_id() === $state['uid'] );

		$res = ajax( 'ashna_passkey_register_options', array( 'nonce' => $state['nonce'] ) );
		check( 'register options accepted with the returned nonce (was 403 before)', ok_json( $res ) && 200 === $res['status'], $res['raw'] );
		check( 'challenge issued for this user', '' !== (string) data_of( $res, 'challenge', '' ) );

		browser_guest();
		$state['guest_nonce'] = wp_create_nonce( 'ashna_otp_nonce' );
		browser_signed_in();
		$stale = ajax( 'ashna_passkey_register_options', array( 'nonce' => $state['guest_nonce'] ) );
		check( 'a stale (guest) nonce gets 403 + machine-readable code, not "-1"', 403 === $stale['status'] && 'ashna_nonce_expired' === data_of( $stale, 'code' ), $stale['raw'] );
		$fresh = data_of( $stale, 'nonce' );
		check( 'the 403 carries a fresh nonce for the current session', 1 === wp_verify_nonce( $fresh, 'ashna_otp_nonce' ) );
		$retry = ajax( 'ashna_passkey_register_options', array( 'nonce' => $fresh ) );
		check( 'retry with that nonce succeeds', ok_json( $retry ), $retry['raw'] );
	}
);

scenario(
	'Re-login from a second tab (stale guest nonce) replaces the session instead of failing',
	function () use ( &$state ) {
		browser_signed_in();
		$old_token = $state['token'];
		// Tab B still holds the guest nonce from before the first sign-in.
		$res = ajax( 'ashna_request_otp', array( 'identifier' => $state['phone'], 'nonce' => $state['guest_nonce'] ) );
		check( 'stale nonce → 403 with a fresh nonce (the form retries by itself)', 403 === $res['status'] && data_of( $res, 'nonce' ), $res['raw'] );

		Ashna_T::$logins = array();
		$retry           = ajax( 'ashna_request_otp', array( 'identifier' => $state['phone'], 'nonce' => data_of( $res, 'nonce' ) ) );
		check( 'retried code request accepted', ok_json( $retry ), $retry['raw'] );
		$verify = ajax(
			'ashna_verify_otp',
			array(
				'identifier' => $state['phone'],
				'code'       => (string) Ashna_T::$codes[ $state['phone'] ],
				'nonce'      => data_of( $res, 'nonce' ),
			)
		);
		check( 're-login succeeded for the same account', ok_json( $verify ) && (int) data_of( $verify, 'user_id' ) === $state['uid'], $verify['raw'] );
		$manager = WP_Session_Tokens::get_instance( $state['uid'] );
		check( 'previous session token destroyed (no stale session left behind)', null === $manager->get( $old_token ) );
		check( 'exactly one live session for the account', 1 === count( $manager->get_all() ) );
		check( 'wp_login fired again', array( $state['uid'] ) === Ashna_T::$logins );
		$state['token'] = Ashna_T::$cookies['logged_in']['token'];
		$state['nonce'] = data_of( $verify, 'nonce' );
	}
);

scenario(
	'Signing in as another account from a signed-in browser switches cleanly',
	function () use ( &$state ) {
		browser_signed_in();
		$other = random_phone();
		$res   = otp_sign_in( $other );
		$new   = (int) data_of( $res, 'user_id' );
		check( 'second account signed in', ok_json( $res ) && $new > 0 && $new !== $state['uid'], $res['raw'] );
		check( 'current user is the new account', get_current_user_id() === $new );
		check( 'first account\'s browser session was destroyed', null === WP_Session_Tokens::get_instance( $state['uid'] )->get( $state['token'] ) );
	}
);

scenario(
	'Refresh endpoint: nonce for the current session, cached pages recover',
	function () {
		browser_guest();
		ashna_t_settings( array( 'captcha_type' => 'math' ) );
		$res = ajax( 'ashna_refresh_session', array() );
		check( 'guest refresh works without a nonce', ok_json( $res ) && false === data_of( $res, 'logged_in' ) );
		check( 'returned nonce is valid for a guest', 1 === wp_verify_nonce( data_of( $res, 'nonce' ), 'ashna_otp_nonce' ) );
		$captcha = data_of( $res, 'captcha' );
		check( 'fresh math captcha included for cached pages', is_array( $captcha ) && ! empty( $captcha['token'] ) );
		ashna_t_settings( array( 'captcha_type' => 'none' ) );
	}
);

scenario(
	'Pre-login gates: approval/ban plugins keep the final word',
	function () {
		browser_guest();
		$blocked = make_user( 'pending', 'subscriber', array( 'ashna_phone' => random_phone() ) );
		$phone   = get_user_meta( $blocked->ID, 'ashna_phone', true );
		$gate    = function ( $user ) use ( $blocked ) {
			return ( $user instanceof WP_User && $user->ID === $blocked->ID ) ? new WP_Error( 'pending_approval', '<strong>خطا:</strong> حساب شما هنوز تأیید نشده است.' ) : $user;
		};
		add_filter( 'wp_authenticate_user', $gate, 10, 2 );
		Ashna_T::$cookies = array();
		$res              = otp_sign_in( $phone );
		check( 'sign-in refused', ! ok_json( $res ) );
		check( 'plugin message shown as plain text', 'حساب شما هنوز تأیید نشده است.' === data_of( $res, 'message' ), data_of( $res, 'message' ) );
		check( 'no cookie issued, nobody signed in', empty( Ashna_T::$cookies ) && 0 === get_current_user_id() );

		ashna_t_settings( array( 'respect_login_gates' => 0 ) );
		browser_guest();
		$res = otp_sign_in( $phone );
		check( 'gate can be switched off in settings', ok_json( $res ), $res['raw'] );
		ashna_t_settings( array( 'respect_login_gates' => 1 ) );
		remove_filter( 'wp_authenticate_user', $gate, 10 );

		$deny = function ( $allowed, $user ) use ( $blocked ) {
			return $user->ID === $blocked->ID ? new WP_Error( 'banned', 'banned by integration' ) : $allowed;
		};
		add_filter( 'ashna_otp_can_login', $deny, 10, 2 );
		browser_guest();
		$res = otp_sign_in( $phone );
		check( 'ashna_otp_can_login can refuse (integrations hook)', ! ok_json( $res ) && 'banned by integration' === data_of( $res, 'message' ) );
		remove_filter( 'ashna_otp_can_login', $deny, 10 );
	}
);

scenario(
	'Accounts are never guessed: staff phones and duplicates',
	function () {
		browser_guest();
		$phone = random_phone();
		make_user( 'shopadmin', 'administrator', array( 'billing_phone' => $phone ) );
		$before = Ashna_T::$codes;
		list( $res, $code ) = request_code( $phone );
		check( 'admin found only through an UNVERIFIED billing phone is not opened', ! ok_json( $res ) && 'ashna_staff_phone_unverified' === data_of( $res, 'code' ), $res['raw'] );
		check( 'no SMS sent and no duplicate account created', null === $code && ! get_user_by( 'login', $phone ) );

		$dup = random_phone();
		make_user( 'twin1', 'subscriber', array( 'billing_phone' => $dup ) );
		make_user( 'twin2', 'subscriber', array( 'billing_phone' => $dup ) );
		list( $res ) = request_code( $dup );
		check( 'phone shared by two accounts is refused (no guessing)', 'ashna_ambiguous_account' === data_of( $res, 'code' ), $res['raw'] );

		$verified = make_user( 'verifiedowner', 'subscriber', array( 'ashna_phone' => $dup ) );
		$account  = Ashna_Auth::resolve_account( $dup, 'phone' );
		check( 'a verified phone wins over unverified duplicates', $account['user'] && $account['user']->ID === $verified->ID );

		$admin_phone = random_phone();
		$admin       = make_user( 'realadmin', 'administrator', array( 'ashna_phone' => $admin_phone ) );
		$account     = Ashna_Auth::resolve_account( $admin_phone, 'phone' );
		check( 'admin with a verified phone signs in normally', $account['user'] && $account['user']->ID === $admin->ID );

		$moved = random_phone();
		make_user( 'moved', 'subscriber', array( 'billing_phone' => $moved, 'ashna_phone' => random_phone() ) );
		$account = Ashna_Auth::resolve_account( $moved, 'phone' );
		check( 'an account with a different verified phone is not opened via billing phone', null === $account['user'] && null === $account['error'] );
	}
);

scenario(
	'Redirects follow WordPress core: login_redirect (e.g. Paid Memberships Pro)',
	function () {
		ashna_fixture_pmpro();
		browser_guest();
		$member = make_user( 'member', 'subscriber', array( 'ashna_phone' => random_phone(), 'ashna_fixture_pmpro_level' => 2 ) );
		$res    = otp_sign_in( get_user_meta( $member->ID, 'ashna_phone', true ) );
		check( 'member sent to the PMPro account page when nothing was requested', home_url( '/membership-account/' ) === data_of( $res, 'redirect_to' ), data_of( $res, 'redirect_to' ) );

		browser_guest();
		$asked = home_url( '/members-only/lesson-3/' );
		$res   = otp_sign_in( get_user_meta( $member->ID, 'ashna_phone', true ), array( 'redirect_to' => $asked ) );
		check( 'a requested page is kept (PMPro only fills an empty destination)', $asked === data_of( $res, 'redirect_to' ), data_of( $res, 'redirect_to' ) );

		ashna_t_settings( array( 'respect_plugin_redirects' => 0 ) );
		browser_guest();
		$res = otp_sign_in( get_user_meta( $member->ID, 'ashna_phone', true ) );
		check( 'switch off → Ashna\'s own setting (home)', home_url( '/' ) === data_of( $res, 'redirect_to' ), data_of( $res, 'redirect_to' ) );
		ashna_t_settings( array( 'respect_plugin_redirects' => 1 ) );
		remove_filter( 'login_redirect', 'pmpro_login_redirect', 10 );
	}
);

scenario(
	'WooCommerce: cart/session moved to the customer, customer pipeline, Dokan redirect',
	function () {
		ashna_fixture_woocommerce();
		$dashboard = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Vendor Dashboard',
				'post_name'   => 'dashboard-' . wp_rand( 100, 999 ),
			)
		);
		Ashna_T::$posts[] = $dashboard;
		ashna_fixture_dokan( $dashboard );

		$created = array();
		$hook    = function ( $id, $data, $generated ) use ( &$created ) {
			$created[] = array( $id, $data['role'], $generated );
		};
		add_action( 'woocommerce_created_customer', $hook, 10, 3 );
		$filter = function ( $data ) {
			$data['role'] = 'administrator'; // a misbehaving extension
			return $data;
		};
		add_filter( 'woocommerce_new_customer_data', $filter, 99 );

		browser_guest();
		WC()->session->calls = array();
		$res                 = otp_sign_in( random_phone() );
		$uid                 = (int) data_of( $res, 'user_id' );
		$user                = get_user_by( 'id', $uid );
		check( 'new customer signed in', ok_json( $res ), $res['raw'] );
		check( 'guest WooCommerce session migrated to the customer (as wc_set_customer_auth_cookie)', array( $uid ) === WC()->session->calls, WC()->session->calls );
		check( 'woocommerce_created_customer fired once (role, generated password)', 1 === count( $created ) && $created[0][0] === $uid && true === $created[0][2], $created );
		check( 'role forced back to customer when an extension asks for administrator', $user && array( 'customer' ) === array_values( $user->roles ), $user ? $user->roles : null );
		remove_filter( 'woocommerce_new_customer_data', $filter, 99 );
		remove_action( 'woocommerce_created_customer', $hook, 10 );

		$vendor = make_user( 'vendor', 'seller', array( 'ashna_phone' => random_phone() ) );
		browser_guest();
		$res = otp_sign_in( get_user_meta( $vendor->ID, 'ashna_phone', true ) );
		check( 'Dokan vendor lands on the vendor dashboard (woocommerce_login_redirect)', get_permalink( $dashboard ) === data_of( $res, 'redirect_to' ), data_of( $res, 'redirect_to' ) );

		browser_guest();
		$asked = home_url( '/product/blue-shirt/?ref=1&utm=x' );
		$res   = otp_sign_in( get_user_meta( $vendor->ID, 'ashna_phone', true ), array( 'redirect_to' => $asked ) );
		check( 'requested page honoured exactly like Dokan does with ?redirect_to (entities decoded)', $asked === data_of( $res, 'redirect_to' ), data_of( $res, 'redirect_to' ) );
		check( '$_GET restored after the filter', ! isset( $_GET['redirect_to'] ) );

		browser_guest();
		$customer = make_user( 'buyer', 'customer', array( 'ashna_phone' => random_phone() ) );
		$res      = otp_sign_in( get_user_meta( $customer->ID, 'ashna_phone', true ) );
		check( 'ordinary customer keeps Ashna\'s destination', home_url( '/' ) === data_of( $res, 'redirect_to' ), data_of( $res, 'redirect_to' ) );

		ashna_t_settings( array( 'admin_redirect_url' => admin_url( 'index.php' ) ) );
		$admin = make_user( 'boss', 'administrator', array( 'ashna_phone' => random_phone() ) );
		browser_guest();
		$res = otp_sign_in( get_user_meta( $admin->ID, 'ashna_phone', true ) );
		check( 'admins go to the pinned admin URL', admin_url( 'index.php' ) === data_of( $res, 'redirect_to' ), data_of( $res, 'redirect_to' ) );
		ashna_t_settings( array( 'admin_redirect_url' => '' ) );
	}
);

scenario(
	'Session length and "remember me" settings',
	function () {
		ashna_t_settings( array( 'session_days' => 3 ) );
		browser_guest();
		otp_sign_in( random_phone() );
		check( '3-day session when configured', abs( Ashna_T::$cookies['auth']['expiration'] - ( time() + 3 * DAY_IN_SECONDS ) ) < 60 );

		ashna_t_settings( array( 'session_remember' => 0 ) );
		browser_guest();
		otp_sign_in( random_phone() );
		check( 'browser-session cookie when "remember" is off', 0 === (int) Ashna_T::$cookies['auth']['expire'] );
		ashna_t_settings( array( 'session_remember' => 1, 'session_days' => 14 ) );
	}
);

scenario(
	'Password fallback goes through wp_authenticate() (2FA / limiter / approval plugins)',
	function () {
		ashna_t_settings( array( 'allow_password_login' => 1 ) );
		$phone = random_phone();
		$user  = make_user( 'pwuser', 'subscriber', array( 'ashna_phone' => $phone ), 'Correct-Horse-9' );
		$fails = array();
		$track = function ( $login ) use ( &$fails ) {
			$fails[] = $login;
		};
		add_action( 'wp_login_failed', $track );

		browser_guest();
		$res = ajax( 'ashna_password_login', array( 'identifier' => $phone, 'password' => 'wrong', 'nonce' => wp_create_nonce( 'ashna_otp_nonce' ) ) );
		check( 'wrong password → generic message', ! ok_json( $res ) && false !== strpos( (string) data_of( $res, 'message' ), 'اشتباه' ) );
		check( 'wp_login_failed fired (login limiters can count it)', 1 === count( $fails ) );

		$two_factor = function ( $result, $username ) use ( $user ) {
			return ( $result instanceof WP_User && $result->ID === $user->ID ) ? new WP_Error( 'two_factor_required', 'کد تأیید دومرحله‌ای لازم است.' ) : $result;
		};
		add_filter( 'authenticate', $two_factor, 50, 2 );
		browser_guest();
		$res = ajax( 'ashna_password_login', array( 'identifier' => $phone, 'password' => 'Correct-Horse-9', 'nonce' => wp_create_nonce( 'ashna_otp_nonce' ) ) );
		check( 'an `authenticate` filter (e.g. 2FA) can stop the password sign-in', ! ok_json( $res ) && 'کد تأیید دومرحله‌ای لازم است.' === data_of( $res, 'message' ), $res['raw'] );
		check( 'nobody signed in', 0 === get_current_user_id() );
		remove_filter( 'authenticate', $two_factor, 50 );

		browser_guest();
		Ashna_T::$logins = array();
		$res             = ajax( 'ashna_password_login', array( 'identifier' => $phone, 'password' => 'Correct-Horse-9', 'nonce' => wp_create_nonce( 'ashna_otp_nonce' ) ) );
		check( 'correct password signs in with the shared session', ok_json( $res ) && get_current_user_id() === $user->ID && array( $user->ID ) === Ashna_T::$logins, $res['raw'] );
		check( 'fresh nonce for the new session returned', 1 === wp_verify_nonce( data_of( $res, 'nonce' ), 'ashna_otp_nonce' ) );
		remove_action( 'wp_login_failed', $track );
		ashna_t_settings( array( 'allow_password_login' => 0 ) );
	}
);

scenario(
	'Passkey sign-in uses the same session, gates and redirect resolver',
	function () {
		// Throw-away P-256 test key (php-wasm has no openssl.cnf for openssl_pkey_new()).
		$key = openssl_pkey_get_private(
			"-----BEGIN PRIVATE KEY-----\n" .
			"MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgQqWpZniZnCOEv40W\n" .
			"XenLutAq/LBRC9kSNPr/4CmnLSuhRANCAARj7wTXTjYxb+bhhY+Eux5xghfs4gHW\n" .
			"lfIYF3ZGCOegzgQX4bBWX58ybzfWvXr039bgFhtxhxfckanYo0v0UETE\n" .
			"-----END PRIVATE KEY-----\n"
		);
		check( 'test key loaded', false !== $key );
		$pem  = openssl_pkey_get_details( $key )['key'];
		$user = make_user( 'pk', 'subscriber', array( 'ashna_phone' => random_phone() ) );
		$cred = Ashna_Passkey::base64url_encode( random_bytes( 16 ) );
		Ashna_Passkey::save_user_passkey(
			$user->ID,
			array(
				'id'             => $cred,
				'public_key_pem' => $pem,
				'alg'            => -7,
				'label'          => 'test',
			)
		);

		$assert = function () use ( $key, $cred ) {
			$options   = ajax( 'ashna_passkey_login_options', array( 'nonce' => wp_create_nonce( 'ashna_otp_nonce' ) ) );
			$client    = wp_json_encode(
				array(
					'type'      => 'webauthn.get',
					'challenge' => data_of( $options, 'challenge' ),
					'origin'    => home_url(),
				)
			);
			$auth_data = hash( 'sha256', Ashna_Passkey::get_rp_id(), true ) . chr( 0x05 ) . pack( 'N', 1 );
			openssl_sign( $auth_data . hash( 'sha256', $client, true ), $signature, $key, OPENSSL_ALGO_SHA256 );
			return ajax(
				'ashna_passkey_login_verify',
				array(
					'nonce'              => wp_create_nonce( 'ashna_otp_nonce' ),
					'credential_id'      => $cred,
					'client_data_json'   => Ashna_Passkey::base64url_encode( $client ),
					'authenticator_data' => Ashna_Passkey::base64url_encode( $auth_data ),
					'signature'          => Ashna_Passkey::base64url_encode( $signature ),
				)
			);
		};

		browser_guest();
		Ashna_T::$logins = array();
		$res             = $assert();
		check( 'valid assertion signs in', ok_json( $res ) && get_current_user_id() === $user->ID, $res['raw'] );
		check( 'wp_login fired, redirect + new-session nonce returned', array( $user->ID ) === Ashna_T::$logins && home_url( '/' ) === data_of( $res, 'redirect_to' ) && 1 === wp_verify_nonce( data_of( $res, 'nonce' ), 'ashna_otp_nonce' ) );

		$deny = function ( $allowed, $u ) use ( $user ) {
			return $u->ID === $user->ID ? new WP_Error( 'banned', 'banned' ) : $allowed;
		};
		add_filter( 'ashna_otp_can_login', $deny, 10, 2 );
		browser_guest();
		$res = $assert();
		check( 'passkey sign-in respects the same gates (was bypassed before)', ! ok_json( $res ) && 0 === get_current_user_id(), $res['raw'] );
		remove_filter( 'ashna_otp_can_login', $deny, 10 );
	}
);

scenario(
	'Only site admins may change the verified phone that OTP sign-in trusts',
	function () {
		$customer = make_user( 'selfedit', 'subscriber', array( 'ashna_phone' => '09120000001' ) );
		wp_set_current_user( $customer->ID );
		$_POST = array( 'ashna_phone' => '09359999999' );
		Ashna_WooCommerce::instance()->save_user_profile_fields( $customer->ID );
		check( 'a user cannot claim another number on their own profile', '09120000001' === get_user_meta( $customer->ID, 'ashna_phone', true ) );

		$admin = make_user( 'editor_admin', 'administrator' );
		wp_set_current_user( $admin->ID );
		Ashna_WooCommerce::instance()->save_user_profile_fields( $customer->ID );
		check( 'an administrator can correct it', '09359999999' === get_user_meta( $customer->ID, 'ashna_phone', true ) );
		$_POST = array();
		browser_guest();
	}
);

ashna_fixture_remove_roles();
ashna_t_finish( 'session' );
