<?php

return [
	'testShouldReplaceExistingSecretWithFreshValue' => [
		'config'   => [
			'existing_option' => '2505a7e5a3e38962f58a4f512071ad93f8b5bd4d9fe19a222ad514aead85ae43',
		],
		'expected' => [],
	],
	'testShouldCreateFreshSecretWhenNoneExisted'    => [
		'config'   => [
			'existing_option' => '',
		],
		'expected' => [],
	],
];
