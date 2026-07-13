<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth\SecretManager;

use WPMedia\MCP\OAuth\Auth\SecretManager;
use WPMedia\MCP\OAuth\Tests\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\SecretManager::get_secret
 *
 * The add_option() race-loss branch cannot be triggered against the real
 * options API from a single process; that branch is covered by the Unit
 * test instead, where add_option() is mocked to return false.
 *
 * @covers \WPMedia\MCP\OAuth\Auth\SecretManager::get_secret
 */
class GetSecretTest extends TestCase {

	/**
	 * Removes the option before each test so runs don't leak state.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		delete_option( SecretManager::OPTION_KEY );
	}

	/**
	 * Removes the option created during the test.
	 *
	 * @return void
	 */
	public function tear_down() {
		delete_option( SecretManager::OPTION_KEY );

		parent::tear_down();
	}

	/**
	 * Persists a secret and returns the same value on every subsequent call.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldPersistAndReturnStableSecret( array $config, array $expected ): void {
		if ( null !== $config['readback_value'] ) {
			$this->markTestSkipped( 'add_option() losing the race cannot be simulated against the real options API; covered by the Unit test instead.' );
		}

		if ( '' !== $config['existing_option'] ) {
			add_option( SecretManager::OPTION_KEY, $config['existing_option'], '', false );
		}

		$secret = SecretManager::get_secret();

		if ( $expected['returns_existing'] ) {
			$this->assertSame( $config['existing_option'], $secret );
		} else {
			$this->assertSame( 1, preg_match( '/^[0-9a-f]{64}$/', $secret ) );
		}

		$this->assertSame( $secret, get_option( SecretManager::OPTION_KEY ) );
		$this->assertSame( $secret, SecretManager::get_secret() );
	}
}
