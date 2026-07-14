<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Logging\McpLogger;

use WPMedia\MCP\OAuth\Logging\McpLogger;
use WPMedia\MCP\OAuth\Tests\Unit\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Logging\McpLogger::log
 *
 * @covers \WPMedia\MCP\OAuth\Logging\McpLogger::log
 */
class LogTest extends TestCase {

	/**
	 * Writes (or skips) a log entry according to the WP_DEBUG_LOG/WP_DEBUG
	 * constants and the (now vestigial) $debug_only flag.
	 *
	 * WP_DEBUG_LOG/WP_DEBUG are define()d constants that, once set, cannot be
	 * redefined or undefined for the rest of the PHP process. @runInSeparateProcess
	 * forks a fresh PHP process per data set (not just per test method), so a
	 * constant defined by one row can never leak into another row or into any
	 * other test in the suite. @preserveGlobalState disabled avoids attempting to
	 * serialise Brain\Monkey/Mockery state across that process boundary.
	 *
	 * error_log() itself is exercised for real (not Brain\Monkey-mocked): Patchwork
	 * refuses to redefine it without a "redefinable-internals" patchwork.json entry
	 * ("Please include {"redefinable-internals": ["error_log"]}..."), which this
	 * repo does not otherwise need. Pointing the 'error_log' ini directive at a
	 * scratch file and inspecting it afterwards asserts the same outcome (whether
	 * a line was written) without that extra config.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldGateLoggingOnWpDebugLog( array $config, array $expected ): void {
		if ( $config['define_wp_debug_log'] ?? false ) {
			define( 'WP_DEBUG_LOG', $config['wp_debug_log_value'] );
		}

		if ( $config['define_wp_debug'] ?? false ) {
			define( 'WP_DEBUG', $config['wp_debug_value'] );
		}

		$log_file = tempnam( sys_get_temp_dir(), 'mcp-oauth-logtest-' );
		ini_set( 'error_log', $log_file ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- test-only, redirects error_log() to a scratch file within an isolated process; never runs in production.

		try {
			McpLogger::log( 'TEST', 'test message', [], $config['debug_only'] );

			$written = file_exists( $log_file ) ? trim( (string) file_get_contents( $log_file ) ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a local scratch file, not a remote URL.

			$this->assertSame( $expected['error_log_called'], '' !== $written );
		} finally {
			if ( file_exists( $log_file ) ) {
				unlink( $log_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- wp_delete_file() requires WordPress to be bootstrapped; this is a Brain\Monkey unit test with no WP runtime.
			}
		}
	}
}
