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

	/**
	 * Number of times the faked HTTP fetch was invoked during a test.
	 *
	 * @var int
	 */
	private $fetch_calls = 0;

	/**
	 * Clears any cached record for the trusted client_id used below.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->fetch_calls = 0;
		delete_transient( CimdResolver::CACHE_PREFIX . md5( self::TRUSTED_CLIENT_ID ) );
	}

	/**
	 * Clears any cached record left behind and removes the HTTP short-circuit.
	 *
	 * @return void
	 */
	public function tear_down() {
		delete_transient( CimdResolver::CACHE_PREFIX . md5( self::TRUSTED_CLIENT_ID ) );
		remove_all_filters( 'pre_http_request' );

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
	 * Builds a CimdResolver whose native cURL connect-only preflight is stubbed
	 * to report a fixed IP, so the DNS-rebinding orchestration can be exercised
	 * without a real connection.
	 *
	 * @param string|null $ip The IP the stubbed preflight reports as connected.
	 * @return CimdResolver
	 */
	private function stubbed_resolver( ?string $ip ): CimdResolver {
		return new class( new ClaudeClientVerifier(), $ip ) extends CimdResolver {

			/**
			 * IP the stubbed preflight reports as the connected address.
			 *
			 * @var string|null
			 */
			private $stub_ip;

			/**
			 * Sets up the resolver with a stubbed preflight IP.
			 *
			 * @param ClaudeClientVerifier $verifier Trusted-publisher verifier.
			 * @param string|null          $stub_ip  Stubbed connected IP.
			 */
			public function __construct( ClaudeClientVerifier $verifier, ?string $stub_ip ) {
				parent::__construct( $verifier );
				$this->stub_ip = $stub_ip;
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
	 * document for the trusted client_id, with the given Content-Type header.
	 *
	 * @param string|null $content_type Content-Type header value, or null to omit it.
	 * @return void
	 */
	private function fake_fetch( ?string $content_type ): void {
		$doc = [
			'client_id'                  => self::TRUSTED_CLIENT_ID,
			'client_name'                => 'Claude',
			'redirect_uris'              => [ 'https://claude.ai/api/mcp/auth_callback' ],
			'grant_types'                => [ 'authorization_code', 'refresh_token' ],
			'token_endpoint_auth_method' => 'none',
		];

		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) use ( $doc, $content_type ) {
				if ( self::TRUSTED_CLIENT_ID !== $url ) {
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
