<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth\Discovery\HealthCheck;

use Brain\Monkey\Functions;
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
	 * Registers exactly one `direct` Site Health test (never `async`, never two entries),
	 * with `test` set to a callable, per the real WP core `site_status_tests` shape.
	 *
	 * @return void
	 */
	public function testShouldRegisterExactlyOneDirectSiteHealthTest(): void {
		$this->stubTranslationFunctions();

		$health_check = new HealthCheck( Mockery::mock( Context::class ) );

		$tests = $health_check->add_test(
			[
				'direct' => [],
				'async'  => [],
			]
		);

		$this->assertCount( 1, $tests['direct'] );
		$this->assertArrayHasKey( HealthCheck::TEST_KEY, $tests['direct'] );
		$this->assertCount( 0, $tests['async'] );

		$entry = $tests['direct'][ HealthCheck::TEST_KEY ];

		$this->assertArrayHasKey( 'label', $entry );
		$this->assertArrayHasKey( 'test', $entry );
		$this->assertIsCallable( $entry['test'] );
		$this->assertSame( [ $health_check, 'run_self_check' ], $entry['test'] );
	}

	/**
	 * Leaves any existing tests (from other plugins/core) untouched.
	 *
	 * @return void
	 */
	public function testShouldPreserveExistingTestsWhenAddingItsOwn(): void {
		$this->stubTranslationFunctions();

		$health_check = new HealthCheck( Mockery::mock( Context::class ) );

		$existing = [
			'direct' => [
				'php_version' => [
					'label' => 'PHP Version',
					'test'  => 'php_version',
				],
			],
			'async'  => [
				'https_status' => [
					'label' => 'HTTPS status',
					'test'  => 'https://example.org/wp-json/wp-site-health/v1/tests/https-status',
				],
			],
		];

		$tests = $health_check->add_test( $existing );

		$this->assertArrayHasKey( 'php_version', $tests['direct'] );
		$this->assertArrayHasKey( 'https_status', $tests['async'] );
		$this->assertArrayHasKey( HealthCheck::TEST_KEY, $tests['direct'] );
	}
}
