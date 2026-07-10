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
		// wpm_apply_filters_typed() is loaded via composer's "files" autoload before
		// Patchwork boots, so it cannot be redefined directly; mock the underlying
		// apply_filters() call it wraps instead, so its real (pass-through) logic runs.
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$verifier = new ClaudeClientVerifier();

		$this->assertSame( $expected['is_trusted'], $verifier->is_trusted_host( $config['client_id'] ) );
	}
}
