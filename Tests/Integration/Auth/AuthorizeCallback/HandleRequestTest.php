<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth\AuthorizeCallback;

use RuntimeException;
use WPDieException;
use WPMedia\MCP\OAuth\Auth\AuthorizeCallback;
use WPMedia\MCP\OAuth\Tests\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\AuthorizeCallback::handle_request
 *
 * The success path ends in `render_consent_screen()`, whose last statement is
 * a bare `exit` after echoing the full HTML consent page. A bare `exit` kills
 * the PHPUnit process and there is no post-output hook to intercept it, so the
 * HTML body itself is intentionally NOT asserted here (would require a source
 * refactor, which is out of scope).
 *
 * `render_consent_screen()`'s first statement is `nocache_headers()`, which
 * would normally be the earliest interceptable point (it applies the
 * `nocache_headers` filter before any echo/exit). In THIS test environment,
 * however, `nocache_headers()` short-circuits via its own `headers_sent()`
 * guard: the WP test bootstrap ("Installing...", etc.) already writes
 * unbuffered output to STDOUT before any test runs, so `headers_sent()` is
 * permanently `true` for the whole process and the filter never fires.
 * The deepest point that *reliably* fires regardless of that is the
 * `language_attributes` filter, applied unconditionally by
 * `language_attributes()` a few bytes into the HTML output (`<html
 * <?php language_attributes(); ?>>`). The success test intercepts there
 * instead — still before the bulk of the page and the final `exit` — which is
 * enough to prove every guard passed and the render step was reached, plus
 * assert the pre-render side effect (the state transient TTL refresh). The
 * handful of literal bytes echoed before that point are swallowed via
 * `ob_start()`/`ob_end_clean()` so they don't trip
 * `beStrictAboutOutputDuringTests`.
 *
 * The guard branches (wp_die()) are fully covered: the base integration
 * TestCase (via WP_UnitTestCase) already registers a `wp_die_handler` filter
 * that throws WPDieException carrying the message and response code, so no
 * extra filter wiring is needed here.
 *
 * @covers \WPMedia\MCP\OAuth\Auth\AuthorizeCallback::handle_request
 */
class HandleRequestTest extends TestCase {

	/**
	 * Backup of $_GET so per-test mutations don't leak.
	 *
	 * @var array<string, mixed>
	 */
	private $get_backup;

	/**
	 * Backup of $_SERVER so per-test mutations don't leak.
	 *
	 * @var array<string, mixed>
	 */
	private $server_backup;

	/**
	 * Backs up superglobals.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->get_backup    = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- test-only backup of the superglobal, not form processing.
		$this->server_backup = $_SERVER;
	}

	/**
	 * Restores superglobals and the logged-in user.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$_GET    = $this->get_backup;
		$_SERVER = $this->server_backup;
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Rejects requests that fail a guard check before reaching the state transient
	 * lookup / consent render, via wp_die().
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldDieWhenAGuardCheckFails( array $config, array $expected ): void {
		$state = 'test-state-token';

		if ( $config['logged_in'] ) {
			$user_id = self::factory()->user->create();
			wp_set_current_user( $user_id );
		}

		if ( $config['set_state'] ) {
			$_GET['state'] = $state;
		}

		if ( $config['set_transient'] ) {
			set_transient(
				'mcp_oauth_state_' . $state,
				[
					'client_id'   => 'https://claude.ai/oauth/client',
					'client_name' => 'Claude',
					'client_uri'  => '',
					'verified'    => true,
					'publisher'   => 'claude',
				],
				60
			);
		}

		try {
			( new AuthorizeCallback() )->handle_request();
			$this->fail( 'Expected a WPDieException to be thrown.' );
		} catch ( WPDieException $exception ) {
			$this->assertSame( $expected['message'], $exception->getMessage() );
			$this->assertSame( $expected['code'], $exception->getCode() );
		}

		delete_transient( 'mcp_oauth_state_' . $state );
	}

	/**
	 * Passes all guards, refreshes the state transient TTL, and reaches
	 * render_consent_screen() when the user is logged in, a state parameter is
	 * present, and its matching transient holds valid client data.
	 *
	 * @return void
	 */
	public function testShouldReachRenderConsentScreenWhenAllGuardsPass(): void {
		$state     = 'test-state-token';
		$state_key = 'mcp_oauth_state_' . $state;

		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$_GET['state'] = $state;

		set_transient(
			$state_key,
			[
				'client_id'   => 'https://claude.ai/oauth/client',
				'client_name' => 'Claude',
				'client_uri'  => '',
				'verified'    => true,
				'publisher'   => 'claude',
			],
			60 // Original authorize-time TTL; handle_request() should refresh it to CONSENT_TTL.
		);

		// The 'language_attributes' filter is applied unconditionally by
		// language_attributes(), a few bytes into the HTML render — unlike
		// 'nocache_headers', which never fires in this environment because
		// nocache_headers()'s own headers_sent() guard is already tripped by
		// the WP test bootstrap's earlier unbuffered output (see class docblock).
		// Throwing here unwinds the call stack before the bulk of the page and
		// the bare `exit`, proving every guard passed and render was reached.
		// @phpstan-ignore-next-line return.missing -- callback intentionally never returns, it always throws.
		add_filter(
			'language_attributes',
			static function () {
				throw new RuntimeException( 'RENDER_REACHED' );
			}
		);

		// Swallow the handful of literal bytes ("<!DOCTYPE html>...<html ")
		// echoed before the filter fires, so they don't trip
		// beStrictAboutOutputDuringTests.
		ob_start();

		try {
			( new AuthorizeCallback() )->handle_request();
			$this->fail( 'Expected render_consent_screen() to be reached and throw.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'RENDER_REACHED', $exception->getMessage() );
		} finally {
			ob_end_clean();
		}

		$this->assertIsArray( get_transient( $state_key ), 'The state transient should still exist after handle_request().' );

		// The transient TTL was refreshed from the original 60 s window to
		// AuthorizeCallback::CONSENT_TTL (300 s); assert the stored expiry moved
		// well past what a mere 60 s window would allow.
		$timeout = (int) get_option( '_transient_timeout_' . $state_key );
		$this->assertGreaterThan( time() + 200, $timeout, 'The state transient TTL should have been refreshed to CONSENT_TTL.' );

		delete_transient( $state_key );
	}
}
