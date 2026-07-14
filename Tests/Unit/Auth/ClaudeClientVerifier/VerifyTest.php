<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use WPMedia\MCP\OAuth\Auth\ClaudeClientVerifier;
use WPMedia\MCP\OAuth\Tests\Unit\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\ClaudeClientVerifier::verify
 *
 * @covers \WPMedia\MCP\OAuth\Auth\ClaudeClientVerifier::verify
 */
class VerifyTest extends TestCase {

	/**
	 * Verifies a fetched CIMD document against the trusted-publisher allowlist.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected verification result.
	 */
	public function testShouldVerifyDocumentAgainstTrustedPublishers( array $config, array $expected ): void {
		// get_trusted_publishers() runs the built-in allowlist through the
		// `wpmedia_mcp_oauth_trusted_publishers` filter. Brain\Monkey returns an
		// applied filter's value unchanged by default, so the built-in Claude entry
		// flows through untouched with no stubbing. Scenarios that need a different
		// allowlist (e.g. a declared host that no longer matches the client_id)
		// override that filter's result via the Filters API.
		$override = $config['trusted_publishers'] ?? null;
		if ( null !== $override ) {
			Filters\expectApplied( 'wpmedia_mcp_oauth_trusted_publishers' )->andReturn( $override );
		}

		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'wp_json_encode' )->justReturn( '{}' );

		$verifier = new ClaudeClientVerifier();

		$this->assertSame( $expected, $verifier->verify( $config['client_id'], $config['doc'] ) );
	}
}
