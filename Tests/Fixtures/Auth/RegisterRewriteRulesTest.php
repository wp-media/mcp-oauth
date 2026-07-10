<?php

return [
	'testShouldNotRegisterRewriteRulesWhenDisabled' => [
		'config'   => [
			'is_enabled' => false,
		],
		'expected' => [
			'should_register' => false,
		],
	],
	'testShouldRegisterRewriteRulesWhenEnabled'     => [
		'config'   => [
			'is_enabled' => true,
		],
		'expected' => [
			'should_register' => true,
		],
	],
];
