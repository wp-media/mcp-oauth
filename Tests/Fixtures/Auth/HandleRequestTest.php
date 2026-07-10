<?php

return [
	'testShouldReturnEarlyWhenEndpointIsEmpty' => [
		'config'   => [
			'endpoint'   => '',
			'is_enabled' => true,
		],
		'expected' => [
			'check_enabled' => false,
			'force_404'     => false,
			'unknown'       => false,
			'dispatch'      => null,
		],
	],
	'testShouldForce404WhenDisabled'           => [
		'config'   => [
			'endpoint'   => 'authorize',
			'is_enabled' => false,
		],
		'expected' => [
			'check_enabled' => true,
			'force_404'     => true,
			'unknown'       => false,
			'dispatch'      => null,
		],
	],
	'testShouldDispatchToAuthorizeEndpoint'    => [
		'config'   => [
			'endpoint'   => 'authorize',
			'is_enabled' => true,
		],
		'expected' => [
			'check_enabled' => true,
			'force_404'     => false,
			'unknown'       => false,
			'dispatch'      => 'authorize_endpoint',
		],
	],
	'testShouldDispatchToAuthorizeCallback'    => [
		'config'   => [
			'endpoint'   => 'authorize-callback',
			'is_enabled' => true,
		],
		'expected' => [
			'check_enabled' => true,
			'force_404'     => false,
			'unknown'       => false,
			'dispatch'      => 'authorize_callback',
		],
	],
	'testShouldDispatchToConsentEndpoint'      => [
		'config'   => [
			'endpoint'   => 'consent',
			'is_enabled' => true,
		],
		'expected' => [
			'check_enabled' => true,
			'force_404'     => false,
			'unknown'       => false,
			'dispatch'      => 'consent_endpoint',
		],
	],
	'testShouldDispatchToRevokeEndpoint'       => [
		'config'   => [
			'endpoint'   => 'revoke',
			'is_enabled' => true,
		],
		'expected' => [
			'check_enabled' => true,
			'force_404'     => false,
			'unknown'       => false,
			'dispatch'      => 'revoke_endpoint',
		],
	],
	'testShouldDispatchToTokenEndpoint'        => [
		'config'   => [
			'endpoint'   => 'token',
			'is_enabled' => true,
		],
		'expected' => [
			'check_enabled' => true,
			'force_404'     => false,
			'unknown'       => false,
			'dispatch'      => 'token_endpoint',
		],
	],
	'testShouldDieOnUnknownEndpoint'           => [
		'config'   => [
			'endpoint'   => 'unknown-endpoint',
			'is_enabled' => true,
		],
		'expected' => [
			'check_enabled' => true,
			'force_404'     => false,
			'unknown'       => true,
			'dispatch'      => null,
		],
	],
];
