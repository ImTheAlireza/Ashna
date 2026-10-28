<?php
/**
 * License-bound entry to Ashna's services.
 *
 * Submit this file together with the generated protected core and widgets,
 * not ashna.php or the RTL license file, for vendor protection.
 * The callback is invoked after the license-check catch has finished so that
 * unrelated errors from plugin services are not reported as license failures.
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Ashna_License_Guard {

	/**
	 * Return a service-boot callback only for the exact, active RTL license.
	 *
	 * @return callable|null
	 */
	public static function get_boot_callback() {
		// --------------------------------------------------------------------------------------------------- Start RTL License

		$rtlLicenseClassName = 'RTL_License_c1595a7238d64f3a';

		if (
			class_exists($rtlLicenseClassName, false)
			&& method_exists($rtlLicenseClassName, 'isActive')
		) {
			try {
				$rtlLicenseReflection = new \ReflectionClass($rtlLicenseClassName);
				$rtlLicenseFilePath   = $rtlLicenseReflection->getFileName();

				if (is_string($rtlLicenseFilePath) && is_file($rtlLicenseFilePath)) {
					$rtlLicenseFileHash = @sha1_file($rtlLicenseFilePath);

					if (
						is_string($rtlLicenseFileHash)
						&& hash_equals('3fac0395eb82fa65036a1e9aabcf0aac9c2bcedc', $rtlLicenseFileHash)
					) {
						$rtlLicenseClass = new $rtlLicenseClassName();

						if ($rtlLicenseClass->{'isActive'}() === true) {
							// لایسنس فعال است؛ کد امکانات لایسنس‌دار را اینجا قرار دهید.
							return static function () {
								require_once __DIR__ . '/class-ashna-autoloader.php';
								Ashna_Autoloader::register();

								// The distribution core independently checks the vendor license.
								// Its classes do not exist if that second check fails.
								if ( ! class_exists( 'Ashna_Plugin' ) ) {
									return false;
								}

								if ( ! function_exists( 'ashna_otp' ) ) {
									/** @return Ashna_Plugin */
									function ashna_otp() {
										return Ashna_Plugin::instance();
									}
								}

								ashna_otp();
							};
						}
					}
				}
			} catch (\ReflectionException $e) {
				// بررسی لایسنس ناموفق بود.
			}
		}

		// ----------------------------------------------------------------------------------------------------- End RTL License
		return null;
	}
}
