<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth\Discovery\HealthCheck;

use Brain\Monkey\Functions;
use Mockery;
use WPMedia\MCP\OAuth\Auth\Discovery\HealthCheck;
use WPMedia\MCP\OAuth\Context;
use WPMedia\MCP\OAuth\Tests\Unit\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\Discovery\HealthCheck::run_self_check
 *
 * The run_self_check() method is the only public entry point that exercises
 * the private classify() status mapping, so every classify() branch is driven
 * end to end through this public method (via a stubbed wp_safe_remote_get())
 * rather than tested in isolation.
 *
 * @covers \WPMedia\MCP\OAuth\Auth\Discovery\HealthCheck::run_self_check
 * @covers \WPMedia\MCP\OAuth\Auth\Discovery\HealthCheck::classify
 */
class RunSelfCheckTest extends TestCase {

	/**
	 * Stubs translation/escaping helpers used by build_result()'s description/actions text.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		$this->stubTranslationFunctions();
		$this->stubEscapeFunctions();
	}

	/**
	 * Runs the self-check according to the given enablement/cache/document state.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldRunSelfCheckAccordingToConfig( array $config, array $expected ): void {
		$context = Mockery::mock( Context::class );
		$context->shouldReceive( 'is_enabled' )->once()->andReturn( $config['is_enabled'] );

		$reaches_permalink_check = $config['is_enabled'];
		$reaches_transient_check = $reaches_permalink_check && '' !== $config['permalink_structure'];
		$reaches_network         = $reaches_transient_check && false === $config['cached'];

		if ( $reaches_permalink_check ) {
			Functions\expect( 'get_option' )->once()->with( 'permalink_structure' )->andReturn( $config['permalink_structure'] );
		} else {
			Functions\expect( 'get_option' )->never();
		}

		if ( $reaches_transient_check ) {
			Functions\expect( 'get_transient' )->once()->with( HealthCheck::TRANSIENT_KEY )->andReturn( $config['cached'] );
		} else {
			Functions\expect( 'get_transient' )->never();
		}

		if ( $reaches_network ) {
			Functions\when( 'home_url' )->alias(
				static function ( $path ) {
					return 'https://example.org' . $path;
				}
			);

			Functions\expect( 'wp_safe_remote_get' )
				->twice()
				->andReturnUsing(
					static function ( $url ) use ( $config ) {
						$doc = ( false !== strpos( $url, 'oauth-protected-resource' ) )
							? 'protected-resource'
							: 'authorization-server';

						return $config['documents'][ $doc ];
					}
				);

			Functions\when( 'is_wp_error' )->alias(
				static function ( $response ) {
					return $response['is_wp_error'] ?? false;
				}
			);
			Functions\when( 'wp_remote_retrieve_response_code' )->alias(
				static function ( $response ) {
					return $response['status'] ?? 0;
				}
			);
			Functions\when( 'wp_remote_retrieve_body' )->alias(
				static function ( $response ) {
					return $response['body'] ?? '';
				}
			);
			Functions\when( 'wp_remote_retrieve_header' )->alias(
				static function ( $response, $header ) {
					if ( 'x-powered-by' === $header ) {
						return $response['powered_by'] ?? '';
					}

					if ( 'x-redirect-by' === $header ) {
						return $response['redirect_by'] ?? '';
					}

					return '';
				}
			);

			Functions\expect( 'set_transient' )
				->once()
				->with( HealthCheck::TRANSIENT_KEY, Mockery::type( 'array' ), HealthCheck::TRANSIENT_TTL );
		} else {
			Functions\expect( 'wp_safe_remote_get' )->never();
			Functions\expect( 'set_transient' )->never();
		}

		$result = ( new HealthCheck( $context ) )->run_self_check();

		if ( ! empty( $expected['exact_result'] ) ) {
			$this->assertSame( $config['cached'], $result );

			return;
		}

		$this->assertSame( $expected['status'], $result['status'] );

		if ( isset( $expected['description_contains'] ) ) {
			$this->assertStringContainsString( $expected['description_contains'], $result['description'] );
		}

		if ( isset( $expected['description_not_contains'] ) ) {
			$this->assertStringNotContainsString( $expected['description_not_contains'], $result['description'] );
		}

		if ( isset( $expected['badge'] ) ) {
			$this->assertSame( $expected['badge']['label'], $result['badge']['label'] );
			$this->assertSame( $expected['badge']['color'], $result['badge']['color'] );
		}
	}
}
