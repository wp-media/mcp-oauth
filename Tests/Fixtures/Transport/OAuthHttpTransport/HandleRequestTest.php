<?php
/**
 * Scenarios for Tests\Integration\Transport\OAuthHttpTransport\HandleRequestTest::testHandleRequest
 *
 * `expected.type` selects which behaviour the test method exercises:
 *  - 'permission'/'register_routes'/'auth_failure_response' short-circuit
 *    into their own fixed assertions and ignore `config`.
 *  - 'error'/'authenticated' drive validate_bearer_token() via `config`, see
 *    HandleRequestTest::build_authorization() for the header/claim options.
 */

return [
	'testShouldAlwaysAllowPermission'            => [
		'config'   => [],
		'expected' => [
			'type' => 'permission',
		],
	],
	'testShouldRegisterRestRoute'                => [
		'config'   => [],
		'expected' => [
			'type' => 'register_routes',
		],
	],
	'testShouldReturn401ResponseOnAuthFailure'   => [
		'config'   => [],
		'expected' => [
			'type' => 'auth_failure_response',
		],
	],
	'testShouldRejectMissingBearerToken'         => [
		'config'   => [
			'header_type' => 'missing',
		],
		'expected' => [
			'type'       => 'error',
			'error_code' => 'mcp_unauthorized',
		],
	],
	'testShouldRejectNonBearerAuthorization'     => [
		'config'   => [
			'header_type' => 'basic',
		],
		'expected' => [
			'type' => 'error',
		],
	],
	'testShouldRejectUndecodableToken'           => [
		'config'   => [
			'header_type' => 'malformed_jwt',
		],
		'expected' => [
			'type' => 'error',
		],
	],
	'testShouldRejectAudienceMismatch'           => [
		'config'   => [
			'header_type' => 'token',
			'claims'      => [ 'aud' => 'https://evil.example/wrong' ],
		],
		'expected' => [
			'type' => 'error',
		],
	],
	'testShouldRejectIssuerMismatch'             => [
		'config'   => [
			'header_type' => 'token',
			'claims'      => [ 'iss' => 'https://evil.example' ],
		],
		'expected' => [
			'type' => 'error',
		],
	],
	'testShouldRejectMalformedClaims'            => [
		'config'   => [
			// Valid signature/aud/iss but no sub/app_pass_id claims.
			'header_type' => 'token',
		],
		'expected' => [
			'type' => 'error',
		],
	],
	'testShouldRejectRevokedApplicationPassword' => [
		'config'   => [
			'header_type' => 'token',
			// A real user, but an Application Password uuid that was never created.
			'create_user' => true,
			'claims'      => [ 'app_pass_id' => 'non-existent-uuid' ],
		],
		'expected' => [
			'type' => 'error',
		],
	],
	'testShouldAuthenticateValidToken'           => [
		'config'   => [
			'header_type'         => 'token',
			'create_user'         => true,
			'create_app_password' => true,
		],
		'expected' => [
			'type' => 'authenticated',
		],
	],
];
