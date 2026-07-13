<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Context;

use WPMedia\MCP\OAuth\Context;
use WPMedia\MCP\OAuth\Tests\Unit\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Context::is_enabled
 *
 * @covers \WPMedia\MCP\OAuth\Context::is_enabled
 */
class IsEnabledTest extends TestCase {

	/**
	 * Evaluates the enabled state according to the primary and legacy filters.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldEvaluateEnabledStateAccordingToFilters( array $config, array $expected ): void {
		if ( null !== $config['primary'] ) {
			add_filter(
				'wpmedia_mcp_oauth_server_enabled',
				static function () use ( $config ) {
					return $config['primary'];
				}
			);
		}

		if ( null !== $config['legacy'] ) {
			add_filter(
				'rocket_mcp_oauth_server_enabled',
				static function () use ( $config ) {
					return $config['legacy'];
				}
			);
		}

		if ( $expected['incorrect_usage'] ) {
			$this->setExpectedIncorrectUsage( 'wpm_apply_filters_typed' );
		}

		$this->assertSame( $expected['result'], ( new Context() )->is_enabled() );
	}
}
