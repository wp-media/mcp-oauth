<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth\RevokeEndpoint;

use WPMedia\MCP\OAuth\Auth\JWT;
use WPMedia\MCP\OAuth\Auth\RevokeEndpoint;
use WPMedia\MCP\OAuth\Auth\SecretManager;
use WPMedia\MCP\OAuth\Tests\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\RevokeEndpoint::handle_request
 *
 * Every response is emitted through `wp_send_json()`, which echoes the JSON and
 * then terminates via `wp_die()` — the WP test suite converts that into a thrown
 * `WPDieException`. Each request is therefore driven through `capture()`, which
 * buffers the emitted JSON and swallows the expected `WPDieException`. The happy
 * path exercises the real static collaborators (`SecretManager`, `JWT`,
 * `WP_Application_Passwords`) end to end, so this suite is integration-only.
 *
 * @covers \WPMedia\MCP\OAuth\Auth\RevokeEndpoint::handle_request
 */
class HandleRequestTest extends TestCase {

	/**
	 * Backup of $_SERVER so per-test mutations don't leak.
	 *
	 * @var array<string, mixed>
	 */
	private $server_backup;

	/**
	 * Backup of $_POST so per-test mutations don't leak.
	 *
	 * @var array<string, mixed>
	 */
	private $post_backup;

	/**
	 * Backs up superglobals and sets a POST form-encoded request by default.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->server_backup = $_SERVER;
		$this->post_backup   = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- backing up the superglobal for tear_down(), not processing form input.

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
	 * @param RevokeEndpoint $endpoint The endpoint under test.
	 * @return array<string, mixed> The decoded JSON response body.
	 */
	private function capture( RevokeEndpoint $endpoint ): array {
		ob_start();

		try {
			$endpoint->handle_request();
		} catch ( \WPDieException $exception ) {
			unset( $exception ); // wp_send_json() terminates via wp_die(); expected.
		}

		$json = (string) ob_get_clean();
		$data = json_decode( $json, true );

		return is_array( $data ) ? $data : [];
	}

	/**
	 * Creates a user with a real Application Password and returns [ user_id, uuid ].
	 *
	 * @return array{0: int, 1: string}
	 */
	private function create_user_with_app_password(): array {
		$user_id = self::factory()->user->create();

		$created = \WP_Application_Passwords::create_new_application_password( $user_id, [ 'name' => 'mcp-test' ] );
		$uuid    = (string) $created[1]['uuid'];

		return [ $user_id, $uuid ];
	}

	/**
	 * Rejects a non-POST request with an invalid_request error.
	 *
	 * @return void
	 */
	public function testShouldRejectNonPostMethod(): void {
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$data = $this->capture( new RevokeEndpoint() );

		$this->assertSame( 'invalid_request', $data['error'] ?? null );
	}

	/**
	 * Rejects a request with no token parameter.
	 *
	 * @return void
	 */
	public function testShouldRejectMissingToken(): void {
		$data = $this->capture( new RevokeEndpoint() );

		$this->assertSame( 'invalid_request', $data['error'] ?? null );
	}

	/**
	 * Returns an empty success object for an unrecognisable token (RFC 7009 §2.2).
	 *
	 * @return void
	 */
	public function testShouldNoOpOnUnrecognisedToken(): void {
		$_POST['token'] = 'not-a-valid-jwt';

		$data = $this->capture( new RevokeEndpoint() );

		$this->assertSame( [], $data );
	}

	/**
	 * Succeeds as a no-op when the token lacks sub/app_pass_id claims.
	 *
	 * @return void
	 */
	public function testShouldNoOpWhenClaimsMissingSubOrAppPassId(): void {
		$_POST['token'] = JWT::encode( [ 'foo' => 'bar' ], SecretManager::get_secret() );

		$data = $this->capture( new RevokeEndpoint() );

		$this->assertSame( [], $data );
	}

	/**
	 * Does not delete the Application Password when the client_id parameter and
	 * the token's client_id claim disagree (RFC 7009 §2.1 client binding).
	 *
	 * @return void
	 */
	public function testShouldNoOpOnClientIdMismatch(): void {
		list( $user_id, $uuid ) = $this->create_user_with_app_password();

		$_POST['token']     = JWT::encode(
			[
				'sub'         => $user_id,
				'app_pass_id' => $uuid,
				'client_id'   => 'https://a.example/app',
			],
			SecretManager::get_secret()
		);
		$_POST['client_id'] = 'https://different.example/app';

		$data = $this->capture( new RevokeEndpoint() );

		$this->assertSame( [], $data );
		$this->assertIsArray(
			\WP_Application_Passwords::get_user_application_password( $user_id, $uuid ),
			'The Application Password must NOT be deleted on a client_id mismatch.'
		);
	}

	/**
	 * Deletes the anchoring Application Password for a valid token, immediately
	 * revoking the session.
	 *
	 * @return void
	 */
	public function testShouldRevokeSessionOnValidToken(): void {
		list( $user_id, $uuid ) = $this->create_user_with_app_password();

		$_POST['token']     = JWT::encode(
			[
				'sub'         => $user_id,
				'app_pass_id' => $uuid,
				'client_id'   => 'https://a.example/app',
				'type'        => 'access',
			],
			SecretManager::get_secret()
		);
		$_POST['client_id'] = 'https://a.example/app';

		$data = $this->capture( new RevokeEndpoint() );

		$this->assertSame( [], $data );
		$this->assertNull(
			\WP_Application_Passwords::get_user_application_password( $user_id, $uuid ),
			'The Application Password must be deleted, revoking the session.'
		);
	}

	/**
	 * Revokes even an expired token, as RFC 7009 requires (decode ignores expiry).
	 *
	 * @return void
	 */
	public function testShouldRevokeExpiredToken(): void {
		list( $user_id, $uuid ) = $this->create_user_with_app_password();

		$_POST['token'] = JWT::encode(
			[
				'sub'         => $user_id,
				'app_pass_id' => $uuid,
				'exp'         => time() - 3600,
			],
			SecretManager::get_secret()
		);

		$data = $this->capture( new RevokeEndpoint() );

		$this->assertSame( [], $data );
		$this->assertNull( \WP_Application_Passwords::get_user_application_password( $user_id, $uuid ) );
	}
}
