<?php
/**
 * W3C WebAuthn / FIDO2 Biometric & Passwordless Passkey Manager for Ashna
 *
 * Supports FaceID, TouchID, Android Biometrics, and Windows Hello without external dependencies.
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Passkey {

	/**
	 * User meta key storing array of passkey credentials
	 */
	const META_CREDENTIALS = '_ashna_passkeys';

	/**
	 * User meta key indexing individual credential IDs for fast lookup
	 */
	const META_CRED_INDEX = '_ashna_passkey_id';

	/**
	 * Constructor: Register AJAX endpoints, shortcode & WooCommerce My Account hooks
	 */
	public function __construct() {
		// Registration endpoints (logged-in users)
		add_action( 'wp_ajax_ashna_passkey_register_options', array( $this, 'ajax_register_options' ) );
		add_action( 'wp_ajax_ashna_passkey_register_verify', array( $this, 'ajax_register_verify' ) );
		add_action( 'wp_ajax_ashna_passkey_delete', array( $this, 'ajax_delete_passkey' ) );

		// Authentication endpoints (guest users & logged-in re-auth)
		add_action( 'wp_ajax_nopriv_ashna_passkey_login_options', array( $this, 'ajax_login_options' ) );
		add_action( 'wp_ajax_ashna_passkey_login_options', array( $this, 'ajax_login_options' ) );
		add_action( 'wp_ajax_nopriv_ashna_passkey_login_verify', array( $this, 'ajax_login_verify' ) );
		add_action( 'wp_ajax_ashna_passkey_login_verify', array( $this, 'ajax_login_verify' ) );

		// Shortcode for managing passkeys anywhere
		add_shortcode( 'ashna_passkey_manager', array( $this, 'render_passkey_manager_shortcode' ) );

		// WooCommerce My Account dashboard integration
		if ( Ashna_Helper::get_option( 'enable_passkey', 1 ) && Ashna_Helper::get_option( 'passkey_wc_myaccount', 1 ) ) {
			add_action( 'woocommerce_account_dashboard', array( $this, 'echo_passkey_manager_box' ), 25 );
		}
	}

	/**
	 * Get Relying Party ID (effective domain name without port or protocol)
	 *
	 * @return string
	 */
	public static function get_rp_id() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return $host ? strtolower( $host ) : 'localhost';
	}

	/**
	 * Base64URL encode binary data
	 *
	 * @param string $data Binary string.
	 * @return string
	 */
	public static function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Base64URL decode to binary string
	 *
	 * @param string $data Base64URL string.
	 * @return string
	 */
	public static function base64url_decode( $data ) {
		$remainder = strlen( $data ) % 4;
		if ( $remainder ) {
			$data .= str_repeat( '=', 4 - $remainder );
		}
		return (string) base64_decode( strtr( $data, '-_', '+/' ) );
	}

	/**
	 * Get all registered Passkeys for a user
	 *
	 * @param int $user_id WordPress User ID.
	 * @return array
	 */
	public static function get_user_passkeys( $user_id ) {
		$list = get_user_meta( $user_id, self::META_CREDENTIALS, true );
		return is_array( $list ) ? $list : array();
	}

	/**
	 * Save a new Passkey credential for a user
	 *
	 * @param int   $user_id    WordPress User ID.
	 * @param array $credential Credential data array.
	 * @return bool
	 */
	public static function save_user_passkey( $user_id, $credential ) {
		if ( empty( $credential['id'] ) || empty( $credential['public_key_pem'] ) ) {
			return false;
		}

		$passkeys = self::get_user_passkeys( $user_id );
		$cred_id  = sanitize_text_field( $credential['id'] );

		// Keep max 8 passkeys per user
		if ( count( $passkeys ) >= 8 && ! isset( $passkeys[ $cred_id ] ) ) {
			array_shift( $passkeys );
		}

		$passkeys[ $cred_id ] = array(
			'id'             => $cred_id,
			'public_key_pem' => $credential['public_key_pem'],
			'alg'            => isset( $credential['alg'] ) ? (int) $credential['alg'] : -7,
			'label'          => ! empty( $credential['label'] ) ? sanitize_text_field( $credential['label'] ) : self::detect_device_label(),
			'created_at'     => current_time( 'mysql' ),
			'last_used'      => current_time( 'mysql' ),
		);

		update_user_meta( $user_id, self::META_CREDENTIALS, $passkeys );

		// Ensure index meta exists for O(1) lookup by credential ID
		$indexed = get_user_meta( $user_id, self::META_CRED_INDEX, false );
		if ( ! is_array( $indexed ) || ! in_array( $cred_id, $indexed, true ) ) {
			add_user_meta( $user_id, self::META_CRED_INDEX, $cred_id, false );
		}

		return true;
	}

	/**
	 * Delete a specific Passkey for a user
	 *
	 * @param int    $user_id WordPress User ID.
	 * @param string $cred_id Base64URL Credential ID.
	 * @return bool
	 */
	public static function delete_user_passkey( $user_id, $cred_id ) {
		$passkeys = self::get_user_passkeys( $user_id );
		if ( ! isset( $passkeys[ $cred_id ] ) ) {
			return false;
		}

		unset( $passkeys[ $cred_id ] );
		update_user_meta( $user_id, self::META_CREDENTIALS, $passkeys );
		delete_user_meta( $user_id, self::META_CRED_INDEX, $cred_id );
		return true;
	}

	/**
	 * Find WordPress User by Credential ID
	 *
	 * @param string $cred_id Base64URL Credential ID.
	 * @return WP_User|null
	 */
	public static function find_user_by_credential_id( $cred_id ) {
		$cred_id = sanitize_text_field( $cred_id );
		if ( empty( $cred_id ) ) {
			return null;
		}

		$users = get_users(
			array(
				'meta_key'   => self::META_CRED_INDEX,
				'meta_value' => $cred_id,
				'number'     => 1,
			)
		);

		if ( ! empty( $users[0] ) && $users[0] instanceof WP_User ) {
			return $users[0];
		}

		return null;
	}

	/**
	 * Detect friendly Persian device label from User-Agent
	 *
	 * @return string
	 */
	public static function detect_device_label() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( stripos( $ua, 'iPhone' ) !== false || stripos( $ua, 'iPad' ) !== false ) {
			return 'آیفون / آیپد (FaceID / TouchID)';
		}
		if ( stripos( $ua, 'Macintosh' ) !== false || stripos( $ua, 'Mac OS' ) !== false ) {
			return 'مک‌بوک اپل (TouchID)';
		}
		if ( stripos( $ua, 'Android' ) !== false ) {
			return 'گوشی اندروید (اثر انگشت / چهره)';
		}
		if ( stripos( $ua, 'Windows' ) !== false ) {
			return 'ویندوز (Windows Hello)';
		}
		return 'دستگاه بیومتریک من';
	}

	/**
	 * AJAX: Generate WebAuthn Registration Options for logged-in user
	 */
	public function ajax_register_options() {
		check_ajax_referer( 'ashna_otp_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'برای ثبت کلید بیومتریک ابتدا وارد حساب کاربری خود شوید.' ) );
		}

		$user      = wp_get_current_user();
		$challenge = self::base64url_encode( random_bytes( 32 ) );

		set_transient( 'ashna_pk_reg_' . $user->ID, $challenge, 120 );

		$existing = self::get_user_passkeys( $user->ID );
		$exclude  = array();
		foreach ( $existing as $cred_id => $item ) {
			$exclude[] = array(
				'id'   => $cred_id,
				'type' => 'public-key',
			);
		}

		$phone        = get_user_meta( $user->ID, 'ashna_phone', true );
		$display_name = ! empty( $user->display_name ) ? $user->display_name : ( $phone ? $phone : $user->user_login );

		wp_send_json_success(
			array(
				'rp'               => array(
					'name' => get_bloginfo( 'name' ),
					'id'   => self::get_rp_id(),
				),
				'user'             => array(
					'id'          => self::base64url_encode( 'ashna_u_' . $user->ID ),
					'name'        => $phone ? $phone : $user->user_login,
					'displayName' => $display_name,
				),
				'challenge'        => $challenge,
				'pubKeyCredParams' => array(
					array(
						'type' => 'public-key',
						'alg'  => -7, // ES256 (ECDSA P-256 with SHA-256)
					),
					array(
						'type' => 'public-key',
						'alg'  => -257, // RS256 (RSASSA-PKCS1-v1_5 with SHA-256)
					),
				),
				'timeout'          => 60000,
				'excludeCredentials' => $exclude,
				'authenticatorSelection' => array(
					'residentKey'      => 'preferred',
					'userVerification' => 'preferred',
				),
				'attestation'      => 'none',
			)
		);
	}

	/**
	 * AJAX: Verify WebAuthn Registration & Save User's Public Key
	 */
	public function ajax_register_verify() {
		check_ajax_referer( 'ashna_otp_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'نشست کاربری شما منقضی شده است.' ) );
		}

		$user              = wp_get_current_user();
		$expected_challenge = get_transient( 'ashna_pk_reg_' . $user->ID );
		delete_transient( 'ashna_pk_reg_' . $user->ID );

		if ( empty( $expected_challenge ) ) {
			wp_send_json_error( array( 'message' => 'فرصت ثبت کلید بیومتریک به پایان رسید. لطفاً دوباره تلاش کنید.' ) );
		}

		$cred_id         = isset( $_POST['credential_id'] ) ? sanitize_text_field( wp_unslash( $_POST['credential_id'] ) ) : '';
		$client_data_b64 = isset( $_POST['client_data_json'] ) ? sanitize_text_field( wp_unslash( $_POST['client_data_json'] ) ) : '';
		$auth_data_b64   = isset( $_POST['authenticator_data'] ) ? sanitize_text_field( wp_unslash( $_POST['authenticator_data'] ) ) : '';
		$spki_b64        = isset( $_POST['public_key_spki'] ) ? sanitize_text_field( wp_unslash( $_POST['public_key_spki'] ) ) : '';
		$alg             = isset( $_POST['public_key_alg'] ) ? (int) $_POST['public_key_alg'] : -7;

		if ( empty( $cred_id ) || empty( $client_data_b64 ) || empty( $spki_b64 ) ) {
			wp_send_json_error( array( 'message' => 'اطلاعات کلید بیومتریک از مرورگر دریافت نشد.' ) );
		}

		$client_data_raw = self::base64url_decode( $client_data_b64 );
		$client_data     = json_decode( $client_data_raw, true );

		if ( ! is_array( $client_data ) || empty( $client_data['type'] ) || 'webauthn.create' !== $client_data['type'] ) {
			wp_send_json_error( array( 'message' => 'پاسخ WebAuthn نامعتبر است.' ) );
		}

		if ( empty( $client_data['challenge'] ) || ! hash_equals( (string) $expected_challenge, (string) $client_data['challenge'] ) ) {
			wp_send_json_error( array( 'message' => 'چالش امنیتی ثبت Passkey تطابق ندارد.' ) );
		}

		$origin_host = isset( $client_data['origin'] ) ? strtolower( (string) wp_parse_url( $client_data['origin'], PHP_URL_HOST ) ) : '';
		$rp_id       = self::get_rp_id();
		if ( $origin_host !== $rp_id ) {
			wp_send_json_error( array( 'message' => 'دامنه مبدا درخواست با دامنه سایت همخوانی ندارد.' ) );
		}

		if ( ! empty( $auth_data_b64 ) ) {
			$auth_data_bin = self::base64url_decode( $auth_data_b64 );
			if ( strlen( $auth_data_bin ) < 37 ) {
				wp_send_json_error( array( 'message' => 'ساختار داده احراز هویت معتبر نیست.' ) );
			}
			$rp_id_hash = substr( $auth_data_bin, 0, 32 );
			if ( ! hash_equals( hash( 'sha256', $rp_id, true ), $rp_id_hash ) ) {
				wp_send_json_error( array( 'message' => 'شناسه دامنه کلید امنیتی معتبر نیست.' ) );
			}
		}

		// Convert SPKI DER to standard PEM Public Key & validate with OpenSSL
		$spki_der = self::base64url_decode( $spki_b64 );
		$pem_key  = "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $spki_der ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";

		if ( function_exists( 'openssl_pkey_get_public' ) ) {
			$pkey_res = @openssl_pkey_get_public( $pem_key );
			if ( false === $pkey_res ) {
				wp_send_json_error( array( 'message' => 'کلید عمومی تولیدشده توسط دستگاه قابل تایید نیست.' ) );
			}
		}

		self::save_user_passkey(
			$user->ID,
			array(
				'id'             => $cred_id,
				'public_key_pem' => $pem_key,
				'alg'            => $alg,
				'label'          => self::detect_device_label(),
			)
		);

		wp_send_json_success(
			array(
				'message' => 'ورود بیومتریک (اثر انگشت / چهره) برای این دستگاه با موفقیت فعال شد!',
				'label'   => self::detect_device_label(),
				'cred_id' => $cred_id,
			)
		);
	}

	/**
	 * AJAX: Delete a Passkey for the current user
	 */
	public function ajax_delete_passkey() {
		check_ajax_referer( 'ashna_otp_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		$cred_id = isset( $_POST['credential_id'] ) ? sanitize_text_field( wp_unslash( $_POST['credential_id'] ) ) : '';
		if ( empty( $cred_id ) || ! self::delete_user_passkey( get_current_user_id(), $cred_id ) ) {
			wp_send_json_error( array( 'message' => 'کلید مورد نظر یافت نشد.' ) );
		}

		wp_send_json_success( array( 'message' => 'دستگاه بیومتریک حذف شد.' ) );
	}

	/**
	 * AJAX: Generate WebAuthn Login Challenge (Assertion Options)
	 */
	public function ajax_login_options() {
		check_ajax_referer( 'ashna_otp_nonce', 'nonce' );

		if ( ! Ashna_Helper::get_option( 'enable_passkey', 1 ) ) {
			wp_send_json_error( array( 'message' => 'ورود بیومتریک در حال حاضر غیرفعال است.' ) );
		}

		$ip        = Ashna_Helper::get_client_ip();
		$challenge = self::base64url_encode( random_bytes( 32 ) );
		$token_key = 'ashna_pk_auth_' . md5( $ip . '_' . $challenge );

		set_transient( $token_key, $challenge, 120 );

		// Optional: if user typed their phone/email in the form, scope allowCredentials
		$allow_credentials = array();
		$raw_identifier    = isset( $_POST['identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['identifier'] ) ) : '';
		if ( ! empty( $raw_identifier ) ) {
			$parsed = Ashna_Helper::parse_identifier( $raw_identifier );
			if ( 'invalid' !== $parsed['type'] ) {
				$user = Ashna_Auth::find_user( $parsed['normalized'], $parsed['type'] );
				if ( $user ) {
					$passkeys = self::get_user_passkeys( $user->ID );
					foreach ( $passkeys as $cid => $pk ) {
						$allow_credentials[] = array(
							'id'   => $cid,
							'type' => 'public-key',
						);
					}
				}
			}
		}

		wp_send_json_success(
			array(
				'challenge'        => $challenge,
				'rpId'             => self::get_rp_id(),
				'timeout'          => 60000,
				'userVerification' => 'preferred',
				'allowCredentials' => $allow_credentials,
			)
		);
	}

	/**
	 * AJAX: Verify WebAuthn Cryptographic Assertion & Log User In
	 */
	public function ajax_login_verify() {
		check_ajax_referer( 'ashna_otp_nonce', 'nonce' );

		if ( ! Ashna_Helper::get_option( 'enable_passkey', 1 ) ) {
			wp_send_json_error( array( 'message' => 'ورود بیومتریک غیرفعال است.' ) );
		}

		$cred_id         = isset( $_POST['credential_id'] ) ? sanitize_text_field( wp_unslash( $_POST['credential_id'] ) ) : '';
		$client_data_b64 = isset( $_POST['client_data_json'] ) ? sanitize_text_field( wp_unslash( $_POST['client_data_json'] ) ) : '';
		$auth_data_b64   = isset( $_POST['authenticator_data'] ) ? sanitize_text_field( wp_unslash( $_POST['authenticator_data'] ) ) : '';
		$signature_b64   = isset( $_POST['signature'] ) ? sanitize_text_field( wp_unslash( $_POST['signature'] ) ) : '';
		$redirect_to     = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';

		if ( empty( $cred_id ) || empty( $client_data_b64 ) || empty( $auth_data_b64 ) || empty( $signature_b64 ) ) {
			wp_send_json_error( array( 'message' => 'اطلاعات تایید بیومتریک ناقص است.' ) );
		}

		$client_data_raw = self::base64url_decode( $client_data_b64 );
		$client_data     = json_decode( $client_data_raw, true );

		if ( ! is_array( $client_data ) || empty( $client_data['type'] ) || 'webauthn.get' !== $client_data['type'] ) {
			wp_send_json_error( array( 'message' => 'نوع پاسخ WebAuthn معتبر نیست.' ) );
		}

		$challenge = isset( $client_data['challenge'] ) ? sanitize_text_field( $client_data['challenge'] ) : '';
		$ip        = Ashna_Helper::get_client_ip();
		$token_key = 'ashna_pk_auth_' . md5( $ip . '_' . $challenge );
		$saved_ch  = get_transient( $token_key );
		delete_transient( $token_key );

		if ( empty( $saved_ch ) || ! hash_equals( (string) $saved_ch, (string) $challenge ) ) {
			wp_send_json_error( array( 'message' => 'چالش امنیتی منقضی شده است. لطفاً دوباره دکمه ورود بیومتریک را بزنید.' ) );
		}

		$origin_host = isset( $client_data['origin'] ) ? strtolower( (string) wp_parse_url( $client_data['origin'], PHP_URL_HOST ) ) : '';
		$rp_id       = self::get_rp_id();
		if ( $origin_host !== $rp_id ) {
			wp_send_json_error( array( 'message' => 'دامنه درخواست نامعتبر است.' ) );
		}

		$auth_data_bin = self::base64url_decode( $auth_data_b64 );
		if ( strlen( $auth_data_bin ) < 37 ) {
			wp_send_json_error( array( 'message' => 'داده احراز هویت نامعتبر است.' ) );
		}

		$rp_id_hash = substr( $auth_data_bin, 0, 32 );
		if ( ! hash_equals( hash( 'sha256', $rp_id, true ), $rp_id_hash ) ) {
			wp_send_json_error( array( 'message' => 'شناسه دامنه کلید امنیتی مطابقت ندارد.' ) );
		}

		// Check User Present (UP) flag (bit 0)
		$flags = ord( $auth_data_bin[32] );
		if ( 0 === ( $flags & 0x01 ) ) {
			wp_send_json_error( array( 'message' => 'حضور کاربر توسط سنسور بیومتریک تایید نشد.' ) );
		}

		// Locate user owning this Credential ID
		$user = self::find_user_by_credential_id( $cred_id );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => 'هیچ حساب کاربری با این کلید بیومتریک در سایت یافت نشد. لطفاً یک‌بار با کد تایید وارد شوید و Passkey را ثبت کنید.' ) );
		}

		$passkeys = self::get_user_passkeys( $user->ID );
		if ( empty( $passkeys[ $cred_id ]['public_key_pem'] ) ) {
			wp_send_json_error( array( 'message' => 'کلید عمومی این دستگاه در حساب شما یافت نشد.' ) );
		}

		$pem_key       = $passkeys[ $cred_id ]['public_key_pem'];
		$signature_bin = self::base64url_decode( $signature_b64 );
		$signed_data   = $auth_data_bin . hash( 'sha256', $client_data_raw, true );

		if ( function_exists( 'openssl_verify' ) ) {
			$verified = @openssl_verify( $signed_data, $signature_bin, $pem_key, OPENSSL_ALGO_SHA256 );
			if ( 1 !== $verified ) {
				wp_send_json_error( array( 'message' => 'امضای دیجیتال بیومتریک تایید نشد.' ) );
			}
		}

		// Update last_used timestamp
		$passkeys[ $cred_id ]['last_used'] = current_time( 'mysql' );
		update_user_meta( $user->ID, self::META_CREDENTIALS, $passkeys );

		// Log successful Passkey login in Ashna Logs
		$phone      = get_user_meta( $user->ID, 'ashna_phone', true );
		$identifier = $phone ? $phone : $user->user_email;
		Ashna_Logger::insert(
			array(
				'recipient'        => $identifier ? $identifier : $user->user_login,
				'channel'          => 'passkey',
				'gateway'          => 'webauthn',
				'otp_code'         => 'PASSKEY',
				'status'           => 'verified',
				'response_message' => 'ورود بیومتریک موفق (' . ( isset( $passkeys[ $cred_id ]['label'] ) ? $passkeys[ $cred_id ]['label'] : 'Passkey' ) . ')',
			)
		);

		// Log the user into WordPress
		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		do_action( 'wp_login', $user->user_login, $user );

		// Resolve redirect URL
		$behavior = Ashna_Helper::get_option( 'redirect_behavior', 'auto' );
		if ( 'custom' === $behavior && ! empty( Ashna_Helper::get_option( 'custom_redirect_url' ) ) ) {
			$final_redirect = Ashna_Helper::get_option( 'custom_redirect_url' );
		} elseif ( 'my_account' === $behavior && function_exists( 'wc_get_page_permalink' ) ) {
			$final_redirect = wc_get_page_permalink( 'myaccount' );
		} elseif ( 'home' === $behavior ) {
			$final_redirect = home_url( '/' );
		} elseif ( ! empty( $redirect_to ) ) {
			$final_redirect = $redirect_to;
		} else {
			$final_redirect = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
		}

		wp_send_json_success(
			array(
				'message'  => sprintf( 'خوش آمدید %s! ورود بیومتریک انجام شد...', $user->display_name ),
				'redirect' => $final_redirect,
			)
		);
	}

	/**
	 * Output Passkey Manager Box in WooCommerce My Account Dashboard
	 */
	public function echo_passkey_manager_box() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->render_passkey_manager_shortcode();
	}

	/**
	 * Shortcode `[ashna_passkey_manager]` to manage user's registered Passkeys
	 *
	 * @return string
	 */
	public function render_passkey_manager_shortcode() {
		if ( ! is_user_logged_in() || ! Ashna_Helper::get_option( 'enable_passkey', 1 ) ) {
			return '';
		}

		if ( class_exists( 'Ashna_Frontend' ) ) {
			Ashna_Frontend::instance()->enqueue_assets( true );
		}

		$user_id  = get_current_user_id();
		$passkeys = self::get_user_passkeys( $user_id );

		ob_start();
		?>
		<div class="ashna-passkey-account-card" dir="rtl">
			<div class="ashna-pk-card-head">
				<div class="ashna-pk-card-title">
					<span class="ashna-pk-badge-icon">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12C2 6.5 6.5 2 12 2a10 10 0 0 1 8 4"/><path d="M5 19.5C5.5 18 6 15 6 12c0-.7.12-1.37.34-2"/><path d="M17.29 21.02c.12-.6.43-2.3.5-3.02"/><path d="M12 10a2 2 0 0 0-2 2c0 1.02-.1 2.51-.26 4"/><path d="M8.65 22c.21-.66.45-1.32.57-2"/><path d="M14 13.12c0 2.38 0 6.38-1 8.88"/><path d="M2 16h.01"/><path d="M21.8 16c.2-2 .131-5.354 0-6"/><path d="M9 6.8a6 6 0 0 1 9 5.2c0 .47 0 1.17-.02 2"/></svg>
					</span>
					<div>
						<h4>ورود بیومتریک و بدون رمز (Passkey)</h4>
						<p>با ثبت اثر انگشت یا تشخیص چهره، دفعات بعد در ۱ ثانیه و بدون نیاز به پیامک وارد شوید.</p>
					</div>
				</div>
				<button type="button" class="ashna-btn-register-passkey">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
					<span>افزودن این دستگاه</span>
				</button>
			</div>

			<div class="ashna-pk-msg" style="display:none;"></div>

			<?php if ( ! empty( $passkeys ) ) : ?>
				<div class="ashna-pk-list">
					<?php foreach ( $passkeys as $cid => $pk ) : ?>
						<div class="ashna-pk-item" data-cred-id="<?php echo esc_attr( $cid ); ?>">
							<div class="ashna-pk-item-info">
								<strong><?php echo esc_html( $pk['label'] ); ?></strong>
								<small>ثبت‌شده در: <span dir="ltr"><?php echo esc_html( $pk['created_at'] ); ?></span></small>
							</div>
							<button type="button" class="ashna-pk-delete-btn" data-cred-id="<?php echo esc_attr( $cid ); ?>">حذف</button>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="ashna-pk-empty">هنوز هیچ دستگاه بیومتریکی برای حساب شما ثبت نشده است.</p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
