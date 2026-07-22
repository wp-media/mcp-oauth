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

Run tests inside the containers (avoids host/container drift — but note the container PHP
is **not** identical to every CI job; see "PHP version matrix" below before relying on a green
local run):
```
npx wp-env run tests-cli --env-cwd=wp-content/plugins/mcp-oauth bash -c "composer install --no-interaction"
npx wp-env run tests-cli --env-cwd=wp-content/plugins/mcp-oauth bash -c "composer test-integration"
npx wp-env run tests-cli --env-cwd=wp-content/plugins/mcp-oauth bash -c "composer test-unit"
```

Requires a reasonably current `git` (>= 2.25) on `$PATH` — `wp-env` uses `git sparse-checkout`
to fetch the WordPress core test suite. If `wp-env start` fails with
`git: 'sparse-checkout' is not a git command`, update `git` or make sure a newer install takes
precedence on `$PATH`.

**Fast inner loop:** unit tests use Brain\Monkey and need only PHP + composer — no WordPress,
no MySQL, no `wp-env` boot. While iterating, run just the affected unit test
(`composer test-unit -- --filter <TestClass>`); run the full `test-integration` + `phpcs` +
`phpstan` once before committing. Keep `wp-env` running across a task rather than re-booting
per run.

### Repository & branches

- Platform / repo: **GitHub — `wp-media/mcp-oauth`**. Package: composer library, PSR-4 root
  `WPMedia\MCP\OAuth\` under `inc/`.
- **Base branch for PRs is `main`** — the GitHub default; `master` has been deleted from the
  remote. Some long-lived local clones still carry a stale `refs/remotes/origin/HEAD → master`
  pointer from before that change, which makes git-based default-branch detection report
  `master`. Fix a stale clone once with `git remote set-head origin -a && git fetch --prune`
  (fresh clones already resolve to `main`).
- Before creating a branch, confirm local `main` is not stale:
  `git fetch origin main && git rev-parse HEAD origin/main` should match. (Branching off a
  stale local `main` silently bases the PR on an old commit.)
- Branch naming: `enhancement/<issue>-<slug>` (feature), `fix/<issue>-<slug>` (bug),
  `tests/<kebab-class>` (test-only). Open PRs as draft; apply the `Made by AI` label to
  AI-generated PRs.

### Composer commands (quick reference)

| Task | Command | Needs WP/DB? |
|------|---------|--------------|
| Unit tests | `composer test-unit` | No (Brain\Monkey) |
| Integration tests | `composer test-integration` | Yes (real WP + MySQL) |
| Coding standards | `composer phpcs` (autofix: `composer phpcs:fix`) | No |
| Static analysis | `composer phpstan` (level 5) | No |
| Everything | `composer run-tests` | Yes |

### PHP version matrix (read before committing)

**Four PHP versions are in play and none of them is the support floor** — a change can be
green locally and in the PHPUnit job yet fail the phpcs/phpstan CI:

- **Support floor: PHP 7.4.** `composer.json` requires `php >=7.4`; `phpcs.xml.dist` enforces
  `PHPCompatibility` at `testVersion: 7.4-`. Do **not** use PHP 8.0+-only syntax/functions —
  e.g. `str_contains()`, `match`, enums, named arguments, nullsafe `?->`, `readonly`. Use
  `strpos()`/`stripos()` etc.
- **PHPUnit CI job:** pinned to **PHP 8.2** (`.github/workflows/phpunit.yml`).
- **phpcs + phpstan CI:** run via the shared `wp-media/workflows` reusable workflows with
  `php-version: 'latest'` → **currently PHP 8.5, and a moving target**. A new PHP release can
  introduce deprecations that fail phpcs CI with **no code change** (this is how
  `curl_close()`, deprecated in 8.5, surfaced). The `DeprecatedFunctions` sniff reflects on the
  *running* interpreter, so version-specific deprecations are invisible to an older local PHP.
- **Local `wp-env`:** default PHP (~8.3) — matches neither the PHPUnit job (8.2) nor the
  phpcs/phpstan `latest` (8.5).

Practical rule: before committing, run `phpcs` against **both** the 7.4 floor **and** the
current `latest` (e.g. via a throwaway `php:8.5-cli` container) to catch version-specific
deprecations the `wp-env` container cannot see.

### Test conventions

- **One test class per method under test**, mirroring the source path — e.g. a method on
  `inc/Auth/CimdResolver.php` → `Tests/{Unit|Integration}/Auth/CimdResolver/<Method>Test.php`,
  with its data provider in `Tests/Fixtures/Auth/CimdResolver/<Method>Test.php`.
- **Unit and/or integration is the implementer's call per method — both are NOT required.**
  Decide from the method's call chain: pure PHP with no WordPress calls → **unit** only
  (Brain\Monkey + Mockery); anything that touches WordPress → **integration**. Write both for
  the same method only when it has genuinely distinct pure-logic and WP-integrated behaviour
  worth isolating — not by default.
- **Extend the project base `TestCase`, not the framework class directly:**
  - Unit: extend `WPMedia\MCP\OAuth\Tests\Unit\TestCase`
    (→ `WPMedia\PHPUnit\Unit\TestCase`; Brain\Monkey + Mockery).
  - Integration: extend `WPMedia\MCP\OAuth\Tests\Integration\TestCase`
    (→ `WPMedia\PHPUnit\Integration\TestCase`, which builds on `WP_UnitTestCase`).
    Do **not** extend `WP_UnitTestCase` directly.
- **Naming:** test methods and their fixture keys follow
  `testShould{DoSomething}When{Condition}` (e.g. `testShouldRejectClientWhenPortIsExplicit`).
- Fixtures return `['config' => …, 'expected' => …]`, consumed via `@dataProvider configTestData` on the project base `TestCase`.

### Labels

- `Made by AI` (exists) — apply to AI-generated PRs.
- Issue lifecycle: `Ready for review` exists; **`In Progress` does not** — only add labels that
  exist, or create them first.

## Session learnings (project-specific gotchas)

- **`curl_setopt()` and `extension_loaded()` are not mockable** under this repo's Brain\Monkey
  unit setup (Patchwork redefinable-internals limitation). To unit-test code that calls them,
  isolate the native call behind a `protected` seam (e.g. `connect_and_get_ip()`) and
  partial-mock that seam; assert the exact args/string at the boundary rather than the native
  call itself.
- **`@runInSeparateProcess` does not reliably re-isolate `define()`d WP constants**
  (`ABSPATH`/`WPINC`) in the `wp-env` unit setup ("already defined") — prefer an integration
  test for logic that depends on those constants.
- **Editing byte-literal strings** (e.g. `"\x00\xff…"` prefixes) with automated edit tools is
  error-prone because `\x` escapes get reinterpreted — anchor edits on adjacent escape-free
  lines, or wrap byte literals in a named helper/constant.
- **Knowledge graph:** a symbol/dependency graph lives at
  `.claude/graph/dependency-graph.json`. Refresh it before symbol lookups with the
  knowledge-graph skill's builder (`build-graph.js`); it scans `inc/` and is incremental.
