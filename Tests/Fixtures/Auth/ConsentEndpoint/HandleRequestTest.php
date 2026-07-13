<?php

return [
	'get'     => [
		[ 'method' => 'GET' ],
		[ 'response' => 405 ],
	],
	'put'     => [
		[ 'method' => 'PUT' ],
		[ 'response' => 405 ],
	],
	'delete'  => [
		[ 'method' => 'DELETE' ],
		[ 'response' => 405 ],
	],
	'missing' => [
		[ 'method' => null ],
		[ 'response' => 405 ],
	],
];
