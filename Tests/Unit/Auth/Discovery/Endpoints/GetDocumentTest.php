<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth\Discovery\Endpoints;

use Brain\Monkey\Functions;
use Mockery;
use WPMedia\MCP\OAuth\Auth\Discovery\Endpoints;
use WPMedia\MCP\OAuth\Context;
use WPMedia\PHPUnit\Unit\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\Discovery\Endpoints::get_document
 *
 * @covers \WPMedia\MCP\OAuth\Auth\Discovery\Endpoints::get_document
 */
class GetDocumentTest extends TestCase {

	/**
	 * Builds the requested document without sending it or checking the context.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed>      $config   Test configuration.
	 * @param array<string, mixed>|null $expected Expected document.
	 */
	public function testShouldBuildDocumentWithoutSendingIt( array $config, ?array $expected ): void {
		$home = 'https://example.org';
		$rest = 'https://example.org/wp-json/mcp/mcp-oauth-server';

		$context = Mockery::mock( Context::class );
		$context->shouldNotReceive( 'is_enabled' );

		Functions\expect( 'home_url' )->once()->andReturn( $home );

		if ( 'protected-resource' === $config['document'] ) {
			Functions\expect( 'get_rest_url' )
				->once()
				->with( null, 'mcp/mcp-oauth-server' )
				->andReturn( $rest );
		} else {
			Functions\expect( 'get_rest_url' )->never();
		}

		Functions\expect( 'wp_send_json' )->never();

		if ( null !== $expected ) {
			array_walk_recursive(
				$expected,
				static function ( &$value ) use ( $home, $rest ): void {
					if ( is_string( $value ) ) {
						$value = str_replace( [ '{home}', '{rest}' ], [ $home, $rest ], $value );
					}
				}
			);
		}

		$this->assertSame( $expected, ( new Endpoints( $context ) )->get_document( $config['document'] ) );
	}
}
