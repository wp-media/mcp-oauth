<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth\Discovery\HealthCheck;

use WPMedia\MCP\OAuth\Auth\Discovery\HealthCheck;
use WPMedia\MCP\OAuth\Context;
use WPMedia\PHPUnit\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\Discovery\HealthCheck::add_test
 *
 * Confirms the `site_status_tests` filter round-trips through real WP core
 * Site Health internals (`WP_Site_Health::get_tests()`) without fatals, and
 * that the registered `direct` test actually executes synchronously — no
 * ajax round-trip, unlike an `async` test.
 *
 * @covers \WPMedia\MCP\OAuth\Auth\Discovery\HealthCheck::add_test
 */
class AddTestTest extends TestCase {

	/**
	 * Loads WP_Site_Health, which core only autoloads on wp-admin requests.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		if ( ! class_exists( 'WP_Site_Health' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
		}
	}

	/**
	 * Registers a real, callable `direct` Site Health test that runs synchronously.
	 *
	 * This is a plain, unparameterized test method (no shared fixture-driven data
	 * provider): HealthCheck::add_test's unit test (above) and this integration test
	 * exercise genuinely different scenarios with incompatible config/expected shapes
	 * (array-merging vs. a real Site Health round-trip), and the shared TestCase's
	 * fixture loader resolves both Unit and Integration suites to the identical
	 * `Tests/Fixtures/.../AddTestTest.php` path — so they cannot each have their own
	 * fixture under this file name. See the unit test's fixture for the scenario this
	 * class name already "owns".
	 *
	 * @return void
	 */
	public function testShouldRegisterDirectTestThatExecutesSynchronously(): void {
		// Short-circuits run_self_check() before any loopback request, so this
		// stays fast and network-free while still exercising the real
		// site_status_tests filter round-trip end to end.
		add_filter( 'wpmedia_mcp_oauth_server_enabled', '__return_false' );

		$health_check = new HealthCheck( new Context() );
		add_filter( 'site_status_tests', [ $health_check, 'add_test' ] );

		$tests = \WP_Site_Health::get_tests();

		$this->assertArrayHasKey( HealthCheck::TEST_KEY, $tests['direct'] );
		$this->assertArrayNotHasKey( HealthCheck::TEST_KEY, $tests['async'] );

		$entry = $tests['direct'][ HealthCheck::TEST_KEY ];
		$this->assertIsCallable( $entry['test'] );

		$result = call_user_func( $entry['test'] );

		$this->assertIsArray( $result );
		$this->assertSame( 'good', $result['status'] );
		$this->assertSame( HealthCheck::TEST_KEY, $result['test'] );
	}
}
