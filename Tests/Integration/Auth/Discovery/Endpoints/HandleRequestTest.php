<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth\Discovery\Endpoints;

use WPMedia\MCP\OAuth\Auth\Discovery\Endpoints;
use WPMedia\MCP\OAuth\Context;
use WPMedia\MCP\OAuth\Tests\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\Discovery\Endpoints::handle_request
 *
 * Only exercises the branches that return normally. Outside of an AJAX
 * request, `wp_send_json()` terminates the request with a bare `die`, which
 * cannot be run in-process without killing the test runner; those branches
 * (serving the protected-resource/authorization-server documents) are
 * covered by the Unit test instead, where `wp_send_json()` is mocked.
 *
 * @covers \WPMedia\MCP\OAuth\Auth\Discovery\Endpoints::handle_request
 */
class HandleRequestTest extends TestCase {

	/**
	 * Handles a discovery request according to the given context.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldHandleRequestAccordingToContext( array $config, array $expected ): void {
		if ( null !== $expected['sent_json'] ) {
			$this->markTestSkipped( 'wp_send_json() terminates the request outside of AJAX; covered by the Unit test instead.' );
		}

		global $wp_query;

		set_query_var( Endpoints::QUERY_VAR, $config['discovery'] );

		add_filter(
			'wpmedia_mcp_oauth_server_enabled',
			static function () use ( $config ) {
				return $config['is_enabled'];
			}
		);

		$endpoints = new Endpoints( new Context() );

		ob_start();
		$endpoints->handle_request();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
		$this->assertSame( $expected['force_404'], $wp_query->is_404 );
	}
}
