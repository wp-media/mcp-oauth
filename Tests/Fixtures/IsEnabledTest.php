<?php

return [
	'testShouldReturnDefaultWhenNoFilterOverridesIt'       => [
		'config'   => [
			'primary' => null,
			'legacy'  => null,
		],
		'expected' => [
			'result'          => false,
			'incorrect_usage' => false,
		],
	],
	'testShouldReturnTrueWhenPrimaryFilterEnablesIt'       => [
		'config'   => [
			'primary' => true,
			'legacy'  => null,
		],
		'expected' => [
			'result'          => true,
			'incorrect_usage' => false,
		],
	],
	'testShouldReturnTrueWhenLegacyFilterEnablesItAndPrimaryIsNotRegistered' => [
		'config'   => [
			'primary' => null,
			'legacy'  => true,
		],
		'expected' => [
			'result'          => true,
			'incorrect_usage' => false,
		],
	],
	'testShouldLetLegacyFilterOverridePrimaryFilterResult' => [
		'config'   => [
			'primary' => true,
			'legacy'  => false,
		],
		'expected' => [
			'result'          => false,
			'incorrect_usage' => false,
		],
	],
	'testShouldFallBackToDefaultWhenPrimaryFilterReturnsNonBoolean' => [
		'config'   => [
			'primary' => 'not-a-boolean',
			'legacy'  => null,
		],
		'expected' => [
			'result'          => false,
			'incorrect_usage' => true,
		],
	],
	'testShouldFallBackToPrimaryResultWhenLegacyFilterReturnsNonBoolean' => [
		'config'   => [
			'primary' => true,
			'legacy'  => 'not-a-boolean',
		],
		'expected' => [
			'result'          => true,
			'incorrect_usage' => true,
		],
	],
];
