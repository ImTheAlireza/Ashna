<?php
/**
 * Ashna product bootstrap — the single file submitted to RTL-Theme's
 * file-protection (ionCube) service.
 *
 * Why this file and not ashna.php:
 * RTL-Theme's protection rules say the main plugin file — the one that carries
 * the `Plugin Name` header and the license Loader — must NOT be protected, and
 * that only a file without the Loader may be sent. So the layout is:
 *
 *     ashna.php          plain text: header, constants, license Loader (never protected)
 *     ashna-license.php  plain text: license Loader + diagnostics (never protected)
 *     ashna-core.php     THIS FILE: boots the product      -> send to RTL-Theme
 *     RTL_License_*.php  the license file issued for this product
 *
 * The protected file may only reach other files through an explicit path base
 * (RTL-Theme rejects a bare `require 'includes/x.php';`), so every include here
 * is `__DIR__`-based. The file also stays well under the 1 MB per-file limit,
 * uses no short tags and no __halt_compiler().
 *
 * Nothing of the product is registered before this file runs, and the license
 * Loader only requires it after isActive() returned strictly true.
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Licensed product: only the verified gate may start the product. This file is
// encoded, so the guard cannot be read — and a direct `require` of it, without
// the license gate, boots nothing. The license-free build opts out explicitly.
if ( ! defined( 'ASHNA_NO_LICENSE_BUILD' )
	&& ( ! class_exists( 'Ashna_License_Bootstrap', false ) || ! Ashna_License_Bootstrap::granted() ) ) {
	return;
}

require_once __DIR__ . '/includes/class-ashna-autoloader.php';
Ashna_Autoloader::register();

if ( ! function_exists( 'ashna_otp' ) ) {
	/**
	 * Return the plugin kernel.
	 *
	 * @return Ashna_Plugin
	 */
	function ashna_otp() {
		return Ashna_Plugin::instance();
	}
}

ashna_otp();
