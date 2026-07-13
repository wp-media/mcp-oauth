<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth;

use Brain\Monkey\Functions;
use WPMedia\MCP\OAuth\Auth\ClaudeClientVerifier;
use WPMedia\MCP\OAuth\Tests\Unit\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\ClaudeClientVerifier::is_trusted_host
 *
 * @covers \WPMedia\MCP\OAuth\Auth\ClaudeClientVerifier::is_trusted_host
 */
class IsTrustedHostTest extends TestCase {

	/**
	 * Determines whether a client_id URL's host belongs to a trusted publisher.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldDetermineWhetherHostIsTrusted( array $config, array $expected ): void {
		// get_trusted_publishers() runs the built-in allowlist through the
		// `wpmedia_mcp_oauth_trusted_publishers` filter. Brain\Monkey returns an
		// applied filter's value unchanged by default, so the built-in Claude entry
		// is evaluated as-is — no filter stubbing needed for these scenarios.
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$verifier = new ClaudeClientVerifier();

		$this->assertSame( $expected['is_trusted'], $verifier->is_trusted_host( $config['client_id'] ) );
	}
}
