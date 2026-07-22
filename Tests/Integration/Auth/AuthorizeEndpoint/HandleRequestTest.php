<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth\AuthorizeEndpoint;

use RuntimeException;
use WPDieException;
use WPMedia\MCP\OAuth\Auth\AuthorizeEndpoint;
use WPMedia\MCP\OAuth\Auth\ClaudeClientVerifier;
use WPMedia\MCP\OAuth\Auth\CimdResolver;
use WPMedia\MCP\OAuth\Tests\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\AuthorizeEndpoint::handle_request
 *
 * `handle_request()` terminates every branch via either `wp_die()` (caught
 * here as `WPDieException`, which the WP test suite's base `wp_die_handler`
 * throws instead of exiting) or `wp_redirect(); exit;` (intercepted by
 * throwing from a `wp_redirect` filter callback before the following `exit`
 * runs).
 *
 * A real `CimdResolver`/`ClaudeClientVerifier` pair is used throughout (no
 * mocking of the collaborator under it): the trusted-publisher allowlist is
 * controlled via the `wpmedia_mcp_oauth_trusted_publishers` filter, and the
 * document fetch is stubbed via `pre_http_request` so no real network fetch
 * ever occurs. `pre_http_request` short-circuits before WP_Http's URL/host
 * validation, so plain `*.example` test hosts are safe to use here.
 *
 * @covers \WPMedia\MCP\OAuth\Auth\AuthorizeEndpoint::handle_request
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
	 * Backs up $_GET/$_SERVER.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->get_backup    = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->server_backup = $_SERVER;
		unset( $_SERVER['REMOTE_ADDR'] );
	}

	/**
	 * Restores $_GET/$_SERVER.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$_GET    = $this->get_backup; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$_SERVER = $this->server_backup;
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Populates $_GET for the test, returning the same values as a local
	 * array so assertions never need to read the superglobal back.
	 *
	 * @param array<string, mixed> $get Values to place in $_GET.
	 * @return array<string, string> The same values, for use in assertions.
	 */
	private function set_get( array $get ): array {
		$_GET = $get; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return $get;
	}

	/**
	 * Registers a trusted publisher, gating `CimdResolver`'s SSRF host check
	 * and `ClaudeClientVerifier`'s verification match.
	 *
	 * @param string   $host        The client_id host to trust.
	 * @param string[] $client_ids  The exact client_id URLs considered verified for this host.
	 * @return void
	 */
	private function install_trusted_publisher( string $host, array $client_ids ): void {
		add_filter(
			'wpmedia_mcp_oauth_trusted_publishers',
			static function () use ( $host, $client_ids ) {
				return [
					'test' => [
						'client_ids' => $client_ids,
						'host'       => $host,
					],
				];
			}
		);
	}

	/**
	 * Stubs the CIMD document fetch for a given client_id via `pre_http_request`,
	 * so `CimdResolver::resolve()` runs its real fetch/validate/verify logic
	 * without making a real network request.
	 *
	 * @param string               $client_id The client_id URL the document is served from.
	 * @param array<string, mixed> $overrides Overrides for the default valid document.
	 * @return void
	 */
	private function install_cimd_document( string $client_id, array $overrides = [] ): void {
		$doc = array_merge(
			[
				'client_id'                  => $client_id,
				'client_name'                => 'Example App',
				'client_uri'                 => 'https://client.example',
				'redirect_uris'              => [ 'https://client.example/callback' ],
				'token_endpoint_auth_method' => 'none',
			],
			$overrides
		);

		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) use ( $client_id, $doc ) {
				if ( $url !== $client_id ) {
					return $preempt;
				}

				return [
					'headers'  => [ 'content-type' => 'application/json' ],
					'body'     => wp_json_encode( $doc ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
					'filename' => null,
				];
			},
			10,
			3
		);
	}

	/**
	 * Builds a CimdResolver whose native cURL connect-only preflight is stubbed
	 * to report a fixed public IP.
	 *
	 * `CimdResolver::connect_and_get_ip()` performs a raw `curl_exec()` that
	 * `pre_http_request` cannot intercept and that would legitimately fail
	 * against the reserved `*.example` test hosts these scenarios use. Overriding
	 * it to return a public IP lets the real `AuthorizeEndpoint` client-resolution
	 * logic run unchanged while the document fetch stays faked via
	 * `install_cimd_document()`. No real network connection is made.
	 *
	 * @return CimdResolver
	 */
	private function make_resolver(): CimdResolver {
		return new class( new ClaudeClientVerifier() ) extends CimdResolver {

			/**
			 * Returns a fixed public IP instead of a real cURL connect.
			 *
			 * @param string      $host      The client_id URL host.
			 * @param string|null $ca_bundle Unused; the real preflight is stubbed.
			 * @return string
			 */
			protected function connect_and_get_ip( string $host, ?string $ca_bundle = null ): string {
				return '93.184.216.34';
			}
		};
	}

	/**
	 * A default set of valid request parameters, overridable per scenario.
	 *
	 * @param array<string, mixed> $overrides Values to override/unset (set to null to unset).
	 * @return array<string, string>
	 */
	private function valid_get( array $overrides = [] ): array {
		$get = [
			'client_id'             => 'https://good-client.example/app',
			'redirect_uri'          => 'https://client.example/callback',
			'response_type'         => 'code',
			'code_challenge'        => 'challenge-value',
			'code_challenge_method' => 'S256',
			'state'                 => 'state-value',
		];

		foreach ( $overrides as $key => $value ) {
			if ( null === $value ) {
				unset( $get[ $key ] );
				continue;
			}
			$get[ $key ] = $value;
		}

		return $get;
	}

	/**
	 * Invokes handle_request(), intercepting the wp_redirect() call that
	 * would otherwise be immediately followed by exit.
	 *
	 * @param AuthorizeEndpoint $endpoint The endpoint under test.
	 * @return string The redirect location passed to wp_redirect().
	 */
	private function capture_redirect( AuthorizeEndpoint $endpoint ): string {
		$callback =
			/**
			 * Aborts wp_redirect() before the following exit by throwing.
			 *
			 * @param string $location The redirect target.
			 * @return string Never returns; always throws.
			 */
			static function ( $location ) {
				throw new RuntimeException( 'REDIRECT:' . $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not HTML output; captured and asserted on in-process, never rendered.
			};

		// The callback intentionally always throws (to abort before the exit that
		// follows wp_redirect()), so it never returns a value like a real filter.
		add_filter( 'wp_redirect', $callback ); // @phpstan-ignore-line

		try {
			$endpoint->handle_request();
			$this->fail( 'Expected handle_request() to redirect, but it returned normally.' );
		} catch ( RuntimeException $exception ) {
			$this->assertStringStartsWith( 'REDIRECT:', $exception->getMessage() );
			return substr( $exception->getMessage(), strlen( 'REDIRECT:' ) );
		} finally {
			remove_filter( 'wp_redirect', $callback );
		}
	}

	/**
	 * Parses the query string of a URL into an associative array.
	 *
	 * @param string $url The URL to parse.
	 * @return array<string, string>
	 */
	private function query_args( string $url ): array {
		$parts = wp_parse_url( $url );
		$query = [];
		parse_str( $parts['query'] ?? '', $query );

		return $query;
	}

	/**
	 * Exercises every branch of handle_request() from a single method, driven
	 * by the scenario data in the sibling fixture file.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testHandleRequest( array $config, array $expected ): void {
		$get = $this->set_get( $this->valid_get( $config['get'] ?? [] ) );

		if ( null !== ( $config['trusted_host'] ?? null ) ) {
			$this->install_trusted_publisher( $config['trusted_host'], $config['trusted_client_ids'] ?? [] );
		}

		if ( null !== ( $config['cimd_document'] ?? null ) ) {
			$this->install_cimd_document( $get['client_id'], $config['cimd_document'] );
		}

		if ( $config['logged_in'] ?? false ) {
			$user_id = self::factory()->user->create();
			wp_set_current_user( $user_id );
		}

		$endpoint = new AuthorizeEndpoint( $this->make_resolver() );

		switch ( $expected['type'] ) {
			case 'die':
				try {
					$endpoint->handle_request();
					$this->fail( 'Expected a WPDieException to be thrown.' );
				} catch ( WPDieException $exception ) {
					$this->assertStringContainsString( $expected['message_contains'], $exception->getMessage() );
					$this->assertSame( $expected['response_code'], $exception->getCode() );
				}
				break;

			case 'redirect_error':
				$location = $this->capture_redirect( $endpoint );
				$query    = $this->query_args( $location );

				$this->assertStringStartsWith( $get['redirect_uri'], $location );
				$this->assertSame( $expected['error'], $query['error'] ?? null );

				if ( array_key_exists( 'state', $expected ) ) {
					$this->assertSame( $expected['state'], $query['state'] ?? null );
				} else {
					$this->assertArrayNotHasKey( 'state', $query );
				}
				break;

			case 'login':
				$location = $this->capture_redirect( $endpoint );
				$this->assertStringContainsString( 'wp-login', $location );

				$expected_callback = add_query_arg( 'state', rawurlencode( $get['state'] ), home_url( '/oauth/authorize-callback' ) );
				$query             = $this->query_args( $location );
				$this->assertSame( $expected_callback, $query['redirect_to'] ?? null );

				$transient = get_transient( 'mcp_oauth_state_' . $get['state'] );
				$this->assertSame( $expected['transient'], $transient );
				break;

			case 'authenticated_redirect':
				$location = $this->capture_redirect( $endpoint );

				$expected_callback = add_query_arg( 'state', rawurlencode( $get['state'] ), home_url( '/oauth/authorize-callback' ) );
				$this->assertSame( $expected_callback, $location );
				$this->assertStringNotContainsString( 'wp-login', $location );

				$transient = get_transient( 'mcp_oauth_state_' . $get['state'] );
				$this->assertSame( $expected['transient'], $transient );
				break;
		}
	}
}
