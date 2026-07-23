<?php

return [
	'testShouldReturnRecommendedWhenResponseIsWpError'  => [
		'config'   => [
			'is_wp_error' => true,
		],
		'expected' => [
			'status' => 'recommended',
		],
	],
	'testShouldReturnRecommendedWhenStatusIs401'        => [
		'config'   => [
			'status' => 401,
		],
		'expected' => [
			'status' => 'recommended',
		],
	],
	'testShouldReturnRecommendedWhenStatusIs403'        => [
		'config'   => [
			'status' => 403,
		],
		'expected' => [
			'status' => 'recommended',
		],
	],
	'testShouldReturnRecommendedWhenStatusIs407'        => [
		'config'   => [
			'status' => 407,
		],
		'expected' => [
			'status' => 'recommended',
		],
	],
	'testShouldReturnCriticalWhenStatusIs404AndNoWordPressHeaderPresent' => [
		'config'   => [
			'status'      => 404,
			'powered_by'  => '',
			'redirect_by' => '',
		],
		'expected' => [
			'status' => 'critical',
		],
	],
	'testShouldReturnRecommendedWhenStatusIs404ButPoweredByHeaderPresent' => [
		'config'   => [
			'status'      => 404,
			'powered_by'  => 'PHP/8.2',
			'redirect_by' => '',
		],
		'expected' => [
			'status' => 'recommended',
		],
	],
	'testShouldReturnRecommendedWhenStatusIs404ButRedirectByWordPressHeaderPresent' => [
		'config'   => [
			'status'      => 404,
			'powered_by'  => '',
			'redirect_by' => 'WordPress',
		],
		'expected' => [
			'status' => 'recommended',
		],
	],
	'testShouldReturnGoodWhenStatusIs200WithValidJson'  => [
		'config'   => [
			'status' => 200,
			'body'   => '{"resource":"https://example.org"}',
		],
		'expected' => [
			'status' => 'good',
		],
	],
	'testShouldReturnRecommendedWhenStatusIs200WithInvalidJson' => [
		'config'   => [
			'status' => 200,
			'body'   => 'not json at all',
		],
		'expected' => [
			'status' => 'recommended',
		],
	],
	'testShouldReturnRecommendedWhenStatusIsUnexpected' => [
		'config'   => [
			'status' => 500,
			'body'   => '',
		],
		'expected' => [
			'status' => 'recommended',
		],
	],
];
