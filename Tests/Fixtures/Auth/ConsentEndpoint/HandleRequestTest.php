<?php

return [
	'testShouldDieWithMethodNotAllowedForGetRequests'     => [
		'config'   => [
			'method'         => 'GET',
			'logged_in'      => false,
			'state'          => null,
			'nonce'          => 'missing',
			'seed_transient' => false,
			'action'         => null,
		],
		'expected' => [
			'outcome'           => 'die',
			'response_code'     => 405,
			'redirect_error'    => null,
			'consumes_state'    => false,
			'creates_auth_code' => false,
		],
	],
	'testShouldDieWithMethodNotAllowedForPutRequests'     => [
		'config'   => [
			'method'         => 'PUT',
			'logged_in'      => false,
			'state'          => null,
			'nonce'          => 'missing',
			'seed_transient' => false,
			'action'         => null,
		],
		'expected' => [
			'outcome'           => 'die',
			'response_code'     => 405,
			'redirect_error'    => null,
			'consumes_state'    => false,
			'creates_auth_code' => false,
		],
	],
	'testShouldDieWithMethodNotAllowedForDeleteRequests'  => [
		'config'   => [
			'method'         => 'DELETE',
			'logged_in'      => false,
			'state'          => null,
			'nonce'          => 'missing',
			'seed_transient' => false,
			'action'         => null,
		],
		'expected' => [
			'outcome'           => 'die',
			'response_code'     => 405,
			'redirect_error'    => null,
			'consumes_state'    => false,
			'creates_auth_code' => false,
		],
	],
	'testShouldDieWithMethodNotAllowedForMissingMethod'   => [
		'config'   => [
			'method'         => null,
			'logged_in'      => false,
			'state'          => null,
			'nonce'          => 'missing',
			'seed_transient' => false,
			'action'         => null,
		],
		'expected' => [
			'outcome'           => 'die',
			'response_code'     => 405,
			'redirect_error'    => null,
			'consumes_state'    => false,
			'creates_auth_code' => false,
		],
	],
	'testShouldDieWhenUserIsNotLoggedIn'                  => [
		'config'   => [
			'method'         => 'POST',
			'logged_in'      => false,
			'state'          => null,
			'nonce'          => 'missing',
			'seed_transient' => false,
			'action'         => null,
		],
		'expected' => [
			'outcome'           => 'die',
			'response_code'     => 401,
			'redirect_error'    => null,
			'consumes_state'    => false,
			'creates_auth_code' => false,
		],
	],
	'testShouldDieWhenStateParamIsMissing'                => [
		'config'   => [
			'method'         => 'POST',
			'logged_in'      => true,
			'state'          => null,
			'nonce'          => 'missing',
			'seed_transient' => false,
			'action'         => null,
		],
		'expected' => [
			'outcome'           => 'die',
			'response_code'     => 400,
			'redirect_error'    => null,
			'consumes_state'    => false,
			'creates_auth_code' => false,
		],
	],
	'testShouldDieWhenNonceIsInvalid'                     => [
		'config'   => [
			'method'         => 'POST',
			'logged_in'      => true,
			'state'          => 'nonce-failure-state',
			'nonce'          => 'invalid',
			'seed_transient' => false,
			'action'         => null,
		],
		'expected' => [
			'outcome'           => 'die',
			'response_code'     => 403,
			'redirect_error'    => null,
			'consumes_state'    => false,
			'creates_auth_code' => false,
		],
	],
	'testShouldDieWhenStateTransientIsMissing'            => [
		'config'   => [
			'method'         => 'POST',
			'logged_in'      => true,
			'state'          => 'expired-state',
			'nonce'          => 'valid',
			'seed_transient' => false,
			'action'         => null,
		],
		'expected' => [
			'outcome'           => 'die',
			'response_code'     => 400,
			'redirect_error'    => null,
			'consumes_state'    => false,
			'creates_auth_code' => false,
		],
	],
	'testShouldRedirectWithAccessDeniedWhenActionIsNotAllow' => [
		'config'   => [
			'method'         => 'POST',
			'logged_in'      => true,
			'state'          => 'deny-state',
			'nonce'          => 'valid',
			'seed_transient' => true,
			'action'         => 'deny',
		],
		'expected' => [
			'outcome'           => 'redirect',
			'response_code'     => null,
			'redirect_error'    => 'access_denied',
			'consumes_state'    => true,
			'creates_auth_code' => false,
		],
	],
	'testShouldIssueAuthCodeAndRedirectWhenActionIsAllow' => [
		'config'   => [
			'method'         => 'POST',
			'logged_in'      => true,
			'state'          => 'allow-state',
			'nonce'          => 'valid',
			'seed_transient' => true,
			'action'         => 'allow',
		],
		'expected' => [
			'outcome'           => 'redirect',
			'response_code'     => null,
			'redirect_error'    => null,
			'consumes_state'    => true,
			'creates_auth_code' => true,
		],
	],
];
