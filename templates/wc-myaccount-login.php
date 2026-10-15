<?php
/**
 * WooCommerce My Account OTP Login Wrapper Template
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( function_exists( 'do_action' ) ) {
	do_action( 'woocommerce_before_customer_login_form' );
}

// Like WooCommerce's own login form: only an explicitly requested page (?redirect_to=) is
// passed on. Otherwise the destination is decided after sign-in, so filters such as Dokan's
// vendor-dashboard redirect (woocommerce_login_redirect) still apply.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only destination of a public form.
$ashna_redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';

$ashna_form_html = Ashna_Frontend::get_login_form_html(
	array(
		'redirect' => $ashna_redirect,
		'context'  => 'wc-myaccount',
	)
);
echo $ashna_form_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside templates/login-form.php.

if ( function_exists( 'do_action' ) ) {
	do_action( 'woocommerce_after_customer_login_form' );
}
