<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth\Discovery\HealthCheck;

use Brain\Monkey\Functions;
use Mockery;
use WPMedia\MCP\OAuth\Auth\Discovery\HealthCheck;
use WPMedia\MCP\OAuth\Context;
use WPMedia\MCP\OAuth\Tests\Unit\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\Discovery\HealthCheck::run_self_check
 *
 * The native wp_remote_get() call is isolated behind the protected fetch()
 * seam, so each scenario here partial-mocks fetch() rather than stubbing
 * wp_remote_get() directly.
 *
 * @covers \WPMedia\MCP\OAuth\Auth\Discovery\HealthCheck::run_self_check
 */
class RunSelfCheckTest extends TestCase {

	/**
	 * Stubs translation/escaping helpers used by build_result()'s description/actions text.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		$this->stubTranslationFunctions();
		$this->stubEscapeFunctions();
	}

	/**
	 * Builds a partial mock of HealthCheck with fetch() mockable.
	 *
	 * @param Context $context OAuth server context.
	 * @return HealthCheck&Mockery\MockInterface
	 */
	private function partial_mock( Context $context ) {
		return Mockery::mock( HealthCheck::class, [ $context ] )
			->makePartial()
			->shouldAllowMockingProtectedMethods();
	}

	/**
	 * When the server is disabled, reports 'good' without any network call or transient lookup.
	 *
	 * @return void
	 */
	public function testShouldReportGoodWhenServerDisabled(): void {
		$context = Mockery::mock( Context::class );
		$context->shouldReceive( 'is_enabled' )->once()->andReturn( false );

		Functions\expect( 'get_option' )->never();
		Functions\expect( 'get_transient' )->never();

		$health_check = $this->partial_mock( $context );
		$health_check->shouldNotReceive( 'fetch' );

		$result = $health_check->run_self_check();

		$this->assertSame( 'good', $result['status'] );
		$this->assertSame( HealthCheck::TEST_KEY, $result['test'] );
		$this->assertStringContainsString( 'disabled', $result['description'] );
	}

	/**
	 * When permalinks are plain, recommends enabling pretty permalinks without any network call.
	 *
	 * @return void
	 */
	public function testShouldRecommendPermalinkFixWhenPlainPermalinks(): void {
		$context = Mockery::mock( Context::class );
		$context->shouldReceive( 'is_enabled' )->once()->andReturn( true );

		Functions\expect( 'get_option' )->once()->with( 'permalink_structure' )->andReturn( '' );
		Functions\expect( 'get_transient' )->never();

		$health_check = $this->partial_mock( $context );
		$health_check->shouldNotReceive( 'fetch' );

		$result = $health_check->run_self_check();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'permalink', strtolower( $result['description'] ) );
	}

	/**
	 * Reports 'good' and caches the result when both discovery documents return 200 with valid JSON.
	 *
	 * @return void
	 */
	public function testShouldReportGoodWhenBothDocumentsReturn200(): void {
		$context = Mockery::mock( Context::class );
		$context->shouldReceive( 'is_enabled' )->once()->andReturn( true );

		Functions\expect( 'get_option' )->once()->with( 'permalink_structure' )->andReturn( '/%postname%/' );
		Functions\expect( 'get_transient' )->once()->with( HealthCheck::TRANSIENT_KEY )->andReturn( false );
		Functions\when( 'home_url' )->alias(
			static function ( $path ) {
				return 'https://example.org' . $path;
			}
		);

		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{"ok":true}' );

		$health_check = $this->partial_mock( $context );
		$health_check->shouldReceive( 'fetch' )
			->with( 'https://example.org/.well-known/oauth-protected-resource' )
			->once()
			->andReturn( [ 'ok' => true ] );
		$health_check->shouldReceive( 'fetch' )
			->with( 'https://example.org/.well-known/oauth-authorization-server' )
			->once()
			->andReturn( [ 'ok' => true ] );

		Functions\expect( 'set_transient' )
			->once()
			->with( HealthCheck::TRANSIENT_KEY, Mockery::type( 'array' ), HealthCheck::TRANSIENT_TTL );

		$result = $health_check->run_self_check();

		$this->assertSame( 'good', $result['status'] );
		$this->assertSame( 'Configuration', $result['badge']['label'] );
		$this->assertSame( 'blue', $result['badge']['color'] );
	}

	/**
	 * Reports the combined result as 'critical' (the worse of the two) and names the
	 * specific failing document when only one of the two documents fails.
	 *
	 * @return void
	 */
	public function testShouldReportCriticalCombinedStatusWhenOnlyOneOfTwoDocumentsFails(): void {
		$context = Mockery::mock( Context::class );
		$context->shouldReceive( 'is_enabled' )->once()->andReturn( true );

		Functions\expect( 'get_option' )->once()->with( 'permalink_structure' )->andReturn( '/%postname%/' );
		Functions\expect( 'get_transient' )->once()->with( HealthCheck::TRANSIENT_KEY )->andReturn( false );
		Functions\when( 'home_url' )->alias(
			static function ( $path ) {
				return 'https://example.org' . $path;
			}
		);

		$responses = [
			'protected-resource'   => [
				'status'      => 404,
				'powered_by'  => '',
				'redirect_by' => '',
			],
			'authorization-server' => [
				'status' => 200,
				'body'   => '{"ok":true}',
			],
		];

		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static function ( $response ) use ( $responses ) {
				return $responses[ $response['doc'] ]['status'];
			}
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static function ( $response ) use ( $responses ) {
				return $responses[ $response['doc'] ]['body'] ?? '';
			}
		);
		Functions\when( 'wp_remote_retrieve_header' )->alias(
			static function ( $response, $header ) use ( $responses ) {
				if ( 'x-powered-by' === $header ) {
					return $responses[ $response['doc'] ]['powered_by'] ?? '';
				}

				if ( 'x-redirect-by' === $header ) {
					return $responses[ $response['doc'] ]['redirect_by'] ?? '';
				}

				return '';
			}
		);

		$health_check = $this->partial_mock( $context );
		$health_check->shouldReceive( 'fetch' )
			->with( 'https://example.org/.well-known/oauth-protected-resource' )
			->once()
			->andReturn( [ 'doc' => 'protected-resource' ] );
		$health_check->shouldReceive( 'fetch' )
			->with( 'https://example.org/.well-known/oauth-authorization-server' )
			->once()
			->andReturn( [ 'doc' => 'authorization-server' ] );

		Functions\expect( 'set_transient' )->once();

		$result = $health_check->run_self_check();

		$this->assertSame( 'critical', $result['status'] );
		$this->assertStringContainsString( 'oauth-protected-resource', $result['description'] );
		$this->assertStringNotContainsString( 'oauth-authorization-server', $result['description'] );
	}

	/**
	 * Serves the cached transient result within its TTL without re-fetching.
	 *
	 * @return void
	 */
	public function testShouldServeCachedResultWithinTransientTtlWithoutRefetching(): void {
		$context = Mockery::mock( Context::class );
		$context->shouldReceive( 'is_enabled' )->once()->andReturn( true );

		$cached = [
			'label'       => 'MCP OAuth discovery documents',
			'status'      => 'good',
			'badge'       => [
				'label' => 'Configuration',
				'color' => 'blue',
			],
			'description' => '<p>cached</p>',
			'actions'     => '<p>cached</p>',
			'test'        => HealthCheck::TEST_KEY,
		];

		Functions\expect( 'get_option' )->once()->with( 'permalink_structure' )->andReturn( '/%postname%/' );
		Functions\expect( 'get_transient' )->once()->with( HealthCheck::TRANSIENT_KEY )->andReturn( $cached );
		Functions\expect( 'set_transient' )->never();

		$health_check = $this->partial_mock( $context );
		$health_check->shouldNotReceive( 'fetch' );

		$this->assertSame( $cached, $health_check->run_self_check() );
	}

	/**
	 * Re-fetches and re-caches once the transient has expired (a cache miss, same as the
	 * first-ever run), proving the miss path is independent from the hit path above.
	 *
	 * @return void
	 */
	public function testShouldRefetchAfterTransientExpires(): void {
		$context = Mockery::mock( Context::class );
		$context->shouldReceive( 'is_enabled' )->once()->andReturn( true );

		Functions\expect( 'get_option' )->once()->with( 'permalink_structure' )->andReturn( '/%postname%/' );
		Functions\expect( 'get_transient' )->once()->with( HealthCheck::TRANSIENT_KEY )->andReturn( false );
		Functions\when( 'home_url' )->alias(
			static function ( $path ) {
				return 'https://example.org' . $path;
			}
		);

		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{"ok":true}' );

		$health_check = $this->partial_mock( $context );
		$health_check->shouldReceive( 'fetch' )->twice()->andReturn( [ 'ok' => true ] );

		Functions\expect( 'set_transient' )
			->once()
			->with( HealthCheck::TRANSIENT_KEY, Mockery::type( 'array' ), HealthCheck::TRANSIENT_TTL );

		$result = $health_check->run_self_check();

		$this->assertSame( 'good', $result['status'] );
	}
}
