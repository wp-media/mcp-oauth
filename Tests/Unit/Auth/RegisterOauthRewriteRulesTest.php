<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth;

use Brain\Monkey\Functions;
use WPMedia\MCP\OAuth\Auth\Rewrite;
use WPMedia\MCP\OAuth\Tests\Unit\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\Rewrite::register_oauth_rewrite_rules
 *
 * @covers \WPMedia\MCP\OAuth\Auth\Rewrite::register_oauth_rewrite_rules
 */
class RegisterOauthRewriteRulesTest extends TestCase {

	/**
	 * Registers a rewrite rule for each of the five OAuth endpoints.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldRegisterRewriteRuleForEachEndpoint( array $config, array $expected ): void {
		foreach ( $expected['rules'] as $rule ) {
			Functions\expect( 'add_rewrite_rule' )
				->once()
				->with( $rule['pattern'], $rule['query'], 'top' );
		}

		( new Rewrite() )->register_oauth_rewrite_rules();
	}
}
