<?php

return [
	'testShouldAppendQueryVarToEmptyList'    => [
		'config'   => [
			'vars' => [],
		],
		'expected' => [ 'mcp_oauth_discovery' ],
	],
	'testShouldAppendQueryVarToExistingList' => [
		'config'   => [
			'vars' => [ 'existing_var' ],
		],
		'expected' => [ 'existing_var', 'mcp_oauth_discovery' ],
	],
];
