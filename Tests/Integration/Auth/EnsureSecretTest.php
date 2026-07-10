<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth;

use WPMedia\MCP\OAuth\Auth\SecretManager;
use WPMedia\MCP\OAuth\Tests\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\SecretManager::ensure_secret
 *
 * @covers \WPMedia\MCP\OAuth\Auth\SecretManager::ensure_secret
 */
class EnsureSecretTest extends TestCase {

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
	 * Creates the option on first activation, and leaves an existing one untouched.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldEnsureSecretOptionExists( array $config, array $expected ): void {
		if ( '' !== $config['existing_option'] ) {
			add_option( SecretManager::OPTION_KEY, $config['existing_option'], '', false );
		}

		SecretManager::ensure_secret();

		$stored = get_option( SecretManager::OPTION_KEY );

		if ( $expected['expects_add_option'] ) {
			$this->assertSame( 1, preg_match( '/^[0-9a-f]{64}$/', $stored ) );
		} else {
			$this->assertSame( $config['existing_option'], $stored );
		}
	}
}
