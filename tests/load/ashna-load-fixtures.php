<?php
/**
 * TEST ONLY — load-test fixtures for `tests/load/load-login.mjs`. Never install on a real site:
 * it creates users, reports server state and answers whoami without any authentication.
 *
 * Endpoints (loopback requests only):
 *   ?qa_seed_users=2000&qa_offset=0   create that many `loaduser*` accounts with an ashna_phone
 *   ?qa_purge_users=1                 delete every `loaduser*` account again
 *   ?qa_whoami=1                      JSON: who the request is signed in as (for the driver)
 *   ?qa_load_report=1                 JSON: counters, limits, attack mode, lockouts, log rows
 */

if ( ! isset( $_SERVER['REMOTE_ADDR'] ) || ! in_array( $_SERVER['REMOTE_ADDR'], array( '127.0.0.1', '::1' ), true ) ) {
	return;
}

require_once __DIR__ . '/seed-users.php';

add_action(
	'init',
	function () {
		if ( isset( $_GET['qa_whoami'] ) ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			$user = wp_get_current_user();
			echo wp_json_encode(
				array(
					'signed_in' => $user->exists(),
					'login'     => $user->exists() ? $user->user_login : null,
					'id'        => $user->exists() ? (int) $user->ID : 0,
					'phone'     => $user->exists() ? (string) get_user_meta( $user->ID, 'ashna_phone', true ) : '',
					'sessions'  => $user->exists() ? count( WP_Session_Tokens::get_instance( $user->ID )->get_all() ) : 0,
				)
			);
			exit;
		}

		if ( isset( $_GET['qa_load_report'] ) ) {
			global $wpdb;
			header( 'Content-Type: application/json; charset=utf-8' );
			$logs = Ashna_Logger::table_name();
			echo wp_json_encode(
				array(
					'users'          => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" ), // phpcs:ignore WordPress.DB
					'load_users'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_login LIKE 'loaduser%'" ), // phpcs:ignore WordPress.DB
					'log_rows'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$logs}" ), // phpcs:ignore WordPress.DB
					'log_statuses'   => $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$logs} GROUP BY status", ARRAY_A ), // phpcs:ignore WordPress.DB
					'log_gateways'   => $wpdb->get_results( "SELECT gateway, COUNT(*) AS n FROM {$logs} GROUP BY gateway", ARRAY_A ), // phpcs:ignore WordPress.DB
					'attack_mode'    => Ashna_Rate_Limiter::attack_mode_active(),
					'lockouts'       => count( Ashna_Security::get_active_lockouts() ),
					'block_stats'    => Ashna_Rate_Limiter::block_stats(),
					'settings'       => array(
						'active_sms_gateway'         => Ashna_Helper::get_option( 'active_sms_gateway' ),
						'attack_mode_threshold'      => Ashna_Helper::get_option( 'attack_mode_threshold' ),
						'max_ip_requests_per_minute' => Ashna_Helper::get_option( 'max_ip_requests_per_minute' ),
						'max_subnet_requests_per_minute' => Ashna_Helper::get_option( 'max_subnet_requests_per_minute' ),
						'max_requests_per_hour'      => Ashna_Helper::get_option( 'max_requests_per_hour' ),
						'captcha_type'               => Ashna_Helper::get_option( 'captcha_type' ),
						'show_debug_code'            => Ashna_Helper::get_option( 'show_debug_code_in_toast' ),
					),
					'db_version'     => get_option( 'ashna_otp_db_version' ),
					'server_time'    => current_time( 'mysql' ),
					'php'            => PHP_VERSION,
					'peak_memory_mb' => round( memory_get_peak_usage( true ) / 1048576, 1 ),
				)
			);
			exit;
		}

		if ( isset( $_GET['qa_seed_users'] ) ) {
			$count = min( 20000, max( 1, (int) $_GET['qa_seed_users'] ) );
			$out   = ashna_load_seed_users( $count, isset( $_GET['qa_offset'] ) ? max( 0, (int) $_GET['qa_offset'] ) : 0 );
			global $wpdb;
			$out['users_now'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" ); // phpcs:ignore WordPress.DB
			header( 'Content-Type: application/json; charset=utf-8' );
			echo wp_json_encode( $out );
			exit;
		}

		if ( isset( $_GET['qa_purge_users'] ) ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			echo wp_json_encode( ashna_load_purge_users() );
			exit;
		}
},
	0
);
