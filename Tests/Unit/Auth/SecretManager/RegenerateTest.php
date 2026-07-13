<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth\SecretManager;

use Brain\Monkey\Functions;
use Mockery;
use WPMedia\MCP\OAuth\Auth\SecretManager;
use WPMedia\MCP\OAuth\Tests\Unit\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\SecretManager::regenerate
 *
 * @covers \WPMedia\MCP\OAuth\Auth\SecretManager::regenerate
 */
class RegenerateTest extends TestCase {

	/**
	 * Persists a fresh secret with autoload disabled, invalidating all current sessions.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldPersistFreshSecretWithAutoloadDisabled( array $config, array $expected ): void {
		Functions\expect( 'update_option' )
			->once()
			->with(
				SecretManager::OPTION_KEY,
				Mockery::on(
					static function ( $value ) {
						return 1 === preg_match( '/^[0-9a-f]{64}$/', $value );
					}
				),
				false
			)
			->andReturn( true );

		SecretManager::regenerate();
	}
}
