<?php
/**
 * Application Password Scope Enforcer.
 *
 * Each MCP OAuth session mints a WordPress Application Password — a Basic Auth
 * credential core accepts on any REST or XML-RPC request. This rejects one of
 * ours when authenticated off the MCP route, complementing the JWT checks in
 * OAuthHttpTransport.
 */

declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Auth;

use WP_Error;
use WP_User;

/**
 * Scopes this library's Application Passwords to the MCP REST route.
 */
class AppPasswordScopeEnforcer {

	/**
	 * Reject this library's Application Passwords when used off the MCP route.
	 *
	 * Hooked on wp_authenticate_application_password_errors, which fires for both
	 * REST and XML-RPC once the password matches — so the credential is blocked at
	 * authentication time regardless of transport, and no earlier filter can
	 * bypass it. Adding to $error fails the authentication; core surfaces it to
	 * REST clients as a 401. Only our own passwords (those carrying the
	 * mcp_refresh_jti_ marker) used off the MCP route are rejected.
	 *
	 * @param WP_Error $error Running authentication error; added to, to reject.
	 * @param WP_User  $user  User the Application Password authenticates.
	 * @param array    $item  Application Password record; includes 'uuid'.
	 * @return void
	 */
	public function maybe_block_out_of_scope( WP_Error $error, WP_User $user, array $item ): void {
		// Keep identical to the aud route in OAuthHttpTransport.
		$current_route = ltrim( untrailingslashit( (string) ( $GLOBALS['wp']->query_vars['rest_route'] ?? '' ) ), '/' );

		if ( 'mcp/mcp-oauth-server' === $current_route ) {
			return;
		}

		$uuid = isset( $item['uuid'] ) ? (string) $item['uuid'] : '';

		if ( '' === $uuid ) {
			return;
		}

		$marker = get_user_meta( $user->ID, TokenEndpoint::REFRESH_JTI_META_PREFIX . $uuid, true );

		if ( '' === (string) $marker ) {
			// Foreign Application Password — untouched.
			return;
		}

		$error->add(
			'mcp_oauth_app_password_out_of_scope',
			__( 'This Application Password is scoped to the MCP endpoint and cannot be used elsewhere.', 'mcp-oauth' ),
			[ 'status' => 401 ]
		);
	}
}
