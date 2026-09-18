<?php
/**
 * Application Password Scope Enforcer.
 *
 * Each MCP OAuth session mints a WordPress Application Password — a Basic Auth
 * credential core accepts on any REST route. This rejects one of ours when used
 * off the MCP route, complementing the JWT checks in OAuthHttpTransport.
 */

declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Auth;

use WP_Error;

/**
 * Scopes this library's Application Passwords to the MCP REST route.
 */
class AppPasswordScopeEnforcer {

	/**
	 * Reject this library's Application Passwords when used off the MCP route.
	 *
	 * The route check runs first (cheap short-circuit). get_current_user_id() is
	 * called before rest_get_authenticated_app_password() to force the
	 * determine_current_user chain that populates the app-password uuid global.
	 *
	 * @param mixed $result Existing authentication result/error, or null.
	 * @return mixed
	 */
	public function maybe_block_out_of_scope( $result ) {
		// Never overwrite a prior auth result/error (WP core idiom).
		if ( null !== $result ) {
			return $result;
		}

		// Keep identical to the aud route in OAuthHttpTransport.
		$current_route = ltrim( untrailingslashit( (string) ( $GLOBALS['wp']->query_vars['rest_route'] ?? '' ) ), '/' );

		if ( 'mcp/mcp-oauth-server' === $current_route ) {
			return $result;
		}

		// Forces uuid-global population before the read below.
		$user_id = get_current_user_id();

		if ( 0 === $user_id ) {
			return $result;
		}

		$uuid = rest_get_authenticated_app_password();

		if ( empty( $uuid ) ) {
			return $result;
		}

		$marker = get_user_meta( $user_id, TokenEndpoint::REFRESH_JTI_META_PREFIX . $uuid, true );

		if ( '' === (string) $marker ) {
			// Foreign Application Password — untouched.
			return $result;
		}

		return new WP_Error(
			'mcp_oauth_app_password_out_of_scope',
			__( 'This Application Password is scoped to the MCP endpoint and cannot be used elsewhere.', 'mcp-oauth' ),
			[ 'status' => 401 ]
		);
	}
}
