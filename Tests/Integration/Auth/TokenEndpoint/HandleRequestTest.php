<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth\TokenEndpoint;

use WPMedia\MCP\OAuth\Auth\JWT;
use WPMedia\MCP\OAuth\Auth\SecretManager;
use WPMedia\MCP\OAuth\Auth\TokenEndpoint;
use WPMedia\MCP\OAuth\Tests\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\TokenEndpoint::handle_request.
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
 * Both grant types exercise the real SecretManager/JWT/WP_Application_Passwords
 * collaborators and real transients/user-meta end to end, so this suite is
 * integration-only.
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
		$this->post_backup   = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- backing up for tear_down(), not processing input.

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
	 * Builds the refresh token to submit, from the fixture's `refresh_token`
	 * config value.
	 *
	 * A plain string is used as-is (e.g. an unparsable token); an array is
	 * treated as JWT claims, with the '{user_id}' and '{uuid}' placeholders
	 * resolved against the seeded user/app-password, and `iss` defaulted to
	 * this site's home_url() and `type` to 'refresh' unless overridden.
	 *
	 * @param string|array<string, mixed> $token_config Fixture `refresh_token` value.
	 * @param int|null                    $user_id      Seeded user id, if any.
	 * @param string|null                 $uuid         Seeded app-password uuid, if any.
	 * @return string
	 */
	private function build_token( $token_config, ?int $user_id, ?string $uuid ): string {
		if ( is_string( $token_config ) ) {
			return $token_config;
		}

		$placeholders = [
			'{user_id}' => $user_id,
			'{uuid}'    => $uuid,
		];

		$claims = array_map(
			static function ( $value ) use ( $placeholders ) {
				return $placeholders[ $value ] ?? $value;
			},
			$token_config
		);

		$claims = array_merge(
			[
				'iss'  => home_url(),
				'type' => 'refresh',
			],
			$claims
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
		if ( isset( $config['method'] ) ) {
			$_SERVER['REQUEST_METHOD'] = $config['method'];
		}

		$user_id = null;
		$uuid    = null;

		if ( 'plain' === ( $config['user'] ?? null ) ) {
			$user_id = self::factory()->user->create();
		} elseif ( 'with_app_password' === ( $config['user'] ?? null ) ) {
			list( $user_id, $uuid ) = $this->create_user_with_app_password( $config['app_password_client_id'] ?? '' );
		}

		$evicted_uuid = null;
		if ( isset( $config['pre_sessions'] ) ) {
			$count = 'cap' === $config['pre_sessions'] ? TokenEndpoint::MAX_SESSIONS_PER_CLIENT : (int) $config['pre_sessions'];

			for ( $i = 0; $i < $count; $i++ ) {
				$created = \WP_Application_Passwords::create_new_application_password(
					$user_id,
					[
						'name'   => 'mcp-test',
						'app_id' => $config['app_password_client_id'],
					]
				);

				if ( null === $evicted_uuid ) {
					$evicted_uuid = (string) $created[1]['uuid'];
				}
			}
		}

		$meta_uuid = $config['refresh_jti_uuid_override'] ?? $uuid;
		if ( isset( $config['refresh_jti_seed'] ) ) {
			update_user_meta( $user_id, TokenEndpoint::REFRESH_JTI_META_PREFIX . $meta_uuid, $config['refresh_jti_seed'] );
		}

		if ( isset( $config['transient'] ) ) {
			$transient = $config['transient'];

			set_transient(
				'mcp_oauth_code_' . $transient['name'],
				[
					'user_id'        => $user_id,
					'client_id'      => $transient['client_id'],
					'client_name'    => $transient['client_name'],
					'code_challenge' => JWT::base64url_encode( hash( 'sha256', $transient['verifier'], true ) ),
					'redirect_uri'   => $transient['redirect_uri'],
				],
				60
			);
		}

		foreach ( $config['post'] ?? [] as $key => $value ) {
			$_POST[ $key ] = $value;
		}

		if ( isset( $config['refresh_token'] ) ) {
			$_POST['refresh_token'] = $this->build_token( $config['refresh_token'], $user_id, $meta_uuid );
		}

		$data = $this->capture();

		if ( isset( $expected['error'] ) ) {
			$this->assertSame( $expected['error'], $data['error'] ?? null );
		}

		if ( $expected['success'] ?? false ) {
			$this->assertArrayHasKey( 'access_token', $data );
			$this->assertArrayHasKey( 'refresh_token', $data );
			$this->assertSame( 'Bearer', $data['token_type'] ?? null );
			$this->assertSame( 'mcp', $data['scope'] ?? null );
			$this->assertSame( HOUR_IN_SECONDS, $data['expires_in'] ?? null );
		}

		if ( isset( $expected['transient_consumed'] ) ) {
			$this->assertFalse( get_transient( 'mcp_oauth_code_' . $expected['transient_consumed'] ) );
		}

		if ( $expected['claims_bound_to_session'] ?? false ) {
			$claims = JWT::decode( (string) $data['access_token'], SecretManager::get_secret() );
			$this->assertIsArray( $claims );
			$this->assertSame( (string) $user_id, $claims['sub'] );
			$this->assertSame( get_rest_url( null, 'mcp/mcp-oauth-server' ), $claims['aud'] );

			$issued_uuid = (string) $claims['app_pass_id'];
			$this->assertIsArray( \WP_Application_Passwords::get_user_application_password( $user_id, $issued_uuid ) );

			if ( $expected['refresh_jti_meta_not_empty'] ?? false ) {
				$this->assertNotEmpty( get_user_meta( $user_id, TokenEndpoint::REFRESH_JTI_META_PREFIX . $issued_uuid, true ) );
			}
		}

		if ( $expected['claims_app_pass_id_is_uuid'] ?? false ) {
			$claims = JWT::decode( (string) $data['access_token'], SecretManager::get_secret() );
			$this->assertIsArray( $claims );
			$this->assertSame( $uuid, $claims['app_pass_id'] );
		}

		if ( isset( $expected['refresh_jti_meta_changed_from'] ) ) {
			$this->assertNotSame(
				$expected['refresh_jti_meta_changed_from'],
				get_user_meta( $user_id, TokenEndpoint::REFRESH_JTI_META_PREFIX . $uuid, true )
			);
		}

		if ( isset( $expected['refresh_jti_meta_cleared'] ) ) {
			$this->assertEmpty( get_user_meta( $user_id, TokenEndpoint::REFRESH_JTI_META_PREFIX . $meta_uuid, true ) );
		}

		if ( isset( $expected['app_password_exists'] ) ) {
			$app_password = \WP_Application_Passwords::get_user_application_password( $user_id, $uuid );

			if ( $expected['app_password_exists'] ) {
				$this->assertIsArray( $app_password );
			} else {
				$this->assertNull( $app_password );
			}
		}

		if ( $expected['eviction_check'] ?? false ) {
			$client_id = $config['app_password_client_id'];
			$remaining = array_filter(
				\WP_Application_Passwords::get_user_application_passwords( $user_id ),
				static function ( $item ) use ( $client_id ) {
					return $client_id === $item['app_id'];
				}
			);
			$this->assertCount( TokenEndpoint::MAX_SESSIONS_PER_CLIENT, $remaining );
			$this->assertNull( \WP_Application_Passwords::get_user_application_password( $user_id, $evicted_uuid ) );
		}
	}
}
