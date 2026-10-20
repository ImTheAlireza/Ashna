<?php
/**
 * Minimal, strict OpenID Connect ID-token verifier.
 *
 * Deliberately small: one algorithm (RS256), one job (verify a signed JWT against a JWKS and
 * check the claims that matter). No package, no "alg" negotiation, no key confusion.
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_JWT {

	/**
	 * Verify a JWT and return its claims.
	 *
	 * @param string $jwt   Compact JWS.
	 * @param array  $keys  JWKS keys array (`keys` member of the JWKS document).
	 * @param array  $args  {
	 *     @type string $iss   Expected issuer (exact match).
	 *     @type string $aud   Expected audience (client id).
	 *     @type string $nonce Expected nonce, when the flow uses one.
	 *     @type int    $leeway Clock skew allowance in seconds.
	 * }
	 * @return array|WP_Error Claims on success.
	 */
	public static function verify( $jwt, array $keys, array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'iss'    => '',
				'aud'    => '',
				'nonce'  => '',
				'leeway' => 60,
			)
		);

		$parts = explode( '.', (string) $jwt );
		if ( 3 !== count( $parts ) ) {
			return new WP_Error( 'ashna_jwt_shape', 'توکن هویت معتبر نیست.' );
		}

		$header = json_decode( self::b64url_decode( $parts[0] ), true );
		$claims = json_decode( self::b64url_decode( $parts[1] ), true );
		$sig    = self::b64url_decode( $parts[2] );

		if ( ! is_array( $header ) || ! is_array( $claims ) || '' === $sig ) {
			return new WP_Error( 'ashna_jwt_shape', 'توکن هویت معتبر نیست.' );
		}

		// Exactly one algorithm. "none" and HS256 are the two classic bypasses: refuse both by
		// name, not by exclusion, so a future provider cannot widen this by accident.
		$alg = isset( $header['alg'] ) ? (string) $header['alg'] : '';
		if ( 'RS256' !== $alg ) {
			return new WP_Error( 'ashna_jwt_alg', 'الگوریتم امضای توکن پذیرفته نیست.' );
		}

		$kid = isset( $header['kid'] ) ? (string) $header['kid'] : '';

		$public_key = self::public_key_for( $keys, $kid );
		if ( is_wp_error( $public_key ) ) {
			return $public_key;
		}

		$ok = openssl_verify( $parts[0] . '.' . $parts[1], $sig, $public_key, OPENSSL_ALGO_SHA256 );
		if ( 1 !== $ok ) {
			return new WP_Error( 'ashna_jwt_signature', 'امضای توکن هویت معتبر نیست.' );
		}

		$now = time();

		if ( ! empty( $args['iss'] ) && ( ! isset( $claims['iss'] ) || ! is_string( $claims['iss'] ) || $args['iss'] !== $claims['iss'] ) ) {
			return new WP_Error( 'ashna_jwt_iss', 'صادرکنندهٔ توکن هویت مورد انتظار نیست.' );
		}

		if ( '' !== (string) $args['aud'] ) {
			$aud = isset( $claims['aud'] ) ? $claims['aud'] : array();
			$aud = is_array( $aud ) ? $aud : array( $aud );
			$aud = array_map( 'strval', $aud );
			if ( ! in_array( (string) $args['aud'], $aud, true ) ) {
				return new WP_Error( 'ashna_jwt_aud', 'توکن هویت برای این سایت صادر نشده است.' );
			}
		}

		if ( empty( $claims['exp'] ) || (int) $claims['exp'] < ( $now - (int) $args['leeway'] ) ) {
			return new WP_Error( 'ashna_jwt_expired', 'توکن هویت منقضی شده است.' );
		}

		if ( ! empty( $claims['iat'] ) && (int) $claims['iat'] > ( $now + (int) $args['leeway'] ) ) {
			return new WP_Error( 'ashna_jwt_iat', 'زمان توکن هویت معتبر نیست.' );
		}

		if ( ! empty( $claims['nbf'] ) && (int) $claims['nbf'] > ( $now + (int) $args['leeway'] ) ) {
			return new WP_Error( 'ashna_jwt_nbf', 'توکن هویت هنوز معتبر نشده است.' );
		}

		if ( '' !== (string) $args['nonce'] ) {
			if ( ! isset( $claims['nonce'] ) || ! hash_equals( (string) $args['nonce'], (string) $claims['nonce'] ) ) {
				return new WP_Error( 'ashna_jwt_nonce', 'nonce توکن هویت مطابقت ندارد.' );
			}
		}

		if ( empty( $claims['sub'] ) || ! is_scalar( $claims['sub'] ) ) {
			return new WP_Error( 'ashna_jwt_sub', 'شناسهٔ کاربر در توکن هویت نیست.' );
		}

		return $claims;
	}

	/**
	 * Pick the RSA key that signed the token (by `kid`, else the only RSA key present).
	 *
	 * @param array  $keys JWKS keys.
	 * @param string $kid  Key id from the token header.
	 * @return string|WP_Error PEM public key.
	 */
	private static function public_key_for( array $keys, $kid ) {
		$candidates = array();
		foreach ( $keys as $key ) {
			if ( ! is_array( $key ) || empty( $key['kty'] ) || 'RSA' !== $key['kty'] ) {
				continue;
			}
			if ( isset( $key['use'] ) && 'sig' !== $key['use'] ) {
				continue;
			}
			$candidates[] = $key;
		}

		if ( '' !== $kid ) {
			foreach ( $candidates as $key ) {
				if ( isset( $key['kid'] ) && (string) $key['kid'] === $kid ) {
					return self::jwk_to_pem( $key );
				}
			}
			// The provider rotated its keys: the caller refetches the JWKS once and retries.
			return new WP_Error( 'ashna_jwt_kid', 'کلید امضای توکن پیدا نشد.' );
		}

		if ( 1 === count( $candidates ) ) {
			return self::jwk_to_pem( $candidates[0] );
		}

		return new WP_Error( 'ashna_jwt_kid', 'کلید امضای توکن پیدا نشد.' );
	}

	/**
	 * Convert an RSA JWK ({n, e}) into a PEM public key.
	 *
	 * @param array $jwk JWK.
	 * @return string|WP_Error
	 */
	private static function jwk_to_pem( array $jwk ) {
		if ( empty( $jwk['n'] ) || empty( $jwk['e'] ) ) {
			return new WP_Error( 'ashna_jwt_jwk', 'کلید عمومی ارائه‌دهنده ناقص است.' );
		}

		$n = self::b64url_decode( $jwk['n'] );
		$e = self::b64url_decode( $jwk['e'] );
		if ( '' === $n || '' === $e ) {
			return new WP_Error( 'ashna_jwt_jwk', 'کلید عمومی ارائه‌دهنده ناقص است.' );
		}

		$modulus  = self::der_integer( $n );
		$exponent = self::der_integer( $e );

		$rsa_key = self::der( 0x30, $modulus . $exponent ); // SEQUENCE { INTEGER n, INTEGER e }

		// AlgorithmIdentifier: rsaEncryption (1.2.840.113549.1.1.1) + NULL parameters
		$algorithm = self::der( 0x30, "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00" );

		$subject_public_key = self::der( 0x03, "\x00" . $rsa_key ); // BIT STRING, 0 unused bits
		$spki               = self::der( 0x30, $algorithm . $subject_public_key );

		$pem = "-----BEGIN PUBLIC KEY-----\n"
			. chunk_split( base64_encode( $spki ), 64, "\n" ) // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- PEM encoding, not obfuscation.
			. "-----END PUBLIC KEY-----\n";

		$key = openssl_pkey_get_public( $pem );
		if ( false === $key ) {
			return new WP_Error( 'ashna_jwt_jwk', 'کلید عمومی ارائه‌دهنده خوانده نشد.' );
		}

		return $pem;
	}

	/**
	 * DER INTEGER from raw big-endian bytes (leading zero added when the high bit is set).
	 *
	 * @param string $bytes Raw bytes.
	 * @return string
	 */
	private static function der_integer( $bytes ) {
		$bytes = ltrim( $bytes, "\x00" );
		if ( '' === $bytes ) {
			$bytes = "\x00";
		}
		if ( ord( $bytes[0] ) > 0x7f ) {
			$bytes = "\x00" . $bytes;
		}
		return self::der( 0x02, $bytes );
	}

	/**
	 * Wrap bytes in a DER tag with a definite length.
	 *
	 * @param int    $tag   DER tag.
	 * @param string $bytes Payload.
	 * @return string
	 */
	private static function der( $tag, $bytes ) {
		$length = strlen( $bytes );
		if ( $length < 0x80 ) {
			$encoded = chr( $length );
		} else {
			$len_bytes = ltrim( pack( 'N', $length ), "\x00" ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary packing, not obfuscation.
			$encoded   = chr( 0x80 | strlen( $len_bytes ) ) . $len_bytes;
		}
		return chr( $tag ) . $encoded . $bytes;
	}

	/**
	 * Base64url decode (RFC 7515).
	 *
	 * @param string $value Encoded value.
	 * @return string
	 */
	public static function b64url_decode( $value ) {
		$value = strtr( (string) $value, '-_', '+/' );
		$pad   = strlen( $value ) % 4;
		if ( $pad ) {
			$value .= str_repeat( '=', 4 - $pad );
		}
		$decoded = base64_decode( $value, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- JWT decoding, not obfuscation.
		return false === $decoded ? '' : $decoded;
	}
}
