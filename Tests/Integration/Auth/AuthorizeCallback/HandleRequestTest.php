<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth\AuthorizeCallback;

use ReflectionMethod;
use RuntimeException;
use WPDieException;
use WPMedia\MCP\OAuth\Auth\AuthorizeCallback;
use WPMedia\MCP\OAuth\Tests\Integration\TestCase;
use WPMedia\MCP\OAuth\Views\Render;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\AuthorizeCallback::handle_request
 *
 * The success path ends in `render_consent_screen()`, whose last statement is
 * a bare `exit` after delegating to `output_consent_screen()`. A bare `exit`
 * kills the PHPUnit process and there is no post-output hook to intercept it,
 * so `handle_request()` itself still cannot assert the rendered HTML body —
 * this test covers `handle_request()`'s guard checks and render-wiring only.
 *
 * `render_consent_screen()`'s first statement (via `output_consent_screen()`)
 * is `nocache_headers()`, which would normally be the earliest interceptable
 * point (it applies the `nocache_headers` filter before any echo/exit). In
 * THIS test environment, however, `nocache_headers()` short-circuits via its
 * own `headers_sent()` guard: the WP test bootstrap ("Installing...", etc.)
 * already writes unbuffered output to STDOUT before any test runs, so
 * `headers_sent()` is permanently `true` for the whole process and the filter
 * never fires. The deepest point that *reliably* fires regardless of that is
 * the `language_attributes` filter, applied unconditionally by
 * `language_attributes()` a few bytes into the HTML output (`<html
 * <?php language_attributes(); ?>>`). The 'render' case intercepts there
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
 * Because `render_consent_screen()` was split (issue #19) into a two-line
 * `exit`-wrapping method and an `exit`-free `output_consent_screen()`, the
 * actual rendered HTML content — title, client name/link, client ID, verified
 * badge, scope sentence, hidden state field, nonce field, Allow/Deny buttons —
 * is asserted separately in `testShouldRenderConsentScreenContent()` below by
 * invoking `output_consent_screen()` directly via `ReflectionMethod`, with no
 * `exit`/filter-interception workaround needed. Both tests exist side by
 * side: this one proves `handle_request()`'s guards and state-transient
 * wiring reach the render step; the other proves the rendered content itself
 * is correct.
 *
 * @covers \WPMedia\MCP\OAuth\Auth\AuthorizeCallback::handle_request
 * @covers \WPMedia\MCP\OAuth\Auth\AuthorizeCallback::output_consent_screen
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
	 * Handles a callback request according to the given configuration: dies via
	 * wp_die() when a guard check fails, or reaches render_consent_screen()
	 * (refreshing the state transient TTL) when every guard passes.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldHandleRequestAccordingToConfig( array $config, array $expected ): void {
		$state     = 'test-state-token';
		$state_key = 'mcp_oauth_state_' . $state;

		if ( $config['logged_in'] ) {
			$user_id = self::factory()->user->create();
			wp_set_current_user( $user_id );
		}

		if ( $config['set_state'] ) {
			$_GET['state'] = $state;
		}

		if ( $config['set_transient'] ) {
			set_transient(
				$state_key,
				[
					'client_id'   => 'https://claude.ai/oauth/client',
					'client_name' => 'Claude',
					'client_uri'  => '',
					'verified'    => true,
					'publisher'   => 'claude',
				],
				60 // Original authorize-time TTL; a 'render' outcome expects handle_request() to refresh it to CONSENT_TTL.
			);
		}

		if ( 'die' === $expected['outcome'] ) {
			try {
				( new AuthorizeCallback( new Render() ) )->handle_request();
				$this->fail( 'Expected a WPDieException to be thrown.' );
			} catch ( WPDieException $exception ) {
				$this->assertSame( $expected['message'], $exception->getMessage() );
				$this->assertSame( $expected['code'], $exception->getCode() );
			}

			delete_transient( $state_key );

			return;
		}

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
			( new AuthorizeCallback( new Render() ) )->handle_request();
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

	/**
	 * Client/expectation pairs for testShouldRenderConsentScreenContent(),
	 * covering both the verified-publisher-badge-present and -absent cases.
	 *
	 * This is a dedicated local data provider, not the shared
	 * `TestCase::configTestData()` fixture-file convention used by
	 * testShouldHandleRequestAccordingToConfig() above — that convention loads
	 * a file matching this class's own name/path, and reusing it here would
	 * force unrelated die-outcome rows to also carry consent-screen content
	 * expectations they have no use for.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function consentScreenClientProvider(): array {
		return [
			'verified publisher'   => [
				'client'   => [
					'client_id'   => 'https://claude.ai/oauth/client',
					'client_name' => 'Claude',
					'client_uri'  => 'https://claude.ai',
					'verified'    => true,
					'publisher'   => 'Anthropic',
				],
				'expected' => [
					'verified_badge_present' => true,
				],
			],
			'unverified publisher' => [
				'client'   => [
					'client_id'   => 'https://example.com/oauth/client',
					'client_name' => 'Example Client',
					'client_uri'  => '',
					'verified'    => false,
					'publisher'   => '',
				],
				'expected' => [
					'verified_badge_present' => false,
				],
			],
		];
	}

	/**
	 * Asserts the actual rendered consent-screen content, invoking the
	 * now-`exit`-free `output_consent_screen()` directly via `ReflectionMethod`
	 * (PHP 8.1+ allows invoking private methods without `setAccessible()` — see
	 * `Tests/Integration/Transport/OAuthHttpTransport/HandleRequestTest.php`).
	 *
	 * @dataProvider consentScreenClientProvider
	 *
	 * @param array<string, mixed> $client   CIMD client record passed to output_consent_screen().
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldRenderConsentScreenContent( array $client, array $expected ): void {
		$state       = 'test-state-token';
		$site_name   = (string) get_bloginfo( 'name' );
		$callback    = new AuthorizeCallback( new Render() );
		$method      = new ReflectionMethod( AuthorizeCallback::class, 'output_consent_screen' );
		$display_uri = '' !== $client['client_uri'] ? $client['client_uri'] : $client['client_id'];

		ob_start();
		$method->invoke( $callback, $state, $client );
		$html = ob_get_clean();

		$this->assertStringContainsString( '<title>Authorize Access — ' . $site_name . '</title>', $html );
		$this->assertStringContainsString( '<h1>Authorize access to your site?</h1>', $html );
		$this->assertStringContainsString( '<a href="' . esc_url( $display_uri ) . '" rel="noopener noreferrer" target="_blank">' . esc_html( $client['client_name'] ) . '</a>', $html );
		$this->assertStringContainsString( '<a href="' . esc_url( $client['client_id'] ) . '" rel="noopener noreferrer" target="_blank">' . esc_html( $client['client_id'] ) . '</a>', $html );

		if ( $expected['verified_badge_present'] ) {
			$this->assertStringContainsString( '<div class="verified-badge">', $html );
			$this->assertStringContainsString( 'Verified publisher: ' . $client['publisher'], $html );
		} else {
			$this->assertStringNotContainsString( '<div class="verified-badge">', $html );
		}

		$this->assertStringContainsString( '<strong>' . $client['client_name'] . '</strong> is requesting access to the MCP tools on <strong>' . $site_name . '</strong> on your behalf.', $html );
		$this->assertStringContainsString( '<input type="hidden" name="state" value="' . $state . '">', $html );
		$this->assertStringContainsString( 'name="mcp_consent_nonce"', $html );
		$this->assertStringContainsString( '<button type="submit" name="mcp_action" value="allow" class="btn btn-allow">', $html );
		$this->assertStringContainsString( '<button type="submit" name="mcp_action" value="deny" class="btn btn-deny">', $html );
	}
}
