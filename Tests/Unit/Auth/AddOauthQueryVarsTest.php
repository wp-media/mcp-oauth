<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth;

use WPMedia\MCP\OAuth\Auth\Rewrite;
use WPMedia\MCP\OAuth\Tests\Unit\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\Rewrite::add_oauth_query_vars
 *
 * @covers \WPMedia\MCP\OAuth\Auth\Rewrite::add_oauth_query_vars
 */
class AddOauthQueryVarsTest extends TestCase {

	/**
	 * Appends the OAuth query var to the given list.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param string[]             $expected Expected query vars list.
	 */
	public function testShouldAppendQueryVar( array $config, array $expected ): void {
		$rewrite = new Rewrite();

		$this->assertSame( $expected, $rewrite->add_oauth_query_vars( $config['vars'] ) );
	}
}
