<?php
/**
 * Ultimate Member integration.
 *
 * - Account status is respected: inactive, rejected and not-yet-approved accounts cannot
 *   sign in with a code either (same rule UM applies on wp-login.php).
 * - New accounts get the status of their role's registration setting (approved / pending
 *   review). An "awaiting e-mail confirmation" account that proves its e-mail with an OTP
 *   is approved, because the code just confirmed that address.
 * - On UM login pages served by Ashna, users go where the role's "after login" setting says
 *   (profile, URL, wp-admin or the same page) unless a destination was requested.
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Integration_UM {

	/**
	 * Statuses that cannot sign in, with the message shown.
	 */
	const BLOCKED = array(
		'inactive'                    => 'حساب کاربری شما غیرفعال شده است.',
		'awaiting_admin_review'       => 'حساب کاربری شما هنوز توسط مدیر سایت تأیید نشده است. پس از تأیید می‌توانید وارد شوید.',
		'awaiting_email_confirmation' => 'حساب کاربری شما در انتظار تأیید ایمیل است. لینک تأیید را در ایمیل خود باز کنید.',
		'rejected'                    => 'درخواست عضویت شما رد شده است.',
	);

	/**
	 * Whether Ultimate Member is active.
	 *
	 * @return bool
	 */
	public static function active() {
		return function_exists( 'UM' );
	}

	/**
	 * Register hooks (only with Ultimate Member).
	 */
	public static function boot() {
		if ( ! self::active() ) {
			return;
		}
		add_filter( 'ashna_otp_can_login', array( __CLASS__, 'gate' ), 10, 3 );
		add_action( 'ashna_otp_user_registered', array( __CLASS__, 'new_user_status' ), 10, 4 );
		add_filter( 'ashna_otp_default_redirect', array( __CLASS__, 'after_login_url' ), 10, 3 );
	}

	/**
	 * UM account status of a user.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function status( $user_id ) {
		$users = self::users_api();
		if ( $users && method_exists( $users, 'get_status' ) ) {
			return (string) $users->get_status( $user_id );
		}
		return (string) get_user_meta( $user_id, 'account_status', true );
	}

	/**
	 * Refuse sign-ins UM would refuse.
	 *
	 * @param true|WP_Error $allowed Allowed so far.
	 * @param WP_User       $user    User.
	 * @param array         $context Sign-in context.
	 * @return true|WP_Error
	 */
	public static function gate( $allowed, $user, $context ) {
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		$status = self::status( $user->ID );
		if ( 'awaiting_email_confirmation' === $status && self::otp_proved_email( $user, $context ) ) {
			self::approve( $user->ID );
			return $allowed;
		}
		if ( isset( self::BLOCKED[ $status ] ) ) {
			return new WP_Error( 'ashna_um_' . $status, self::BLOCKED[ $status ] );
		}
		return $allowed;
	}

	/**
	 * Give a new account the status of its role's registration setting.
	 *
	 * @param int    $user_id    New user.
	 * @param string $identifier Normalized phone/email.
	 * @param string $type       phone|email.
	 * @param array  $extra      Sign-up data.
	 */
	public static function new_user_status( $user_id, $identifier, $type, $extra ) {
		$registration_status = '';
		if ( function_exists( 'um_fetch_user' ) && function_exists( 'um_user' ) ) {
			um_fetch_user( $user_id );
			$registration_status = (string) um_user( 'status' );
		}
		if ( 'pending' === $registration_status ) {
			$users = self::users_api();
			if ( $users && method_exists( $users, 'set_as_pending' ) ) {
				$users->set_as_pending( $user_id, true );
			} else {
				update_user_meta( $user_id, 'account_status', 'awaiting_admin_review' );
			}
			return;
		}
		// approved, or checkmail: the one-time code already confirmed the phone/e-mail.
		self::approve( $user_id );
	}

	/**
	 * Where UM sends this role after login (only on UM login pages served by Ashna).
	 *
	 * @param string  $redirect Current destination.
	 * @param WP_User $user     User.
	 * @param array   $context  Sign-in context.
	 * @return string
	 */
	public static function after_login_url( $redirect, $user, $context ) {
		if ( empty( $context['form'] ) || 'member-ultimatemember' !== $context['form'] || ! function_exists( 'um_fetch_user' ) || ! function_exists( 'um_user' ) ) {
			return $redirect;
		}
		um_fetch_user( $user->ID );
		switch ( (string) um_user( 'after_login' ) ) {
			case 'redirect_admin':
				return admin_url();
			case 'redirect_url':
				$url = apply_filters( 'um_login_redirect_url', um_user( 'login_redirect_url' ), $user->ID );
				return is_string( $url ) && '' !== $url ? $url : $redirect;
			case 'refresh':
				return $redirect;
			default:
				return function_exists( 'um_user_profile_url' ) ? (string) um_user_profile_url( $user->ID ) : $redirect;
		}
	}

	/**
	 * Whether this sign-in was an OTP sent to the account's own e-mail.
	 *
	 * @param WP_User $user    User.
	 * @param array   $context Sign-in context.
	 * @return bool
	 */
	private static function otp_proved_email( $user, $context ) {
		return isset( $context['method'], $context['identifier'] ) && 'otp' === $context['method']
			&& is_email( $context['identifier'] ) && strtolower( $context['identifier'] ) === strtolower( $user->user_email );
	}

	/**
	 * Approve an account through UM (falls back to the status meta on old UM versions).
	 *
	 * @param int $user_id User ID.
	 */
	private static function approve( $user_id ) {
		$users = self::users_api();
		if ( $users && method_exists( $users, 'approve' ) ) {
			$users->approve( $user_id, true );
			return;
		}
		update_user_meta( $user_id, 'account_status', 'approved' );
	}

	/**
	 * UM()->common()->users() (UM 2.8.7+), or null.
	 *
	 * @return object|null
	 */
	private static function users_api() {
		$um = UM();
		if ( is_object( $um ) && method_exists( $um, 'common' ) ) {
			$common = $um->common();
			if ( is_object( $common ) && method_exists( $common, 'users' ) ) {
				return $common->users();
			}
		}
		return null;
	}
}
