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

The OAuth server is enabled by default. Disable it with:

```php
add_filter( 'wpmedia_mcp_oauth_server_enabled', '__return_false' );
```

When disabled, all `/oauth/*` endpoints and `/.well-known/oauth-*` discovery
documents return `404`, and the MCP OAuth transport server is not registered.

### Trusted CIMD publishers

The trusted-publisher allowlist is a **trust signal**, not a gate. Any client
presenting a valid Client ID Metadata Document may complete the authorization
flow; the consent screen tells the user which tier the client is in — a client
matching a trusted publisher gets a green "Verified publisher" badge, and every
other client gets a prominent "not a verified publisher" warning above its
display name so the user makes an informed choice. Claude is bundled as a
trusted publisher by default. Add your own via:

```php
add_filter( 'wpmedia_mcp_oauth_trusted_publishers', function ( array $publishers ) {
	$publishers['my-client'] = [
		'client_ids' => [ 'https://example.com/oauth/client-metadata' ],
		'host'       => 'example.com',
	];

	return $publishers;
} );
```

A CIMD `client_id` URL must be an HTTPS URL served on the default port (443);
URLs carrying an explicit port (e.g. `https://example.com:8443/client-metadata`)
are rejected before any fetch. This lets the resolver pin the connection to a
validated IP as an anti-DNS-rebinding (SSRF) safeguard.

### Restoring the trusted-publisher hard gate

Unverified providers are allowed by default (`wpmedia_mcp_oauth_allow_untrusted_providers`
defaults to `true`). Sites that want only verified publishers to authorize can
restore the old hard-reject — unverified clients are then refused with a `400`
before consent:

```php
add_filter( 'wpmedia_mcp_oauth_allow_untrusted_providers', '__return_false' );
```

The filter must return a **real boolean**. A non-boolean return is reported via
`_doing_it_wrong()` and then coerced with a `(bool)` cast, so a truthy
non-boolean (e.g. `'no'`) leaves untrusted providers *allowed*, while a falsy one
(`''`, `0`, `null`) blocks them.

With the filter set to `false`, a `client_id` whose host is not on the allowlist
is refused with "Unknown OAuth client." before any fetch — including when a
record for it is already in the transient cache, since the host check runs before
the cache read. A `client_id` on an allowlisted host that does not match the
publisher's exact `client_ids` is refused with "This OAuth client is not a
verified publisher."

Prefer the canonical `wpmedia_mcp_oauth_allow_untrusted_providers` name. The
legacy `rocket_mcp_allow_untrusted_providers` alias is still honoured, but it
emits a `_deprecated_hook()` notice — on a site with `WP_DEBUG` and
`display_errors` enabled that notice can emit bytes mid-request on
`/oauth/authorize` and break the subsequent `wp_redirect()`.

### CIMD fetch rate limit

Resolving an unknown `client_id` costs one outbound HTTPS fetch, so the resolver
keeps a global budget of 30 fetches per minute. Only cache misses count against
it — an already-resolved `client_id` is served from its transient and is always
free. When the budget is exhausted, further cache-miss resolutions fail until the
window resets. Raise (or lower) the ceiling with:

```php
add_filter( 'wpmedia_mcp_oauth_cimd_fetch_limit', function ( int $max ) {
	return 100;
} );
```

The budget is global rather than per host or per client, so it is deliberately
coarse: a flood of unknown `client_id` URLs can delay a legitimate client whose
cached document has just expired. Raise the limit on sites that serve many
distinct MCP clients.

Because unverified providers are allowed by default, this shared budget is also
reachable by anonymous callers: a burst of untrusted `client_id` URLs can briefly
starve resolution of a trusted client whose cached document expires inside the
same window. The window self-heals within 60 seconds of the last allowed fetch;
per-tier counters are tracked as a follow-up.

Returning a value below 1 is a deliberate way to block every cache-miss fetch,
which disables resolution of any new `client_id`; already-cached clients are
unaffected, since cache hits never consult the budget.

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
- **`Auth\AuthorizeEndpoint`** — owns the trust *policy*: reads
  `wpmedia_mcp_oauth_allow_untrusted_providers`, passes it into the resolver,
  applies the hard-reject when untrusted providers are disallowed, and records the
  resulting trust signal in the state transient for the consent screen.
- **`Auth\CimdResolver`** — the fetch *mechanism*: dereferences a `client_id` URL
  into a normalised client record. `resolve( string $client_id, bool $allow_untrusted = false )`
  fails closed by default, so a caller that omits the flag keeps the allowlist gate.
  Carries the SSRF guards (URL-shape validation, connect-only preflight, IP-range
  validation, `CURLOPT_RESOLVE` pinning) and the global fetch budget.
- **`Auth\ClaudeClientVerifier`** — the trust *signal*: matches a fetched document
  against the trusted-publisher allowlist. Drives the consent-screen badge, and is a
  hard gate only when untrusted providers are disallowed.
- **`Auth\Discovery\Endpoints`** — serves the `/.well-known/oauth-protected-resource`
  and `/.well-known/oauth-authorization-server` RFC discovery documents.
- **`Auth\Discovery\HealthCheck`** — a WordPress Site Health `direct` test that
  self-checks both discovery documents and reports a combined status; see
  "Hosting: `.well-known` conflicts" below.
- **`Transport\ServerRegistrar`** — registers the MCP OAuth server (and, when
  needed, the shared `mcp-adapter` abilities) with `wordpress/mcp-adapter`.
- **`Context`** — the single `is_enabled()` gate consulted everywhere.
- **`Views\Render`** — generic view renderer. Loads a named template and
  executes it with `$data` in scope; used by `Auth\AuthorizeCallback` for the consent screen.

## Hosting: `.well-known` conflicts

The two RFC discovery documents are served via a WordPress rewrite rule
(`^\.well-known/oauth-(protected-resource|authorization-server)$`), registered
at `'top'` priority — the recommended pattern for competing with WordPress's
own default rewrite rules.

On some hosts (OVH, cPanel/AutoSSL, Plesk, and most managed-WP hosts are
common defaults), the web server itself provisions a **physical
`.well-known/acme-challenge/` directory** for Let's Encrypt auto-SSL, and
scopes that provisioning to the entire `.well-known/` path prefix — for
example an Apache `<Directory>`/`Alias` block with `AllowOverride None`, or an
Nginx `location` block matching the whole prefix rather than just
`acme-challenge/`. Once that happens, sibling paths under `.well-known/` —
including our two discovery documents, which don't physically exist on disk —
can 404 before Apache/Nginx ever hands the request to PHP. When that's the
case, `template_redirect` never fires, and **no WordPress-level code change
can fix this**: the interception happens in the web server, before WordPress's
rewrite engine runs at all. This is a known, unsolved WordPress core gap
([Trac #37201](https://core.trac.wordpress.org/ticket/37201), wontfix).

This library never touches pre-existing content under `.well-known/` (it has
no static-file-write fallback — writing into a directory a host already
manages is exactly what causes permission/ownership failures on other
plugins, e.g. the WooCommerce Stripe gateway's abandoned attempt at the same
thing). It also ships a Site Health self-check (`Auth\Discovery\HealthCheck`)
that surfaces a "MCP OAuth discovery documents" test under **Tools → Site
Health → Status**, which flags this exact failure mode with a `critical`
status when it detects the fingerprint of the confirmed bug (a bare 404 with
no WordPress-originated response header). The only real fix is a server-config
change, applied by whoever controls the host/vhost:

### Apache

Add this to your vhost config, or to `.htaccess` in the site root (**above**
WordPress's own `# BEGIN WordPress` block, so it is evaluated first) — it
re-enables rewriting only for the two OAuth discovery paths, leaving
`acme-challenge/` and everything else under `.well-known/` untouched:

```apache
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{REQUEST_URI} ^/\.well-known/oauth-(protected-resource|authorization-server)$
RewriteRule ^ /index.php [L]
</IfModule>
```

If your host scopes `.well-known/` with a `<Directory>`/`Alias` block that
sets `AllowOverride None`, you will additionally need that block changed (or
carved out) at the vhost level — a `.htaccess` rule alone cannot override it.
Only your host or sysadmin can make that change.

### Nginx

Nginx prioritizes an exact-match `location =` block over a broader prefix
match (e.g. `location ^~ /.well-known/`), so adding these two exact-match
blocks wins over a wider `.well-known/` interception without touching it:

```nginx
location = /.well-known/oauth-protected-resource {
    try_files $uri /index.php?$args;
}

location = /.well-known/oauth-authorization-server {
    try_files $uri /index.php?$args;
}
```

### Caveats

- **This must be applied by whoever controls the web-server config** (your
  host or sysadmin) — it cannot be delivered by WordPress or this library.
- **CDN/page cache:** if a CDN or page cache sits in front of the site, it may
  have already cached the 404 response for these paths. Purge it after
  applying the fix, or the discovery documents may still appear broken until
  the cache expires.
- **Loopback vs. external:** the Site Health self-check runs *from the server
  to itself*. On some hosts the site's own domain resolves internally and
  bypasses a CDN/WAF/reverse-proxy that all *external* traffic traverses, so a
  "Good" result there is necessary but not sufficient — it does not prove
  external clients can reach the documents. After applying the snippet above,
  also verify with an external `curl -i` request (e.g. from a different
  network) that both discovery documents return `HTTP 200` with valid JSON.

## Logging

MCP structured logging lives in `McpLogger` (`inc/Logging/`), the single
choke-point for all `[MCP]` log lines. All log output — including
security/audit lines such as refresh-token-reuse detection — is gated on
**both** `WP_DEBUG` and `WP_DEBUG_LOG` being enabled, matching WordPress
core's own behaviour of only redirecting `error_log()` output to
`wp-content/debug.log` when `WP_DEBUG` is true.

> **Operational note:** because audit logging shares this same gate,
> operators who need audit-trail visibility in production must enable
> **both** constants (not just `WP_DEBUG_LOG`). Understand that doing so
> also enables verbose WP debug logging generally.

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
