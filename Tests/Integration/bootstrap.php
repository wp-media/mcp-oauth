<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration;

define( 'WPMEDIA_MCP_OAUTH_PLUGIN_ROOT', dirname( dirname( __DIR__ ) ) . DIRECTORY_SEPARATOR );
define( 'WPMEDIA_MCP_OAUTH_TESTS_FIXTURES_DIR', dirname( __DIR__ ) . '/Fixtures' );
define( 'WPMEDIA_MCP_OAUTH_TESTS_DIR', __DIR__ );

tests_add_filter(
	'muplugins_loaded',
	function () {
		require_once WPMEDIA_MCP_OAUTH_PLUGIN_ROOT . 'vendor/autoload.php';

		// Load the MCP Adapter as a plugin, as on a real site; its classes come from our autoloader.
		// The path assumes the default vendor/ install (no composer/installers in the dev tree).
		define( 'WP_MCP_AUTOLOAD', false ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- the MCP Adapter's own constant.
		require_once WPMEDIA_MCP_OAUTH_PLUGIN_ROOT . 'vendor/wordpress/mcp-adapter/mcp-adapter.php';

		\WPMedia\MCP\OAuth\Bootstrap::instance();
	}
);
