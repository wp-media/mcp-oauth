<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth;

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
 * Tests for WPMedia\MCP\OAuth\Auth\Router::add_query_vars
 *
 * @covers \WPMedia\MCP\OAuth\Auth\Router::add_query_vars
 */
class AddQueryVarsTest extends TestCase {

	/**
	 * Delegates to Rewrite::add_oauth_query_vars() and returns its result.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldDelegateToRewrite( array $config, array $expected ): void {
		$rewrite = Mockery::mock( Rewrite::class );
		$rewrite->shouldReceive( 'add_oauth_query_vars' )
			->once()
			->with( $config['vars'] )
			->andReturn( $expected['vars'] );

		$router = new Router(
			$rewrite,
			Mockery::mock( AuthorizeEndpoint::class ),
			Mockery::mock( AuthorizeCallback::class ),
			Mockery::mock( TokenEndpoint::class ),
			Mockery::mock( ConsentEndpoint::class ),
			Mockery::mock( RevokeEndpoint::class ),
			Mockery::mock( Context::class )
		);

		$this->assertSame( $expected['vars'], $router->add_query_vars( $config['vars'] ) );
	}
}
