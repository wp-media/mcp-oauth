<?php
/**
 * Scenarios for Tests\Integration\Auth\AuthorizeEndpoint\HandleRequestTest::testHandleRequest
 *
 * `config.get` holds overrides applied on top of a valid request (see
 * HandleRequestTest::valid_get()); `config.trusted_host`/`trusted_client_ids`
 * control the `wpmedia_mcp_oauth_trusted_publishers` allowlist, and
 * `config.cimd_document` (when set) stubs the fetched CIMD document for
 * `client_id`, both against the real CimdResolver/ClaudeClientVerifier.
 *
 * `config.allow_untrusted => false` hooks
 * `wpmedia_mcp_oauth_allow_untrusted_providers` to `__return_false`, restoring
 * the pre-1.x hard gate; omitting the key uses the plugin default (`true`).
 * `config.canonical_allow_untrusted` hooks that filter to an arbitrary
 * (possibly non-boolean) return value, paired with `expected.incorrect_usage`.
 */

$wpmedia_mcp_oauth_test_unverified_transient = [
	'client_id'             => 'https://good-client.example/app',
	'client_name'           => 'Example App',
	'client_uri'            => 'https://client.example',
	'verified'              => false,
	'publisher'             => '',
	'redirect_uri'          => 'https://client.example/callback',
	'code_challenge'        => 'challenge-value',
	'code_challenge_method' => 'S256',
	'state'                 => 'state-value',
];

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
			// Untrusted host + providers disallowed: rejected before any fetch,
			// which is why no cimd_document is needed.
			'get'             => [],
			'allow_untrusted' => false,
		],
		'expected' => [
			'type'             => 'die',
			'message_contains' => 'Unknown OAuth client.',
			'response_code'    => 400,
		],
	],
	'testShouldReachConsentWhenHostIsUntrustedAndUntrustedAllowed' => [
		// Mirror of the above under the default filter: fetched, and reaches
		// consent with verified => false in the state transient.
		'config'   => [
			'get'           => [],
			'cimd_document' => [],
		],
		'expected' => [
			'type'      => 'login',
			'transient' => $wpmedia_mcp_oauth_test_unverified_transient,
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
			// Required for the hard-reject; the default-filter mirror is below.
			'allow_untrusted'    => false,
		],
		'expected' => [
			'type'             => 'die',
			'message_contains' => 'not a verified publisher',
			'response_code'    => 400,
		],
	],
	'testShouldReachConsentWhenClientIsUnverifiedAndUntrustedAllowed' => [
		'config'   => [
			'get'                => [],
			'trusted_host'       => 'good-client.example',
			'trusted_client_ids' => [],
			'cimd_document'      => [],
		],
		'expected' => [
			'type'      => 'login',
			'transient' => $wpmedia_mcp_oauth_test_unverified_transient,
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
	'testShouldRedirectDirectlyToConsentWhenAlreadyLoggedIn' => [
		'config'   => [
			'get'                => [],
			'trusted_host'       => 'good-client.example',
			'trusted_client_ids' => [ 'https://good-client.example/app' ],
			'cimd_document'      => [],
			'logged_in'          => true,
		],
		'expected' => [
			'type'      => 'authenticated_redirect',
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
	'testShouldDieRatherThanRedirectWhenUnverifiedClientSendsUnsupportedResponseType' => [
		// Open-redirect closure: this branch runs before login and consent, so no
		// 302 to an unverified client's redirect_uri may be emitted. The test
		// installs a throwing wp_redirect interceptor so a regression fails loudly.
		'config'   => [
			'get'           => [
				'redirect_uri'  => 'https://phish.example/landing',
				'response_type' => 'token',
			],
			'cimd_document' => [ 'redirect_uris' => [ 'https://phish.example/landing' ] ],
		],
		'expected' => [
			'type'             => 'die',
			'message_contains' => 'unsupported_response_type',
			'response_code'    => 400,
		],
	],
	'testShouldDieRatherThanRedirectWhenUnverifiedClientOmitsCodeChallengeMethod' => [
		'config'   => [
			'get'           => [
				'redirect_uri'          => 'https://phish.example/landing',
				'code_challenge_method' => null,
			],
			'cimd_document' => [ 'redirect_uris' => [ 'https://phish.example/landing' ] ],
		],
		'expected' => [
			'type'             => 'die',
			'message_contains' => 'invalid_request',
			'response_code'    => 400,
		],
	],
	'testShouldFallBackToTheSeedWhenCanonicalFilterReturnsNonBoolean' => [
		// wpm_apply_filters_typed() reports the misuse and returns the seed, so a
		// non-boolean return leaves the effective policy at the default (true).
		'config'   => [
			'get'                       => [],
			'trusted_host'              => 'good-client.example',
			'trusted_client_ids'        => [],
			'cimd_document'             => [],
			'canonical_allow_untrusted' => 'nope',
		],
		'expected' => [
			'type'            => 'login',
			'incorrect_usage' => true,
			'transient'       => $wpmedia_mcp_oauth_test_unverified_transient,
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
