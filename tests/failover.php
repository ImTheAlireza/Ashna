<?php
/**
 * Fast-failover engine tests (QC 3): short per-gateway timeouts, total budget,
 * circuit breaker, error classification, Bale token retry and channel fallback.
 *
 * Gateways are simulated through the real `pre_http_request` filter. A "hang"
 * really sleeps for the timeout WordPress was given, so durations are real.
 *
 * Isolated mode (tests/run-failover.py): WordPress's real hook API and WP_Error,
 * with stubbed options, transients and database. Full mode: require wp-load.php
 * of a disposable WordPress with Ashna active, then include this file.
 */

error_reporting( E_ALL );
set_error_handler(
	function ( $level, $message, $file, $line ) {
		if ( error_reporting() & $level ) {
			throw new ErrorException( $message, 0, $level, $file, $line );
		}
		return false;
	}
);

$ashna_full_wp = defined( 'ABSPATH' ) && function_exists( 'wp_remote_post' );

if ( ! $ashna_full_wp ) {
	$plugin_dir = rtrim( $argv[1], '/' ) . '/';
	define( 'ABSPATH', rtrim( $argv[2], '/' ) . '/' );
	define( 'ASHNA_OTP_PATH', $plugin_dir );
	define( 'ASHNA_OTP_URL', 'https://example.test/wp-content/plugins/ashna-otp/' );
	define( 'ASHNA_OTP_VERSION', '3.0.0' );
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DAY_IN_SECONDS', 86400 );
	require ABSPATH . 'wp-includes/plugin.php';
	require ABSPATH . 'wp-includes/class-wp-error.php';

	$GLOBALS['options']    = array();
	$GLOBALS['transients'] = array();
	$GLOBALS['log_rows']   = array();

	function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
	function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
	function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; return true; }
	function add_option( $key, $value = '', $deprecated = '', $autoload = null ) { $GLOBALS['options'][ $key ] = $value; return true; }
	function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); return true; }
	function get_transient( $key ) { return isset( $GLOBALS['transients'][ $key ] ) ? $GLOBALS['transients'][ $key ] : false; }
	function set_transient( $key, $value, $ttl = 0 ) { $GLOBALS['transients'][ $key ] = $value; return true; }
	function delete_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); return true; }
	function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
	function absint( $value ) { return abs( (int) $value ); }
	function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
	function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
	function wp_unslash( $value ) { return is_string( $value ) ? stripslashes( $value ) : $value; }
	function wp_strip_all_tags( $text ) { return trim( strip_tags( (string) $text ) ); }
	function wp_html_excerpt( $str, $count, $more = '' ) { return mb_strlen( $str ) > $count ? mb_substr( $str, 0, $count ) . $more : $str; }
	function wp_json_encode( $data ) { return json_encode( $data ); }
	function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
	function wp_hash_password( $password ) { return password_hash( $password, PASSWORD_DEFAULT ); }
	function wp_rand( $min = 0, $max = 0 ) { return random_int( $min, $max ); }
	function current_time( $type ) { return gmdate( 'Y-m-d H:i:s' ); }
	function get_bloginfo( $key = '' ) { return 'name' === $key ? 'Test Site' : 'admin@example.test'; }
	function is_email( $email ) { return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ); }
	function get_users( $args = array() ) { return array(); }
	function get_user_meta( $user_id, $key = '', $single = false ) { return ''; }
	function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
	function wp_mail( $to, $subject, $message, $headers = '' ) { return true; }
	function wp_remote_request( $url, $args = array() ) {
		$args = array_merge( array( 'timeout' => 5, 'method' => 'GET' ), $args );
		$pre  = apply_filters( 'pre_http_request', false, $args, $url );
		return false !== $pre ? $pre : new WP_Error( 'http_request_failed', 'Network access is disabled in tests.' );
	}
	function wp_remote_post( $url, $args = array() ) { return wp_remote_request( $url, array_merge( $args, array( 'method' => 'POST' ) ) ); }
	function wp_remote_head( $url, $args = array() ) { return wp_remote_request( $url, array_merge( $args, array( 'method' => 'HEAD' ) ) ); }
	function wp_generate_uuid4() { return sprintf( '%04x%04x-%04x-4%03x-%04x-%04x%04x%04x', mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xfff ), mt_rand( 0x8000, 0xbfff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ) ); }
	function wp_remote_retrieve_body( $response ) { return is_wp_error( $response ) || ! isset( $response['body'] ) ? '' : $response['body']; }
	function wp_remote_retrieve_response_code( $response ) { return is_wp_error( $response ) || ! isset( $response['response']['code'] ) ? '' : $response['response']['code']; }

	class Ashna_Test_Wpdb {
		public $prefix    = 'wp_';
		public $insert_id = 0;
		public function prepare( $query, ...$args ) { return vsprintf( str_replace( array( '%s', '%d' ), array( "'%s'", '%d' ), $query ), $args ); }
		public function query( $sql ) { return 0; }
		public function insert( $table, $data, $format = null ) { $GLOBALS['log_rows'][] = $data; $this->insert_id = count( $GLOBALS['log_rows'] ); return 1; }
	}
	$GLOBALS['wpdb'] = new Ashna_Test_Wpdb();
	$_SERVER['REMOTE_ADDR'] = '203.0.113.7';

	require ASHNA_OTP_PATH . 'includes/class-ashna-autoloader.php';
	Ashna_Autoloader::register();
}

/* -------------------------------------------------------------------------
 * Simulated SMS / Bale providers
 * ----------------------------------------------------------------------- */

$GLOBALS['sim']   = array();
$GLOBALS['calls'] = array();

function sim( array $behaviour ) {
	$GLOBALS['sim']   = $behaviour;
	$GLOBALS['calls'] = array();
	delete_transient( 'ashna_bale_safir_token' );
}

function http_response( $code, $body ) {
	return array(
		'headers'  => array(),
		'body'     => is_string( $body ) ? $body : json_encode( $body ),
		'response' => array( 'code' => $code, 'message' => '' ),
		'cookies'  => array(),
		'filename' => null,
	);
}

function ok_response( $host, $path ) {
	if ( false !== strpos( $host, 'sms.ir' ) ) {
		return http_response( 200, array( 'status' => 1, 'message' => 'موفق' ) );
	}
	if ( false !== strpos( $host, 'kavenegar' ) ) {
		return http_response( 200, array( 'return' => array( 'status' => 200, 'message' => 'تایید شد' ), 'entries' => array() ) );
	}
	if ( false !== strpos( $host, 'payamak-panel' ) ) {
		return http_response( 200, array( 'RetStatus' => 1, 'StrRetStatus' => 'Ok', 'Value' => '1234' ) );
	}
	if ( false !== strpos( $host, 'console.melipayamak' ) ) {
		return http_response( 200, array( 'recId' => 4815162342, 'status' => 'ارسال موفق بود' ) );
	}
	if ( false !== strpos( $host, 'iranpayamak' ) ) {
		return http_response( 200, array( 'status' => 'success', 'data' => array( 'message_id' => 77 ) ) );
	}
	if ( false !== strpos( $host, 'msgway' ) ) {
		return http_response( 200, array( 'status' => 'success', 'referenceID' => '1650364661182050044' ) );
	}
	if ( false !== strpos( $host, 'edge.ippanel' ) ) {
		return http_response( 200, array( 'meta' => array( 'status' => true ), 'data' => array() ) );
	}
	if ( false !== strpos( $host, 'safir.bale.ai' ) ) {
		return http_response( 200, array( 'message_id' => 'BvQjaR.fIKt7kH.EXTddgYduJ2' ) );
	}
	return http_response( 200, '{}' );
}

add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		$host   = (string) parse_url( $url, PHP_URL_HOST );
		$path   = (string) parse_url( $url, PHP_URL_PATH );
		$method = isset( $args['method'] ) ? strtoupper( $args['method'] ) : 'POST';
		$mode   = 'ok';
		foreach ( $GLOBALS['sim'] as $pattern => $behaviour ) {
			if ( false !== strpos( $method . ' ' . $host . $path, $pattern ) ) {
				if ( is_array( $behaviour ) ) {
					$mode = count( $behaviour ) > 1 ? array_shift( $GLOBALS['sim'][ $pattern ] ) : $behaviour[0];
				} else {
					$mode = $behaviour;
				}
				break;
			}
		}
		$GLOBALS['calls'][] = array(
			'method'  => $method,
			'host'    => $host,
			'path'    => $path,
			'url'     => $url,
			'timeout' => (float) $args['timeout'],
			'connect' => isset( $args['ashna_connect_timeout'] ) ? (float) $args['ashna_connect_timeout'] : null,
			'headers' => isset( $args['headers'] ) ? $args['headers'] : array(),
			'body'    => isset( $args['body'] ) && is_string( $args['body'] ) ? json_decode( $args['body'], true ) : null,
		);

		if ( 0 === strpos( (string) $mode, 'slow:' ) ) {
			$delay = (float) substr( $mode, 5 );
			usleep( (int) ( min( $delay, (float) $args['timeout'] ) * 1000000 ) );
			if ( $delay > (float) $args['timeout'] ) {
				return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
			}
			return 'HEAD' === $method ? http_response( 404, '' ) : ok_response( $host, $path );
		}

		switch ( $mode ) {
			case 'hang':
				usleep( (int) ( $args['timeout'] * 1000000 ) );
				return new WP_Error( 'http_request_failed', sprintf( 'cURL error 28: Operation timed out after %d milliseconds with 0 bytes received', $args['timeout'] * 1000 ) );
			case 'refused':
				return new WP_Error( 'http_request_failed', 'cURL error 7: Failed to connect to ' . $host . ' port 443 after 3 ms: Connection refused' );
			case '500':
				return http_response( 500, array( 'message' => 'Internal Server Error' ) );
			case '401':
				return http_response( 401, array( 'message' => 'Unauthorized' ) );
			case 'kav411':
				return http_response( 411, array( 'return' => array( 'status' => 411, 'message' => 'گیرنده نامعتبر است' ), 'entries' => null ) );
			case 'kav418':
				return http_response( 418, array( 'return' => array( 'status' => 418, 'message' => 'اعتبار حساب شما کافی نیست' ), 'entries' => null ) );
			case 'msgway400':
				return http_response( 400, array( 'status' => 'error', 'error' => array( 'code' => '1103', 'message' => 'الگو یافت نشد' ) ) );
			case 'safir17':
				return http_response( 400, array( 'message' => 'not a bale user', 'error_data' => array( array( 'phone_number' => '989121234567', 'code' => 17, 'description' => 'کاربر اکانت بله ندارد' ) ) ) );
			default:
				return 'HEAD' === $method ? http_response( 404, '' ) : ok_response( $host, $path );
		}
	},
	10,
	3
);

/* -------------------------------------------------------------------------
 * Helpers
 * ----------------------------------------------------------------------- */

function check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function configure( array $overrides = array() ) {
	$settings = array_merge(
		Ashna_Helper::default_settings(),
		array(
			'active_sms_gateway'      => 'smsir',
			'smsir_api_key'           => 'smsir-key',
			'smsir_template_id'       => 100000,
			'backup_sms_gateway_1'    => 'kavenegar',
			'kavenegar_api_key'       => 'kavenegar-key',
			'kavenegar_template'      => 'verify',
			'backup_sms_gateway_2'    => 'melipayamak',
			'melipayamak_username'    => 'user',
			'melipayamak_password'    => 'pass',
			'melipayamak_body_id'     => 123,
			'backup_sms_gateway_3'    => 'none',
			'ippanel_api_key'         => 'ippanel-key',
			'ippanel_pattern_code'    => 'pattern',
			'farazsms_api_key'        => 'faraz-key',
			'farazsms_pattern_code'   => 'faraz-pattern',
			'bale_mode'               => 'safir',
			'bale_api_key'            => 'safir-access-key',
			'bale_bot_id'             => '123456789',
			'msgway_api_key'          => 'msgway-key',
			'msgway_sms_template_id'  => '3',
			'msgway_messenger_template_id' => '10',
			'msgway_messenger_mode'   => 'smart',
			'mobile_delivery_channel' => 'sms',
			'gateway_timeout'         => 2,
			'gateway_cooldown'        => 1,
		),
		$overrides
	);
	Ashna_Helper::save_settings( $settings );
	Ashna_Helper::flush_cache();
}

function health_reload() {
	$property = new ReflectionProperty( 'Ashna_Failover', 'health' );
	$property->setAccessible( true );
	$property->setValue( null, null );
}

function fresh_start( array $overrides = array() ) {
	configure( $overrides );
	Ashna_Failover::reset_all();
	health_reload();
}

function expire_cooldown( $id ) {
	$all                      = get_option( Ashna_Failover::OPTION, array() );
	$all[ $id ]['open_until'] = time() - 1;
	update_option( Ashna_Failover::OPTION, $all, false );
	health_reload();
}

/**
 * Dispatch and measure. The returned time is the delivery time reported by the
 * engine (excludes code hashing and logging), or wall time for failures.
 */
function dispatch( $phone = '09121234567', $channel = '', $gateway = '' ) {
	$started = microtime( true );
	$result  = Ashna_Gateway_Manager::dispatch_otp( $phone, 'phone', $channel, $gateway );
	$wall    = microtime( true ) - $started;
	return array( $result, is_array( $result ) && isset( $result['elapsed_ms'] ) ? $result['elapsed_ms'] / 1000 : $wall );
}

function calls_to( $needle ) {
	return array_values(
		array_filter(
			$GLOBALS['calls'],
			function ( $call ) use ( $needle ) {
				return false !== strpos( $call['host'] . $call['path'], $needle );
			}
		)
	);
}

function heads_to( $needle ) {
	return array_values(
		array_filter(
			calls_to( $needle ),
			function ( $call ) {
				return 'HEAD' === $call['method'];
			}
		)
	);
}

function posts_to( $needle ) {
	return array_values(
		array_filter(
			calls_to( $needle ),
			function ( $call ) {
				return 'POST' === $call['method'];
			}
		)
	);
}

function last_log_message() {
	global $wpdb;
	if ( ! empty( $GLOBALS['log_rows'] ) ) {
		$row = end( $GLOBALS['log_rows'] );
		return $row['response_message'];
	}
	return (string) $wpdb->get_var( "SELECT response_message FROM {$wpdb->prefix}ashna_otp_logs ORDER BY id DESC LIMIT 1" ); // phpcs:ignore
}

$GLOBALS['passed'] = 0;
function pass( $name, $seconds = null ) {
	++$GLOBALS['passed'];
	echo 'PASS ', $name, null === $seconds ? '' : sprintf( ' (%.2fs)', $seconds ), "\n";
}

/* -------------------------------------------------------------------------
 * Scenarios
 * ----------------------------------------------------------------------- */

// 1. Healthy primary: one call with the short timeout; connect phase capped.
fresh_start();
sim( array() );
list( $r, $t ) = dispatch();
check( ! is_wp_error( $r ) && 'smsir' === $r['gateway'] && 'sms' === $r['channel'], 'healthy: wrong gateway' );
// The attempt slot starts a few ms before the HTTP call, hence the tolerance.
check( 1 === count( $GLOBALS['calls'] ) && abs( $GLOBALS['calls'][0]['timeout'] - 2.0 ) < 0.05, 'healthy: expected one call with timeout 2s' );
check( abs( $GLOBALS['calls'][0]['connect'] - 2.0 ) < 0.05, 'healthy: connect timeout not passed to the HTTP API' );
check( false !== has_action( 'http_api_curl', array( 'Ashna_Abstract_Gateway', 'apply_connect_timeout' ) ), 'healthy: cURL connect-timeout hook missing' );
check( false !== strpos( last_log_message(), 'مسیر:' ), 'healthy: log entry has no delivery trace' );
pass( 'healthy primary delivers with short timeout', $t );

// 2. Primary hangs: the first visitor waits one short timeout, then the backup delivers.
fresh_start();
sim( array( 'api.sms.ir' => 'hang' ) );
list( $r, $t ) = dispatch();
check( ! is_wp_error( $r ) && 'kavenegar' === $r['gateway'], 'hang: backup did not deliver' );
check( $t >= 1.9 && $t < 2.8, sprintf( 'hang: first visitor waited %.2fs (expected about 2s, not 15s)', $t ) );
check( 'open' === Ashna_Failover::state( 'smsir' ), 'hang: primary was not sidelined' );
check( 'timeout' === Ashna_Failover::get( 'smsir' )['last_type'], 'hang: error not classified as timeout' );
pass( 'hung primary: first visitor waits one short timeout', $t );

// 3. Later visitors skip the sidelined primary: no delay at all.
$GLOBALS['calls'] = array();
list( $r, $t ) = dispatch( '09121234568' );
check( ! is_wp_error( $r ) && 'kavenegar' === $r['gateway'], 'skip: backup did not deliver' );
check( 0 === count( calls_to( 'sms.ir' ) ), 'skip: sidelined primary was called again' );
check( $t < 0.5, sprintf( 'skip: later visitor waited %.2fs', $t ) );
check( false !== strpos( last_log_message(), 'کنار گذاشته' ), 'skip: trace does not mention the skipped gateway' );
pass( 'later visitors skip the failing gateway instantly', $t );

// 4. After the cooldown one quick trial runs (3s cap); still down -> cooldown doubles.
configure( array( 'gateway_timeout' => 5 ) );
expire_cooldown( 'smsir' );
$GLOBALS['calls'] = array();
list( $r, $t ) = dispatch( '09121234569' );
$trial = calls_to( 'sms.ir' );
check( 1 === count( $trial ) && abs( $trial[0]['timeout'] - 3.0 ) < 0.05, 'trial: expected a single 3s trial call' );
check( ! is_wp_error( $r ) && 'kavenegar' === $r['gateway'], 'trial: backup did not deliver' );
$h = Ashna_Failover::get( 'smsir' );
check( 'open' === Ashna_Failover::state( 'smsir' ) && 2 === (int) $h['opens'], 'trial: circuit not re-opened' );
check( abs( ( $h['open_until'] - time() ) - 120 ) <= 2, 'trial: cooldown did not double (60s -> 120s)' );
pass( 'trial after cooldown is capped at 3s and backs off', $t );

// 5. Provider recovers: the trial succeeds and the gateway is back in service.
sim( array() );
expire_cooldown( 'smsir' );
list( $r, $t ) = dispatch( '09121234570' );
check( ! is_wp_error( $r ) && 'smsir' === $r['gateway'], 'recovery: primary not used again' );
check( 'closed' === Ashna_Failover::state( 'smsir' ) && 0 === (int) Ashna_Failover::get( 'smsir' )['opens'], 'recovery: circuit not closed' );
pass( 'recovered gateway returns to service', $t );

// 6. Connection refused: instant failover, gateway sidelined.
fresh_start();
sim( array( 'api.sms.ir' => 'refused' ) );
list( $r, $t ) = dispatch();
check( ! is_wp_error( $r ) && 'kavenegar' === $r['gateway'] && $t < 0.5, 'refused: no instant failover' );
check( 'open' === Ashna_Failover::state( 'smsir' ) && 'connect' === Ashna_Failover::get( 'smsir' )['last_type'], 'refused: not sidelined as connect error' );
pass( 'unreachable gateway fails over instantly', $t );

// 7. HTTP 500: server error, sidelined.
fresh_start();
sim( array( 'api.sms.ir' => '500' ) );
list( $r, $t ) = dispatch();
check( ! is_wp_error( $r ) && 'kavenegar' === $r['gateway'], 'server: no failover' );
check( 'server' === Ashna_Failover::get( 'smsir' )['last_type'] && 'open' === Ashna_Failover::state( 'smsir' ), 'server: not sidelined' );
pass( 'HTTP 5xx fails over and sidelines', $t );

// 8. Missing credentials: skipped instantly, never sidelined (nothing was sent).
fresh_start( array( 'smsir_api_key' => '' ) );
sim( array() );
list( $r, $t ) = dispatch();
check( ! is_wp_error( $r ) && 'kavenegar' === $r['gateway'] && 0 === count( calls_to( 'sms.ir' ) ), 'config: wrong routing' );
check( 'closed' === Ashna_Failover::state( 'smsir' ) && 'config' === Ashna_Failover::get( 'smsir' )['last_type'], 'config: should not sideline' );
pass( 'missing settings fail over without sidelining', $t );

// 9. Invalid recipient (Kavenegar 411): other SMS gateways are not tried, nothing sidelined.
fresh_start( array( 'active_sms_gateway' => 'kavenegar', 'backup_sms_gateway_1' => 'smsir' ) );
sim( array( 'kavenegar' => 'kav411' ) );
list( $r, $t ) = dispatch();
check( is_wp_error( $r ), 'input: expected an error' );
check( 0 === count( calls_to( 'sms.ir' ) ) && 0 === count( calls_to( 'payamak' ) ), 'input: other SMS gateways were tried' );
check( 'closed' === Ashna_Failover::state( 'kavenegar' ), 'input: recipient error sidelined the gateway' );
pass( 'rejected number does not cascade through SMS gateways', $t );

// 9b. ...but a different channel is still tried.
fresh_start( array( 'active_sms_gateway' => 'kavenegar', 'backup_sms_gateway_1' => 'smsir', 'mobile_delivery_channel' => 'sms_fallback_bale' ) );
sim( array( 'kavenegar' => 'kav411' ) );
list( $r, $t ) = dispatch();
check( ! is_wp_error( $r ) && 'bale' === $r['channel'] && 0 === count( calls_to( 'sms.ir' ) ), 'input: Bale fallback not used' );
pass( 'rejected number still falls back to Bale', $t );

// 10. Out of credit (Kavenegar 418): account problem, sidelined, backup delivers.
fresh_start( array( 'active_sms_gateway' => 'kavenegar', 'backup_sms_gateway_1' => 'smsir' ) );
sim( array( 'kavenegar' => 'kav418' ) );
list( $r, $t ) = dispatch();
check( ! is_wp_error( $r ) && 'smsir' === $r['gateway'], 'account: no failover' );
check( 'account' === Ashna_Failover::get( 'kavenegar' )['last_type'] && 'open' === Ashna_Failover::state( 'kavenegar' ), 'account: not sidelined' );
pass( 'out-of-credit gateway is sidelined', $t );

// 11. Everything hangs: the total budget stops the chain (3s x 3 = 9s), the 4th is never called.
fresh_start( array( 'gateway_timeout' => 3, 'backup_sms_gateway_3' => 'ippanel' ) );
sim( array( 'api.sms.ir' => 'hang', 'kavenegar' => 'hang', 'payamak' => 'hang', 'ippanel' => 'hang' ) );
list( $r, $t ) = dispatch();
check( is_wp_error( $r ), 'budget: expected an error' );
check( $t < Ashna_Failover::total_budget() + 0.8, sprintf( 'budget: took %.2fs, budget is %.0fs', $t, Ashna_Failover::total_budget() ) );
check( 0 === count( calls_to( 'ippanel' ) ), 'budget: gateway called after the budget ran out' );
check( false !== strpos( last_log_message(), 'امتحان نشد' ), 'budget: trace does not mention the untried gateway' );
pass( 'total budget caps a fully broken chain', $t );

// 12. Bale (Safir v3) hangs: Bale gets one short slot, then SMS delivers.
fresh_start( array( 'mobile_delivery_channel' => 'bale_fallback_sms' ) );
sim( array( 'safir.bale.ai' => 'hang' ) );
list( $r, $t ) = dispatch();
check( ! is_wp_error( $r ) && 'sms' === $r['channel'] && 'smsir' === $r['gateway'], 'bale-timeout: SMS fallback failed' );
check( $t >= 1.9 && $t < 2.8, sprintf( 'bale-timeout: waited %.2fs (expected about 2s)', $t ) );
check( 'open' === Ashna_Failover::state( 'bale' ), 'bale-timeout: Bale not sidelined' );
pass( 'Bale hang is bounded, SMS takes over', $t );

// 13. Safir v3 request: one call, api-access-key header, bot_id, otp_message, idempotency key.
fresh_start( array( 'mobile_delivery_channel' => 'bale' ) );
sim( array() );
list( $r, $t ) = dispatch( '09121234567' );
$safir = posts_to( 'safir.bale.ai' );
check( ! is_wp_error( $r ) && 'bale' === $r['channel'] && 1 === count( $safir ), 'safir-v3: expected exactly one request' );
check( '/api/v3/send_message' === $safir[0]['path'] && 'safir-access-key' === $safir[0]['headers']['api-access-key'], 'safir-v3: wrong endpoint or auth header' );
$b = $safir[0]['body'];
check( 123456789 === $b['bot_id'] && '989121234567' === $b['phone_number'] && $r['code'] === $b['message_data']['otp_message']['otp'] && 36 === strlen( $b['request_id'] ), 'safir-v3: wrong payload ' . json_encode( $b ) );
pass( 'Bale uses Safir v3 (single request)', $t );

// 14. "Not a Bale user": falls back to SMS without sidelining Bale for everyone.
fresh_start( array( 'mobile_delivery_channel' => 'bale_fallback_sms' ) );
sim( array( 'safir.bale.ai/api/v3/send_message' => 'safir17' ) );
list( $r, $t ) = dispatch();
check( ! is_wp_error( $r ) && 'sms' === $r['channel'], 'not-bale-user: no SMS fallback' );
check( 'closed' === Ashna_Failover::state( 'bale' ), 'not-bale-user: Bale was sidelined' );
pass( 'recipient without Bale gets SMS; Bale stays in service', $t );

// 15. Admin test reaches a sidelined gateway and a success closes the circuit.
fresh_start();
sim( array( 'api.sms.ir' => 'refused' ) );
dispatch();
check( 'open' === Ashna_Failover::state( 'smsir' ), 'forced: setup failed' );
sim( array() );
list( $r, $t ) = dispatch( '09121234567', 'sms', 'smsir' );
check( ! is_wp_error( $r ) && 'smsir' === $r['gateway'] && 'closed' === Ashna_Failover::state( 'smsir' ), 'forced: test did not reach/close the gateway' );
pass( 'admin test bypasses and closes the circuit', $t );

// 16. "Both" strategy: SMS delivered and Bale sidelined -> Bale skipped, no delay.
fresh_start( array( 'mobile_delivery_channel' => 'bale' ) );
sim( array( 'safir.bale.ai' => 'refused' ) );
dispatch();
check( 'open' === Ashna_Failover::state( 'bale' ), 'both: setup failed' );
configure( array( 'mobile_delivery_channel' => 'both' ) );
sim( array() );
list( $r, $t ) = dispatch();
check( ! is_wp_error( $r ) && 'sms' === $r['channel'] && 0 === count( calls_to( 'safir.bale.ai' ) ), 'both: sidelined Bale was called' );
pass( '"both" skips a sidelined Bale', $t );

// 17. Only gateway is sidelined: still tried (last resort) instead of failing outright.
fresh_start( array( 'backup_sms_gateway_1' => 'none', 'backup_sms_gateway_2' => 'none' ) );
sim( array( 'api.sms.ir' => 'refused' ) );
dispatch();
sim( array() );
list( $r, $t ) = dispatch( '09121234571' );
check( ! is_wp_error( $r ) && 'smsir' === $r['gateway'], 'last-resort: lone gateway not tried' );
pass( 'a lone sidelined gateway is still tried', $t );

// 18. Custom third-party gateway that throws: caught, chain continues.
class Ashna_Test_Throwing_Gateway implements Ashna_Gateway_Interface {
	public function get_id() { return 'thrower'; }
	public function get_title() { return 'Thrower'; }
	public function send( $recipient, $code ) { throw new RuntimeException( 'boom' ); }
}
Ashna_Gateway_Manager::register_gateway( new Ashna_Test_Throwing_Gateway() );
fresh_start( array( 'active_sms_gateway' => 'thrower', 'backup_sms_gateway_1' => 'kavenegar' ) );
sim( array() );
list( $r, $t ) = dispatch();
check( ! is_wp_error( $r ) && 'kavenegar' === $r['gateway'], 'exception: chain stopped' );
pass( 'exception in a custom gateway does not break delivery', $t );

// 19. Settings changes close circuits but keep stats; fallback detection for the form.
fresh_start();
sim( array( 'api.sms.ir' => 'refused' ) );
dispatch();
Ashna_Failover::reset_circuits();
health_reload();
check( 'closed' === Ashna_Failover::state( 'smsir' ) && 1 === (int) Ashna_Failover::get( 'smsir' )['fail'], 'reset: circuits/stats wrong' );
check( Ashna_Gateway_Manager::has_fallback(), 'fallback: backups not detected' );
configure( array( 'backup_sms_gateway_1' => 'none', 'backup_sms_gateway_2' => 'none' ) );
check( ! Ashna_Gateway_Manager::has_fallback(), 'fallback: reported without backups' );
configure( array( 'backup_sms_gateway_1' => 'none', 'backup_sms_gateway_2' => 'none', 'mobile_delivery_channel' => 'bale_fallback_sms' ) );
check( Ashna_Gateway_Manager::has_fallback(), 'fallback: channel fallback not detected' );
pass( 'settings save closes circuits; fallback detection' );

// 21. Learned time slot: a fast provider (0.2s) gets 2s instead of the 5s maximum.
fresh_start( array( 'gateway_timeout' => 5 ) );
sim( array( 'api.sms.ir' => 'slow:0.2' ) );
for ( $i = 0; $i < Ashna_Failover::ADAPTIVE_MIN_SAMPLES; $i++ ) {
	dispatch( '0912000000' . $i );
}
check( abs( Ashna_Failover::slot_for( 'smsir' ) - 2.0 ) < 0.01, 'adaptive: slot is ' . Ashna_Failover::slot_for( 'smsir' ) );
sim( array( 'api.sms.ir' => 'hang' ) );
list( $r, $t ) = dispatch( '09121234580' );
check( ! is_wp_error( $r ) && 'kavenegar' === $r['gateway'] && $t >= 1.9 && $t < 2.8, sprintf( 'adaptive: first visitor waited %.2fs (expected ~2s, not 5s)', $t ) );
configure( array( 'gateway_timeout' => 5, 'gateway_adaptive' => 0 ) );
check( 5.0 === Ashna_Failover::slot_for( 'smsir' ), 'adaptive: switch off not honoured' );
pass( 'learned time slot: 2s instead of 5s for a fast gateway', $t );

// 22. Background probe: an unreachable host is sidelined before any visitor arrives.
fresh_start( array( 'gateway_timeout' => 5 ) );
sim( array( 'HEAD api.sms.ir' => 'refused', 'POST api.sms.ir' => 'hang' ) );
$probe = Ashna_Failover::run_probe();
check( 'connect' === $probe['smsir'] && 'ok' === $probe['kavenegar'] && ! isset( $probe['sandbox'] ), 'probe: wrong results ' . json_encode( $probe ) );
check( 'open' === Ashna_Failover::state( 'smsir' ) && ! empty( Ashna_Failover::get( 'smsir' )['by_probe'] ), 'probe: primary not sidelined' );
$GLOBALS['calls'] = array();
list( $r, $t ) = dispatch( '09121234581' );
check( ! is_wp_error( $r ) && 'kavenegar' === $r['gateway'] && 0 === count( posts_to( 'sms.ir' ) ) && $t < 0.5, sprintf( 'probe: first visitor still waited %.2fs', $t ) );
pass( 'probe sidelines a dead gateway before the first visitor', $t );

// 23. Probe sees the host again: a probe-opened circuit gets an immediate trial;
//     a circuit opened by real sends keeps its cooldown.
sim( array() );
Ashna_Failover::run_probe();
check( 'trial' === Ashna_Failover::state( 'smsir' ), 'probe-recovery: no early trial' );
fresh_start();
sim( array( 'POST api.sms.ir' => 'refused' ) );
dispatch();
sim( array() );
Ashna_Failover::run_probe();
check( 'open' === Ashna_Failover::state( 'smsir' ), 'probe-recovery: real-send circuit was cut short' );
configure( array( 'gateway_probe' => 0 ) );
check( array() === Ashna_Failover::run_probe(), 'probe: switch off not honoured' );
pass( 'probe recovery rules and switch' );

// 24. Iranian messengers via Rahpayam (smart route), with SMS fallback strategy.
fresh_start( array( 'mobile_delivery_channel' => 'messenger_fallback_sms' ) );
sim( array() );
list( $r, $t ) = dispatch( '09121234567' );
$mw = posts_to( 'api.msgway.com' );
check( ! is_wp_error( $r ) && 'messenger' === $r['channel'] && 'msgway_messenger' === $r['gateway'] && 1 === count( $mw ), 'msgway: not delivered by messenger' );
$b = $mw[0]['body'];
check( 'smart' === $b['method'] && '+989121234567' === $b['mobile'] && 10 === $b['templateID'] && $r['code'] === $b['code'] && ! isset( $b['provider'] ), 'msgway: wrong smart payload ' . json_encode( $b ) );
check( 'msgway-key' === $mw[0]['headers']['apiKey'] && 'fa' === $mw[0]['headers']['accept-language'] && '/send' === $mw[0]['path'], 'msgway: wrong headers/endpoint' );
pass( 'Rahpayam smart messenger route', $t );

// 25. Rahpayam fixed messenger (Gap = provider 2); template error falls back to SMS, not sidelined.
fresh_start( array( 'mobile_delivery_channel' => 'messenger_fallback_sms', 'msgway_messenger_mode' => 'gap' ) );
sim( array() );
dispatch();
check( 'messenger' === posts_to( 'api.msgway.com' )[0]['body']['method'] && 2 === posts_to( 'api.msgway.com' )[0]['body']['provider'], 'msgway-gap: provider 2 not sent' );
sim( array( 'api.msgway.com' => 'msgway400' ) );
list( $r, $t ) = dispatch( '09121234582' );
check( ! is_wp_error( $r ) && 'sms' === $r['channel'] && 'closed' === Ashna_Failover::state( 'msgway_messenger' ), 'msgway-error: wrong fallback or sidelined' );
check( false !== strpos( last_log_message(), 'الگو یافت نشد' ), 'msgway-error: provider message missing from the log' );
fresh_start( array( 'mobile_delivery_channel' => 'messenger', 'msgway_messenger_mode' => 'custom', 'msgway_messenger_provider' => 0 ) );
sim( array() );
list( $r, $t ) = dispatch();
check( is_wp_error( $r ) && 0 === count( calls_to( 'msgway' ) ), 'msgway-custom: missing provider must not call the API' );
pass( 'Rahpayam gap/custom modes and fallback', $t );

// 26. Rahpayam as a plain SMS gateway in the chain (line 3000 = provider 1).
fresh_start( array( 'active_sms_gateway' => 'msgway', 'msgway_sms_line' => 1 ) );
sim( array() );
list( $r, $t ) = dispatch();
$b = posts_to( 'api.msgway.com' )[0]['body'];
check( ! is_wp_error( $r ) && 'msgway' === $r['gateway'] && 'sms' === $r['channel'] && 'sms' === $b['method'] && 1 === $b['provider'] && 3 === $b['templateID'], 'msgway-sms: wrong payload ' . json_encode( $b ) );
pass( 'Rahpayam SMS gateway', $t );

// 27. Latest provider APIs: FarazSMS (iranpayamak), Melipayamak console + legacy, IPPanel Edge.
fresh_start( array( 'active_sms_gateway' => 'farazsms', 'farazsms_param_name' => 'otp' ) );
sim( array() );
list( $r ) = dispatch( '09121234567' );
$c = posts_to( 'iranpayamak' )[0];
check( ! is_wp_error( $r ) && '/ws/v1/sms/pattern' === $c['path'] && 'faraz-key' === $c['headers']['Api-Key'], 'faraz: wrong endpoint/auth' );
check( 'faraz-pattern' === $c['body']['code'] && array( 'otp' => $r['code'] ) === $c['body']['attributes'] && '09121234567' === $c['body']['recipient'] && ! isset( $c['body']['line_number'] ), 'faraz: wrong payload ' . json_encode( $c['body'] ) );

fresh_start( array( 'active_sms_gateway' => 'melipayamak', 'melipayamak_api_key' => 'meli-key' ) );
sim( array() );
list( $r ) = dispatch( '09121234567' );
$c = posts_to( 'console.melipayamak.com' )[0];
check( ! is_wp_error( $r ) && '/api/send/shared/meli-key' === $c['path'] && 123 === $c['body']['bodyId'] && array( $r['code'] ) === $c['body']['args'], 'meli-console: wrong request ' . json_encode( $c ) );
fresh_start( array( 'active_sms_gateway' => 'melipayamak', 'melipayamak_api_key' => '' ) );
sim( array() );
list( $r ) = dispatch();
check( ! is_wp_error( $r ) && 1 === count( posts_to( 'rest.payamak-panel.com' ) ), 'meli-legacy: username/password fallback not used' );

fresh_start( array( 'active_sms_gateway' => 'ippanel' ) );
sim( array() );
list( $r ) = dispatch( '09121234567' );
$c = posts_to( 'edge.ippanel.com' )[0];
check( ! is_wp_error( $r ) && 'pattern' === $c['body']['sending_type'] && array( '+989121234567' ) === $c['body']['recipients'] && array( 'code' => $r['code'] ) === $c['body']['params'] && 'ippanel-key' === $c['headers']['Authorization'], 'ippanel: wrong request ' . json_encode( $c['body'] ) );
pass( 'FarazSMS / Melipayamak / IPPanel use their current APIs' );

// 28. Probe targets follow the delivery path.
configure( array( 'mobile_delivery_channel' => 'messenger' ) );
$ids = array_map( function ( $g ) { return $g->get_id(); }, Ashna_Gateway_Manager::probe_targets() );
check( array( 'msgway_messenger' ) === $ids, 'targets: messenger-only ' . json_encode( $ids ) );
configure( array( 'mobile_delivery_channel' => 'sms_fallback_bale', 'active_sms_gateway' => 'sandbox' ) );
$ids = array_map( function ( $g ) { return $g->get_id(); }, Ashna_Gateway_Manager::probe_targets() );
check( array( 'kavenegar', 'melipayamak', 'bale' ) === $ids, 'targets: sms+bale ' . json_encode( $ids ) );
pass( 'probe targets follow the delivery path' );

// 20. Classification table.
$cases = array(
	array( new WP_Error( 'x', 'e' ), array( 'calls' => 1, 'exhausted' => '', 'last' => array( 'code' => 0, 'error' => 'cURL error 28: Operation timed out' ) ), 'timeout' ),
	array( new WP_Error( 'x', 'e' ), array( 'calls' => 1, 'exhausted' => '', 'last' => array( 'code' => 0, 'error' => 'cURL error 6: Could not resolve host: api.sms.ir' ) ), 'connect' ),
	array( new WP_Error( 'x', 'e' ), array( 'calls' => 1, 'exhausted' => '', 'last' => array( 'code' => 0, 'error' => 'cURL error 60: SSL certificate problem' ) ), 'connect' ),
	array( new WP_Error( 'x', 'e' ), array( 'calls' => 1, 'exhausted' => '', 'last' => array( 'code' => 503, 'error' => '' ) ), 'server' ),
	array( new WP_Error( 'x', 'e' ), array( 'calls' => 1, 'exhausted' => '', 'last' => array( 'code' => 429, 'error' => '' ) ), 'server' ),
	array( new WP_Error( 'x', 'e' ), array( 'calls' => 1, 'exhausted' => '', 'last' => array( 'code' => 403, 'error' => '' ) ), 'auth' ),
	array( new WP_Error( 'x', 'e' ), array( 'calls' => 1, 'exhausted' => '', 'last' => array( 'code' => 400, 'error' => '' ) ), 'api' ),
	array( new WP_Error( 'x', 'e' ), array( 'calls' => 0, 'exhausted' => '', 'last' => null ), 'config' ),
	array( new WP_Error( 'x', 'e' ), array( 'calls' => 0, 'exhausted' => 'budget', 'last' => null ), 'budget' ),
	array( new WP_Error( 'x', 'Operation timed out after 5000 ms' ), null, 'timeout' ),
	array( new WP_Error( 'x', 'Template not approved' ), null, 'api' ),
	array( new WP_Error( 'x', 'e', array( 'ashna_error_type' => 'input' ) ), array( 'calls' => 1, 'exhausted' => '', 'last' => array( 'code' => 503, 'error' => '' ) ), 'input' ),
);
foreach ( $cases as $i => $case ) {
	$type = Ashna_Failover::classify( $case[0], $case[1] );
	check( $case[2] === $type, sprintf( 'classify #%d: expected %s, got %s', $i, $case[2], $type ) );
}
pass( 'error classification (' . count( $cases ) . ' cases)' );

restore_error_handler();
echo 'PASS ', $GLOBALS['passed'], " failover scenarios\n";
