<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Transport\OAuthHttpTransport;

use Mockery;
use ReflectionMethod;
use WP\MCP\Core\McpServer;
use WP\MCP\Transport\Infrastructure\McpTransportContext;
use WPMedia\MCP\OAuth\Auth\JWT;
use WPMedia\MCP\OAuth\Auth\SecretManager;
use WPMedia\MCP\OAuth\Tests\Integration\TestCase;
use WPMedia\MCP\OAuth\Transport\OAuthHttpTransport;

/**
 * Tests for WPMedia\MCP\OAuth\Transport\OAuthHttpTransport.
 *
 * The transport's constructor hard-instantiates the mcp-adapter
 * HttpRequestHandler, so the McpTransportContext collaborator is replaced with
 * a Mockery mock. The security-critical bearer-token validation lives in the
 * private validate_bearer_token(), reached here via reflection against real
 * WordPress (real JWTs, users, and Application Passwords). handle_request()'s
 * success path delegates to the mcp-adapter HttpRequestHandler and is covered
 * by the adapter's own suite; here we cover its 401 (auth-error) path.
 *
 * @covers \WPMedia\MCP\OAuth\Transport\OAuthHttpTransport::handle_request
 * @covers \WPMedia\MCP\OAuth\Transport\OAuthHttpTransport::check_permission
 * @covers \WPMedia\MCP\OAuth\Transport\OAuthHttpTransport::register_routes
 */
class HandleRequestTest extends TestCase {

	/**
	 * User ID created by build_authorization() for the current scenario, or
	 * null when the scenario doesn't create one.
	 *
	 * @var int|null
	 */
	private $created_user_id;

	/**
	 * Builds a transport backed by a mocked transport context.
	 *
	 * @param McpServer|null $server Optional server mock to expose on the context.
	 * @return OAuthHttpTransport
	 */
	private function make_transport( ?McpServer $server = null ): OAuthHttpTransport {
		$context = Mockery::mock( McpTransportContext::class );

		if ( null !== $server ) {
			$context->mcp_server = $server;
		}

		return new OAuthHttpTransport( $context );
	}

	/**
	 * Builds a REST request with an optional Authorization header.
	 *
	 * @param string|null $authorization Authorization header value, or null to omit.
	 * @return \WP_REST_Request
	 */
	private function make_request( ?string $authorization = null ): \WP_REST_Request {
		$request = new \WP_REST_Request( 'POST', '/mcp/mcp-oauth-server' );

		if ( null !== $authorization ) {
			$request->set_header( 'Authorization', $authorization );
		}

		return $request;
	}

	/**
	 * Invokes the private validate_bearer_token() via reflection.
	 *
	 * @param OAuthHttpTransport $transport The transport under test.
	 * @param \WP_REST_Request   $request   The request to validate.
	 * @return \WP_User|\WP_Error
	 */
	private function validate( OAuthHttpTransport $transport, \WP_REST_Request $request ) {
		// No setAccessible() call: since PHP 8.1 reflection can invoke private
		// methods directly, and calling it emits a deprecation notice on 8.5+.
		$method = new ReflectionMethod( OAuthHttpTransport::class, 'validate_bearer_token' );

		// McpLogger::log() writes [MCP] diagnostics to output; swallow it so the
		// suite's strict-output check does not flag the test as risky.
		ob_start();
		try {
			return $method->invoke( $transport, $request );
		} finally {
			ob_end_clean();
		}
	}

	/**
	 * Mints a signed JWT for the current site with the given claim overrides.
	 *
	 * @param array<string, mixed> $overrides Claim overrides.
	 * @return string
	 */
	private function mint_token( array $overrides = [] ): string {
		$claims = array_merge(
			[
				'aud' => get_rest_url( null, 'mcp/mcp-oauth-server' ),
				'iss' => home_url(),
			],
			$overrides
		);

		return JWT::encode( $claims, SecretManager::get_secret() );
	}

	/**
	 * Builds the Authorization header for a scenario, optionally creating a
	 * real user (and Application Password) whose identifiers get folded into
	 * the minted token's claims. The created user ID is stashed on
	 * $this->created_user_id so the caller can assert against it afterwards.
	 *
	 * @param array<string, mixed> $config Scenario configuration.
	 * @return string|null
	 */
	private function build_authorization( array $config ): ?string {
		$this->created_user_id = null;

		switch ( $config['header_type'] ) {
			case 'missing':
				return null;

			case 'basic':
				return $config['basic_value'] ?? 'Basic abc123';

			case 'malformed_jwt':
				return 'Bearer not-a-jwt';

			case 'token':
				$claims = $config['claims'] ?? [];

				if ( $config['create_user'] ?? false ) {
					$this->created_user_id = self::factory()->user->create();
					$claims['sub']         = $this->created_user_id;

					if ( $config['create_app_password'] ?? false ) {
						$created               = \WP_Application_Passwords::create_new_application_password( $this->created_user_id, [ 'name' => 'mcp-test' ] );
						$claims['app_pass_id'] = (string) $created[1]['uuid'];
					}
				}

				return 'Bearer ' . $this->mint_token( $claims );
		}

		return null;
	}

	/**
	 * Exercises every branch of check_permission(), register_routes(),
	 * validate_bearer_token(), and handle_request()'s auth-failure path from a
	 * single method, driven by the scenario data in the sibling fixture file.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testHandleRequest( array $config, array $expected ): void {
		switch ( $expected['type'] ) {
			case 'permission':
				$this->assertTrue( $this->make_transport()->check_permission( $this->make_request() ) ); // @phpstan-ignore-line

				return;

			case 'register_routes':
				$server = Mockery::mock( McpServer::class );
				$server->shouldReceive( 'get_server_route_namespace' )->andReturn( 'mcp' );
				$server->shouldReceive( 'get_server_route' )->andReturn( 'mcp-oauth-server' );

				$transport = $this->make_transport( $server );

				// Ensure the REST server has bootstrapped (fires rest_api_init) so
				// that registering a route directly is not flagged as "called too early".
				$rest_server = rest_get_server();
				$transport->register_routes();

				$this->assertArrayHasKey( '/mcp/mcp-oauth-server', $rest_server->get_routes() );

				return;

			case 'auth_failure_response':
				// handle_request() logs via McpLogger; buffer it to keep the test non-risky.
				ob_start();
				$response = $this->make_transport()->handle_request( $this->make_request() );
				ob_end_clean();

				$this->assertSame( 401, $response->get_status() );

				$headers = $response->get_headers();
				$this->assertArrayHasKey( 'WWW-Authenticate', $headers );
				$this->assertStringContainsString( 'Bearer', $headers['WWW-Authenticate'] );

				return;
		}

		$authorization = $this->build_authorization( $config );
		$result        = $this->validate( $this->make_transport(), $this->make_request( $authorization ) );

		if ( 'authenticated' === $expected['type'] ) {
			$this->assertInstanceOf( \WP_User::class, $result );
			$this->assertSame( $this->created_user_id, $result->ID );
			$this->assertSame( $this->created_user_id, get_current_user_id() );

			return;
		}

		$this->assertInstanceOf( \WP_Error::class, $result );

		if ( isset( $expected['error_code'] ) ) {
			$this->assertSame( $expected['error_code'], $result->get_error_code() );
		}
	}
}
