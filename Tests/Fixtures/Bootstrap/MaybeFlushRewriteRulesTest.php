<?php

return [
	'testShouldFlushWhenOauthRuleMissingDespiteMatchingVersionFlag' => [
		'config'   => [
			'enabled'               => true,
			'permalink_structure'   => '/%postname%/',
			'register_oauth_rules'  => true,
			'initial_rewrite_rules' => [ '^foo$' => 'index.php?foo=1' ],
			'initial_flag'          => 'match',
		],
		'expected' => [
			'flush_count' => 1,
			'flag_after'  => 'match',
			'rule_after'  => true,
		],
	],
	'testShouldNotFlushWhenVersionFlagAndOauthRulePresent' => [
		'config'   => [
			'enabled'               => true,
			'permalink_structure'   => '/%postname%/',
			'register_oauth_rules'  => true,
			'initial_rewrite_rules' => [ '^oauth/authorize$' => 'index.php?mcp_oauth_endpoint=authorize' ],
			'initial_flag'          => 'match',
		],
		'expected' => [
			'flush_count' => 0,
			'flag_after'  => 'match',
			'rule_after'  => true,
		],
	],
	'testShouldNotFlushUnderPlainPermalinksWhenVersionFlagMatches' => [
		'config'   => [
			'enabled'               => true,
			'permalink_structure'   => '',
			'register_oauth_rules'  => false,
			'initial_rewrite_rules' => [],
			'initial_flag'          => 'match',
		],
		'expected' => [
			'flush_count' => 0,
			'flag_after'  => 'match',
			'rule_after'  => null,
		],
	],
	'testShouldNotFlushWhenServerDisabled'                 => [
		'config'   => [
			'enabled'               => false,
			'permalink_structure'   => '/%postname%/',
			'register_oauth_rules'  => true,
			'initial_rewrite_rules' => [],
			'initial_flag'          => 'missing',
		],
		'expected' => [
			'flush_count' => 0,
			'flag_after'  => 'missing',
			'rule_after'  => null,
		],
	],
];
