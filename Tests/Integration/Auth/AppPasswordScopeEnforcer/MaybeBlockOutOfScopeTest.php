<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth\AppPasswordScopeEnforcer;

use WP_Error;
use WPMedia\MCP\OAuth\Auth\AppPasswordScopeEnforcer;
use WPMedia\MCP\OAuth\Auth\TokenEndpoint;
use WPMedia\PHPUnit\Integration\TestCase;

/**
 * Driven against real WordPress Application Passwords, user meta, and the
 * $GLOBALS['wp']->query_vars route — the enforcer reads those plus the
 * $error/$user/$item the wp_authenticate_application_password_errors action passes.
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
	 * Backs up the WordPress globals this test manipulates.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->original_rest_route = $GLOBALS['wp']->query_vars['rest_route'] ?? null;
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

		parent::tear_down();
	}

	/**
	 * Builds the action arguments for a scenario: a fresh WP_Error, the
	 * authenticating user, and the Application Password record.
	 *
	 * @param array<string, mixed> $config Scenario configuration.
	 * @return array{0: WP_Error, 1: \WP_User, 2: array<string, mixed>}
	 */
	private function set_up_scenario( array $config ): array {
		$GLOBALS['wp']->query_vars['rest_route'] = 'mcp' === $config['route']
			? 'mcp/mcp-oauth-server'
			: 'wp/v2/users/me';

		$user_id = self::factory()->user->create();
		$user    = get_user_by( 'id', $user_id );

		$created = \WP_Application_Passwords::create_new_application_password( $user_id, [ 'name' => 'mcp-test' ] );
		$item    = $created[1];

		switch ( $config['ownership'] ) {
			case 'owned':
				update_user_meta( $user_id, TokenEndpoint::REFRESH_JTI_META_PREFIX . (string) $item['uuid'], 'some-jti' );
				break;

			case 'no_uuid':
				unset( $item['uuid'] );
				break;

			case 'foreign':
			default:
				// No ownership marker written.
				break;
		}

		return [ new WP_Error(), $user, $item ];
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
		[ $error, $user, $item ] = $this->set_up_scenario( $config );

		( new AppPasswordScopeEnforcer() )->maybe_block_out_of_scope( $error, $user, $item );

		if ( 'blocked' === $expected['type'] ) {
			$this->assertTrue( $error->has_errors() );
			$this->assertSame( 'mcp_oauth_app_password_out_of_scope', $error->get_error_code() );
			$this->assertSame( 401, $error->get_error_data( 'mcp_oauth_app_password_out_of_scope' )['status'] );

			return;
		}

		// 'allowed' means no error was added — authentication proceeds untouched.
		$this->assertFalse( $error->has_errors() );
	}
}
