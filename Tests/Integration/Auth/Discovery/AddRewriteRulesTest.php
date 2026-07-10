<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth\Discovery;

use WPMedia\MCP\OAuth\Auth\Discovery\Endpoints;
use WPMedia\MCP\OAuth\Context;
use WPMedia\MCP\OAuth\Tests\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\Discovery\Endpoints::add_rewrite_rules
 *
 * @covers \WPMedia\MCP\OAuth\Auth\Discovery\Endpoints::add_rewrite_rules
 */
class AddRewriteRulesTest extends TestCase {

	const PROTECTED_RESOURCE_RULE   = '^\.well-known/oauth-protected-resource$';
	const AUTHORIZATION_SERVER_RULE = '^\.well-known/oauth-authorization-server$';

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
	 * Registers rewrite rules only when the OAuth server is enabled.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldRegisterRewriteRulesBasedOnContext( array $config, array $expected ): void {
		global $wp_rewrite;

		add_filter(
			'wpmedia_mcp_oauth_server_enabled',
			static function () use ( $config ) {
				return $config['is_enabled'];
			}
		);

		( new Endpoints( new Context() ) )->add_rewrite_rules();

		if ( $expected['should_register'] ) {
			$this->assertSame(
				'index.php?mcp_oauth_discovery=protected-resource',
				$wp_rewrite->extra_rules_top[ self::PROTECTED_RESOURCE_RULE ] ?? null
			);
			$this->assertSame(
				'index.php?mcp_oauth_discovery=authorization-server',
				$wp_rewrite->extra_rules_top[ self::AUTHORIZATION_SERVER_RULE ] ?? null
			);
		} else {
			$this->assertArrayNotHasKey( self::PROTECTED_RESOURCE_RULE, $wp_rewrite->extra_rules_top );
			$this->assertArrayNotHasKey( self::AUTHORIZATION_SERVER_RULE, $wp_rewrite->extra_rules_top );
		}
	}
}
