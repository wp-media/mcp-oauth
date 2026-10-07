<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Bootstrap;

use WPMedia\MCP\OAuth\Auth\SecretManager;
use WPMedia\MCP\OAuth\Bootstrap;
use WPMedia\PHPUnit\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Bootstrap::instance
 *
 * @covers \WPMedia\MCP\OAuth\Bootstrap::instance
 */
class InstanceTest extends TestCase {

	/**
	 * Defers wiring to 'plugins_loaded' when booted earlier, then wires the library there.
	 *
	 * The test bootstrap calls instance() on 'muplugins_loaded', before 'plugins_loaded'.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldDeferWiringToPluginsLoaded( array $config, array $expected ): void {
		$bootstrap = Bootstrap::instance();

		$this->assertSame( $expected['register_priority'], has_action( 'plugins_loaded', [ $bootstrap, 'register' ] ) );
		$this->assertSame( $expected['ensure_secret_priority'], has_action( 'init', [ SecretManager::class, 'ensure_secret' ] ) );
		$this->assertSame( $expected['flush_priority'], has_action( 'init', [ $bootstrap, 'maybe_flush_rewrite_rules' ] ) );
	}
}
