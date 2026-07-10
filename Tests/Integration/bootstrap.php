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
		\WPMedia\MCP\OAuth\Bootstrap::instance();
	}
);
