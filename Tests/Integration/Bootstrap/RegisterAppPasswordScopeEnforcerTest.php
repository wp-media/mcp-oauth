<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Bootstrap;

use WPMedia\MCP\OAuth\Auth\AppPasswordScopeEnforcer;
use WPMedia\PHPUnit\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Bootstrap::register_app_password_scope_enforcer
 *
 * Bootstrap::instance() (and therefore register()) has already run once for
 * the whole test process (Tests/Integration/bootstrap.php, on
 * 'muplugins_loaded'), so this asserts against the resulting global filter
 * state rather than re-invoking the singleton.
 *
 * @covers \WPMedia\MCP\OAuth\Bootstrap::register_app_password_scope_enforcer
 */
class RegisterAppPasswordScopeEnforcerTest extends TestCase {

	/**
	 * A missing wiring would silently disable the whole Application Password
	 * scoping protection, so this is asserted explicitly rather than relying
	 * on the enforcer's own unit coverage.
	 *
	 * @return void
	 */
	public function testShouldRegisterEnforcerOnRestAuthenticationErrors(): void {
		$this->assertNotFalse( has_filter( 'rest_authentication_errors' ) );

		global $wp_filter;

		$found = false;

		if ( isset( $wp_filter['rest_authentication_errors'] ) ) {
			foreach ( $wp_filter['rest_authentication_errors']->callbacks as $callbacks ) {
				foreach ( $callbacks as $callback ) {
					$function = $callback['function'];

					if ( is_array( $function ) && $function[0] instanceof AppPasswordScopeEnforcer && 'maybe_block_out_of_scope' === $function[1] ) {
						$found = true;
					}
				}
			}
		}

		$this->assertTrue( $found, 'AppPasswordScopeEnforcer::maybe_block_out_of_scope is not registered on rest_authentication_errors.' );
	}
}
