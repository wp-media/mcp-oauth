<?php

return [
	'testShouldAllowPublicIpv4'          => [
		'config'   => [ 'ip' => '93.184.216.34' ],
		'expected' => [ 'allowed' => true ],
	],
	'testShouldAllowPublicIpv6'          => [
		'config'   => [ 'ip' => '2606:2800:220:1:248:1893:25c8:1946' ],
		'expected' => [ 'allowed' => true ],
	],
	'testShouldRejectPrivate10'          => [
		'config'   => [ 'ip' => '10.0.0.5' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectPrivate172'         => [
		'config'   => [ 'ip' => '172.16.5.4' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectPrivate192168'      => [
		'config'   => [ 'ip' => '192.168.1.1' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectLoopbackIpv4'       => [
		'config'   => [ 'ip' => '127.0.0.1' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectLinkLocalIpv4'      => [
		'config'   => [ 'ip' => '169.254.1.1' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectCgnat'              => [
		'config'   => [ 'ip' => '100.64.0.1' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectThisNetwork'        => [
		'config'   => [ 'ip' => '0.0.0.1' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectIetfProtocol'       => [
		'config'   => [ 'ip' => '192.0.0.1' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectLoopbackIpv6'       => [
		'config'   => [ 'ip' => '::1' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectUnspecifiedIpv6'    => [
		'config'   => [ 'ip' => '::' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectLinkLocalIpv6'      => [
		'config'   => [ 'ip' => 'fe80::1' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectUlaIpv6'            => [
		'config'   => [ 'ip' => 'fc00::1' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectIpv4MappedPrivate'  => [
		'config'   => [ 'ip' => '::ffff:10.0.0.1' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectIpv4MappedLoopback' => [
		'config'   => [ 'ip' => '::ffff:127.0.0.1' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectMalformedString'    => [
		'config'   => [ 'ip' => 'not-an-ip' ],
		'expected' => [ 'allowed' => false ],
	],
];
