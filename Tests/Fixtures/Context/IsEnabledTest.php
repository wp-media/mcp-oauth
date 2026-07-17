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
			'deprecated'      => false,
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
			'deprecated'      => false,
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
			'deprecated'      => true,
		],
	],
	'testShouldLetPrimaryFilterOverrideLegacyFilterResult' => [
		'config'   => [
			'primary' => true,
			'legacy'  => false,
		],
		'expected' => [
			'result'          => true,
			'incorrect_usage' => false,
			'deprecated'      => true,
		],
	],
	'testShouldLetPrimaryFilterDisableWhatTheLegacyFilterEnabled' => [
		'config'   => [
			'primary' => false,
			'legacy'  => true,
		],
		'expected' => [
			'result'          => false,
			'incorrect_usage' => false,
			'deprecated'      => true,
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
			'deprecated'      => false,
		],
	],
	'testShouldIgnoreNonBooleanLegacyValueWhenPrimaryFilterWins' => [
		'config'   => [
			'primary' => true,
			'legacy'  => 'not-a-boolean',
		],
		'expected' => [
			'result'          => true,
			'incorrect_usage' => false,
			'deprecated'      => true,
		],
	],
];
