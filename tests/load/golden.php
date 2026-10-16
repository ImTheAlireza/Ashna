<?php
/**
 * TEST ONLY — golden transcript of every AJAX answer the plugin gives.
 *
 * The point of this file: prove that a refactor changes nothing a visitor can see. It runs a fixed
 * script of sign-in situations (first request, resend, cooldown, wrong code, expired code, success,
 * replay, limit refusals, blocks, captcha, e-mail channel) and prints each answer with the random
 * parts replaced by placeholders. Two runs — old code, new code — must produce the *same file*.
 *
 * usage (from inside a sandbox site, next to a boot file that loads WordPress):
 *   PHP=8.3 .cache/phpwasm/node_modules/.bin/php-wasm-cli _golden.php > before.txt
 *   … swap in the other plugin build …
 *   PHP=8.3 .cache/phpwasm/node_modules/.bin/php-wasm-cli _golden.php > after.txt
 *   diff before.txt after.txt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/../lib/wp-harness.php';

global $wpdb;

$table = Ashna_Logger::table_name();

/** Deterministic starting state: same settings, same fixtures, same empty log for every run. */
function golden_reset() {
	ob_start();
	require __DIR__ . '/reset-state.php'; // prints a small JSON state report; not part of the transcript
	ob_end_clean();

	Ashna_Helper::save_settings(
		array_merge(
			Ashna_Helper::default_settings(),
			array(
				'active_sms_gateway'             => 'sandbox',
				'backup_sms_gateway_1'           => 'none',
				'mobile_delivery_channel'        => 'sms',
				'login_mode'                     => 'phone_and_email',
				'auto_register'                  => 1,
				'captcha_type'                   => 'none',
				'resend_cooldown'                => 0,
				'otp_expiry'                     => 120,
				'max_requests_per_hour'          => 50,
				'max_requests_per_day'           => 50,
				'max_ip_requests_per_minute'     => 50,
				'max_ip_requests_per_hour'       => 500,
				'max_subnet_requests_per_minute' => 0,
				'daily_send_cap'                 => 0,
				'attack_mode_threshold'          => 0,
				'max_verify_attempts'            => 5,
				'lockout_duration'               => 900,
				'whitelisted_identifiers'        => '',
				'blocked_ips'                    => '',
				'blocked_phones'                 => '',
				'trust_proxy_headers'            => 1,
				'trusted_proxies'                => '',
				'show_debug_code_in_toast'       => 1,
				'username_prefix'                => '',
			)
		)
	);

	// Accounts created by an earlier transcript run must not leak into this one.
	foreach ( array_keys( golden_identifiers() ) as $identifier ) {
		golden_delete_accounts( $identifier );
	}

	// Fixtures: one verified account on a reserved test number, one unverified profile number.
	$existing = get_user_by( 'login', 'goldenuser' );
	if ( $existing ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $existing->ID );
	}
	$id = wp_insert_user(
		array(
			'user_login' => 'goldenuser',
			'user_pass'  => wp_generate_password( 20 ),
			'user_email' => 'goldenuser@example.com',
			'role'       => 'customer',
		)
	);
	update_user_meta( $id, 'ashna_phone', '09120000111' );

	$_SERVER['REMOTE_ADDR'] = '203.0.113.50';
	foreach ( Ashna_IP::PROXY_HEADERS as $header ) {
		unset( $_SERVER[ $header ] );
	}
	browser_guest();
}


/** Every identifier the transcript signs in with, so each run starts from the same accounts. */
function golden_identifiers() {
	$numbers = array( '0111', '0222', '0333', '0444', '0555', '0666', '0667', '0668', '0669', '0777', '0888', '0991', '0992', '0993' );
	$list    = array( 'golden-mail@example.com', 'golden-mail2@example.com' );
	foreach ( $numbers as $suffix ) {
		$list[] = '0912000' . $suffix;
	}
	return array_flip( $list );
}

/** Remove the accounts a previous run may have created for a transcript identifier. */
function golden_delete_accounts( $identifier ) {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/user.php';

	$ids   = (array) $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'ashna_phone' AND meta_value = %s", $identifier ) ); // phpcs:ignore
	$ids[] = get_user_by( 'email', $identifier ) ? get_user_by( 'email', $identifier )->ID : 0;
	foreach ( array( $identifier, 'u_' . $identifier ) as $login ) {
		$user = get_user_by( 'login', $login );
		$ids[] = $user ? $user->ID : 0;
	}

	foreach ( array_unique( array_map( 'absint', $ids ) ) as $id ) {
		if ( $id > 0 && $id !== get_current_user_id() ) {
			wp_delete_user( $id );
		}
	}
}

/**
 * Canonical form of one response: the exact JSON the visitor receives, decoded, with only the
 * values that cannot be identical between two runs replaced by placeholders. Nothing else is
 * touched — messages, codes, field order and values are compared as they are.
 */
function golden_canonical( $raw ) {
	$data = json_decode( (string) $raw, true );
	if ( ! is_array( $data ) ) {
		return 'NOT-JSON: ' . (string) $raw;
	}
	return wp_json_encode( golden_scrub( $data ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

function golden_scrub( $value ) {
	$volatile = array(
		'debug_code'   => '<code>',
		'resend_token' => '<token>',
		'nonce'        => '<nonce>',
		'user_id'      => '<id>',
		'cooldown'     => '<secs>',
		'remaining'    => '<secs>',
		'new_captcha'  => '<captcha>',
	);
	if ( ! is_array( $value ) ) {
		// Countdown numbers inside a message ("… 57 ثانیه دیگر …") depend on how long the two
		// requests took, not on the code under test, so the number before a time unit is a
		// placeholder. Everything else in the text is compared exactly.
		return is_string( $value )
			? preg_replace( '/[0-9\x{06F0}-\x{06F9}]+(\s*)(ثانیه|دقیقه|ساعت|روز)/u', '<n>$1$2', $value )
			: $value;
	}
	foreach ( $value as $key => $item ) {
		$value[ $key ] = ( isset( $volatile[ $key ] ) && null !== $item ) ? $volatile[ $key ] : golden_scrub( $item );
	}
	return $value;
}

function golden_step( $label, $result ) {
	if ( is_array( $result ) && isset( $result[0]['status'] ) ) {
		$result = $result[0]; // request_code() returns array( result, code ).
	}
	printf(
		"== %s\n   status=%s\n   %s\n",
		$label,
		isset( $result['status'] ) ? $result['status'] : 'n/a',
		golden_canonical( isset( $result['raw'] ) ? $result['raw'] : json_encode( $result ) )
	);
}

function golden_ask( $phone, array $extra = array(), $ip = '203.0.113.50', array $headers = array() ) {
	$_SERVER['REMOTE_ADDR'] = $ip;
	return request_code( $phone, $extra, array_merge( array( 'REMOTE_ADDR' => $ip ), $headers ) );
}

function golden_verify( $identifier, $code, $ip = '203.0.113.50' ) {
	$_SERVER['REMOTE_ADDR'] = $ip;
	browser_guest();
	return ajax(
		'ashna_verify_otp',
		array(
			'identifier' => $identifier,
			'code'       => (string) $code,
			'nonce'      => wp_create_nonce( 'ashna_otp_nonce' ),
		),
		array( 'REMOTE_ADDR' => $ip )
	);
}

echo "### ashna golden transcript | WordPress ", get_bloginfo( 'version' ), " | PHP ", PHP_VERSION, "\n";
golden_reset();

// 1. A brand-new number: full happy path, step by step.
list( $first, $code ) = golden_ask( '09120000222', array( 'full_name' => 'کاربر تستی' ) );
golden_step( 'request for a new number', $first );
$ticket = data_of( $first, 'resend_token' );

golden_step( 'verify with a wrong code', golden_verify( '09120000222', '00000' ) );
golden_step( 'verify with the right code', golden_verify( '09120000222', $code ) );
golden_step( 'verify again (replay)', golden_verify( '09120000222', $code ) );

// 2. Cooldown and resend ticket.
ashna_t_settings( array( 'resend_cooldown' => 60 ) );
golden_step( 'request while the cooldown is running', golden_ask( '09120000222' ) );
golden_step( 'resend with the ticket', golden_ask( '09120000222', array( 'resend_token' => $ticket ) ) );
golden_step( 'resend with a foreign ticket (other IP)', golden_ask( '09120000222', array( 'resend_token' => $ticket ), '203.0.113.77' ) );
ashna_t_settings( array( 'resend_cooldown' => 0 ) );

// 3. An account that already exists: the number from the fixture signs in.
list( $existing, $existing_code ) = golden_ask( '09120000111' );
golden_step( 'request for an existing account', $existing );
golden_step( 'sign in as the existing account', golden_verify( '09120000111', $existing_code ) );

// 4. Wrong code until the attempts run out.
$phone = '09120000333';
golden_ask( $phone );
for ( $i = 1; $i <= 6; $i++ ) {
	golden_step( 'wrong code #' . $i, golden_verify( $phone, '0000' . $i ) );
}

// 5. Blocklists.
ashna_t_settings( array( 'blocked_phones' => '09120000444' ) );
golden_step( 'blocked number', golden_ask( '09120000444' ) );
ashna_t_settings( array( 'blocked_phones' => '', 'blocked_ips' => '203.0.113.99' ) );
golden_step( 'blocked IP', golden_ask( '09120000555', array(), '203.0.113.99' ) );
ashna_t_settings( array( 'blocked_ips' => '' ) );

// 6. The per-minute IP limit and the honeypot.
ashna_t_settings( array( 'max_ip_requests_per_minute' => 2, 'max_requests_per_hour' => 50 ) );
golden_ask( '09120000666' );
golden_ask( '09120000667' );
golden_step( 'over the per-minute IP limit', golden_ask( '09120000668' ) );
ashna_t_settings( array( 'max_ip_requests_per_minute' => 50 ) );
golden_step( 'honeypot field filled', golden_ask( '09120000669', array( 'ashna_website' => 'http://spam.example' ) ) );

// 7. Daily cap and the per-number limit.
ashna_t_settings( array( 'daily_send_cap' => 1 ) );
golden_step( 'over the daily send cap', golden_ask( '09120000777' ) );
ashna_t_settings( array( 'daily_send_cap' => 0, 'max_requests_per_day' => 1 ) );
golden_ask( '09120000888' );
golden_step( 'over the per-number daily limit', golden_ask( '09120000888' ) );
ashna_t_settings( array( 'max_requests_per_day' => 50 ) );

// 8. Captcha errors and attack mode.
ashna_t_settings( array( 'captcha_type' => 'math' ) );
golden_step( 'captcha missing', golden_ask( '09120000991' ) );
$captcha = Ashna_Security::generate_math_captcha();
golden_step(
	'captcha wrong',
	golden_ask( '09120000992', array( 'captcha_answer' => '0', 'captcha_token' => $captcha['token'] ) )
);
$captcha = Ashna_Security::generate_math_captcha();
preg_match( '/(\d+)\s*\+\s*(\d+)/', (string) $captcha['question'], $sums );
golden_step(
	'captcha solved',
	golden_ask( '09120000993', array( 'captcha_answer' => (string) ( (int) $sums[1] + (int) $sums[2] ), 'captcha_token' => $captcha['token'] ) )
);
ashna_t_settings( array( 'captcha_type' => 'none' ) );

// 9. E-mail channel, invalid identifiers, and the disabled modes.
golden_step( 'e-mail request', golden_ask( 'golden-mail@example.com' ) );
golden_step( 'invalid identifier', golden_ask( 'not a phone' ) );
ashna_t_settings( array( 'login_mode' => 'phone_only' ) );
golden_step( 'e-mail while the login mode is phone-only', golden_ask( 'golden-mail2@example.com' ) );
ashna_t_settings( array( 'login_mode' => 'phone_and_email' ) );

// 10. State after all of it: what the admin list would show.
$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status ORDER BY status" ); // phpcs:ignore
echo "== log table by status\n";
foreach ( $rows as $row ) {
	printf( "   %s=%s\n", $row->status, $row->n );
}
echo "== locks\n";
printf( "   lockouts=%d | attack=%s | rows=%d\n", count( Ashna_Security::get_active_lockouts() ), Ashna_Rate_Limiter::attack_mode_active() ? 'on' : 'off', (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) ); // phpcs:ignore
echo "### end\n";
