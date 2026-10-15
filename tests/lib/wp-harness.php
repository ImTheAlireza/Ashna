<?php
/**
 * Minimal harness for running Ashna's AJAX handlers inside a disposable full WordPress.
 *
 * - wp_send_json()/wp_die() throw instead of exiting, the JSON body and HTTP status are captured;
 * - auth cookies are captured from WordPress's own `set_auth_cookie` / `set_logged_in_cookie`
 *   actions (CLI cannot send headers) and can be replayed as the next "browser request";
 * - every change to settings, users and posts is rolled back by ashna_t_cleanup().
 *
 * Load after wp-load.php with Ashna active. Not shipped with the plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Load WordPress first.\n" );
	exit( 1 );
}

error_reporting( E_ALL );
set_error_handler(
	function ( $level, $message, $file, $line ) {
		// WordPress core itself may emit deprecations on newer PHP; only Ashna code must be clean.
		if ( ( E_DEPRECATED === $level || E_USER_DEPRECATED === $level ) && false === strpos( $file, '/ashna-otp/' ) ) {
			return true;
		}
		if ( error_reporting() & $level ) {
			throw new ErrorException( $message, 0, $level, $file, $line );
		}
		return false;
	}
);

require_once ABSPATH . 'wp-admin/includes/user.php';

/*
 * WordPress's own warnings (_doing_it_wrong, deprecated functions / hooks / arguments / classes)
 * are raised from core files, so the handler above cannot attribute them. Record every one that
 * Ashna code is on the call stack for; ashna_t_finish() fails the suite if there are any.
 */
$GLOBALS['ashna_t_wp_notices'] = array();
foreach ( array( 'doing_it_wrong_run', 'deprecated_function_run', 'deprecated_hook_run', 'deprecated_argument_run', 'deprecated_class_run', 'deprecated_file_included' ) as $ashna_t_hook ) {
	add_action(
		$ashna_t_hook,
		function ( $what = '', $message = '' ) use ( $ashna_t_hook ) {
			foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) as $frame ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
				if ( isset( $frame['file'] ) && false !== strpos( $frame['file'], '/ashna-otp/' ) ) {
					$GLOBALS['ashna_t_wp_notices'][] = $ashna_t_hook . ': ' . $what . ' ' . ( is_scalar( $message ) ? $message : '' ) . ' (' . basename( $frame['file'] ) . ':' . $frame['line'] . ')';
					break;
				}
			}
		},
		10,
		2
	);
}
unset( $ashna_t_hook );

class Ashna_Test_Halt extends Exception {}

class Ashna_T {
	public static $pass     = 0;
	public static $fail     = 0;
	public static $status   = 200;
	public static $cookies  = array();
	public static $logins   = array();
	public static $codes    = array();
	public static $users    = array();
	public static $posts    = array();
	public static $settings = null;
	public static $options  = array();
}

Ashna_T::$settings = get_option( 'ashna_otp_settings', array() );

add_filter( 'wp_doing_ajax', '__return_true' );
add_filter(
	'wp_die_ajax_handler',
	function () {
		return function ( $message = '' ) {
			throw new Ashna_Test_Halt( is_scalar( $message ) ? (string) $message : '' );
		};
	}
);
add_filter( 'send_auth_cookies', '__return_false' );
add_filter(
	'status_header',
	function ( $header, $code ) {
		Ashna_T::$status = (int) $code;
		return $header;
	},
	10,
	2
);
add_action(
	'set_auth_cookie',
	function ( $cookie, $expire, $expiration, $user_id, $scheme, $token ) {
		Ashna_T::$cookies['auth'] = compact( 'cookie', 'expire', 'expiration', 'user_id', 'scheme', 'token' );
	},
	10,
	6
);
add_action(
	'set_logged_in_cookie',
	function ( $cookie, $expire, $expiration, $user_id, $scheme, $token ) {
		Ashna_T::$cookies['logged_in'] = compact( 'cookie', 'expire', 'expiration', 'user_id', 'scheme', 'token' );
	},
	10,
	6
);
add_action(
	'wp_login',
	function ( $login, $user ) {
		Ashna_T::$logins[] = (int) $user->ID;
	},
	10,
	2
);
add_action(
	'ashna_otp_sent',
	function ( $recipient, $code ) {
		Ashna_T::$codes[ $recipient ] = $code;
	},
	10,
	2
);

function check( $label, $condition, $detail = '' ) {
	if ( $condition ) {
		++Ashna_T::$pass;
		echo '  ok   ', $label, "\n";
	} else {
		++Ashna_T::$fail;
		echo '  FAIL ', $label, ( '' !== $detail ? ' — ' . ( is_scalar( $detail ) ? $detail : wp_json_encode( $detail, JSON_UNESCAPED_UNICODE ) ) : '' ), "\n";
	}
}

function scenario( $name, callable $fn ) {
	echo "\n== ", $name, "\n";
	try {
		$fn();
	} catch ( Throwable $e ) {
		check( 'no exception', false, get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() );
	}
}

/** Merge settings for the next steps (no admin sanitizing: tests may use edge values). */
function ashna_t_settings( array $changes ) {
	$current = get_option( 'ashna_otp_settings', array() );
	Ashna_Helper::save_settings( array_merge( is_array( $current ) ? $current : array(), $changes ) );
	Ashna_Helper::flush_cache();
}

function ashna_t_option( $name, $value ) {
	if ( ! array_key_exists( $name, Ashna_T::$options ) ) {
		Ashna_T::$options[ $name ] = get_option( $name, null );
	}
	update_option( $name, $value );
}

/** Browser without cookies. */
function browser_guest() {
	$_COOKIE                 = array();
	$GLOBALS['current_user'] = null;
	wp_set_current_user( 0 );
}

/** Browser that kept the cookies of the last sign-in (next request). */
function browser_signed_in() {
	$_COOKIE = array(
		AUTH_COOKIE      => Ashna_T::$cookies['auth']['cookie'],
		LOGGED_IN_COOKIE => Ashna_T::$cookies['logged_in']['cookie'],
	);
	$GLOBALS['current_user'] = null;
	wp_get_current_user(); // determine_current_user from the cookies, like a real request.
}

/**
 * Run an AJAX action as the current browser.
 *
 * Input is slashed exactly like wp-settings.php does (wp_magic_quotes), so handlers that forget
 * wp_unslash() store "O\'Brien" here too, and ones that unslash twice lose real backslashes.
 */
function ajax( $action, array $post = array(), array $server = array() ) {
	$_POST    = wp_slash( array( 'action' => $action ) + $post );
	$_REQUEST = $_POST;
	$_GET     = array();
	$saved    = $_SERVER;
	foreach ( $server as $key => $value ) {
		$_SERVER[ $key ] = wp_slash( $value );
	}
	Ashna_T::$status = 200;
	$hook = ( is_user_logged_in() ? 'wp_ajax_' : 'wp_ajax_nopriv_' ) . $action;
	ob_start();
	try {
		do_action( $hook );
	} catch ( Ashna_Test_Halt $e ) {
		unset( $e );
	} finally {
		$out     = ob_get_clean();
		$_SERVER = $saved;
	}
	return array(
		'status' => Ashna_T::$status,
		'json'   => json_decode( trim( (string) $out ), true ),
		'raw'    => $out,
	);
}

function ok_json( $res ) {
	return is_array( $res['json'] ) && ! empty( $res['json']['success'] );
}

function data_of( $res, $key, $default = null ) {
	return is_array( $res['json'] ) && isset( $res['json']['data'][ $key ] ) ? $res['json']['data'][ $key ] : $default;
}

function random_phone() {
	return '0912' . str_pad( (string) wp_rand( 0, 9999999 ), 7, '0', STR_PAD_LEFT );
}

function make_user( $login, $role = 'subscriber', array $meta = array(), $password = null ) {
	$id = wp_insert_user(
		array(
			'user_login' => $login . '_' . wp_rand( 1000, 9999 ),
			'user_pass'  => null === $password ? wp_generate_password( 20 ) : $password,
			'user_email' => $login . wp_rand( 1000, 9999 ) . '@example.test',
			'role'       => $role,
		)
	);
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( $id->get_error_message() );
	}
	Ashna_T::$users[] = $id;
	foreach ( $meta as $key => $value ) {
		update_user_meta( $id, $key, $value );
	}
	return get_user_by( 'id', $id );
}

/** Request a code as the current browser, return [response, code]. */
function request_code( $identifier, array $extra = array(), array $server = array() ) {
	unset( Ashna_T::$codes[ Ashna_Helper::parse_identifier( $identifier )['normalized'] ] );
	$res  = ajax( 'ashna_request_otp', array_merge( array( 'identifier' => $identifier, 'nonce' => wp_create_nonce( 'ashna_otp_nonce' ) ), $extra ), $server );
	$norm = Ashna_Helper::parse_identifier( $identifier )['normalized'];
	return array( $res, isset( Ashna_T::$codes[ $norm ] ) ? Ashna_T::$codes[ $norm ] : null );
}

/** Track users created by Ashna during a test for cleanup. */
function track_user_id( $id ) {
	if ( $id ) {
		Ashna_T::$users[] = (int) $id;
	}
}

function ashna_t_cleanup() {
	foreach ( array_unique( Ashna_T::$users ) as $id ) {
		wp_delete_user( $id );
	}
	foreach ( array_unique( Ashna_T::$posts ) as $id ) {
		wp_delete_post( $id, true );
	}
	update_option( 'ashna_otp_settings', Ashna_T::$settings );
	foreach ( Ashna_T::$options as $name => $value ) {
		if ( null === $value ) {
			delete_option( $name );
		} else {
			update_option( $name, $value );
		}
	}
	Ashna_Helper::flush_cache();
}

function ashna_t_finish( $suite ) {
	ashna_t_cleanup();
	$notices = array_values( array_unique( $GLOBALS['ashna_t_wp_notices'] ) );
	check( 'WordPress raised no _doing_it_wrong / deprecation notice for Ashna code', empty( $notices ), array_slice( $notices, 0, 5 ) );
	printf( "\n%d passed, %d failed in %s scenarios\n", Ashna_T::$pass, Ashna_T::$fail, $suite );
	exit( Ashna_T::$fail > 0 ? 1 : 0 );
}
