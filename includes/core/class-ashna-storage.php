<?php
/**
 * Stable on-disk storage contract across the Ashna rebrand.
 *
 * These private legacy names deliberately remain unchanged. Reusing the original
 * records avoids copying credentials, resetting security limits, invalidating an
 * external object cache, or requiring a destructive database migration.
 *
 * @package Ashna_OTP
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Ashna_Storage {
	const PASSKEYS = '_signa_passkeys';
	const PASSKEY_ID = '_signa_passkey_id';

	/** Resolve branded internal keys to their permanent storage names. */
	public static function key( $key ) {
		if ( 0 === strpos( $key, 'ashna_' ) ) {
			return 'signa_' . substr( $key, 6 );
		}
		return $key;
	}
}
