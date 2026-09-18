<?php
/**
 * Scenarios for MaybeBlockOutOfScopeTest.
 *
 * Keys — route: 'mcp' | 'other'. user: 'none' | 'authenticated' | 'owned'
 * (app password with the mcp_refresh_jti_ marker) | 'foreign' (without).
 * incoming_result: 'null' | 'wp_error' | 'true'.
 */

return [
	'testShouldBlockWhenOwnedAppPasswordUsedOffMcpRoute'   => [
		'config'   => [
			'route'           => 'other',
			'user'            => 'owned',
			'incoming_result' => 'null',
		],
		'expected' => [
			'type' => 'blocked',
		],
	],
	'testShouldAllowWhenOwnedAppPasswordUsedOnMcpRoute'    => [
		'config'   => [
			'route'           => 'mcp',
			'user'            => 'owned',
			'incoming_result' => 'null',
		],
		'expected' => [
			'type' => 'allowed',
		],
	],
	'testShouldAllowWhenForeignAppPasswordUsedOffMcpRoute' => [
		'config'   => [
			'route'           => 'other',
			'user'            => 'foreign',
			'incoming_result' => 'null',
		],
		'expected' => [
			'type' => 'allowed',
		],
	],
	'testShouldAllowWhenNoApplicationPasswordAuth'         => [
		'config'   => [
			'route'           => 'other',
			'user'            => 'authenticated',
			'incoming_result' => 'null',
		],
		'expected' => [
			'type' => 'allowed',
		],
	],
	'testShouldAllowWhenUnauthenticatedUser'               => [
		'config'   => [
			'route'           => 'other',
			'user'            => 'none',
			'incoming_result' => 'null',
		],
		'expected' => [
			'type' => 'allowed',
		],
	],
	'testShouldReturnResultUnchangedWhenRouteIsMcpEndpoint' => [
		'config'   => [
			'route'           => 'mcp',
			'user'            => 'none',
			'incoming_result' => 'null',
		],
		'expected' => [
			'type' => 'allowed',
		],
	],
	'testShouldPreserveIncomingWpErrorUnchanged'           => [
		'config'   => [
			'route'           => 'other',
			'user'            => 'owned',
			'incoming_result' => 'wp_error',
		],
		'expected' => [
			'type' => 'unchanged',
		],
	],
	'testShouldPreserveIncomingSuccessResultUnchanged'     => [
		'config'   => [
			'route'           => 'other',
			'user'            => 'owned',
			'incoming_result' => 'true',
		],
		'expected' => [
			'type' => 'unchanged',
		],
	],
];
