<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth;

use Brain\Monkey\Functions;
use WPMedia\MCP\OAuth\Auth\JWT;
use WPMedia\MCP\OAuth\Tests\Unit\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\JWT::decode
 *
 * @covers \WPMedia\MCP\OAuth\Auth\JWT::decode
 */
class DecodeTest extends TestCase {

	/**
	 * Decodes a token built and optionally tampered according to the given
	 * configuration, and verifies the resulting payload.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldDecodeTokenAccordingToItsState( array $config, array $expected ): void {
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

		$token = JWT::encode( $config['payload'], $config['encode_secret'] );
		$token = $this->apply_tamper( $token, $config['tamper'], $config['encode_secret'] );

		$this->assertSame(
			$expected['result'],
			JWT::decode( $token, $config['decode_secret'], $config['verify_expiry'] )
		);
	}

	/**
	 * Applies the requested tampering strategy to an otherwise valid token.
	 *
	 * @param string      $token  Valid JWT string.
	 * @param string|null $tamper Tampering strategy to apply, or null for none.
	 * @param string      $secret Secret used to (re)sign the token when needed.
	 * @return string Possibly tampered JWT string.
	 */
	private function apply_tamper( string $token, ?string $tamper, string $secret ): string {
		if ( null === $tamper ) {
			return $token;
		}

		$parts = explode( '.', $token );

		if ( 'signature' === $tamper ) {
			list( $header, $body, $signature ) = $parts;

			$tampered_signature = strrev( $signature );
			if ( $tampered_signature === $signature ) {
				$tampered_signature .= 'x';
			}

			return $header . '.' . $body . '.' . $tampered_signature;
		}

		if ( 'alg_none' === $tamper ) {
			return $this->rebuild_with_alg( $parts[1], 'none', $secret );
		}

		if ( 'alg_rs256' === $tamper ) {
			return $this->rebuild_with_alg( $parts[1], 'RS256', $secret );
		}

		if ( 'too_few_parts' === $tamper ) {
			return $parts[0] . '.' . $parts[1];
		}

		if ( 'too_many_parts' === $tamper ) {
			return $token . '.extra';
		}

		return $token;
	}

	/**
	 * Rebuilds a token with the given body but a different header algorithm.
	 *
	 * @param string $body   Base64URL-encoded token body.
	 * @param string $alg    Algorithm to set in the header.
	 * @param string $secret Secret used to sign the rebuilt token.
	 * @return string Rebuilt JWT string.
	 */
	private function rebuild_with_alg( string $body, string $alg, string $secret ): string {
		$header = JWT::base64url_encode(
			(string) wp_json_encode(
				[
					'typ' => 'JWT',
					'alg' => $alg,
				]
			)
		);

		$signature = JWT::base64url_encode( hash_hmac( 'sha256', $header . '.' . $body, $secret, true ) );

		return $header . '.' . $body . '.' . $signature;
	}
}
