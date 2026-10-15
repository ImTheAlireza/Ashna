<?php
/**
 * Client IP resolution that cannot be spoofed with request headers.
 *
 * Proxy headers (CF-Connecting-IP, X-Forwarded-For, AR-Real-IP, X-Real-IP) are read only
 * when the TCP peer (REMOTE_ADDR) is a proxy the site actually sits behind: Cloudflare's
 * published edge ranges, a private/loopback address (local reverse proxy or load balancer)
 * or a range the site owner listed (e.g. ArvanCloud). Anyone else can put any IP in those
 * headers, so for them the peer address is used — rotating fake headers no longer resets
 * per-IP limits.
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_IP {

	/**
	 * Cloudflare edge ranges (https://www.cloudflare.com/ips/).
	 */
	const CLOUDFLARE = array(
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
		'2400:cb00::/32',
		'2606:4700::/32',
		'2803:f800::/32',
		'2405:b500::/32',
		'2405:8100::/32',
		'2a06:98c0::/29',
		'2c0f:f248::/32',
	);

	/**
	 * Private, loopback and link-local ranges (a proxy on the same host or network).
	 */
	const PRIVATE_RANGES = array(
		'10.0.0.0/8',
		'172.16.0.0/12',
		'192.168.0.0/16',
		'127.0.0.0/8',
		'100.64.0.0/10',
		'169.254.0.0/16',
		'::1/128',
		'fc00::/7',
		'fe80::/10',
	);

	/**
	 * Request headers proxies use to pass the visitor address.
	 */
	const PROXY_HEADERS = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_AR_REAL_IP', 'HTTP_X_REAL_IP' );

	/**
	 * The visitor's IP address.
	 *
	 * @return string
	 */
	public static function client() {
		$remote = self::valid( self::server( 'REMOTE_ADDR' ) );
		if ( '' === $remote ) {
			$remote = '127.0.0.1';
		}

		$ip = $remote;
		if ( Ashna_Helper::get_option( 'trust_proxy_headers', 1 ) && self::is_trusted_proxy( $remote ) ) {
			$ip = self::from_proxy_headers( $remote );
		} elseif ( self::has_proxy_headers() && ! self::is_private( $remote ) ) {
			self::note_untrusted_proxy( $remote );
		}

		/**
		 * Filters the resolved visitor IP.
		 *
		 * @param string $ip     Resolved IP.
		 * @param string $remote TCP peer address (REMOTE_ADDR).
		 */
		$filtered = apply_filters( 'ashna_otp_client_ip', $ip, $remote );
		$filtered = self::valid( $filtered );
		return '' !== $filtered ? $filtered : $ip;
	}

	/**
	 * Visitor IP as reported by a trusted proxy.
	 *
	 * @param string $remote Trusted TCP peer.
	 * @return string
	 */
	private static function from_proxy_headers( $remote ) {
		// Cloudflare overwrites CF-Connecting-IP itself, so it is authoritative when the peer is Cloudflare.
		if ( self::is_cloudflare( $remote ) ) {
			$cf = self::valid( self::server( 'HTTP_CF_CONNECTING_IP' ) );
			if ( '' !== $cf ) {
				return $cf;
			}
		}

		// A CDN edge listed by the site owner (e.g. ArvanCloud) sends its own visitor header.
		if ( ! self::is_private( $remote ) ) {
			$arvan = self::valid( self::server( 'HTTP_AR_REAL_IP' ) );
			if ( '' !== $arvan ) {
				return $arvan;
			}
		}

		// Every proxy appends the address it received the request from: walk the chain from the
		// closest hop and stop at the first address that is not one of our proxies. Entries left
		// of that point were written by the client and are ignored.
		$chain = self::forwarded_chain();
		if ( ! empty( $chain ) ) {
			foreach ( array_reverse( $chain ) as $hop ) {
				if ( ! self::is_trusted_proxy( $hop ) ) {
					return $hop;
				}
			}
			return $chain[0];
		}

		foreach ( array( 'HTTP_AR_REAL_IP', 'HTTP_X_REAL_IP' ) as $header ) {
			$value = self::valid( self::server( $header ) );
			if ( '' !== $value ) {
				return $value;
			}
		}

		return $remote;
	}

	/**
	 * Valid IPs from X-Forwarded-For, left (client) to right (closest proxy).
	 *
	 * @return string[]
	 */
	private static function forwarded_chain() {
		$raw = self::server( 'HTTP_X_FORWARDED_FOR' );
		if ( '' === $raw ) {
			return array();
		}
		$chain = array();
		foreach ( explode( ',', $raw ) as $part ) {
			$ip = self::valid( $part );
			if ( '' !== $ip ) {
				$chain[] = $ip;
			}
		}
		return array_slice( $chain, -20 );
	}

	/**
	 * Whether a peer is a proxy whose headers may be read.
	 *
	 * @param string $ip IP.
	 * @return bool
	 */
	public static function is_trusted_proxy( $ip ) {
		if ( self::is_private( $ip ) || self::is_cloudflare( $ip ) ) {
			return true;
		}
		/**
		 * Extra trusted proxy IPs/CIDRs (load balancers, CDNs).
		 *
		 * @param string[] $ranges Ranges from the settings.
		 */
		$ranges = apply_filters( 'ashna_otp_trusted_proxies', self::custom_proxies() );
		return self::in_any( $ip, (array) $ranges );
	}

	/**
	 * Proxy ranges entered by the site owner.
	 *
	 * @return string[]
	 */
	public static function custom_proxies() {
		$ranges = array();
		foreach ( preg_split( '/[\s,]+/', (string) Ashna_Helper::get_option( 'trusted_proxies', '' ) ) as $item ) {
			$item = trim( $item );
			if ( '' !== $item && self::valid_range( $item ) ) {
				$ranges[] = $item;
			}
		}
		return $ranges;
	}

	/**
	 * Whether an IP is in Cloudflare's edge ranges.
	 *
	 * @param string $ip IP.
	 * @return bool
	 */
	public static function is_cloudflare( $ip ) {
		return self::in_any( $ip, self::CLOUDFLARE );
	}

	/**
	 * Whether an IP is private, loopback or link-local.
	 *
	 * @param string $ip IP.
	 * @return bool
	 */
	public static function is_private( $ip ) {
		return self::in_any( $ip, self::PRIVATE_RANGES );
	}

	/**
	 * Whether an IP is inside any of the given IPs/CIDRs.
	 *
	 * @param string   $ip     IP.
	 * @param string[] $ranges IPs or CIDRs.
	 * @return bool
	 */
	public static function in_any( $ip, array $ranges ) {
		foreach ( $ranges as $range ) {
			if ( self::in_range( $ip, $range ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether an IP is inside an IP/CIDR (IPv4 or IPv6).
	 *
	 * @param string $ip    IP.
	 * @param string $range IP or CIDR.
	 * @return bool
	 */
	public static function in_range( $ip, $range ) {
		$range = trim( (string) $range );
		$bits  = null;
		if ( false !== strpos( $range, '/' ) ) {
			list( $range, $bits ) = explode( '/', $range, 2 );
			$bits                 = (int) $bits;
		}
		$ip_bin    = @inet_pton( (string) $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$range_bin = @inet_pton( $range ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $ip_bin || false === $range_bin || strlen( $ip_bin ) !== strlen( $range_bin ) ) {
			return false;
		}
		$max  = strlen( $ip_bin ) * 8;
		$bits = null === $bits ? $max : max( 0, min( $max, $bits ) );

		$bytes = intdiv( $bits, 8 );
		if ( $bytes > 0 && substr( $ip_bin, 0, $bytes ) !== substr( $range_bin, 0, $bytes ) ) {
			return false;
		}
		$rest = $bits % 8;
		if ( 0 === $rest ) {
			return true;
		}
		$mask = ( 0xff << ( 8 - $rest ) ) & 0xff;
		return ( ord( $ip_bin[ $bytes ] ) & $mask ) === ( ord( $range_bin[ $bytes ] ) & $mask );
	}

	/**
	 * Network an IP belongs to: /24 for IPv4, /64 for IPv6 (attackers rotate inside it).
	 *
	 * @param string $ip IP.
	 * @return string
	 */
	public static function subnet( $ip ) {
		$bin = @inet_pton( (string) $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $bin ) {
			return '';
		}
		if ( 4 === strlen( $bin ) ) {
			return inet_ntop( substr( $bin, 0, 3 ) . "\0" ) . '/24';
		}
		return inet_ntop( substr( $bin, 0, 8 ) . str_repeat( "\0", 8 ) ) . '/64';
	}

	/**
	 * Normalized IP or ''.
	 *
	 * @param mixed $value Candidate.
	 * @return string
	 */
	public static function valid( $value ) {
		$value = trim( (string) $value );
		if ( '' !== $value && '[' === $value[0] && false !== strpos( $value, ']' ) ) {
			$value = substr( $value, 1, strpos( $value, ']' ) - 1 ); // [IPv6]:port
		} elseif ( substr_count( $value, ':' ) === 1 && false !== strpos( $value, '.' ) ) {
			$value = substr( $value, 0, strpos( $value, ':' ) ); // IPv4:port
		}
		if ( ! filter_var( $value, FILTER_VALIDATE_IP ) ) {
			return '';
		}
		$bin = @inet_pton( $value ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return false === $bin ? '' : inet_ntop( $bin );
	}

	/**
	 * Whether a string is a valid IP or CIDR.
	 *
	 * @param string $range Candidate.
	 * @return bool
	 */
	public static function valid_range( $range ) {
		$parts = explode( '/', (string) $range, 2 );
		if ( '' === self::valid( $parts[0] ) ) {
			return false;
		}
		if ( ! isset( $parts[1] ) ) {
			return true;
		}
		$max = false !== strpos( $parts[0], ':' ) ? 128 : 32;
		return ctype_digit( $parts[1] ) && (int) $parts[1] <= $max;
	}

	/**
	 * Whether the request carries proxy headers.
	 *
	 * @return bool
	 */
	private static function has_proxy_headers() {
		foreach ( self::PROXY_HEADERS as $header ) {
			if ( '' !== self::server( $header ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Remember that proxy headers arrived from a peer we do not trust, so the admin can be
	 * told the site may be behind an unlisted CDN (written at most every 10 minutes).
	 *
	 * @param string $remote Peer address.
	 */
	private static function note_untrusted_proxy( $remote ) {
		$hint = get_option( 'ashna_otp_proxy_hint', array() );
		$now  = time();
		if ( is_array( $hint ) && isset( $hint['seen'] ) && $now - (int) $hint['seen'] < 10 * MINUTE_IN_SECONDS ) {
			return;
		}
		$headers = array();
		foreach ( self::PROXY_HEADERS as $header ) {
			if ( '' !== self::server( $header ) ) {
				$headers[] = str_replace( '_', '-', substr( $header, 5 ) );
			}
		}
		update_option(
			'ashna_otp_proxy_hint',
			array(
				'remote'  => $remote,
				'headers' => $headers,
				'arvan'   => '' !== self::server( 'HTTP_AR_REAL_IP' ),
				'seen'    => $now,
			),
			false
		);
	}

	/**
	 * Recent "proxy headers from an untrusted peer" sighting, or null.
	 *
	 * @return array|null
	 */
	public static function untrusted_proxy_hint() {
		$hint = get_option( 'ashna_otp_proxy_hint', array() );
		if ( ! is_array( $hint ) || empty( $hint['remote'] ) || time() - (int) $hint['seen'] > DAY_IN_SECONDS ) {
			return null;
		}
		if ( self::is_trusted_proxy( $hint['remote'] ) ) {
			return null; // Already fixed.
		}
		return $hint;
	}

	/**
	 * What this request looks like, for the settings screen.
	 *
	 * @return array
	 */
	public static function diagnostics() {
		$headers = array();
		foreach ( self::PROXY_HEADERS as $header ) {
			$value = self::server( $header );
			if ( '' !== $value ) {
				$headers[ str_replace( '_', '-', substr( $header, 5 ) ) ] = $value;
			}
		}
		$remote = self::valid( self::server( 'REMOTE_ADDR' ) );
		return array(
			'remote'   => $remote,
			'headers'  => $headers,
			'trusted'  => '' !== $remote && self::is_trusted_proxy( $remote ),
			'resolved' => self::client(),
		);
	}

	/**
	 * Raw $_SERVER value.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function server( $key ) {
		return isset( $_SERVER[ $key ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) : '';
	}
}
