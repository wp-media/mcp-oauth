<?php
/**
 * `{home}` and `{rest}` stand for home_url() and get_rest_url( null, 'mcp/mcp-oauth-server' ):
 * the Unit test mocks them, the Integration test reads them from WordPress.
 */

return [
	'testShouldReturnProtectedResourceDocument'   => [
		'config'   => [
			'document' => 'protected-resource',
		],
		'expected' => [
			'resource'                 => '{rest}',
			'authorization_servers'    => [ '{home}' ],
			'bearer_methods_supported' => [ 'header' ],
			'scopes_supported'         => [ 'mcp' ],
		],
	],
	'testShouldReturnAuthorizationServerDocument' => [
		'config'   => [
			'document' => 'authorization-server',
		],
		'expected' => [
			'issuer'                                => '{home}',
			'authorization_endpoint'                => '{home}/oauth/authorize',
			'token_endpoint'                        => '{home}/oauth/token',
			'revocation_endpoint'                   => '{home}/oauth/revoke',
			'response_types_supported'              => [ 'code' ],
			'grant_types_supported'                 => [ 'authorization_code', 'refresh_token' ],
			'code_challenge_methods_supported'      => [ 'S256' ],
			'scopes_supported'                      => [ 'mcp' ],
			'token_endpoint_auth_methods_supported' => [ 'none' ],
			'client_id_metadata_document_supported' => true,
			'authorization_response_iss_parameter_supported' => true,
		],
	],
	'testShouldReturnNullForUnknownDocument'      => [
		'config'   => [
			'document' => 'unknown-document',
		],
		'expected' => null,
	],
	'testShouldReturnNullForEmptyDocument'        => [
		'config'   => [
			'document' => '',
		],
		'expected' => null,
	],
];
