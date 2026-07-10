<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth;

use Brain\Monkey\Functions;
use Mockery;
use WPMedia\MCP\OAuth\Auth\SecretManager;
use WPMedia\MCP\OAuth\Tests\Unit\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\SecretManager::get_secret
 *
 * @covers \WPMedia\MCP\OAuth\Auth\SecretManager::get_secret
 */
class GetSecretTest extends TestCase {

	/**
	 * Returns the stored secret, or generates and persists a new one when absent.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldReturnOrGenerateSecret( array $config, array $expected ): void {
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

		if ( $expected['expects_readback'] ) {
			Functions\expect( 'get_option' )
				->once()
				->with( SecretManager::OPTION_KEY )
				->andReturn( $config['readback_value'] );
		}

		$secret = SecretManager::get_secret();

		if ( $expected['returns_existing'] ) {
			$expected_value = '' !== $config['existing_option'] ? $config['existing_option'] : $config['readback_value'];
			$this->assertSame( $expected_value, $secret );
		} else {
			$this->assertSame( 1, preg_match( '/^[0-9a-f]{64}$/', $secret ) );
		}
	}
}
