<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth;

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
	 * Fetches and validates a document for a trusted publisher, caches it,
	 * then serves the second resolve() call from the transient cache without
	 * fetching again.
	 *
	 * @return void
	 */
	public function testShouldFetchValidateAndCacheDocumentThenServeFromCacheOnSecondCall(): void {
		$doc = [
			'client_id'                  => self::TRUSTED_CLIENT_ID,
			'client_name'                => 'Claude',
			'redirect_uris'              => [ 'https://claude.ai/api/mcp/auth_callback' ],
			'grant_types'                => [ 'authorization_code', 'refresh_token' ],
			'token_endpoint_auth_method' => 'none',
		];

		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) use ( $doc ) {
				if ( self::TRUSTED_CLIENT_ID !== $url ) {
					return $preempt;
				}

				++$this->fetch_calls;

				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => wp_json_encode( $doc ),
					'headers'  => [
						'content-type'  => 'application/json',
						'cache-control' => 'max-age=3600',
					],
					'cookies'  => [],
					'filename' => null,
				];
			},
			10,
			3
		);

		$resolver = new CimdResolver( new ClaudeClientVerifier() );

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
}
