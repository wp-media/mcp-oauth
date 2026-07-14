<?php
/**
 * Scenarios for Tests\Integration\Auth\AuthorizeEndpoint\HandleRequestTest::testHandleRequest
 *
 * `config.get` holds overrides applied on top of a valid request (see
 * HandleRequestTest::valid_get()); `config.trusted_host`/`trusted_client_ids`
 * control the `wpmedia_mcp_oauth_trusted_publishers` allowlist, and
 * `config.cimd_document` (when set) stubs the fetched CIMD document for
 * `client_id`, both against the real CimdResolver/ClaudeClientVerifier.
 */

return [
	'testShouldDieWhenClientIdIsMissing'                  => [
		'config'   => [
			'get' => [ 'client_id' => null ],
		],
		'expected' => [
			'type'             => 'die',
			'message_contains' => 'client_id is required.',
			'response_code'    => 400,
		],
	],
	'testShouldDieWhenClientCannotBeResolved'             => [
		'config'   => [
			// No trusted-publisher registered for this host, so CimdResolver
			// rejects the client_id before any fetch is attempted.
			'get' => [],
		],
		'expected' => [
			'type'             => 'die',
			'message_contains' => 'Unknown OAuth client.',
			'response_code'    => 400,
		],
	],
	'testShouldDieWhenClientIsNotVerified'                => [
		'config'   => [
			'get'                => [],
			'trusted_host'       => 'good-client.example',
			// Host is trusted (so the fetch proceeds) but the exact client_id
			// isn't in the allowlist, so ClaudeClientVerifier::verify() fails.
			'trusted_client_ids' => [],
			'cimd_document'      => [],
		],
		'expected' => [
			'type'             => 'die',
			'message_contains' => 'not a verified publisher',
			'response_code'    => 400,
		],
	],
	'testShouldDieWhenRedirectUriIsMissing'               => [
		'config'   => [
			'get'                => [ 'redirect_uri' => null ],
			'trusted_host'       => 'good-client.example',
			'trusted_client_ids' => [ 'https://good-client.example/app' ],
			'cimd_document'      => [],
		],
		'expected' => [
			'type'             => 'die',
			'message_contains' => 'redirect_uri is required.',
			'response_code'    => 400,
		],
	],
	'testShouldDieWhenRedirectUriDoesNotMatchRegistered'  => [
		'config'   => [
			'get'                => [ 'redirect_uri' => 'https://evil.example/callback' ],
			'trusted_host'       => 'good-client.example',
			'trusted_client_ids' => [ 'https://good-client.example/app' ],
			'cimd_document'      => [],
		],
		'expected' => [
			'type'             => 'die',
			'message_contains' => 'does not match registered value.',
			'response_code'    => 400,
		],
	],
	'testShouldRedirectWithUnsupportedResponseTypeError'  => [
		'config'   => [
			'get'                => [ 'response_type' => 'token' ],
			'trusted_host'       => 'good-client.example',
			'trusted_client_ids' => [ 'https://good-client.example/app' ],
			'cimd_document'      => [],
		],
		'expected' => [
			'type'  => 'redirect_error',
			'error' => 'unsupported_response_type',
			'state' => 'state-value',
		],
	],
	'testShouldRedirectWithInvalidRequestErrorWhenCodeChallengeIsMissing' => [
		'config'   => [
			'get'                => [ 'code_challenge' => null ],
			'trusted_host'       => 'good-client.example',
			'trusted_client_ids' => [ 'https://good-client.example/app' ],
			'cimd_document'      => [],
		],
		'expected' => [
			'type'  => 'redirect_error',
			'error' => 'invalid_request',
			'state' => 'state-value',
		],
	],
	'testShouldRedirectWithInvalidRequestErrorWhenCodeChallengeMethodIsNotS256' => [
		'config'   => [
			'get'                => [ 'code_challenge_method' => 'plain' ],
			'trusted_host'       => 'good-client.example',
			'trusted_client_ids' => [ 'https://good-client.example/app' ],
			'cimd_document'      => [],
		],
		'expected' => [
			'type'  => 'redirect_error',
			'error' => 'invalid_request',
			'state' => 'state-value',
		],
	],
	'testShouldRedirectWithInvalidRequestErrorAndNoStateWhenStateIsMissing' => [
		'config'   => [
			'get'                => [ 'state' => null ],
			'trusted_host'       => 'good-client.example',
			'trusted_client_ids' => [ 'https://good-client.example/app' ],
			'cimd_document'      => [],
		],
		'expected' => [
			'type'  => 'redirect_error',
			'error' => 'invalid_request',
			// No 'state' key: the client never provided one to echo back.
		],
	],
	'testShouldPersistStateAndRedirectToLoginOnValidRequest' => [
		'config'   => [
			'get'                => [],
			'trusted_host'       => 'good-client.example',
			'trusted_client_ids' => [ 'https://good-client.example/app' ],
			'cimd_document'      => [],
		],
		'expected' => [
			'type'      => 'login',
			'transient' => [
				'client_id'             => 'https://good-client.example/app',
				'client_name'           => 'Example App',
				'client_uri'            => 'https://client.example',
				'verified'              => true,
				'publisher'             => 'test',
				'redirect_uri'          => 'https://client.example/callback',
				'code_challenge'        => 'challenge-value',
				'code_challenge_method' => 'S256',
				'state'                 => 'state-value',
			],
		],
	],
	'testShouldAcceptLoopbackRedirectUriRegardlessOfPort' => [
		'config'   => [
			'get'                => [ 'redirect_uri' => 'http://127.0.0.1:51204/cb' ],
			'trusted_host'       => 'good-client.example',
			'trusted_client_ids' => [ 'https://good-client.example/app' ],
			'cimd_document'      => [ 'redirect_uris' => [ 'http://127.0.0.1:9999/cb' ] ],
		],
		'expected' => [
			'type'      => 'login',
			'transient' => [
				'client_id'             => 'https://good-client.example/app',
				'client_name'           => 'Example App',
				'client_uri'            => 'https://client.example',
				'verified'              => true,
				'publisher'             => 'test',
				'redirect_uri'          => 'http://127.0.0.1:51204/cb',
				'code_challenge'        => 'challenge-value',
				'code_challenge_method' => 'S256',
				'state'                 => 'state-value',
			],
		],
	],
];
