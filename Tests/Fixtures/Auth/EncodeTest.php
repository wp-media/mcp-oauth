<?php

$wpmedia_mcp_oauth_test_future_exp = time() + 3600;

return [
	'testShouldEncodePayloadAsValidSignedToken' => [
		'config'   => [
			'payload' => [
				'sub'   => 'user-1',
				'scope' => 'mcp',
				'exp'   => $wpmedia_mcp_oauth_test_future_exp,
			],
			'secret'  => 'top-secret-1',
		],
		'expected' => [
			'payload' => [
				'sub'   => 'user-1',
				'scope' => 'mcp',
				'exp'   => $wpmedia_mcp_oauth_test_future_exp,
			],
		],
	],
	'testShouldEncodePayloadAsValidSignedTokenWhenPayloadIsEmpty' => [
		'config'   => [
			'payload' => [],
			'secret'  => 'another-secret',
		],
		'expected' => [
			'payload' => [],
		],
	],
	'testShouldEncodePayloadAsValidSignedTokenWithNestedDataAndUnicode' => [
		'config'   => [
			'payload' => [
				'client' => [
					'name' => 'Café ☕',
					'id'   => 'abc-123',
				],
				'roles'  => [ 'admin', 'editor' ],
			],
			'secret'  => 'unicode-secret',
		],
		'expected' => [
			'payload' => [
				'client' => [
					'name' => 'Café ☕',
					'id'   => 'abc-123',
				],
				'roles'  => [ 'admin', 'editor' ],
			],
		],
	],
	'testShouldEncodePayloadAsValidSignedTokenWithNumericAndBooleanValues' => [
		'config'   => [
			'payload' => [
				'user_id'  => 42,
				'active'   => true,
				'balance'  => 19.99,
				'nickname' => null,
			],
			'secret'  => 'mixed-types-secret',
		],
		'expected' => [
			'payload' => [
				'user_id'  => 42,
				'active'   => true,
				'balance'  => 19.99,
				'nickname' => null,
			],
		],
	],
];
