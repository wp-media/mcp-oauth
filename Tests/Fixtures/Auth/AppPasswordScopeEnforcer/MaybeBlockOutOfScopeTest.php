<?php
/**
 * Scenarios for Tests\Integration\Auth\AppPasswordScopeEnforcer\MaybeBlockOutOfScopeTest::testMaybeBlockOutOfScopeAccordingToConfig
 *
 * `config.route` picks the simulated current REST route ('mcp' for the MCP
 * endpoint, 'other' for any other route). `config.user` picks the simulated
 * authentication state: 'none' (unauthenticated), 'authenticated' (a real
 * user, no Application Password), 'owned' (a real Application Password with
 * the mcp_refresh_jti_ ownership marker), or 'foreign' (a real Application
 * Password without the marker). `config.incoming_result` picks the value
 * passed in as $result: 'null', 'wp_error', or 'true'.
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
