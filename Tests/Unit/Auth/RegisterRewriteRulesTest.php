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
 * Tests for WPMedia\MCP\OAuth\Auth\Router::register_rewrite_rules
 *
 * @covers \WPMedia\MCP\OAuth\Auth\Router::register_rewrite_rules
 */
class RegisterRewriteRulesTest extends TestCase {

	/**
	 * Registers the OAuth rewrite rules only when the OAuth server is enabled.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldRegisterRewriteRulesBasedOnContext( array $config, array $expected ): void {
		$rewrite = Mockery::mock( Rewrite::class );
		$context = Mockery::mock( Context::class );
		$context->shouldReceive( 'is_enabled' )->once()->andReturn( $config['is_enabled'] );

		if ( $expected['should_register'] ) {
			$rewrite->shouldReceive( 'register_oauth_rewrite_rules' )->once();
		} else {
			$rewrite->shouldNotReceive( 'register_oauth_rewrite_rules' );
		}

		$router = new Router(
			$rewrite,
			Mockery::mock( AuthorizeEndpoint::class ),
			Mockery::mock( AuthorizeCallback::class ),
			Mockery::mock( TokenEndpoint::class ),
			Mockery::mock( ConsentEndpoint::class ),
			Mockery::mock( RevokeEndpoint::class ),
			$context
		);

		$router->register_rewrite_rules();
	}
}
