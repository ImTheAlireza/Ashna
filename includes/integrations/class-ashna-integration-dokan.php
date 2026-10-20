<?php
/**
 * Dokan (multi-vendor marketplace) integration.
 *
 * - Vendors signing in with a code land on their vendor dashboard: Ashna applies the same
 *   `woocommerce_login_redirect` / `woocommerce_registration_redirect` filters Dokan uses on
 *   WooCommerce's own forms (dashboard, or the vendor setup wizard after signing up).
 * - Vendor sign-up inside the OTP form: shop name, shop URL, name; the verified mobile is
 *   the store phone. The account is created through WooCommerce's customer pipeline, so
 *   Dokan's own code (Registration::set_new_vendor_names / save_vendor_info, the
 *   "enable selling" rule on user_register and `dokan_new_seller_created`) sets the vendor up.
 * - Dokan's "please log in" redirect for its dashboard points to Ashna's login page.
 * - Vendors whose mobile only lives in their store profile can sign in with it.
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Missing -- every AJAX handler below calls Ashna_Session::verify_request() first.
class Ashna_Integration_Dokan {

	/**
	 * Nonce action of Ashna's dedicated vendor sign-up form ([ashna_otp_login vendor="yes"]).
	 */
	const VENDOR_FORM_ACTION = 'ashna_vendor_form';

	/**
	 * Request state saved while Dokan's sign-up hooks run.
	 *
	 * @var array|null
	 */
	private static $pending = null;

	/**
	 * Whether Dokan is active.
	 *
	 * @return bool
	 */
	public static function active() {
		return function_exists( 'dokan' ) || function_exists( 'dokan_get_option' );
	}

	/**
	 * Register hooks (only with Dokan).
	 */
	public static function boot() {
		if ( ! self::active() ) {
			return;
		}
		add_filter( 'dokan_redirect_login', array( __CLASS__, 'login_page' ) );
		add_filter( 'ashna_otp_registration_data', array( __CLASS__, 'registration_data' ), 10, 4 );
		add_filter( 'ashna_otp_registration_errors', array( __CLASS__, 'validate' ), 10, 4 );
		add_action( 'ashna_otp_before_register_user', array( __CLASS__, 'before_register' ), 10, 3 );
		add_action( 'ashna_otp_after_register_user', array( __CLASS__, 'after_register' ), 10, 4 );
		add_filter( 'ashna_otp_phone_candidates', array( __CLASS__, 'phone_candidates' ), 10, 2 );
		add_action( 'wp_ajax_nopriv_ashna_dokan_check_shopurl', array( __CLASS__, 'ajax_check_shopurl' ) );
		add_action( 'wp_ajax_ashna_dokan_check_shopurl', array( __CLASS__, 'ajax_check_shopurl' ) );
	}

	/**
	 * Whether visitors may sign up as vendors in this form.
	 *
	 * @param bool $vendor_form True on a dedicated vendor sign-up page (like Dokan's own
	 *                          vendor registration page, it ignores the "show vendor option
	 *                          on the WooCommerce form" setting).
	 * @return bool
	 */
	public static function vendor_signup_allowed( $vendor_form = false ) {
		if ( ! self::active() || ! Ashna_Helper::get_option( 'dokan_vendor_signup', 1 ) || ! Ashna_Helper::get_option( 'auto_register', 1 ) ) {
			return false;
		}
		if ( $vendor_form ) {
			return true;
		}
		return ! function_exists( 'dokan_get_option' ) || 'off' !== dokan_get_option( 'show_register_as_vendor', 'dokan_appearance', 'on' );
	}

	/**
	 * Store URL prefix shown next to the shop URL field.
	 *
	 * @return string
	 */
	public static function store_url_base() {
		$base = function_exists( 'dokan_get_option' ) ? (string) dokan_get_option( 'custom_store_url', 'dokan_general', 'store' ) : 'store';
		return trailingslashit( home_url( '/' . trim( '' !== $base ? $base : 'store', '/' ) ) );
	}

	/**
	 * Dokan sends guests from the vendor dashboard to a login page: use Ashna's.
	 *
	 * @param string $url Login URL.
	 * @return string
	 */
	public static function login_page( $url ) {
		if ( ! Ashna_Helper::get_option( 'login_url_to_ashna', 1 ) ) {
			return $url;
		}
		$dashboard = function_exists( 'dokan_get_navigation_url' ) ? dokan_get_navigation_url() : '';
		$page      = Ashna_Integrations::login_page_url( $dashboard );
		return '' !== $page ? $page : $url;
	}

	/**
	 * Normalize a shop URL slug (Latin letters, digits and hyphens).
	 *
	 * @param string $raw Raw value.
	 * @return string
	 */
	public static function shop_slug( $raw ) {
		$slug = strtolower( remove_accents( sanitize_text_field( Ashna_Helper::as_string( $raw ) ) ) );
		$slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );
		return substr( trim( (string) $slug, '-' ), 0, 50 );
	}

	/**
	 * Read the vendor fields of the verify request.
	 *
	 * @param array  $data       Sign-up data.
	 * @param array  $post       Request.
	 * @param string $identifier Normalized phone/email.
	 * @param string $type       phone|email.
	 * @return array
	 */
	public static function registration_data( $data, $post, $identifier, $type ) {
		if ( ! isset( $post['account_type'] ) || 'seller' !== sanitize_key( $post['account_type'] ) ) {
			return $data;
		}
		$field = static function ( $key ) use ( $post ) {
			return isset( $post[ $key ] ) ? trim( sanitize_text_field( $post[ $key ] ) ) : '';
		};

		$vendor_form = isset( $post['vendor_form_nonce'] ) && false !== wp_verify_nonce( sanitize_key( $post['vendor_form_nonce'] ), self::VENDOR_FORM_ACTION );
		$phone       = 'phone' === $type ? $identifier : Ashna_Helper::normalize_phone( $field( 'phone' ) );

		$data['account_type'] = 'seller';
		$data['vendor']       = array(
			'fname'       => $field( 'fname' ),
			'lname'       => $field( 'lname' ),
			'shopname'    => $field( 'shopname' ),
			'shopurl'     => self::shop_slug( $field( 'shopurl' ) ),
			'phone'       => $phone,
			'vendor_form' => $vendor_form,
		);
		if ( empty( $data['full_name'] ) ) {
			$data['full_name'] = trim( $data['vendor']['fname'] . ' ' . $data['vendor']['lname'] );
		}
		return $data;
	}

	/**
	 * Validate vendor sign-up before the code is checked (Dokan's required fields + unique URL).
	 *
	 * @param null|WP_Error $error      Error so far.
	 * @param array         $data       Sign-up data.
	 * @param string        $identifier Normalized phone/email.
	 * @param string        $type       phone|email.
	 * @return null|WP_Error
	 */
	public static function validate( $error, $data, $identifier, $type ) {
		if ( is_wp_error( $error ) || empty( $data['account_type'] ) || 'seller' !== $data['account_type'] ) {
			return $error;
		}
		$vendor = $data['vendor'];
		if ( ! self::vendor_signup_allowed( ! empty( $vendor['vendor_form'] ) ) ) {
			return new WP_Error( 'ashna_vendor_disabled', 'ثبت‌نام فروشنده در این فرم فعال نیست.' );
		}

		$messages = array(
			'fname'    => 'لطفاً نام خود را وارد کنید.',
			'lname'    => 'لطفاً نام خانوادگی خود را وارد کنید.',
			'phone'    => 'لطفاً شماره موبایل فروشگاه را وارد کنید.',
			'shopname' => 'لطفاً نام فروشگاه را وارد کنید.',
			'shopurl'  => 'لطفاً آدرس فروشگاه را با حروف انگلیسی وارد کنید.',
		);
		// Dokan decides which fields are required (its own filter); Ashna shows Persian messages.
		$required = apply_filters( 'dokan_seller_registration_required_fields', $messages );
		foreach ( (array) $required as $key => $message ) {
			if ( ! array_key_exists( $key, $vendor ) ) {
				continue; // Fields added by other plugins are validated by them.
			}
			if ( '' === (string) $vendor[ $key ] ) {
				return new WP_Error( 'ashna_vendor_' . $key, isset( $messages[ $key ] ) ? $messages[ $key ] : wp_strip_all_tags( (string) $message ), $key );
			}
		}
		if ( 'email' === $type && '' !== $vendor['phone'] && ! Ashna_Helper::is_valid_phone( $vendor['phone'] ) ) {
			return new WP_Error( 'ashna_vendor_phone', 'شماره موبایل فروشگاه معتبر نیست. (مثال: 09123456789)', 'phone' );
		}
		if ( '' !== $vendor['shopurl'] && get_user_by( 'slug', $vendor['shopurl'] ) ) {
			return new WP_Error( 'ashna_vendor_shopurl_taken', 'این آدرس فروشگاه قبلاً ثبت شده است؛ آدرس دیگری انتخاب کنید.', 'shopurl' );
		}
		return $error;
	}

	/**
	 * Let Dokan set up the vendor during the account creation.
	 *
	 * @param string $identifier Normalized phone/email.
	 * @param string $type       phone|email.
	 * @param array  $extra      Sign-up data.
	 */
	public static function before_register( $identifier, $type, $extra ) {
		if ( empty( $extra['account_type'] ) || 'seller' !== $extra['account_type'] || empty( $extra['vendor'] ) ) {
			return;
		}
		$vendor        = $extra['vendor'];
		self::$pending = array(
			'post'   => $_POST, // phpcs:ignore WordPress.Security.NonceVerification.Missing -- restored right after.
			'vendor' => $vendor,
		);

		// Dokan reads its sign-up fields from the request, exactly as on its own forms.
		$_POST['role']     = 'seller';
		$_POST['fname']    = $vendor['fname'];
		$_POST['lname']    = $vendor['lname'];
		$_POST['shopname'] = $vendor['shopname'];
		$_POST['shopurl']  = $vendor['shopurl'];
		$_POST['phone']    = $vendor['phone'];

		// Ashna already verified its form nonce and the one-time code; this documented Dokan
		// switch lets third-party sign-ups skip the WooCommerce register-form nonce.
		add_filter( 'dokan_register_nonce_check', '__return_false', 99 );
	}

	/**
	 * Clean up and make sure the vendor exists even on Dokan versions without those hooks.
	 *
	 * @param WP_User|WP_Error $result     New user.
	 * @param string           $identifier Normalized phone/email.
	 * @param string           $type       phone|email.
	 * @param array            $extra      Sign-up data.
	 */
	public static function after_register( $result, $identifier, $type, $extra ) {
		if ( null === self::$pending ) {
			return;
		}
		$pending       = self::$pending;
		self::$pending = null;
		remove_filter( 'dokan_register_nonce_check', '__return_false', 99 );
		$_POST = $pending['post'];

		if ( ! $result instanceof WP_User ) {
			return;
		}
		$user   = get_user_by( 'id', $result->ID );
		$vendor = $pending['vendor'];
		if ( $user && ! in_array( 'seller', (array) $user->roles, true ) && function_exists( 'dokan_user_update_to_seller' ) ) {
			dokan_user_update_to_seller(
				$user,
				array(
					'fname'    => $vendor['fname'],
					'lname'    => $vendor['lname'],
					'shopname' => $vendor['shopname'],
					'shopurl'  => $vendor['shopurl'],
					'phone'    => $vendor['phone'],
					'address'  => array(),
				)
			);
		}
		update_user_meta( $result->ID, 'ashna_registered_as_vendor', 1 );
	}

	/**
	 * Vendors whose mobile is only stored in their Dokan store profile.
	 *
	 * @param int[]  $ids        Candidates so far.
	 * @param string $identifier Normalized phone.
	 * @return int[]
	 */
	public static function phone_candidates( $ids, $identifier ) {
		$forms = array_unique(
			array(
				$identifier,
				Ashna_Helper::to_international_phone( $identifier, true ),
				Ashna_Helper::to_international_phone( $identifier, false ),
				ltrim( $identifier, '0' ),
			)
		);
		$query = array( 'relation' => 'OR' );
		foreach ( $forms as $form ) {
			$query[] = array(
				'key'     => 'dokan_profile_settings',
				'value'   => sprintf( '"phone";s:%d:"%s";', strlen( $form ), $form ),
				'compare' => 'LIKE',
			);
		}
		$found = get_users(
			array(
				'meta_query'  => $query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'number'      => 5,
				'fields'      => 'ID',
				'count_total' => false,
			)
		);
		return array_merge( (array) $ids, array_map( 'absint', (array) $found ) );
	}

	/**
	 * AJAX: is a shop URL free?
	 */
	public static function ajax_check_shopurl() {
		if ( true !== Ashna_Session::verify_request() ) {
			return;
		}
		$slug = self::shop_slug( isset( $_POST['shopurl'] ) ? wp_unslash( $_POST['shopurl'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- shop_slug() sanitizes.
		Ashna_Session::send_json(
			true,
			array(
				'slug'      => $slug,
				'available' => '' !== $slug && ! get_user_by( 'slug', $slug ),
				'url'       => self::store_url_base() . $slug,
			)
		);
		return; // wp_send_json() ends the request: never continue past a response.
	}
}
// phpcs:enable WordPress.Security.NonceVerification.Missing
