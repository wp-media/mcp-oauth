<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth\Discovery\HealthCheck;

use Mockery;
use WPMedia\MCP\OAuth\Auth\Discovery\HealthCheck;
use WPMedia\MCP\OAuth\Context;
use WPMedia\MCP\OAuth\Tests\Unit\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\Discovery\HealthCheck::add_test
 *
 * @covers \WPMedia\MCP\OAuth\Auth\Discovery\HealthCheck::add_test
 */
class AddTestTest extends TestCase {

	/**
	 * Registers exactly one `direct` Site Health test (never `async`), with `test` set to a
	 * callable, per the real WP core `site_status_tests` shape — while leaving any
	 * pre-existing tests (from other plugins/core) untouched.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldRegisterTestAccordingToExistingTests( array $config, array $expected ): void {
		$this->stubTranslationFunctions();

		$health_check = new HealthCheck( Mockery::mock( Context::class ) );

		$tests = $health_check->add_test( $config['existing'] );

		$this->assertCount( count( $config['existing']['direct'] ) + 1, $tests['direct'] );
		$this->assertCount( count( $config['existing']['async'] ), $tests['async'] );
		$this->assertArrayHasKey( HealthCheck::TEST_KEY, $tests['direct'] );

		$entry = $tests['direct'][ HealthCheck::TEST_KEY ];

		$this->assertArrayHasKey( 'label', $entry );
		$this->assertArrayHasKey( 'test', $entry );
		$this->assertIsCallable( $entry['test'] );
		$this->assertSame( [ $health_check, 'run_self_check' ], $entry['test'] );

		foreach ( $expected['preserved']['direct'] as $key ) {
			$this->assertArrayHasKey( $key, $tests['direct'] );
		}

		foreach ( $expected['preserved']['async'] as $key ) {
			$this->assertArrayHasKey( $key, $tests['async'] );
		}
	}
}
