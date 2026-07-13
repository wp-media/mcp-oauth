<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth\Router;

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
 * Tests for WPMedia\MCP\OAuth\Auth\Router::purge_refresh_jti_meta
 *
 * @covers \WPMedia\MCP\OAuth\Auth\Router::purge_refresh_jti_meta
 */
class PurgeRefreshJtiMetaTest extends TestCase {

	/**
	 * Delegates to TokenEndpoint::purge_refresh_jti_meta() with cast arguments.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldDelegateToTokenEndpointWithCastArguments( array $config, array $expected ): void {
		$token_endpoint = Mockery::mock( TokenEndpoint::class );
		$token_endpoint->shouldReceive( 'purge_refresh_jti_meta' )
			->once()
			->with( $expected['user_id'], $expected['item'] );

		$router = new Router(
			Mockery::mock( Rewrite::class ),
			Mockery::mock( AuthorizeEndpoint::class ),
			Mockery::mock( AuthorizeCallback::class ),
			$token_endpoint,
			Mockery::mock( ConsentEndpoint::class ),
			Mockery::mock( RevokeEndpoint::class ),
			Mockery::mock( Context::class )
		);

		$router->purge_refresh_jti_meta( $config['user_id'], $config['item'] );
	}
}
