<?php

return [
	'testShouldRegisterRewriteRuleForEachEndpoint' => [
		'config'   => [],
		'expected' => [
			'rules' => [
				[
					'pattern' => '^oauth/authorize$',
					'query'   => 'index.php?mcp_oauth_endpoint=authorize',
				],
				[
					'pattern' => '^oauth/authorize-callback$',
					'query'   => 'index.php?mcp_oauth_endpoint=authorize-callback',
				],
				[
					'pattern' => '^oauth/token$',
					'query'   => 'index.php?mcp_oauth_endpoint=token',
				],
				[
					'pattern' => '^oauth/consent$',
					'query'   => 'index.php?mcp_oauth_endpoint=consent',
				],
				[
					'pattern' => '^oauth/revoke$',
					'query'   => 'index.php?mcp_oauth_endpoint=revoke',
				],
			],
		],
	],
];
