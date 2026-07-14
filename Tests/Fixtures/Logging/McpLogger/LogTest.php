<?php

return [
	'testShouldWriteWhenWpDebugLogTrueAndDebugOnlyFalse' => [
		'config'   => [
			'define_wp_debug_log' => true,
			'wp_debug_log_value'  => true,
			'debug_only'          => false,
		],
		'expected' => [
			'error_log_called' => true,
		],
	],
	'testShouldWriteWhenWpDebugLogTrueAndDebugOnlyTrue'  => [
		'config'   => [
			'define_wp_debug_log' => true,
			'wp_debug_log_value'  => true,
			'debug_only'          => true,
		],
		'expected' => [
			'error_log_called' => true,
		],
	],
	'testShouldNotWriteWhenWpDebugLogFalseAndDebugOnlyFalse' => [
		'config'   => [
			'define_wp_debug_log' => true,
			'wp_debug_log_value'  => false,
			'debug_only'          => false,
		],
		'expected' => [
			'error_log_called' => false,
		],
	],
	'testShouldNotWriteWhenWpDebugLogFalseAndDebugOnlyTrue' => [
		'config'   => [
			'define_wp_debug_log' => true,
			'wp_debug_log_value'  => false,
			'debug_only'          => true,
		],
		'expected' => [
			'error_log_called' => false,
		],
	],
	'testShouldNotWriteWhenNeitherConstantDefined'       => [
		'config'   => [
			'debug_only' => false,
		],
		'expected' => [
			'error_log_called' => false,
		],
	],
	'testShouldNotWriteWhenOnlyWpDebugTrue'              => [
		'config'   => [
			'define_wp_debug' => true,
			'wp_debug_value'  => true,
			'debug_only'      => false,
		],
		'expected' => [
			'error_log_called' => false,
		],
	],
	'testShouldWriteWhenWpDebugLogIsFilePathString'      => [
		'config'   => [
			'define_wp_debug_log' => true,
			'wp_debug_log_value'  => '/tmp/mcp-oauth-debug.log',
			'debug_only'          => false,
		],
		'expected' => [
			'error_log_called' => true,
		],
	],
];
