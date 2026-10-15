<?php
/**
 * Integrations with other plugins: one login page for every "log in" link, member login
 * forms (Paid Memberships Pro, Ultimate Member, MemberPress) served by Ashna, per-role
 * destinations after sign-in, Dokan vendors and Ultimate Member account approval.
 *
 * Everything goes through hooks those plugins and WordPress already expose, so nothing
 * here runs unless the related plugin is active.
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Integrations {

	/**
	 * Shortcodes whose guest login form Ashna replaces.
	 */
	const MEMBER_LOGIN_SHORTCODES = array( 'pmpro_login', 'mepr-login-form', 'ultimatemember' );

	/**
	 * Register hooks once WordPress and all plugins are loaded.
	 */
	public static function init() {
		if ( did_action( 'init' ) ) {
			self::boot();
		} else {
			add_action( 'init', array( __CLASS__, 'boot' ), 1 );
		}
	}

	/**
	 * Hook everything up.
	 */
	public static function boot() {
		static $booted = false;
		if ( $booted ) {
			return;
		}
		$booted = true;

		add_filter( 'login_url', array( __CLASS__, 'filter_login_url' ), 99, 3 );
		add_filter( 'register_url', array( __CLASS__, 'filter_register_url' ), 99 );
		add_filter( 'pmpro_login_url', array( __CLASS__, 'filter_login_url' ), 99, 3 );
		add_filter( 'pre_do_shortcode_tag', array( __CLASS__, 'replace_member_login_form' ), 10, 3 );
		add_filter( 'ashna_otp_pinned_redirect', array( __CLASS__, 'role_redirect' ), 10, 2 );

		Ashna_Integration_Dokan::boot();
		Ashna_Integration_UM::boot();
	}

	/**
	 * URL of Ashna's dedicated login page ('' when none is set up).
	 *
	 * @param string $redirect Optional destination after sign-in.
	 * @return string
	 */
	public static function login_page_url( $redirect = '' ) {
		$page_id = absint( Ashna_Helper::get_option( 'standalone_page_id', 0 ) );
		if ( $page_id <= 0 || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}
		$url = (string) get_permalink( $page_id );
		if ( '' !== $url && '' !== (string) $redirect ) {
			$url = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $url );
		}
		return $url;
	}

	/**
	 * Send front-end "log in" links (themes, comments, Paid Memberships Pro, any plugin using
	 * wp_login_url()) to Ashna's login page. wp-admin keeps wp-login.php (session-expired
	 * pop-up and re-authentication need it).
	 *
	 * @param string $login_url    Login URL.
	 * @param string $redirect     Destination after sign-in.
	 * @param bool   $force_reauth Re-authentication requested.
	 * @return string
	 */
	public static function filter_login_url( $login_url, $redirect = '', $force_reauth = false ) {
		if ( $force_reauth || is_admin() || ! Ashna_Helper::get_option( 'login_url_to_ashna', 1 ) ) {
			return $login_url;
		}
		$page = self::login_page_url( $redirect );
		return '' !== $page ? $page : $login_url;
	}

	/**
	 * "Register" links lead to the same page: the first OTP sign-in creates the account.
	 *
	 * @param string $register_url Registration URL.
	 * @return string
	 */
	public static function filter_register_url( $register_url ) {
		if ( is_admin() || ! Ashna_Helper::get_option( 'login_url_to_ashna', 1 ) || ! Ashna_Helper::get_option( 'auto_register', 1 ) ) {
			return $register_url;
		}
		$page = self::login_page_url();
		return '' !== $page ? $page : $register_url;
	}

	/**
	 * Show Ashna's form instead of the guest login form of membership plugins:
	 * [pmpro_login] (Paid Memberships Pro), [mepr-login-form] (MemberPress) and Ultimate
	 * Member login forms ([ultimatemember form_id="…"] whose form mode is "login").
	 * Logged-in visitors and password-reset screens keep the plugin's own output.
	 *
	 * @param false|string $output Short-circuit output.
	 * @param string       $tag    Shortcode tag.
	 * @param array|string $attr   Attributes.
	 * @return false|string
	 */
	public static function replace_member_login_form( $output, $tag, $attr ) {
		if ( false !== $output || ! in_array( $tag, self::MEMBER_LOGIN_SHORTCODES, true ) ) {
			return $output;
		}
		if ( is_user_logged_in() || ! Ashna_Helper::get_option( 'replace_member_forms', 1 ) ) {
			return $output;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only routing of a public page.
		if ( isset( $_GET['action'] ) && in_array( sanitize_key( Ashna_Helper::as_string( wp_unslash( $_GET['action'] ) ) ), array( 'lostpassword', 'reset_pass', 'resetpass', 'rp', 'forgot_password', 'reset' ), true ) ) {
			return $output;
		}
		if ( 'ultimatemember' === $tag ) {
			$form_id = is_array( $attr ) && isset( $attr['form_id'] ) ? absint( $attr['form_id'] ) : 0;
			if ( ! $form_id || 'login' !== get_post_meta( $form_id, '_um_mode', true ) ) {
				return $output;
			}
		}
		$redirect = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( Ashna_Helper::as_string( wp_unslash( $_REQUEST['redirect_to'] ) ) ) : '';
		if ( '' === $redirect && is_array( $attr ) && ! empty( $attr['redirect'] ) ) {
			$redirect = esc_url_raw( $attr['redirect'] );
		}
		// phpcs:enable

		Ashna_Frontend::instance()->enqueue_assets( true );
		return Ashna_Frontend::get_login_form_html(
			array(
				'redirect' => $redirect,
				'context'  => 'member-' . sanitize_key( $tag ),
			)
		);
	}

	/**
	 * Destination pinned for the user's role (Integrations tab).
	 *
	 * @param string  $pinned Already pinned URL.
	 * @param WP_User $user   User.
	 * @return string
	 */
	public static function role_redirect( $pinned, $user ) {
		if ( '' !== (string) $pinned || ! $user instanceof WP_User ) {
			return $pinned;
		}
		$map = Ashna_Helper::get_option( 'role_redirects', array() );
		if ( ! is_array( $map ) ) {
			return $pinned;
		}
		foreach ( (array) $user->roles as $role ) {
			if ( ! empty( $map[ $role ] ) ) {
				return esc_url_raw( $map[ $role ] );
			}
		}
		return $pinned;
	}

	/**
	 * Supported plugins found on this site (for the settings screen).
	 *
	 * @return array<string, array{name: string, active: bool}>
	 */
	public static function detected() {
		return array(
			'woocommerce' => array(
				'name'   => 'ووکامرس',
				'active' => class_exists( 'WooCommerce' ),
			),
			'dokan'       => array(
				'name'   => 'دکان (Dokan)',
				'active' => Ashna_Integration_Dokan::active(),
			),
			'um'          => array(
				'name'   => 'Ultimate Member',
				'active' => Ashna_Integration_UM::active(),
			),
			'pmpro'       => array(
				'name'   => 'Paid Memberships Pro',
				'active' => defined( 'PMPRO_VERSION' ) || function_exists( 'pmpro_login_url' ),
			),
			'memberpress' => array(
				'name'   => 'MemberPress',
				'active' => defined( 'MEPR_VERSION' ) || class_exists( 'MeprUser' ),
			),
			'wcm'         => array(
				'name'   => 'WooCommerce Memberships',
				'active' => function_exists( 'wc_memberships' ),
			),
		);
	}
}
