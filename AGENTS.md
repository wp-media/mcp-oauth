- If you encounter something surprising or confusing in this project, flag it as a comment.

## Project Configuration

### Local test environment (Docker)

This repo uses `@wordpress/env` (`wp-env`) for a dockerized WordPress + MySQL + PHPUnit
environment — same convention as sibling wp-media/group.one projects (e.g. wp-rocket).

Setup (one-time): `npm install`, then `npx wp-env start`. This starts WordPress dev/test
sites (default ports 8888/8889) plus MySQL and CLI containers. Config lives in
`.wp-env.json`, committed and shared by everyone. The repo is bind-mounted — not registered
as a WP plugin, since it's a library with no plugin header — at
`wp-content/plugins/mcp-oauth` inside the containers via the `mappings` key, so `wp-env`'s
auto plugin-activation step is intentionally skipped.

If default ports 8888/8889 conflict with another `wp-env` project already running on your
machine, create a `.wp-env.override.json` (gitignored, per-developer, not committed) with
your own `port` / `env.tests.port` values — `wp-env` merges it automatically.

Run tests inside the containers (matches CI's PHP version, avoids host/container drift):
```
npx wp-env run tests-cli --env-cwd=wp-content/plugins/mcp-oauth bash -c "composer install --no-interaction"
npx wp-env run tests-cli --env-cwd=wp-content/plugins/mcp-oauth bash -c "composer test-integration"
npx wp-env run tests-cli --env-cwd=wp-content/plugins/mcp-oauth bash -c "composer test-unit"
```

Requires a reasonably current `git` (>= 2.25) on `$PATH` — `wp-env` uses `git sparse-checkout`
to fetch the WordPress core test suite. If `wp-env start` fails with
`git: 'sparse-checkout' is not a git command`, update `git` or make sure a newer install takes
precedence on `$PATH`.
