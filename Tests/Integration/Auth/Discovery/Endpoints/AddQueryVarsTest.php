<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth\Discovery\Endpoints;

use WPMedia\MCP\OAuth\Auth\Discovery\Endpoints;
use WPMedia\MCP\OAuth\Context;
use WPMedia\MCP\OAuth\Tests\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\Discovery\Endpoints::add_query_vars
 *
 * @covers \WPMedia\MCP\OAuth\Auth\Discovery\Endpoints::add_query_vars
 */
class AddQueryVarsTest extends TestCase {

	/**
	 * Appends the discovery query var to the given list.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param string[]             $expected Expected query vars list.
	 */
	public function testShouldAppendQueryVar( array $config, array $expected ): void {
		// Not routed through the real 'query_vars' filter: WordPress core and
		// the library's own Router already have callbacks registered on it,
		// which would pollute the result with unrelated query vars.
		$endpoints = new Endpoints( new Context() );

		$this->assertSame( $expected, $endpoints->add_query_vars( $config['vars'] ) );
	}
}
