<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth\Discovery;

use Brain\Monkey\Functions;
use Mockery;
use WPMedia\MCP\OAuth\Auth\Discovery\Endpoints;
use WPMedia\MCP\OAuth\Context;
use WPMedia\MCP\OAuth\Tests\Unit\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\Discovery\Endpoints::handle_request
 *
 * @covers \WPMedia\MCP\OAuth\Auth\Discovery\Endpoints::handle_request
 */
class HandleRequestTest extends TestCase {

	/**
	 * Backup of the superglobal so per-test mutations don't leak.
	 *
	 * @var array<string, mixed>
	 */
	private $server_backup;

	/**
	 * Backs up $_SERVER and strips the keys read by handle_request().
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		$this->server_backup = $_SERVER;
		unset( $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] );
	}

	/**
	 * Restores $_SERVER.
	 *
	 * @return void
	 */
	protected function tear_down(): void {
		$_SERVER = $this->server_backup;

		parent::tear_down();
	}

	/**
	 * Handles a discovery request according to the given context.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldHandleRequestAccordingToContext( array $config, array $expected ): void {
		$context = Mockery::mock( Context::class );

		Functions\expect( 'get_query_var' )
			->once()
			->with( Endpoints::QUERY_VAR, '' )
			->andReturn( $config['discovery'] );

		if ( ! $expected['check_enabled'] ) {
			$context->shouldNotReceive( 'is_enabled' );
		} else {
			$context->shouldReceive( 'is_enabled' )->once()->andReturn( $config['is_enabled'] );
		}

		if ( $expected['force_404'] ) {
			global $wp_query;
			$wp_query = Mockery::mock(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$wp_query->shouldReceive( 'set_404' )->once();
			Functions\expect( 'status_header' )->once()->with( 404 );
		} else {
			Functions\expect( 'status_header' )->never();
		}

		if ( $expected['check_enabled'] && $config['is_enabled'] ) {
			Functions\expect( 'home_url' )->once()->andReturn( 'https://example.org' );
			Functions\when( 'wp_json_encode' )->justReturn( '{}' );
		}

		if ( null !== $expected['sent_json'] ) {
			if ( 'protected-resource' === $config['discovery'] ) {
				Functions\expect( 'get_rest_url' )
					->once()
					->with( null, 'mcp/mcp-oauth-server' )
					->andReturn( 'https://example.org/wp-json/mcp/mcp-oauth-server' );
			}

			Functions\expect( 'wp_send_json' )->once()->with( $expected['sent_json'] );
		} else {
			Functions\expect( 'wp_send_json' )->never();
		}

		( new Endpoints( $context ) )->handle_request();

		if ( $expected['force_404'] ) {
			global $wp_query;
			$wp_query = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
	}
}
