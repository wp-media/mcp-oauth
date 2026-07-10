<?php

return [
	'testShouldDoNothingWhenNoDiscoveryDocumentRequested'  => [
		'config'   => [
			'discovery'  => '',
			'is_enabled' => true,
		],
		'expected' => [
			'check_enabled' => false,
			'force_404'     => false,
			'sent_json'     => null,
		],
	],
	'testShouldForce404WhenDisabled'                       => [
		'config'   => [
			'discovery'  => 'protected-resource',
			'is_enabled' => false,
		],
		'expected' => [
			'check_enabled' => true,
			'force_404'     => true,
			'sent_json'     => null,
		],
	],
	'testShouldServeProtectedResourceDocumentWhenEnabled'  => [
		'config'   => [
			'discovery'  => 'protected-resource',
			'is_enabled' => true,
		],
		'expected' => [
			'check_enabled' => true,
			'force_404'     => false,
			'sent_json'     => [
				'resource'                 => 'https://example.org/wp-json/mcp/mcp-oauth-server',
				'authorization_servers'    => [ 'https://example.org' ],
				'bearer_methods_supported' => [ 'header' ],
				'scopes_supported'         => [ 'mcp' ],
			],
		],
	],
	'testShouldServeAuthorizationServerDocumentWhenEnabled' => [
		'config'   => [
			'discovery'  => 'authorization-server',
			'is_enabled' => true,
		],
		'expected' => [
			'check_enabled' => true,
			'force_404'     => false,
			'sent_json'     => [
				'issuer'                                => 'https://example.org',
				'authorization_endpoint'                => 'https://example.org/oauth/authorize',
				'token_endpoint'                        => 'https://example.org/oauth/token',
				'revocation_endpoint'                   => 'https://example.org/oauth/revoke',
				'response_types_supported'              => [ 'code' ],
				'grant_types_supported'                 => [ 'authorization_code', 'refresh_token' ],
				'code_challenge_methods_supported'      => [ 'S256' ],
				'scopes_supported'                      => [ 'mcp' ],
				'token_endpoint_auth_methods_supported' => [ 'none' ],
				'client_id_metadata_document_supported' => true,
			],
		],
	],
	'testShouldNotSendAnyDocumentForUnknownDiscoveryValue' => [
		'config'   => [
			'discovery'  => 'unknown-document',
			'is_enabled' => true,
		],
		'expected' => [
			'check_enabled' => true,
			'force_404'     => false,
			'sent_json'     => null,
		],
	],
];
