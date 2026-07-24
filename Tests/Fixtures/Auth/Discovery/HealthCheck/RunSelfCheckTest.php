<?php
/**
 * Data provider for HealthCheck::run_self_check (and, indirectly, classify()).
 *
 * Rows 6-13 each vary only the "protected-resource" document and pin
 * "authorization-server" to a 200/valid-JSON response, so the combined
 * (worst-of-two) status and the failing-document naming in build_result()
 * are attributable to the varied document alone.
 */

$wpmedia_mcp_oauth_test_good_document = [
	'status' => 200,
	'body'   => '{"ok":true}',
];

$wpmedia_mcp_oauth_test_cached_result = [
	'label'       => 'MCP OAuth discovery documents',
	'status'      => 'good',
	'badge'       => [
		'label' => 'Configuration',
		'color' => 'blue',
	],
	'description' => '<p>cached</p>',
	'actions'     => '<p>cached</p>',
	'test'        => 'wpmedia_mcp_oauth_wellknown_discovery',
];

return [
	'testShouldReportGoodWhenServerDisabled'             => [
		'config'   => [
			'is_enabled'          => false,
			'permalink_structure' => '/%postname%/',
			'cached'              => false,
			'documents'           => [],
		],
		'expected' => [
			'status'               => 'good',
			'description_contains' => 'disabled',
		],
	],
	'testShouldRecommendPermalinkFixWhenPlainPermalinks' => [
		'config'   => [
			'is_enabled'          => true,
			'permalink_structure' => '',
			'cached'              => false,
			'documents'           => [],
		],
		'expected' => [
			'status'               => 'recommended',
			'description_contains' => 'permalink',
		],
	],
	'testShouldServeCachedResultWithoutRefetching'       => [
		'config'   => [
			'is_enabled'          => true,
			'permalink_structure' => '/%postname%/',
			'cached'              => $wpmedia_mcp_oauth_test_cached_result,
			'documents'           => [],
		],
		'expected' => [
			'exact_result' => true,
		],
	],
	'testShouldReportGoodWhenBothDocumentsReturn200'     => [
		'config'   => [
			'is_enabled'          => true,
			'permalink_structure' => '/%postname%/',
			'cached'              => false,
			'documents'           => [
				'protected-resource'   => $wpmedia_mcp_oauth_test_good_document,
				'authorization-server' => $wpmedia_mcp_oauth_test_good_document,
			],
		],
		'expected' => [
			'status' => 'good',
			'badge'  => [
				'label' => 'Configuration',
				'color' => 'blue',
			],
		],
	],
	'testShouldReportRecommendedWhenBothDocumentsAreWpError' => [
		'config'   => [
			'is_enabled'          => true,
			'permalink_structure' => '/%postname%/',
			'cached'              => false,
			'documents'           => [
				'protected-resource'   => [ 'is_wp_error' => true ],
				'authorization-server' => [ 'is_wp_error' => true ],
			],
		],
		'expected' => [
			'status' => 'recommended',
		],
	],
	'testShouldReportRecommendedWhenStatusIs401'         => [
		'config'   => [
			'is_enabled'          => true,
			'permalink_structure' => '/%postname%/',
			'cached'              => false,
			'documents'           => [
				'protected-resource'   => [ 'status' => 401 ],
				'authorization-server' => $wpmedia_mcp_oauth_test_good_document,
			],
		],
		'expected' => [
			'status' => 'recommended',
		],
	],
	'testShouldReportRecommendedWhenStatusIs403'         => [
		'config'   => [
			'is_enabled'          => true,
			'permalink_structure' => '/%postname%/',
			'cached'              => false,
			'documents'           => [
				'protected-resource'   => [ 'status' => 403 ],
				'authorization-server' => $wpmedia_mcp_oauth_test_good_document,
			],
		],
		'expected' => [
			'status' => 'recommended',
		],
	],
	'testShouldReportRecommendedWhenStatusIs407'         => [
		'config'   => [
			'is_enabled'          => true,
			'permalink_structure' => '/%postname%/',
			'cached'              => false,
			'documents'           => [
				'protected-resource'   => [ 'status' => 407 ],
				'authorization-server' => $wpmedia_mcp_oauth_test_good_document,
			],
		],
		'expected' => [
			'status' => 'recommended',
		],
	],
	'testShouldReportCriticalWhenStatusIs404AndNoWordPressHeaderPresent' => [
		'config'   => [
			'is_enabled'          => true,
			'permalink_structure' => '/%postname%/',
			'cached'              => false,
			'documents'           => [
				'protected-resource'   => [
					'status'      => 404,
					'powered_by'  => '',
					'redirect_by' => '',
				],
				'authorization-server' => $wpmedia_mcp_oauth_test_good_document,
			],
		],
		'expected' => [
			'status'                   => 'critical',
			'description_contains'     => 'oauth-protected-resource',
			'description_not_contains' => 'oauth-authorization-server',
		],
	],
	'testShouldReportRecommendedWhenStatusIs404ButPoweredByHeaderPresent' => [
		'config'   => [
			'is_enabled'          => true,
			'permalink_structure' => '/%postname%/',
			'cached'              => false,
			'documents'           => [
				'protected-resource'   => [
					'status'      => 404,
					'powered_by'  => 'PHP/8.2',
					'redirect_by' => '',
				],
				'authorization-server' => $wpmedia_mcp_oauth_test_good_document,
			],
		],
		'expected' => [
			'status' => 'recommended',
		],
	],
	'testShouldReportRecommendedWhenStatusIs404ButRedirectByWordPressHeaderPresent' => [
		'config'   => [
			'is_enabled'          => true,
			'permalink_structure' => '/%postname%/',
			'cached'              => false,
			'documents'           => [
				'protected-resource'   => [
					'status'      => 404,
					'powered_by'  => '',
					'redirect_by' => 'WordPress',
				],
				'authorization-server' => $wpmedia_mcp_oauth_test_good_document,
			],
		],
		'expected' => [
			'status' => 'recommended',
		],
	],
	'testShouldReportRecommendedWhenStatusIs200WithInvalidJson' => [
		'config'   => [
			'is_enabled'          => true,
			'permalink_structure' => '/%postname%/',
			'cached'              => false,
			'documents'           => [
				'protected-resource'   => [
					'status' => 200,
					'body'   => 'not json at all',
				],
				'authorization-server' => $wpmedia_mcp_oauth_test_good_document,
			],
		],
		'expected' => [
			'status' => 'recommended',
		],
	],
	'testShouldReportRecommendedWhenStatusIsUnexpected'  => [
		'config'   => [
			'is_enabled'          => true,
			'permalink_structure' => '/%postname%/',
			'cached'              => false,
			'documents'           => [
				'protected-resource'   => [
					'status' => 500,
					'body'   => '',
				],
				'authorization-server' => $wpmedia_mcp_oauth_test_good_document,
			],
		],
		'expected' => [
			'status' => 'recommended',
		],
	],
];
