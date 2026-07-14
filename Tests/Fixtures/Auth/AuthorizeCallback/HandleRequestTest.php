<?php

return [
	'testShouldDieWhenUserNotLoggedIn'                    => [
		'config'   => [
			'logged_in'     => false,
			'set_state'     => false,
			'set_transient' => false,
		],
		'expected' => [
			'outcome' => 'die',
			'message' => 'You must be logged in to authorise an MCP session.',
			'code'    => 401,
		],
	],
	'testShouldDieWhenStateParameterIsMissing'            => [
		'config'   => [
			'logged_in'     => true,
			'set_state'     => false,
			'set_transient' => false,
		],
		'expected' => [
			'outcome' => 'die',
			'message' => 'Missing state parameter.',
			'code'    => 400,
		],
	],
	'testShouldDieWhenStateTransientIsMissing'            => [
		'config'   => [
			'logged_in'     => true,
			'set_state'     => true,
			'set_transient' => false,
		],
		'expected' => [
			'outcome' => 'die',
			'message' => 'Invalid or expired state. Please restart the authorization flow.',
			'code'    => 400,
		],
	],
	'testShouldReachRenderConsentScreenWhenAllGuardsPass' => [
		'config'   => [
			'logged_in'     => true,
			'set_state'     => true,
			'set_transient' => true,
		],
		'expected' => [
			'outcome' => 'render',
		],
	],
];
