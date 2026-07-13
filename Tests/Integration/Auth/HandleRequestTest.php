<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth;

use Mockery;
use RuntimeException;
use WPDieException;
use WPMedia\MCP\OAuth\Auth\AuthorizeEndpoint;
use WPMedia\MCP\OAuth\Auth\CimdResolver;
use WPMedia\MCP\OAuth\Tests\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\AuthorizeEndpoint::handle_request
 *
 * `handle_request()` terminates every branch via either `wp_die()` (caught
 * here as `WPDieException`, which the WP test suite's base `wp_die_handler`
 * throws instead of exiting) or `wp_redirect(); exit;` (intercepted by
 * throwing from a `wp_redirect` filter callback before the following `exit`
 * runs). The `CimdResolver` collaborator is replaced with a Mockery mock so
 * no network fetch occurs.
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
	 * A default verified client record, overridable per scenario.
	 *
	 * @param array<string, mixed> $overrides Values to override.
	 * @return array<string, mixed>
	 */
	private function verified_client( array $overrides = [] ): array {
		return array_merge(
			[
				'client_id'     => 'https://good-client.example/app',
				'client_name'   => 'Example App',
				'client_uri'    => 'https://client.example',
				'redirect_uris' => [ 'https://client.example/callback' ],
				'verified'      => true,
				'publisher'     => 'anthropic',
			],
			$overrides
		);
	}

	/**
	 * Builds a Mockery mock of CimdResolver.
	 *
	 * @param bool                      $called         Whether resolve() is expected to be called.
	 * @param array<string, mixed>|null $resolve_return What resolve() should return, when called.
	 * @param string|null               $with           The client_id resolve() is expected to be called with.
	 * @return CimdResolver
	 */
	private function mock_resolver( bool $called, ?array $resolve_return = null, ?string $with = null ): CimdResolver {
		$resolver = Mockery::mock( CimdResolver::class );

		if ( ! $called ) {
			$resolver->shouldNotReceive( 'resolve' );
			return $resolver;
		}

		$expectation = $resolver->shouldReceive( 'resolve' )->once();
		if ( null !== $with ) {
			$expectation->with( $with );
		}
		$expectation->andReturn( $resolve_return );

		return $resolver;
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
	 * Rejects invalid requests (missing/unresolvable/unverified client, or a
	 * missing/mismatched redirect_uri) with a wp_die() before redirect_uri is
	 * ever used for a redirect.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldDieAccordingToClientValidation( array $config, array $expected ): void {
		$get = $this->set_get( $config['get'] );

		$resolver = $this->mock_resolver( $config['resolve_called'], $config['client'], $get['client_id'] ?? null );

		$endpoint = new AuthorizeEndpoint( $resolver );

		try {
			$endpoint->handle_request();
			$this->fail( 'Expected a WPDieException to be thrown.' );
		} catch ( WPDieException $exception ) {
			$this->assertStringContainsString( $expected['message_contains'], $exception->getMessage() );
			$this->assertSame( $expected['response_code'], $exception->getCode() );
		}
	}

	/**
	 * Redirects to the client's redirect_uri with error=unsupported_response_type
	 * when response_type isn't 'code'.
	 */
	public function testShouldRedirectWithUnsupportedResponseTypeError(): void {
		$get = $this->set_get( $this->valid_get( [ 'response_type' => 'token' ] ) );

		$resolver = $this->mock_resolver( true, $this->verified_client(), $get['client_id'] );
		$endpoint = new AuthorizeEndpoint( $resolver );

		$location = $this->capture_redirect( $endpoint );
		$query    = $this->query_args( $location );

		$this->assertStringStartsWith( $get['redirect_uri'], $location );
		$this->assertSame( 'unsupported_response_type', $query['error'] ?? null );
		$this->assertSame( $get['state'], $query['state'] ?? null );
	}

	/**
	 * Redirects with error=invalid_request when code_challenge is missing.
	 */
	public function testShouldRedirectWithInvalidRequestErrorWhenCodeChallengeIsMissing(): void {
		$get = $this->set_get( $this->valid_get( [ 'code_challenge' => null ] ) );

		$resolver = $this->mock_resolver( true, $this->verified_client(), $get['client_id'] );
		$endpoint = new AuthorizeEndpoint( $resolver );

		$location = $this->capture_redirect( $endpoint );
		$query    = $this->query_args( $location );

		$this->assertStringStartsWith( $get['redirect_uri'], $location );
		$this->assertSame( 'invalid_request', $query['error'] ?? null );
		$this->assertSame( $get['state'], $query['state'] ?? null );
	}

	/**
	 * Redirects with error=invalid_request when code_challenge_method isn't S256.
	 */
	public function testShouldRedirectWithInvalidRequestErrorWhenCodeChallengeMethodIsNotS256(): void {
		$get = $this->set_get( $this->valid_get( [ 'code_challenge_method' => 'plain' ] ) );

		$resolver = $this->mock_resolver( true, $this->verified_client(), $get['client_id'] );
		$endpoint = new AuthorizeEndpoint( $resolver );

		$location = $this->capture_redirect( $endpoint );
		$query    = $this->query_args( $location );

		$this->assertStringStartsWith( $get['redirect_uri'], $location );
		$this->assertSame( 'invalid_request', $query['error'] ?? null );
		$this->assertSame( $get['state'], $query['state'] ?? null );
	}

	/**
	 * Redirects with error=invalid_request and no state param when state is missing.
	 */
	public function testShouldRedirectWithInvalidRequestErrorAndNoStateWhenStateIsMissing(): void {
		$get = $this->set_get( $this->valid_get( [ 'state' => null ] ) );

		$resolver = $this->mock_resolver( true, $this->verified_client(), $get['client_id'] );
		$endpoint = new AuthorizeEndpoint( $resolver );

		$location = $this->capture_redirect( $endpoint );
		$query    = $this->query_args( $location );

		$this->assertStringStartsWith( $get['redirect_uri'], $location );
		$this->assertSame( 'invalid_request', $query['error'] ?? null );
		$this->assertArrayNotHasKey( 'state', $query );
	}

	/**
	 * On a fully valid request, persists the PKCE/state data in a transient
	 * and redirects the browser to the WP login form with the callback URL.
	 */
	public function testShouldPersistStateAndRedirectToLoginOnValidRequest(): void {
		$get = $this->set_get( $this->valid_get() );

		$client   = $this->verified_client();
		$resolver = $this->mock_resolver( true, $client, $get['client_id'] );
		$endpoint = new AuthorizeEndpoint( $resolver );

		$location = $this->capture_redirect( $endpoint );

		$this->assertStringContainsString( 'wp-login', $location );

		$expected_callback = add_query_arg( 'state', rawurlencode( $get['state'] ), home_url( '/oauth/authorize-callback' ) );
		$query             = $this->query_args( $location );
		$this->assertSame( $expected_callback, $query['redirect_to'] ?? null );

		$transient = get_transient( 'mcp_oauth_state_' . $get['state'] );
		$this->assertSame(
			[
				'client_id'             => $client['client_id'],
				'client_name'           => $client['client_name'],
				'client_uri'            => $client['client_uri'],
				'verified'              => true,
				'publisher'             => $client['publisher'],
				'redirect_uri'          => $get['redirect_uri'],
				'code_challenge'        => $get['code_challenge'],
				'code_challenge_method' => $get['code_challenge_method'],
				'state'                 => $get['state'],
			],
			$transient
		);
	}

	/**
	 * Loopback redirect_uris (RFC 8252) are matched port-agnostically: a
	 * registered http://127.0.0.1:<port>/cb accepts any ephemeral port.
	 */
	public function testShouldAcceptLoopbackRedirectUriRegardlessOfPort(): void {
		$get = $this->set_get( $this->valid_get( [ 'redirect_uri' => 'http://127.0.0.1:51204/cb' ] ) );

		$client   = $this->verified_client( [ 'redirect_uris' => [ 'http://127.0.0.1:9999/cb' ] ] );
		$resolver = $this->mock_resolver( true, $client, $get['client_id'] );
		$endpoint = new AuthorizeEndpoint( $resolver );

		$location = $this->capture_redirect( $endpoint );

		$this->assertStringContainsString( 'wp-login', $location );

		$transient = get_transient( 'mcp_oauth_state_' . $get['state'] );
		$this->assertSame( $get['redirect_uri'], $transient['redirect_uri'] );
	}
}
