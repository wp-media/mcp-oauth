<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth\Discovery\HealthCheck;

use Brain\Monkey\Functions;
use Mockery;
use WPMedia\MCP\OAuth\Auth\Discovery\HealthCheck;
use WPMedia\MCP\OAuth\Context;
use WPMedia\MCP\OAuth\Tests\Unit\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\Discovery\HealthCheck::classify
 *
 * Classify() is private and has no public entry point of its own; it is the
 * single source of truth for the Status Mapping table, so it is exercised
 * directly via reflection rather than only indirectly through
 * run_self_check().
 *
 * @covers \WPMedia\MCP\OAuth\Auth\Discovery\HealthCheck::classify
 */
class ClassifyTest extends TestCase {

	/**
	 * Classifies a fetch result into a Site Health status according to config.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldClassifyResponseAccordingToStatusMapping( array $config, array $expected ): void {
		Functions\when( 'is_wp_error' )->justReturn( $config['is_wp_error'] ?? false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( $config['status'] ?? 0 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $config['body'] ?? '' );
		Functions\when( 'wp_remote_retrieve_header' )->alias(
			static function ( $response, $header ) use ( $config ) {
				if ( 'x-powered-by' === $header ) {
					return $config['powered_by'] ?? '';
				}

				if ( 'x-redirect-by' === $header ) {
					return $config['redirect_by'] ?? '';
				}

				return '';
			}
		);

		$response = ( $config['is_wp_error'] ?? false ) ? Mockery::mock( 'WP_Error' ) : [ 'response' => true ];

		$health_check = new HealthCheck( Mockery::mock( Context::class ) );
		$method       = $this->get_reflective_method( 'classify', HealthCheck::class );

		$this->assertSame( $expected['status'], $method->invoke( $health_check, $response ) );
	}
}
