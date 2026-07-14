<?php
/**
 * Data provider for JWT::encode.
 *
 * Payloads mirror the real token claim sets this library mints in
 * TokenEndpoint::issue_token_pair() — an access token and a refresh token — and
 * the secret is a 256-bit value in the same hex form SecretManager::generate()
 * produces. This keeps the encoder exercised with the exact data shapes it sees
 * in production rather than abstract placeholders.
 */

// 256-bit signing secret, as SecretManager stores it (bin2hex of 32 random bytes).
$wpmedia_mcp_oauth_test_secret = '9c1f4b7e2a8d0356e9b4c7f1a2d8e60355bf9c1e4a7d203f6b8c9e100a2d4b7c';

$wpmedia_mcp_oauth_test_now         = time();
$wpmedia_mcp_oauth_test_access_exp  = $wpmedia_mcp_oauth_test_now + 3600;            // Access token: 1 hour.
$wpmedia_mcp_oauth_test_refresh_exp = $wpmedia_mcp_oauth_test_now + ( 30 * 86400 );  // Refresh token: 30 days.

// An access-token claim set exactly as TokenEndpoint::issue_token_pair() builds it.
$wpmedia_mcp_oauth_test_access_payload = [
	'iss'         => 'https://example.org',
	'aud'         => 'https://example.org/wp-json/mcp/mcp-oauth-server',
	'sub'         => '42',
	'app_pass_id' => '3b9c1f4a-7e2a-4d03-8e60-355bf9c1e4a7',
	'client_id'   => 'https://claude.ai/oauth/claude-code-client-metadata',
	'scope'       => 'mcp',
	'iat'         => $wpmedia_mcp_oauth_test_now,
	'exp'         => $wpmedia_mcp_oauth_test_access_exp,
];

// A refresh-token claim set (adds type + jti, no aud), as the same method builds it.
$wpmedia_mcp_oauth_test_refresh_payload = [
	'iss'         => 'https://example.org',
	'sub'         => '42',
	'app_pass_id' => '3b9c1f4a-7e2a-4d03-8e60-355bf9c1e4a7',
	'client_id'   => 'https://claude.ai/oauth/claude-code-client-metadata',
	'type'        => 'refresh',
	'jti'         => '6b8c9e1f0a2d4b7c9c1f4b7e2a8d0356',
	'iat'         => $wpmedia_mcp_oauth_test_now,
	'exp'         => $wpmedia_mcp_oauth_test_refresh_exp,
];

// A second access token issued to the other trusted Claude client_id / another user.
$wpmedia_mcp_oauth_test_access_payload_alt = [
	'iss'         => 'https://example.org',
	'aud'         => 'https://example.org/wp-json/mcp/mcp-oauth-server',
	'sub'         => '128',
	'app_pass_id' => 'a17f0c92-4be3-4d51-9f28-6c0e5b3a71d4',
	'client_id'   => 'https://claude.ai/oauth/mcp-oauth-client-metadata',
	'scope'       => 'mcp',
	'iat'         => $wpmedia_mcp_oauth_test_now,
	'exp'         => $wpmedia_mcp_oauth_test_access_exp,
];

return [
	'testShouldEncodeAccessTokenClaims'             => [
		'config'   => [
			'payload' => $wpmedia_mcp_oauth_test_access_payload,
			'secret'  => $wpmedia_mcp_oauth_test_secret,
		],
		'expected' => [
			'payload' => $wpmedia_mcp_oauth_test_access_payload,
		],
	],
	'testShouldEncodeRefreshTokenClaims'            => [
		'config'   => [
			'payload' => $wpmedia_mcp_oauth_test_refresh_payload,
			'secret'  => $wpmedia_mcp_oauth_test_secret,
		],
		'expected' => [
			'payload' => $wpmedia_mcp_oauth_test_refresh_payload,
		],
	],
	'testShouldEncodeAccessTokenForAlternateClient' => [
		'config'   => [
			'payload' => $wpmedia_mcp_oauth_test_access_payload_alt,
			'secret'  => $wpmedia_mcp_oauth_test_secret,
		],
		'expected' => [
			'payload' => $wpmedia_mcp_oauth_test_access_payload_alt,
		],
	],
];
