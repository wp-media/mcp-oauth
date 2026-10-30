<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth\Discovery\Endpoints;

use WPMedia\MCP\OAuth\Auth\Discovery\Endpoints;
use WPMedia\MCP\OAuth\Context;
use WPMedia\PHPUnit\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\Discovery\Endpoints::get_document
 *
 * Covers the two documents that handle_request() serves through
 * wp_send_json(), which its own Integration test has to skip.
 *
 * @covers \WPMedia\MCP\OAuth\Auth\Discovery\Endpoints::get_document
 */
class GetDocumentTest extends TestCase {

	/**
	 * Builds the requested document, also when the server is disabled.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed>      $config   Test configuration.
	 * @param array<string, mixed>|null $expected Expected document.
	 */
	public function testShouldBuildDocumentWithoutSendingIt( array $config, ?array $expected ): void {
		add_filter( 'wpmedia_mcp_oauth_server_enabled', '__return_false' );

		if ( null !== $expected ) {
			$home = home_url();
			$rest = get_rest_url( null, 'mcp/mcp-oauth-server' );

			array_walk_recursive(
				$expected,
				static function ( &$value ) use ( $home, $rest ): void {
					if ( is_string( $value ) ) {
						$value = str_replace( [ '{home}', '{rest}' ], [ $home, $rest ], $value );
					}
				}
			);
		}

		ob_start();
		$document = ( new Endpoints( new Context() ) )->get_document( $config['document'] );
		$output   = ob_get_clean();

		$this->assertSame( '', $output );
		$this->assertSame( $expected, $document );
	}
}
