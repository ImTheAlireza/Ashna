<?php
/**
 * TEST ONLY — set Ashna settings for a load phase (CLI, inside the sandbox site).
 *
 * usage: php set-settings.php "attack_mode_threshold=0,resend_cooldown=0"
 *        php set-settings.php --show
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Load WordPress first (see tests/load/load-multiproc.py).\n";
	exit( 1 );
}

$arg = isset( $argv[1] ) ? (string) $argv[1] : '';

if ( '--show' === $arg ) {
	$settings = get_option( 'ashna_otp_settings', array() );
	echo wp_json_encode( array_intersect_key( (array) $settings, array_flip( array(
		'active_sms_gateway', 'show_debug_code_in_toast', 'attack_mode_threshold', 'resend_cooldown',
		'max_requests_per_hour', 'max_requests_per_day', 'max_ip_requests_per_minute', 'max_ip_requests_per_hour',
		'max_subnet_requests_per_minute', 'daily_send_cap', 'max_verify_attempts', 'lockout_duration', 'captcha_type',
	) ) ) ), "\n";
	exit( 0 );
}

$settings = get_option( 'ashna_otp_settings', array() );
$settings = is_array( $settings ) ? $settings : array();
$applied  = array();
foreach ( explode( ',', $arg ) as $pair ) {
	if ( '' === trim( $pair ) || false === strpos( $pair, '=' ) ) {
		continue;
	}
	list( $key, $value ) = explode( '=', trim( $pair ), 2 );
	$key                 = preg_replace( '/[^a-z0-9_]/', '', $key );
	if ( '' === $key ) {
		continue;
	}
	$settings[ $key ] = is_numeric( $value ) ? (int) $value : $value;
	$applied[ $key ]  = $settings[ $key ];
}
update_option( 'ashna_otp_settings', $settings );
wp_cache_flush();
echo wp_json_encode( $applied ), "\n";
