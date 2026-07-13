<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Auth;

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
	 * Deletes the rotation marker for the deleted Application Password's UUID.
	 *
	 * @return void
	 */
	public function testShouldPurgeMetaForGivenUuid(): void {
		$user_id = self::factory()->user->create();
		$uuid    = 'session-uuid-1';

		update_user_meta( $user_id, TokenEndpoint::REFRESH_JTI_META_PREFIX . $uuid, 'some-jti' );

		( new TokenEndpoint() )->purge_refresh_jti_meta( $user_id, [ 'uuid' => $uuid ] );

		$this->assertEmpty( get_user_meta( $user_id, TokenEndpoint::REFRESH_JTI_META_PREFIX . $uuid, true ) );
	}

	/**
	 * Does nothing when the deleted record carries no UUID.
	 *
	 * @return void
	 */
	public function testShouldDoNothingWhenUuidMissing(): void {
		$user_id = self::factory()->user->create();
		$uuid    = 'session-uuid-2';

		update_user_meta( $user_id, TokenEndpoint::REFRESH_JTI_META_PREFIX . $uuid, 'some-jti' );

		( new TokenEndpoint() )->purge_refresh_jti_meta( $user_id, [] );

		// An unrelated session's marker is left intact.
		$this->assertSame(
			'some-jti',
			get_user_meta( $user_id, TokenEndpoint::REFRESH_JTI_META_PREFIX . $uuid, true )
		);
	}
}
