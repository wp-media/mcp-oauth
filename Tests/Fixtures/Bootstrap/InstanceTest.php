<?php

return [
	'testShouldDeferWiringToPluginsLoadedWhenBootedBeforeIt' => [
		'config'   => [],
		'expected' => [
			'register_priority'      => 0,
			'ensure_secret_priority' => 5,
			'flush_priority'         => 20,
		],
	],
];
