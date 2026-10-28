<?php
/**
 * Adversarial suite: the attacks a hostile visitor would run against Ashna.
 *
 * Every check asserts that the attack FAILED: no bypass, no crash, no leak, no escalation.
 * The harness turns any PHP warning/notice raised by Ashna code into an exception, so a fuzzed
 * input that makes Ashna emit a notice fails its own check as well.
 *
 * TEST ONLY — not shipped with the plugin. See tests/README.md.
 */

require_once __DIR__ . '/lib/wp-harness.php';
require_once __DIR__ . '/fixtures/third-party.php';

$_SERVER['REMOTE_ADDR'] = '203.0.113.7';

/** Baseline the attacks start from: protection defaults, no whitelist, sandbox gateway. */
$GLOBALS['at_base'] = array(
	'active_sms_gateway'             => 'sandbox',
	'backup_sms_gateway_1'           => 'none',
	'backup_sms_gateway_2'           => 'none',
	'backup_sms_gateway_3'           => 'none',
	'mobile_delivery_channel'        => 'sms',
	'login_mode'                     => 'phone_and_email',
	'auto_register'                  => 1,
	'whitelisted_identifiers'        => '',
	'trusted_proxies'                => '',
	'captcha_type'                   => 'none',
	'redirect_behavior'              => 'home',
	'require_name_on_register'       => 'optional',
	'require_email_on_register'      => 'disabled',
	'otp_length'                     => 5,
	'allow_password_login'           => 1,
	'wp_login_integration'           => 0,
	'wp_login_behavior'              => 'otp_only',
	'enable_passkey'                 => 1,
	'max_verify_attempts'            => 5,
	'lockout_duration'               => 900,
	'resend_cooldown'                => 60,
	'max_requests_per_hour'          => 100,
	'max_requests_per_day'           => 100,
	'max_ip_requests_per_minute'     => 20,
	'max_ip_requests_per_hour'       => 200,
	'max_subnet_requests_per_minute' => 0,
	'subnet_requests_per_minute'     => 0,
	'daily_send_cap'                 => 0,
	'attack_mode_threshold'          => 0,
	'attack_mode_minutes'            => 30,
	'trust_proxy_headers'            => 0,
	'delete_data_on_uninstall'       => 0,
	'respect_login_gates'            => 1,
	'respect_plugin_redirects'       => 1,
	'login_url_to_ashna'             => 0,
);

/**
 * Clear everything an earlier attack left behind: logs, lockouts, counters, attack mode.
 *
 * @param array $settings Settings for this scenario.
 */
function at_reset( array $settings = array() ) {
	global $wpdb;
	$wpdb->query( 'DELETE FROM ' . Ashna_Logger::table_name() ); // phpcs:ignore WordPress.DB
	foreach ( array_keys( Ashna_Security::get_active_lockouts() ) as $target ) {
		Ashna_Security::unlock_target( $target );
	}
	// phpcs:disable WordPress.DB
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ashna\_lock%'" );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_ashna\_%' OR option_name LIKE 'ashna\_otp\_cap\_%' OR option_name LIKE 'ashna\_capfail\_%'" );
	// phpcs:enable WordPress.DB
	delete_option( 'ashna_otp_block_stats' );
	Ashna_Rate_Limiter::end_attack_mode();
	ashna_t_settings( array_merge( $GLOBALS['at_base'], $settings ) );
	Ashna_Failover::reset_all();
	wp_cache_flush();
	browser_guest();
	Ashna_T::$cookies = array();
	Ashna_T::$logins  = array();
	Ashna_T::$codes   = array();
}

/** A guest POST with a freshly created nonce. */
function at_post( $action, array $data = array(), array $server = array() ) {
	return ajax( $action, array_merge( array( 'nonce' => wp_create_nonce( 'ashna_otp_nonce' ) ), $data ), $server );
}

/** The response is JSON with an HTTP status Ashna intends to send: no fatal, no 500, no HTML. */
function at_clean( $res ) {
	return is_array( $res ) && is_array( $res['json'] ) && isset( $res['json']['success'] ) && in_array( (int) $res['status'], array( 200, 403 ), true );
}

/** Tables, users and administrators are still what they were. */
function at_intact() {
	global $wpdb;
	return array(
		'logs_table' => $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Ashna_Logger::table_name() ) ),
		'admins'     => count( get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) ) ),
		'users'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" ), // phpcs:ignore WordPress.DB
	);
}

/** Ask for a code as a given visitor; returns [response, code]. */
function at_code( $identifier, array $server = array() ) {
	$key = Ashna_Helper::parse_identifier( (string) $identifier )['normalized'];
	return array( at_post( 'ashna_request_otp', array( 'identifier' => $identifier ), $server ), isset( Ashna_T::$codes[ $key ] ) ? Ashna_T::$codes[ $key ] : null );
}

function at_verify( $identifier, $code, array $extra = array() ) {
	return at_post( 'ashna_verify_otp', array_merge( array( 'identifier' => $identifier, 'code' => $code ), $extra ) );
}

/** Solve the internal math captcha the way a human does: read the question, add. */
function at_solve( array $captcha ) {
	preg_match( '/(\d+)\s*\+\s*(\d+)/', (string) $captcha['question'], $m );
	return (string) ( (int) $m[1] + (int) $m[2] );
}

function at_captcha_post( $identifier, array $server = array() ) {
	$q = Ashna_Security::generate_math_captcha();
	return at_post( 'ashna_request_otp', array( 'identifier' => $identifier, 'captcha_answer' => at_solve( $q ), 'captcha_token' => $q['token'] ), $server );
}

/**
 * Register the admin AJAX endpoints the way a real admin request does.
 *
 * admin-ajax.php is an admin request, so Ashna_Admin (and with it the wp_ajax_ hooks) exists
 * there. A CLI harness is not an admin request, so the instance is created explicitly.
 */
function at_admin_boot() {
	Ashna_Admin::instance();
}

/** The endpoint answers with a JSON error/refusal rather than running. */
function at_refused( $res ) {
	return ! ok_json( $res ) && true === at_clean( $res );
}

/** A signed-in administrator (created once). */
function as_admin_attack() {
	static $admin = null;
	if ( null === $admin ) {
		$admin = make_user( 'at_admin', 'administrator' );
	}
	wp_set_current_user( $admin->ID );
	return $admin;
}

/** Administrator AJAX call with a valid admin nonce. */
function at_admin_post( $action, array $data = array() ) {
	as_admin_attack();
	at_admin_boot();
	return ajax( $action, array_merge( array( 'nonce' => wp_create_nonce( 'ashna_admin_nonce' ) ), $data ) );
}

/** Payloads a hostile client sends where a scalar is expected. */
function at_payloads() {
	return array(
		'array'          => array( 'a' ),
		'nested array'   => array( 'a' => array( 'b' => array( 'c' ) ) ),
		'assoc array'    => array( 'key' => 'value' ),
		'very long'      => str_repeat( 'A', 20000 ),
		'null byte'      => "12\0 34",
		'invalid utf8'   => "\xC3\x28\xA0\xA1",
		'sql quotes'     => "'\"`",
		'sql injection'  => "' OR 1=1 -- ",
		'sql drop'       => "1'; DROP TABLE " . Ashna_Logger::table_name() . '; --',
		'sql union'      => "' UNION SELECT user_login,user_pass FROM " . $GLOBALS['wpdb']->users . ' -- ',
		'xss'            => '<script>alert(1)</script>',
		'xss attr'       => '" onmouseover="alert(1)',
		'template'       => '{{7*7}}{${7*7}}<%= 7*7 %>',
		'format string'  => '%1$s%n%s%99999999s',
		'path traversal' => '../../../../etc/passwd',
		'url'            => 'http://evil.example/x.php?a=1',
		'url scheme'     => 'javascript:alert(document.cookie)',
		'crlf'           => "a\r\nSet-Cookie: ashna_hacked=1",
		'rtl override'   => "\xE2\x80\xAE\xE2\x80\xADgpj.exe",
		'php object'     => 'O:8:"stdClass":1:{s:1:"a";s:1:"b";}',
		'json'           => '{"__proto__":{"polluted":true}}',
		'huge number'    => '99999999999999999999999999999999999999',
		'negative'       => '-1',
		'nan'            => 'NaN',
		'infinity'       => 'Infinity',
	);
}

/**
 * Literal sequences that only survive into the page when the output was NOT escaped.
 *
 * Escaped text (&lt;img src=x onerror=alert(1)&gt;) still contains "onerror=alert(1)", so a
 * substring search alone would report a leak where there is none.
 */
function at_needles() {
	return array( '<script>alert(1)', '</textarea><svg', '<svg/onload', '<iframe src=javascript', '<img src=x onerror', '<img src="x" onerror', '"><img src=x' );
}

/**
 * Whether this database driver honours backslash escapes in LIKE patterns.
 *
 * The old single-file wp-sqlite-db drop-in (needed only for the WordPress 5.8 test site) treats
 * \_ as a literal underscore, so a search for "%" or "_" matches every row there. MySQL and the
 * official sqlite-database-integration driver handle it, so the wildcard checks only run where
 * the escape is meaningful. Detected by looking for a row whose name only matches when _ is
 * treated as a single-character wildcard.
 *
 * @return bool
 */
function at_like_escaped() {
	global $wpdb;
	$probe = 'ashnaXprobeYzz';
	add_option( $probe, '1', '', false );
	$pattern = $wpdb->esc_like( 'ashna_probe_' ) . '%';
	// phpcs:ignore WordPress.DB
	$matched = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern ) );
	delete_option( $probe );
	return 0 === $matched;
}

/**
 * Whether the DOM extension can be used on this PHP build.
 *
 * php-wasm's PHP 7.4 libxml aborts the whole process inside libxml_clear_errors()
 * ("null function or function signature mismatch"), which would kill the run instead of
 * reporting a failure. On 7.4 the two literal-needle passes still run; the parsed-attribute
 * pass is verified on PHP 8, where the rendered markup is the same.
 *
 * @return bool
 */
function at_dom_usable() {
	return PHP_VERSION_ID >= 80000;
}

/**
 * Collect every place a payload survived into live markup.
 *
 * Two signals, both free of false positives:
 *  - a dangerous literal sequence (escaping always rewrites it);
 *  - a *parsed* attribute that executes: an on* handler running code from the payload, or an
 *    href/src with a javascript:/data: scheme. Parsing matters: the same text inside an escaped
 *    attribute value (&lt;img src=x onerror=alert(1)&gt; or value="&quot; onmouseover=...") is inert
 *    and must not be reported, while an attribute that really broke out is.
 */
function at_scan( $label, $html, array &$leaks ) {
	$html = (string) $html;
	foreach ( at_needles() as $needle ) {
		if ( false !== strpos( $html, $needle ) ) {
			$leaks[] = $label . ' → raw ' . $needle;
		}
	}
	if ( ! class_exists( 'DOMDocument' ) || ! at_dom_usable() ) {
		return;
	}
	$previous = libxml_use_internal_errors( true );
	$doc      = new DOMDocument();
	$doc->loadHTML( '<?xml encoding="UTF-8"><div id="ashna-scan">' . $html . '</div>', LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	foreach ( $doc->getElementsByTagName( '*' ) as $element ) {
		foreach ( $element->attributes as $attribute ) {
			$name  = strtolower( $attribute->nodeName );
			$value = (string) $attribute->nodeValue;
			if ( 0 === strpos( $name, 'on' ) && preg_match( '/alert\s*\(|prompt\s*\(|eval\s*\(|document\.cookie|<[a-z\/]/i', $value ) ) {
				$leaks[] = $label . ' → executing handler ' . $name . '=' . substr( $value, 0, 80 );
			}
			if ( preg_match( '/^(href|src|action|formaction|xlink:href|data)$/', $name ) && preg_match( '/^\s*(javascript|data|vbscript)\s*:/i', $value ) ) {
				$leaks[] = $label . ' → executing URL ' . $name . '=' . substr( $value, 0, 80 );
			}
		}
	}
}

/** Render a callable and scan its output. */
function at_render( $label, callable $fn, array &$leaks ) {
	$level = ob_get_level();
	ob_start();
	try {
		$fn();
	} catch ( Throwable $e ) {
		while ( ob_get_level() > $level ) {
			ob_end_clean();
		}
		$leaks[] = $label . ' threw ' . get_class( $e ) . ': ' . $e->getMessage();
		return;
	}
	$html = '';
	while ( ob_get_level() > $level ) {
		$html = (string) ob_get_clean() . $html;
	}
	at_scan( $label, $html, $leaks );
}

/* =========================================================================
 * 1. The nonce gate: none, forged, someone else's, guest-vs-member confusion.
 * ======================================================================= */
scenario(
	'Nonce gate on every public endpoint',
	function () {
		at_reset();
		// The Dokan endpoint only exists while Dokan is active: bring it up like a Dokan site.
		ashna_fixture_dokan( 4242 );
		Ashna_Integration_Dokan::boot();

		$other_user = make_user( 'at_other', 'subscriber' );
		wp_set_current_user( $other_user->ID );
		$member_nonce = wp_create_nonce( 'ashna_otp_nonce' );
		browser_guest();
		$guest_nonce = wp_create_nonce( 'ashna_otp_nonce' );

		$public = array(
			'ashna_request_otp'           => array( 'identifier' => random_phone() ),
			'ashna_verify_otp'            => array( 'identifier' => random_phone(), 'code' => '12345' ),
			'ashna_password_login'        => array( 'identifier' => 'somebody', 'password' => 'x' ),
			'ashna_passkey_login_options' => array(),
			'ashna_passkey_login_verify'  => array( 'credential_id' => 'x', 'client_data_json' => 'x', 'authenticator_data' => 'x', 'signature' => 'x' ),
			'ashna_dokan_check_shopurl'   => array( 'shopurl' => 'my-shop' ),
		);
		foreach ( $public as $action => $data ) {
			$missing = ajax( $action, $data );
			check( 'no nonce: ' . $action . ' refused with 403 JSON', at_refused( $missing ) && 403 === $missing['status'], $missing['raw'] );
			$forged = ajax( $action, array_merge( $data, array( 'nonce' => 'not-a-nonce' ) ) );
			check( 'forged nonce: ' . $action . ' refused', at_refused( $forged ) && 403 === $forged['status'], $forged['raw'] );
			$empty = ajax( $action, array_merge( $data, array( 'nonce' => '' ) ) );
			check( 'empty nonce: ' . $action . ' refused', at_refused( $empty ) && 403 === $empty['status'], $empty['raw'] );
			$tampered = ajax( $action, array_merge( $data, array( 'nonce' => substr( $guest_nonce, 0, -1 ) . ( '0' === substr( $guest_nonce, -1 ) ? '1' : '0' ) ) ) );
			check( 'tampered nonce: ' . $action . ' refused', at_refused( $tampered ) && 403 === $tampered['status'], $tampered['raw'] );
		}

		// The endpoint still works with a valid nonce (the gate is not "block everyone").
		$ok = at_post( 'ashna_request_otp', array( 'identifier' => random_phone() ) );
		check( 'a valid nonce still opens the endpoint', ok_json( $ok ), $ok['raw'] );
		$shop = at_post( 'ashna_dokan_check_shopurl', array( 'shopurl' => 'my-shop' ) );
		check( 'the Dokan slug check works with a valid nonce', ok_json( $shop ), $shop['raw'] );

		// Another visitor's nonce must not open my request, and vice versa.
		wp_set_current_user( $other_user->ID );
		Ashna_T::$cookies = array();
		$res              = ajax( 'ashna_passkey_register_options', array( 'nonce' => $guest_nonce ) );
		check( 'a guest nonce does not open a signed-in request (passkey registration)', at_refused( $res ) && 403 === $res['status'], $res['raw'] );
		browser_guest();
		$res = ajax( 'ashna_request_otp', array( 'nonce' => $member_nonce, 'identifier' => random_phone() ) );
		check( 'a member nonce does not open a guest request', at_refused( $res ) && 403 === $res['status'], $res['raw'] );

		// Another member's nonce must not delete my passkey.
		$victim = make_user( 'at_nonce_victim', 'subscriber' );
		$cred   = Ashna_Passkey::base64url_encode( random_bytes( 16 ) );
		Ashna_Passkey::save_user_passkey( $victim->ID, array( 'id' => $cred, 'public_key_pem' => 'x', 'alg' => -7 ) );
		wp_set_current_user( $other_user->ID );
		$res  = ajax( 'ashna_passkey_delete', array( 'nonce' => $guest_nonce, 'credential_id' => $cred ) );
		$kept = Ashna_Passkey::get_user_passkeys( $victim->ID );
		check( 'guest nonce + another member’s credential deletes nothing', ! ok_json( $res ) && isset( $kept[ $cred ] ), $res['raw'] );

		// A signed-in administrator is not exempt from the nonce requirement either.
		as_admin_attack();
		$res = ajax( 'ashna_request_otp', array( 'identifier' => random_phone() ) );
		check( 'signed-in admin without a nonce is refused', at_refused( $res ) && 403 === $res['status'], $res['raw'] );

		/*
		 * ashna_refresh_session is deliberately open: it hands a cached page's form a fresh nonce
		 * (and fresh captcha questions) without doing anything else. Prove it stays harmless.
		 */
		browser_guest();
		Ashna_T::$cookies = array();
		Ashna_T::$logins  = array();
		$refresh          = ajax( 'ashna_refresh_session', array() );
		check( 'refresh-session (by design, no nonce) answers a guest', ok_json( $refresh ), (string) $refresh['raw'] );
		check( 'refresh-session signs nobody in', empty( Ashna_T::$logins ) && empty( Ashna_T::$cookies ) && 0 === get_current_user_id(), Ashna_T::$logins );
		check( 'refresh-session returns only a nonce and the visitor’s own state', false === strpos( (string) $refresh['raw'], 'user_pass' ) && false === strpos( (string) $refresh['raw'], 'user_email' ) && 1 === wp_verify_nonce( data_of( $refresh, 'nonce' ), 'ashna_otp_nonce' ), (string) $refresh['raw'] );
		check( 'refresh-session gives a caller no member nonce', false === data_of( $refresh, 'logged_in', false ) );

		// The nonce it hands out is usable for the form (and only for the public form).
		$fresh = data_of( $refresh, 'nonce' );
		$res   = ajax( 'ashna_request_otp', array( 'nonce' => $fresh, 'identifier' => random_phone() ) );
		check( 'the refreshed nonce is valid for the public form', ok_json( $res ), $res['raw'] );
		browser_guest();
	}
);

/* =========================================================================
 * 2. Input fuzzing: arrays, 20 KB strings, NUL bytes, broken UTF-8, SQL, XSS.
 * ======================================================================= */
scenario(
	'Fuzzing every request parameter with hostile input',
	function () {
		at_reset( array( 'resend_cooldown' => 0, 'max_ip_requests_per_minute' => 100000 ) );
		add_filter( 'ashna_otp_notify_admin', '__return_false' );
		$before = at_intact();
		$calls  = 0;
		$bad    = array();

		$targets = array(
			'ashna_request_otp'           => array( 'identifier', 'resend_token', 'captcha_answer', 'captcha_token', 'ashna_website', 'redirect_to', 'context', 'full_name', 'user_email' ),
			'ashna_verify_otp'            => array( 'identifier', 'code', 'full_name', 'user_email', 'redirect_to', 'context', 'account_type', 'fname', 'lname', 'shopname', 'shopurl', 'vendor_phone', 'vendor_form_nonce' ),
			'ashna_password_login'        => array( 'identifier', 'password', 'redirect_to', 'context' ),
			'ashna_refresh_session'       => array( 'redirect_to' ),
			'ashna_dokan_check_shopurl'   => array( 'shopurl' ),
			'ashna_passkey_login_verify'  => array( 'credential_id', 'client_data_json', 'authenticator_data', 'signature', 'redirect_to' ),
			'ashna_passkey_login_options' => array( 'identifier' ),
		);
		foreach ( $targets as $action => $params ) {
			foreach ( $params as $param ) {
				foreach ( at_payloads() as $label => $payload ) {
					++$calls;
					try {
						$res = at_post( $action, array( $param => $payload ) );
						if ( ! at_clean( $res ) ) {
							$bad[] = $action . '[' . $param . '] = ' . $label . ' → HTTP ' . $res['status'] . ' ' . substr( (string) $res['raw'], 0, 100 );
						}
					} catch ( Throwable $e ) {
						$bad[] = $action . '[' . $param . '] = ' . $label . ' → ' . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine();
					}
				}
			}
		}

		// The same treatment for the administrator endpoints: a hijacked admin session, or an
		// administrator who clicks a crafted link, must not be able to break the site either.
		$admin_targets = array(
			'ashna_admin_save_settings'        => array( 'ashna', 'nonce' ),
			'ashna_admin_test_gateway'         => array( 'gateway' ),
			'ashna_admin_unlock_target'        => array( 'target', 'ip' ),
			'ashna_admin_import_settings'      => array( 'json_data' ),
			'ashna_admin_trust_proxy'          => array( 'ip', 'range' ),
			'ashna_admin_fetch_arvan_ranges'   => array( 'force' ),
			'ashna_admin_reset_settings'       => array(),
			'ashna_admin_reset_gateway_health' => array(),
			'ashna_admin_end_attack_mode'      => array(),
		);
		$admin_nonce = wp_create_nonce( 'ashna_admin_nonce' );
		foreach ( $admin_targets as $action => $params ) {
			foreach ( $params as $param ) {
				foreach ( at_payloads() as $label => $payload ) {
					++$calls;
					try {
						$res = at_admin_post( $action, array( $param => $payload ) );
						if ( ! at_clean( $res ) ) {
							$bad[] = $action . '[' . $param . '] = ' . $label . ' → HTTP ' . $res['status'] . ' ' . substr( (string) $res['raw'], 0, 100 );
						}
					} catch ( Throwable $e ) {
						$bad[] = $action . '[' . $param . '] = ' . $label . ' → ' . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine();
					}
				}
			}
		}

		// Public pages read $_GET too: a visitor must not be able to break a page render.
		browser_guest();
		ashna_fixture_woocommerce();
		ashna_fixture_um( array() );
		foreach ( array( 'redirect_to', 'action', 'ashna_action', 'interim-login', 'paged', 's', 'status', 'channel' ) as $param ) {
			foreach ( at_payloads() as $label => $payload ) {
				++$calls;
				$_GET     = array( $param => $payload );
				$_REQUEST = $_GET;
				try {
					// The render paths echo their HTML: buffer it so the suite's own output stays readable.
					ob_start();
					Ashna_Frontend::instance()->render_global_modal();
					Ashna_Integrations::replace_member_login_form( false, 'ultimatemember', array( 'form_id' => 1 ) );
					do_action( 'wp_enqueue_scripts' );
					ob_end_clean();
				} catch ( Throwable $e ) {
					if ( ob_get_level() > 0 ) {
						ob_end_clean();
					}
					$bad[] = 'frontend GET ' . $param . ' = ' . $label . ' → ' . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine();
				}
			}
		}
		$_GET     = array();
		$_REQUEST = array();

		/*
		 * A parameter that one endpoint consumes can reach a different one through a shared helper.
		 * Cross every endpoint with the high-risk parameter names, not only the ones it documents,
		 * and reach each endpoint in the session state it really exists in: guests for the public
		 * handlers, a member for the passkey ones, an administrator for the ashna_admin_* ones.
		 */
		$cross_keys = array( 'nonce', 'identifier', 'redirect_to', 'code', 'shopurl', 'credential_id', 'target', 'ip', 'json_data' );
		$cross_val  = array( array( 'x' ), array( 'a' => array( 'b' ) ), "x\0y" );
		$cross      = array();
		foreach ( array_keys( $targets ) as $action ) {
			$cross[ $action ] = 'guest';
		}
		foreach ( array_keys( $admin_targets ) as $action ) {
			$cross[ $action ] = 'admin';
		}
		foreach ( array( 'ashna_passkey_delete', 'ashna_passkey_register_options', 'ashna_passkey_register_verify' ) as $action ) {
			$cross[ $action ] = 'member';
		}
		$member = make_user( 'at_cross_member', 'subscriber' );
		foreach ( $cross as $action => $as ) {
			foreach ( $cross_keys as $param ) {
				foreach ( $cross_val as $payload ) {
					++$calls;
					$data = array( $param => $payload );
					try {
						if ( 'admin' === $as ) {
							$res = at_admin_post( $action, $data );
						} else {
							if ( 'member' === $as ) {
								wp_set_current_user( $member->ID );
							} else {
								browser_guest();
							}
							$res = at_post( $action, $data );
						}
						// Ashna answers its own JSON, and the handlers that use core's check_ajax_referer()
						// answer WordPress's canonical "-1" body. Anything else is a defect.
						if ( ! at_clean( $res ) && '-1' !== trim( (string) $res['raw'] ) ) {
							$bad[] = $as . ' ' . $action . '[' . $param . '] = ' . substr( wp_json_encode( $payload ), 0, 30 ) . ' → HTTP ' . $res['status'] . ' ' . substr( (string) $res['raw'], 0, 80 );
						}
					} catch ( Throwable $e ) {
						$bad[] = $as . ' ' . $action . '[' . $param . '] cross → ' . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine();
					}
				}
			}
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $member->ID );
		browser_guest();
		remove_filter( 'ashna_otp_notify_admin', '__return_false' );

		check( $calls . ' hostile requests handled (clean JSON, no 500, no fatal, no page break)', empty( $bad ), array_slice( $bad, 0, 6 ) );
		check( 'no session was created by any fuzzed request', 0 === get_current_user_id() );
		$after = at_intact();
		check( 'database intact after the fuzz (logs table, user count, admin count)', $before === $after, array( 'before' => $before, 'after' => $after ) );
		check( 'the site still answers normally afterwards', ok_json( at_post( 'ashna_request_otp', array( 'identifier' => random_phone() ) ) ) );
		at_reset();
	}
);

/* =========================================================================
 * 3. Account enumeration.
 * ======================================================================= */
scenario(
	'Account enumeration through the code-request and code-verify answers',
	function () {
		at_reset( array( 'resend_cooldown' => 0 ) );
		$known = make_user( 'at_known', 'subscriber', array( 'ashna_phone' => random_phone() ) );
		$phone = get_user_meta( $known->ID, 'ashna_phone', true );

		list( $r1 ) = at_code( $phone );
		list( $r2 ) = at_code( random_phone() );
		check( 'a code is requested for both known and unknown numbers', ok_json( $r1 ) && ok_json( $r2 ), $r1['raw'] . ' | ' . $r2['raw'] );

		$diff = array();
		foreach ( array( 'masked', 'type', 'channel', 'cooldown', 'expiry', 'otp_length' ) as $key ) {
			$v1   = data_of( $r1, $key );
			$v2   = data_of( $r2, $key );
			$same = ( 'masked' === $key ) ? ( strlen( (string) $v1 ) === strlen( (string) $v2 ) ) : ( $v1 === $v2 );
			if ( ! $same ) {
				$diff[ $key ] = array( $v1, $v2 );
			}
		}
		check( 'the answer does not differ between known and unknown numbers', empty( $diff ), $diff );
		check( 'no code, hash or user id is echoed back', false === strpos( (string) $r1['raw'], (string) Ashna_T::$codes[ $phone ] ) && null === data_of( $r1, 'user_id' ) && null === data_of( $r1, 'otp_hash' ), (string) $r1['raw'] );
		// Documented trade-off: the form asks a new visitor for a name, so the client learns
		// "not registered yet". It never learns *who* is registered.
		check( 'a registered number is reported as an existing account', false === data_of( $r1, 'is_new_user', false ), data_of( $r1, 'is_new_user' ) );
		check( 'an unregistered number is reported as a new account', true === data_of( $r2, 'is_new_user', true ), data_of( $r2, 'is_new_user' ) );

		// Compare like with like: with a pending code, and without one.
		$unknown = random_phone();
		list( , $code_known )  = at_code( $phone );
		list( , $code_new )    = at_code( $unknown );
		$wrong_known = '11111' === $code_known ? '22222' : '11111';
		$wrong_new   = '11111' === $code_new ? '22222' : '11111';
		browser_guest();
		$res_known = at_verify( $phone, $wrong_known );
		browser_guest();
		$res_new = at_verify( $unknown, $wrong_new );
		check( 'a wrong code answers identically for a registered and an unregistered number', data_of( $res_known, 'code' ) === data_of( $res_new, 'code' ) && data_of( $res_known, 'message' ) === data_of( $res_new, 'message' ), array( data_of( $res_known, 'code' ), data_of( $res_new, 'code' ) ) );

		at_reset( array( 'resend_cooldown' => 0 ) );
		browser_guest();
		$a = at_verify( $phone, '11111' );
		browser_guest();
		$b = at_verify( random_phone(), '11111' );
		check( 'a code that was never requested answers identically too (no account oracle)', data_of( $a, 'code' ) === data_of( $b, 'code' ) && data_of( $a, 'message' ) === data_of( $b, 'message' ), array( data_of( $a, 'code' ), data_of( $b, 'code' ) ) );
		at_reset();
	}
);

/* =========================================================================
 * 4. OTP guessing, replay, wrong recipient, expiry, digits.
 * ======================================================================= */
scenario(
	'Guessing and reusing one-time codes',
	function () {
		at_reset( array( 'resend_cooldown' => 0 ) );
		$phone = random_phone();
		list( $req, $code ) = at_code( $phone );
		check( 'code issued', ok_json( $req ) && $code, $req['raw'] );

		$refused = 0;
		for ( $i = 0; $i < 8; $i++ ) {
			$guess = str_pad( (string) $i, strlen( (string) $code ), '0', STR_PAD_LEFT );
			if ( $guess === (string) $code ) {
				continue;
			}
			browser_guest();
			if ( ! ok_json( at_verify( $phone, $guess ) ) ) {
				++$refused;
			}
		}
		check( 'every wrong guess refused', $refused >= 8, $refused );
		browser_guest();
		$res = at_verify( $phone, $code );
		check( 'the real code is dead after the attempt limit', ! ok_json( $res ) && 0 === get_current_user_id(), $res['raw'] );
		check( 'the account is locked out', is_wp_error( Ashna_Security::check_lockout( $phone ) ) );

		// A fresh number: one correct use, then nothing.
		at_reset( array( 'resend_cooldown' => 0 ) );
		list( , $code2 ) = at_code( $phone );
		$res             = at_verify( $phone, $code2 );
		check( 'a correct code signs in', ok_json( $res ) && 0 !== get_current_user_id(), $res['raw'] );
		track_user_id( get_current_user_id() );
		browser_guest();
		$res = at_verify( $phone, $code2 );
		check( 'the same code cannot be used twice', ! ok_json( $res ) && 0 === get_current_user_id(), $res['raw'] );

		// A code issued for one number must not open another account.
		at_reset( array( 'resend_cooldown' => 0 ) );
		$victim = make_user( 'at_victim', 'subscriber', array( 'ashna_phone' => random_phone() ) );
		$victim_phone = get_user_meta( $victim->ID, 'ashna_phone', true );
		list( , $victim_code ) = at_code( $victim_phone );
		$attacker_phone = random_phone();
		list( , $attacker_code ) = at_code( $attacker_phone );
		browser_guest();
		$res = at_verify( $attacker_phone, $victim_code );
		check( 'another visitor’s code is refused', ! ok_json( $res ) && 0 === get_current_user_id(), $res['raw'] );
		browser_guest();
		$res = at_verify( $victim_phone, $attacker_code );
		check( 'a code for a brand-new number cannot sign in as the victim', ! ok_json( $res ) && 0 === get_current_user_id(), $res['raw'] );

		// Expired codes.
		at_reset( array( 'resend_cooldown' => 0 ) );
		$phone = random_phone();
		list( , $code3 ) = at_code( $phone );
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Ashna_Logger::table_name() . ' SET expires_at = %s, created_at = %s WHERE recipient = %s', gmdate( 'Y-m-d H:i:s', time() - 3600 ), gmdate( 'Y-m-d H:i:s', time() - 3700 ), $phone ) ); // phpcs:ignore WordPress.DB
		$res = at_verify( $phone, $code3 );
		check( 'an expired code is refused', ! ok_json( $res ) && 0 === get_current_user_id(), $res['raw'] );

		// Whitespace and padding must not turn a wrong code into an accepted one.
		at_reset( array( 'resend_cooldown' => 0 ) );
		$phone = random_phone();
		list( , $code4 ) = at_code( $phone );
		$wrong = ( '11111' === (string) $code4 || '1111' === (string) $code4 ) ? '22222' : '11111';
		$wrong = substr( $wrong, 0, strlen( (string) $code4 ) );
		$accepted = array();
		foreach ( array( ' ' . $wrong . ' ', $wrong . "\n", "\t" . $wrong, $wrong . "\r\n", '0' . $wrong, $wrong . '0' ) as $trick ) {
			browser_guest();
			if ( ok_json( at_verify( $phone, $trick ) ) ) {
				$accepted[] = wp_json_encode( $trick );
			}
		}
		check( 'padded and newline-wrapped wrong codes are all refused', empty( $accepted ), $accepted );

		// Persian digits: accepted for the same account, never for a different one.
		browser_guest();
		$persian = strtr( (string) $code4, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
		$res     = at_verify( $phone, $persian );
		$owner   = get_user_by( 'login', 'at_victim' );
		check( 'Persian digits sign in only the number they belong to (or are refused)', ! ok_json( $res ) || 0 === get_current_user_id() || ( get_userdata( get_current_user_id() ) && $phone === get_user_meta( get_current_user_id(), 'ashna_phone', true ) ), $res['raw'] );
		track_user_id( get_current_user_id() );
		at_reset();
	}
);

/* =========================================================================
 * 5. Lockout escape.
 * ======================================================================= */
scenario(
	'Lockout cannot be escaped by rewriting the identifier or the IP',
	function () {
		at_reset( array( 'max_verify_attempts' => 3, 'resend_cooldown' => 0 ) );
		// A fresh number per run: a fixed one would belong to the account an earlier run created,
		// and the sign-in below would then land on that (equally valid) user.
		$phone = random_phone();
		$user  = make_user( 'at_lock', 'subscriber', array( 'ashna_phone' => $phone ), 'Real-Pass-1!' );
		browser_guest();
		for ( $i = 0; $i < 3; $i++ ) {
			at_post( 'ashna_password_login', array( 'identifier' => $phone, 'password' => 'wrong-' . $i ) );
			browser_guest();
		}
		check( 'account locked after 3 password failures', is_wp_error( Ashna_Security::check_lockout( $phone ) ) );

		$variants = array(
			'+989121234567',
			'00989121234567',
			'989121234567',
			'0912 123 4567',
			'0912-123-4567',
			'(0912) 1234567',
			strtr( $phone, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) ),
			$user->user_login,
			strtoupper( $user->user_email ),
		);
		$alive = array();
		foreach ( $variants as $variant ) {
			browser_guest();
			$res = at_post( 'ashna_password_login', array( 'identifier' => $variant, 'password' => 'Real-Pass-1!' ) );
			if ( ok_json( $res ) || 0 !== get_current_user_id() ) {
				$alive[] = $variant;
			}
		}
		check( 'all ' . count( $variants ) . ' rewrites of the locked identifier stay refused, correct password included', empty( $alive ), $alive );

		// Spoofed proxy headers must not move the attacker into a fresh bucket.
		foreach ( array( 'HTTP_X_FORWARDED_FOR', 'HTTP_CF_CONNECTING_IP', 'HTTP_TRUE_CLIENT_IP', 'HTTP_X_REAL_IP', 'HTTP_FORWARDED' ) as $header ) {
			browser_guest();
			$res = at_post( 'ashna_password_login', array( 'identifier' => $phone, 'password' => 'Real-Pass-1!' ), array( $header => '198.51.100.9' ) );
			check( 'spoofed ' . $header . ' does not reset the lockout', ! ok_json( $res ) && 0 === get_current_user_id(), $res['raw'] );
		}

		// OTP requests for the locked account are refused as well.
		browser_guest();
		list( $req ) = at_code( $phone, array( 'REMOTE_ADDR' => '203.0.113.8' ) );
		check( 'no new code is sent to a locked account', ! ok_json( $req ), $req['raw'] );

		// The lock is lasting, not permanent: once lifted, the owner gets back in.
		foreach ( array_keys( Ashna_Security::get_active_lockouts() ) as $target ) {
			Ashna_Security::unlock_target( $target );
		}
		browser_guest();
		$res = at_post( 'ashna_password_login', array( 'identifier' => $phone, 'password' => 'Real-Pass-1!' ) );
		check( 'after unlocking, the real password works again', ok_json( $res ) && get_current_user_id() === $user->ID, $res['raw'] );
		browser_guest();
		at_reset();
	}
);

/* =========================================================================
 * 6. Password oracle.
 * ======================================================================= */
scenario(
	'Password sign-in gives no account oracle',
	function () {
		at_reset( array( 'max_verify_attempts' => 50, 'resend_cooldown' => 0 ) );
		$user = make_user( 'at_oracle', 'subscriber', array( 'ashna_phone' => '09129876543' ), 'Real-Pass-1!' );
		browser_guest();
		$no_such = at_post( 'ashna_password_login', array( 'identifier' => 'no_such_user_' . wp_rand(), 'password' => 'Real-Pass-1!' ) );
		check( 'unknown account refused', ! ok_json( $no_such ) && 0 === get_current_user_id(), $no_such['raw'] );
		browser_guest();
		$wrong_pass = at_post( 'ashna_password_login', array( 'identifier' => $user->user_login, 'password' => 'x' ) );
		check( 'wrong password refused', ! ok_json( $wrong_pass ) && 0 === get_current_user_id(), $wrong_pass['raw'] );
		check( 'the same message for both (no oracle)', data_of( $no_such, 'message' ) === data_of( $wrong_pass, 'message' ), array( data_of( $no_such, 'message' ), data_of( $wrong_pass, 'message' ) ) );

		// Failures on an address with no account count too, so the lockout cannot be used as an oracle.
		ashna_t_settings( array( 'max_verify_attempts' => 3, 'resend_cooldown' => 0 ) );
		$ghost = 'ghost_' . wp_rand() . '@example.com';
		$real  = 'ghostlike_' . wp_rand() . '@example.com';
		$u2    = make_user( 'at_oracle2', 'subscriber', array( 'ashna_phone' => '09129876544' ), 'Real-Pass-1!' );
		wp_update_user( array( 'ID' => $u2->ID, 'user_email' => $real ) );
		$msgs = array();
		foreach ( array( 'ghost' => $ghost, 'real' => $real ) as $kind => $identifier ) {
			$seen = array();
			for ( $i = 0; $i < 6; $i++ ) {
				browser_guest();
				$res    = at_post( 'ashna_password_login', array( 'identifier' => $identifier, 'password' => 'x' ) , array( 'REMOTE_ADDR' => '198.51.10' . ( 'ghost' === $kind ? '0' : '1' ) . '.' . $i ) );
				$seen[] = (string) data_of( $res, 'message' );
			}
			$msgs[ $kind ] = $seen;
			// The locked-out answer must appear for the address with no account as well.
			check( $kind . ': repeated failures end in the same lockout answer', count( array_unique( $seen ) ) > 1 && is_wp_error( Ashna_Security::check_lockout( $identifier ) ), array_unique( $seen ) );
		}
		check( 'the sequence of answers is identical for a registered and an unregistered address', $msgs['ghost'] === $msgs['real'], array( $msgs['ghost'], $msgs['real'] ) );

		// Sign-in switched off for customers must not answer differently for real accounts.
		at_reset( array( 'allow_password_login' => 0, 'wp_login_integration' => 1, 'max_verify_attempts' => 50 ) );
		browser_guest();
		$a = at_post( 'ashna_password_login', array( 'identifier' => $user->user_login, 'password' => 'Real-Pass-1!', 'context' => 'wp-login' ) );
		browser_guest();
		$b = at_post( 'ashna_password_login', array( 'identifier' => 'ghost2_' . wp_rand() . '@example.com', 'password' => 'Real-Pass-1!', 'context' => 'wp-login' ) );
		check( 'staff-only mode answers identically for real and unknown accounts', data_of( $a, 'message' ) === data_of( $b, 'message' ) && 0 === get_current_user_id(), array( data_of( $a, 'message' ), data_of( $b, 'message' ) ) );
		at_reset();
	}
);

/* =========================================================================
 * 7. Privilege escalation and IDOR.
 * ======================================================================= */
scenario(
	'Privilege escalation: lower roles cannot reach admin endpoints or each other',
	function () {
		at_reset();
		ashna_fixture_woocommerce();
		at_admin_boot();
		$admin_endpoints = array(
			'ashna_admin_save_settings'        => array( 'ashna' => array( 'form_title' => 'HACKED' ) ),
			'ashna_admin_test_gateway'         => array(),
			'ashna_admin_unlock_target'        => array( 'target' => 'anybody' ),
			'ashna_admin_import_settings'      => array( 'json_data' => '{"form_title":"HACKED"}' ),
			'ashna_admin_reset_settings'       => array(),
			'ashna_admin_reset_gateway_health' => array(),
			'ashna_admin_end_attack_mode'      => array(),
			'ashna_admin_trust_proxy'          => array( 'ip' => '127.0.0.1' ),
			'ashna_admin_fetch_arvan_ranges'   => array(),
		);
		$before = Ashna_Helper::get_settings();
		$roles  = array_values( array_filter( array( 'subscriber', 'contributor', 'author', 'editor', 'shop_manager', 'seller' ), 'get_role' ) );
		foreach ( $roles as $role ) {
			$user = make_user( 'at_' . $role, $role );
			wp_set_current_user( $user->ID );
			$opened = array();
			foreach ( $admin_endpoints as $action => $data ) {
				if ( ok_json( ajax( $action, array_merge( array( 'nonce' => wp_create_nonce( 'ashna_admin_nonce' ) ), $data ) ) ) ) {
					$opened[] = $action;
				}
				if ( ok_json( ajax( $action, array() ) ) ) {
					$opened[] = $action . ' (no nonce)';
				}
				if ( ok_json( ajax( $action, array_merge( array( 'nonce' => wp_create_nonce( 'ashna_otp_nonce' ) ), $data ) ) ) ) {
					$opened[] = $action . ' (public nonce)';
				}
			}
			check( $role . ': none of the ' . count( $admin_endpoints ) . ' admin endpoints answered success', empty( $opened ), $opened );
			$after = Ashna_Helper::get_settings();
			check( $role . ': settings untouched', $before['form_title'] === $after['form_title'] && $before['default_user_role'] === $after['default_user_role'] );
		}

		// The administrator path does work (so the checks above are not vacuous).
		$ok = at_admin_post( 'ashna_admin_reset_settings' );
		check( 'control: an administrator can reach the same endpoint', ok_json( $ok ), $ok['raw'] );

		// A member cannot read, register or delete another member's passkey.
		$owner    = make_user( 'at_pk_owner', 'subscriber', array( 'ashna_phone' => random_phone() ) );
		$attacker = make_user( 'at_pk_thief', 'subscriber' );
		$cred     = Ashna_Passkey::base64url_encode( random_bytes( 16 ) );
		Ashna_Passkey::save_user_passkey( $owner->ID, array( 'id' => $cred, 'public_key_pem' => 'x', 'alg' => -7 ) );
		wp_set_current_user( $attacker->ID );
		$res   = ajax( 'ashna_passkey_delete', array( 'nonce' => wp_create_nonce( 'ashna_otp_nonce' ), 'credential_id' => $cred ) );
		$still = Ashna_Passkey::get_user_passkeys( $owner->ID );
		check( 'deleting someone else’s passkey is refused', ! ok_json( $res ) && isset( $still[ $cred ] ), $res['raw'] );
		$res = ajax( 'ashna_passkey_register_options', array( 'nonce' => wp_create_nonce( 'ashna_otp_nonce' ) ) );
		check( 'the registration challenge never carries another member’s credential id', ! ok_json( $res ) || false === strpos( (string) $res['raw'], $cred ), substr( (string) $res['raw'], 0, 200 ) );
		wp_set_current_user( 0 );

		// A request cannot escalate through the settings payload.
		$res   = at_admin_post( 'ashna_admin_save_settings', array( 'ashna' => array_merge( Ashna_Helper::get_settings(), array( 'default_user_role' => 'administrator' ) ) ) );
		$saved = Ashna_Helper::get_settings();
		$role  = get_role( $saved['default_user_role'] );
		check( 'the sign-up role cannot be set to administrator', ok_json( $res ) && 'administrator' !== $saved['default_user_role'] && ! ( $role && $role->has_cap( 'manage_options' ) ), $saved['default_user_role'] );
		check( 'safe_registration_role refuses every privileged role', 'administrator' !== Ashna_Auth::safe_registration_role( 'administrator' ) && 'administrator' !== Ashna_Auth::safe_registration_role( 'editor' ) );

		// The role a visitor gets on sign-up is the safe one even if the option was tampered with.
		ashna_t_settings( array( 'default_user_role' => 'administrator' ) );
		$phone = random_phone();
		browser_guest();
		list( , $code ) = at_code( $phone );
		$res = at_verify( $phone, $code );
		$new = 0 !== get_current_user_id() ? get_userdata( get_current_user_id() ) : null;
		check( 'a brand-new visitor can never sign up as an administrator', ok_json( $res ) && $new && ! in_array( 'administrator', (array) $new->roles, true ), $new ? $new->roles : $res['raw'] );
		track_user_id( $new ? $new->ID : 0 );
		browser_guest();
		at_reset();
	}
);

/* =========================================================================
 * 8. WebAuthn forgery.
 * ======================================================================= */
scenario(
	'WebAuthn forgery attempts',
	function () {
		at_reset();
		$key = openssl_pkey_get_private(
			"-----BEGIN PRIVATE KEY-----\n" .
			"MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgQqWpZniZnCOEv40W\n" .
			"XenLutAq/LBRC9kSNPr/4CmnLSuhRANCAARj7wTXTjYxb+bhhY+Eux5xghfs4gHW\n" .
			"lfIYF3ZGCOegzgQX4bBWX58ybzfWvXr039bgFhtxhxfckanYo0v0UETE\n" .
			"-----END PRIVATE KEY-----\n"
		);
		if ( ! $key ) {
			check( 'test key available', false, 'openssl unavailable' );
			return;
		}
		$pem  = openssl_pkey_get_details( $key )['key'];
		$user = make_user( 'at_pk_login', 'subscriber', array( 'ashna_phone' => random_phone() ) );
		$cred = Ashna_Passkey::base64url_encode( random_bytes( 16 ) );
		Ashna_Passkey::save_user_passkey( $user->ID, array( 'id' => $cred, 'public_key_pem' => $pem, 'alg' => -7 ) );

		/** One full assertion, with optional tampering. */
		$make = function ( array $overrides = array() ) use ( $key, $cred ) {
			$options    = at_post( 'ashna_passkey_login_options' );
			$challenge  = isset( $overrides['challenge'] ) ? $overrides['challenge'] : data_of( $options, 'challenge' );
			$client     = array(
				'type'      => isset( $overrides['type'] ) ? $overrides['type'] : 'webauthn.get',
				'challenge' => $challenge,
				'origin'    => isset( $overrides['origin'] ) ? $overrides['origin'] : rtrim( home_url(), '/' ),
			);
			$client_raw = wp_json_encode( $client );
			$ad         = ( isset( $overrides['rp_id_hash'] ) ? $overrides['rp_id_hash'] : hash( 'sha256', Ashna_Passkey::get_rp_id(), true ) )
				. chr( isset( $overrides['flags'] ) ? $overrides['flags'] : 0x05 ) . pack( 'N', 1 );
			openssl_sign( $ad . hash( 'sha256', $client_raw, true ), $signature, $key, OPENSSL_ALGO_SHA256 );
			if ( ! empty( $overrides['tamper_signature'] ) ) {
				$signature[0] = chr( ord( $signature[0] ) ^ 0xFF );
			}
			return at_post(
				'ashna_passkey_login_verify',
				array(
					'credential_id'      => isset( $overrides['credential_id'] ) ? $overrides['credential_id'] : $cred,
					'client_data_json'   => Ashna_Passkey::base64url_encode( $client_raw ),
					'authenticator_data' => Ashna_Passkey::base64url_encode( $ad ),
					'signature'          => Ashna_Passkey::base64url_encode( $signature ),
				)
			);
		};

		browser_guest();
		$res = $make();
		check( 'control: a real assertion signs in', ok_json( $res ) && get_current_user_id() === $user->ID, $res['raw'] );

		// Replay the exact same bytes: the challenge must already be burnt.
		browser_guest();
		$options   = at_post( 'ashna_passkey_login_options' );
		$client    = wp_json_encode( array( 'type' => 'webauthn.get', 'challenge' => data_of( $options, 'challenge' ), 'origin' => rtrim( home_url(), '/' ) ) );
		$ad        = hash( 'sha256', Ashna_Passkey::get_rp_id(), true ) . chr( 0x05 ) . pack( 'N', 1 );
		openssl_sign( $ad . hash( 'sha256', $client, true ), $signature, $key, OPENSSL_ALGO_SHA256 );
		$assertion = array(
			'credential_id'      => $cred,
			'client_data_json'   => Ashna_Passkey::base64url_encode( $client ),
			'authenticator_data' => Ashna_Passkey::base64url_encode( $ad ),
			'signature'          => Ashna_Passkey::base64url_encode( $signature ),
		);
		$first     = at_post( 'ashna_passkey_login_verify', $assertion );
		check( 'control: the freshly minted assertion signs in', ok_json( $first ), $first['raw'] );
		browser_guest();
		$second = at_post( 'ashna_passkey_login_verify', $assertion );
		check( 'replaying an already used assertion is refused', ! ok_json( $second ) && 0 === get_current_user_id(), $second['raw'] );

		$cases = array(
			'a flipped bit in the signature'           => array( 'tamper_signature' => true ),
			'a foreign origin'                         => array( 'origin' => 'https://evil.example' ),
			'a wrong rpId hash'                        => array( 'rp_id_hash' => hash( 'sha256', 'evil.example', true ) ),
			'a cleared user-present flag'              => array( 'flags' => 0x04 ),
			'the webauthn.create ceremony'             => array( 'type' => 'webauthn.create' ),
			'another credential id with our signature' => array( 'credential_id' => Ashna_Passkey::base64url_encode( random_bytes( 16 ) ) ),
			'an unknown challenge'                     => array( 'challenge' => Ashna_Passkey::base64url_encode( random_bytes( 32 ) ) ),
		);
		foreach ( $cases as $label => $overrides ) {
			browser_guest();
			$res = $make( $overrides );
			check( 'refused: ' . $label, ! ok_json( $res ) && 0 === get_current_user_id(), $res['raw'] );
		}

		// A blocked account cannot sign in even with a valid assertion.
		add_filter(
			'ashna_otp_can_login',
			function ( $allowed, $u ) use ( $user ) {
				return $u->ID === $user->ID ? new WP_Error( 'blocked', 'blocked' ) : $allowed;
			},
			10,
			2
		);
		browser_guest();
		$res = $make();
		check( 'a blocked account cannot sign in with a valid passkey', ! ok_json( $res ) && 0 === get_current_user_id() );
		remove_all_filters( 'ashna_otp_can_login' );

		// The challenge endpoint hands nothing private to guests.
		browser_guest();
		$res = at_post( 'ashna_passkey_login_options' );
		check( 'the challenge response leaks no credential ids or keys to guests', ! ok_json( $res ) || ( false === strpos( (string) $res['raw'], (string) $cred ) && false === strpos( (string) $res['raw'], 'public_key_pem' ) ), substr( (string) $res['raw'], 0, 200 ) );
		at_reset();
	}
);

/* =========================================================================
 * 9. Session and cookie forgery.
 * ======================================================================= */
scenario(
	'Session forgery and cookie replay',
	function () {
		at_reset( array( 'resend_cooldown' => 0 ) );
		$user  = make_user( 'at_session', 'subscriber', array( 'ashna_phone' => random_phone() ) );
		$phone = get_user_meta( $user->ID, 'ashna_phone', true );

		// Failed sign-ins must never hand out a cookie.
		browser_guest();
		Ashna_T::$cookies = array();
		list( , $code ) = at_code( $phone );
		at_verify( $phone, ( '11111' === $code ? '22222' : '11111' ) );
		check( 'no auth cookie for a wrong code', empty( Ashna_T::$cookies ) && 0 === get_current_user_id(), Ashna_T::$cookies );

		// A hand-made logged-in cookie must not authenticate.
		$_COOKIE[ 'wordpress_logged_in_' . COOKIEHASH ] = 'administrator|' . ( time() + 3600 ) . '|' . str_repeat( 'a', 43 ) . '|' . hash_hmac( 'sha256', 'admin|forged', wp_salt( 'nonce' ) );
		$GLOBALS['current_user']                        = null;
		wp_get_current_user();
		check( 'a forged wordpress_logged_in cookie does not authenticate', 0 === get_current_user_id() );
		$res = at_post( 'ashna_refresh_session' );
		check( 'the refresh endpoint does not turn a forged cookie into a session', false === data_of( $res, 'logged_in', false ) && 0 === get_current_user_id(), $res['raw'] );
		unset( $_COOKIE[ 'wordpress_logged_in_' . COOKIEHASH ] );

		// A real session cookie must be worthless after logout.
		browser_guest();
		list( , $code2 ) = at_code( $phone );
		$res             = at_verify( $phone, $code2 );
		check( 'control: sign-in works', ok_json( $res ) && isset( Ashna_T::$cookies['auth'] ), $res['raw'] );
		track_user_id( get_current_user_id() );
		wp_logout();
		check( 'after logout the visitor is a guest', 0 === wp_get_current_user()->ID );
		browser_signed_in();
		check( 'the stolen session cookie is dead after logout', 0 === get_current_user_id() );
		browser_guest();

		// The refresh endpoint never signs anyone in.
		Ashna_T::$logins  = array();
		Ashna_T::$cookies = array();
		$res              = at_post( 'ashna_refresh_session' );
		check( 'refresh sends no login and sets no cookie', empty( Ashna_T::$logins ) && empty( Ashna_T::$cookies ) && at_clean( $res ), $res['raw'] );

		// The sign-in cookie is a real WordPress session.
		at_reset( array( 'resend_cooldown' => 0 ) );
		browser_guest();
		list( , $code3 ) = at_code( $phone );
		$res             = at_verify( $phone, $code3 );
		$cookie          = isset( Ashna_T::$cookies['auth'] ) ? Ashna_T::$cookies['auth'] : array();
		check( 'a session token is issued with the login', ok_json( $res ) && ! empty( $cookie['token'] ), $cookie );
		$session = WP_Session_Tokens::get_instance( $user->ID )->get( (string) $cookie['token'] );
		check( 'the token is a real WordPress session for that user', is_array( $session ) && $session['expiration'] > time(), $session );
		track_user_id( get_current_user_id() );

		// Destroying the session server-side (as WordPress does when a password changes) kills it.
		WP_Session_Tokens::get_instance( $user->ID )->destroy_all();
		browser_signed_in();
		check( 'after the session is destroyed server-side the cookie is worthless', 0 === get_current_user_id() );
		browser_guest();
		at_reset();
	}
);

/* =========================================================================
 * 10. Open redirect / header injection.
 * ======================================================================= */
scenario(
	'redirect_to cannot point off-site or inject headers',
	function () {
		at_reset( array( 'resend_cooldown' => 0 ) );
		$host    = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$attacks = array(
			'https://evil.example/',
			'//evil.example/',
			'http://evil.example\\@' . $host,
			'javascript:alert(document.cookie)',
			'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
			'///evil.example/',
			"/\\evil.example/",
			'http://' . $host . '.evil.example/',
			'http://' . $host . "/%0d%0aLocation:%20http://evil.example/",
			'vbscript:msgbox(1)',
			'hTTpS://evil.example/',
			'https:evil.example',
			'https://' . $host . '@evil.example/',
			'\\\\evil.example\\share',
			'file:///etc/passwd',
			"java\tscript:alert(1)",
			'jAvAsCrIpT:alert(1)',
		);
		$leaks = array();
		foreach ( $attacks as $attack ) {
			$phone = random_phone();
			list( , $code ) = at_code( $phone );
			browser_guest();
			$res = at_verify( $phone, $code, array( 'redirect_to' => $attack ) );
			if ( ! ok_json( $res ) ) {
				continue;
			}
			$to      = (string) data_of( $res, 'redirect_to', home_url( '/' ) );
			$to_host = strtolower( (string) wp_parse_url( $to, PHP_URL_HOST ) );
			if ( ( '' !== $to_host && $to_host !== $host ) || preg_match( '/[\r\n]/', $to ) || preg_match( '/^(javascript|data|vbscript|file):/i', $to ) ) {
				$leaks[] = $attack . ' → ' . $to;
			}
			track_user_id( get_current_user_id() );
		}
		check( count( $attacks ) . ' off-site redirect payloads all stay on this host', empty( $leaks ), $leaks );

		// A legitimate same-site destination still works: the guard is not "block everything".
		at_reset( array( 'resend_cooldown' => 0 ) );
		$phone = random_phone();
		list( , $code ) = at_code( $phone );
		browser_guest();
		$res = at_verify( $phone, $code, array( 'redirect_to' => home_url( '/cart/?x=1' ) ) );
		check( 'a real same-site destination is still honoured', ok_json( $res ) && false !== strpos( (string) data_of( $res, 'redirect_to' ), '/cart/' ), $res['raw'] );
		track_user_id( get_current_user_id() );

		// The password and passkey endpoints use the same resolver.
		$u = make_user( 'at_redirect', 'subscriber', array( 'ashna_phone' => random_phone() ), 'Real-Pass-1!' );
		browser_guest();
		$respw = at_post( 'ashna_password_login', array( 'identifier' => $u->user_login, 'password' => 'Real-Pass-1!', 'redirect_to' => 'https://evil.example/' ) );
		check( 'password sign-in keeps the visitor on this host too', ok_json( $respw ) && false === strpos( (string) data_of( $respw, 'redirect_to', '' ), 'evil.example' ), $respw['raw'] );
		browser_guest();

		// A pinned admin redirect (set in settings) must not be hijackable by a request either.
		at_reset( array( 'redirect_behavior' => 'custom', 'custom_redirect_url' => home_url( '/welcome/' ) ) );
		$phone = random_phone();
		list( , $code ) = at_code( $phone );
		browser_guest();
		$res = at_verify( $phone, $code, array( 'redirect_to' => '//evil.example/' ) );
		check( 'the configured redirect wins and stays on site', ok_json( $res ) && false === strpos( (string) data_of( $res, 'redirect_to', '' ), 'evil.example' ), $res['raw'] );
		track_user_id( get_current_user_id() );
		at_reset();
	}
);

/* =========================================================================
 * 11. Rate-limit escape.
 * ======================================================================= */
scenario(
	'Rate limits cannot be escaped by rotating identifiers, spoofing headers or forging tickets',
	function () {
		$ip = '198.51.100.50';
		at_reset( array( 'resend_cooldown' => 0, 'max_ip_requests_per_minute' => 3, 'max_subnet_requests_per_minute' => 0, 'attack_mode_threshold' => 0 ) );
		$allowed = 0;
		for ( $i = 0; $i < 12; $i++ ) {
			list( $res ) = at_code( random_phone(), array( 'REMOTE_ADDR' => $ip ) );
			if ( ok_json( $res ) ) {
				++$allowed;
			}
		}
		check( 'rotating the number from one IP stops at the IP limit (3)', 3 === $allowed, $allowed );

		// Proxy headers the attacker controls must not look like new IPs.
		at_reset( array( 'resend_cooldown' => 0, 'max_ip_requests_per_minute' => 2, 'attack_mode_threshold' => 0 ) );
		$allowed = 0;
		foreach ( array( 'HTTP_X_FORWARDED_FOR', 'HTTP_CF_CONNECTING_IP', 'HTTP_TRUE_CLIENT_IP', 'HTTP_X_REAL_IP', 'HTTP_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_X_ORIGINAL_FORWARDED_FOR' ) as $header ) {
			list( $res ) = at_code( random_phone(), array( 'REMOTE_ADDR' => $ip, $header => '198.51.100.' . wp_rand( 1, 250 ) ) );
			if ( ok_json( $res ) ) {
				++$allowed;
			}
		}
		browser_guest();
		list( $last ) = at_code( random_phone(), array( 'REMOTE_ADDR' => $ip ) );
		check( '7 spoofed header names do not buy more than the 2 allowed from that IP', $allowed <= 2 && ! ok_json( $last ), $allowed );

		// Forged resend tickets must not skip the captcha.
		at_reset( array( 'captcha_type' => 'math', 'resend_cooldown' => 0, 'max_ip_requests_per_minute' => 100, 'attack_mode_threshold' => 0 ) );
		$phone = random_phone();
		browser_guest();
		$req    = at_captcha_post( $phone, array( 'REMOTE_ADDR' => $ip ) );
		$ticket = (string) data_of( $req, 'resend_token' );
		check( 'control: a solved captcha issues a resend ticket', ok_json( $req ) && '' !== $ticket, $req['raw'] );

		$forgeries = array(
			'random string'    => wp_generate_password( 40, false ),
			'tampered tail'    => substr( $ticket, 0, -1 ) . ( '0' === substr( $ticket, -1 ) ? '1' : '0' ),
			'truncated'        => substr( $ticket, 0, 20 ),
			'empty signature'  => preg_replace( '/\.[^.]+$/', '.', $ticket ),
			'guessed shape'    => time() . '.' . str_repeat( 'a', 64 ),
			'stale timestamp'  => ( time() - 86400 ) . '.' . str_repeat( 'a', 64 ),
			'future timestamp' => ( time() + 86400 ) . '.' . str_repeat( 'a', 64 ),
		);
		foreach ( $forgeries as $label => $forged ) {
			browser_guest();
			$res = at_post( 'ashna_request_otp', array( 'identifier' => random_phone(), 'resend_token' => $forged ), array( 'REMOTE_ADDR' => $ip ) );
			check( 'forged resend ticket refused (' . $label . ')', ! ok_json( $res ), $res['raw'] );
		}
		browser_guest();
		$res = at_post( 'ashna_request_otp', array( 'identifier' => random_phone(), 'resend_token' => $ticket ), array( 'REMOTE_ADDR' => $ip ) );
		check( 'the genuine ticket does not work for another number', ! ok_json( $res ), $res['raw'] );
		browser_guest();
		$res = at_post( 'ashna_request_otp', array( 'identifier' => $phone, 'resend_token' => $ticket ), array( 'REMOTE_ADDR' => '203.0.113.99' ) );
		check( 'the genuine ticket does not work from another IP', ! ok_json( $res ), $res['raw'] );

		// Captcha tokens are single use and signed.
		browser_guest();
		$q2     = Ashna_Security::generate_math_captcha();
		$solved = array( 'captcha_answer' => at_solve( $q2 ), 'captcha_token' => $q2['token'] );
		$res    = at_post( 'ashna_request_otp', array_merge( array( 'identifier' => random_phone() ), $solved ), array( 'REMOTE_ADDR' => $ip ) );
		check( 'a solved captcha works once', ok_json( $res ), $res['raw'] );
		browser_guest();
		$res = at_post( 'ashna_request_otp', array_merge( array( 'identifier' => random_phone() ), $solved ), array( 'REMOTE_ADDR' => $ip ) );
		check( 'the same captcha token cannot be replayed', ! ok_json( $res ), $res['raw'] );
		browser_guest();
		$q3   = Ashna_Security::generate_math_captcha();
		$res  = at_post( 'ashna_request_otp', array( 'identifier' => random_phone(), 'captcha_answer' => (string) ( (int) at_solve( $q3 ) + 1 ), 'captcha_token' => $q3['token'] ), array( 'REMOTE_ADDR' => $ip ) );
		check( 'a wrong answer with a real token is refused', ! ok_json( $res ) && 'ashna_captcha_wrong' === data_of( $res, 'code' ), $res['raw'] );
		browser_guest();
		$res = at_post( 'ashna_request_otp', array( 'identifier' => random_phone(), 'captcha_answer' => '999999', 'captcha_token' => 'x.y' ), array( 'REMOTE_ADDR' => $ip ) );
		check( 'a hand-written token is refused', ! ok_json( $res ), $res['raw'] );

		// Brute force: 60 wrong answers, none of them may be accepted as a solve.
		browser_guest();
		$accepted = 0;
		for ( $i = 0; $i < 60; $i++ ) {
			$q   = Ashna_Security::generate_math_captcha();
			$res = at_post( 'ashna_request_otp', array( 'identifier' => random_phone(), 'captcha_answer' => (string) wp_rand( 1, 200 ), 'captcha_token' => $q['token'] ), array( 'REMOTE_ADDR' => $ip ) );
			if ( ok_json( $res ) ) {
				++$accepted;
			}
		}
		check( 'guessing the sum is the only way through (≈1 in 40 per try)', $accepted <= 2, $accepted );
		at_reset();
	}
);

/* =========================================================================
 * 12. Flood.
 * ======================================================================= */
scenario(
	'Under a flood: the site answers everything and real visitors can still sign in',
	function () {
		at_reset( array( 'resend_cooldown' => 0, 'captcha_type' => 'none', 'attack_mode_threshold' => 10, 'attack_mode_minutes' => 30, 'max_ip_requests_per_minute' => 5, 'max_subnet_requests_per_minute' => 0 ) );
		add_filter( 'ashna_otp_notify_admin', '__return_false' );
		$clean = 0;
		for ( $i = 0; $i < 40; $i++ ) {
			$res = at_post( 'ashna_request_otp', array( 'identifier' => random_phone() ), array( 'REMOTE_ADDR' => '198.51.100.' . ( 100 + $i ) ) );
			if ( at_clean( $res ) ) {
				++$clean;
			}
		}
		check( '40 hostile requests from 40 IPs all answered cleanly', 40 === $clean, $clean );
		Ashna_Rate_Limiter::maybe_enter_attack_mode();
		check( 'the burst switched attack mode on', Ashna_Rate_Limiter::attack_mode_active() );

		// A real visitor on a different network can still sign in by solving the captcha.
		$phone   = random_phone();
		$blocked = at_post( 'ashna_request_otp', array( 'identifier' => $phone ), array( 'REMOTE_ADDR' => '192.0.2.200' ) );
		check( 'under attack a plain request is asked for the captcha', ! ok_json( $blocked ) && in_array( data_of( $blocked, 'code' ), array( 'ashna_captcha_empty', 'ashna_captcha_invalid' ), true ), $blocked['raw'] );
		$res = at_captcha_post( $phone, array( 'REMOTE_ADDR' => '192.0.2.200' ) );
		check( 'a real visitor can still get a code by solving the captcha', ok_json( $res ), $res['raw'] );
		$key  = Ashna_Helper::parse_identifier( $phone )['normalized'];
		$code = isset( Ashna_T::$codes[ $key ] ) ? Ashna_T::$codes[ $key ] : null;
		$res  = at_verify( $phone, $code );
		check( 'and can still sign in', ok_json( $res ), $res['raw'] );
		track_user_id( get_current_user_id() );
		browser_guest();

		// The administrator can see it and switch it off.
		check( 'attack mode is active', Ashna_Rate_Limiter::attack_mode_active() );
		$res = at_admin_post( 'ashna_admin_end_attack_mode' );
		check( 'the administrator can end attack mode', ok_json( $res ) && ! Ashna_Rate_Limiter::attack_mode_active(), $res['raw'] );
		remove_filter( 'ashna_otp_notify_admin', '__return_false' );
		at_reset();
	}
);

/* =========================================================================
 * 13. Stored XSS on every render path.
 * ======================================================================= */
scenario(
	'Stored XSS payloads come back escaped everywhere Ashna renders them',
	function () {
		at_reset( array( 'resend_cooldown' => 0 ) );
		$leaks = array();
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';

		// The name a visitor types is sanitized on the way in…
		$phone = random_phone();
		list( , $code ) = at_code( $phone );
		browser_guest();
		$res = at_verify( $phone, $code, array( 'full_name' => '<script>alert(1)</script>' ) );
		check( 'control: the hostile name signs in', ok_json( $res ), $res['raw'] );
		$uid = get_current_user_id();
		track_user_id( $uid );
		at_render( 'logged-in box after a hostile name', function () {
			echo do_shortcode( '[ashna_otp_form]' ); // phpcs:ignore WordPress.Security.EscapeOutput
		}, $leaks );
		$stored = (string) get_userdata( $uid )->display_name;
		check( 'a hostile name is stripped before it is stored', false === strpos( $stored, '<script' ), $stored );
		browser_guest();

		// …and every one of them is escaped on the way out, even when it arrives through another
		// door (an import, another plugin, a restored backup).
		$payloads = array(
			'<script>alert(1)</script>',
			'<img src=x onerror=alert(1)>',
			'" onmouseover="alert(1)',
			'</textarea><svg onload=alert(1)>',
			"javascript:alert('xss')",
			'<iframe src=javascript:alert(1)>',
			'"><img src=x onerror=alert(1)>',
			'<svg/onload=alert(1)>',
			"'\"><script>alert(1)</script>",
		);
		$victim = make_user( 'at_xss', 'subscriber', array( 'ashna_phone' => random_phone() ) );
		track_user_id( $victim->ID );

		foreach ( $payloads as $payload ) {
			$wpdb->update( $wpdb->users, array( 'display_name' => $payload ), array( 'ID' => $victim->ID ) ); // A payload from another data source.
			wp_cache_flush();
			wp_set_current_user( $victim->ID );

			// Frontend: the logged-in box greets the member by display name.
			at_render( 'frontend box (' . $payload . ')', function () {
				echo do_shortcode( '[ashna_otp_form]' ); // phpcs:ignore WordPress.Security.EscapeOutput
			}, $leaks );
			// Frontend: the passkey manager prints the member's name.
			at_render( 'passkey manager (' . $payload . ')', function () {
				echo do_shortcode( '[ashna_passkey_manager]' ); // phpcs:ignore WordPress.Security.EscapeOutput
			}, $leaks );
			// Frontend: the global modal.
			at_render( 'global modal (' . $payload . ')', function () {
				Ashna_Frontend::instance()->render_global_modal();
			}, $leaks );

			// Admin: profile field, logs page, settings page.
			wp_set_current_user( as_admin_attack()->ID );
			update_user_meta( $victim->ID, 'ashna_phone', $payload );
			at_render( 'admin profile field (' . $payload . ')', function () use ( $victim ) {
				do_action( 'show_user_profile', get_userdata( $victim->ID ) );
			}, $leaks );
			Ashna_Logger::insert( array( 'recipient' => $payload, 'status' => 'failed', 'response_message' => $payload, 'gateway' => $payload ) );
			at_render( 'admin log page (' . $payload . ')', function () {
				$GLOBALS['current_screen'] = WP_Screen::get( 'toplevel_page_ashna-otp' );
				Ashna_Admin::instance()->render_logs_page();
			}, $leaks );
			at_render( 'admin settings page (' . $payload . ')', function () {
				$GLOBALS['current_screen'] = WP_Screen::get( 'toplevel_page_ashna-otp' );
				Ashna_Admin::instance()->render_settings_page();
			}, $leaks );
			$wpdb->query( 'DELETE FROM ' . Ashna_Logger::table_name() ); // phpcs:ignore WordPress.DB
		}
		check( count( $payloads ) . ' payloads × 6 render paths: nothing survives as live markup', empty( $leaks ), array_slice( $leaks, 0, 6 ) );

		// Settings text straight from the option: what Ashna prints must be escaped.
		$leaks = array();
		$s     = Ashna_Helper::get_settings();
		$s['form_title']    = '<script>alert(1)</script>';
		$s['form_subtitle'] = '<img src=x onerror=alert(1)>';
		update_option( 'ashna_otp_settings', $s );
		Ashna_Helper::flush_cache();
		browser_guest();
		at_render( 'form title from the option', function () {
			echo do_shortcode( '[ashna_otp_form]' ); // phpcs:ignore WordPress.Security.EscapeOutput
		}, $leaks );
		wp_set_current_user( as_admin_attack()->ID );
		at_render( 'panel with hostile settings', function () {
			$GLOBALS['current_screen'] = WP_Screen::get( 'toplevel_page_ashna-otp' );
			Ashna_Admin::instance()->render_settings_page();
		}, $leaks );
		check( 'hostile settings are escaped in the form and in the panel', empty( $leaks ), array_slice( $leaks, 0, 6 ) );

		// The escaped name is still readable text (the fix is escaping, not deletion).
		wp_set_current_user( $victim->ID );
		$html = do_shortcode( '[ashna_otp_form]' );
		check( 'the member’s name is still shown (escaped, not dropped)', false !== strpos( $html, '&lt;script&gt;' ) || false !== strpos( $html, 'alert(1)' ), substr( $html, 0, 200 ) );
		browser_guest();
		at_reset();
	}
);

/* =========================================================================
 * 14. Import / backup abuse.
 * ======================================================================= */
scenario(
	'Backup import cannot smuggle a role, a script or an off-site value',
	function () {
		at_reset();
		at_admin_boot();
		$evil = array(
			'form_title'              => '<script>alert(1)</script>',
			'default_user_role'       => 'administrator',
			'redirect_url'            => 'javascript:alert(1)',
			'custom_redirect_url'     => 'data:text/html,<script>alert(1)</script>',
			'role_redirects'          => array( 'administrator' => 'https://evil.example/', 'subscriber' => 'javascript:alert(1)' ),
			'custom_css'              => '}</style><script>alert(1)</script>',
			'blocked_ips'             => "1.2.3.4\n<script>alert(1)</script>",
			'logo_url'                => 'javascript:alert(1)',
			'daily_send_cap'          => -5,
			'otp_length'              => 999,
			'session_days'            => 99999,
			'max_verify_attempts'     => 0,
			'whitelisted_identifiers' => "1' OR '1'='1",
		);
		$res = at_admin_post( 'ashna_admin_import_settings', array( 'json_data' => wp_json_encode( $evil ) ) );
		check( 'the import is accepted and answered cleanly', ok_json( $res ), $res['raw'] );
		$saved = Ashna_Helper::get_settings();
		$role  = get_role( $saved['default_user_role'] );
		check( 'sign-up role not escalated by the file', 'administrator' !== $saved['default_user_role'] && ! ( $role && $role->has_cap( 'manage_options' ) ), $saved['default_user_role'] );
		check( 'otp_length clamped', $saved['otp_length'] >= 4 && $saved['otp_length'] <= 8, $saved['otp_length'] );
		check( 'session length clamped', (int) $saved['session_days'] >= 1 && (int) $saved['session_days'] <= 365, $saved['session_days'] );
		check( 'the attempt limit cannot be zeroed out', (int) $saved['max_verify_attempts'] >= 1, $saved['max_verify_attempts'] );
		check( 'a negative daily cap refused', (int) $saved['daily_send_cap'] >= 0, $saved['daily_send_cap'] );
		check( 'javascript:/data: URLs dropped', false === stripos( (string) $saved['redirect_url'], 'javascript:' ) && false === stripos( (string) $saved['custom_redirect_url'], 'data:' ), array( $saved['redirect_url'], $saved['custom_redirect_url'] ) );
		check( 'off-site role redirect dropped', ! isset( $saved['role_redirects']['administrator'] ) || false === strpos( (string) $saved['role_redirects']['administrator'], 'evil.example' ), $saved['role_redirects'] );
		check( 'off-site login destinations dropped', false === strpos( (string) $saved['redirect_url'], 'evil.example' ) && false === strpos( (string) $saved['custom_redirect_url'], 'evil.example' ), array( $saved['redirect_url'], $saved['custom_redirect_url'] ) );
		check( 'a javascript: role redirect dropped', ! isset( $saved['role_redirects']['subscriber'] ) || false === stripos( (string) $saved['role_redirects']['subscriber'], 'javascript:' ), $saved['role_redirects'] );

		// What was imported must not execute anywhere.
		$leaks = array();
		browser_guest();
		at_render( 'form with imported settings', function () {
			echo do_shortcode( '[ashna_otp_form]' ); // phpcs:ignore WordPress.Security.EscapeOutput
		}, $leaks );
		wp_set_current_user( as_admin_attack()->ID );
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		at_render( 'panel with imported settings', function () {
			$GLOBALS['current_screen'] = WP_Screen::get( 'toplevel_page_ashna-otp' );
			Ashna_Admin::instance()->render_settings_page();
		}, $leaks );
		check( 'imported values render escaped', empty( $leaks ), array_slice( $leaks, 0, 5 ) );

		// The sign-in page of the site with these settings: nothing may execute there either.
		browser_guest();
		at_render( 'global modal with imported settings', function () {
			Ashna_Frontend::instance()->render_global_modal();
		}, $leaks );
		check( 'no payload from the backup reaches a page unescaped', empty( $leaks ), array_slice( $leaks, 0, 5 ) );

		// Junk files.
		foreach ( array( '{', '[]', 'null', 'true', '"x"', str_repeat( 'A', 200000 ), '{"form_title":' . str_repeat( '"x",', 5000 ) . '"y"}', '{"a":{"b":{"c":{"d":' . str_repeat( '[', 200 ) . str_repeat( ']', 200 ) . '}}}}' ) as $i => $junk ) {
			try {
				$res = at_admin_post( 'ashna_admin_import_settings', array( 'json_data' => $junk ) );
				$ok  = at_clean( $res );
			} catch ( Throwable $e ) {
				$ok  = false;
				$res = array( 'raw' => get_class( $e ) . ': ' . $e->getMessage() );
			}
			check( 'junk backup #' . $i . ' handled cleanly', $ok, substr( (string) $res['raw'], 0, 120 ) );
		}
		browser_guest();
		check( 'the site still answers after the junk', ok_json( at_post( 'ashna_refresh_session' ) ) );
		at_reset();
	}
);

/* =========================================================================
 * 15. Static self-audit of the shipped source.
 * ======================================================================= */
scenario(
	'Static audit: dangerous sinks, guards and secret handling in the shipped files',
	function () {
		$files = array();
		foreach ( array( 'includes', 'templates' ) as $dir ) {
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( ASHNA_OTP_PATH . $dir ) );
			foreach ( $it as $f ) {
				if ( $f->isFile() && 'php' === strtolower( $f->getExtension() ) ) {
					$files[] = $f->getPathname();
				}
			}
		}
		foreach ( array( 'index.php', 'ashna.php', 'uninstall.php' ) as $top ) {
			$files[] = ASHNA_OTP_PATH . $top;
		}

		$sinks   = array( 'eval(', 'create_function(', 'unserialize(', 'shell_exec', 'passthru(', 'proc_open(', 'pcntl_exec', 'call_user_func( $_', 'include $_', 'require $_', 'include_once $_', 'require_once $_', 'extract( $_', 'assert( $_' );
		$hits    = array();
		$noguard = array();
		$remote  = array();
		foreach ( $files as $file ) {
			$code = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			foreach ( $sinks as $sink ) {
				if ( false !== strpos( $code, $sink ) ) {
					$hits[] = basename( $file ) . ' contains ' . $sink;
				}
			}
			// index.php files that only hold a comment are silence stubs: nothing to guard.
			$body = preg_replace( '#^<\?php|//.*|/\*.*?\*/|\s#s', '', $code );
			if ( '' !== trim( (string) $body ) && ! preg_match( '/ABSPATH|WP_UNINSTALL_PLUGIN/', $code ) ) {
				$noguard[] = str_replace( ASHNA_OTP_PATH, '', $file );
			}
			if ( preg_match_all( '/wp_remote_(?:get|post|request|head)\s*\(\s*([^,]+)/', $code, $m ) ) {
				foreach ( $m[1] as $target ) {
					$remote[] = basename( $file ) . ': ' . trim( $target );
				}
			}
		}
		check( count( $files ) . ' shipped PHP files contain none of the ' . count( $sinks ) . ' dangerous sinks', empty( $hits ), array_slice( $hits, 0, 5 ) );
		check( 'every shipped PHP file refuses direct web access (or is a silence stub)', empty( $noguard ), $noguard );

		// Outbound requests: hard-coded endpoints, no URL taken from a request.
		$bad_remote = array();
		foreach ( $remote as $target ) {
			if ( preg_match( '/\$_(?:POST|GET|REQUEST)/', $target ) ) {
				$bad_remote[] = $target;
			}
		}
		check( 'no outbound request is built from request data (' . count( $remote ) . ' wp_remote_* calls audited)', empty( $bad_remote ), array_slice( $bad_remote, 0, 5 ) );

		// Codes are hashed, never stored in the clear; the sandbox gateway is the only exception.
		$packed_core = ASHNA_OTP_PATH . 'includes/class-ashna-protected-core.php';
		$source_for = static function ( $relative ) use ( $packed_core ) {
			if ( ! is_file( $packed_core ) ) {
				return (string) file_get_contents( ASHNA_OTP_PATH . $relative ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
			$core = (string) file_get_contents( $packed_core ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$marker = '// ---- Source: ' . $relative . ' ----';
			$start = strpos( $core, $marker );
			if ( false === $start ) {
				return '';
			}
			$next = strpos( $core, '// ---- Source: ', $start + strlen( $marker ) );
			return false === $next ? substr( $core, $start ) : substr( $core, $start, $next - $start );
		};
		$gateway = $source_for( 'includes/gateways/class-ashna-gateway-manager.php' );
		check( 'codes are stored as a WordPress password hash', false !== strpos( $gateway, 'wp_hash_password' ) );
		$logger = $source_for( 'includes/core/class-ashna-logger.php' );
		check( 'the logger keeps a readable code only for the sandbox gateway', false !== strpos( $logger, 'sandbox' ) );

		// Uninstalling only deletes when the owner asked for it.
		$uninstall = (string) file_get_contents( ASHNA_OTP_PATH . 'uninstall.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		check( 'uninstall data removal is opt-in', false !== strpos( $uninstall, 'delete_data_on_uninstall' ) );
		check( 'uninstall never drops users, posts or other plugins’ tables', false === strpos( $uninstall, '$wpdb->users' ) && false === strpos( $uninstall, '$wpdb->posts' ) );

		// Every AJAX handler opens with a nonce or a capability check — refresh-session excepted.
		$handlers = 0;
		$ungated  = array();
		$handler_files = glob( ASHNA_OTP_PATH . 'includes/*/class-ashna-*.php' );
		if ( is_file( $packed_core ) ) {
			$handler_files[] = $packed_core;
		}
		foreach ( $handler_files as $file ) {
			$src = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( preg_match_all( '/function\s+((?:ajax)_[a-z_]+)\s*\(\s*\)\s*\{(.{0,400})/s', $src, $m, PREG_SET_ORDER ) ) {
				foreach ( $m as $match ) {
					++$handlers;
					$body = $match[2];
					if ( false === strpos( $body, 'verify_request' ) && false === strpos( $body, 'current_user_can' ) && false === strpos( $body, 'check_ajax_referer' ) ) {
						$ungated[] = basename( $file ) . '::' . $match[1];
					}
				}
			}
		}
		$refresh_file = is_file( $packed_core ) ? 'class-ashna-protected-core.php' : 'class-ashna-session.php';
		check( $handlers . ' AJAX handlers all open with a nonce or capability check', array( $refresh_file . '::ajax_refresh' ) === $ungated, $ungated );
		check( 'the audit found the expected number of handlers', $handlers >= 18, $handlers );
	}
);

/* =========================================================================
 * 16. SQL safety with quoting, wildcard and encoding traps.
 * ======================================================================= */
scenario(
	'Database queries stay safe with quoting, wildcard and encoding traps',
	function () {
		at_reset();
		global $wpdb;
		$table  = Ashna_Logger::table_name();
		$before = at_intact();

		// Captcha markers: the LIKE pattern must not eat look-alike options.
		$marker = 'ashna_otp_cap_' . str_repeat( 'a', 40 );
		add_option( $marker, (string) ( time() - 10 ), '', false );
		add_option( 'ashnaXotpXcapXlookalike', 'keep-me', '', false );
		add_option( 'ashna_otp_capture_notice', 'keep-me-too', '', false );
		Ashna_Security::purge_used_captcha_tokens();
		check( 'expired captcha markers are purged', null === $wpdb->get_var( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name = %s", $marker ) ) ); // phpcs:ignore WordPress.DB
		check( 'look-alike option names survive (the pattern is escaped)', 'keep-me' === get_option( 'ashnaXotpXcapXlookalike' ) && 'keep-me-too' === get_option( 'ashna_otp_capture_notice' ) );
		delete_option( 'ashnaXotpXcapXlookalike' );
		delete_option( 'ashna_otp_capture_notice' );

		// A recipient full of SQL metacharacters round-trips, and the injected SQL does not run.
		$nasty = "'; DROP TABLE {$table}; --";
		$id    = Ashna_Logger::insert( array( 'recipient' => $nasty, 'status' => 'sent' ) );
		$row   = Ashna_Logger::get_latest_record( $nasty );
		check( 'a quoted recipient is stored and found exactly', $id && $row && $nasty === $row->recipient, $row ? $row->recipient : null );
		check( 'the logs table survived the injection attempt', $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) );

		// Backslash + quote + percent together (the killer of naive escaping) must round-trip too.
		$mixed = "C:\\\\path\\'s 100% 'quoted'";
		Ashna_Logger::insert( array( 'recipient' => $mixed, 'status' => 'sent' ) );
		$row = Ashna_Logger::get_latest_record( $mixed );
		check( 'a backslash + quote + percent recipient round-trips', $row && $mixed === $row->recipient, $row ? $row->recipient : null );

		// Search terms: SQL injection matches nothing, and % / _ stay literal.
		Ashna_Logger::insert( array( 'recipient' => 'normal-phone', 'status' => 'sent' ) );
		$total_all = (int) Ashna_Logger::get_logs( array( 'per_page' => 50 ) )['total'];
		$injection = (int) Ashna_Logger::get_logs( array( 'search' => "' OR 1=1 --", 'per_page' => 50 ) )['total'];
		check( 'a SQL-injection search term matches nothing instead of everything', 0 === $injection && $total_all > 0, array( $injection, $total_all ) );
		$wild  = (int) Ashna_Logger::get_logs( array( 'search' => '%', 'per_page' => 50 ) )['total'];
		$under = (int) Ashna_Logger::get_logs( array( 'search' => '_', 'per_page' => 50 ) )['total'];
		if ( at_like_escaped() ) {
			check( 'a bare % search term does not match every row', $wild < $total_all, array( $wild, $total_all ) );
			check( 'a bare _ search term does not match every row', $under < $total_all, array( $under, $total_all ) );
		} else {
			echo "  note  this database driver ignores backslash escapes in LIKE (the old wp-sqlite-db drop-in used for WordPress 5.8): the two wildcard checks are skipped here and pass on MySQL and the official driver.\n";
		}
		$quoted = (int) Ashna_Logger::get_logs( array( 'search' => $nasty, 'per_page' => 50 ) )['total'];
		check( 'searching for a quoted value finds that row', 1 === $quoted, $quoted );

		// Filters from the URL are sanitized, not passed through: the page renders.
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		$leaks                     = array();
		as_admin_attack();
		$GLOBALS['current_screen'] = WP_Screen::get( 'toplevel_page_ashna-otp' );
		$_GET['status']            = "sent' OR 1=1 --";
		$_GET['s']                 = '%';
		$_GET['channel']           = "x' UNION SELECT 1 --";
		$_GET['paged']             = array( 'x' );
		at_render( 'logs page with hostile filters', function () {
			Ashna_Admin::instance()->render_logs_page();
		}, $leaks );
		check( 'a hostile log filter renders without an error', empty( $leaks ), array_slice( $leaks, 0, 3 ) );
		$_GET = array();

		$after = at_intact();
		check( 'users and administrators unchanged by the injection attempts', $before['users'] === $after['users'] && $before['admins'] === $after['admins'], array( $before, $after ) );
		at_reset();
	}
);

scenario(
	'Nonce refusals stay refused when wp_die() does not end the request',
	function () {
		at_reset();
		$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );

		// A plugin or a broken host can replace the wp_die handler so that it returns instead of
		// ending the request. Every refusal must then fail closed instead of running the handler.
		$swallow = static function () {};
		$swap    = static function () use ( $swallow ) {
			return $swallow;
		};
		add_filter( 'wp_die_ajax_handler', $swap, 99 );

		$phone = random_phone();
		$key   = Ashna_Helper::parse_identifier( $phone )['normalized'];
		unset( Ashna_T::$codes[ $key ] );
		$res = at_post( 'ashna_request_otp', array( 'identifier' => $phone, 'nonce' => 'deadbeef' ) );
		check( 'a public endpoint with a bad nonce still answers the JSON refusal', at_refused( $res ), $res['raw'] );
		check( 'and the refusal created no code', ! isset( Ashna_T::$codes[ $key ] ) );

		// A code that belongs to a real visitor must not be verifiable without a nonce.
		$victim = random_phone();
		list( $asked, $code ) = at_code( $victim );
		check( 'the control flow still issues a code for a valid nonce', ok_json( $asked ) && $code, $asked['raw'] );
		Ashna_T::$cookies = array();
		$verify = at_post( 'ashna_verify_otp', array( 'identifier' => $victim, 'code' => $code, 'nonce' => 'deadbeef' ) );
		check( 'verify_otp with a bad nonce is refused', at_refused( $verify ), $verify['raw'] );
		check( 'and it signed nobody in', empty( Ashna_T::$cookies ) );

		// The whole suite of administrator endpoints: a bad nonce must leave no trace at all.
		as_admin_attack();
		at_admin_boot();
		$before_title    = Ashna_Helper::get_option( 'form_title' );
		$before_settings = get_option( 'ashna_otp_settings' );
		$before_locks    = Ashna_Security::get_active_lockouts();
		$admin_calls     = 0;
		foreach ( array( 'ashna_admin_save_settings', 'ashna_admin_reset_settings', 'ashna_admin_import_settings', 'ashna_admin_unlock_target', 'ashna_admin_test_gateway', 'ashna_admin_trust_proxy', 'ashna_admin_reset_gateway_health', 'ashna_admin_end_attack_mode', 'ashna_admin_fetch_arvan_ranges' ) as $action ) {
			++$admin_calls;
			at_admin_post(
				$action,
				array(
					'nonce'     => 'deadbeef',
					'target'    => 'someone@example.com',
					'ip'        => '203.0.113.9',
					'gateway'   => 'sandbox',
					'ashna'     => array( 'form_title' => 'PWNED-' . wp_rand() ),
					'json_data' => '{"form_title":"PWNED"}',
				)
			);
		}
		check( $admin_calls . ' admin endpoints with a bad nonce changed no setting', $before_title === Ashna_Helper::get_option( 'form_title' ) && $before_settings === get_option( 'ashna_otp_settings' ), array( $before_title, Ashna_Helper::get_option( 'form_title' ) ) );
		check( 'and none of them cleared a lockout', $before_locks === Ashna_Security::get_active_lockouts() );
		check( 'and none of them left attack mode', ! Ashna_Rate_Limiter::attack_mode_active() );

		// Put the harness handler back and confirm refusals behave as before.
		remove_filter( 'wp_die_ajax_handler', $swap, 99 );
		$after = at_post( 'ashna_request_otp', array( 'identifier' => random_phone(), 'nonce' => 'deadbeef' ) );
		check( 'with the normal wp_die handler back, refusals are JSON again', at_refused( $after ), $after['raw'] );
		unset( $admins );
		at_reset();
	}
);

scenario(
	'A refused request must not fall through into the action it was guarding',
	function () {
		at_reset( array( 'resend_cooldown' => 0, 'max_ip_requests_per_minute' => 100000 ) );
		global $wpdb;
		$users_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" ); // phpcs:ignore WordPress.DB

		// Same trick as above: wp_die() comes back instead of ending the request.
		$swallow = static function () {};
		$swap    = static function () use ( $swallow ) {
			return $swallow;
		};
		add_filter( 'wp_die_ajax_handler', $swap, 99 );

		$bad = array();
		browser_guest();
		foreach ( array( 'ashna_request_otp', 'ashna_verify_otp', 'ashna_password_login', 'ashna_passkey_login_options', 'ashna_passkey_login_verify' ) as $action ) {
			foreach ( at_payloads() as $label => $payload ) {
				try {
					// No nonce at all: the gate must stop the handler.
					ajax( $action, array( 'identifier' => $payload, 'code' => $payload, 'password' => $payload, 'nonce' => 'deadbeef' ) );
					// A valid nonce with input that has to be refused later in the handler.
					at_post(
						$action,
						array(
							'identifier'      => 'zz-invalid-' . wp_rand(),
							'code'            => $payload,
							'password'        => $payload,
							'credential_id'   => $payload,
							'client_data_json' => $payload,
							'authenticator_data' => $payload,
							'signature'       => $payload,
							'captcha_answer'  => $payload,
							'captcha_token'   => $payload,
						)
					);
				} catch ( Throwable $e ) {
					$bad[] = $action . ' / ' . $label . ' → ' . get_class( $e ) . ': ' . $e->getMessage();
				}
			}
		}
		browser_guest();
		check( 'no refused request sent a code', empty( Ashna_T::$codes ), array_slice( array_keys( Ashna_T::$codes ), 0, 4 ) );
		check( 'no refused request signed anybody in', empty( Ashna_T::$cookies ) && empty( Ashna_T::$logins ) && 0 === get_current_user_id() );
		check( 'no refused request created a user', $users_before === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" ) ); // phpcs:ignore WordPress.DB
		check( 'no throwable escaped a refused request', empty( $bad ), array_slice( $bad, 0, 4 ) );

		remove_filter( 'wp_die_ajax_handler', $swap, 99 );
		at_reset();
	}
);

// Leave the site exactly as it was found (the fixtures create roles in the database).
ashna_fixture_remove_roles();
ashna_t_finish( 'attacks' );
