<?php
/**
 * TEST ONLY: use a reserved example hostname in the help screenshot for SSO.
 * Copy to a disposable site's wp-content/mu-plugins/ while capturing help.
 * Do not install this on a real site: SSO callbacks will not point at that site.
 */
add_filter(
	'home_url',
	static function ( $url, $path ) {
		if ( is_string( $path ) && preg_match( '~^/ashna-sso/callback/(google|telegram)/$~', $path ) ) {
			return 'https://demo.example.com' . $path;
		}
		return $url;
	},
	10,
	2
);
