<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth\Discovery\Endpoints;

use Mockery;
use WPMedia\MCP\OAuth\Auth\Discovery\Endpoints;
use WPMedia\MCP\OAuth\Context;
use WPMedia\MCP\OAuth\Tests\Unit\TestCase;

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
		$endpoints = new Endpoints( Mockery::mock( Context::class ) );

		$this->assertSame( $expected, $endpoints->add_query_vars( $config['vars'] ) );
	}
}
