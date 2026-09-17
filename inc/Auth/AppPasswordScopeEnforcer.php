<?php
/**
 * Application Password Scope Enforcer.
 *
 * Every MCP OAuth session mints a WordPress core Application Password
 * (TokenEndpoint::handle_authorization_code()) anchoring its JWT pair. That
 * Application Password is an independent Basic Auth credential WordPress
 * core will accept on any authenticated REST route, not just the MCP
 * endpoint. This hooks `rest_authentication_errors` to reject one of this
 * library's own Application Passwords when it is used off the MCP route —
 * independent of, and in addition to, the JWT aud/iss/expiry checks in
 * OAuthHttpTransport, which guard the JWT rather than the raw credential.
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
	 * Gate order matters: the route check runs first (cheap short-circuit)
	 * before any ownership lookup. get_current_user_id() is called before
	 * rest_get_authenticated_app_password() on purpose — it forces the
	 * determine_current_user chain (and therefore wp_validate_application_password())
	 * to resolve, which is what populates the app-password uuid global read
	 * immediately after. Wired at the default filter priority; correctness
	 * does not depend on priority ordering.
	 *
	 * @param mixed $result Existing authentication result/error, or null.
	 * @return mixed
	 */
	public function maybe_block_out_of_scope( $result ) {
		// Never overwrite a prior auth result/error (WP core idiom for this filter).
		if ( null !== $result ) {
			return $result;
		}

		// Keep identical to the aud route in OAuthHttpTransport.
		$current_route = ltrim( untrailingslashit( (string) ( $GLOBALS['wp']->query_vars['rest_route'] ?? '' ) ), '/' );

		if ( 'mcp/mcp-oauth-server' === $current_route ) {
			return $result;
		}

		// Forces determine_current_user (-> wp_validate_application_password()),
		// populating the app-password uuid global before it is read below.
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
			// Not one of ours (foreign Application Password) — untouched.
			return $result;
		}

		return new WP_Error(
			'mcp_oauth_app_password_out_of_scope',
			__( 'This Application Password is scoped to the MCP endpoint and cannot be used elsewhere.', 'mcp-oauth' ),
			[ 'status' => 401 ]
		);
	}
}
