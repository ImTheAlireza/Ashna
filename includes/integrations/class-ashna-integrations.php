<?php
/** Existing-account bridges; never create vendor roles, subscriptions or approvals. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Ashna_Integrations {
	private static $registered = false;

	public static function register() {
		if ( self::$registered ) { return; }
		self::$registered = true;
		add_filter( 'ashna_authenticate_user', array( __CLASS__, 'authorize' ), 20, 2 );
	}

	public static function enabled( $name ) {
		return (bool) Ashna_Helper::get_option( 'integrate_' . $name, 0 );
	}

	/** Managed signup stays with the original plugin, even when auto_register is on. */
	public static function registration_managed() {
		return ( self::enabled( 'dokan' ) && function_exists( 'dokan_is_user_seller' ) )
			|| ( self::enabled( 'pmpro' ) && function_exists( 'pmpro_login_redirect' ) )
			|| ( self::enabled( 'um' ) && function_exists( 'UM' ) )
			|| ( self::enabled( 'memberpress' ) && class_exists( 'MeprOptions' ) );
	}

	public static function registration_error() {
		return new WP_Error( 'ashna_managed_registration', 'برای ساخت حساب جدید از فرم ثبت‌نام فروشنده یا عضویت سایت استفاده کنید؛ این فرم مخصوص ورود حساب‌های موجود است.' );
	}

	/** Runs for OTP, password and Passkey BEFORE any cookie/session is issued. */
	public static function authorize( $user, $method ) {
		if ( is_wp_error( $user ) || ! $user instanceof WP_User ) { return $user; }
		if ( self::enabled( 'um' ) && function_exists( 'UM' ) ) {
			// Invoke UM's real WP login account-status policy, not a copied status list.
			if ( ! function_exists( 'um_wp_form_errors_hook_logincheck' ) ) {
				return new WP_Error( 'ashna_um_unavailable', 'کنترل وضعیت حساب عضویت در دسترس نیست. با مدیر سایت تماس بگیرید.' );
			}
			$checked = um_wp_form_errors_hook_logincheck( $user );
			if ( is_wp_error( $checked ) ) { return $checked; }
			$options = UM()->options();
			if ( $options->get( 'deny_admin_frontend_login' ) && user_can( $user, 'manage_options' ) ) {
				return new WP_Error( 'ashna_um_admin_denied', 'ورود این حساب از فرم عمومی مجاز نیست.' );
			}
			// UM's original form handlers exit/redirect. Mirror their block matching as
			// JSON-compatible errors instead of executing form submission side effects.
			$emails = array_map( 'trim', explode( "\n", strtolower( (string) $options->get( 'blocked_emails' ) ) ) );
			$email = strtolower( $user->user_email );
			$domain = strrchr( $email, '@' );
			if ( '' !== $email && ( in_array( $email, $emails, true ) || ( $domain && in_array( '*' . $domain, $emails, true ) ) ) ) {
				return new WP_Error( 'ashna_um_blocked_email', 'ورود این حساب مجاز نیست.' );
			}
			$ip = function_exists( 'um_user_ip' ) ? um_user_ip() : Ashna_Helper::get_client_ip();
			foreach ( explode( "\n", (string) $options->get( 'blocked_ips' ) ) as $blocked ) {
				$prefix = str_replace( '*', '', trim( $blocked ) );
				if ( '' !== $prefix && 0 === strpos( $ip, $prefix ) ) {
					return new WP_Error( 'ashna_um_blocked_ip', 'ورود از این نشانی مجاز نیست.' );
				}
			}
		}
		// PMPro/MemberPress expiry governs CONTENT, not the ability to log in and
		// renew. Never mark a plan paid/active or un-suspend a Dokan seller here.
		return $user;
	}

	/** Only automatic destinations; explicit form/admin/custom/referer choices win. */
	public static function redirect( $fallback, $user ) {
		if ( function_exists( 'wc_get_page_permalink' ) && $fallback === wc_get_page_permalink( 'checkout' ) ) { return $fallback; }
		$url = '';
		if ( self::enabled( 'dokan' ) && function_exists( 'dokan_is_user_seller' ) && dokan_is_user_seller( $user->ID ) ) {
			if ( function_exists( 'dokan_is_seller_enabled' ) && dokan_is_seller_enabled( $user->ID ) && function_exists( 'dokan_get_navigation_url' ) ) {
				$url = dokan_get_navigation_url();
			} else {
				$url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
			}
		} elseif ( self::enabled( 'pmpro' ) && function_exists( 'pmpro_login_redirect' ) ) {
			$url = pmpro_login_redirect( '', '', $user );
		} elseif ( self::enabled( 'um' ) && function_exists( 'um_get_core_page' ) ) {
			$url = um_get_core_page( 'account' );
		} elseif ( self::enabled( 'memberpress' ) && class_exists( 'MeprOptions' ) && is_callable( array( 'MeprOptions', 'fetch' ) ) ) {
			// Provisional public-options bridge; needs a licensed version for verification.
			$options = MeprOptions::fetch();
			if ( is_object( $options ) && ! empty( $options->account_page_id ) ) { $url = get_permalink( $options->account_page_id ); }
		}
		return wp_validate_redirect( is_string( $url ) && '' !== $url ? $url : $fallback, home_url( '/' ) );
	}
}
