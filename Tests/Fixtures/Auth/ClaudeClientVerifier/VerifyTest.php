<?php

return [
	'testShouldVerifyClaudeCodeClientId'          => [
		'config'   => [
			'client_id' => 'https://claude.ai/oauth/claude-code-client-metadata',
			'doc'       => [
				'token_endpoint_auth_method' => 'none',
			],
		],
		'expected' => [
			'verified'  => true,
			'publisher' => 'claude',
		],
	],
	'testShouldVerifyMcpOauthClientId'            => [
		'config'   => [
			'client_id' => 'https://claude.ai/oauth/mcp-oauth-client-metadata',
			'doc'       => [
				'token_endpoint_auth_method' => 'none',
			],
		],
		'expected' => [
			'verified'  => true,
			'publisher' => 'claude',
		],
	],
	'testShouldVerifyWhenAuthMethodIsAbsent'      => [
		'config'   => [
			'client_id' => 'https://claude.ai/oauth/claude-code-client-metadata',
			'doc'       => [],
		],
		'expected' => [
			'verified'  => true,
			'publisher' => 'claude',
		],
	],
	'testShouldNotVerifyUnknownClientId'          => [
		'config'   => [
			'client_id' => 'https://evil.example/oauth/claude-code-client-metadata',
			'doc'       => [
				'token_endpoint_auth_method' => 'none',
			],
		],
		'expected' => [
			'verified'  => false,
			'publisher' => '',
		],
	],
	'testShouldNotVerifyKnownClientIdOnWrongHost' => [
		'config'   => [
			'client_id'          => 'https://claude.ai/oauth/claude-code-client-metadata',
			'doc'                => [
				'token_endpoint_auth_method' => 'none',
			],
			'trusted_publishers' => [
				'claude' => [
					'client_ids' => [ 'https://claude.ai/oauth/claude-code-client-metadata' ],
					'host'       => 'not-claude.ai',
				],
			],
		],
		'expected' => [
			'verified'  => false,
			'publisher' => '',
		],
	],
	'testShouldNotVerifyKnownClientIdWithNonNoneAuthMethod' => [
		'config'   => [
			'client_id' => 'https://claude.ai/oauth/claude-code-client-metadata',
			'doc'       => [
				'token_endpoint_auth_method' => 'client_secret_post',
			],
		],
		'expected' => [
			'verified'  => false,
			'publisher' => '',
		],
	],
];
