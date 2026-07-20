<?php
declare(strict_types=1);

namespace WPMedia\MCP\OAuth;

class Context {
	/**
	 * Determines whether the MCP OAuth server is enabled.
	 *
	 * @return bool True when the OAuth server (rewrite rules, endpoints,
	 *              discovery documents, transport) should be registered; false otherwise.
	 */
	public function is_enabled(): bool {
		$enabled = apply_filters_deprecated( 'rocket_mcp_oauth_server_enabled', [ true ], '1.0.1', 'wpmedia_mcp_oauth_server_enabled' );

		/**
		 * Filters whether the MCP OAuth server is enabled.
		 *
		 * When `false`, the host plugin does not register the OAuth rewrite rules
		 * or respond to any /oauth/* endpoint or /.well-known discovery request,
		 * and does not register the MCP OAuth transport server.
		 *
		 * @param bool $enabled Whether the MCP OAuth server is enabled. Default true.
		 */
		return wpm_apply_filters_typed( 'boolean', 'wpmedia_mcp_oauth_server_enabled', $enabled );
	}
}
