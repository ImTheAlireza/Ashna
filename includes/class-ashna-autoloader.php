<?php
/**
 * Automatic Class & Interface Loader for Ashna
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Autoloader {

	/**
	 * Explicit class-to-relative-path map for fast O(1) lookup
	 *
	 * @var array<string, string>
	 */
	private static $class_map = array(
		'Ashna_Plugin'              => 'includes/class-ashna-plugin.php',
		'Ashna_Activator'           => 'includes/core/class-ashna-activator.php',
		'Ashna_Helper'              => 'includes/core/class-ashna-helper.php',
		'Ashna_Security'            => 'includes/core/class-ashna-security.php',
		'Ashna_Session'             => 'includes/core/class-ashna-session.php',
		'Ashna_IP'                  => 'includes/core/class-ashna-ip.php',
		'Ashna_Rate_Limiter'        => 'includes/core/class-ashna-rate-limiter.php',
		'Ashna_Passkey'             => 'includes/core/class-ashna-passkey.php',
		'Ashna_JWT'                 => 'includes/core/class-ashna-jwt.php',
		'Ashna_Logger'              => 'includes/core/class-ashna-logger.php',
		'Ashna_Auth'                => 'includes/services/class-ashna-auth.php',
		'Ashna_Frontend'            => 'includes/services/class-ashna-frontend.php',
		'Ashna_WooCommerce'         => 'includes/services/class-ashna-woocommerce.php',
		'Ashna_SSO'                 => 'includes/services/class-ashna-sso.php',
		'Ashna_Elementor'           => 'includes/integrations/elementor/class-ashna-elementor.php',
		'Ashna_Integrations'        => 'includes/integrations/class-ashna-integrations.php',
		'Ashna_Integration_Dokan'   => 'includes/integrations/class-ashna-integration-dokan.php',
		'Ashna_Integration_UM'      => 'includes/integrations/class-ashna-integration-um.php',
		'Ashna_Gateway_Interface'   => 'includes/gateways/interface-ashna-gateway.php',
		'Ashna_Abstract_Gateway'    => 'includes/gateways/abstract-ashna-gateway.php',
		'Ashna_Gateway_Manager'     => 'includes/gateways/class-ashna-gateway-manager.php',
		'Ashna_Failover'            => 'includes/gateways/class-ashna-failover.php',
		'Ashna_Gateway_Sandbox'     => 'includes/gateways/class-ashna-gateway-sandbox.php',
		'Ashna_Gateway_Smsir'       => 'includes/gateways/class-ashna-gateway-smsir.php',
		'Ashna_Gateway_Kavenegar'   => 'includes/gateways/class-ashna-gateway-kavenegar.php',
		'Ashna_Gateway_Melipayamak' => 'includes/gateways/class-ashna-gateway-melipayamak.php',
		'Ashna_Gateway_Farazsms'    => 'includes/gateways/class-ashna-gateway-farazsms.php',
		'Ashna_Gateway_Ippanel'     => 'includes/gateways/class-ashna-gateway-ippanel.php',
		'Ashna_Gateway_Bale'        => 'includes/gateways/class-ashna-gateway-bale.php',
		'Ashna_Gateway_Msgway'      => 'includes/gateways/class-ashna-gateway-msgway.php',
		'Ashna_Gateway_Msgway_Messenger' => 'includes/gateways/class-ashna-gateway-msgway-messenger.php',
		'Ashna_Gateway_Email'       => 'includes/gateways/class-ashna-gateway-email.php',
		'Ashna_Admin'               => 'includes/admin/class-ashna-admin.php',
	);

	/**
	 * Register SPL autoloader
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload callback
	 *
	 * @param string $class_name Requested class name.
	 */
	public static function autoload( $class_name ) {
		if ( strpos( $class_name, 'Ashna_' ) !== 0 ) {
			return;
		}

		if ( isset( self::$class_map[ $class_name ] ) ) {
			$file = ASHNA_OTP_PATH . self::$class_map[ $class_name ];
			if ( file_exists( $file ) ) {
				require_once $file;
				return;
			}
		}

		// Dynamic fallback for future classes following WordPress naming conventions
		$slug = strtolower( str_replace( '_', '-', $class_name ) );
		$dirs = array( 'core/', 'services/', 'gateways/', 'admin/', '' );

		foreach ( $dirs as $dir ) {
			$candidate = ASHNA_OTP_PATH . 'includes/' . $dir . 'class-' . $slug . '.php';
			if ( file_exists( $candidate ) ) {
				require_once $candidate;
				return;
			}
		}
	}
}
