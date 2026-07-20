<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth\CimdResolver;

use Brain\Monkey\Functions;
use Mockery;
use WPMedia\MCP\OAuth\Auth\CimdResolver;
use WPMedia\MCP\OAuth\Auth\ClaudeClientVerifier;
use WPMedia\MCP\OAuth\Tests\Unit\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\CimdResolver::resolve
 *
 * The DNS-rebinding preflight (connect_and_get_ip()) wraps native cURL calls
 * that cannot run under Brain\Monkey, so it is partial-mocked here: the real
 * resolve()/fetch_document()/is_ip_allowed() logic runs while only the native
 * connect step is stubbed. No real network or cURL call is ever made.
 *
 * @covers \WPMedia\MCP\OAuth\Auth\CimdResolver::resolve
 */
class ResolveTest extends TestCase {

	/**
	 * Resolves a client_id URL into a normalised client record according to config.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldResolveClientAccordingToConfig( array $config, array $expected ): void {
		$this->stubEscapeFunctions();

		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'is_wp_error' )->justReturn( $config['is_wp_error'] ?? false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( $config['status'] ?? 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $config['body'] ?? '' );
		Functions\when( 'wp_remote_retrieve_header' )->alias(
			static function ( $response, $header ) use ( $config ) {
				if ( 'content-type' === $header ) {
					return $config['content_type'] ?? 'application/json';
				}

				if ( 'cache-control' === $header ) {
					return $config['cache_control'] ?? '';
				}

				return '';
			}
		);

		$verifier = Mockery::mock( ClaudeClientVerifier::class );

		if ( $expected['is_trusted_host_checked'] ) {
			$verifier->shouldReceive( 'is_trusted_host' )
				->once()
				->with( $config['client_id'] )
				->andReturn( $config['is_trusted_host'] );
		} else {
			$verifier->shouldNotReceive( 'is_trusted_host' );
		}

		if ( $expected['verify_called'] ) {
			$verifier->shouldReceive( 'verify' )
				->once()
				->andReturn( $config['verify_result'] );
		} else {
			$verifier->shouldNotReceive( 'verify' );
		}

		// Partial mock: only the native-call preflight is stubbed; resolve(),
		// fetch_document() and is_ip_allowed() run for real.
		$resolver = Mockery::mock( CimdResolver::class . '[connect_and_get_ip]', [ $verifier ] );
		$resolver->shouldAllowMockingProtectedMethods();

		if ( $expected['preflight'] ) {
			// The cURL extension is loaded in the test environment, so the real
			// extension_loaded('curl') check passes and the preflight branch is
			// reached; connect_and_get_ip() is stubbed so no real cURL call runs.
			Functions\when( 'add_action' )->justReturn( true );
			Functions\when( 'remove_action' )->justReturn( true );

			$host       = (string) wp_parse_url( $config['client_id'], PHP_URL_HOST );
			$connect_ip = array_key_exists( 'connect_ip', $config ) ? $config['connect_ip'] : '93.184.216.34';
			$resolver->shouldReceive( 'connect_and_get_ip' )
				->once()
				->with( $host )
				->andReturn( $connect_ip );
		} else {
			$resolver->shouldReceive( 'connect_and_get_ip' )->never();
		}

		$cache_key = CimdResolver::CACHE_PREFIX . md5( $config['client_id'] );

		if ( $expected['cache_checked'] ) {
			Functions\expect( 'get_transient' )
				->once()
				->with( $cache_key )
				->andReturn( $config['cached'] ?? null );
		} else {
			Functions\expect( 'get_transient' )->never();
		}

		if ( $expected['fetch'] ) {
			$response = ( $config['is_wp_error'] ?? false ) ? $this->mock_wp_error( $config['error_message'] ?? '' ) : [ 'fetched' => true ];

			Functions\expect( 'wp_safe_remote_get' )
				->once()
				->with(
					$config['client_id'],
					[
						'timeout'             => CimdResolver::FETCH_TIMEOUT,
						'redirection'         => 0,
						'limit_response_size' => CimdResolver::MAX_DOCUMENT_BYTES,
						'headers'             => [ 'Accept' => 'application/json' ],
					]
				)
				->andReturn( $response );
		} else {
			Functions\expect( 'wp_safe_remote_get' )->never();
		}

		if ( $expected['cache_set'] ) {
			Functions\expect( 'set_transient' )
				->once()
				->with( $cache_key, Mockery::type( 'array' ), $expected['ttl'] );
		} else {
			Functions\expect( 'set_transient' )->never();
		}

		$this->assertSame( $expected['result'], $resolver->resolve( $config['client_id'] ) );
	}

	/**
	 * Emits the distinct 'explicit port not allowed' reason for a client_id
	 * that carries an explicit port, rather than the generic 'invalid client_id
	 * url' reason, so a legitimate non-standard-port publisher is diagnosable.
	 *
	 * WP_DEBUG/WP_DEBUG_LOG are define()-once constants; @runInSeparateProcess
	 * forks a fresh process so they never leak, matching McpLogger\LogTest. The
	 * log line is asserted by pointing the error_log ini directive at a scratch
	 * file (Patchwork will not redefine error_log without extra config).
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @dataProvider explicitPortProvider
	 *
	 * @param string $client_id A client_id URL carrying an explicit port.
	 */
	public function testShouldLogDistinctReasonForExplicitPort( string $client_id ): void {
		define( 'WP_DEBUG', true );
		define( 'WP_DEBUG_LOG', true );

		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'wp_json_encode' )->alias(
			static function ( $data ) {
				return json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Brain\Monkey unit test with no WP runtime; wp_json_encode() is stubbed to this.
			}
		);

		$log_file = tempnam( sys_get_temp_dir(), 'mcp-oauth-resolve-porttest-' );
		ini_set( 'error_log', $log_file ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- test-only, redirects error_log() to a scratch file in an isolated process; never runs in production.

		$verifier = Mockery::mock( ClaudeClientVerifier::class );
		$verifier->shouldNotReceive( 'is_trusted_host' );
		$resolver = new CimdResolver( $verifier );

		try {
			$this->assertNull( $resolver->resolve( $client_id ) );

			$written = file_exists( $log_file ) ? (string) file_get_contents( $log_file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a local scratch file, not a remote URL.

			$this->assertStringContainsString( 'rejected: explicit port not allowed', $written );
			$this->assertStringNotContainsString( 'rejected: invalid client_id url', $written );
		} finally {
			if ( file_exists( $log_file ) ) {
				unlink( $log_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- wp_delete_file() requires WordPress; this is a Brain\Monkey unit test.
			}
		}
	}

	/**
	 * Data provider: client_id URLs with an explicit port (both a non-standard
	 * port and an explicit :443).
	 *
	 * @return array<string, array{0: string}>
	 */
	public function explicitPortProvider(): array {
		return [
			'non-standard port' => [ 'https://example.com:8080/cimd.json' ],
			'explicit 443'      => [ 'https://example.com:443/cimd.json' ],
		];
	}

	/**
	 * Builds a Mockery mock standing in for a WP_Error instance.
	 *
	 * @param string $message Error message returned by get_error_message().
	 * @return Mockery\MockInterface
	 */
	private function mock_wp_error( string $message ) {
		$error = Mockery::mock( 'WP_Error' );
		$error->shouldReceive( 'get_error_message' )->andReturn( $message );

		return $error;
	}
}
