<?php
/**
 * Autoloader for the licensed distribution. The merged runtime checks the
 * official license independently before declaring any functionality class.
 * This file itself is deliberately unencoded; it is not a security boundary.
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Autoloader {
	/** Register the autoloader once. */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/** Load the independently licensed runtime on the first Ashna class. */
	public static function autoload( $class_name ) {
		if ( 0 !== strpos( $class_name, 'Ashna_' ) ) {
			return;
		}

		// A missing runtime leaves the class undefined, allowing the bootstrap to
		// fail closed instead of issuing a fatal require error.
		if ( is_file( __DIR__ . '/class-ashna-protected-core.php' ) && is_readable( __DIR__ . '/class-ashna-protected-core.php' ) ) {
			require_once __DIR__ . '/class-ashna-protected-core.php';
		}
	}
}
