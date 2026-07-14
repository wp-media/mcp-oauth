<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth\JWT;

use Brain\Monkey\Functions;
use WPMedia\MCP\OAuth\Auth\JWT;
use WPMedia\MCP\OAuth\Tests\Unit\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\JWT::encode
 *
 * @covers \WPMedia\MCP\OAuth\Auth\JWT::encode
 */
class EncodeTest extends TestCase {

	/**
	 * Encodes the given payload and verifies the resulting token structure.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldEncodePayloadAsValidSignedToken( array $config, array $expected ): void {
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

		$token = JWT::encode( $config['payload'], $config['secret'] );

		$segments = explode( '.', $token );

		$this->assertCount( 3, $segments );

		foreach ( $segments as $segment ) {
			$this->assertMatchesRegularExpression( '/^[A-Za-z0-9_-]*$/', $segment );
		}

		list( $header ) = $segments;

		$header_data = json_decode( (string) base64_decode( strtr( $header, '-_', '+/' ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		$this->assertSame(
			[
				'typ' => 'JWT',
				'alg' => 'HS256',
			],
			$header_data
		);

		$this->assertSame( $expected['payload'], JWT::decode( $token, $config['secret'] ) );
		$this->assertNull( JWT::decode( $token, 'a-completely-different-secret' ) );
	}
}
