<?php
/**
 * Client ID Metadata Document (CIMD) resolver.
 *
 * Replaces Dynamic Client Registration (RFC 7591).  Instead of pre-registering
 * a client and minting a client_id/secret, the client presents an HTTPS URL as
 * its client_id; this resolver dereferences that URL, validates the returned
 * metadata document, and produces a normalised client record compatible with
 * the one AuthorizeEndpoint previously read from ClientRegistration::get_client().
 *
 * CIMD is the default client-registration model in the 2025-11-25 MCP
 * specification (IETF OAuth WG, adopted October 2025).
 */

declare(strict_types=1);

namespace WPMedia\MCP\OAuth\Auth;

use WPMedia\MCP\OAuth\Logging\McpLogger;

class CimdResolver {
	/**
	 * Maximum size (bytes) of a metadata document we will read.
	 */
	const MAX_DOCUMENT_BYTES = 5120;

	/**
	 * HTTP request timeout (seconds) for fetching a document.
	 */
	const FETCH_TIMEOUT = 5;

	/**
	 * Timeout (seconds) for the connect-only DNS-rebinding preflight.
	 *
	 * Kept well below FETCH_TIMEOUT so the preflight is a cheap, strictly
	 * time-bounded gate rather than a second full fetch: a TCP+TLS connect to a
	 * legitimate public host is normally well under a second, so 2s is generous
	 * headroom while capping the worst-case worker hang on a slow/unresponsive
	 * client_id host (was open-ended under a raw OS-resolver lookup).
	 */
	const CONNECT_TIMEOUT = 2;

	/**
	 * Transient key prefix for cached, validated documents.
	 */
	const CACHE_PREFIX = 'mcp_cimd_';

	/**
	 * Grant types this server supports.
	 */
	const SUPPORTED_GRANT_TYPES = [ 'authorization_code', 'refresh_token' ];

	/**
	 * Trusted-publisher verifier.
	 *
	 * @var ClaudeClientVerifier
	 */
	private ClaudeClientVerifier $verifier;

	/**
	 * Constructor.
	 *
	 * @param ClaudeClientVerifier $verifier Trusted-publisher verifier.
	 */
	public function __construct( ClaudeClientVerifier $verifier ) {
		$this->verifier = $verifier;
	}

	/**
	 * Resolve a client_id URL into a normalised client record.
	 *
	 * @param string $client_id The client_id URL presented by the client.
	 * @return array<string, mixed>|null Normalised client record, or null on any failure.
	 */
	public function resolve( string $client_id ): ?array {
		if ( ! $this->is_valid_client_id_url( $client_id ) ) {
			// is_valid_client_id_url() also rejects any explicit port (see 4.0):
			// a client_id carrying a non-443 port would silently bypass the
			// host:443:ip CURLOPT_RESOLVE pin, so it is refused up front. Emit a
			// distinct reason for that case so an operator can tell a legitimate
			// non-standard-port publisher apart from a genuinely malformed URL.
			$parts = wp_parse_url( $client_id );
			if ( is_array( $parts ) && isset( $parts['port'] ) ) {
				McpLogger::log( 'CIMD', 'rejected: explicit port not allowed', [ 'client_id' => $client_id ] );
			} else {
				McpLogger::log( 'CIMD', 'rejected: invalid client_id url', [ 'client_id' => $client_id ] );
			}
			return null;
		}

		// Gate the network fetch on the trusted-publisher host allowlist. The
		// authorize endpoint is unauthenticated, so dereferencing an arbitrary
		// client_id URL would let any caller use this server as an outbound
		// fetch proxy and pollute the transient cache. Only allowlisted hosts
		// are ever fetched; exact client_id verification still happens later.
		if ( ! $this->verifier->is_trusted_host( $client_id ) ) {
			McpLogger::log( 'CIMD', 'rejected: client_id host not in trusted-publisher allowlist', [ 'client_id' => $client_id ] );
			return null;
		}

		$cached = $this->cache_get( $client_id );
		if ( null !== $cached ) {
			return $cached;
		}

		$fetched = $this->fetch_document( $client_id );
		if ( null === $fetched ) {
			return null;
		}

		$record = $this->validate_document( $fetched['doc'], $client_id );
		if ( null === $record ) {
			return null;
		}

		$verification        = $this->verifier->verify( $client_id, $fetched['doc'] );
		$record['verified']  = (bool) $verification['verified'];
		$record['publisher'] = (string) $verification['publisher'];

		$this->cache_set( $client_id, $record, $fetched['ttl'] );

		return $record;
	}

	/**
	 * Validate the shape of a client_id URL per the CIMD spec.
	 *
	 * Requires an HTTPS URL with a path and no fragment or userinfo.
	 *
	 * @param string $client_id The client_id URL.
	 * @return bool
	 */
	private function is_valid_client_id_url( string $client_id ): bool {
		if ( '' === $client_id ) {
			return false;
		}

		$parts = wp_parse_url( $client_id );

		if ( ! is_array( $parts ) ) {
			return false;
		}

		if ( 'https' !== ( $parts['scheme'] ?? '' ) ) {
			return false;
		}

		if ( empty( $parts['host'] ) ) {
			return false;
		}

		$path = (string) ( $parts['path'] ?? '' );
		if ( '' === $path || '/' === $path ) {
			return false;
		}

		if ( isset( $parts['fragment'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return false;
		}

		// Reject any explicit port. CIMD client_id URLs use the default HTTPS
		// port 443; wp_parse_url() only populates $parts['port'] for an explicit
		// port. Rejecting it guarantees the connection always uses 443, so the
		// "host:443:ip" CURLOPT_RESOLVE pin built in build_resolve_pin() always
		// matches the request's host:port and cannot be silently bypassed.
		if ( isset( $parts['port'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Fetch a metadata document with SSRF guards and a size cap.
	 *
	 * @param string $url The client_id URL.
	 * @return array{doc: array<string, mixed>, ttl: int}|null Decoded document and cache TTL, or null on failure.
	 */
	private function fetch_document( string $url ) {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		$pin  = null;

		// DNS-rebinding guard. The cURL extension is required for the preflight
		// and the CURLOPT_RESOLVE pin; if it is unavailable the only reachable
		// caller here is the trusted-host allowlist path, so we fall back to an
		// unpinned fetch (the allowlist still constrains the host). There is no
		// raw-DNS fallback, which would reintroduce an unbounded-timeout lookup.
		if ( extension_loaded( 'curl' ) ) {
			$ip = $this->connect_and_get_ip( $host );
			if ( null === $ip ) {
				// connect_and_get_ip() already logged the reason.
				return null;
			}

			if ( ! $this->is_ip_allowed( $ip ) ) {
				McpLogger::log(
					'CIMD',
					'rejected: client_id host resolves to a disallowed IP',
					[
						'client_id' => $url,
						'ip'        => $ip,
					]
				);
				return null;
			}

			// Pin the real fetch to the exact IP the preflight validated, so no
			// re-resolution can happen between validation and fetch (no TOCTOU /
			// rebinding gap): validated IP == connected IP == fetched IP.
			$pin = function ( $handle ) use ( $host, $ip ) {
				curl_setopt( $handle, CURLOPT_RESOLVE, [ $this->build_resolve_pin( $host, $ip ) ] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- pinning the resolved IP on the underlying cURL handle is the whole point; wp_remote_get() exposes no equivalent.
			};
			add_action( 'http_api_curl', $pin );
		}

		try {
			$response = wp_safe_remote_get(
				$url,
				[
					'timeout'             => self::FETCH_TIMEOUT,
					'redirection'         => 0,
					'limit_response_size' => self::MAX_DOCUMENT_BYTES,
					'headers'             => [ 'Accept' => 'application/json' ],
				]
			);
		} finally {
			if ( null !== $pin ) {
				remove_action( 'http_api_curl', $pin );
			}
		}

		if ( is_wp_error( $response ) ) {
			McpLogger::log(
				'CIMD',
				'rejected: fetch failed',
				[
					'client_id' => $url,
					'error'     => $response->get_error_message(),
				]
			);
			return null;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			McpLogger::log(
				'CIMD',
				'rejected: non-200 status',
				[
					'client_id' => $url,
					'status'    => $status,
				]
			);
			return null;
		}

		$body = (string) wp_remote_retrieve_body( $response );
		if ( strlen( $body ) > self::MAX_DOCUMENT_BYTES ) {
			McpLogger::log(
				'CIMD',
				'rejected: document too large',
				[
					'client_id' => $url,
					'bytes'     => strlen( $body ),
				]
			);
			return null;
		}

		// Content-type policy: a present-but-non-JSON type is rejected; an
		// absent/empty header is tolerated (some CDNs and static object stores
		// omit Content-Type on JSON objects, and the body is independently
		// validated by the json_decode + client_id-match checks below).
		$content_type = (string) wp_remote_retrieve_header( $response, 'content-type' );
		if ( '' !== $content_type && false === strpos( $content_type, 'application/json' ) ) {
			McpLogger::log(
				'CIMD',
				'rejected: unexpected content-type',
				[
					'client_id'    => $url,
					'content_type' => $content_type,
				]
			);
			return null;
		}

		$doc = json_decode( $body, true );
		if ( ! is_array( $doc ) || empty( $doc ) ) {
			McpLogger::log( 'CIMD', 'rejected: body is not a JSON object', [ 'client_id' => $url ] );
			return null;
		}

		return [
			'doc' => $doc,
			'ttl' => $this->parse_ttl( (string) wp_remote_retrieve_header( $response, 'cache-control' ) ),
		];
	}

	/**
	 * Time-bounded cURL connect-only preflight: resolve DNS and open a TCP/TLS
	 * connection under a firm timeout, then report the IP actually connected to.
	 *
	 * Uses CURLOPT_CONNECT_ONLY so no HTTP request is sent and no body is read;
	 * the handle is discarded (never reused for the real fetch). Unlike PHP's
	 * gethostbynamel()/dns_get_record() — which take no timeout parameter and
	 * block on the OS resolver — cURL bounds both the connect and (on an
	 * async-DNS-capable libcurl) the name-resolution phase at CONNECT_TIMEOUT.
	 *
	 * @param string $host The client_id URL host (always connected on port 443).
	 * @return string|null The connected IP (CURLINFO_PRIMARY_IP), or null on failure/timeout.
	 */
	protected function connect_and_get_ip( string $host ): ?string {
		// The WP HTTP API cannot perform a connect-only probe or report the
		// connected IP (CURLINFO_PRIMARY_IP), so this SSRF preflight uses the
		// cURL functions directly and intentionally. The curl_close() calls
		// below are guarded by PHP_VERSION_ID < 80500 so they never run on PHP
		// 8.5+, where curl_close() is deprecated; the DeprecatedFunctions sniff
		// is a static token scan that cannot see that guard, so it is disabled
		// for this block too.
		// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_init, WordPress.WP.AlternativeFunctions.curl_curl_setopt_array, WordPress.WP.AlternativeFunctions.curl_curl_exec, WordPress.WP.AlternativeFunctions.curl_curl_errno, WordPress.WP.AlternativeFunctions.curl_curl_close, WordPress.WP.AlternativeFunctions.curl_curl_getinfo, Generic.PHP.DeprecatedFunctions.Deprecated
		$ch      = curl_init();
		$options = [
			CURLOPT_URL            => "https://{$host}/",
			CURLOPT_PORT           => 443,
			CURLOPT_CONNECT_ONLY   => true,
			CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
			CURLOPT_TIMEOUT        => self::CONNECT_TIMEOUT,
		];

		// Verify TLS against the same CA bundle WordPress core's own HTTP API
		// uses (ABSPATH . WPINC . '/certificates/ca-bundle.crt', the default
		// 'sslcertificates' path WP_Http_Curl passes to CURLOPT_CAINFO), so the
		// preflight's trust store matches the real wp_safe_remote_get() fetch and
		// a legitimate host is not false-rejected on a system without a CA store.
		if ( defined( 'ABSPATH' ) && defined( 'WPINC' ) ) {
			$ca_bundle = ABSPATH . WPINC . '/certificates/ca-bundle.crt';
			if ( is_readable( $ca_bundle ) ) {
				$options[ CURLOPT_CAINFO ] = $ca_bundle;
			}
		}

		curl_setopt_array( $ch, $options );

		$result = curl_exec( $ch );

		if ( false === $result || 0 !== curl_errno( $ch ) ) {
			// curl_close() was deprecated in PHP 8.5 (a no-op since 8.0, where a
			// CurlHandle object is freed automatically when it goes out of scope);
			// still call it below 8.5 so the handle is freed promptly on PHP 7.4,
			// where it is a resource.
			if ( PHP_VERSION_ID < 80500 ) {
				curl_close( $ch );
			}
			McpLogger::log( 'CIMD', 'rejected: preflight connect failed or timed out', [ 'host' => $host ] );
			return null;
		}

		$ip = (string) curl_getinfo( $ch, CURLINFO_PRIMARY_IP );
		if ( PHP_VERSION_ID < 80500 ) {
			curl_close( $ch );
		}
		// phpcs:enable WordPress.WP.AlternativeFunctions.curl_curl_init, WordPress.WP.AlternativeFunctions.curl_curl_setopt_array, WordPress.WP.AlternativeFunctions.curl_curl_exec, WordPress.WP.AlternativeFunctions.curl_curl_errno, WordPress.WP.AlternativeFunctions.curl_curl_close, WordPress.WP.AlternativeFunctions.curl_curl_getinfo, Generic.PHP.DeprecatedFunctions.Deprecated

		if ( '' === $ip ) {
			McpLogger::log( 'CIMD', 'rejected: preflight connect failed or timed out', [ 'host' => $host ] );
			return null;
		}

		return $ip;
	}

	/**
	 * Build the CURLOPT_RESOLVE pin entry for a host/IP pair.
	 *
	 * The port is always 443 because is_valid_client_id_url() rejects any
	 * explicit port, so the pin's host:port always matches the real request and
	 * cannot be bypassed. libcurl parses the entry as "host:port:addr", where
	 * addr is everything after the second colon, so an unbracketed IPv6 literal
	 * is unambiguous and needs no brackets.
	 *
	 * @param string $host The request host.
	 * @param string $ip   The IP to pin the host to.
	 * @return string The "host:443:ip" pin entry.
	 */
	protected function build_resolve_pin( string $host, string $ip ): string {
		return "{$host}:443:{$ip}";
	}

	/**
	 * Whether an IP is allowed as a CIMD fetch target (pure range validation).
	 *
	 * Rejects private, loopback, link-local and ULA ranges plus the CGNAT
	 * (100.64.0.0/10), "this-network" (0.0.0.0/8) and IETF-protocol
	 * (192.0.0.0/24) ranges. Any IPv6 form that embeds an IPv4 address is
	 * normalised to that IPv4 and re-checked against the IPv4 ranges, so a
	 * private/reserved IPv4 cannot slip through wrapped in an IPv6 literal:
	 * IPv4-mapped (::ffff:x.x.x.x), IPv4-compatible (::/96), NAT64
	 * (64:ff9b::/96) and 6to4 (2002::/16). These are cases the filter_var()
	 * flags alone do not reliably catch across PHP versions.
	 *
	 * @param string $ip The IP address to validate.
	 * @return bool True if the IP is a routable public address, false otherwise.
	 */
	protected function is_ip_allowed( string $ip ): bool {
		$packed = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- inet_pton() emits a warning on malformed input; we intentionally treat that as "reject".

		if ( false === $packed ) {
			return false;
		}

		if ( 16 === strlen( $packed ) ) {
			// IPv4-mapped IPv6 (::ffff:x.x.x.x): normalise to the embedded IPv4
			// and re-run the IPv4 range checks. stripos() (not str_contains(),
			// which is PHP 8.0+) keeps this compatible with the PHP 7.4 floor.
			$mapped_prefix = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff";
			if ( false !== stripos( $ip, '::ffff:' ) || 0 === strncmp( $packed, $mapped_prefix, 12 ) ) {
				$allowed = $this->extract_and_validate_embedded_ipv4( substr( $packed, 12, 4 ) );
				if ( null !== $allowed ) {
					return $allowed;
				}
			}

			// Other IPv6 encodings that embed an IPv4 address: NAT64
			// (64:ff9b::/96) and the deprecated IPv4-compatible form (::/96)
			// both carry the IPv4 in the last 4 bytes; 6to4 (2002::/16) carries
			// it in bytes 2-5. Decode it and re-run the IPv4 range checks so a
			// private/reserved IPv4 cannot slip through wrapped in one of these
			// prefixes (e.g. on a DNS64/NAT64 or 6to4 network).
			$nat64_prefix  = "\x00\x64\xff\x9b\x00\x00\x00\x00\x00\x00\x00\x00";
			$compat_prefix = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
			$allowed       = null;
			if ( 0 === strncmp( $packed, $nat64_prefix, 12 ) || 0 === strncmp( $packed, $compat_prefix, 12 ) ) {
				$allowed = $this->extract_and_validate_embedded_ipv4( substr( $packed, 12, 4 ) );
			} elseif ( "\x20\x02" === substr( $packed, 0, 2 ) ) {
				$allowed = $this->extract_and_validate_embedded_ipv4( substr( $packed, 2, 4 ) );
			}
			if ( null !== $allowed ) {
				return $allowed;
			}

			return $this->is_ipv6_allowed( $ip, $packed );
		}

		return $this->is_ipv4_allowed( $ip );
	}

	/**
	 * Decode a 4-byte embedded IPv4 slice and run the IPv4 range checks on it.
	 *
	 * Shared decode-and-validate tail for every IPv4-in-IPv6 encoding: the
	 * caller isolates which bytes hold the IPv4 (that offset differs per
	 * prefix); this validates them the same way regardless of the prefix.
	 *
	 * @param string $packed_v4_bytes The raw 4 bytes of the embedded IPv4.
	 * @return bool|null True/false when the slice is a valid IPv4 that is
	 *                   allowed/disallowed; null when it is not a decodable IPv4
	 *                   (so the caller falls back to IPv6 handling).
	 */
	private function extract_and_validate_embedded_ipv4( string $packed_v4_bytes ): ?bool {
		if ( 4 !== strlen( $packed_v4_bytes ) ) {
			return null;
		}

		$ip = inet_ntop( $packed_v4_bytes );
		if ( ! is_string( $ip ) || false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return null;
		}

		return $this->is_ipv4_allowed( $ip );
	}

	/**
	 * Whether an IPv4 dotted-quad is a routable public address.
	 *
	 * @param string $ip The IPv4 address.
	 * @return bool
	 */
	private function is_ipv4_allowed( string $ip ): bool {
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return false;
		}

		$ip_long = ip2long( $ip );
		if ( false === $ip_long ) {
			return false;
		}
		$ip_long &= 0xFFFFFFFF;

		$blocked = [
			[ '10.0.0.0', 8 ],     // Private.
			[ '172.16.0.0', 12 ],  // Private.
			[ '192.168.0.0', 16 ], // Private.
			[ '127.0.0.0', 8 ],    // Loopback.
			[ '169.254.0.0', 16 ], // Link-local.
			[ '100.64.0.0', 10 ],  // CGNAT (RFC 6598).
			[ '0.0.0.0', 8 ],      // This-network range per RFC 1122.
			[ '192.0.0.0', 24 ],   // IETF protocol assignments per RFC 6890.
		];

		foreach ( $blocked as $range ) {
			$mask     = ( 0xFFFFFFFF << ( 32 - $range[1] ) ) & 0xFFFFFFFF;
			$net_long = ip2long( $range[0] ) & 0xFFFFFFFF;
			if ( ( $ip_long & $mask ) === ( $net_long & $mask ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether an IPv6 address is a routable public address.
	 *
	 * @param string $ip     The IPv6 address string.
	 * @param string $packed The 16-byte inet_pton() form of $ip.
	 * @return bool
	 */
	private function is_ipv6_allowed( string $ip, string $packed ): bool {
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return false;
		}

		// Reject the unspecified address and the loopback address.
		if ( "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00" === $packed ) {
			return false;
		}
		if ( "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x01" === $packed ) {
			return false;
		}

		$first_byte  = ord( $packed[0] );
		$second_byte = ord( $packed[1] );

		// fe80::/10 link-local.
		if ( 0xfe === $first_byte && 0x80 === ( $second_byte & 0xc0 ) ) {
			return false;
		}

		// fc00::/7 unique local address (ULA).
		if ( 0xfc === ( $first_byte & 0xfe ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate a metadata document and build a normalized client record.
	 *
	 * @param array<string, mixed> $doc The decoded metadata document.
	 * @param string               $url The client_id URL it was fetched from.
	 * @return array<string, mixed>|null Normalized record, or null on validation failure.
	 */
	private function validate_document( array $doc, string $url ): ?array {
		// The document's client_id MUST exactly equal the document URL.
		$doc_client_id = isset( $doc['client_id'] ) && is_string( $doc['client_id'] ) ? $doc['client_id'] : '';
		if ( '' === $doc_client_id || $url !== $doc_client_id ) {
			McpLogger::log(
				'CIMD',
				'rejected: client_id mismatch',
				[
					'client_id'          => $url,
					'document_client_id' => $doc_client_id,
				]
			);
			return null;
		}

		// Only public clients are supported (no shared secret to authenticate).
		$auth_method = isset( $doc['token_endpoint_auth_method'] ) ? (string) $doc['token_endpoint_auth_method'] : 'none';
		if ( 'none' !== $auth_method ) {
			McpLogger::log(
				'CIMD',
				'rejected: unsupported token_endpoint_auth_method',
				[
					'client_id' => $url,
					'method'    => $auth_method,
				]
			);
			return null;
		}

		// redirect_uris is required.
		$redirect_uris = $doc['redirect_uris'] ?? [];
		if ( ! is_array( $redirect_uris ) || empty( $redirect_uris ) ) {
			McpLogger::log( 'CIMD', 'rejected: missing redirect_uris', [ 'client_id' => $url ] );
			return null;
		}

		$redirect_uris = array_values(
			array_filter(
				array_map( 'esc_url_raw', array_map( 'strval', $redirect_uris ) )
			)
		);

		if ( empty( $redirect_uris ) ) {
			McpLogger::log( 'CIMD', 'rejected: no valid redirect_uris after sanitisation', [ 'client_id' => $url ] );
			return null;
		}

		// grant_types: intersect with what we support; must include authorization_code.
		$requested_grants = isset( $doc['grant_types'] ) && is_array( $doc['grant_types'] )
			? array_map( 'strval', $doc['grant_types'] )
			: self::SUPPORTED_GRANT_TYPES;

		$grant_types = array_values( array_intersect( self::SUPPORTED_GRANT_TYPES, $requested_grants ) );
		if ( ! in_array( 'authorization_code', $grant_types, true ) ) {
			McpLogger::log(
				'CIMD',
				'rejected: authorization_code grant not offered',
				[
					'client_id'   => $url,
					'grant_types' => $requested_grants,
				]
			);
			return null;
		}

		$client_name = isset( $doc['client_name'] ) ? sanitize_text_field( (string) $doc['client_name'] ) : '';
		if ( '' === $client_name ) {
			$client_name = (string) wp_parse_url( $url, PHP_URL_HOST );
		}

		$client_uri = isset( $doc['client_uri'] ) && is_string( $doc['client_uri'] ) ? esc_url_raw( $doc['client_uri'] ) : '';

		return [
			'client_id'                  => $url,
			'client_name'                => $client_name,
			'client_uri'                 => $client_uri,
			'redirect_uris'              => $redirect_uris,
			'grant_types'                => $grant_types,
			'token_endpoint_auth_method' => 'none',
			'source'                     => 'cimd',
			'verified'                   => false,
			'publisher'                  => '',
		];
	}

	/**
	 * Parse a max-age TTL from a Cache-Control header, clamped to a sane range.
	 *
	 * @param string $cache_control The Cache-Control header value.
	 * @return int TTL in seconds.
	 */
	private function parse_ttl( string $cache_control ): int {
		$default = HOUR_IN_SECONDS;

		if ( '' !== $cache_control && preg_match( '/max-age\s*=\s*(\d+)/i', $cache_control, $matches ) ) {
			$default = (int) $matches[1];
		}

		return (int) max( 300, min( $default, DAY_IN_SECONDS ) );
	}

	/**
	 * Build the transient cache key for a client_id URL.
	 *
	 * @param string $url The client_id URL.
	 * @return string
	 */
	private function cache_key( string $url ): string {
		return self::CACHE_PREFIX . md5( $url );
	}

	/**
	 * Retrieve a cached, validated client record.
	 *
	 * @param string $url The client_id URL.
	 * @return array<string, mixed>|null
	 */
	private function cache_get( string $url ): ?array {
		$cached = get_transient( $this->cache_key( $url ) );

		return is_array( $cached ) ? $cached : null;
	}

	/**
	 * Cache a validated client record. Only successful documents are ever cached.
	 *
	 * @param string               $url    The client_id URL.
	 * @param array<string, mixed> $record The normalised record.
	 * @param int                  $ttl    Cache TTL in seconds.
	 * @return void
	 */
	private function cache_set( string $url, array $record, int $ttl ): void {
		set_transient( $this->cache_key( $url ), $record, $ttl );
	}
}
