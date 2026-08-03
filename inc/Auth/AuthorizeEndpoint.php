<?php
/**
 * Authorization Endpoint.
 *
 * Handles GET /oauth/authorize — validates the PKCE parameters, stores the
 * challenge in a 60-second transient, then routes the browser onward: a
 * user with an existing WordPress session is redirected straight to
 * /oauth/authorize-callback; otherwise the browser goes to the WordPress
 * login form first, and WordPress delivers the user back to
 * /oauth/authorize-callback after a successful login, where the auth code
 * is issued.
 */

declare(strict_types=1);

namespace WPMedia\MCP\OAuth\Auth;

use WPMedia\MCP\OAuth\Logging\McpLogger;

/**
 * Authorize Endpoint.
 */
class AuthorizeEndpoint {
	/**
	 * Transient TTL for the state parameter (seconds).
	 */
	const STATE_TTL = 60;

	/**
	 * CIMD resolver used to dereference and validate the client_id URL.
	 *
	 * @var CimdResolver
	 */
	private CimdResolver $resolver;

	/**
	 * Constructor.
	 *
	 * @param CimdResolver $resolver CIMD resolver.
	 */
	public function __construct( CimdResolver $resolver ) {
		$this->resolver = $resolver;
	}

	/**
	 * Handle an authorization request.
	 *
	 * @return void
	 */
	public function handle_request(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- OAuth 2.1 authorization request from an external client; CSRF protection is provided by the state parameter and PKCE, not a WP nonce.
		$client_id             = esc_url_raw( wp_unslash( $_GET['client_id'] ?? '' ) );
		$redirect_uri          = esc_url_raw( wp_unslash( $_GET['redirect_uri'] ?? '' ) );
		$response_type         = sanitize_text_field( wp_unslash( $_GET['response_type'] ?? '' ) );
		$code_challenge        = sanitize_text_field( wp_unslash( $_GET['code_challenge'] ?? '' ) );
		$code_challenge_method = sanitize_text_field( wp_unslash( $_GET['code_challenge_method'] ?? '' ) );
		$state                 = sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		McpLogger::log(
			'AUTHORIZE',
			'authorization request received',
			[
				'response_type'         => $response_type,
				'client_id'             => $client_id,
				'redirect_uri'          => $redirect_uri,
				'code_challenge_method' => $code_challenge_method,
				'has_code_challenge'    => '' !== $code_challenge ? 'yes' : 'no',
				'has_state'             => '' !== $state ? 'yes' : 'no',
				'remote_addr'           => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			]
		);

		// Validate the client and redirect_uri BEFORE using redirect_uri in any redirect.
		// Per OAuth 2.1 §7.5.2 and RFC 6749 §10.15, the AS MUST NOT redirect to a URI
		// that has not been positively validated against a registered client.

		if ( '' === $client_id ) {
			McpLogger::log( 'AUTHORIZE', 'rejected: missing client_id' );
			wp_die( esc_html__( 'client_id is required.', 'mcp-oauth' ), esc_html__( 'OAuth Error', 'mcp-oauth' ), [ 'response' => 400 ] );
		}

		// (bool) on the seed: apply_filters_deprecated() returns whatever the legacy
		// callback returned, and wpm_apply_filters_typed() returns that same
		// unvalidated seed when its own type check fails. Without the cast a legacy
		// 'yes'/'0'/null would reach the bool-typed resolve() parameter under
		// strict_types and fatal with a TypeError on a public, unauthenticated
		// endpoint. Both casts are therefore load-bearing, not stylistic.
		$allow_untrusted = (bool) apply_filters_deprecated(
			'rocket_mcp_allow_untrusted_providers',
			[ true ],
			'1.0.1',
			'wpmedia_mcp_oauth_allow_untrusted_providers'
		);

		/**
		 * Filters whether OAuth clients that are not verified trusted publishers may authorize.
		 *
		 * When true (default) any client presenting a valid CIMD document may proceed, and the
		 * consent screen warns the user that the publisher is not verified. When false, the
		 * pre-1.x hard-reject is restored: unverified clients are refused with a 400.
		 *
		 * Must return a real boolean. A non-boolean return is reported via _doing_it_wrong() and
		 * then coerced with a (bool) cast, so a truthy non-boolean leaves untrusted providers
		 * ALLOWED and a falsy one blocks them.
		 *
		 * @param bool $allow_untrusted Whether unverified providers may authorize. Default true.
		 */
		$allow_untrusted = (bool) wpm_apply_filters_typed( 'boolean', 'wpmedia_mcp_oauth_allow_untrusted_providers', $allow_untrusted );

		$client = $this->resolver->resolve( $client_id, $allow_untrusted );
		if ( null === $client ) {
			McpLogger::log( 'AUTHORIZE', 'rejected: client_id could not be resolved via CIMD', [ 'client_id' => $client_id ] );
			wp_die( esc_html__( 'Unknown OAuth client.', 'mcp-oauth' ), esc_html__( 'OAuth Error', 'mcp-oauth' ), [ 'response' => 400 ] );
		}

		$client_verified = ! empty( $client['verified'] );

		if ( ! $allow_untrusted && ! $client_verified ) {
			McpLogger::log( 'AUTHORIZE', 'rejected: client not a verified publisher', [ 'client_id' => $client_id ] );
			wp_die( esc_html__( 'This OAuth client is not a verified publisher.', 'mcp-oauth' ), esc_html__( 'OAuth Error', 'mcp-oauth' ), [ 'response' => 400 ] );
		}

		// Audit trail for the newly allowed tier, so an operator can see which
		// unverified clients were admitted by the filter.
		if ( $allow_untrusted && ! $client_verified ) {
			McpLogger::log( 'AUTHORIZE', 'unverified publisher allowed by filter', [ 'client_id' => $client_id ] );
		}

		if ( '' === $redirect_uri ) {
			McpLogger::log( 'AUTHORIZE', 'rejected: missing redirect_uri' );
			wp_die( esc_html__( 'redirect_uri is required.', 'mcp-oauth' ), esc_html__( 'OAuth Error', 'mcp-oauth' ), [ 'response' => 400 ] );
		}

		if ( ! $this->redirect_uri_matches( $redirect_uri, $client['redirect_uris'] ) ) {
			McpLogger::log(
				'AUTHORIZE',
				'rejected: redirect_uri mismatch',
				[
					'provided'   => $redirect_uri,
					'registered' => $client['redirect_uris'],
				]
			);
			wp_die( esc_html__( 'redirect_uri does not match registered value.', 'mcp-oauth' ), esc_html__( 'OAuth Error', 'mcp-oauth' ), [ 'response' => 400 ] );
		}

		// redirect_uri is now validated — remaining errors may safely redirect to it.

		if ( 'code' !== $response_type ) {
			McpLogger::log( 'AUTHORIZE', 'rejected: unsupported response_type', [ 'response_type' => $response_type ] );
			$this->send_error( $redirect_uri, 'unsupported_response_type', $state, $client_verified );
			return;
		}

		if ( '' === $code_challenge || 'S256' !== $code_challenge_method ) {
			McpLogger::log(
				'AUTHORIZE',
				'rejected: missing or invalid PKCE params',
				[
					'has_code_challenge'    => '' !== $code_challenge ? 'yes' : 'no',
					'code_challenge_method' => $code_challenge_method,
				]
			);
			$this->send_error( $redirect_uri, 'invalid_request', $state, $client_verified );
			return;
		}

		// OAuth 2.1 §4.1.1 requires state; reject rather than generate silently.
		// A server-generated state never reaches the client before the redirect,
		// so the client cannot validate it on return — providing no CSRF protection.
		if ( '' === $state ) {
			McpLogger::log( 'AUTHORIZE', 'rejected: state parameter is required' );
			$this->send_error( $redirect_uri, 'invalid_request', '', $client_verified );
			return;
		}

		// Persist the validated client display data alongside the PKCE state so the
		// consent screen can be rendered after login without a second CIMD fetch.
		set_transient(
			'mcp_oauth_state_' . $state,
			[
				'client_id'             => $client_id,
				'client_name'           => (string) ( $client['client_name'] ?? '' ),
				'client_uri'            => (string) ( $client['client_uri'] ?? '' ),
				// The real trust signal: false is reachable whenever untrusted
				// providers are allowed, and drives the consent-screen warning.
				'verified'              => $client_verified,
				'publisher'             => (string) ( $client['publisher'] ?? '' ),
				'redirect_uri'          => $redirect_uri,
				'code_challenge'        => $code_challenge,
				'code_challenge_method' => $code_challenge_method,
				'state'                 => $state,
			],
			self::STATE_TTL
		);

		// home_url(): the callback is a rewrite endpoint served from the Site Address,
		// so it must match home_url() and not get_site_url() on split-directory installs.
		$callback_url = add_query_arg( 'state', rawurlencode( $state ), home_url( '/oauth/authorize-callback' ) );

		if ( is_user_logged_in() ) {
			McpLogger::log(
				'AUTHORIZE',
				'existing session, skipping login',
				[
					'state'        => $state,
					'callback_url' => $callback_url,
				]
			);
			wp_redirect( $callback_url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- server-constructed home_url() target.
			exit;
		}

		$login_url = wp_login_url( $callback_url );

		McpLogger::log(
			'AUTHORIZE',
			'redirecting to login',
			[
				'state'        => $state,
				'callback_url' => $callback_url,
			]
		);

		wp_redirect( $login_url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Determine whether a provided redirect_uri matches a registered one.
	 *
	 * Non-loopback clients require an exact match.  Loopback clients (native
	 * apps per RFC 8252) are matched port-agnostically: the ephemeral port
	 * varies per session, so scheme, host, and path must match but the port is
	 * ignored.  The exemption is constrained to the literal loopback hosts over
	 * http so it cannot widen open-redirect exposure for normal clients.
	 *
	 * @param string   $provided   The redirect_uri supplied in the request.
	 * @param string[] $registered The redirect URIs from the client metadata.
	 * @return bool
	 */
	private function redirect_uri_matches( string $provided, array $registered ): bool {
		if ( in_array( $provided, $registered, true ) ) {
			return true;
		}

		$provided_parts = wp_parse_url( $provided );
		if ( ! is_array( $provided_parts ) || ! $this->is_loopback( $provided_parts ) ) {
			return false;
		}

		foreach ( $registered as $candidate ) {
			$candidate_parts = wp_parse_url( (string) $candidate );
			if ( ! is_array( $candidate_parts ) || ! $this->is_loopback( $candidate_parts ) ) {
				continue;
			}

			if (
				( $provided_parts['scheme'] ?? '' ) === ( $candidate_parts['scheme'] ?? '' )
				&& ( $provided_parts['host'] ?? '' ) === ( $candidate_parts['host'] ?? '' )
				&& ( $provided_parts['path'] ?? '' ) === ( $candidate_parts['path'] ?? '' )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a parsed URL points at a loopback address over http.
	 *
	 * Covers IPv4 (127.0.0.1), hostname (localhost), and IPv6 (::1) loopback
	 * addresses per RFC 8252 §8.3.  Only plain HTTP is permitted — HTTPS
	 * loopback is not exempted to avoid widening open-redirect exposure for
	 * normal (non-native-app) clients.  wp_parse_url() strips the brackets
	 * from IPv6 literals, so the host value is '::1', not '[::1]'.
	 *
	 * @param array<string, mixed> $parts Parsed URL components from wp_parse_url().
	 * @return bool
	 */
	private function is_loopback( array $parts ): bool {
		$scheme = (string) ( $parts['scheme'] ?? '' );
		$host   = (string) ( $parts['host'] ?? '' );

		return 'http' === $scheme && in_array( $host, [ '127.0.0.1', 'localhost', '::1' ], true );
	}

	/**
	 * Redirect the client to redirect_uri with an error parameter.
	 *
	 * Only a client verified against the trusted-publisher allowlist is redirected
	 * to. An unverified client's redirect_uri comes from a CIMD document anyone can
	 * publish, and these paths run before is_user_logged_in() and before any
	 * consent, so redirecting there would turn the public authorize endpoint into
	 * an unauthenticated open redirector. Unverified clients therefore fall through
	 * to wp_die(). The post-consent success redirect is unaffected: the user
	 * explicitly clicks Allow first.
	 *
	 * @param string $redirect_uri    Destination URI (may be empty on early failure).
	 * @param string $error           OAuth error code.
	 * @param string $state           State token echoed back to the client.
	 * @param bool   $client_verified Whether the client is a verified trusted publisher.
	 *                                No default on purpose: PHP then forces every call
	 *                                site to state it, so none can be silently missed.
	 * @return void
	 */
	private function send_error( string $redirect_uri, string $error, string $state, bool $client_verified ): void {
		if ( $client_verified && '' !== $redirect_uri ) {
			$params = [
				'error' => $error,
				'iss'   => home_url(),
			];
			if ( '' !== $state ) {
				$params['state'] = $state;
			}
			wp_redirect( add_query_arg( $params, $redirect_uri ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- redirecting to a redirect_uri registered in this client's CIMD document AND matched by redirect_uri_matches(); reached only for clients verified against the trusted-publisher allowlist, so an unverified client can never turn this into an open redirect (it takes the wp_die() path below). Not a same-site redirect, so wp_safe_redirect() is not applicable.
			exit;
		}

		// Unverified client (or no validated redirect_uri): never emit a pre-consent
		// redirect to a URI we only learned from an unverified, attacker-publishable
		// CIMD document. Falls through to the pre-existing wp_die() branch. The
		// message is a fixed OAuth error-code literal, not request-controlled data.
		wp_die( esc_html( $error ), esc_html__( 'OAuth Error', 'mcp-oauth' ), [ 'response' => 400 ] );
	}
}
