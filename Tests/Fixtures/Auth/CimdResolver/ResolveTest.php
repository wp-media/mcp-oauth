<?php

$wpmedia_mcp_oauth_test_url = 'https://example.com/cimd.json';

$wpmedia_mcp_oauth_test_happy_doc = [
	'client_id'                  => $wpmedia_mcp_oauth_test_url,
	'client_name'                => 'Example Client',
	'client_uri'                 => 'https://example.com',
	'redirect_uris'              => [ 'https://client.example/callback' ],
	'grant_types'                => [ 'authorization_code', 'refresh_token' ],
	'token_endpoint_auth_method' => 'none',
];

$wpmedia_mcp_oauth_test_happy_record = [
	'client_id'                  => $wpmedia_mcp_oauth_test_url,
	'client_name'                => 'Example Client',
	'client_uri'                 => 'https://example.com',
	'redirect_uris'              => [ 'https://client.example/callback' ],
	'grant_types'                => [ 'authorization_code', 'refresh_token' ],
	'token_endpoint_auth_method' => 'none',
	'source'                     => 'cimd',
	'verified'                   => true,
	'publisher'                  => 'claude',
];

$wpmedia_mcp_oauth_test_unverified_record = array_merge(
	$wpmedia_mcp_oauth_test_happy_record,
	[
		'verified'  => false,
		'publisher' => '',
	]
);

return [
	'testShouldReturnNullForEmptyClientId'                => [
		'config'   => [
			'client_id' => '',
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => false,
			'cache_checked'           => false,
			'budget_checked'          => false,
			'budget_consumed'         => false,
			'preflight'               => false,
			'fetch'                   => false,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullForNonHttpsUrl'                  => [
		'config'   => [
			'client_id' => 'http://example.com/cimd.json',
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => false,
			'cache_checked'           => false,
			'budget_checked'          => false,
			'budget_consumed'         => false,
			'preflight'               => false,
			'fetch'                   => false,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullForUrlMissingPath'               => [
		'config'   => [
			'client_id' => 'https://example.com',
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => false,
			'cache_checked'           => false,
			'budget_checked'          => false,
			'budget_consumed'         => false,
			'preflight'               => false,
			'fetch'                   => false,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullForUrlWithRootPathOnly'          => [
		'config'   => [
			'client_id' => 'https://example.com/',
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => false,
			'cache_checked'           => false,
			'budget_checked'          => false,
			'budget_consumed'         => false,
			'preflight'               => false,
			'fetch'                   => false,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullForUrlWithFragment'              => [
		'config'   => [
			'client_id' => 'https://example.com/cimd.json#section',
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => false,
			'cache_checked'           => false,
			'budget_checked'          => false,
			'budget_consumed'         => false,
			'preflight'               => false,
			'fetch'                   => false,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullForUrlWithUserinfo'              => [
		'config'   => [
			'client_id' => 'https://user:pass@example.com/cimd.json',
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => false,
			'cache_checked'           => false,
			'budget_checked'          => false,
			'budget_consumed'         => false,
			'preflight'               => false,
			'fetch'                   => false,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullForUrlWithExplicitPort'          => [
		'config'   => [
			'client_id' => 'https://example.com:8080/cimd.json',
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => false,
			'cache_checked'           => false,
			'budget_checked'          => false,
			'budget_consumed'         => false,
			'preflight'               => false,
			'fetch'                   => false,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullForUrlWithExplicitDefaultPort'   => [
		'config'   => [
			'client_id' => 'https://example.com:443/cimd.json',
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => false,
			'cache_checked'           => false,
			'budget_checked'          => false,
			'budget_consumed'         => false,
			'preflight'               => false,
			'fetch'                   => false,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullWhenHostNotTrusted'              => [
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => false,
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => true,
			'cache_checked'           => false,
			'budget_checked'          => false,
			'budget_consumed'         => false,
			'preflight'               => false,
			'fetch'                   => false,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullWhenHostNotTrustedEvenWithCachedRecord' => [
		// Host-gate-before-cache regression (round-2 MUST_HAVE): an untrusted
		// host must resolve to null even when a record is already cached, so
		// get_transient() must NEVER be consulted (cache_checked === false).
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => false,
			'cached'          => $wpmedia_mcp_oauth_test_happy_record,
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => true,
			'cache_checked'           => false,
			'budget_checked'          => false,
			'budget_consumed'         => false,
			'preflight'               => false,
			'fetch'                   => false,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnCachedRecordWithoutFetching'         => [
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => true,
			'cached'          => $wpmedia_mcp_oauth_test_happy_record,
		],
		'expected' => [
			'result'                  => $wpmedia_mcp_oauth_test_happy_record,
			'is_trusted_host_checked' => true,
			'cache_checked'           => true,
			'budget_checked'          => false,
			'budget_consumed'         => false,
			'preflight'               => false,
			'fetch'                   => false,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullWhenPreflightConnectFails'       => [
		// connect_and_get_ip() returns null (connect failure/timeout): reject
		// before the real fetch, so wp_safe_remote_get() is never called.
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => true,
			'cached'          => null,
			'connect_ip'      => null,
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => true,
			'cache_checked'           => true,
			'budget_checked'          => true,
			'budget_consumed'         => true,
			'budget_value'            => 1,
			'preflight'               => true,
			'fetch'                   => false,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullWhenPreflightIpDisallowed'       => [
		// connect_and_get_ip() connects to a private IP: is_ip_allowed() rejects
		// it before the real fetch.
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => true,
			'cached'          => null,
			'connect_ip'      => '10.0.0.5',
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => true,
			'cache_checked'           => true,
			'budget_checked'          => true,
			'budget_consumed'         => true,
			'budget_value'            => 1,
			'preflight'               => true,
			'fetch'                   => false,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullWhenFetchReturnsWpError'         => [
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => true,
			'cached'          => null,
			'is_wp_error'     => true,
			'error_message'   => 'Connection timed out.',
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => true,
			'cache_checked'           => true,
			'budget_checked'          => true,
			'budget_consumed'         => true,
			'budget_value'            => 1,
			'preflight'               => true,
			'fetch'                   => true,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullForNon200Status'                 => [
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => true,
			'cached'          => null,
			'status'          => 404,
			'body'            => '',
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => true,
			'cache_checked'           => true,
			'budget_checked'          => true,
			'budget_consumed'         => true,
			'budget_value'            => 1,
			'preflight'               => true,
			'fetch'                   => true,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullWhenBodyExceedsMaxBytes'         => [
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => true,
			'cached'          => null,
			'status'          => 200,
			'body'            => str_repeat( 'a', 5121 ),
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => true,
			'cache_checked'           => true,
			'budget_checked'          => true,
			'budget_consumed'         => true,
			'budget_value'            => 1,
			'preflight'               => true,
			'fetch'                   => true,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullForNonJsonBody'                  => [
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => true,
			'cached'          => null,
			'status'          => 200,
			'body'            => 'not-json-at-all',
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => true,
			'cache_checked'           => true,
			'budget_checked'          => true,
			'budget_consumed'         => true,
			'budget_value'            => 1,
			'preflight'               => true,
			'fetch'                   => true,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullForEmptyJsonBody'                => [
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => true,
			'cached'          => null,
			'status'          => 200,
			'body'            => '{}',
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => true,
			'cache_checked'           => true,
			'budget_checked'          => true,
			'budget_consumed'         => true,
			'budget_value'            => 1,
			'preflight'               => true,
			'fetch'                   => true,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullWhenDocumentClientIdMismatch'    => [
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => true,
			'cached'          => null,
			'status'          => 200,
			'body'            => json_encode( // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- fixture data loads before WP is bootstrapped; wp_json_encode() is unavailable at this point.
				[
					'client_id'     => 'https://other.example/cimd.json',
					'redirect_uris' => [ 'https://client.example/callback' ],
				]
			),
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => true,
			'cache_checked'           => true,
			'budget_checked'          => true,
			'budget_consumed'         => true,
			'budget_value'            => 1,
			'preflight'               => true,
			'fetch'                   => true,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullWhenAuthMethodNotNone'           => [
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => true,
			'cached'          => null,
			'status'          => 200,
			'body'            => json_encode( // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- fixture data loads before WP is bootstrapped; wp_json_encode() is unavailable at this point.
				[
					'client_id'                  => $wpmedia_mcp_oauth_test_url,
					'token_endpoint_auth_method' => 'client_secret_basic',
					'redirect_uris'              => [ 'https://client.example/callback' ],
				]
			),
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => true,
			'cache_checked'           => true,
			'budget_checked'          => true,
			'budget_consumed'         => true,
			'budget_value'            => 1,
			'preflight'               => true,
			'fetch'                   => true,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullWhenRedirectUrisMissing'         => [
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => true,
			'cached'          => null,
			'status'          => 200,
			'body'            => json_encode( // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- fixture data loads before WP is bootstrapped; wp_json_encode() is unavailable at this point.
				[
					'client_id' => $wpmedia_mcp_oauth_test_url,
				]
			),
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => true,
			'cache_checked'           => true,
			'budget_checked'          => true,
			'budget_consumed'         => true,
			'budget_value'            => 1,
			'preflight'               => true,
			'fetch'                   => true,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullWhenRedirectUrisEmpty'           => [
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => true,
			'cached'          => null,
			'status'          => 200,
			'body'            => json_encode( // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- fixture data loads before WP is bootstrapped; wp_json_encode() is unavailable at this point.
				[
					'client_id'     => $wpmedia_mcp_oauth_test_url,
					'redirect_uris' => [],
				]
			),
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => true,
			'cache_checked'           => true,
			'budget_checked'          => true,
			'budget_consumed'         => true,
			'budget_value'            => 1,
			'preflight'               => true,
			'fetch'                   => true,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullWhenGrantTypesMissingAuthorizationCode' => [
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => true,
			'cached'          => null,
			'status'          => 200,
			'body'            => json_encode( // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- fixture data loads before WP is bootstrapped; wp_json_encode() is unavailable at this point.
				[
					'client_id'     => $wpmedia_mcp_oauth_test_url,
					'redirect_uris' => [ 'https://client.example/callback' ],
					'grant_types'   => [ 'refresh_token' ],
				]
			),
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => true,
			'cache_checked'           => true,
			'budget_checked'          => true,
			'budget_consumed'         => true,
			'budget_value'            => 1,
			'preflight'               => true,
			'fetch'                   => true,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNormalizedRecordOnHappyPath'         => [
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => true,
			'cached'          => null,
			'status'          => 200,
			'body'            => json_encode( $wpmedia_mcp_oauth_test_happy_doc ), // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- fixture data loads before WP is bootstrapped; wp_json_encode() is unavailable at this point.
			'content_type'    => 'application/json',
			'cache_control'   => 'max-age=7200',
			'verify_result'   => [
				'verified'  => true,
				'publisher' => 'claude',
			],
		],
		'expected' => [
			'result'                  => $wpmedia_mcp_oauth_test_happy_record,
			'is_trusted_host_checked' => true,
			'cache_checked'           => true,
			'budget_checked'          => true,
			'budget_consumed'         => true,
			'budget_value'            => 1,
			'preflight'               => true,
			'fetch'                   => true,
			'verify_called'           => true,
			'cache_set'               => true,
			'ttl'                     => 7200,
		],
	],
	'testShouldReturnNullOnUnexpectedContentType'         => [
		// Present-but-non-JSON content-type is now rejected (was a warning).
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => true,
			'cached'          => null,
			'status'          => 200,
			'body'            => json_encode( $wpmedia_mcp_oauth_test_happy_doc ), // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- fixture data loads before WP is bootstrapped; wp_json_encode() is unavailable at this point.
			'content_type'    => 'text/html',
			'cache_control'   => 'max-age=7200',
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => true,
			'cache_checked'           => true,
			'budget_checked'          => true,
			'budget_consumed'         => true,
			'budget_value'            => 1,
			'preflight'               => true,
			'fetch'                   => true,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldResolveWhenContentTypeAbsent'              => [
		// Absent/empty content-type is tolerated; body is still validated.
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => true,
			'cached'          => null,
			'status'          => 200,
			'body'            => json_encode( $wpmedia_mcp_oauth_test_happy_doc ), // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- fixture data loads before WP is bootstrapped; wp_json_encode() is unavailable at this point.
			'content_type'    => '',
			'cache_control'   => 'max-age=7200',
			'verify_result'   => [
				'verified'  => true,
				'publisher' => 'claude',
			],
		],
		'expected' => [
			'result'                  => $wpmedia_mcp_oauth_test_happy_record,
			'is_trusted_host_checked' => true,
			'cache_checked'           => true,
			'budget_checked'          => true,
			'budget_consumed'         => true,
			'budget_value'            => 1,
			'preflight'               => true,
			'fetch'                   => true,
			'verify_called'           => true,
			'cache_set'               => true,
			'ttl'                     => 7200,
		],
	],
	'testShouldReturnNullWhenFetchBudgetIsExhausted'      => [
		// AC1: the 31st cache-miss fetch within the window is rejected before
		// any preflight, fetch, or cache write — proving no network call.
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => true,
			'cached'          => null,
			'fetch_count'     => 30,
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => true,
			'cache_checked'           => true,
			'budget_checked'          => true,
			'budget_consumed'         => false,
			'preflight'               => false,
			'fetch'                   => false,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldFetchWhenBudgetHasOneSlotLeft'             => [
		// Boundary: the 30th fetch in a window (count 29 -> 30) is allowed.
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => true,
			'cached'          => null,
			'fetch_count'     => 29,
			'status'          => 200,
			'body'            => json_encode( $wpmedia_mcp_oauth_test_happy_doc ), // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- fixture data loads before WP is bootstrapped; wp_json_encode() is unavailable at this point.
			'content_type'    => 'application/json',
			'cache_control'   => 'max-age=7200',
			'verify_result'   => [
				'verified'  => true,
				'publisher' => 'claude',
			],
		],
		'expected' => [
			'result'                  => $wpmedia_mcp_oauth_test_happy_record,
			'is_trusted_host_checked' => true,
			'cache_checked'           => true,
			'budget_checked'          => true,
			'budget_consumed'         => true,
			'budget_value'            => 30,
			'preflight'               => true,
			'fetch'                   => true,
			'verify_called'           => true,
			'cache_set'               => true,
			'ttl'                     => 7200,
		],
	],
	'testShouldFetchUntrustedHostWhenUntrustedIsAllowed'  => [
		// #36: the host gate is skipped when the endpoint allows untrusted
		// providers, so the document is fetched and returned with verified=false.
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => false,
			'allow_untrusted' => true,
			'cached'          => null,
			'status'          => 200,
			'body'            => json_encode( $wpmedia_mcp_oauth_test_happy_doc ), // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- fixture data loads before WP is bootstrapped; wp_json_encode() is unavailable at this point.
			'content_type'    => 'application/json',
			'cache_control'   => 'max-age=7200',
			'verify_result'   => [
				'verified'  => false,
				'publisher' => '',
			],
		],
		'expected' => [
			'result'                  => $wpmedia_mcp_oauth_test_unverified_record,
			'is_trusted_host_checked' => true,
			'cache_checked'           => true,
			'budget_checked'          => true,
			'budget_consumed'         => true,
			'budget_value'            => 1,
			'preflight'               => true,
			'fetch'                   => true,
			'verify_called'           => true,
			'cache_set'               => true,
			'ttl'                     => 7200,
		],
	],
	'testShouldRejectUntrustedHostWhenUntrustedIsDisallowed' => [
		// Mirror of the case above with the filter restored to false: refused
		// before the cache read, so no fetch and no budget consumption.
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => false,
			'allow_untrusted' => false,
			'cached'          => $wpmedia_mcp_oauth_test_unverified_record,
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => true,
			'cache_checked'           => false,
			'budget_checked'          => false,
			'budget_consumed'         => false,
			'preflight'               => false,
			'fetch'                   => false,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldRejectUntrustedHostWhenCurlIsUnavailable'  => [
		// #36: without cURL there is no bounded preflight and no CURLOPT_RESOLVE
		// pin, and no allowlist bounding the target — so an untrusted host is
		// refused rather than fetched unpinned. Budget is consumed before
		// fetch_document() is entered, matching every other in-fetch rejection.
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => false,
			'allow_untrusted' => true,
			'curl_available'  => false,
			'cached'          => null,
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => true,
			'cache_checked'           => true,
			'budget_checked'          => true,
			'budget_consumed'         => true,
			'budget_value'            => 1,
			'preflight'               => false,
			'fetch'                   => false,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldFetchTrustedHostUnpinnedWhenCurlIsUnavailable' => [
		// The pre-existing unpinned fallback survives for a trusted host: the
		// decision keys off host trust, not the filter value. No preflight runs.
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => true,
			'allow_untrusted' => true,
			'curl_available'  => false,
			'cached'          => null,
			'status'          => 200,
			'body'            => json_encode( $wpmedia_mcp_oauth_test_happy_doc ), // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- fixture data loads before WP is bootstrapped; wp_json_encode() is unavailable at this point.
			'content_type'    => 'application/json',
			'cache_control'   => 'max-age=7200',
			'verify_result'   => [
				'verified'  => true,
				'publisher' => 'claude',
			],
		],
		'expected' => [
			'result'                  => $wpmedia_mcp_oauth_test_happy_record,
			'is_trusted_host_checked' => true,
			'cache_checked'           => true,
			'budget_checked'          => true,
			'budget_consumed'         => true,
			'budget_value'            => 1,
			'preflight'               => false,
			'fetch'                   => true,
			'verify_called'           => true,
			'cache_set'               => true,
			'ttl'                     => 7200,
		],
	],
	'testShouldReturnNullWhenFilteredFetchLimitIsReached' => [
		// AC3: a filtered limit of 1, already at count 1, rejects the fetch.
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => true,
			'cached'          => null,
			'fetch_count'     => 1,
			'fetch_limit'     => 1,
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => true,
			'cache_checked'           => true,
			'budget_checked'          => true,
			'budget_consumed'         => false,
			'preflight'               => false,
			'fetch'                   => false,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
];
