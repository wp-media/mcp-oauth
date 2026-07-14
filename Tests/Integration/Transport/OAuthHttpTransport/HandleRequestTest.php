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
	 * Creates a user with a real Application Password; returns [ user_id, uuid ].
	 *
	 * @return array{0: int, 1: string}
	 */
	private function create_user_with_app_password(): array {
		$user_id = self::factory()->user->create();
		$created = \WP_Application_Passwords::create_new_application_password( $user_id, [ 'name' => 'mcp-test' ] );

		return [ $user_id, (string) $created[1]['uuid'] ];
	}

	/**
	 * Always authorises via check_permission() (auth is enforced in handle_request()).
	 *
	 * @return void
	 */
	public function testShouldAlwaysAllowPermission(): void {
		// check_permission() unconditionally returns true (auth is enforced in
		// handle_request()); the assertion documents that contract.
		$this->assertTrue( $this->make_transport()->check_permission( $this->make_request() ) ); // @phpstan-ignore-line
	}

	/**
	 * Registers the MCP OAuth REST route via register_routes().
	 *
	 * @return void
	 */
	public function testShouldRegisterRestRoute(): void {
		$server = Mockery::mock( McpServer::class );
		$server->shouldReceive( 'get_server_route_namespace' )->andReturn( 'mcp' );
		$server->shouldReceive( 'get_server_route' )->andReturn( 'mcp-oauth-server' );

		$transport = $this->make_transport( $server );

		// Ensure the REST server has bootstrapped (fires rest_api_init) so that
		// registering a route directly is not flagged as "called too early".
		$rest_server = rest_get_server();
		$transport->register_routes();

		$routes = $rest_server->get_routes();
		$this->assertArrayHasKey( '/mcp/mcp-oauth-server', $routes );
	}

	/**
	 * A request with no Authorization header is rejected as unauthorised.
	 *
	 * @return void
	 */
	public function testShouldRejectMissingBearerToken(): void {
		$result = $this->validate( $this->make_transport(), $this->make_request() );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'mcp_unauthorized', $result->get_error_code() );
	}

	/**
	 * A non-Bearer Authorization header is rejected.
	 *
	 * @return void
	 */
	public function testShouldRejectNonBearerAuthorization(): void {
		$result = $this->validate( $this->make_transport(), $this->make_request( 'Basic abc123' ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * A token that fails signature/format decoding is rejected as invalid_token.
	 *
	 * @return void
	 */
	public function testShouldRejectUndecodableToken(): void {
		$result = $this->validate( $this->make_transport(), $this->make_request( 'Bearer not-a-jwt' ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * A validly-signed token whose audience does not match is rejected.
	 *
	 * @return void
	 */
	public function testShouldRejectAudienceMismatch(): void {
		$token  = $this->mint_token( [ 'aud' => 'https://evil.example/wrong' ] );
		$result = $this->validate( $this->make_transport(), $this->make_request( 'Bearer ' . $token ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * A validly-signed token whose issuer does not match is rejected.
	 *
	 * @return void
	 */
	public function testShouldRejectIssuerMismatch(): void {
		$token  = $this->mint_token( [ 'iss' => 'https://evil.example' ] );
		$result = $this->validate( $this->make_transport(), $this->make_request( 'Bearer ' . $token ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * A token missing the sub/app_pass_id claims is rejected.
	 *
	 * @return void
	 */
	public function testShouldRejectMalformedClaims(): void {
		$token  = $this->mint_token();
		$result = $this->validate( $this->make_transport(), $this->make_request( 'Bearer ' . $token ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * A token whose Application Password has been revoked/does not exist is rejected.
	 *
	 * @return void
	 */
	public function testShouldRejectRevokedApplicationPassword(): void {
		$user_id = self::factory()->user->create();
		$token   = $this->mint_token(
			[
				'sub'         => $user_id,
				'app_pass_id' => 'non-existent-uuid',
			]
		);

		$result = $this->validate( $this->make_transport(), $this->make_request( 'Bearer ' . $token ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * A fully valid token authenticates the user and sets the current user.
	 *
	 * @return void
	 */
	public function testShouldAuthenticateValidToken(): void {
		list( $user_id, $uuid ) = $this->create_user_with_app_password();

		$token = $this->mint_token(
			[
				'sub'         => $user_id,
				'app_pass_id' => $uuid,
			]
		);

		$result = $this->validate( $this->make_transport(), $this->make_request( 'Bearer ' . $token ) );

		$this->assertInstanceOf( \WP_User::class, $result );
		$this->assertSame( $user_id, $result->ID );
		$this->assertSame( $user_id, get_current_user_id() );
	}

	/**
	 * Returns a 401 WP_REST_Response with a WWW-Authenticate challenge from
	 * handle_request() when the bearer token is missing/invalid.
	 *
	 * @return void
	 */
	public function testShouldReturn401ResponseOnAuthFailure(): void {
		// handle_request() logs via McpLogger; buffer it to keep the test non-risky.
		ob_start();
		$response = $this->make_transport()->handle_request( $this->make_request() );
		ob_end_clean();

		$this->assertSame( 401, $response->get_status() );

		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'WWW-Authenticate', $headers );
		$this->assertStringContainsString( 'Bearer', $headers['WWW-Authenticate'] );
	}
}
