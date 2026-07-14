<?php

return [
	'testShouldWriteWhenWpDebugLogTrueAndWpDebugTrue'      => [
		'config'   => [
			'define_wp_debug_log' => true,
			'wp_debug_log_value'  => true,
			'define_wp_debug'     => true,
			'wp_debug_value'      => true,
		],
		'expected' => [
			'error_log_called' => true,
		],
	],
	'testShouldNotWriteWhenWpDebugLogTrueAndWpDebugFalse'  => [
		'config'   => [
			'define_wp_debug_log' => true,
			'wp_debug_log_value'  => true,
			'define_wp_debug'     => true,
			'wp_debug_value'      => false,
		],
		'expected' => [
			'error_log_called' => false,
		],
	],
	'testShouldNotWriteWhenWpDebugLogFalseAndWpDebugTrue'  => [
		'config'   => [
			'define_wp_debug_log' => true,
			'wp_debug_log_value'  => false,
			'define_wp_debug'     => true,
			'wp_debug_value'      => true,
		],
		'expected' => [
			'error_log_called' => false,
		],
	],
	'testShouldNotWriteWhenWpDebugLogFalseAndWpDebugFalse' => [
		'config'   => [
			'define_wp_debug_log' => true,
			'wp_debug_log_value'  => false,
			'define_wp_debug'     => true,
			'wp_debug_value'      => false,
		],
		'expected' => [
			'error_log_called' => false,
		],
	],
	'testShouldNotWriteWhenNeitherConstantDefined'         => [
		'config'   => [],
		'expected' => [
			'error_log_called' => false,
		],
	],
	'testShouldNotWriteWhenOnlyWpDebugTrue'                => [
		'config'   => [
			'define_wp_debug' => true,
			'wp_debug_value'  => true,
		],
		'expected' => [
			'error_log_called' => false,
		],
	],
	'testShouldWriteWhenWpDebugLogIsFilePathStringAndWpDebugTrue' => [
		'config'   => [
			'define_wp_debug_log' => true,
			'wp_debug_log_value'  => '/tmp/mcp-oauth-debug.log',
			'define_wp_debug'     => true,
			'wp_debug_value'      => true,
		],
		'expected' => [
			'error_log_called' => true,
		],
	],
	'testShouldNotWriteWhenWpDebugLogIsFilePathStringAndWpDebugFalse' => [
		'config'   => [
			'define_wp_debug_log' => true,
			'wp_debug_log_value'  => '/tmp/mcp-oauth-debug.log',
			'define_wp_debug'     => true,
			'wp_debug_value'      => false,
		],
		'expected' => [
			'error_log_called' => false,
		],
	],
];
