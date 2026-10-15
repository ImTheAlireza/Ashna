<?php
/**
 * TEST ONLY — browser QA fixtures for Ashna (tests/browser/qa-browser.mjs). Never install on a real site:
 * it adds an auto-login and a settings switch without any authentication.
 */

// Defence in depth: do nothing unless the request comes from this machine (Playground uses 127.0.0.1).
if ( ! isset( $_SERVER['REMOTE_ADDR'] ) || ! in_array( $_SERVER['REMOTE_ADDR'], array( '127.0.0.1', '::1' ), true ) ) {
	return;
}

add_action(
	'init',
	function () {
		if ( get_option( 'ashna_browser_qa_ready' ) !== '5' ) {
			$s = get_option( 'ashna_otp_settings', array() );
			$s = array_merge(
				is_array( $s ) ? $s : array(),
				array(
					'active_sms_gateway'             => 'sandbox',
					'show_debug_code_in_toast'       => 1,
					'login_mode'                     => 'phone_and_email',
					'allow_password_login'           => 1,
					'enable_passkey'                 => 1,
					'enable_global_modal'            => 1,
					'modal_mobile_sheet'             => 1,
					'resend_cooldown'                => 8,
					'wp_login_integration'           => 1,
					'whitelisted_identifiers'        => '',
					'max_ip_requests_per_minute'     => 30,
					'max_ip_requests_per_hour'       => 200,
					'max_subnet_requests_per_minute' => 60,
					'attack_mode_threshold'          => 0,
				)
			);
			$pages = array(
				'login-test'   => array( 'ورود آزمایشی', '[ashna_otp_form]' ),
				'modal-test'   => array( 'دکمه مودال', '<p>صفحه آزمایش مودال</p>[ashna_otp_button]' ),
				'account-test' => array( 'کلیدهای عبور', '[ashna_passkey_manager]' ),
				'ashna-login'  => array( 'صفحه ورود مستقل', '' ),
			);
			foreach ( $pages as $slug => $page ) {
				$existing = get_page_by_path( $slug );
				$id       = $existing ? $existing->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $page[0], 'post_content' => $page[1] ) );
				if ( 'ashna-login' === $slug ) {
					$s['standalone_page_id'] = (int) $id;
				}
			}
			update_option( 'ashna_otp_settings', $s );
			if ( ! username_exists( 'qa_pw_user' ) ) {
				$uid = wp_insert_user( array( 'user_login' => 'qa_pw_user', 'user_pass' => 'Qa-Pass-123!', 'user_email' => 'qa_pw_user@example.com', 'role' => 'subscriber' ) );
				update_user_meta( $uid, 'ashna_phone', '09351112233' );
			}
			if ( ! username_exists( 'qa_admin' ) ) {
				wp_insert_user( array( 'user_login' => 'qa_admin', 'user_pass' => 'Qa-Admin-123!', 'user_email' => 'qa_admin@example.com', 'role' => 'administrator' ) );
			}
			update_option( 'permalink_structure', '/%postname%/' );
			flush_rewrite_rules( false );
			update_option( 'ashna_browser_qa_ready', '5' );
		}
		// Auto-login for the admin pass of the browser QA (test only).
		if ( isset( $_GET['qa_login_as'] ) && 'admin' === $_GET['qa_login_as'] ) {
			$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
			if ( $admins ) {
				wp_set_current_user( $admins[0]->ID );
				wp_set_auth_cookie( $admins[0]->ID, false );
				wp_safe_redirect( admin_url( 'admin.php?page=ashna-otp' ) );
				exit;
			}
		}
	},
	1
);

// Test inbox: Playground cannot send mail, so keep the last message instead (test only).
add_filter(
	'pre_wp_mail',
	function ( $short, $atts ) {
		update_option( 'qa_last_mail', array( 'to' => $atts['to'], 'subject' => $atts['subject'], 'message' => $atts['message'] ), false );
		return true;
	},
	10,
	2
);

add_action(
	'init',
	function () {
		if ( isset( $_GET['qa_last_mail'] ) ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			echo wp_json_encode( get_option( 'qa_last_mail', array() ) );
			exit;
		}
		// Flip one Ashna setting for a browser scenario: ?qa_set=key:value (test only).
		if ( isset( $_GET['qa_set'] ) ) {
			list( $k, $v ) = array_pad( explode( ':', sanitize_text_field( wp_unslash( $_GET['qa_set'] ) ), 2 ), 2, '' );
			$s       = get_option( 'ashna_otp_settings', array() );
			$s[ $k ] = is_numeric( $v ) ? (int) $v : $v;
			update_option( 'ashna_otp_settings', $s );
			header( 'Content-Type: text/plain' );
			echo 'ok ' . esc_html( $k );
			exit;
		}
		// Fresh state for a browser phase: no lockouts, counters or attack mode from earlier runs.
		if ( isset( $_GET['qa_reset'] ) ) {
			foreach ( array_keys( Ashna_Security::get_active_lockouts() ) as $target ) {
				Ashna_Security::unlock_target( $target );
			}
			global $wpdb;
			$like1 = $wpdb->esc_like( '_transient_ashna_' ) . '%';
			$like2 = $wpdb->esc_like( '_transient_timeout_ashna_' ) . '%';
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like1, $like2 ) );
			wp_cache_flush();
			Ashna_Rate_Limiter::end_attack_mode();
			header( 'Content-Type: text/plain' );
			echo 'reset';
			exit;
		}
	},
	0
);
