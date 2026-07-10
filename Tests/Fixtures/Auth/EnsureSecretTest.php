<?php

return [
	'testShouldNotOverwriteExistingSecret' => [
		'config'   => [
			'existing_option'   => '113920fc93346d66663c5bc1d81562cc6b99c3c5516b97f36a8d5f400a2e13e3',
			'add_option_result' => null,
		],
		'expected' => [
			'expects_add_option' => false,
		],
	],
	'testShouldCreateSecretWhenNoneExists' => [
		'config'   => [
			'existing_option'   => '',
			'add_option_result' => true,
		],
		'expected' => [
			'expects_add_option' => true,
		],
	],
];
