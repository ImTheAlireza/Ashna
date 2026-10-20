<?php
/**
 * QC 4 — integration with membership plugins and Dokan login.
 *
 * Runs inside a disposable full WordPress with Ashna active (tests/run-full-wp.py integrations).
 * Dokan's Registration class, login/registration redirects and helpers are verbatim excerpts
 * (tests/fixtures/dokan-lite-excerpt.php); WooCommerce, Ultimate Member, Paid Memberships Pro
 * and MemberPress are reproduced by tests/fixtures/third-party.php.
 */

require_once __DIR__ . '/lib/wp-harness.php';
require_once __DIR__ . '/fixtures/third-party.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';

$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
add_filter( 'pre_wp_mail', '__return_true' ); // e-mail codes "delivered" (no mail server in the sandbox)
ashna_t_settings(
	array(
		'active_sms_gateway'       => 'sandbox',
		'mobile_delivery_channel'  => 'sms',
		'login_mode'               => 'phone_and_email',
		'auto_register'            => 1,
		'whitelisted_identifiers'  => '203.0.113.7',
		'captcha_type'             => 'none',
		'redirect_behavior'        => 'home',
		'respect_login_gates'      => 1,
		'respect_plugin_redirects' => 1,
		'require_name_on_register' => 'optional',
		'login_url_to_ashna'       => 1,
		'replace_member_forms'     => 1,
		'dokan_vendor_signup'      => 1,
		'role_redirects'           => array(),
		'standalone_page_id'       => 0,
	)
);

function make_page( $title, $content = '' ) {
	$id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => sanitize_title( $title ) . '-' . wp_rand( 100, 999 ),
			'post_content' => $content,
		)
	);
	Ashna_T::$posts[] = $id;
	return $id;
}

/** Full OTP sign-in/up for a phone or e-mail with extra verify fields. */
function sign_in( $identifier, array $extra = array(), $request = true ) {
	browser_guest();
	if ( $request ) {
		request_code( $identifier );
	}
	$norm = Ashna_Helper::parse_identifier( $identifier )['normalized'];
	$res  = ajax(
		'ashna_verify_otp',
		array_merge(
			array(
				'identifier' => $identifier,
				'code'       => isset( Ashna_T::$codes[ $norm ] ) ? (string) Ashna_T::$codes[ $norm ] : '',
				'nonce'      => wp_create_nonce( 'ashna_otp_nonce' ),
			),
			$extra
		)
	);
	track_user_id( data_of( $res, 'user_id' ) );
	return $res;
}

$login_page = make_page( 'Ashna Login' );

/** What Dokan's own login redirect returns: get_permalink() of the dashboard page (dokan_get_navigation_url() appends a slash, which differs under plain permalinks). */
function dokan_dashboard_permalink() {
	return get_permalink( (int) dokan_get_option( 'dashboard', 'dokan_pages' ) );
}

scenario(
	'Every front-end login link leads to Ashna\'s login page',
	function () use ( $login_page ) {
		$target = home_url( '/members/lesson/' );
		check( 'without a login page nothing changes', false !== strpos( wp_login_url( $target ), 'wp-login.php' ) );

		ashna_t_settings( array( 'standalone_page_id' => $login_page ) );
		$url = wp_login_url( $target );
		check( 'wp_login_url() → Ashna page, destination kept', 0 === strpos( $url, get_permalink( $login_page ) ) && false !== strpos( $url, rawurlencode( $target ) ), $url );
		check( 'wp_registration_url() → Ashna page (first code creates the account)', get_permalink( $login_page ) === wp_registration_url() );
		check( 're-authentication keeps wp-login.php', false !== strpos( wp_login_url( $target, true ), 'wp-login.php' ) );

		$GLOBALS['current_screen'] = WP_Screen::get( 'dashboard' );
		check( 'inside wp-admin keeps wp-login.php (session-expired pop-up)', false !== strpos( wp_login_url(), 'wp-login.php' ) );
		unset( $GLOBALS['current_screen'] );

		ashna_fixture_pmpro();
		check( 'Paid Memberships Pro links (pmpro_login_url) → Ashna page', 0 === strpos( pmpro_login_url( $target ), get_permalink( $login_page ) ) );

		ashna_t_settings( array( 'login_url_to_ashna' => 0 ) );
		check( 'switch off → original URLs', false !== strpos( wp_login_url( $target ), 'wp-login.php' ) && false !== strpos( pmpro_login_url(), 'membership-login' ) );
		ashna_t_settings( array( 'login_url_to_ashna' => 1 ) );
		remove_filter( 'login_redirect', 'pmpro_login_redirect', 10 ); // PMPro redirects are tested on their own below.
	}
);

scenario(
	'Membership login forms show Ashna\'s form to guests',
	function () {
		ashna_fixture_pmpro();
		ashna_fixture_memberpress();
		ashna_fixture_um( array() );
		$um_login    = wp_insert_post( array( 'post_type' => 'um_form', 'post_status' => 'publish', 'post_title' => 'UM login' ) );
		$um_register = wp_insert_post( array( 'post_type' => 'um_form', 'post_status' => 'publish', 'post_title' => 'UM register' ) );
		Ashna_T::$posts[] = $um_login;
		Ashna_T::$posts[] = $um_register;
		update_post_meta( $um_login, '_um_mode', 'login' );
		update_post_meta( $um_register, '_um_mode', 'register' );

		browser_guest();
		$pm = do_shortcode( '[pmpro_login]' );
		check( '[pmpro_login] → Ashna form', false !== strpos( $pm, 'ashna-otp-wrapper' ) && false === strpos( $pm, 'PMPRO-LOGIN' ) && false !== strpos( $pm, 'data-context="member-pmpro_login"' ) );
		check( '[mepr-login-form] → Ashna form', false !== strpos( do_shortcode( '[mepr-login-form]' ), 'ashna-otp-wrapper' ) );
		$um = do_shortcode( '[ultimatemember form_id="' . $um_login . '"]' );
		check( 'Ultimate Member login form → Ashna form (context member-ultimatemember)', false !== strpos( $um, 'data-context="member-ultimatemember"' ) );
		check( 'Ultimate Member registration form is left alone', false !== strpos( do_shortcode( '[ultimatemember form_id="' . $um_register . '"]' ), 'UM-FORM-' . $um_register ) );

		$_GET['action'] = 'reset_pass';
		check( 'password-reset screens keep the plugin\'s own form', false !== strpos( do_shortcode( '[pmpro_login]' ), 'PMPRO-LOGIN' ) );
		unset( $_GET['action'] );

		$user = make_user( 'member_li', 'subscriber' );
		wp_set_current_user( $user->ID );
		check( 'signed-in members see the plugin\'s own box', false !== strpos( do_shortcode( '[pmpro_login]' ), 'PMPRO-LOGIN' ) );
		browser_guest();

		ashna_t_settings( array( 'replace_member_forms' => 0 ) );
		check( 'switch off → plugin forms', false !== strpos( do_shortcode( '[mepr-login-form]' ), 'MEPR-LOGIN' ) );
		ashna_t_settings( array( 'replace_member_forms' => 1 ) );
	}
);

scenario(
	'Destination after sign-in per role',
	function () {
		ashna_t_settings( array( 'role_redirects' => array( 'editor' => home_url( '/editor-desk/' ) ) ) );
		$editor = make_user( 'ed', 'editor', array( 'ashna_phone' => random_phone() ) );
		$res    = sign_in( get_user_meta( $editor->ID, 'ashna_phone', true ) );
		check( 'editor → configured page', home_url( '/editor-desk/' ) === data_of( $res, 'redirect_to' ), data_of( $res, 'redirect_to' ) );
		$sub = make_user( 'sub', 'subscriber', array( 'ashna_phone' => random_phone() ) );
		$res = sign_in( get_user_meta( $sub->ID, 'ashna_phone', true ) );
		check( 'other roles → general setting', home_url( '/' ) === data_of( $res, 'redirect_to' ), data_of( $res, 'redirect_to' ) );
		ashna_t_settings( array( 'role_redirects' => array() ) );
	}
);

// ---- Dokan ---------------------------------------------------------------
ashna_fixture_woocommerce();
$dashboard = make_page( 'Vendor Dashboard' );
ashna_fixture_dokan( $dashboard );
Ashna_Integration_Dokan::boot(); // Dokan "loaded" after Ashna booted its integrations.
$created_sellers = array();
add_action(
	'dokan_new_seller_created',
	function ( $user_id, $settings ) use ( &$created_sellers ) {
		$created_sellers[] = array( $user_id, $settings );
	},
	10,
	2
);

scenario(
	'Dokan: vendor signs up inside the OTP form; Dokan itself creates the store',
	function () use ( $dashboard, &$created_sellers ) {
		$created_sellers = array();
		$phone           = random_phone();
		$slug            = 'test-shop-' . wp_rand( 1000, 9999 );
		$res             = sign_in(
			$phone,
			array(
				'account_type' => 'seller',
				'fname'        => 'سارا',
				'lname'        => 'رضایی',
				'shopname'     => 'گالری سارا',
				'shopurl'      => strtoupper( $slug ) . ' ',
			)
		);
		$uid      = (int) data_of( $res, 'user_id' );
		$user     = get_user_by( 'id', $uid );
		$settings = get_user_meta( $uid, 'dokan_profile_settings', true );
		check( 'sign-up succeeded', ok_json( $res ), $res['raw'] );
		check( 'role: Dokan vendor (seller)', $user && in_array( 'seller', $user->roles, true ) && dokan_is_user_seller( $uid ), $user ? $user->roles : null );
		check( 'store created by Dokan\'s save_vendor_info(): name + verified mobile as store phone', is_array( $settings ) && 'گالری سارا' === $settings['store_name'] && $phone === $settings['phone'], $settings );
		check( 'dokan_store_name meta', 'گالری سارا' === get_user_meta( $uid, 'dokan_store_name', true ) );
		check( 'store URL = normalized slug (user_nicename)', $user && $slug === $user->user_nicename, $user ? $user->user_nicename : null );
		check( 'first/last name set by Dokan', 'سارا' === get_user_meta( $uid, 'first_name', true ) && 'رضایی' === get_user_meta( $uid, 'last_name', true ) );
		check( 'Dokan\'s "enable selling" rule applied on user_register', 'yes' === get_user_meta( $uid, 'dokan_enable_selling', true ) );
		check( 'dokan_new_seller_created fired once', 1 === count( $created_sellers ) && $created_sellers[0][0] === $uid );
		check( 'new vendor → Dokan\'s setup wizard (woocommerce_registration_redirect)', site_url( '?page=dokan-seller-setup' ) === data_of( $res, 'redirect_to' ), data_of( $res, 'redirect_to' ) );
		check( 'request fields restored after Dokan\'s hooks ran', ! isset( $_POST['role'] ) || 'seller' !== $_POST['role'] );
		check( 'Dokan nonce bypass removed again', true === apply_filters( 'dokan_register_nonce_check', true ) );

		$res = sign_in( $phone );
		check( 'returning vendor → vendor dashboard (woocommerce_login_redirect)', dokan_dashboard_permalink() === data_of( $res, 'redirect_to' ), array( dokan_dashboard_permalink(), data_of( $res, 'redirect_to' ) ) );

		$GLOBALS['ashna_fixture_dokan_options']['dokan_selling'] = array( 'disable_welcome_wizard' => 'on' );
		$res = sign_in( random_phone(), array( 'account_type' => 'seller', 'fname' => 'a', 'lname' => 'b', 'shopname' => 'Shop B', 'shopurl' => 'shop-b-' . wp_rand( 1000, 9999 ) ) );
		check( 'setup wizard switched off in Dokan → straight to the dashboard', in_array( data_of( $res, 'redirect_to' ), array( dokan_get_navigation_url(), dokan_dashboard_permalink() ), true ), data_of( $res, 'redirect_to' ) );
		unset( $GLOBALS['ashna_fixture_dokan_options']['dokan_selling'] );

		$res = sign_in( random_phone(), array( 'account_type' => 'customer' ) );
		$c   = get_user_by( 'id', (int) data_of( $res, 'user_id' ) );
		check( 'choosing "buyer" creates a normal customer', $c && array( 'customer' ) === array_values( $c->roles ) );
	}
);

scenario(
	'Dokan: validation happens before the code is used',
	function () {
		$phone = random_phone();
		browser_guest();
		request_code( $phone );
		$code = Ashna_T::$codes[ $phone ];
		$res  = sign_in( $phone, array( 'account_type' => 'seller', 'fname' => 'a', 'lname' => 'b', 'shopname' => '', 'shopurl' => 'x' ), false );
		check( 'missing shop name → field error', 'ashna_vendor_shopname' === data_of( $res, 'code' ) && 'shopname' === data_of( $res, 'field' ), $res['raw'] );

		$taken = make_user( 'takenshop', 'seller' );
		wp_update_user( array( 'ID' => $taken->ID, 'user_nicename' => 'taken-shop' ) );
		$res = sign_in( $phone, array( 'account_type' => 'seller', 'fname' => 'a', 'lname' => 'b', 'shopname' => 'S', 'shopurl' => 'taken-shop' ), false );
		check( 'store URL already used → field error', 'ashna_vendor_shopurl_taken' === data_of( $res, 'code' ), $res['raw'] );

		$res = sign_in( $phone, array( 'account_type' => 'seller', 'fname' => 'a', 'lname' => 'b', 'shopname' => 'S', 'shopurl' => 'free-shop-' . wp_rand( 1000, 9999 ) ), false );
		check( 'the same code still works after fixing the fields', ok_json( $res ) && Ashna_T::$codes[ $phone ] === $code, $res['raw'] );

		$ajax = ajax( 'ashna_dokan_check_shopurl', array( 'shopurl' => 'Taken Shop', 'nonce' => wp_create_nonce( 'ashna_otp_nonce' ) ) );
		check( 'live URL check: normalized + taken', 'taken-shop' === data_of( $ajax, 'slug' ) && false === data_of( $ajax, 'available' ) );
	}
);

scenario(
	'Dokan settings are respected',
	function () {
		$GLOBALS['ashna_fixture_dokan_options']['dokan_appearance']['show_register_as_vendor'] = 'off';
		$res = sign_in( random_phone(), array( 'account_type' => 'seller', 'fname' => 'a', 'lname' => 'b', 'shopname' => 'S', 'shopurl' => 'off-shop-' . wp_rand( 1000, 9999 ) ) );
		check( 'Dokan "show vendor option" off → vendor sign-up refused on normal forms', 'ashna_vendor_disabled' === data_of( $res, 'code' ), $res['raw'] );
		browser_guest();
		$res = sign_in( random_phone(), array( 'account_type' => 'seller', 'vendor_form_nonce' => wp_create_nonce( 'ashna_vendor_form' ), 'fname' => 'a', 'lname' => 'b', 'shopname' => 'S', 'shopurl' => 'page-shop-' . wp_rand( 1000, 9999 ) ) );
		check( '…but allowed on the dedicated vendor page (like Dokan\'s own)', ok_json( $res ), $res['raw'] );
		$GLOBALS['ashna_fixture_dokan_options']['dokan_appearance']['show_register_as_vendor'] = 'on';

		ashna_t_settings( array( 'dokan_vendor_signup' => 0 ) );
		$res = sign_in( random_phone(), array( 'account_type' => 'seller', 'fname' => 'a', 'lname' => 'b', 'shopname' => 'S', 'shopurl' => 'x-' . wp_rand( 1000, 9999 ) ) );
		check( 'Ashna switch off → refused', 'ashna_vendor_disabled' === data_of( $res, 'code' ) );
		ashna_t_settings( array( 'dokan_vendor_signup' => 1 ) );

		$email = 'vendor' . wp_rand( 1000, 9999 ) . '@example.test';
		$res   = sign_in( $email, array( 'account_type' => 'seller', 'fname' => 'a', 'lname' => 'b', 'shopname' => 'Mail Shop', 'shopurl' => 'mail-' . wp_rand( 1000, 9999 ) ) );
		check( 'e-mail sign-up needs the store mobile', 'ashna_vendor_phone' === data_of( $res, 'code' ), $res['raw'] );
		$res      = sign_in( $email, array( 'account_type' => 'seller', 'fname' => 'a', 'lname' => 'b', 'shopname' => 'Mail Shop', 'shopurl' => 'mail-' . wp_rand( 1000, 9999 ), 'phone' => '۰۹۱۲۳۴۵۶۷۸۹' ), false );
		$settings = get_user_meta( (int) data_of( $res, 'user_id' ), 'dokan_profile_settings', true );
		check( '…stored normalized (Persian digits → 09123456789)', ok_json( $res ) && is_array( $settings ) && '09123456789' === $settings['phone'], $res['raw'] );
	}
);

scenario(
	'Dokan: existing vendors and Dokan\'s login redirect',
	function () use ( $login_page ) {
		$phone  = random_phone();
		$vendor = make_user( 'oldvendor', 'seller', array( 'dokan_profile_settings' => array( 'store_name' => 'Old', 'phone' => Ashna_Helper::to_international_phone( $phone, true ) ) ) );
		$res    = sign_in( $phone );
		check( 'vendor whose mobile is only in the store profile signs into that account', ok_json( $res ) && (int) data_of( $res, 'user_id' ) === $vendor->ID, $res['raw'] );

		ashna_t_settings( array( 'standalone_page_id' => $login_page ) );
		$url = apply_filters( 'dokan_redirect_login', home_url( '/my-account/' ) );
		check( 'Dokan sends guests from its dashboard to Ashna\'s page (back to the dashboard after)', 0 === strpos( $url, get_permalink( $login_page ) ) && false !== strpos( $url, rawurlencode( dokan_get_navigation_url() ) ), $url );

		browser_guest();
		$html = Ashna_Frontend::instance()->shortcode_login_form( array( 'vendor' => 'yes' ) );
		check( 'vendor page shortcode renders the vendor form (fixed type + marker)', false !== strpos( $html, 'name="account_type" value="seller"' ) && false !== strpos( $html, 'vendor_form_nonce' ) );
		$normal = Ashna_Frontend::get_login_form_html( array() );
		check( 'normal form offers buyer / vendor', false !== strpos( $normal, 'value="customer" checked' ) && false !== strpos( $normal, 'فروشنده هستم' ) );
	}
);

// ---- Ultimate Member -------------------------------------------------------
ashna_fixture_um(
	array(
		'subscriber' => array( 'status' => 'pending', 'after_login' => 'redirect_url', 'login_redirect_url' => home_url( '/um-welcome/' ) ),
		'customer'   => array( 'status' => 'approved', 'after_login' => 'redirect_profile' ),
		'author'     => array( 'status' => 'checkmail' ),
	)
);
Ashna_Integration_UM::boot();

scenario(
	'Ultimate Member: account status is respected',
	function () {
		$u = make_user( 'umpending', 'customer', array( 'ashna_phone' => random_phone(), 'account_status' => 'awaiting_admin_review' ) );
		$res = sign_in( get_user_meta( $u->ID, 'ashna_phone', true ) );
		check( '"awaiting review" cannot sign in with a code', ! ok_json( $res ) && 'ashna_um_awaiting_admin_review' === data_of( $res, 'code' ) && 0 === get_current_user_id(), $res['raw'] );
		update_user_meta( $u->ID, 'account_status', 'rejected' );
		$res = sign_in( get_user_meta( $u->ID, 'ashna_phone', true ) );
		check( '"rejected" cannot sign in', 'ashna_um_rejected' === data_of( $res, 'code' ) );
		update_user_meta( $u->ID, 'account_status', 'inactive' );
		$res = sign_in( get_user_meta( $u->ID, 'ashna_phone', true ) );
		check( '"inactive" cannot sign in', 'ashna_um_inactive' === data_of( $res, 'code' ) );
		update_user_meta( $u->ID, 'account_status', 'approved' );
		$res = sign_in( get_user_meta( $u->ID, 'ashna_phone', true ) );
		check( '"approved" signs in', ok_json( $res ) );

		$mail = 'umconfirm' . wp_rand( 1000, 9999 ) . '@example.test';
		$c    = make_user( 'umconfirm', 'customer', array( 'ashna_phone' => random_phone(), 'account_status' => 'awaiting_email_confirmation' ) );
		wp_update_user( array( 'ID' => $c->ID, 'user_email' => $mail ) );
		$res = sign_in( get_user_meta( $c->ID, 'ashna_phone', true ) );
		check( '"awaiting e-mail confirmation" + code by SMS → still refused', 'ashna_um_awaiting_email_confirmation' === data_of( $res, 'code' ) );
		$res = sign_in( $mail );
		check( '…code sent to that e-mail proves it → approved and signed in', ok_json( $res ) && 'approved' === get_user_meta( $c->ID, 'account_status', true ), $res['raw'] );
	}
);

scenario(
	'Ultimate Member: new accounts follow the role\'s registration status',
	function () {
		ashna_t_settings( array( 'default_user_role' => 'subscriber' ) );
		$GLOBALS['ashna_fixture_um_mails'] = array();
		$res = sign_in( random_phone() );
		$uid = (int) get_users( array( 'orderby' => 'ID', 'order' => 'DESC', 'number' => 1, 'fields' => 'ID' ) )[0];
		track_user_id( $uid );
		check( 'role requires review → account created as "awaiting review", not signed in', ! ok_json( $res ) && 'awaiting_admin_review' === get_user_meta( $uid, 'account_status', true ), $res['raw'] );
		check( 'user is told why', 'ashna_um_awaiting_admin_review' === data_of( $res, 'code' ) );
		check( 'UM\'s own "pending" notification path used', in_array( 'pending_email', $GLOBALS['ashna_fixture_um_mails'], true ) );

		ashna_t_settings( array( 'default_user_role' => 'customer' ) );
		$res = sign_in( random_phone() );
		check( 'role auto-approves → approved and signed in', ok_json( $res ) && 'approved' === get_user_meta( (int) data_of( $res, 'user_id' ), 'account_status', true ), $res['raw'] );

		ashna_t_settings( array( 'default_user_role' => 'author' ) );
		$res = sign_in( random_phone() );
		check( 'role needs e-mail confirmation → the code already confirmed the contact → approved', ok_json( $res ) && 'approved' === get_user_meta( (int) data_of( $res, 'user_id' ), 'account_status', true ), $res['raw'] );
		ashna_t_settings( array( 'default_user_role' => 'customer' ) );
	}
);

scenario(
	'Ultimate Member: role "after login" on UM login pages',
	function () {
		$sub = make_user( 'umsub', 'subscriber', array( 'ashna_phone' => random_phone(), 'account_status' => 'approved' ) );
		$res = sign_in( get_user_meta( $sub->ID, 'ashna_phone', true ), array( 'context' => 'member-ultimatemember' ) );
		check( 'UM login page: role setting "redirect to URL"', home_url( '/um-welcome/' ) === data_of( $res, 'redirect_to' ), data_of( $res, 'redirect_to' ) );
		$cus = make_user( 'umcus', 'customer', array( 'ashna_phone' => random_phone(), 'account_status' => 'approved' ) );
		$res = sign_in( get_user_meta( $cus->ID, 'ashna_phone', true ), array( 'context' => 'member-ultimatemember' ) );
		check( 'UM login page: "redirect to profile"', um_user_profile_url( $cus->ID ) === data_of( $res, 'redirect_to' ), data_of( $res, 'redirect_to' ) );
		$res = sign_in( get_user_meta( $sub->ID, 'ashna_phone', true ), array( 'context' => 'member-ultimatemember', 'redirect_to' => home_url( '/protected/' ) ) );
		check( 'a requested page wins, as in UM', home_url( '/protected/' ) === data_of( $res, 'redirect_to' ), data_of( $res, 'redirect_to' ) );
		$res = sign_in( get_user_meta( $sub->ID, 'ashna_phone', true ), array( 'context' => 'shortcode' ) );
		check( 'other forms keep Ashna\'s setting', home_url( '/' ) === data_of( $res, 'redirect_to' ), data_of( $res, 'redirect_to' ) );
	}
);

scenario(
	'Paid Memberships Pro through the shared redirect resolver',
	function () {
		ashna_fixture_pmpro();
		add_filter( 'login_redirect', 'pmpro_login_redirect', 10, 3 );
		$sub = get_users( array( 'search' => 'umsub*', 'search_columns' => array( 'user_login' ), 'number' => 1 ) );
		if ( $sub ) {
			$res = sign_in( get_user_meta( $sub[0]->ID, 'ashna_phone', true ), array( 'context' => 'member-ultimatemember' ) );
			check( 'with UM + PMPro both active, a UM login page still uses the UM role destination', home_url( '/um-welcome/' ) === data_of( $res, 'redirect_to' ), data_of( $res, 'redirect_to' ) );
		}
		$member = make_user( 'pmm', 'customer', array( 'ashna_phone' => random_phone(), 'ashna_fixture_pmpro_level' => 1, 'account_status' => 'approved' ) );
		$res    = sign_in( get_user_meta( $member->ID, 'ashna_phone', true ) );
		check( 'member → PMPro account page', home_url( '/membership-account/' ) === data_of( $res, 'redirect_to' ), data_of( $res, 'redirect_to' ) );
		$res = sign_in( random_phone() );
		check( 'brand-new account (no level yet) → PMPro levels page', home_url( '/membership-levels/' ) === data_of( $res, 'redirect_to' ), data_of( $res, 'redirect_to' ) );
		remove_filter( 'login_redirect', 'pmpro_login_redirect', 10 );
	}
);

ashna_fixture_remove_roles();
ashna_t_finish( 'integrations' );
