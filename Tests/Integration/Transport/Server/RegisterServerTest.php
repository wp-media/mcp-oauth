<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Transport\Server;

use WP\MCP\Core\McpAdapter;
use WPMedia\MCP\OAuth\Context;
use WPMedia\MCP\OAuth\Transport\Server;
use WPMedia\PHPUnit\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Transport\Server::register_server
 *
 * `mcp_adapter_init` is a public action a third party can re-fire; a second
 * firing must not cause a duplicate create_server() call for the same ID.
 * No setExpectedIncorrectUsage() is registered, so a regression surfaces
 * through WP_UnitTestCase's native failure on an unexpected _doing_it_wrong().
 *
 * @covers \WPMedia\MCP\OAuth\Transport\Server::register_server
 */
class RegisterServerTest extends TestCase {

	/**
	 * Fires `mcp_adapter_init` twice and asserts the server survives both
	 * firings without triggering a duplicate-server-id notice.
	 *
	 * The production `ServerRegistrar` hook wired by `Bootstrap` on the same
	 * action is also still attached and will run here too; that is harmless
	 * and does not need to be unhooked, since it is guarded the same way.
	 *
	 * @return void
	 */
	public function testShouldRegisterServerOnlyOnceWhenActionFiresTwice(): void {
		$adapter = McpAdapter::instance();
		$server  = new Server( new Context() );

		add_action(
			'mcp_adapter_init',
			static function () use ( $server ) {
				$server->register_server();
			}
		);

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- firing the mcp-adapter package's own action to simulate a third-party re-fire, not defining a new hook.
		do_action( 'mcp_adapter_init', $adapter );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- firing the mcp-adapter package's own action to simulate a third-party re-fire, not defining a new hook.
		do_action( 'mcp_adapter_init', $adapter );

		$this->assertNotNull( $adapter->get_server( 'mcp-oauth-server' ) );
	}
}
