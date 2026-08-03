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
 * `config.legacy_allow_untrusted` / `config.canonical_allow_untrusted` hook the
 * deprecated and canonical filters to an arbitrary (possibly non-boolean) return
 * value, paired with `expected.deprecated` / `expected.incorrect_usage`.
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
	'testShouldDieWhenClientIdIsMissing'                   => [
		'config'   => [
			'get' => [ 'client_id' => null ],
		],
		'expected' => [
			'type'             => 'die',
			'message_contains' => 'client_id is required.',
			'response_code'    => 400,
		],
	],
	'testShouldDieWhenClientCannotBeResolved'              => [
		'config'   => [
			// No trusted-publisher registered for this host, and untrusted
			// providers disallowed, so CimdResolver rejects the client_id before
			// any fetch is attempted (also why no cimd_document is needed).
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
		// Mirror of the case above under the default filter value: an untrusted
		// host presenting a valid CIMD document is fetched and reaches consent
		// with the unverified trust signal in the state transient.
		'config'   => [
			'get'           => [],
			'cimd_document' => [],
		],
		'expected' => [
			'type'      => 'login',
			'transient' => $wpmedia_mcp_oauth_test_unverified_transient,
		],
	],
	'testShouldDieWhenClientIsNotVerified'                 => [
		'config'   => [
			'get'                => [],
			'trusted_host'       => 'good-client.example',
			// Host is trusted (so the fetch proceeds) but the exact client_id
			// isn't in the allowlist, so ClaudeClientVerifier::verify() fails.
			'trusted_client_ids' => [],
			'cimd_document'      => [],
			// Required for the hard-reject: with the default filter value this
			// same scenario completes to consent (the mirror case below).
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
	'testShouldDieWhenRedirectUriIsMissing'                => [
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
	'testShouldDieWhenRedirectUriDoesNotMatchRegistered'   => [
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
	'testShouldRedirectWithUnsupportedResponseTypeError'   => [
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
		// Open-redirect closure: the redirect_uri is only known from an
		// unverified, attacker-publishable CIMD document and this branch runs
		// before login and before consent, so no 302 may be emitted. The test
		// method installs a throwing wp_redirect interceptor, so a regression
		// surfaces as a failure rather than a real redirect.
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
	'testShouldAllowUntrustedWhenLegacyFilterReturnsTruthyString' => [
		// The legacy hook's raw return is (bool)-cast before it reaches the
		// bool-typed resolve() parameter, so a non-boolean cannot fatal under
		// strict_types on this public, unauthenticated endpoint.
		'config'   => [
			'get'                    => [],
			'trusted_host'           => 'good-client.example',
			'trusted_client_ids'     => [],
			'cimd_document'          => [],
			'legacy_allow_untrusted' => 'yes',
		],
		'expected' => [
			'type'       => 'login',
			'deprecated' => true,
			'transient'  => $wpmedia_mcp_oauth_test_unverified_transient,
		],
	],
	'testShouldRejectUntrustedWhenLegacyFilterReturnsFalsyString' => [
		'config'   => [
			'get'                    => [],
			'trusted_host'           => 'good-client.example',
			'trusted_client_ids'     => [],
			'cimd_document'          => [],
			'legacy_allow_untrusted' => '0',
		],
		'expected' => [
			'type'             => 'die',
			'deprecated'       => true,
			'message_contains' => 'not a verified publisher',
			'response_code'    => 400,
		],
	],
	'testShouldRejectUntrustedWhenLegacyFilterReturnsNull' => [
		'config'   => [
			'get'                    => [],
			'trusted_host'           => 'good-client.example',
			'trusted_client_ids'     => [],
			'cimd_document'          => [],
			'legacy_allow_untrusted' => null,
		],
		'expected' => [
			'type'             => 'die',
			'deprecated'       => true,
			'message_contains' => 'not a verified publisher',
			'response_code'    => 400,
		],
	],
	'testShouldFallBackToTheSeedWhenCanonicalFilterReturnsNonBoolean' => [
		// wpm_apply_filters_typed() reports the misuse and returns the SEED,
		// which the outer (bool) cast has already normalised — so the effective
		// policy is the seed's value (true) and nothing fatals.
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
	'testShouldAcceptLoopbackRedirectUriRegardlessOfPort'  => [
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
