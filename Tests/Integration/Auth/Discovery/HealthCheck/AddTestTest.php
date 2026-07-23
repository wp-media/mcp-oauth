<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth\Discovery\HealthCheck;

use WPMedia\MCP\OAuth\Auth\Discovery\HealthCheck;
use WPMedia\MCP\OAuth\Context;
use WPMedia\MCP\OAuth\Tests\Integration\TestCase;

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
			// ABSPATH is a WordPress-defined runtime constant absent from the
			// stub packages PHPStan scans, so it statically resolves this path
			// incorrectly (a false positive, not a real missing file).
			require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php'; // @phpstan-ignore requireOnce.fileNotFound
		}
	}

	/**
	 * Registers a real, callable `direct` Site Health test that runs synchronously.
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

		// Calling the callable directly (as WP_Site_Health::perform_test() does
		// for 'direct' tests) proves it runs synchronously: no ajax action, no
		// REST round-trip, just a plain function call returning the result.
		$result = call_user_func( $entry['test'] );

		$this->assertIsArray( $result );
		$this->assertSame( 'good', $result['status'] );
		$this->assertSame( HealthCheck::TEST_KEY, $result['test'] );
	}
}
