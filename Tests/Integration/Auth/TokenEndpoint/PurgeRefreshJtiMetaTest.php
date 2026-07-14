<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth\TokenEndpoint;

use WPMedia\MCP\OAuth\Auth\TokenEndpoint;
use WPMedia\MCP\OAuth\Tests\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Auth\TokenEndpoint::purge_refresh_jti_meta.
 *
 * Hooked on `wp_delete_application_password`, this removes the per-session
 * refresh-token rotation marker (user meta) when the anchoring Application
 * Password is deleted. Exercised against real user meta.
 *
 * @covers \WPMedia\MCP\OAuth\Auth\TokenEndpoint::purge_refresh_jti_meta
 */
class PurgeRefreshJtiMetaTest extends TestCase {

	/**
	 * Deletes the rotation marker only when the deleted record's `$item`
	 * carries the matching uuid, driven by the scenario data in the sibling
	 * fixture file.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldPurgeRefreshJtiMetaAccordingToConfig( array $config, array $expected ): void {
		$user_id = self::factory()->user->create();
		$uuid    = 'session-uuid';

		update_user_meta( $user_id, TokenEndpoint::REFRESH_JTI_META_PREFIX . $uuid, 'some-jti' );

		$item = $config['pass_uuid'] ? [ 'uuid' => $uuid ] : [];

		( new TokenEndpoint() )->purge_refresh_jti_meta( $user_id, $item );

		if ( $expected['should_purge'] ) {
			$this->assertEmpty( get_user_meta( $user_id, TokenEndpoint::REFRESH_JTI_META_PREFIX . $uuid, true ) );
		} else {
			// An unrelated session's marker is left intact.
			$this->assertSame( 'some-jti', get_user_meta( $user_id, TokenEndpoint::REFRESH_JTI_META_PREFIX . $uuid, true ) );
		}
	}
}
