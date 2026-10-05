<?php

return [
	'testShouldWireNothingWhenAdapterIsMissing' => [
		'config'   => [
			'initialized' => false,
		],
		'expected' => [
			'actions'     => [
				'init',
				'template_redirect',
				'wp_delete_application_password',
				'wp_abilities_api_categories_init',
				'wp_abilities_api_init',
				'mcp_adapter_init',
			],
			'filters'     => [
				'query_vars',
				'site_status_tests',
			],
			'initialized' => false,
		],
	],
];
