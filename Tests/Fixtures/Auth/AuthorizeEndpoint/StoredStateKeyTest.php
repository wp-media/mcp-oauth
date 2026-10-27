<?php

return [
	'testShouldReturnHashedKeyWhenStoredUnderIt'   => [
		'config'   => [ 'stored_as' => 'hashed' ],
		'expected' => [ 'key' => 'hashed' ],
	],
	'testShouldReturnLegacyKeyWhenStoredUnderIt'   => [
		'config'   => [ 'stored_as' => 'legacy' ],
		'expected' => [ 'key' => 'legacy' ],
	],
	'testShouldReturnHashedKeyWhenNothingIsStored' => [
		'config'   => [ 'stored_as' => null ],
		'expected' => [ 'key' => 'hashed' ],
	],
];
