<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth;

use WPMedia\MCP\OAuth\Auth\Rewrite;
use WPMedia\MCP\OAuth\Tests\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\Rewrite::register_oauth_rewrite_rules
 *
 * @covers \WPMedia\MCP\OAuth\Auth\Rewrite::register_oauth_rewrite_rules
 */
class RegisterOauthRewriteRulesTest extends TestCase {

	/**
	 * Clears the rewrite rules registered by previous tests.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		global $wp_rewrite;
		$wp_rewrite->extra_rules_top = [];
	}

	/**
	 * Registers a rewrite rule for each of the five OAuth endpoints.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldRegisterRewriteRuleForEachEndpoint( array $config, array $expected ): void {
		global $wp_rewrite;

		( new Rewrite() )->register_oauth_rewrite_rules();

		foreach ( $expected['rules'] as $rule ) {
			$this->assertSame(
				$rule['query'],
				$wp_rewrite->extra_rules_top[ $rule['pattern'] ] ?? null
			);
		}
	}
}
