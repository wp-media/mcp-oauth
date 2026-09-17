<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth\AppPasswordScopeEnforcer;

use WP_Error;
use WPMedia\MCP\OAuth\Auth\AppPasswordScopeEnforcer;
use WPMedia\MCP\OAuth\Auth\TokenEndpoint;
use WPMedia\PHPUnit\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\AppPasswordScopeEnforcer::maybe_block_out_of_scope.
 *
 * Exercised against real WordPress state (Application Passwords, user meta,
 * the current-user global, and the $GLOBALS['wp']->query_vars route) rather
 * than a full REST dispatch, since the method only reads those globals.
 *
 * @covers \WPMedia\MCP\OAuth\Auth\AppPasswordScopeEnforcer::maybe_block_out_of_scope
 */
class MaybeBlockOutOfScopeTest extends TestCase {

	/**
	 * Backed-up $GLOBALS['wp']->query_vars['rest_route'] value.
	 *
	 * @var mixed
	 */
	private $original_rest_route;

	/**
	 * Backed-up $wp_rest_application_password_uuid global value.
	 *
	 * @var string|null
	 */
	private $original_app_password_uuid;

	/**
	 * Backs up the WordPress globals this test manipulates.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->original_rest_route = $GLOBALS['wp']->query_vars['rest_route'] ?? null;

		global $wp_rest_application_password_uuid;
		$this->original_app_password_uuid = $wp_rest_application_password_uuid;
	}

	/**
	 * Restores the WordPress globals this test manipulated.
	 *
	 * @return void
	 */
	public function tear_down() {
		if ( null === $this->original_rest_route ) {
			unset( $GLOBALS['wp']->query_vars['rest_route'] );
		} else {
			$GLOBALS['wp']->query_vars['rest_route'] = $this->original_rest_route;
		}

		global $wp_rest_application_password_uuid;
		$wp_rest_application_password_uuid = $this->original_app_password_uuid; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- restoring WP core's own global, not defining a new one.

		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Simulates the request state for a scenario and returns the value to
	 * pass in as $result.
	 *
	 * @param array<string, mixed> $config Scenario configuration.
	 * @return mixed
	 */
	private function set_up_scenario( array $config ) {
		$GLOBALS['wp']->query_vars['rest_route'] = 'mcp' === $config['route']
			? 'mcp/mcp-oauth-server'
			: 'wp/v2/users/me';

		global $wp_rest_application_password_uuid;
		$wp_rest_application_password_uuid = null; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- simulating WP core's own global, not defining a new one.

		switch ( $config['user'] ) {
			case 'owned':
				$user_id = self::factory()->user->create();
				$created = \WP_Application_Passwords::create_new_application_password( $user_id, [ 'name' => 'mcp-test' ] );
				$uuid    = (string) $created[1]['uuid'];
				update_user_meta( $user_id, TokenEndpoint::REFRESH_JTI_META_PREFIX . $uuid, 'some-jti' );
				wp_set_current_user( $user_id );
				$wp_rest_application_password_uuid = $uuid; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- simulating WP core's own global, not defining a new one.
				break;

			case 'foreign':
				$user_id = self::factory()->user->create();
				$created = \WP_Application_Passwords::create_new_application_password( $user_id, [ 'name' => 'foreign-app' ] );
				wp_set_current_user( $user_id );
				$wp_rest_application_password_uuid = (string) $created[1]['uuid']; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- simulating WP core's own global, not defining a new one.
				break;

			case 'authenticated':
				$user_id = self::factory()->user->create();
				wp_set_current_user( $user_id );
				break;

			case 'none':
			default:
				wp_set_current_user( 0 );
				break;
		}

		switch ( $config['incoming_result'] ) {
			case 'wp_error':
				return new WP_Error( 'some_other_error', 'Pre-existing error.' );

			case 'true':
				return true;

			case 'null':
			default:
				return null;
		}
	}

	/**
	 * Exercises every gate of maybe_block_out_of_scope(), driven by the
	 * scenario data in the sibling fixture file.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testMaybeBlockOutOfScopeAccordingToConfig( array $config, array $expected ): void {
		$incoming_result = $this->set_up_scenario( $config );

		$result = ( new AppPasswordScopeEnforcer() )->maybe_block_out_of_scope( $incoming_result );

		if ( 'blocked' === $expected['type'] ) {
			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertSame( 'mcp_oauth_app_password_out_of_scope', $result->get_error_code() );
			$this->assertSame( 401, $result->get_error_data()['status'] );

			return;
		}

		// 'allowed' and 'unchanged' both mean the incoming value is returned untouched.
		$this->assertSame( $incoming_result, $result );
	}
}
