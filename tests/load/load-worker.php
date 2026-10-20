<?php
/**
 * TEST ONLY — one load-test worker: a real PHP process doing K sign-in flows against the shared
 * sandbox database. `tests/load/load-multiproc.py` starts many of these at the same time, which is
 * what "thousands of users at once" really exercises: concurrent writers on the same tables
 * (sessions, transients, the OTP log), not a single-worker web server.
 *
 * usage: PHP_BINARY=... php load-worker.php <first-user-index> <flows> <mode> <hostile> <start-ip>
 * prints one JSON line.
 */

require_once __DIR__ . '/../lib/wp-harness.php';

global $wpdb;

$index   = isset( $argv[1] ) ? (int) $argv[1] : 0;
$flows   = isset( $argv[2] ) ? max( 1, (int) $argv[2] ) : 3;
$mode    = isset( $argv[3] ) ? (string) $argv[3] : 'otp';
$hostile = isset( $argv[4] ) ? max( 0, (int) $argv[4] ) : 0;
$ipbase  = isset( $argv[5] ) ? (string) $argv[5] : '203.0.119.';

$out = array(
	'worker'   => $index,
	'flows'    => array(),
	'db_error' => array(),
	'ms'       => 0,
);

$phone_for = static function ( $i ) {
	return '0999' . str_pad( (string) ( $i % 10000000 ), 7, '0', STR_PAD_LEFT );
};
$login_for = static function ( $i ) {
	return 'loaduser' . $i;
};

$started = microtime( true );

for ( $f = 0; $f < $flows; $f++ ) {
	// 'same-user': every worker signs the same account in again and again (session storm).
	$i     = 'same-user' === $mode ? 0 : $index + $f;
	$ip    = $ipbase . ( ( ( $index + $f ) % 250 ) + 1 );
	$step  = array( 'user' => $login_for( $i ), 'ip' => $ip );
	$_SERVER['REMOTE_ADDR'] = $ip;

	// A hostile flow is mixed in to check that abuse never damages a legitimate one.
	if ( $hostile && 0 === $f % $hostile ) {
		$bad = ajax( 'ashna_request_otp', array( 'identifier' => array( 'x' ), 'nonce' => 'deadbeef' ) );
		$step['hostile'] = array( 'status' => $bad['status'], 'json' => is_array( $bad['json'] ) );
	}

	$t0 = microtime( true );
	list( $ask, $code ) = request_code( $phone_for( $i ) );
	// Under attack mode (or a captcha setting) the request comes back asking for the captcha.
	// A real visitor reads the question and answers it, so the load test does the same.
	$ask_code = (string) data_of( $ask, 'code', '' );
	if ( ! ok_json( $ask ) && ( 0 === strpos( $ask_code, 'ashna_captcha' ) || null !== data_of( $ask, 'new_captcha' ) ) ) {
		$question = Ashna_Security::generate_math_captcha();
		$sum      = 0;
		foreach ( preg_split( '/\\D+/', (string) $question['question'], -1, PREG_SPLIT_NO_EMPTY ) as $n ) {
			$sum += (int) $n;
		}
		list( $ask, $code ) = request_code(
			$phone_for( $i ),
			array( 'captcha_answer' => (string) $sum, 'captcha_token' => $question['token'] )
		);
		$step['captcha_ms'] = round( ( microtime( true ) - $t0 ) * 1000 );
	}
	$step['ask_ms'] = round( ( microtime( true ) - $t0 ) * 1000 );
	$step['ask']    = ok_json( $ask ) ? 'sent' : (string) data_of( $ask, 'code', 'error' );

	if ( ! $code ) {
		$step['result'] = 'no-code';
		$out['flows'][] = $step;
		continue;
	}

	$t0                    = microtime( true );
	$verify                = ajax(
		'ashna_verify_otp',
		array(
			'identifier' => $phone_for( $i ),
			'code'       => $code,
			'nonce'      => wp_create_nonce( 'ashna_otp_nonce' ),
		)
	);
	$step['verify_ms']     = round( ( microtime( true ) - $t0 ) * 1000 );
	$step['verify']        = ok_json( $verify ) ? 'ok' : (string) data_of( $verify, 'code', 'error' );
	// The session must belong to this user, never to a neighbour in a parallel worker.
	$signed_in             = get_current_user_id();
	$step['signed_in_as']  = $signed_in ? (string) get_userdata( $signed_in )->user_login : '';
	$step['result']        = ( ok_json( $verify ) && $login_for( $i ) === $step['signed_in_as'] ) ? 'ok' : 'mismatch';
	// In a session storm every worker signs in the same account; the invariant is only that the
	// session belongs to that one account and that no other account is touched.
	$step['session_user'] = (string) get_userdata( $signed_in )->user_login;

	browser_guest();
	$out['flows'][] = $step;
}

// Any SQLite/SQL error a parallel writer ran into (real MySQL hosts queue instead).
// phpcs:ignore WordPress.DB
$out['db_error'] = array( 'last' => (string) $wpdb->last_error, 'query' => substr( (string) $wpdb->last_query, 0, 120 ) );
$out['ms']       = round( ( microtime( true ) - $started ) * 1000 );
$out['peak_mb']  = round( memory_get_peak_usage( true ) / 1048576, 1 );

echo wp_json_encode( $out ), "\n";
