<?php
/**
 * Records WooCommerce feature-compatibility declarations (tests/scenarios.php).
 */

// phpcs:ignoreFile

namespace Automattic\WooCommerce\Utilities;

class FeaturesUtil {
	public static $declared = array();

	public static function declare_compatibility( $feature_id, $plugin_file, $positive_compatibility = true ) {
		self::$declared[ $feature_id ] = array( $positive_compatibility, $plugin_file );
		return true;
	}
}
