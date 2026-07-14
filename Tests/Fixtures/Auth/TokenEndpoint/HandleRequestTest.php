<?php
/**
 * Scenarios for Tests\Integration\Auth\TokenEndpoint\HandleRequestTest::testShouldHandleRequestAccordingToConfig
 *
 * `config.user` seeds the user driving the request: 'plain' creates a bare
 * user (id only), 'with_app_password' additionally creates a real Application
 * Password (see HandleRequestTest::create_user_with_app_password()), tagged
 * with `config.app_password_client_id` when given.
 * `config.pre_sessions` (int, or 'cap' for TokenEndpoint::MAX_SESSIONS_PER_CLIENT)
 * pre-creates that many extra Application Passwords for `app_password_client_id`
 * before the request, to exercise session eviction.
 * `config.refresh_jti_seed` seeds the rotation-marker user meta for the
 * session uuid (overridden by `config.refresh_jti_uuid_override` when set, for
 * a uuid with no matching Application Password).
 * `config.transient` seeds the auth-code transient consumed by the request;
 * `code_challenge` is derived from its `verifier`.
 * `config.post` sets literal $_POST fields. `config.refresh_token` is either a
 * literal string or a claims array encoded into a JWT via
 * HandleRequestTest::build_token(), assigned to $_POST['refresh_token']; the
 * placeholders '{user_id}' and '{uuid}' resolve against the seeded user/uuid.
 */

return [
	'testShouldRejectNonPostMethod'                     => [
		'config'   => [
			'method' => 'GET',
		],
		'expected' => [
			'error' => 'invalid_request',
		],
	],
	'testShouldRejectUnsupportedGrantType'              => [
		'config'   => [
			'post' => [ 'grant_type' => 'password' ],
		],
		'expected' => [
			'error' => 'unsupported_grant_type',
		],
	],
	'testShouldRejectAuthorizationCodeMissingParams'    => [
		'config'   => [
			'post' => [
				'grant_type' => 'authorization_code',
				'code'       => 'some-code',
			],
		],
		'expected' => [
			'error' => 'invalid_request',
		],
	],
	'testShouldRejectUnknownAuthorizationCode'          => [
		'config'   => [
			'post' => [
				'grant_type'    => 'authorization_code',
				'code'          => 'does-not-exist',
				'code_verifier' => 'verifier',
			],
		],
		'expected' => [
			'error' => 'invalid_grant',
		],
	],
	'testShouldRejectPkceMismatch'                      => [
		'config'   => [
			'user'      => 'plain',
			'transient' => [
				'name'         => 'badpkce',
				'verifier'     => 'the-real-verifier',
				'redirect_uri' => 'https://client.example/cb',
				'client_id'    => 'https://client.example/app',
				'client_name'  => 'Client',
			],
			'post'      => [
				'grant_type'    => 'authorization_code',
				'code'          => 'badpkce',
				'code_verifier' => 'the-WRONG-verifier',
				'redirect_uri'  => 'https://client.example/cb',
			],
		],
		'expected' => [
			'error' => 'invalid_grant',
		],
	],
	'testShouldRejectRedirectUriMismatch'               => [
		'config'   => [
			'user'      => 'plain',
			'transient' => [
				'name'         => 'reduri',
				'verifier'     => 'a-valid-code-verifier-value',
				'redirect_uri' => 'https://client.example/cb',
				'client_id'    => 'https://client.example/app',
				'client_name'  => 'Client',
			],
			'post'      => [
				'grant_type'    => 'authorization_code',
				'code'          => 'reduri',
				'code_verifier' => 'a-valid-code-verifier-value',
				'redirect_uri'  => 'https://client.example/DIFFERENT',
			],
		],
		'expected' => [
			'error' => 'invalid_grant',
		],
	],
	'testShouldIssueTokenPairForValidAuthorizationCode' => [
		'config'   => [
			'user'      => 'plain',
			'transient' => [
				'name'         => 'good',
				'verifier'     => 'a-valid-code-verifier-value',
				'redirect_uri' => 'https://client.example/cb',
				'client_id'    => 'https://client.example/app',
				'client_name'  => 'Example Client',
			],
			'post'      => [
				'grant_type'    => 'authorization_code',
				'code'          => 'good',
				'code_verifier' => 'a-valid-code-verifier-value',
				'redirect_uri'  => 'https://client.example/cb',
			],
		],
		'expected' => [
			'success'                    => true,
			'transient_consumed'         => 'good',
			'claims_bound_to_session'    => true,
			'refresh_jti_meta_not_empty' => true,
		],
	],
	'testShouldRejectRefreshGrantMissingToken'          => [
		'config'   => [
			'post' => [ 'grant_type' => 'refresh_token' ],
		],
		'expected' => [
			'error' => 'invalid_request',
		],
	],
	'testShouldRejectUndecodableRefreshToken'           => [
		'config'   => [
			'post' => [
				'grant_type'    => 'refresh_token',
				'refresh_token' => 'not-a-jwt',
			],
		],
		'expected' => [
			'error' => 'invalid_token',
		],
	],
	'testShouldRejectRefreshTokenIssuerMismatch'        => [
		'config'   => [
			'user'          => 'with_app_password',
			'post'          => [ 'grant_type' => 'refresh_token' ],
			'refresh_token' => [
				'iss'         => 'https://evil.example',
				'sub'         => '{user_id}',
				'app_pass_id' => '{uuid}',
			],
		],
		'expected' => [
			'error' => 'invalid_token',
		],
	],
	'testShouldRejectRefreshTokenForRevokedSession'     => [
		'config'   => [
			'user'                      => 'plain',
			'refresh_jti_uuid_override' => 'missing-app-pass-uuid',
			'refresh_jti_seed'          => 'stale-jti',
			'post'                      => [ 'grant_type' => 'refresh_token' ],
			'refresh_token'             => [
				'sub'         => '{user_id}',
				'app_pass_id' => '{uuid}',
				'jti'         => 'stale-jti',
			],
		],
		'expected' => [
			'error'                    => 'invalid_token',
			'refresh_jti_meta_cleared' => true,
		],
	],
	'testShouldRevokeSessionOnRefreshTokenReuse'        => [
		'config'   => [
			'user'             => 'with_app_password',
			'refresh_jti_seed' => 'current-jti',
			'post'             => [ 'grant_type' => 'refresh_token' ],
			'refresh_token'    => [
				'sub'         => '{user_id}',
				'app_pass_id' => '{uuid}',
				'jti'         => 'an-old-rotated-jti',
			],
		],
		'expected' => [
			'error'               => 'invalid_grant',
			'app_password_exists' => false,
		],
	],
	'testShouldIssueRotatedPairForValidRefreshToken'    => [
		'config'   => [
			'user'                   => 'with_app_password',
			'app_password_client_id' => 'https://client.example/app',
			'refresh_jti_seed'       => 'current-jti',
			'post'                   => [ 'grant_type' => 'refresh_token' ],
			'refresh_token'          => [
				'sub'         => '{user_id}',
				'app_pass_id' => '{uuid}',
				'client_id'   => 'https://client.example/app',
				'jti'         => 'current-jti',
			],
		],
		'expected' => [
			'success'                       => true,
			'refresh_jti_meta_changed_from' => 'current-jti',
			'claims_app_pass_id_is_uuid'    => true,
		],
	],
	'testShouldEvictOldestSessionsBeyondPerClientCap'   => [
		'config'   => [
			'user'                   => 'plain',
			'app_password_client_id' => 'https://client.example/app',
			'pre_sessions'           => 'cap',
			'transient'              => [
				'name'         => 'capped',
				'verifier'     => 'a-valid-code-verifier-value',
				'redirect_uri' => 'https://client.example/cb',
				'client_id'    => 'https://client.example/app',
				'client_name'  => 'Example Client',
			],
			'post'                   => [
				'grant_type'    => 'authorization_code',
				'code'          => 'capped',
				'code_verifier' => 'a-valid-code-verifier-value',
				'redirect_uri'  => 'https://client.example/cb',
			],
		],
		'expected' => [
			'success'        => true,
			'eviction_check' => true,
		],
	],
];
