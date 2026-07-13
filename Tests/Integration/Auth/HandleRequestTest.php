<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth;

use WPMedia\MCP\OAuth\Auth\JWT;
use WPMedia\MCP\OAuth\Auth\SecretManager;
use WPMedia\MCP\OAuth\Auth\TokenEndpoint;
use WPMedia\MCP\OAuth\Tests\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\TokenEndpoint::handle_request.
 *
 * The endpoint emits every response through `wp_send_json()`, which echoes the
 * JSON then terminates via `wp_die()` — the WP test suite converts that into a
 * thrown `WPDieException`. Requests are therefore driven through `capture()`,
 * which buffers the JSON and swallows the expected exception. Both grant types
 * exercise the real SecretManager/JWT/WP_Application_Passwords collaborators and
 * real transients/user-meta end to end, so this suite is integration-only.
 *
 * @covers \WPMedia\MCP\OAuth\Auth\TokenEndpoint::handle_request
 */
class HandleRequestTest extends TestCase {

	/**
	 * Backup of $_SERVER.
	 *
	 * @var array<string, mixed>
	 */
	private $server_backup;

	/**
	 * Backup of $_POST.
	 *
	 * @var array<string, mixed>
	 */
	private $post_backup;

	/**
	 * Backs up superglobals and defaults to a POST form-encoded request.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->server_backup = $_SERVER;
		$this->post_backup   = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- backing up for tear_down(), not processing input.

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['CONTENT_TYPE']   = 'application/x-www-form-urlencoded';
		$_POST                     = [];
	}

	/**
	 * Restores superglobals.
	 *
	 * @return void
	 */
	public function tear_down() {
		$_SERVER = $this->server_backup;
		$_POST   = $this->post_backup;

		parent::tear_down();
	}

	/**
	 * Runs handle_request(), buffering the JSON emitted before wp_send_json()'s
	 * terminating wp_die() (caught here as WPDieException).
	 *
	 * @return array<string, mixed> The decoded JSON response body.
	 */
	private function capture(): array {
		ob_start();

		try {
			( new TokenEndpoint() )->handle_request();
		} catch ( \WPDieException $exception ) {
			unset( $exception ); // wp_send_json() terminates via wp_die(); expected.
		}

		$json = (string) ob_get_clean();
		$data = json_decode( $json, true );

		return is_array( $data ) ? $data : [];
	}

	/**
	 * Creates a user with a real Application Password; returns [ user_id, uuid ].
	 *
	 * @param string $client_id Optional app_id to tag the password with.
	 * @return array{0: int, 1: string}
	 */
	private function create_user_with_app_password( string $client_id = '' ): array {
		$user_id = self::factory()->user->create();
		$created = \WP_Application_Passwords::create_new_application_password(
			$user_id,
			[
				'name'   => 'mcp-test',
				'app_id' => $client_id,
			]
		);

		return [ $user_id, (string) $created[1]['uuid'] ];
	}

	/**
	 * Mints a refresh JWT for the current site with the given claim overrides.
	 *
	 * @param array<string, mixed> $overrides Claim overrides.
	 * @return string
	 */
	private function mint_refresh_token( array $overrides = [] ): string {
		$claims = array_merge(
			[
				'iss'  => home_url(),
				'type' => 'refresh',
			],
			$overrides
		);

		return JWT::encode( $claims, SecretManager::get_secret() );
	}

	/**
	 * Rejects a non-POST request.
	 *
	 * @return void
	 */
	public function testShouldRejectNonPostMethod(): void {
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$this->assertSame( 'invalid_request', $this->capture()['error'] ?? null );
	}

	/**
	 * Rejects an unsupported grant_type.
	 *
	 * @return void
	 */
	public function testShouldRejectUnsupportedGrantType(): void {
		$_POST['grant_type'] = 'password';

		$this->assertSame( 'unsupported_grant_type', $this->capture()['error'] ?? null );
	}

	/**
	 * Rejects an authorization_code exchange missing code or code_verifier.
	 *
	 * @return void
	 */
	public function testShouldRejectAuthorizationCodeMissingParams(): void {
		$_POST['grant_type'] = 'authorization_code';
		$_POST['code']       = 'some-code';
		// No code_verifier.

		$this->assertSame( 'invalid_request', $this->capture()['error'] ?? null );
	}

	/**
	 * Rejects an authorization_code exchange whose code transient is absent/expired.
	 *
	 * @return void
	 */
	public function testShouldRejectUnknownAuthorizationCode(): void {
		$_POST['grant_type']    = 'authorization_code';
		$_POST['code']          = 'does-not-exist';
		$_POST['code_verifier'] = 'verifier';

		$this->assertSame( 'invalid_grant', $this->capture()['error'] ?? null );
	}

	/**
	 * Rejects an authorization_code exchange whose PKCE verifier does not match.
	 *
	 * @return void
	 */
	public function testShouldRejectPkceMismatch(): void {
		set_transient(
			'mcp_oauth_code_badpkce',
			[
				'user_id'        => self::factory()->user->create(),
				'client_id'      => 'https://client.example/app',
				'client_name'    => 'Client',
				'code_challenge' => JWT::base64url_encode( hash( 'sha256', 'the-real-verifier', true ) ),
				'redirect_uri'   => 'https://client.example/cb',
			],
			60
		);

		$_POST['grant_type']    = 'authorization_code';
		$_POST['code']          = 'badpkce';
		$_POST['code_verifier'] = 'the-WRONG-verifier';
		$_POST['redirect_uri']  = 'https://client.example/cb';

		$this->assertSame( 'invalid_grant', $this->capture()['error'] ?? null );
	}

	/**
	 * Rejects an authorization_code exchange whose redirect_uri does not match.
	 *
	 * @return void
	 */
	public function testShouldRejectRedirectUriMismatch(): void {
		$verifier = 'a-valid-code-verifier-value';

		set_transient(
			'mcp_oauth_code_reduri',
			[
				'user_id'        => self::factory()->user->create(),
				'client_id'      => 'https://client.example/app',
				'client_name'    => 'Client',
				'code_challenge' => JWT::base64url_encode( hash( 'sha256', $verifier, true ) ),
				'redirect_uri'   => 'https://client.example/cb',
			],
			60
		);

		$_POST['grant_type']    = 'authorization_code';
		$_POST['code']          = 'reduri';
		$_POST['code_verifier'] = $verifier;
		$_POST['redirect_uri']  = 'https://client.example/DIFFERENT';

		$this->assertSame( 'invalid_grant', $this->capture()['error'] ?? null );
	}

	/**
	 * Exchanges a valid auth code for a token pair, creating an Application
	 * Password and persisting the refresh-token rotation marker.
	 *
	 * @return void
	 */
	public function testShouldIssueTokenPairForValidAuthorizationCode(): void {
		$user_id  = self::factory()->user->create();
		$verifier = 'a-valid-code-verifier-value';

		set_transient(
			'mcp_oauth_code_good',
			[
				'user_id'        => $user_id,
				'client_id'      => 'https://client.example/app',
				'client_name'    => 'Example Client',
				'code_challenge' => JWT::base64url_encode( hash( 'sha256', $verifier, true ) ),
				'redirect_uri'   => 'https://client.example/cb',
			],
			60
		);

		$_POST['grant_type']    = 'authorization_code';
		$_POST['code']          = 'good';
		$_POST['code_verifier'] = $verifier;
		$_POST['redirect_uri']  = 'https://client.example/cb';

		$data = $this->capture();

		$this->assertArrayHasKey( 'access_token', $data );
		$this->assertArrayHasKey( 'refresh_token', $data );
		$this->assertSame( 'Bearer', $data['token_type'] ?? null );
		$this->assertSame( 'mcp', $data['scope'] ?? null );
		$this->assertSame( HOUR_IN_SECONDS, $data['expires_in'] ?? null );

		// The auth code is single-use: its transient must be consumed.
		$this->assertFalse( get_transient( 'mcp_oauth_code_good' ) );

		// The access token binds to a freshly-created Application Password.
		$claims = JWT::decode( (string) $data['access_token'], SecretManager::get_secret() );
		$this->assertIsArray( $claims );
		$this->assertSame( (string) $user_id, $claims['sub'] );
		$this->assertSame( get_rest_url( null, 'mcp/mcp-oauth-server' ), $claims['aud'] );

		$uuid = (string) $claims['app_pass_id'];
		$this->assertIsArray( \WP_Application_Passwords::get_user_application_password( $user_id, $uuid ) );

		// The refresh-token rotation marker is stored for this session.
		$this->assertNotEmpty( get_user_meta( $user_id, TokenEndpoint::REFRESH_JTI_META_PREFIX . $uuid, true ) );
	}

	/**
	 * Rejects a refresh_token grant with no refresh_token.
	 *
	 * @return void
	 */
	public function testShouldRejectRefreshGrantMissingToken(): void {
		$_POST['grant_type'] = 'refresh_token';

		$this->assertSame( 'invalid_request', $this->capture()['error'] ?? null );
	}

	/**
	 * Rejects a refresh token that fails to decode or has the wrong type.
	 *
	 * @return void
	 */
	public function testShouldRejectUndecodableRefreshToken(): void {
		$_POST['grant_type']    = 'refresh_token';
		$_POST['refresh_token'] = 'not-a-jwt';

		$this->assertSame( 'invalid_token', $this->capture()['error'] ?? null );
	}

	/**
	 * Rejects a refresh token whose issuer is not this site.
	 *
	 * @return void
	 */
	public function testShouldRejectRefreshTokenIssuerMismatch(): void {
		list( $user_id, $uuid ) = $this->create_user_with_app_password();

		$_POST['grant_type']    = 'refresh_token';
		$_POST['refresh_token'] = $this->mint_refresh_token(
			[
				'iss'         => 'https://evil.example',
				'sub'         => $user_id,
				'app_pass_id' => $uuid,
			]
		);

		$this->assertSame( 'invalid_token', $this->capture()['error'] ?? null );
	}

	/**
	 * Rejects a refresh token whose Application Password has been revoked, and
	 * clears the orphaned rotation marker.
	 *
	 * @return void
	 */
	public function testShouldRejectRefreshTokenForRevokedSession(): void {
		$user_id = self::factory()->user->create();
		$uuid    = 'missing-app-pass-uuid';
		update_user_meta( $user_id, TokenEndpoint::REFRESH_JTI_META_PREFIX . $uuid, 'stale-jti' );

		$_POST['grant_type']    = 'refresh_token';
		$_POST['refresh_token'] = $this->mint_refresh_token(
			[
				'sub'         => $user_id,
				'app_pass_id' => $uuid,
				'jti'         => 'stale-jti',
			]
		);

		$this->assertSame( 'invalid_token', $this->capture()['error'] ?? null );
		$this->assertEmpty( get_user_meta( $user_id, TokenEndpoint::REFRESH_JTI_META_PREFIX . $uuid, true ) );
	}

	/**
	 * Detects refresh-token reuse (a non-current jti) and revokes the session.
	 *
	 * @return void
	 */
	public function testShouldRevokeSessionOnRefreshTokenReuse(): void {
		list( $user_id, $uuid ) = $this->create_user_with_app_password();
		update_user_meta( $user_id, TokenEndpoint::REFRESH_JTI_META_PREFIX . $uuid, 'current-jti' );

		$_POST['grant_type']    = 'refresh_token';
		$_POST['refresh_token'] = $this->mint_refresh_token(
			[
				'sub'         => $user_id,
				'app_pass_id' => $uuid,
				'jti'         => 'an-old-rotated-jti',
			]
		);

		$this->assertSame( 'invalid_grant', $this->capture()['error'] ?? null );
		// The whole session is revoked: the Application Password is deleted.
		$this->assertNull( \WP_Application_Passwords::get_user_application_password( $user_id, $uuid ) );
	}

	/**
	 * Issues a new, rotated token pair for a valid refresh token.
	 *
	 * @return void
	 */
	public function testShouldIssueRotatedPairForValidRefreshToken(): void {
		list( $user_id, $uuid ) = $this->create_user_with_app_password( 'https://client.example/app' );
		update_user_meta( $user_id, TokenEndpoint::REFRESH_JTI_META_PREFIX . $uuid, 'current-jti' );

		$_POST['grant_type']    = 'refresh_token';
		$_POST['refresh_token'] = $this->mint_refresh_token(
			[
				'sub'         => $user_id,
				'app_pass_id' => $uuid,
				'client_id'   => 'https://client.example/app',
				'jti'         => 'current-jti',
			]
		);

		$data = $this->capture();

		$this->assertArrayHasKey( 'access_token', $data );
		$this->assertArrayHasKey( 'refresh_token', $data );

		// The rotation marker is replaced, invalidating the token just presented.
		$this->assertNotSame(
			'current-jti',
			get_user_meta( $user_id, TokenEndpoint::REFRESH_JTI_META_PREFIX . $uuid, true )
		);

		$claims = JWT::decode( (string) $data['access_token'], SecretManager::get_secret() );
		$this->assertIsArray( $claims );
		$this->assertSame( $uuid, $claims['app_pass_id'] );
	}

	/**
	 * Enforces the per-client session cap by evicting the oldest sessions.
	 *
	 * @return void
	 */
	public function testShouldEvictOldestSessionsBeyondPerClientCap(): void {
		$user_id   = self::factory()->user->create();
		$client_id = 'https://client.example/app';
		$verifier  = 'a-valid-code-verifier-value';

		// Pre-create MAX_SESSIONS_PER_CLIENT sessions for this client.
		$uuids = [];
		for ( $i = 0; $i < TokenEndpoint::MAX_SESSIONS_PER_CLIENT; $i++ ) {
			$created = \WP_Application_Passwords::create_new_application_password(
				$user_id,
				[
					'name'   => 'mcp-test',
					'app_id' => $client_id,
				]
			);
			$uuids[] = (string) $created[1]['uuid'];
		}

		set_transient(
			'mcp_oauth_code_capped',
			[
				'user_id'        => $user_id,
				'client_id'      => $client_id,
				'client_name'    => 'Example Client',
				'code_challenge' => JWT::base64url_encode( hash( 'sha256', $verifier, true ) ),
				'redirect_uri'   => 'https://client.example/cb',
			],
			60
		);

		$_POST['grant_type']    = 'authorization_code';
		$_POST['code']          = 'capped';
		$_POST['code_verifier'] = $verifier;
		$_POST['redirect_uri']  = 'https://client.example/cb';

		$data = $this->capture();
		$this->assertArrayHasKey( 'access_token', $data );

		// The oldest session was evicted; the total stays at the cap.
		$remaining = array_filter(
			\WP_Application_Passwords::get_user_application_passwords( $user_id ),
			static function ( $item ) use ( $client_id ) {
				return $client_id === $item['app_id'];
			}
		);
		$this->assertCount( TokenEndpoint::MAX_SESSIONS_PER_CLIENT, $remaining );
		$this->assertNull( \WP_Application_Passwords::get_user_application_password( $user_id, $uuids[0] ) );
	}
}
