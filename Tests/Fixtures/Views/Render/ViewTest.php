<?php

return [
	'testShouldEchoTemplateOutputWithGivenData'            => [
		'config'   => [
			'view' => 'sample',
			'data' => [ 'message' => 'Hello, World!' ],
		],
		'expected' => [
			'output'    => 'Hello, World!',
			'exception' => null,
		],
	],
	'testShouldThrowRuntimeExceptionWhenTemplateIsMissing' => [
		'config'   => [
			'view' => 'does-not-exist',
			'data' => [],
		],
		'expected' => [
			'output'    => null,
			'exception' => RuntimeException::class,
		],
	],
	'testShouldConfinePathTraversalAttemptsToTemplatesDirectory' => [
		'config'   => [
			'view' => '../../secret',
			'data' => [],
		],
		'expected' => [
			'output'    => null,
			'exception' => RuntimeException::class,
		],
	],
];
