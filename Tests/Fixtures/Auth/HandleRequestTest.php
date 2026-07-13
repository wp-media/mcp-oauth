<?php

return [
	'testShouldDieWhenClientIdIsMissing'                 => [
		'config'   => [
			'get'            => [
				'redirect_uri'          => 'https://client.example/callback',
				'response_type'         => 'code',
				'code_challenge'        => 'challenge-value',
				'code_challenge_method' => 'S256',
				'state'                 => 'state-value',
			],
			'resolve_called' => false,
			'client'         => null,
		],
		'expected' => [
			'message_contains' => 'client_id is required.',
			'response_code'    => 400,
		],
	],
	'testShouldDieWhenClientCannotBeResolved'            => [
		'config'   => [
			'get'            => [
				'client_id'             => 'https://good-client.example/app',
				'redirect_uri'          => 'https://client.example/callback',
				'response_type'         => 'code',
				'code_challenge'        => 'challenge-value',
				'code_challenge_method' => 'S256',
				'state'                 => 'state-value',
			],
			'resolve_called' => true,
			'client'         => null,
		],
		'expected' => [
			'message_contains' => 'Unknown OAuth client.',
			'response_code'    => 400,
		],
	],
	'testShouldDieWhenClientIsNotVerified'               => [
		'config'   => [
			'get'            => [
				'client_id'             => 'https://good-client.example/app',
				'redirect_uri'          => 'https://client.example/callback',
				'response_type'         => 'code',
				'code_challenge'        => 'challenge-value',
				'code_challenge_method' => 'S256',
				'state'                 => 'state-value',
			],
			'resolve_called' => true,
			'client'         => [
				'client_id'     => 'https://good-client.example/app',
				'client_name'   => 'Example App',
				'client_uri'    => 'https://client.example',
				'redirect_uris' => [ 'https://client.example/callback' ],
				'verified'      => false,
				'publisher'     => '',
			],
		],
		'expected' => [
			'message_contains' => 'not a verified publisher',
			'response_code'    => 400,
		],
	],
	'testShouldDieWhenRedirectUriIsMissing'              => [
		'config'   => [
			'get'            => [
				'client_id'             => 'https://good-client.example/app',
				'response_type'         => 'code',
				'code_challenge'        => 'challenge-value',
				'code_challenge_method' => 'S256',
				'state'                 => 'state-value',
			],
			'resolve_called' => true,
			'client'         => [
				'client_id'     => 'https://good-client.example/app',
				'client_name'   => 'Example App',
				'client_uri'    => 'https://client.example',
				'redirect_uris' => [ 'https://client.example/callback' ],
				'verified'      => true,
				'publisher'     => 'anthropic',
			],
		],
		'expected' => [
			'message_contains' => 'redirect_uri is required.',
			'response_code'    => 400,
		],
	],
	'testShouldDieWhenRedirectUriDoesNotMatchRegistered' => [
		'config'   => [
			'get'            => [
				'client_id'             => 'https://good-client.example/app',
				'redirect_uri'          => 'https://evil.example/callback',
				'response_type'         => 'code',
				'code_challenge'        => 'challenge-value',
				'code_challenge_method' => 'S256',
				'state'                 => 'state-value',
			],
			'resolve_called' => true,
			'client'         => [
				'client_id'     => 'https://good-client.example/app',
				'client_name'   => 'Example App',
				'client_uri'    => 'https://client.example',
				'redirect_uris' => [ 'https://client.example/callback' ],
				'verified'      => true,
				'publisher'     => 'anthropic',
			],
		],
		'expected' => [
			'message_contains' => 'does not match registered value.',
			'response_code'    => 400,
		],
	],
];
