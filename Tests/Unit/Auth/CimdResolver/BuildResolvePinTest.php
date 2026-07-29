<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth\CimdResolver;

use Mockery;
use ReflectionMethod;
use WPMedia\MCP\OAuth\Auth\CimdResolver;
use WPMedia\MCP\OAuth\Auth\ClaudeClientVerifier;
use WPMedia\MCP\OAuth\Tests\Unit\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\CimdResolver::build_resolve_pin
 *
 * @covers \WPMedia\MCP\OAuth\Auth\CimdResolver::build_resolve_pin
 */
class BuildResolvePinTest extends TestCase {

	/**
	 * Builds the exact "host:443:ip" CURLOPT_RESOLVE pin entry.
	 *
	 * Pure host,ip -> string method: no network or cURL call. In production the
	 * IP comes from CURLINFO_PRIMARY_IP, but the helper itself is deterministic.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldBuildResolvePinAccordingToConfig( array $config, array $expected ): void {
		$verifier = Mockery::mock( ClaudeClientVerifier::class );
		$resolver = new CimdResolver( $verifier );

		$method = new ReflectionMethod( CimdResolver::class, 'build_resolve_pin' );
		$method->setAccessible( true );

		$this->assertSame(
			$expected['pin'],
			$method->invoke( $resolver, $config['host'], $config['ip'] )
		);
	}
}
