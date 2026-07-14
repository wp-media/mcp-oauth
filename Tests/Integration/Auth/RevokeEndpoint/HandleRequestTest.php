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
 * Every response is emitted through `wp_send_json()`. Outside of an AJAX
 * request, `wp_send_json()` terminates with a bare `die` that cannot be
 * caught, which would kill the test runner. `set_up()` therefore filters
 * `wp_doing_ajax` to `true` for the duration of each test (undone in
 * `tear_down()`), so `wp_send_json()` instead calls `wp_die()`, whose
 * `wp_die_ajax_handler` we filter to throw a catchable `WPDieException` —
 * mirroring what WP core's own `WP_Ajax_UnitTestCase` does, without adopting
 * its unrelated admin-ajax action wiring. Each request is driven through
 * `capture()`, which buffers the emitted JSON and swallows that exception.
 * The happy path exercises the real static collaborators (`SecretManager`,
 * `JWT`, `WP_Application_Passwords`) end to end, so this suite is
 * integration-only.
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
	 * Filter callback that forces wp_doing_ajax() to true for the test.
	 *
	 * @var callable
	 */
	private $doing_ajax_filter;

	/**
	 * Filter callback that swaps in an exception-throwing wp_die_ajax_handler.
	 *
	 * @var callable
	 */
	private $ajax_die_handler_filter;

	/**
	 * Backs up superglobals, sets a POST form-encoded request by default, and
	 * makes wp_send_json()'s termination catchable (see class docblock).
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

		$this->doing_ajax_filter = static function () {
			return true;
		};
		add_filter( 'wp_doing_ajax', $this->doing_ajax_filter );

		$this->ajax_die_handler_filter = static function () {
			return static function ( $message, $title = '', $args = [] ) {
				unset( $title );
				$args = wp_parse_args( $args, [ 'response' => 200 ] );
				throw new \WPDieException( (string) $message, (int) $args['response'] ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message consumed by the test itself, never rendered as output.
			};
		};
		add_filter( 'wp_die_ajax_handler', $this->ajax_die_handler_filter );
	}

	/**
	 * Restores superglobals and the wp_send_json()/wp_die() filters.
	 *
	 * @return void
	 */
	public function tear_down() {
		remove_filter( 'wp_doing_ajax', $this->doing_ajax_filter );
		remove_filter( 'wp_die_ajax_handler', $this->ajax_die_handler_filter );

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
	 * Builds the token to submit, from the fixture's `token` config value.
	 *
	 * A plain string is used as-is (e.g. an unparsable token); an array is
	 * treated as JWT claims, with the '{user_id}', '{uuid}', and '{exp_past}'
	 * placeholders resolved against the seeded user/app-password.
	 *
	 * @param string|array<string, mixed> $token_config Fixture `token` value.
	 * @param int|null                    $user_id      Seeded user id, if any.
	 * @param string|null                 $uuid         Seeded app-password uuid, if any.
	 * @return string
	 */
	private function build_token( $token_config, ?int $user_id, ?string $uuid ): string {
		if ( is_string( $token_config ) ) {
			return $token_config;
		}

		$placeholders = [
			'{user_id}'  => $user_id,
			'{uuid}'     => $uuid,
			'{exp_past}' => time() - 3600,
		];

		$claims = array_map(
			static function ( $value ) use ( $placeholders ) {
				return $placeholders[ $value ] ?? $value;
			},
			$token_config
		);

		return JWT::encode( $claims, SecretManager::get_secret() );
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
	public function testShouldHandleRequestAccordingToConfig( array $config, array $expected ): void {
		if ( null !== $config['method'] ) {
			$_SERVER['REQUEST_METHOD'] = $config['method'];
		}

		$user_id = null;
		$uuid    = null;
		if ( $config['create_app_password'] ) {
			list( $user_id, $uuid ) = $this->create_user_with_app_password();
		}

		if ( null !== $config['token'] ) {
			$_POST['token'] = $this->build_token( $config['token'], $user_id, $uuid );
		}

		if ( null !== $config['client_id_post'] ) {
			$_POST['client_id'] = $config['client_id_post'];
		}

		$data = $this->capture( new RevokeEndpoint() );

		if ( null !== $expected['error'] ) {
			$this->assertSame( $expected['error'], $data['error'] ?? null );
		} else {
			$this->assertSame( [], $data );
		}

		if ( null !== $expected['app_password_deleted'] ) {
			$app_password = \WP_Application_Passwords::get_user_application_password( $user_id, $uuid );

			if ( $expected['app_password_deleted'] ) {
				$this->assertNull( $app_password, 'The Application Password must be deleted, revoking the session.' );
			} else {
				$this->assertIsArray( $app_password, 'The Application Password must NOT be deleted on a client_id mismatch.' );
			}
		}
	}
}
