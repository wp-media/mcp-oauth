<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth\ClaudeClientVerifier;

use WPMedia\MCP\OAuth\Auth\ClaudeClientVerifier;
use WPMedia\MCP\OAuth\Tests\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\ClaudeClientVerifier::verify
 *
 * @covers \WPMedia\MCP\OAuth\Auth\ClaudeClientVerifier::verify
 */
class VerifyTest extends TestCase {

	/**
	 * A callback hooked to the legacy `rocket_mcp_trusted_publishers` filter
	 * triggers a deprecation notice, while its return value still flows into
	 * the allowlist so verification keeps working during the deprecation cycle.
	 */
	public function testShouldTriggerDeprecationNoticeWhenLegacyTrustedPublishersFilterIsUsed(): void {
		$this->setExpectedDeprecated( 'rocket_mcp_trusted_publishers' );

		add_filter(
			'rocket_mcp_trusted_publishers',
			static function ( $publishers ) {
				return $publishers;
			}
		);

		$result = ( new ClaudeClientVerifier() )->verify(
			'https://claude.ai/oauth/claude-code-client-metadata',
			[ 'token_endpoint_auth_method' => 'none' ]
		);

		$this->assertTrue( $result['verified'] );
		$this->assertSame( 'claude', $result['publisher'] );
	}

	/**
	 * With no callback on the legacy filter, apply_filters_deprecated() must not
	 * emit a deprecation notice and the built-in Claude publisher still verifies.
	 */
	public function testShouldNotTriggerDeprecationNoticeWhenLegacyFilterIsUnused(): void {
		$result = ( new ClaudeClientVerifier() )->verify(
			'https://claude.ai/oauth/claude-code-client-metadata',
			[ 'token_endpoint_auth_method' => 'none' ]
		);

		$this->assertTrue( $result['verified'] );
		$this->assertSame( 'claude', $result['publisher'] );
	}
}
