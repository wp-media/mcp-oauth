<?php

return [
	'testShouldReturnRewriteResultForEmptyList'    => [
		'config'   => [
			'vars' => [],
		],
		'expected' => [
			'vars' => [ 'mcp_oauth_endpoint' ],
		],
	],
	'testShouldReturnRewriteResultForExistingList' => [
		'config'   => [
			'vars' => [ 'existing_var' ],
		],
		'expected' => [
			'vars' => [ 'existing_var', 'mcp_oauth_endpoint' ],
		],
	],
];
