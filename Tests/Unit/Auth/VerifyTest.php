<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth;

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
		$override = $config['trusted_publishers'] ?? null;

		// wpm_apply_filters_typed() is loaded via composer's "files" autoload before
		// Patchwork boots, so it cannot be redefined directly; mock the underlying
		// apply_filters() call it wraps instead, so its real (pass-through) logic runs.
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook_name, $value ) use ( $override ) {
				return null !== $override ? $override : $value;
			}
		);
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'wp_json_encode' )->justReturn( '{}' );

		$verifier = new ClaudeClientVerifier();

		$this->assertSame( $expected, $verifier->verify( $config['client_id'], $config['doc'] ) );
	}
}
