<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth\AuthorizeEndpoint;

use WPMedia\MCP\OAuth\Auth\AuthorizeEndpoint;
use WPMedia\PHPUnit\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\AuthorizeEndpoint::stored_state_key
 *
 * @covers \WPMedia\MCP\OAuth\Auth\AuthorizeEndpoint::stored_state_key
 */
class StoredStateKeyTest extends TestCase {

	/**
	 * Returns the key the state transient is stored under.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldReturnKeyTheStateIsStoredUnder( array $config, array $expected ): void {
		$state = 'state-value';
		$keys  = [
			'hashed' => AuthorizeEndpoint::state_key( $state ),
			'legacy' => 'mcp_oauth_state_' . $state,
		];

		if ( null !== $config['stored_as'] ) {
			set_transient( $keys[ $config['stored_as'] ], [ 'state' => $state ], 60 );
		}

		$this->assertSame( $keys[ $expected['key'] ], AuthorizeEndpoint::stored_state_key( $state ) );
	}
}
