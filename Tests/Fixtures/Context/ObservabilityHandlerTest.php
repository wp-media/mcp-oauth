<?php

use WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler;
use WPMedia\MCP\OAuth\Transport\McpObservabilityHandler;

return [
	'testShouldReturnNullHandlerWhenNoFilterOverridesIt' => [
		'config'   => [
			'filtered' => null,
		],
		'expected' => [
			'result'          => NullMcpObservabilityHandler::class,
			'incorrect_usage' => false,
		],
	],
	'testShouldReturnVerboseHandlerWhenFilterOptsIn'     => [
		'config'   => [
			'filtered' => McpObservabilityHandler::class,
		],
		'expected' => [
			'result'          => McpObservabilityHandler::class,
			'incorrect_usage' => false,
		],
	],
	'testShouldFallBackToNullHandlerWhenFilteredClassDoesNotExist' => [
		'config'   => [
			'filtered' => 'WPMedia\MCP\OAuth\Tests\NoSuchHandler',
		],
		'expected' => [
			'result'          => NullMcpObservabilityHandler::class,
			'incorrect_usage' => false,
		],
	],
	'testShouldFallBackToNullHandlerWhenFilteredClassDoesNotImplementTheInterface' => [
		'config'   => [
			'filtered' => \WPMedia\MCP\OAuth\Context::class,
		],
		'expected' => [
			'result'          => NullMcpObservabilityHandler::class,
			'incorrect_usage' => false,
		],
	],
	'testShouldFallBackToNullHandlerWhenFilterReturnsNonString' => [
		'config'   => [
			'filtered' => 42,
		],
		'expected' => [
			'result'          => NullMcpObservabilityHandler::class,
			'incorrect_usage' => true,
		],
	],
];
