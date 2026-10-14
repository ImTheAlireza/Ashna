<?php
/**
 * WooCommerce My Account OTP Login Wrapper Template
 *
 * @package Ashna_OTP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( function_exists( 'do_action' ) ) {
	do_action( 'woocommerce_before_customer_login_form' );
}

$redirect = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo Ashna_Frontend::get_login_form_html(
	array(
		'redirect' => $redirect,
		'context'  => 'wc-myaccount',
	)
);

if ( function_exists( 'do_action' ) ) {
	do_action( 'woocommerce_after_customer_login_form' );
}
