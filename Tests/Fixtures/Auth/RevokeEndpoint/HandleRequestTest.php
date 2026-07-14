<?php
/**
 * Scenarios for Tests\Integration\Auth\RevokeEndpoint\HandleRequestTest::testShouldHandleRequestAccordingToConfig
 *
 * `config.create_app_password` seeds a user with a real Application Password
 * before the request runs (see HandleRequestTest::create_user_with_app_password()).
 * `config.token` is either the literal string 'not-a-valid-jwt', or a claims
 * array encoded into a JWT via HandleRequestTest::build_token(); the
 * placeholders '{user_id}', '{uuid}', and '{exp_past}' are substituted with
 * the seeded user's id/app-password uuid, and an already-expired timestamp,
 * respectively. `config.client_id_post` sets the request's client_id param.
 */

return [
	'testShouldRejectNonPostMethod'                 => [
		'config'   => [
			'method'              => 'GET',
			'create_app_password' => false,
			'token'               => null,
			'client_id_post'      => null,
		],
		'expected' => [
			'error'                => 'invalid_request',
			'app_password_deleted' => null,
		],
	],
	'testShouldRejectMissingToken'                  => [
		'config'   => [
			'method'              => null,
			'create_app_password' => false,
			'token'               => null,
			'client_id_post'      => null,
		],
		'expected' => [
			'error'                => 'invalid_request',
			'app_password_deleted' => null,
		],
	],
	'testShouldNoOpOnUnrecognisedToken'             => [
		'config'   => [
			'method'              => null,
			'create_app_password' => false,
			'token'               => 'not-a-valid-jwt',
			'client_id_post'      => null,
		],
		'expected' => [
			'error'                => null,
			'app_password_deleted' => null,
		],
	],
	'testShouldNoOpWhenClaimsMissingSubOrAppPassId' => [
		'config'   => [
			'method'              => null,
			'create_app_password' => false,
			'token'               => [ 'foo' => 'bar' ],
			'client_id_post'      => null,
		],
		'expected' => [
			'error'                => null,
			'app_password_deleted' => null,
		],
	],
	'testShouldNoOpOnClientIdMismatch'              => [
		'config'   => [
			'method'              => null,
			'create_app_password' => true,
			'token'               => [
				'sub'         => '{user_id}',
				'app_pass_id' => '{uuid}',
				'client_id'   => 'https://a.example/app',
			],
			'client_id_post'      => 'https://different.example/app',
		],
		'expected' => [
			'error'                => null,
			'app_password_deleted' => false,
		],
	],
	'testShouldRevokeSessionOnValidToken'           => [
		'config'   => [
			'method'              => null,
			'create_app_password' => true,
			'token'               => [
				'sub'         => '{user_id}',
				'app_pass_id' => '{uuid}',
				'client_id'   => 'https://a.example/app',
				'type'        => 'access',
			],
			'client_id_post'      => 'https://a.example/app',
		],
		'expected' => [
			'error'                => null,
			'app_password_deleted' => true,
		],
	],
	'testShouldRevokeExpiredToken'                  => [
		'config'   => [
			'method'              => null,
			'create_app_password' => true,
			'token'               => [
				'sub'         => '{user_id}',
				'app_pass_id' => '{uuid}',
				'exp'         => '{exp_past}',
			],
			'client_id_post'      => null,
		],
		'expected' => [
			'error'                => null,
			'app_password_deleted' => true,
		],
	],
];
