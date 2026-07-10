<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth;

use Brain\Monkey\Functions;
use Mockery;
use WPMedia\MCP\OAuth\Auth\SecretManager;
use WPMedia\MCP\OAuth\Tests\Unit\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\SecretManager::ensure_secret
 *
 * @covers \WPMedia\MCP\OAuth\Auth\SecretManager::ensure_secret
 */
class EnsureSecretTest extends TestCase {

	/**
	 * Delegates to get_secret() so a secret is created on first activation only.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldDelegateToGetSecret( array $config, array $expected ): void {
		Functions\expect( 'get_option' )
			->once()
			->with( SecretManager::OPTION_KEY, '' )
			->andReturn( $config['existing_option'] );

		if ( $expected['expects_add_option'] ) {
			Functions\expect( 'add_option' )
				->once()
				->with(
					SecretManager::OPTION_KEY,
					Mockery::on(
						static function ( $value ) {
							return 1 === preg_match( '/^[0-9a-f]{64}$/', $value );
						}
					),
					'',
					false
				)
				->andReturn( $config['add_option_result'] );
		} else {
			Functions\expect( 'add_option' )->never();
		}

		SecretManager::ensure_secret();
	}
}
