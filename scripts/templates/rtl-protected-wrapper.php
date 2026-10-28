<?php
/**
 * Template used at build time for a licensed, protectable PHP file.
 * The marker is replaced BEFORE encoding; never edit an encoded file.
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
/* ASHNA_LICENSED_CONTENT */
				}
			}
		}
	} catch (\ReflectionException $e) {
		// بررسی لایسنس ناموفق بود.
	}
}

// ----------------------------------------------------------------------------------------------------- End RTL License
