<?php
/**
 * Data provider for JWT::decode.
 *
 * Tokens are built from the real MCP token claim sets this library issues
 * (TokenEndpoint::issue_token_pair) and verified/decoded exactly as
 * OAuthHttpTransport (access, verify expiry), TokenEndpoint (refresh, verify
 * expiry) and RevokeEndpoint (ignore expiry) do. Secrets are 256-bit hex values
 * matching SecretManager::generate(). The scenarios cover the security
 * behaviours those callers rely on: signature verification, algorithm pinning,
 * structural validation, and expiry handling.
 */

// Two distinct 256-bit signing secrets, as SecretManager stores them.
$wpmedia_mcp_oauth_test_secret       = '9c1f4b7e2a8d0356e9b4c7f1a2d8e60355bf9c1e4a7d203f6b8c9e100a2d4b7c';
$wpmedia_mcp_oauth_test_other_secret = '0a2d4b7c6b8c9e104a7d203f55bf9c1ea2d8e603e9b4c7f12a8d03569c1f4b7e';

$wpmedia_mcp_oauth_test_now    = time();
$wpmedia_mcp_oauth_test_future = $wpmedia_mcp_oauth_test_now + 3600;  // Valid access-token window.
$wpmedia_mcp_oauth_test_past   = $wpmedia_mcp_oauth_test_now - 3600;  // Already expired.

// A valid access-token claim set, as minted for an authenticated MCP session.
$wpmedia_mcp_oauth_test_access = [
	'iss'         => 'https://example.org',
	'aud'         => 'https://example.org/wp-json/mcp/mcp-oauth-server',
	'sub'         => '42',
	'app_pass_id' => '3b9c1f4a-7e2a-4d03-8e60-355bf9c1e4a7',
	'client_id'   => 'https://claude.ai/oauth/claude-code-client-metadata',
	'scope'       => 'mcp',
	'iat'         => $wpmedia_mcp_oauth_test_now,
	'exp'         => $wpmedia_mcp_oauth_test_future,
];

// The same session's access token, but already past its exp.
$wpmedia_mcp_oauth_test_access_expired        = $wpmedia_mcp_oauth_test_access;
$wpmedia_mcp_oauth_test_access_expired['exp'] = $wpmedia_mcp_oauth_test_past;

// A refresh token minted with no exp claim (decode must skip the expiry check).
$wpmedia_mcp_oauth_test_refresh_no_exp = [
	'iss'         => 'https://example.org',
	'sub'         => '42',
	'app_pass_id' => '3b9c1f4a-7e2a-4d03-8e60-355bf9c1e4a7',
	'client_id'   => 'https://claude.ai/oauth/claude-code-client-metadata',
	'type'        => 'refresh',
	'jti'         => '6b8c9e1f0a2d4b7c9c1f4b7e2a8d0356',
];

return [
	'testShouldReturnPayloadForValidAccessToken'         => [
		'config'   => [
			'payload'       => $wpmedia_mcp_oauth_test_access,
			'encode_secret' => $wpmedia_mcp_oauth_test_secret,
			'decode_secret' => $wpmedia_mcp_oauth_test_secret,
			'verify_expiry' => true,
			'tamper'        => null,
		],
		'expected' => [
			'result' => $wpmedia_mcp_oauth_test_access,
		],
	],
	'testShouldReturnPayloadWhenNoExpiryClaimIsPresent'  => [
		'config'   => [
			'payload'       => $wpmedia_mcp_oauth_test_refresh_no_exp,
			'encode_secret' => $wpmedia_mcp_oauth_test_secret,
			'decode_secret' => $wpmedia_mcp_oauth_test_secret,
			'verify_expiry' => true,
			'tamper'        => null,
		],
		'expected' => [
			'result' => $wpmedia_mcp_oauth_test_refresh_no_exp,
		],
	],
	'testShouldReturnNullWhenSignedWithADifferentSecret' => [
		'config'   => [
			'payload'       => $wpmedia_mcp_oauth_test_access,
			'encode_secret' => $wpmedia_mcp_oauth_test_secret,
			'decode_secret' => $wpmedia_mcp_oauth_test_other_secret,
			'verify_expiry' => true,
			'tamper'        => null,
		],
		'expected' => [
			'result' => null,
		],
	],
	'testShouldReturnNullForATamperedSignature'          => [
		'config'   => [
			'payload'       => $wpmedia_mcp_oauth_test_access,
			'encode_secret' => $wpmedia_mcp_oauth_test_secret,
			'decode_secret' => $wpmedia_mcp_oauth_test_secret,
			'verify_expiry' => true,
			'tamper'        => 'signature',
		],
		'expected' => [
			'result' => null,
		],
	],
	'testShouldReturnNullForNoneAlgorithm'               => [
		'config'   => [
			'payload'       => $wpmedia_mcp_oauth_test_access,
			'encode_secret' => $wpmedia_mcp_oauth_test_secret,
			'decode_secret' => $wpmedia_mcp_oauth_test_secret,
			'verify_expiry' => true,
			'tamper'        => 'alg_none',
		],
		'expected' => [
			'result' => null,
		],
	],
	'testShouldReturnNullForANonHS256Algorithm'          => [
		'config'   => [
			'payload'       => $wpmedia_mcp_oauth_test_access,
			'encode_secret' => $wpmedia_mcp_oauth_test_secret,
			'decode_secret' => $wpmedia_mcp_oauth_test_secret,
			'verify_expiry' => true,
			'tamper'        => 'alg_rs256',
		],
		'expected' => [
			'result' => null,
		],
	],
	'testShouldReturnNullForATokenWithTooFewSegments'    => [
		'config'   => [
			'payload'       => $wpmedia_mcp_oauth_test_access,
			'encode_secret' => $wpmedia_mcp_oauth_test_secret,
			'decode_secret' => $wpmedia_mcp_oauth_test_secret,
			'verify_expiry' => true,
			'tamper'        => 'too_few_parts',
		],
		'expected' => [
			'result' => null,
		],
	],
	'testShouldReturnNullForATokenWithTooManySegments'   => [
		'config'   => [
			'payload'       => $wpmedia_mcp_oauth_test_access,
			'encode_secret' => $wpmedia_mcp_oauth_test_secret,
			'decode_secret' => $wpmedia_mcp_oauth_test_secret,
			'verify_expiry' => true,
			'tamper'        => 'too_many_parts',
		],
		'expected' => [
			'result' => null,
		],
	],
	'testShouldReturnNullForAnExpiredAccessTokenWhenVerifyingExpiry' => [
		'config'   => [
			'payload'       => $wpmedia_mcp_oauth_test_access_expired,
			'encode_secret' => $wpmedia_mcp_oauth_test_secret,
			'decode_secret' => $wpmedia_mcp_oauth_test_secret,
			'verify_expiry' => true,
			'tamper'        => null,
		],
		'expected' => [
			'result' => null,
		],
	],
	'testShouldReturnPayloadForAnExpiredTokenWhenNotVerifyingExpiry' => [
		'config'   => [
			'payload'       => $wpmedia_mcp_oauth_test_access_expired,
			'encode_secret' => $wpmedia_mcp_oauth_test_secret,
			'decode_secret' => $wpmedia_mcp_oauth_test_secret,
			'verify_expiry' => false,
			'tamper'        => null,
		],
		'expected' => [
			'result' => $wpmedia_mcp_oauth_test_access_expired,
		],
	],
];
