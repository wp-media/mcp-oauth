<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth\CimdResolver;

use WPMedia\MCP\OAuth\Auth\CimdResolver;
use WPMedia\MCP\OAuth\Auth\ClaudeClientVerifier;
use WPMedia\MCP\OAuth\Tests\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\CimdResolver::resolve
 *
 * Exercises the real ClaudeClientVerifier and real transient cache. The
 * `is_trusted_host()`/`verify()` allowlist only recognizes the bundled
 * Claude client_id URLs, so the fetch/cache round-trip scenario below uses
 * one of those exact URLs and short-circuits the HTTP call via the core
 * `pre_http_request` filter rather than hitting the network. The remaining
 * scenarios (invalid client_id shape, untrusted host) never reach the fetch
 * step, so no filter is required for them.
 *
 * The connect-only DNS-rebinding preflight (connect_and_get_ip()) makes a raw
 * cURL call that `pre_http_request` cannot intercept, so the resolver returned
 * by stubbed_resolver() overrides it to report a controlled IP — no real
 * network or cURL connection is ever made. The private-IP scenario proves the
 * fetch is rejected before wp_safe_remote_get() is reached.
 *
 * @covers \WPMedia\MCP\OAuth\Auth\CimdResolver::resolve
 */
class ResolveTest extends TestCase {

	const TRUSTED_CLIENT_ID = 'https://claude.ai/oauth/claude-code-client-metadata';

	const SECOND_TRUSTED_CLIENT_ID = 'https://claude.ai/oauth/mcp-oauth-client-metadata';

	const UNTRUSTED_CLIENT_ID = 'https://untrusted.example/cimd.json';

	/**
	 * Number of times the faked HTTP fetch was invoked during a test.
	 *
	 * @var int
	 */
	private $fetch_calls = 0;

	/**
	 * Clears any cached record for the trusted client_id used below, and the
	 * fetch-budget counter (shared by every test in this class).
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->fetch_calls = 0;
		delete_transient( CimdResolver::CACHE_PREFIX . md5( self::TRUSTED_CLIENT_ID ) );
		delete_transient( CimdResolver::CACHE_PREFIX . md5( self::SECOND_TRUSTED_CLIENT_ID ) );
		delete_transient( CimdResolver::CACHE_PREFIX . md5( self::UNTRUSTED_CLIENT_ID ) );
		delete_transient( CimdResolver::RATE_LIMIT_KEY );
	}

	/**
	 * Clears any cached record left behind, resets the fetch-budget counter,
	 * and removes the HTTP short-circuit and any fetch-limit filter override.
	 *
	 * @return void
	 */
	public function tear_down() {
		delete_transient( CimdResolver::CACHE_PREFIX . md5( self::TRUSTED_CLIENT_ID ) );
		delete_transient( CimdResolver::CACHE_PREFIX . md5( self::SECOND_TRUSTED_CLIENT_ID ) );
		delete_transient( CimdResolver::CACHE_PREFIX . md5( self::UNTRUSTED_CLIENT_ID ) );
		delete_transient( CimdResolver::RATE_LIMIT_KEY );
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'wpmedia_mcp_oauth_cimd_fetch_limit' );

		parent::tear_down();
	}

	/**
	 * Rejects a client_id that does not satisfy the CIMD URL shape without
	 * ever consulting the trusted-publisher verifier.
	 *
	 * @return void
	 */
	public function testShouldReturnNullForInvalidClientIdUrl(): void {
		$resolver = new CimdResolver( new ClaudeClientVerifier() );

		$this->assertNull( $resolver->resolve( 'http://not-https.example/cimd.json' ) );
	}

	/**
	 * Rejects a client_id URL that carries an explicit port up front, before any
	 * preflight or fetch, so the CURLOPT_RESOLVE pin cannot be bypassed.
	 *
	 * @return void
	 */
	public function testShouldReturnNullForClientIdWithExplicitPort(): void {
		$resolver = new CimdResolver( new ClaudeClientVerifier() );

		$this->assertNull( $resolver->resolve( 'https://claude.ai:8080/oauth/claude-code-client-metadata' ) );
	}

	/**
	 * Rejects a well-formed client_id URL whose host is not on the
	 * trusted-publisher allowlist, without ever fetching it.
	 *
	 * @return void
	 */
	public function testShouldReturnNullWhenHostIsNotTrusted(): void {
		// `is_trusted_host()` gates the fetch before any HTTP call is made, so no
		// `pre_http_request` short-circuit is required here.
		$resolver = new CimdResolver( new ClaudeClientVerifier() );

		$this->assertNull( $resolver->resolve( 'https://untrusted.example/cimd.json' ) );
	}

	/**
	 * Rejects the fetch when the preflight connects to a private IP: the
	 * disallowed-IP guard runs before wp_safe_remote_get(), so no document is
	 * fetched even for an allowlisted host.
	 *
	 * @return void
	 */
	public function testShouldReturnNullWhenPreflightConnectsToPrivateIp(): void {
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( self::TRUSTED_CLIENT_ID === $url ) {
					++$this->fetch_calls;
				}

				return $preempt;
			},
			10,
			3
		);

		$resolver = $this->stubbed_resolver( '10.0.0.5' );

		$this->assertNull( $resolver->resolve( self::TRUSTED_CLIENT_ID ) );
		$this->assertSame( 0, $this->fetch_calls, 'A private connected IP must be rejected before any HTTP fetch.' );
	}

	/**
	 * Rejects a fetched document whose Content-Type is present but not JSON.
	 *
	 * @return void
	 */
	public function testShouldReturnNullWhenContentTypeIsPresentAndNotJson(): void {
		$this->fake_fetch( 'text/html' );

		$resolver = $this->stubbed_resolver( '93.184.216.34' );

		$this->assertNull( $resolver->resolve( self::TRUSTED_CLIENT_ID ) );
	}

	/**
	 * Tolerates an absent Content-Type header and resolves the document, since
	 * the body is independently validated.
	 *
	 * @return void
	 */
	public function testShouldResolveWhenContentTypeIsAbsent(): void {
		$this->fake_fetch( null );

		$resolver = $this->stubbed_resolver( '93.184.216.34' );

		$record = $resolver->resolve( self::TRUSTED_CLIENT_ID );

		$this->assertIsArray( $record );
		$this->assertSame( self::TRUSTED_CLIENT_ID, $record['client_id'] );
		$this->assertTrue( $record['verified'] );
	}

	/**
	 * Fetches and validates a document for a trusted publisher, caches it,
	 * then serves the second resolve() call from the transient cache without
	 * fetching again.
	 *
	 * @return void
	 */
	public function testShouldFetchValidateAndCacheDocumentThenServeFromCacheOnSecondCall(): void {
		$this->fake_fetch( 'application/json' );

		$resolver = $this->stubbed_resolver( '93.184.216.34' );

		$first = $resolver->resolve( self::TRUSTED_CLIENT_ID );

		$this->assertIsArray( $first );
		$this->assertSame( self::TRUSTED_CLIENT_ID, $first['client_id'] );
		$this->assertSame( [ 'https://claude.ai/api/mcp/auth_callback' ], $first['redirect_uris'] );
		$this->assertSame( [ 'authorization_code', 'refresh_token' ], $first['grant_types'] );
		$this->assertSame( 'cimd', $first['source'] );
		$this->assertTrue( $first['verified'] );
		$this->assertSame( 'claude', $first['publisher'] );
		$this->assertSame( 1, $this->fetch_calls );

		$second = $resolver->resolve( self::TRUSTED_CLIENT_ID );

		$this->assertSame( $first, $second );
		$this->assertSame( 1, $this->fetch_calls, 'The second resolve() call should be served from cache, without a second HTTP fetch.' );
	}

	/**
	 * Rejects a cache-miss fetch once the window's budget is already spent,
	 * proving no HTTP request is made (AC1, with a real transient).
	 *
	 * @return void
	 */
	public function testShouldReturnNullWhenFetchBudgetIsExhausted(): void {
		set_transient( CimdResolver::RATE_LIMIT_KEY, CimdResolver::MAX_FETCHES_PER_WINDOW, MINUTE_IN_SECONDS );
		$this->fake_fetch( 'application/json' );

		$resolver = $this->stubbed_resolver( '93.184.216.34' );

		$this->assertNull( $resolver->resolve( self::TRUSTED_CLIENT_ID ) );
		$this->assertSame( 0, $this->fetch_calls, 'An exhausted fetch budget must reject before any HTTP fetch.' );
	}

	/**
	 * A second resolve() call for an already-cached client_id never consumes
	 * the fetch budget (AC2, with a real transient round trip).
	 *
	 * @return void
	 */
	public function testShouldNotConsumeFetchBudgetWhenServedFromCache(): void {
		$this->fake_fetch( 'application/json' );

		$resolver = $this->stubbed_resolver( '93.184.216.34' );

		$resolver->resolve( self::TRUSTED_CLIENT_ID );
		$resolver->resolve( self::TRUSTED_CLIENT_ID );

		$this->assertSame( 1, $this->fetch_calls );
		$this->assertSame( 1, (int) get_transient( CimdResolver::RATE_LIMIT_KEY ) );
	}

	/**
	 * The `wpmedia_mcp_oauth_cimd_fetch_limit` filter overrides the default
	 * ceiling (AC3): a filtered limit of 1 allows the first distinct cache-miss
	 * fetch and rejects the second, without a second HTTP call.
	 *
	 * @return void
	 */
	public function testShouldHonourFilteredFetchLimit(): void {
		add_filter(
			'wpmedia_mcp_oauth_cimd_fetch_limit',
			static function () {
				return 1;
			}
		);

		$this->fake_fetch( 'application/json' );
		$this->fake_fetch( 'application/json', self::SECOND_TRUSTED_CLIENT_ID );

		$resolver = $this->stubbed_resolver( '93.184.216.34' );

		$first = $resolver->resolve( self::TRUSTED_CLIENT_ID );
		$this->assertIsArray( $first );
		$this->assertSame( 1, $this->fetch_calls );

		$second = $resolver->resolve( self::SECOND_TRUSTED_CLIENT_ID );
		$this->assertNull( $second, 'A distinct client_id is a cache miss, so the spent budget must reject it.' );
		$this->assertSame( 1, $this->fetch_calls, 'The rejected fetch must not reach the network.' );
	}

	/**
	 * A record cached while untrusted providers were allowed is still refused
	 * once the filter is flipped back: the host gate runs before the cache read,
	 * so no cached untrusted record can survive it.
	 *
	 * @return void
	 */
	public function testShouldRejectCachedUntrustedHostWhenUntrustedNotAllowed(): void {
		$this->prime_untrusted_cache();

		$resolver = new CimdResolver( new ClaudeClientVerifier() );

		$this->assertNull( $resolver->resolve( self::UNTRUSTED_CLIENT_ID, false ) );
	}

	/**
	 * Mirror of the above: with untrusted providers allowed, the same cached
	 * record is served, without a fetch.
	 *
	 * @return void
	 */
	public function testShouldServeCachedUntrustedHostWhenUntrustedAllowed(): void {
		$record = $this->prime_untrusted_cache();

		$resolver = new CimdResolver( new ClaudeClientVerifier() );

		$this->assertSame( $record, $resolver->resolve( self::UNTRUSTED_CLIENT_ID, true ) );
		$this->assertSame( 0, $this->fetch_calls, 'A cache hit must not fetch.' );
	}

	/**
	 * Refuses an untrusted host when cURL is unavailable: there is no bounded
	 * preflight, no CURLOPT_RESOLVE pin, and no allowlist bounding the target,
	 * so the unpinned fallback is not offered to that tier.
	 *
	 * @return void
	 */
	public function testShouldRejectUntrustedHostWhenCurlIsUnavailable(): void {
		$this->fake_fetch( 'application/json', self::UNTRUSTED_CLIENT_ID );

		$resolver = $this->stubbed_resolver( '93.184.216.34', false );

		$this->assertNull( $resolver->resolve( self::UNTRUSTED_CLIENT_ID, true ) );
		$this->assertSame( 0, $this->fetch_calls, 'An untrusted host must not be fetched unpinned.' );
	}

	/**
	 * The pre-existing unpinned fallback survives for a trusted host when cURL
	 * is unavailable: the decision keys off host trust, not the filter value.
	 *
	 * @return void
	 */
	public function testShouldFetchTrustedHostUnpinnedWhenCurlIsUnavailable(): void {
		$this->fake_fetch( 'application/json' );

		$resolver = $this->stubbed_resolver( null, false );

		$record = $resolver->resolve( self::TRUSTED_CLIENT_ID, true );

		$this->assertIsArray( $record );
		$this->assertTrue( $record['verified'] );
		$this->assertSame( 1, $this->fetch_calls );
	}

	/**
	 * Primes the transient cache with a valid, unverified record for the
	 * untrusted client_id, as if it had been resolved while the filter allowed
	 * untrusted providers.
	 *
	 * @return array<string, mixed> The record written to the cache.
	 */
	private function prime_untrusted_cache(): array {
		$record = [
			'client_id'                  => self::UNTRUSTED_CLIENT_ID,
			'client_name'                => 'Untrusted App',
			'client_uri'                 => '',
			'redirect_uris'              => [ 'https://untrusted.example/callback' ],
			'grant_types'                => [ 'authorization_code', 'refresh_token' ],
			'token_endpoint_auth_method' => 'none',
			'source'                     => 'cimd',
			'verified'                   => false,
			'publisher'                  => '',
		];

		set_transient( CimdResolver::CACHE_PREFIX . md5( self::UNTRUSTED_CLIENT_ID ), $record, MINUTE_IN_SECONDS );

		return $record;
	}

	/**
	 * Builds a CimdResolver whose native cURL connect-only preflight is stubbed
	 * to report a fixed IP, so the DNS-rebinding orchestration can be exercised
	 * without a real connection.
	 *
	 * @param string|null $ip             The IP the stubbed preflight reports as connected.
	 * @param bool        $curl_available What the is_curl_available() seam reports. The
	 *                                    extension is always loaded in the test container,
	 *                                    so the no-cURL branch is only reachable this way.
	 * @return CimdResolver
	 */
	private function stubbed_resolver( ?string $ip, bool $curl_available = true ): CimdResolver {
		return new class( new ClaudeClientVerifier(), $ip, $curl_available ) extends CimdResolver {

			/**
			 * IP the stubbed preflight reports as the connected address.
			 *
			 * @var string|null
			 */
			private $stub_ip;

			/**
			 * What the is_curl_available() seam reports.
			 *
			 * @var bool
			 */
			private $stub_curl_available;

			/**
			 * Sets up the resolver with a stubbed preflight IP.
			 *
			 * @param ClaudeClientVerifier $verifier            Trusted-publisher verifier.
			 * @param string|null          $stub_ip             Stubbed connected IP.
			 * @param bool                 $stub_curl_available Stubbed cURL availability.
			 */
			public function __construct( ClaudeClientVerifier $verifier, ?string $stub_ip, bool $stub_curl_available ) {
				parent::__construct( $verifier );
				$this->stub_ip             = $stub_ip;
				$this->stub_curl_available = $stub_curl_available;
			}

			/**
			 * Reports the configured cURL availability instead of probing the
			 * extension, which cannot be unloaded in the test container.
			 *
			 * @return bool
			 */
			protected function is_curl_available(): bool {
				return $this->stub_curl_available;
			}

			/**
			 * Returns the configured stub IP instead of a real cURL connect.
			 *
			 * @param string      $host      The client_id URL host.
			 * @param string|null $ca_bundle Unused; the real preflight is stubbed.
			 * @return string|null
			 */
			protected function connect_and_get_ip( string $host, ?string $ca_bundle = null ): ?string {
				return $this->stub_ip;
			}
		};
	}

	/**
	 * Registers a `pre_http_request` short-circuit that returns a canned CIMD
	 * document for the given client_id, with the given Content-Type header.
	 *
	 * @param string|null $content_type Content-Type header value, or null to omit it.
	 * @param string      $client_id    The client_id URL the canned document is built for.
	 * @return void
	 */
	private function fake_fetch( ?string $content_type, string $client_id = self::TRUSTED_CLIENT_ID ): void {
		$doc = [
			'client_id'                  => $client_id,
			'client_name'                => 'Claude',
			'redirect_uris'              => [ 'https://claude.ai/api/mcp/auth_callback' ],
			'grant_types'                => [ 'authorization_code', 'refresh_token' ],
			'token_endpoint_auth_method' => 'none',
		];

		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) use ( $doc, $content_type, $client_id ) {
				if ( $client_id !== $url ) {
					return $preempt;
				}

				++$this->fetch_calls;

				$headers = [ 'cache-control' => 'max-age=3600' ];
				if ( null !== $content_type ) {
					$headers['content-type'] = $content_type;
				}

				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => wp_json_encode( $doc ),
					'headers'  => $headers,
					'cookies'  => [],
					'filename' => null,
				];
			},
			10,
			3
		);
	}
}
