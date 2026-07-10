# MCP OAuth

OAuth 2.1 + Client ID Metadata Document (CIMD) authentication layer for the
[`wordpress/mcp-adapter`](https://github.com/wordpress/mcp-adapter) package.

This library is designed to be embedded, via Composer, into one or more
WordPress plugins. It centralizes OAuth endpoint routing, `.well-known`
discovery documents, JWT-based MCP transport authentication, and MCP server
registration behind a single wiring point so that multiple consuming plugins
never register duplicate rewrite rules or duplicate MCP servers.

## Installation

```bash
composer require wp-media/mcp-oauth
```

## Usage

Boot the library from your plugin's main file. Calling it on `plugins_loaded`
is recommended; the hard requirement is that it runs no later than
`rest_api_init` priority 15 (when the MCP adapter registers its servers):

```php
add_action( 'plugins_loaded', static function () {
	\WPMedia\MCP\OAuth\Bootstrap::instance();
} );
```

`Bootstrap::instance()` is a singleton: if more than one plugin on the same
site calls it, only the first call wires the library (rewrite rules, OAuth
endpoint routing, discovery documents, and MCP server registration); every
later call returns the same instance and binds nothing further.

The OAuth server is disabled by default. Enable it with:

```php
add_filter( 'wpmedia_mcp_oauth_server_enabled', '__return_true' );
```

When disabled, all `/oauth/*` endpoints and `/.well-known/oauth-*` discovery
documents return `404`, and the MCP OAuth transport server is not registered.

### Trusted CIMD publishers

Only clients whose Client ID Metadata Document matches a known, trusted
publisher can complete the authorization flow. Claude is bundled as a trusted
publisher by default. Add your own via:

```php
add_filter( 'wpmedia_mcp_oauth_trusted_publishers', function ( array $publishers ) {
	$publishers['my-client'] = [
		'client_ids' => [ 'https://example.com/oauth/client-metadata' ],
		'host'       => 'example.com',
	];

	return $publishers;
} );
```

### Rewrite rules

Rewrite rules are flushed lazily and automatically the first time `init` runs
after installing or upgrading the library (tracked by an internal version
flag), so no activation hook is required. If your plugin flips the
`wpmedia_mcp_oauth_server_enabled` filter at runtime (e.g. from a settings
screen), call `Bootstrap::schedule_rewrite_flush()` afterwards so the rules
are re-flushed on the next request.

## Architecture

- **`Bootstrap`** — the single entry point. Hand-wires the object graph and
  binds every WordPress hook directly (`add_action`/`add_filter`).
- **`Auth\Router`** — dispatches `/oauth/{authorize,authorize-callback,token,consent,revoke}`
  to their respective endpoint handlers.
- **`Auth\Discovery\Endpoints`** — serves the `/.well-known/oauth-protected-resource`
  and `/.well-known/oauth-authorization-server` RFC discovery documents.
- **`Transport\ServerRegistrar`** — registers the MCP OAuth server (and, when
  needed, the shared `mcp-adapter` abilities) with `wordpress/mcp-adapter`.
- **`Context`** — the single `is_enabled()` gate consulted everywhere.

## Testing

```bash
composer run-tests        # unit + integration
composer test-unit
composer test-integration
composer phpcs
composer phpstan
```

## License

GPL-2.0-or-later
