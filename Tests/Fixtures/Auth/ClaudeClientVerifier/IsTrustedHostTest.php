<?php

return [
	'testShouldTrustClaudeHost'                  => [
		'config'   => [
			'client_id' => 'https://claude.ai/oauth/claude-code-client-metadata',
		],
		'expected' => [
			'is_trusted' => true,
		],
	],
	'testShouldNotTrustAnUnknownHost'            => [
		'config'   => [
			'client_id' => 'https://evil.example/oauth/claude-code-client-metadata',
		],
		'expected' => [
			'is_trusted' => false,
		],
	],
	'testShouldNotTrustAMalformedHostlessString' => [
		'config'   => [
			'client_id' => 'not-a-url',
		],
		'expected' => [
			'is_trusted' => false,
		],
	],
];
