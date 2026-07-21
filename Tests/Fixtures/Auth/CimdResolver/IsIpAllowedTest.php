<?php

return [
	'testShouldAllowPublicIpv4'                     => [
		'config'   => [ 'ip' => '93.184.216.34' ],
		'expected' => [ 'allowed' => true ],
	],
	'testShouldAllowPublicIpv6'                     => [
		'config'   => [ 'ip' => '2606:2800:220:1:248:1893:25c8:1946' ],
		'expected' => [ 'allowed' => true ],
	],
	'testShouldRejectPrivate10'                     => [
		'config'   => [ 'ip' => '10.0.0.5' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectPrivate172'                    => [
		'config'   => [ 'ip' => '172.16.5.4' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectPrivate192168'                 => [
		'config'   => [ 'ip' => '192.168.1.1' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectLoopbackIpv4'                  => [
		'config'   => [ 'ip' => '127.0.0.1' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectLinkLocalIpv4'                 => [
		'config'   => [ 'ip' => '169.254.1.1' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectCgnat'                         => [
		'config'   => [ 'ip' => '100.64.0.1' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectThisNetwork'                   => [
		'config'   => [ 'ip' => '0.0.0.1' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectIetfProtocol'                  => [
		'config'   => [ 'ip' => '192.0.0.1' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectLoopbackIpv6'                  => [
		'config'   => [ 'ip' => '::1' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectUnspecifiedIpv6'               => [
		'config'   => [ 'ip' => '::' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectLinkLocalIpv6'                 => [
		'config'   => [ 'ip' => 'fe80::1' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectUlaIpv6'                       => [
		'config'   => [ 'ip' => 'fc00::1' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectIpv4MappedPrivate'             => [
		'config'   => [ 'ip' => '::ffff:10.0.0.1' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectIpv4MappedLoopback'            => [
		'config'   => [ 'ip' => '::ffff:127.0.0.1' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldRejectNat64EmbeddedPrivate'          => [
		// NAT64 64:ff9b::/96 embedding 10.0.0.1.
		'config'   => [ 'ip' => '64:ff9b::a00:1' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldAllowNat64EmbeddedPublic'            => [
		// NAT64 64:ff9b::/96 embedding the public 8.8.8.8.
		'config'   => [ 'ip' => '64:ff9b::808:808' ],
		'expected' => [ 'allowed' => true ],
	],
	'testShouldReject6to4EmbeddedPrivate'           => [
		// 6to4 2002::/16 embedding 10.0.0.1 (bytes 2-5).
		'config'   => [ 'ip' => '2002:0a00:0001::1' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldAllow6to4EmbeddedPublic'             => [
		// 6to4 2002::/16 embedding the public 8.8.8.8 (bytes 2-5).
		'config'   => [ 'ip' => '2002:0808:0808::1' ],
		'expected' => [ 'allowed' => true ],
	],
	'testShouldRejectIpv4CompatibleEmbeddedPrivate' => [
		// Deprecated IPv4-compatible ::/96 embedding 10.0.0.1.
		'config'   => [ 'ip' => '::a00:1' ],
		'expected' => [ 'allowed' => false ],
	],
	'testShouldAllowIpv4CompatibleEmbeddedPublic'   => [
		// Deprecated IPv4-compatible ::/96 embedding the public 8.8.8.8.
		'config'   => [ 'ip' => '::808:808' ],
		'expected' => [ 'allowed' => true ],
	],
	'testShouldRejectMalformedString'               => [
		'config'   => [ 'ip' => 'not-an-ip' ],
		'expected' => [ 'allowed' => false ],
	],
];
