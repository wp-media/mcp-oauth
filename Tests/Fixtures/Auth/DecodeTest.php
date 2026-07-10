<?php

$wpmedia_mcp_oauth_test_future_exp = time() + 3600;
$wpmedia_mcp_oauth_test_past_exp   = time() - 3600;

return [
	'testShouldReturnPayloadForValidToken'               => [
		'config'   => [
			'payload'       => [
				'sub' => 'user-1',
				'exp' => $wpmedia_mcp_oauth_test_future_exp,
			],
			'encode_secret' => 'shared-secret',
			'decode_secret' => 'shared-secret',
			'verify_expiry' => true,
			'tamper'        => null,
		],
		'expected' => [
			'result' => [
				'sub' => 'user-1',
				'exp' => $wpmedia_mcp_oauth_test_future_exp,
			],
		],
	],
	'testShouldReturnPayloadWhenNoExpiryClaimIsPresent'  => [
		'config'   => [
			'payload'       => [
				'sub'   => 'user-2',
				'scope' => 'mcp',
			],
			'encode_secret' => 'shared-secret',
			'decode_secret' => 'shared-secret',
			'verify_expiry' => true,
			'tamper'        => null,
		],
		'expected' => [
			'result' => [
				'sub'   => 'user-2',
				'scope' => 'mcp',
			],
		],
	],
	'testShouldReturnNullWhenSignedWithADifferentSecret' => [
		'config'   => [
			'payload'       => [
				'sub' => 'user-3',
				'exp' => $wpmedia_mcp_oauth_test_future_exp,
			],
			'encode_secret' => 'secret-a',
			'decode_secret' => 'secret-b',
			'verify_expiry' => true,
			'tamper'        => null,
		],
		'expected' => [
			'result' => null,
		],
	],
	'testShouldReturnNullForATamperedSignature'          => [
		'config'   => [
			'payload'       => [
				'sub' => 'user-4',
				'exp' => $wpmedia_mcp_oauth_test_future_exp,
			],
			'encode_secret' => 'shared-secret',
			'decode_secret' => 'shared-secret',
			'verify_expiry' => true,
			'tamper'        => 'signature',
		],
		'expected' => [
			'result' => null,
		],
	],
	'testShouldReturnNullForNoneAlgorithm'               => [
		'config'   => [
			'payload'       => [
				'sub' => 'user-5',
				'exp' => $wpmedia_mcp_oauth_test_future_exp,
			],
			'encode_secret' => 'shared-secret',
			'decode_secret' => 'shared-secret',
			'verify_expiry' => true,
			'tamper'        => 'alg_none',
		],
		'expected' => [
			'result' => null,
		],
	],
	'testShouldReturnNullForANonHS256Algorithm'          => [
		'config'   => [
			'payload'       => [
				'sub' => 'user-6',
				'exp' => $wpmedia_mcp_oauth_test_future_exp,
			],
			'encode_secret' => 'shared-secret',
			'decode_secret' => 'shared-secret',
			'verify_expiry' => true,
			'tamper'        => 'alg_rs256',
		],
		'expected' => [
			'result' => null,
		],
	],
	'testShouldReturnNullForATokenWithTooFewSegments'    => [
		'config'   => [
			'payload'       => [
				'sub' => 'user-7',
			],
			'encode_secret' => 'shared-secret',
			'decode_secret' => 'shared-secret',
			'verify_expiry' => true,
			'tamper'        => 'too_few_parts',
		],
		'expected' => [
			'result' => null,
		],
	],
	'testShouldReturnNullForATokenWithTooManySegments'   => [
		'config'   => [
			'payload'       => [
				'sub' => 'user-8',
			],
			'encode_secret' => 'shared-secret',
			'decode_secret' => 'shared-secret',
			'verify_expiry' => true,
			'tamper'        => 'too_many_parts',
		],
		'expected' => [
			'result' => null,
		],
	],
	'testShouldReturnNullForAnExpiredTokenWhenVerifyingExpiry' => [
		'config'   => [
			'payload'       => [
				'sub' => 'user-9',
				'exp' => $wpmedia_mcp_oauth_test_past_exp,
			],
			'encode_secret' => 'shared-secret',
			'decode_secret' => 'shared-secret',
			'verify_expiry' => true,
			'tamper'        => null,
		],
		'expected' => [
			'result' => null,
		],
	],
	'testShouldReturnPayloadForAnExpiredTokenWhenNotVerifyingExpiry' => [
		'config'   => [
			'payload'       => [
				'sub' => 'user-10',
				'exp' => $wpmedia_mcp_oauth_test_past_exp,
			],
			'encode_secret' => 'shared-secret',
			'decode_secret' => 'shared-secret',
			'verify_expiry' => false,
			'tamper'        => null,
		],
		'expected' => [
			'result' => [
				'sub' => 'user-10',
				'exp' => $wpmedia_mcp_oauth_test_past_exp,
			],
		],
	],
];
