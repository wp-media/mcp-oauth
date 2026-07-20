<?php

return [
	'testShouldBuildPinForIpv4'            => [
		'config'   => [
			'host' => 'example.com',
			'ip'   => '93.184.216.34',
		],
		'expected' => [
			'pin' => 'example.com:443:93.184.216.34',
		],
	],
	'testShouldBuildPinForIpv6Unbracketed' => [
		'config'   => [
			'host' => 'example.com',
			'ip'   => '2606:2800:220:1:248:1893:25c8:1946',
		],
		'expected' => [
			'pin' => 'example.com:443:2606:2800:220:1:248:1893:25c8:1946',
		],
	],
];
