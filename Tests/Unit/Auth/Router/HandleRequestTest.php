<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth\Router;

use Brain\Monkey\Functions;
use Exception;
use Mockery;
use WPMedia\MCP\OAuth\Auth\AuthorizeCallback;
use WPMedia\MCP\OAuth\Auth\AuthorizeEndpoint;
use WPMedia\MCP\OAuth\Auth\ConsentEndpoint;
use WPMedia\MCP\OAuth\Auth\RevokeEndpoint;
use WPMedia\MCP\OAuth\Auth\Rewrite;
use WPMedia\MCP\OAuth\Auth\Router;
use WPMedia\MCP\OAuth\Auth\TokenEndpoint;
use WPMedia\MCP\OAuth\Context;
use WPMedia\MCP\OAuth\Tests\Unit\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\Router::handle_request
 *
 * @covers \WPMedia\MCP\OAuth\Auth\Router::handle_request
 */
class HandleRequestTest extends TestCase {

	/**
	 * Dispatches an incoming OAuth endpoint request according to context.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldDispatchRequestAccordingToContext( array $config, array $expected ): void {
		$this->stubEscapeFunctions();

		Functions\expect( 'get_query_var' )
			->once()
			->with( Rewrite::OAUTH_QUERY_VAR, '' )
			->andReturn( $config['endpoint'] );

		$context = Mockery::mock( Context::class );

		if ( ! $expected['check_enabled'] ) {
			$context->shouldNotReceive( 'is_enabled' );
		} else {
			$context->shouldReceive( 'is_enabled' )->once()->andReturn( $config['is_enabled'] );
		}

		if ( $expected['force_404'] ) {
			global $wp_query;
			$wp_query = Mockery::mock(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$wp_query->shouldReceive( 'set_404' )->once();
			Functions\expect( 'status_header' )->once()->with( 404 );
		} elseif ( $expected['unknown'] ) {
			Functions\expect( 'status_header' )->once()->with( 404 );
			Functions\expect( 'wp_die' )->once()->andThrow( new Exception( 'wp_die called' ) );

			$this->expectException( Exception::class );
			$this->expectExceptionMessage( 'wp_die called' );
		} else {
			Functions\expect( 'status_header' )->never();
		}

		$endpoints = [
			'authorize_endpoint' => Mockery::mock( AuthorizeEndpoint::class ),
			'authorize_callback' => Mockery::mock( AuthorizeCallback::class ),
			'token_endpoint'     => Mockery::mock( TokenEndpoint::class ),
			'consent_endpoint'   => Mockery::mock( ConsentEndpoint::class ),
			'revoke_endpoint'    => Mockery::mock( RevokeEndpoint::class ),
		];

		foreach ( $endpoints as $key => $endpoint ) {
			if ( $expected['dispatch'] === $key ) {
				$endpoint->shouldReceive( 'handle_request' )->once();
			} else {
				$endpoint->shouldNotReceive( 'handle_request' );
			}
		}

		$router = new Router(
			Mockery::mock( Rewrite::class ),
			$endpoints['authorize_endpoint'],
			$endpoints['authorize_callback'],
			$endpoints['token_endpoint'],
			$endpoints['consent_endpoint'],
			$endpoints['revoke_endpoint'],
			$context
		);

		$router->handle_request();

		if ( $expected['force_404'] ) {
			global $wp_query;
			$wp_query = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
	}
}
