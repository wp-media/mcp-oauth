<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth;

use Brain\Monkey\Functions;
use Mockery;
use WPMedia\MCP\OAuth\Auth\CimdResolver;
use WPMedia\MCP\OAuth\Auth\ClaudeClientVerifier;
use WPMedia\MCP\OAuth\Tests\Unit\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\CimdResolver::resolve
 *
 * @covers \WPMedia\MCP\OAuth\Auth\CimdResolver::resolve
 */
class ResolveTest extends TestCase {

	/**
	 * Sets up the WP constants read by CimdResolver::parse_ttl().
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
			define( 'HOUR_IN_SECONDS', 3600 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WP core constant, not a plugin-defined global.
		}

		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			define( 'DAY_IN_SECONDS', 86400 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WP core constant, not a plugin-defined global.
		}
	}

	/**
	 * Resolves a client_id URL into a normalised client record according to config.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldResolveClientAccordingToConfig( array $config, array $expected ): void {
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'esc_url_raw' )->alias( 'strval' );
		Functions\when( 'wp_json_encode' )->justReturn( '{}' );
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

		$resolver = new CimdResolver( $verifier );

		$this->assertSame( $expected['result'], $resolver->resolve( $config['client_id'] ) );
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
