<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Context;

use WPMedia\MCP\OAuth\Context;
use WPMedia\PHPUnit\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Context::observability_handler
 *
 * @covers \WPMedia\MCP\OAuth\Context::observability_handler
 */
class ObservabilityHandlerTest extends TestCase {

	/**
	 * Resolves the observability handler according to the filter.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldResolveHandlerAccordingToFilter( array $config, array $expected ): void {
		if ( null !== $config['filtered'] ) {
			add_filter(
				'wpmedia_mcp_oauth_observability_handler',
				static function () use ( $config ) {
					return $config['filtered'];
				}
			);
		}

		if ( $expected['incorrect_usage'] ) {
			$this->setExpectedIncorrectUsage( 'wpm_apply_filters_typed' );
		}

		$this->assertSame( $expected['result'], ( new Context() )->observability_handler() );
	}
}
