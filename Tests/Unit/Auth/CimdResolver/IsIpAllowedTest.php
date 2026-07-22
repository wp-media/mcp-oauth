<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Auth\CimdResolver;

use Mockery;
use ReflectionMethod;
use WPMedia\MCP\OAuth\Auth\CimdResolver;
use WPMedia\MCP\OAuth\Auth\ClaudeClientVerifier;
use WPMedia\MCP\OAuth\Tests\Unit\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\CimdResolver::is_ip_allowed
 *
 * @covers \WPMedia\MCP\OAuth\Auth\CimdResolver::is_ip_allowed
 */
class IsIpAllowedTest extends TestCase {

	/**
	 * Determines whether a connected IP is a routable public address.
	 *
	 * Pure range validation: no native DNS/cURL calls, so every range case is
	 * covered directly with zero mocking.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldDetermineWhetherIpIsAllowed( array $config, array $expected ): void {
		$verifier = Mockery::mock( ClaudeClientVerifier::class );
		$resolver = new CimdResolver( $verifier );

		$method = new ReflectionMethod( CimdResolver::class, 'is_ip_allowed' );
		$method->setAccessible( true );

		$this->assertSame(
			$expected['allowed'],
			$method->invoke( $resolver, $config['ip'] )
		);
	}
}
