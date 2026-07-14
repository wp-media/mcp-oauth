<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth\Discovery\Endpoints;

use Brain\Monkey\Functions;
use Mockery;
use WPMedia\MCP\OAuth\Auth\Discovery\Endpoints;
use WPMedia\MCP\OAuth\Context;
use WPMedia\MCP\OAuth\Tests\Unit\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\Discovery\Endpoints::add_rewrite_rules
 *
 * @covers \WPMedia\MCP\OAuth\Auth\Discovery\Endpoints::add_rewrite_rules
 */
class AddRewriteRulesTest extends TestCase {

	/**
	 * Registers rewrite rules only when the OAuth server is enabled.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldRegisterRewriteRulesBasedOnContext( array $config, array $expected ): void {
		$context = Mockery::mock( Context::class );
		$context->shouldReceive( 'is_enabled' )->once()->andReturn( $config['is_enabled'] );

		if ( $expected['should_register'] ) {
			Functions\expect( 'add_rewrite_rule' )
				->once()
				->with(
					'^\\.well-known/oauth-protected-resource$',
					'index.php?mcp_oauth_discovery=protected-resource',
					'top'
				);
			Functions\expect( 'add_rewrite_rule' )
				->once()
				->with(
					'^\\.well-known/oauth-authorization-server$',
					'index.php?mcp_oauth_discovery=authorization-server',
					'top'
				);
		} else {
			Functions\expect( 'add_rewrite_rule' )->never();
		}

		( new Endpoints( $context ) )->add_rewrite_rules();
	}
}
