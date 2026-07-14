<?php

return [
	'testShouldReturnExistingSecretWithoutGenerating'   => [
		'config'   => [
			'existing_option'   => '9e1658d55b3d45617f1e2c854ade9887d319986153a350a978a51ab0138363cf',
			'add_option_result' => null,
			'readback_value'    => null,
		],
		'expected' => [
			'expects_add_option' => false,
			'expects_readback'   => false,
			'returns_existing'   => true,
		],
	],
	'testShouldGenerateAndStoreNewSecretWhenNoneExists' => [
		'config'   => [
			'existing_option'   => '',
			'add_option_result' => true,
			'readback_value'    => null,
		],
		'expected' => [
			'expects_add_option' => true,
			'expects_readback'   => false,
			'returns_existing'   => false,
		],
	],
	'testShouldReadBackSecretWhenAddOptionLosesTheRace' => [
		'config'   => [
			'existing_option'   => '',
			'add_option_result' => false,
			'readback_value'    => 'a484d63fc586aa8acd0d2b8b369a8bf2f08a8d35d24947e84ea051d5137c62ba',
		],
		'expected' => [
			'expects_add_option' => true,
			'expects_readback'   => true,
			'returns_existing'   => true,
		],
	],
];
