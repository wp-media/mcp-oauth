<?php
/**
 * Scenarios for MaybeBlockOutOfScopeTest.
 *
 * Keys — route: 'mcp' | 'other'. ownership: 'owned' (app password with the
 * mcp_refresh_jti_ marker) | 'foreign' (without) | 'no_uuid' (item missing its
 * uuid). expected.type: 'blocked' | 'allowed'.
 */

return [
	'testShouldBlockWhenOwnedAppPasswordUsedOffMcpRoute'   => [
		'config'   => [
			'route'     => 'other',
			'ownership' => 'owned',
		],
		'expected' => [
			'type' => 'blocked',
		],
	],
	'testShouldAllowWhenOwnedAppPasswordUsedOnMcpRoute'    => [
		'config'   => [
			'route'     => 'mcp',
			'ownership' => 'owned',
		],
		'expected' => [
			'type' => 'allowed',
		],
	],
	'testShouldAllowWhenForeignAppPasswordUsedOffMcpRoute' => [
		'config'   => [
			'route'     => 'other',
			'ownership' => 'foreign',
		],
		'expected' => [
			'type' => 'allowed',
		],
	],
	'testShouldAllowWhenAppPasswordHasNoUuidOffMcpRoute'   => [
		'config'   => [
			'route'     => 'other',
			'ownership' => 'no_uuid',
		],
		'expected' => [
			'type' => 'allowed',
		],
	],
	'testShouldAllowWhenForeignAppPasswordUsedOnMcpRoute'  => [
		'config'   => [
			'route'     => 'mcp',
			'ownership' => 'foreign',
		],
		'expected' => [
			'type' => 'allowed',
		],
	],
];
