<?php
/**
 * QC 2 — SMS-bombing protection: exact per-minute limits per IP and per mobile number.
 *
 * Runs inside a disposable full WordPress with Ashna active (tests/run-full-wp.py security).
 * Time windows are tested by moving log rows back in time, not by sleeping.
 */

require_once __DIR__ . '/lib/wp-harness.php';

global $wpdb;
$table = Ashna_Logger::table_name();

$base = array(
	'active_sms_gateway'             => 'sandbox',
	'backup_sms_gateway_1'           => 'none',
	'mobile_delivery_channel'        => 'sms',
	'login_mode'                     => 'phone_and_email',
	'auto_register'                  => 1,
	'whitelisted_identifiers'        => '',
	'blocked_ips'                    => '',
	'blocked_phones'                 => '',
	'captcha_type'                   => 'none',
	'resend_cooldown'                => 60,
	'max_requests_per_hour'          => 50,
	'max_requests_per_day'           => 50,
	'max_ip_requests_per_minute'     => 50,
	'max_ip_requests_per_hour'       => 500,
	'max_subnet_requests_per_minute' => 0,
	'attack_mode_threshold'          => 0,
	'attack_mode_minutes'            => 30,
	'daily_send_cap'                 => 0,
	'trust_proxy_headers'            => 1,
	'trusted_proxies'                => '',
	'max_verify_attempts'            => 5,
	'lockout_duration'               => 900,
);

/** Fresh state for a scenario: settings, empty log table, no attack mode/lockouts. */
function fresh( array $changes = array() ) {
	global $wpdb, $base;
	ashna_t_settings( array_merge( $base, $changes ) );
	$wpdb->query( 'DELETE FROM ' . Ashna_Logger::table_name() ); // phpcs:ignore
	Ashna_Rate_Limiter::end_attack_mode();
	delete_option( 'ashna_active_lockouts' );
	delete_option( Ashna_Rate_Limiter::STATS_OPTION );
	delete_option( 'ashna_otp_proxy_hint' );
	foreach ( array( 'ashna_otp_notified_attack', 'ashna_otp_notified_daily_cap' ) as $t ) {
		delete_transient( $t );
	}
	$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
	foreach ( Ashna_IP::PROXY_HEADERS as $h ) {
		unset( $_SERVER[ $h ] );
	}
	browser_guest();
}

/** Move every log row back in time (simulates waiting). */
function age_rows( $seconds ) {
	global $wpdb;
	$table = Ashna_Logger::table_name();
	foreach ( $wpdb->get_results( "SELECT id, created_at FROM {$table}" ) as $row ) { // phpcs:ignore
		$wpdb->update( $table, array( 'created_at' => gmdate( 'Y-m-d H:i:s', strtotime( $row->created_at ) - $seconds ) ), array( 'id' => $row->id ) );
	}
}

function ask( $phone, $ip = '203.0.113.7', array $extra = array(), array $headers = array() ) {
	$_SERVER['REMOTE_ADDR'] = $ip;
	list( $res, $code ) = request_code( $phone, $extra, array_merge( array( 'REMOTE_ADDR' => $ip ), $headers ) );
	return array( $res, $code );
}

function code_of( $res ) {
	return data_of( $res, 'code', ok_json( $res ) ? 'ok' : 'error' );
}

function sent_rows() {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Ashna_Logger::table_name() . " WHERE status = 'sent'" ); // phpcs:ignore
}

function solve( $captcha ) {
	preg_match( '/(\d+)\s*\+\s*(\d+)/', $captcha['question'], $m );
	return (string) ( (int) $m[1] + (int) $m[2] );
}

scenario(
	'Real client IP: proxy headers are only read from trusted proxies',
	function () {
		fresh();
		$ip = function ( $remote, array $headers = array() ) {
			$_SERVER['REMOTE_ADDR'] = $remote;
			foreach ( Ashna_IP::PROXY_HEADERS as $h ) {
				unset( $_SERVER[ $h ] );
			}
			foreach ( $headers as $k => $v ) {
				$_SERVER[ $k ] = $v;
			}
			return Ashna_Helper::get_client_ip();
		};
		check( 'public peer + fake X-Forwarded-For → peer address', '203.0.113.50' === $ip( '203.0.113.50', array( 'HTTP_X_FORWARDED_FOR' => '6.6.6.6' ) ) );
		check( 'public peer + fake CF-Connecting-IP → peer address', '203.0.113.50' === $ip( '203.0.113.50', array( 'HTTP_CF_CONNECTING_IP' => '6.6.6.6' ) ) );
		check( '…and the admin is told an unlisted proxy may exist', is_array( Ashna_IP::untrusted_proxy_hint() ) && '203.0.113.50' === Ashna_IP::untrusted_proxy_hint()['remote'] );
		check( 'local reverse proxy: right-most untrusted hop wins (client-written part ignored)', '198.51.100.9' === $ip( '10.0.0.5', array( 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 198.51.100.9' ) ) );
		check( 'two local hops are skipped', '198.51.100.9' === $ip( '127.0.0.1', array( 'HTTP_X_FORWARDED_FOR' => '198.51.100.9, 10.0.0.7' ) ) );
		check( 'Cloudflare edge → CF-Connecting-IP', '198.51.100.20' === $ip( '173.245.48.10', array( 'HTTP_CF_CONNECTING_IP' => '198.51.100.20' ) ) );
		check( 'Cloudflare IPv6 edge → CF-Connecting-IP', '2001:db8::77' === $ip( '2606:4700::1111', array( 'HTTP_CF_CONNECTING_IP' => '2001:db8::77' ) ) );
		check( 'local proxy forwarding a Cloudflare hop → visitor behind it', '198.51.100.30' === $ip( '127.0.0.1', array( 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 198.51.100.30, 162.158.1.2' ) ) );
		ashna_t_settings( array( 'trusted_proxies' => "185.143.232.0/22\n2001:db8:f00::/48" ) );
		check( 'listed CDN (ArvanCloud range) → AR-Real-IP', '198.51.100.40' === $ip( '185.143.233.9', array( 'HTTP_AR_REAL_IP' => '198.51.100.40', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6' ) ) );
		check( 'listed IPv6 range is trusted', Ashna_IP::is_trusted_proxy( '2001:db8:f00:1::5' ) && ! Ashna_IP::is_trusted_proxy( '2001:db8:f01::5' ) );
		ashna_t_settings( array( 'trust_proxy_headers' => 0 ) );
		check( 'switch off → always the peer address', '10.0.0.5' === $ip( '10.0.0.5', array( 'HTTP_X_FORWARDED_FOR' => '198.51.100.9' ) ) );
		check( '/24 network for IPv4', '203.0.113.0/24' === Ashna_IP::subnet( '203.0.113.77' ) );
		check( '/64 network for IPv6', '2001:db8:abcd:12::/64' === Ashna_IP::subnet( '2001:db8:abcd:12:ffff::1' ) );
		check( 'ports stripped, IPv6 normalized', '203.0.113.5' === Ashna_IP::valid( '203.0.113.5:443' ) && '2001:db8::1' === Ashna_IP::valid( '[2001:0db8::0001]:443' ) );
		check( 'garbage rejected', '' === Ashna_IP::valid( '999.1.1.1' ) && ! Ashna_IP::valid_range( '10.0.0.0/33' ) );
	}
);

scenario(
	'Per mobile number: 1 code per cooldown (60 s = per minute), per hour, per day',
	function () {
		fresh( array( 'max_requests_per_hour' => 3, 'max_requests_per_day' => 4 ) );
		$phone = random_phone();
		list( $r ) = ask( $phone );
		check( '1st code sent', ok_json( $r ), $r['raw'] );
		list( $r ) = ask( $phone );
		check( '2nd within the minute refused (cooldown)', 'ashna_cooldown' === code_of( $r ) && data_of( $r, 'remaining' ) > 50, $r['raw'] );
		age_rows( 61 );
		list( $r ) = ask( $phone );
		check( 'after 61 s allowed again', ok_json( $r ), $r['raw'] );
		age_rows( 61 );
		list( $r ) = ask( $phone );
		check( '3rd in the hour allowed', ok_json( $r ) );
		age_rows( 61 );
		list( $r ) = ask( $phone );
		check( '4th in the hour refused (hourly limit 3)', 'ashna_rate_limit_recipient' === code_of( $r ), $r['raw'] );
		age_rows( HOUR_IN_SECONDS );
		list( $r ) = ask( $phone );
		check( 'next hour: 4th of the day allowed', ok_json( $r ), $r['raw'] );
		age_rows( 61 );
		list( $r ) = ask( $phone );
		check( '5th in 24 h refused (daily limit 4)', 'ashna_rate_limit_recipient_day' === code_of( $r ), $r['raw'] );
		check( 'exactly 4 codes went out for this number', 4 === (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT COUNT(*) FROM ' . Ashna_Logger::table_name() . ' WHERE recipient = %s', $phone ) ) ); // phpcs:ignore
	}
);

scenario(
	'Failed sends do not lock a number out',
	function () {
		fresh( array( 'active_sms_gateway' => 'kavenegar', 'kavenegar_api_key' => 'k', 'kavenegar_template' => 't' ) );
		$fail = function () {
			return array( 'headers' => array(), 'body' => '{"return":{"status":500,"message":"down"}}', 'response' => array( 'code' => 500, 'message' => '' ), 'cookies' => array(), 'filename' => null );
		};
		add_filter( 'pre_http_request', $fail );
		$phone = random_phone();
		list( $r ) = ask( $phone );
		check( 'gateway down → error shown', ! ok_json( $r ) );
		remove_filter( 'pre_http_request', $fail );
		Ashna_Failover::reset_all();
		ashna_t_settings( array( 'active_sms_gateway' => 'sandbox' ) );
		list( $r ) = ask( $phone );
		check( 'retry right away is not blocked by the cooldown', ok_json( $r ), $r['raw'] );
	}
);

scenario(
	'Per IP: exact per-minute limit, also with rotating fake headers',
	function () {
		fresh( array( 'max_ip_requests_per_minute' => 3 ) );
		$ok = 0;
		for ( $i = 0; $i < 3; $i++ ) {
			list( $r ) = ask( random_phone() );
			$ok       += ok_json( $r ) ? 1 : 0;
		}
		check( '3 numbers from one IP in a minute allowed', 3 === $ok );
		list( $r ) = ask( random_phone() );
		check( '4th refused (per-minute IP limit)', 'ashna_rate_limit_ip_minute' === code_of( $r ), $r['raw'] );
		list( $r ) = ask( random_phone(), '203.0.113.8' );
		check( 'another visitor is not affected', ok_json( $r ) );
		list( $r ) = ask( random_phone(), '203.0.113.7', array(), array( 'HTTP_X_FORWARDED_FOR' => '6.6.6.' . wp_rand( 1, 250 ), 'HTTP_CF_CONNECTING_IP' => '7.7.7.' . wp_rand( 1, 250 ) ) );
		check( 'rotating X-Forwarded-For / CF-Connecting-IP does not reset the limit', 'ashna_rate_limit_ip_minute' === code_of( $r ), $r['raw'] );
		age_rows( 61 );
		list( $r ) = ask( random_phone() );
		check( 'next minute allowed again', ok_json( $r ) );
	}
);

scenario(
	'Per network (/24): rotating addresses inside one range',
	function () {
		fresh( array( 'max_subnet_requests_per_minute' => 3 ) );
		$codes = array();
		for ( $i = 1; $i <= 4; $i++ ) {
			list( $r ) = ask( random_phone(), '198.51.100.' . $i );
			$codes[]  = code_of( $r );
		}
		check( '4th address in the same /24 refused', array( 'ok', 'ok', 'ok', 'ashna_rate_limit_subnet' ) === $codes, $codes );
		list( $r ) = ask( random_phone(), '198.51.101.1' );
		check( 'neighbouring /24 unaffected', ok_json( $r ) );
	}
);

scenario(
	'Site-wide daily credit guard + one admin e-mail',
	function () {
		fresh( array( 'daily_send_cap' => 3 ) );
		$mails = array();
		$catch = function ( $null, $atts ) use ( &$mails ) {
			$mails[] = $atts['subject'];
			return true;
		};
		add_filter( 'pre_wp_mail', $catch, 10, 2 );
		for ( $i = 0; $i < 3; $i++ ) {
			ask( random_phone(), '203.0.113.' . ( 20 + $i ) );
		}
		list( $r ) = ask( random_phone(), '203.0.113.30' );
		check( 'code number 4 of the day refused', 'ashna_daily_cap' === code_of( $r ), $r['raw'] );
		ask( random_phone(), '203.0.113.31' );
		check( 'sending stopped: still exactly 3 codes', 3 === sent_rows() );
		check( 'admin e-mailed exactly once', 1 === count( $mails ), $mails );
		remove_filter( 'pre_wp_mail', $catch, 10 );
	}
);

scenario(
	'Attack mode: a site-wide burst makes a (single-use) captcha mandatory',
	function () {
		fresh( array( 'attack_mode_threshold' => 4 ) );
		$mails = 0;
		$catch = function () use ( &$mails ) {
			++$mails;
			return true;
		};
		add_filter( 'pre_wp_mail', $catch );
		for ( $i = 0; $i < 4; $i++ ) {
			ask( random_phone(), '192.0.2.' . ( 10 + $i ) );
		}
		check( 'attack mode switched on after 4 requests in a minute', Ashna_Rate_Limiter::attack_mode_active() );
		check( 'admin e-mailed', 1 === $mails );
		check( 'math captcha becomes the effective captcha', 'math' === Ashna_Security::effective_captcha_type() );

		$phone       = random_phone();
		list( $r )   = ask( $phone, '192.0.2.50' );
		$captcha     = data_of( $r, 'new_captcha' );
		check( 'request without captcha refused, a question is returned', 'ashna_captcha_empty' === code_of( $r ) && ! empty( $captcha['token'] ), $r['raw'] );

		list( $r ) = ask( $phone, '192.0.2.50', array( 'captcha_answer' => solve( $captcha ), 'captcha_token' => $captcha['token'] ) );
		check( 'correct answer → code sent', ok_json( $r ), $r['raw'] );
		age_rows( 61 );
		list( $r ) = ask( random_phone(), '192.0.2.51', array( 'captcha_answer' => solve( $captcha ), 'captcha_token' => $captcha['token'] ) );
		check( 'the same solved captcha cannot be replayed', 'ashna_captcha_used' === code_of( $r ), $r['raw'] );

		Ashna_Rate_Limiter::end_attack_mode();
		list( $r ) = ask( random_phone(), '192.0.2.52' );
		check( 'after attack mode ends, no captcha needed again', ok_json( $r ), $r['raw'] );
		remove_filter( 'pre_wp_mail', $catch );
	}
);

scenario(
	'Race-free reservation (what parallel requests see)',
	function () {
		fresh( array( 'max_ip_requests_per_minute' => 2 ) );
		$phone = random_phone();
		$r1    = Ashna_Logger::reserve( $phone, '203.0.113.7', '203.0.113.0/24' );
		$r2    = Ashna_Logger::reserve( $phone, '203.0.113.7', '203.0.113.0/24' );
		check( 'the earlier reservation keeps its slot', true === Ashna_Rate_Limiter::evaluate( $phone, '203.0.113.7', '203.0.113.0/24', $r1 ) );
		$second = Ashna_Rate_Limiter::evaluate( $phone, '203.0.113.7', '203.0.113.0/24', $r2 );
		check( 'the later one for the same number is refused', is_wp_error( $second ) && 'ashna_cooldown' === $second->get_error_code() );
		list( $r ) = ask( $phone, '203.0.113.9' );
		check( 'an in-flight ("pending") send already counts for new requests', 'ashna_cooldown' === code_of( $r ), $r['raw'] );

		fresh( array( 'max_ip_requests_per_minute' => 2 ) );
		$ids = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$ids[] = Ashna_Logger::reserve( random_phone(), '203.0.113.7', '203.0.113.0/24' );
		}
		check( 'IP limit 2: reservations #1 and #2 pass the recount', true === Ashna_Rate_Limiter::evaluate( 'x', '203.0.113.7', '203.0.113.0/24', $ids[1] ) );
		check( 'reservation #3 is refused by the recount', is_wp_error( Ashna_Rate_Limiter::evaluate( 'y', '203.0.113.7', '203.0.113.0/24', $ids[2] ) ) );
		check( 'pending rows are ignored by code verification', null === Ashna_Logger::get_latest_record( $phone ) || 'pending' !== Ashna_Logger::get_latest_record( $phone )->status );
	}
);

/**
 * All orders in which N parallel requests can run their 3 steps (each request keeps its own
 * order). The requests in each check are interchangeable, so orders that only differ by which
 * request is called A, B or C are equivalent: keep the ones where A starts first, then B, then C.
 */
function interleavings( $n, $steps = 3 ) {
	$out  = array();
	$walk = function ( $left, $seq ) use ( &$walk, &$out, $n, $steps ) {
		if ( 0 === array_sum( $left ) ) {
			$out[] = $seq;
			return;
		}
		for ( $i = 0; $i < $n; $i++ ) {
			// Request i may only start after request i-1 has started.
			if ( $left[ $i ] === $steps && $i > 0 && $left[ $i - 1 ] === $steps ) {
				continue;
			}
			if ( $left[ $i ] > 0 ) {
				$next = $left;
				--$next[ $i ];
				$walk( $next, array_merge( $seq, array( $i ) ) );
			}
		}
	};
	$walk( array_fill( 0, $n, $steps ), array() );
	return $out;
}

/** Replay one interleaving of precheck → reserve → recount; return how many requests kept a slot. */
function replay( array $schedule, array $requests ) {
	global $wpdb;
	$wpdb->query( 'DELETE FROM ' . Ashna_Logger::table_name() ); // phpcs:ignore
	$state = array_fill( 0, count( $requests ), array( 'step' => 0, 'id' => 0, 'out' => null ) );
	foreach ( $schedule as $i ) {
		list( $phone, $ip ) = $requests[ $i ];
		$st                 = &$state[ $i ];
		if ( null !== $st['out'] ) {
			$st['step']++;
			unset( $st );
			continue;
		}
		if ( 0 === $st['step'] ) {
			if ( is_wp_error( Ashna_Rate_Limiter::precheck( $phone, $ip ) ) ) {
				$st['out'] = 'refused';
			}
		} elseif ( 1 === $st['step'] ) {
			$st['id'] = Ashna_Logger::reserve( $phone, $ip, Ashna_IP::subnet( $ip ) );
		} else {
			$st['out'] = is_wp_error( Ashna_Rate_Limiter::recount( $phone, $ip, $st['id'] ) ) ? 'refused' : 'sent';
		}
		$st['step']++;
		unset( $st );
	}
	$sent = 0;
	foreach ( $state as $st ) {
		$sent += 'sent' === $st['out'] ? 1 : 0;
	}
	return $sent;
}

scenario(
	'Every interleaving of 3 parallel requests stays inside the limits',
	function () {
		$schedules = interleavings( 3 );
		check( 'all 280 distinct orders of precheck / reserve / recount for 3 requests (1680 / 3!)', 280 === count( $schedules ) );

		fresh();
		$phone  = random_phone();
		$counts = array();
		foreach ( $schedules as $schedule ) {
			$counts[ replay( $schedule, array( array( $phone, '198.51.100.1' ), array( $phone, '198.51.100.2' ), array( $phone, '198.51.100.3' ) ) ) ] = true;
		}
		check( 'same number from 3 IPs: exactly 1 SMS in every order', array( 1 ) === array_keys( $counts ), array_keys( $counts ) );

		fresh( array( 'max_ip_requests_per_minute' => 2 ) );
		$counts = array();
		foreach ( $schedules as $schedule ) {
			$counts[ replay( $schedule, array( array( random_phone(), '203.0.113.7' ), array( random_phone(), '203.0.113.7' ), array( random_phone(), '203.0.113.7' ) ) ) ] = true;
		}
		check( 'one IP, 3 numbers, limit 2/min: exactly 2 SMS in every order', array( 2 ) === array_keys( $counts ), array_keys( $counts ) );

		fresh( array( 'daily_send_cap' => 2 ) );
		add_filter( 'ashna_otp_notify_admin', '__return_false' );
		$counts = array();
		foreach ( $schedules as $schedule ) {
			$counts[ replay( $schedule, array( array( random_phone(), '198.51.100.11' ), array( random_phone(), '198.51.100.12' ), array( random_phone(), '198.51.100.13' ) ) ) ] = true;
		}
		remove_filter( 'ashna_otp_notify_admin', '__return_false' );
		check( 'site-wide daily cap 2: exactly 2 SMS in every order', array( 2 ) === array_keys( $counts ), array_keys( $counts ) );
	}
);

scenario(
	'Honeypot: bots get a normal-looking answer, nothing is sent',
	function () {
		fresh();
		$phone = random_phone();
		list( $r, $code ) = ask( $phone, '203.0.113.7', array( 'ashna_website' => 'http://spam.example' ) );
		check( 'looks like success to the bot', ok_json( $r ) );
		check( 'no code generated, no row stored', null === $code && 0 === (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . Ashna_Logger::table_name() ) ); // phpcs:ignore
		$stats = Ashna_Rate_Limiter::block_stats();
		check( 'counted in today\'s refused requests', isset( $stats['counts']['ashna_honeypot'] ) );
	}
);

scenario(
	'Only the hash of the code is stored (readable code kept for the test gateway only)',
	function () {
		fresh( array( 'active_sms_gateway' => 'kavenegar', 'kavenegar_api_key' => 'k', 'kavenegar_template' => 't' ) );
		$ok = function () {
			return array( 'headers' => array(), 'body' => '{"return":{"status":200,"message":"ok"},"entries":[]}', 'response' => array( 'code' => 200, 'message' => '' ), 'cookies' => array(), 'filename' => null );
		};
		add_filter( 'pre_http_request', $ok );
		$phone            = random_phone();
		list( $r, $code ) = ask( $phone );
		remove_filter( 'pre_http_request', $ok );
		$row = Ashna_Logger::get_latest_record( $phone );
		check( 'code sent through a real gateway driver', ok_json( $r ) && $row && 'kavenegar' === $row->gateway, $r['raw'] );
		check( 'readable code NOT stored', $row && false === strpos( (string) $row->otp_code, (string) $code ) && '' !== (string) $row->otp_code );
		check( 'hash verifies the code', $row && wp_check_password( (string) $code, $row->otp_hash ) );
		check( 'the user can still sign in with it', true === Ashna_Security::verify_otp( $phone, $code ) );
		Ashna_Failover::reset_all();
	}
);

scenario(
	'Captcha: resend ticket, single use, strikes',
	function () {
		fresh( array( 'captcha_type' => 'math' ) );
		$phone   = random_phone();
		$captcha = Ashna_Security::generate_math_captcha();
		list( $r ) = ask( $phone, '203.0.113.7', array( 'captcha_answer' => solve( $captcha ), 'captcha_token' => $captcha['token'] ) );
		$ticket = data_of( $r, 'resend_token' );
		check( 'first request with captcha sent, resend ticket + next question returned', ok_json( $r ) && $ticket && data_of( $r, 'new_captcha' ), $r['raw'] );
		age_rows( 61 );
		list( $r ) = ask( $phone, '203.0.113.7', array( 'resend_token' => $ticket ) );
		check( 'resend for the same number without a new captcha', ok_json( $r ), $r['raw'] );
		age_rows( 61 );
		list( $r ) = ask( $phone, '203.0.113.99', array( 'resend_token' => $ticket ) );
		check( 'ticket is bound to the visitor (other IP needs the captcha)', 'ashna_captcha_empty' === code_of( $r ), $r['raw'] );
		list( $r ) = ask( random_phone(), '203.0.113.7', array( 'resend_token' => $ticket ) );
		check( 'ticket is bound to the number', 'ashna_captcha_empty' === code_of( $r ) );

		$codes = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$c         = Ashna_Security::generate_math_captcha();
			list( $r ) = ask( random_phone(), '203.0.113.66', array( 'captcha_answer' => '0', 'captcha_token' => $c['token'] ) );
			$codes[]   = code_of( $r );
		}
		// The counter is per IP, so first prove every answer really was refused as a captcha error
		// (a silently different answer here would make the lockout check below meaningless).
		$refused = array_values( array_filter( $codes, static function ( $code ) {
			return 0 === strpos( (string) $code, 'ashna_captcha_' );
		} ) );
		check( 'all 10 wrong answers are refused as captcha errors', 10 === count( $refused ), $codes );
		list( $r ) = ask( random_phone(), '203.0.113.66', array( 'captcha_answer' => '0', 'captcha_token' => 'x.y' ) );
		$c         = Ashna_Security::generate_math_captcha();
		list( $r ) = ask( random_phone(), '203.0.113.66', array( 'captcha_answer' => solve( $c ), 'captcha_token' => $c['token'] ) );
		check( '10 wrong answers lock the IP (brute force)', 'ashna_locked_out' === code_of( $r ), $r['raw'] );

		$tokens = array();
		for ( $i = 0; $i < 60; $i++ ) {
			$tokens[] = Ashna_Security::generate_math_captcha()['token'];
		}
		check( 'every question gets its own token, even in the same second with the same answer', 60 === count( array_unique( $tokens ) ) );

		Ashna_Security::purge_used_captcha_tokens();
		$left = (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->options} WHERE option_name LIKE %s", 'ashna\_otp\_cap\_%' ) ); // phpcs:ignore
		check( 'used-token markers kept until they expire (purge keeps fresh ones)', $left >= 10 );
	}
);

scenario(
	'Passkey sign-ins and whitelisted numbers',
	function () {
		fresh( array( 'max_requests_per_hour' => 1, 'whitelisted_identifiers' => '09120000999' ) );
		$phone = random_phone();
		Ashna_Logger::insert( array( 'recipient' => $phone, 'channel' => 'passkey', 'gateway' => 'webauthn', 'otp_code' => 'PASSKEY', 'status' => 'verified' ) );
		list( $r ) = ask( $phone );
		check( 'a passkey sign-in does not consume the number\'s code quota', ok_json( $r ), $r['raw'] );
		$ok = 0;
		for ( $i = 0; $i < 3; $i++ ) {
			list( $r ) = ask( '09120000999' );
			$ok       += ok_json( $r ) ? 1 : 0;
		}
		check( 'whitelisted number is exempt from limits', 3 === $ok );
	}
);

scenario(
	'Schema upgrade stores the network of each request',
	function () {
		fresh();
		ask( random_phone(), '198.51.100.77' );
		$row = $GLOBALS['wpdb']->get_row( 'SELECT * FROM ' . Ashna_Logger::table_name() . ' ORDER BY id DESC LIMIT 1' ); // phpcs:ignore
		check( 'ip_subnet column filled', $row && '198.51.100.0/24' === $row->ip_subnet, $row );
		check( 'DB version bumped', Ashna_Activator::DB_VERSION === get_option( 'ashna_otp_db_version' ) );
	}
);

fresh();
ashna_t_finish( 'security' );
