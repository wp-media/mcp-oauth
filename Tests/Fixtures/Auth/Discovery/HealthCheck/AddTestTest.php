<?php
/**
 * Data provider for HealthCheck::add_test (unit test only).
 *
 * The integration AddTestTest exercises a different scenario (a real
 * WP_Site_Health round-trip) with an incompatible config/expected shape, and
 * cannot have its own fixture at this path — see that test's docblock.
 */

return [
	'testShouldRegisterExactlyOneDirectSiteHealthTestWhenNoneExist' => [
		'config'   => [
			'existing' => [
				'direct' => [],
				'async'  => [],
			],
		],
		'expected' => [
			'preserved' => [
				'direct' => [],
				'async'  => [],
			],
		],
	],
	'testShouldPreserveExistingTestsWhenAddingItsOwn' => [
		'config'   => [
			'existing' => [
				'direct' => [
					'php_version' => [
						'label' => 'PHP Version',
						'test'  => 'php_version',
					],
				],
				'async'  => [
					'https_status' => [
						'label' => 'HTTPS status',
						'test'  => 'https://example.org/wp-json/wp-site-health/v1/tests/https-status',
					],
				],
			],
		],
		'expected' => [
			'preserved' => [
				'direct' => [ 'php_version' ],
				'async'  => [ 'https_status' ],
			],
		],
	],
];
