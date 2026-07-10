<?php

return [
	'testShouldCastStringUserIdAndObjectItem' => [
		'config'   => [
			'user_id' => '42',
			'item'    => (object) [ 'uuid' => 'abc-123' ],
		],
		'expected' => [
			'user_id' => 42,
			'item'    => [ 'uuid' => 'abc-123' ],
		],
	],
	'testShouldCastIntUserIdAndArrayItem'     => [
		'config'   => [
			'user_id' => 7,
			'item'    => [ 'uuid' => 'def-456' ],
		],
		'expected' => [
			'user_id' => 7,
			'item'    => [ 'uuid' => 'def-456' ],
		],
	],
];
