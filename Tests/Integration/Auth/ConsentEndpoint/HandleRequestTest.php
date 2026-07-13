<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth\ConsentEndpoint;

use RuntimeException;
use WPDieException;
use WPMedia\MCP\OAuth\Auth\ConsentEndpoint;
use WPMedia\MCP\OAuth\Tests\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\ConsentEndpoint::handle_request
 *
 * `handle_request()` always terminates via `wp_die()` or `wp_redirect(); exit;`.
 * Both are intercepted so the tests can run in-process:
 *  - `wp_die()` is already converted into a `WPDieException` by the base
 *    WordPress test case (see `WP_UnitTestCase::wp_die_handler()`), so it is
 *    simply caught/expected.
 *  - `wp_redirect()` has no such built-in interception, so a filter that
 *    throws is installed for the duration of each test.
 *
 * @covers \WPMedia\MCP\OAuth\Auth\ConsentEndpoint::handle_request
 */
class HandleRequestTest extends TestCase {

	const REDIRECT_URI = 'https://client.example/cb';

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
	 * Backup of $_REQUEST so per-test mutations don't leak.
	 *
	 * @var array<string, mixed>
	 */
	private $request_backup;

	/**
	 * Closure installed on the `wp_redirect` filter for the duration of the
	 * test, kept so it can be removed again in tear_down().
	 *
	 * @var callable
	 */
	private $redirect_interceptor;

	/**
	 * Backs up superglobals and installs the wp_redirect interceptor.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->server_backup  = $_SERVER;
		$this->post_backup    = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- backing up the superglobal for tear_down(), not processing form input.
		$this->request_backup = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- backing up the superglobal for tear_down(), not processing form input.

		$this->redirect_interceptor = static function ( $location ) {
			throw new RuntimeException( 'REDIRECT:' . $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message consumed by the test itself, never rendered as output.
		};

		// The interceptor intentionally always throws (to abort before the exit
		// that follows wp_redirect()), so it never returns a value like a real filter.
		add_filter( 'wp_redirect', $this->redirect_interceptor ); // @phpstan-ignore-line
	}

	/**
	 * Restores superglobals and removes the wp_redirect interceptor.
	 *
	 * @return void
	 */
	public function tear_down() {
		remove_filter( 'wp_redirect', $this->redirect_interceptor );

		$_SERVER  = $this->server_backup;
		$_POST    = $this->post_backup;
		$_REQUEST = $this->request_backup;

		parent::tear_down();
	}

	/**
	 * Rejects any request that isn't a POST with a 405.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldDieWithMethodNotAllowedForNonPostRequests( array $config, array $expected ): void {
		if ( null === $config['method'] ) {
			unset( $_SERVER['REQUEST_METHOD'] );
		} else {
			$_SERVER['REQUEST_METHOD'] = $config['method'];
		}

		$this->assertDiesWithResponseCode( $expected['response'], new ConsentEndpoint() );
	}

	/**
	 * Rejects a POST from a logged-out visitor with a 401.
	 *
	 * @return void
	 */
	public function testShouldDieWhenUserIsNotLoggedIn(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		wp_set_current_user( 0 );

		$this->assertDiesWithResponseCode( 401, new ConsentEndpoint() );
	}

	/**
	 * Rejects a logged-in POST that is missing the state parameter with a 400.
	 *
	 * @return void
	 */
	public function testShouldDieWhenStateParamIsMissing(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$this->logInNewUser();

		unset( $_POST['state'] );

		$this->assertDiesWithResponseCode( 400, new ConsentEndpoint() );
	}

	/**
	 * Dies when check_admin_referer() rejects a missing/invalid nonce, before
	 * the state transient is ever consumed.
	 *
	 * @return void
	 */
	public function testShouldDieWhenNonceIsInvalid(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$this->logInNewUser();

		$state          = 'nonce-failure-state';
		$_POST['state'] = $state;
		// Deliberately no (or an invalid) mcp_consent_nonce value.
		$_POST['mcp_consent_nonce']    = 'not-a-valid-nonce';
		$_REQUEST['mcp_consent_nonce'] = 'not-a-valid-nonce';

		$this->expectException( WPDieException::class );

		( new ConsentEndpoint() )->handle_request();
	}

	/**
	 * Dies with a 400 when the state transient is absent/expired, even with a
	 * valid nonce.
	 *
	 * @return void
	 */
	public function testShouldDieWhenStateTransientIsMissing(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$this->logInNewUser();

		$state          = 'expired-state';
		$_POST['state'] = $state;
		$this->useValidNonce( $state );

		// No transient seeded for this state.
		$this->assertDiesWithResponseCode( 400, new ConsentEndpoint() );
	}

	/**
	 * Denying access redirects to the client's redirect_uri with
	 * error=access_denied, consumes the state transient, and never mints an
	 * auth code.
	 *
	 * @return void
	 */
	public function testShouldRedirectWithAccessDeniedWhenActionIsNotAllow(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$this->logInNewUser();

		$state          = 'deny-state';
		$_POST['state'] = $state;
		$this->useValidNonce( $state );
		$state_data = $this->seedStateTransient( $state );

		$_POST['mcp_action'] = 'deny';

		$query = $this->assertRedirectsTo( $state_data['redirect_uri'], new ConsentEndpoint() );

		$this->assertSame( 'access_denied', $query['error'] );
		$this->assertSame( $state, $query['state'] );

		$this->assertFalse( get_transient( 'mcp_oauth_state_' . $state ), 'State transient should be consumed.' );
		$this->assertSame( [], $this->getAuthCodeTransientNames(), 'No auth code transient should have been created.' );
	}

	/**
	 * Allowing access mints a single-use auth code transient carrying the
	 * user/client/PKCE details, consumes the state transient, and redirects to
	 * the client's redirect_uri with the code.
	 *
	 * @return void
	 */
	public function testShouldIssueAuthCodeAndRedirectWhenActionIsAllow(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$user_id                   = $this->logInNewUser();

		$state          = 'allow-state';
		$_POST['state'] = $state;
		$this->useValidNonce( $state );
		$state_data = $this->seedStateTransient( $state );

		$_POST['mcp_action'] = 'allow';

		$query = $this->assertRedirectsTo( $state_data['redirect_uri'], new ConsentEndpoint() );

		$this->assertSame( $state, $query['state'] );
		$this->assertArrayHasKey( 'code', $query );
		$this->assertNotSame( '', $query['code'] );

		$code_data = get_transient( 'mcp_oauth_code_' . $query['code'] );

		$this->assertIsArray( $code_data );
		$this->assertSame( $user_id, $code_data['user_id'] );
		$this->assertSame( $state_data['client_id'], $code_data['client_id'] );
		$this->assertSame( $state_data['redirect_uri'], $code_data['redirect_uri'] );
		$this->assertSame( $state_data['code_challenge'], $code_data['code_challenge'] );

		$this->assertFalse( get_transient( 'mcp_oauth_state_' . $state ), 'State transient should be consumed.' );
	}

	/**
	 * Creates and logs in a new user, returning their user ID.
	 *
	 * @return int
	 */
	private function logInNewUser(): int {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * Generates a valid consent nonce for the given state and places it where
	 * check_admin_referer() reads it from ($_REQUEST).
	 *
	 * @param string $state State value the nonce is scoped to.
	 *
	 * @return void
	 */
	private function useValidNonce( string $state ): void {
		$nonce                         = wp_create_nonce( 'mcp_consent_' . $state );
		$_POST['mcp_consent_nonce']    = $nonce;
		$_REQUEST['mcp_consent_nonce'] = $nonce;
	}

	/**
	 * Seeds the state transient consumed by handle_request().
	 *
	 * @param string               $state     State value the transient is keyed on.
	 * @param array<string, mixed> $overrides Values overriding the defaults.
	 *
	 * @return array<string, mixed> The seeded transient data.
	 */
	private function seedStateTransient( string $state, array $overrides = [] ): array {
		$data = array_merge(
			[
				'redirect_uri'   => self::REDIRECT_URI,
				'client_id'      => 'client-123',
				'client_name'    => 'Test Client',
				'code_challenge' => 'challenge-abc',
			],
			$overrides
		);

		set_transient( 'mcp_oauth_state_' . $state, $data, 60 );

		return $data;
	}

	/**
	 * Asserts that calling handle_request() on the given endpoint dies with
	 * the expected HTTP response code.
	 *
	 * @param int             $response_code Expected wp_die() response code.
	 * @param ConsentEndpoint $endpoint      Endpoint under test.
	 *
	 * @return void
	 */
	private function assertDiesWithResponseCode( int $response_code, ConsentEndpoint $endpoint ): void {
		try {
			$endpoint->handle_request();
			$this->fail( 'Expected WPDieException was not thrown.' );
		} catch ( WPDieException $e ) {
			$this->assertSame( $response_code, $e->getCode() );
		}
	}

	/**
	 * Asserts that calling handle_request() on the given endpoint redirects to
	 * the given base URL, and returns the redirect's parsed query args.
	 *
	 * @param string          $expected_base_url Expected redirect_uri (without query string).
	 * @param ConsentEndpoint $endpoint          Endpoint under test.
	 *
	 * @return array<string, string> Parsed query args of the redirect location.
	 */
	private function assertRedirectsTo( string $expected_base_url, ConsentEndpoint $endpoint ): array {
		try {
			$endpoint->handle_request();
			$this->fail( 'Expected redirect was not thrown.' );
		} catch ( RuntimeException $e ) {
			$location = substr( $e->getMessage(), strlen( 'REDIRECT:' ) );
		}

		$this->assertStringStartsWith( $expected_base_url, $location );

		$query = [];
		parse_str( (string) wp_parse_url( $location, PHP_URL_QUERY ), $query );

		return $query;
	}

	/**
	 * Lists the names of any `mcp_oauth_code_*` transients currently stored in
	 * the options table.
	 *
	 * @return string[]
	 */
	private function getAuthCodeTransientNames(): array {
		global $wpdb;

		$prefix = '_transient_mcp_oauth_code_';

		$names = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test assertion, not production code; transients aren't cacheable by option name pattern.
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( $prefix ) . '%'
			)
		);

		return array_map(
			static function ( $name ) use ( $prefix ) {
				return substr( $name, strlen( $prefix ) );
			},
			$names
		);
	}
}
