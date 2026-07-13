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

return [
	'testShouldReturnNullForEmptyClientId'             => [
		'config'   => [
			'client_id' => '',
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => false,
			'cache_checked'           => false,
			'fetch'                   => false,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullForNonHttpsUrl'               => [
		'config'   => [
			'client_id' => 'http://example.com/cimd.json',
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => false,
			'cache_checked'           => false,
			'fetch'                   => false,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullForUrlMissingPath'            => [
		'config'   => [
			'client_id' => 'https://example.com',
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => false,
			'cache_checked'           => false,
			'fetch'                   => false,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullForUrlWithRootPathOnly'       => [
		'config'   => [
			'client_id' => 'https://example.com/',
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => false,
			'cache_checked'           => false,
			'fetch'                   => false,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullForUrlWithFragment'           => [
		'config'   => [
			'client_id' => 'https://example.com/cimd.json#section',
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => false,
			'cache_checked'           => false,
			'fetch'                   => false,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullForUrlWithUserinfo'           => [
		'config'   => [
			'client_id' => 'https://user:pass@example.com/cimd.json',
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => false,
			'cache_checked'           => false,
			'fetch'                   => false,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullWhenHostNotTrusted'           => [
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => false,
		],
		'expected' => [
			'result'                  => null,
			'is_trusted_host_checked' => true,
			'cache_checked'           => false,
			'fetch'                   => false,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnCachedRecordWithoutFetching'      => [
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => true,
			'cached'          => $wpmedia_mcp_oauth_test_happy_record,
		],
		'expected' => [
			'result'                  => $wpmedia_mcp_oauth_test_happy_record,
			'is_trusted_host_checked' => true,
			'cache_checked'           => true,
			'fetch'                   => false,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullWhenFetchReturnsWpError'      => [
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
			'fetch'                   => true,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullForNon200Status'              => [
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
			'fetch'                   => true,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullWhenBodyExceedsMaxBytes'      => [
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
			'fetch'                   => true,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullForNonJsonBody'               => [
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
			'fetch'                   => true,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullForEmptyJsonBody'             => [
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
			'fetch'                   => true,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullWhenDocumentClientIdMismatch' => [
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
			'fetch'                   => true,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullWhenAuthMethodNotNone'        => [
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
			'fetch'                   => true,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullWhenRedirectUrisMissing'      => [
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
			'fetch'                   => true,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNullWhenRedirectUrisEmpty'        => [
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
			'fetch'                   => true,
			'verify_called'           => false,
			'cache_set'               => false,
		],
	],
	'testShouldReturnNormalizedRecordOnHappyPath'      => [
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
			'fetch'                   => true,
			'verify_called'           => true,
			'cache_set'               => true,
			'ttl'                     => 7200,
		],
	],
	'testShouldResolveAndWarnOnUnexpectedContentType'  => [
		'config'   => [
			'client_id'       => $wpmedia_mcp_oauth_test_url,
			'is_trusted_host' => true,
			'cached'          => null,
			'status'          => 200,
			'body'            => json_encode( $wpmedia_mcp_oauth_test_happy_doc ), // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- fixture data loads before WP is bootstrapped; wp_json_encode() is unavailable at this point.
			'content_type'    => 'text/html',
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
			'fetch'                   => true,
			'verify_called'           => true,
			'cache_set'               => true,
			'ttl'                     => 7200,
		],
	],
];
