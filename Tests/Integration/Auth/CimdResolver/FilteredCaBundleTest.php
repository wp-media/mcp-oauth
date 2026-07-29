<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth\CimdResolver;

use WPMedia\MCP\OAuth\Auth\CimdResolver;
use WPMedia\MCP\OAuth\Auth\ClaudeClientVerifier;
use WPMedia\MCP\OAuth\Tests\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\CimdResolver::filtered_ca_bundle
 *
 * Exercised as an integration test because the method resolves the effective
 * CA bundle through the real `http_request_args` filter and the WordPress
 * ABSPATH/WPINC constants — the same path WP_Http::request() uses for the real
 * fetch. `filtered_ca_bundle()` is protected, so it is invoked via reflection.
 *
 * @covers \WPMedia\MCP\OAuth\Auth\CimdResolver::filtered_ca_bundle
 */
class FilteredCaBundleTest extends TestCase {

	const URL = 'https://example.com/cimd.json';

	/**
	 * Removes any http_request_args override registered by a test.
	 *
	 * @return void
	 */
	public function tear_down() {
		remove_all_filters( 'http_request_args' );

		parent::tear_down();
	}

	/**
	 * Invokes the protected filtered_ca_bundle() for the test URL.
	 *
	 * @return string|null
	 */
	private function resolve_bundle() {
		$resolver = new CimdResolver( new ClaudeClientVerifier() );
		$method   = $this->get_reflective_method( 'filtered_ca_bundle', CimdResolver::class );

		return $method->invoke( $resolver, self::URL );
	}

	/**
	 * Without any override, resolves WordPress core's default bundle (the same
	 * path WP_Http::request() seeds and the real fetch verifies against).
	 *
	 * @return void
	 */
	public function testShouldResolveTheCoreDefaultBundleWithoutOverride(): void {
		$expected = ABSPATH . (string) constant( 'WPINC' ) . '/certificates/ca-bundle.crt';
		$this->assertSame( $expected, $this->resolve_bundle() );
	}

	/**
	 * Honors a site's http_request_args override of 'sslcertificates', so the
	 * preflight verifies against the same custom CA bundle as the real fetch.
	 *
	 * @return void
	 */
	public function testShouldHonorHttpRequestArgsOverride(): void {
		$custom = tempnam( sys_get_temp_dir(), 'mcp-oauth-ca-' );

		add_filter(
			'http_request_args',
			static function ( $args ) use ( $custom ) {
				$args['sslcertificates'] = $custom;

				return $args;
			}
		);

		try {
			$this->assertSame( $custom, $this->resolve_bundle() );
		} finally {
			if ( is_string( $custom ) && file_exists( $custom ) ) {
				wp_delete_file( $custom );
			}
		}
	}

	/**
	 * Falls back to the system CA store (null) when the resolved bundle path is
	 * not readable, rather than pinning a bogus path.
	 *
	 * @return void
	 */
	public function testShouldReturnNullWhenOverrideBundleIsUnreadable(): void {
		add_filter(
			'http_request_args',
			static function ( $args ) {
				$args['sslcertificates'] = '/nonexistent/mcp-oauth/ca-bundle.crt';

				return $args;
			}
		);

		$this->assertNull( $this->resolve_bundle() );
	}
}
