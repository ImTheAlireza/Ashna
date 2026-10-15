<?php
/**
 * Third-party fixtures for Ashna's integration tests.
 *
 * Neither WooCommerce, Dokan, Paid Memberships Pro nor Ultimate Member can be installed in
 * the offline test sandbox, so the code paths Ashna integrates with are reproduced here.
 * Functions marked "verbatim" are copied unchanged from the upstream GPL sources named in
 * each block; storage/bootstrapping helpers marked "simplified" stand in for code that
 * needs the full plugin. Only loaded by tests, never shipped.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * WooCommerce (simplified): the class/function names Ashna checks, a session object that
 * records init_session_cookie() calls, and the "customer" role WooCommerce creates.
 * ----------------------------------------------------------------------- */
function ashna_fixture_woocommerce() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		// phpcs:ignore Squiz.PHP.Eval.Discouraged -- conditional declaration in tests only.
		eval(
			'class WooCommerce { public $session; }
			class Ashna_Fixture_WC_Session {
				public $calls = array();
				public function init_session_cookie() { $this->calls[] = get_current_user_id(); }
			}
			function WC() {
				static $wc = null;
				if ( null === $wc ) { $wc = new WooCommerce(); $wc->session = new Ashna_Fixture_WC_Session(); }
				return $wc;
			}
			function wc_get_page_permalink( $page ) { return home_url( "/" . $page . "/" ); }
			function is_checkout() { return false; }
			function wc_clean( $var ) { return is_array( $var ) ? array_map( "wc_clean", $var ) : sanitize_text_field( $var ); }'
		);
	}
	if ( ! get_role( 'customer' ) ) {
		add_role( 'customer', 'Customer', array( 'read' => true ) );
		$GLOBALS['ashna_fixture_roles'][] = 'customer';
	}
}

/* -------------------------------------------------------------------------
 * Dokan Lite — getdokan/dokan, branch develop (v5.3.0), GPL-2.0-or-later.
 * ----------------------------------------------------------------------- */
function ashna_fixture_dokan( $dashboard_page_id ) {
	$GLOBALS['ashna_fixture_dokan_options'] = array(
		'dokan_pages'      => array( 'dashboard' => $dashboard_page_id ),
		'dokan_appearance' => array( 'show_register_as_vendor' => 'on' ),
	);

	if ( ! get_role( 'seller' ) ) {
		// includes/Install/Installer.php::user_roles() (capability subset that matters here).
		add_role(
			'seller',
			'Vendor',
			array(
				'read'          => true,
				'publish_posts' => true,
				'edit_posts'    => true,
				'upload_files'  => true,
				'edit_product'  => true,
				'dokandar'      => true,
			)
		);
		$GLOBALS['ashna_fixture_roles'][] = 'seller';
	}

	if ( function_exists( 'dokan_get_option' ) ) {
		return;
	}

	require_once __DIR__ . '/dokan-lite-excerpt.php';
	add_filter( 'woocommerce_login_redirect', 'dokan_after_login_redirect', 1, 2 );
	add_filter( 'woocommerce_registration_redirect', 'ashna_fixture_dokan_registration_redirect', 10, 1 );
	add_action( 'user_register', 'dokan_admin_user_register' );
	$GLOBALS['ashna_fixture_dokan_registration'] = new Ashna_Fixture_Dokan_Registration();
}

/* -------------------------------------------------------------------------
 * Paid Memberships Pro — strangerstudios/paid-memberships-pro, branch dev, GPL-2.0-or-later.
 * ----------------------------------------------------------------------- */
function ashna_fixture_pmpro() {
	if ( function_exists( 'pmpro_login_redirect' ) ) {
		return;
	}

	/** Simplified: the real pmpro_url() resolves the configured PMPro pages. */
	function pmpro_url( $page ) {
		return home_url( '/membership-' . $page . '/' );
	}

	/**
	 * includes/login.php — pmpro_login_redirect(). Verbatim except the membership lookup,
	 * which reads the pmpro_memberships_users table upstream and user meta here.
	 */
	function pmpro_login_redirect( $redirect_to, $request = null, $user = null ) {
		$is_logged_in = ! empty( $user ) && ! empty( $user->ID );

		if ( $is_logged_in && empty( $redirect_to ) ) {
			$is_member = get_user_meta( $user->ID, 'ashna_fixture_pmpro_level', true );
			if ( $is_member ) {
				$redirect_to = pmpro_url( 'account' );
			} else {
				$redirect_to = pmpro_url( 'levels' );
			}
		}

		return apply_filters( 'pmpro_login_redirect_url', $redirect_to, $request, $user );
	}

	add_filter( 'login_redirect', 'pmpro_login_redirect', 10, 3 );

	/** includes/login.php — pmpro_login_url(), verbatim except the PMPro pages global (simplified). */
	function pmpro_login_url( $redirect = '', $force_reauth = false ) {
		$login_url = home_url( '/membership-login/' );
		if ( ! empty( $redirect ) ) {
			$login_url = add_query_arg( 'redirect_to', urlencode( $redirect ), $login_url );
		}
		if ( $force_reauth ) {
			$login_url = add_query_arg( 'reauth', '1', $login_url );
		}
		return apply_filters( 'pmpro_login_url', $login_url, $redirect, $force_reauth );
	}

	// shortcodes/pmpro_login.php registers [pmpro_login]; the fixture prints a marker form.
	add_shortcode(
		'pmpro_login',
		function () {
			return '<form class="pmpro-login-form">PMPRO-LOGIN</form>';
		}
	);
}

/* -------------------------------------------------------------------------
 * MemberPress (closed source): only the public [mepr-login-form] shortcode name.
 * ----------------------------------------------------------------------- */
function ashna_fixture_memberpress() {
	if ( ! shortcode_exists( 'mepr-login-form' ) ) {
		add_shortcode(
			'mepr-login-form',
			function () {
				return '<form class="mepr-login-form">MEPR-LOGIN</form>';
			}
		);
	}
}

/* -------------------------------------------------------------------------
 * Ultimate Member — ultimatemember/ultimatemember (master), GPL-2.0-or-later.
 * ----------------------------------------------------------------------- */
function ashna_fixture_um( array $role_data ) {
	$GLOBALS['ashna_fixture_um_roles'] = $role_data;
	$GLOBALS['ashna_fixture_um_mails'] = array();
	if ( ! function_exists( 'UM' ) ) {
		require_once __DIR__ . '/ultimate-member-excerpt.php';
	}
}

function ashna_fixture_remove_roles() {
	foreach ( isset( $GLOBALS['ashna_fixture_roles'] ) ? $GLOBALS['ashna_fixture_roles'] : array() as $role ) {
		remove_role( $role );
	}
}
