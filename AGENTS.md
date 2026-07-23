- If you encounter something surprising or confusing in this project, flag it as a comment.

## Operating principles

These apply to every AI agent and skill working in this repo, before any tool-specific guidance:

1. **Surface assumptions before building.** If the spec or codebase is ambiguous, state the
   assumption rather than silently guessing.
2. **Stop when requirements conflict.** If the issue, the spec, and the code disagree, surface the
   conflict instead of proceeding on a guess.
3. **Prefer the simplest correct solution.** A boring, obvious approach beats a clever one that
   adds risk.
4. **Touch only what you were asked to touch.** No drive-by refactors or unrelated renames — scope
   discipline is what makes a PR mergeable.
5. **Verify, don't infer.** "Seems right" never closes a task. Run the tests/tools; don't reason
   that the code works.

> This file is the authority the delivery-pipeline agents (`.claude/agents/`) and the delivery +
> WordPress skills (`.claude/skills/`) read at the start of their context. They derive
> repo/platform/branch from git and read everything else — commands, labels, conventions — from
> here. Keep repo-specific facts in **this file**, not in those templates: they are unversioned
> copies of the source plugins and get overwritten when the plugins update.

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
- **Protected branch:** never push directly to `main` or force-push it — always work on a
  `fix/…` / `enhancement/…` / `tests/…` branch and open a PR.
- **Commit format:** `type(scope): summary` (≤72 chars), `type` ∈
  `feat`/`fix`/`chore`/`refactor`/`test`/`docs`. Include the model trailer so a later regression can
  be traced to its author: `Co-Authored-By: <model> <noreply@anthropic.com>`.

### Composer commands (quick reference)

| Task | Command | Needs WP/DB? |
|------|---------|--------------|
| Unit tests | `composer test-unit` | No (Brain\Monkey) |
| Integration tests | `composer test-integration` | Yes (real WP + MySQL) |
| Coding standards | `composer phpcs` (autofix: `composer phpcs:fix`) | No |
| Static analysis | `composer phpstan` (level 5) | No |
| Install | `composer install` | No |
| Everything | `composer run-tests` | Yes |

> **Run these inside the wp-env containers** (not on the host) to avoid host/container drift — see
> *Local test environment* above. There is **no build step** and no JS lint (PHP library).
> Pipeline mapping: `implementer` and `dod` use `test-unit`/`test-integration`,
> `phpcs` (+`phpcs:fix`), and `phpstan` (level 5).

### PHP version matrix (read before committing)

CI exercises PHP across the full supported range, but your **local** `wp-env` runs a single
version — so a change can be green locally yet fail a CI leg on an older or newer PHP:

- **Support floor: PHP 7.4.** `composer.json` requires `php >=7.4`; `phpcs.xml.dist` enforces
  `PHPCompatibility` at `testVersion: 7.4-`. Do **not** use PHP 8.0+-only syntax/functions —
  e.g. `str_contains()`, `match`, enums, named arguments, nullsafe `?->`, `readonly`. Use
  `strpos()`/`stripos()` etc.
- **PHPUnit CI:** runs a **matrix across PHP 7.4 → 8.5** (7.4 floor through the current
  `latest`), one leg per version, `fail-fast: false` (`.github/workflows/phpunit.yml`). The
  floor and every intermediate version are covered — e.g. reflection code that needs
  `setAccessible()` on PHP < 8.1 is caught here (see Session learnings). Extend the `php:`
  matrix list when a new PHP version ships.
- **phpcs + phpstan CI:** run via the shared `wp-media/workflows` reusable workflows with
  `php-version: 'latest'` → **currently PHP 8.5, and a moving target**. A new PHP release can
  introduce deprecations that fail phpcs CI with **no code change** (this is how
  `curl_close()`, deprecated in 8.5, surfaced). The `DeprecatedFunctions` sniff reflects on the
  *running* interpreter, so version-specific deprecations are invisible to an older local PHP.
- **Local `wp-env`:** a single default PHP (~8.3) — neither the 7.4 floor nor the
  phpcs/phpstan `latest`, so a green local run proves neither end of the range.

Practical rule: before committing, run `phpcs` against **both** the 7.4 floor **and** the
current `latest` (e.g. via a throwaway `php:8.5-cli` container) to catch version-specific
deprecations the `wp-env` container cannot see. PHPUnit itself is now covered end-to-end by
the CI matrix.

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

## Project shape & WordPress conventions

Read by the WordPress engineering skills (they resolve these from this file first, then docs, then
the code):

- **This is a Composer library, not a distributable plugin.** No plugin header, no `readme.txt`, no
  WordPress.org submission, no activation/deactivation hooks. Skill guidance about plugin headers,
  the WP.org Plugin Check, or `readme.txt` does **not** apply.
- **No admin UI / JS / enqueue, and no `$wpdb`.** The only rendered output is the server-side
  consent screen (`inc/Views/`); the only direct persistence is `update_option`/`delete_option`
  (rewrite-version flag, secrets). Skill sections on admin client-side hygiene,
  React/`wp_localize_script`, N+1 `$wpdb` queries, and post-save/activation performance do not apply.
- **Access control is OAuth/JWT-scoped, not WordPress capabilities.** There is no
  `current_user_can`/capability map; the server is gated by JWT + the
  `wpmedia_mcp_oauth_server_enabled` filter (`Context::is_enabled()`), plus one REST
  `permission_callback` in `inc/Transport/OAuthHttpTransport.php`.
- **Request routing is rewrite-rule + `template_redirect`** (`inc/Bootstrap.php`,
  `inc/Auth/Router.php`), **not** REST controllers — the sole `register_rest_route` lives in
  `inc/Transport/OAuthHttpTransport.php`. Trace requests through the query-var/rewrite path, not
  `WP_REST_Controller::register_routes()`.
- **Hooks are wired directly** with `add_action`/`add_filter` in `inc/Bootstrap.php` — no
  Subscriber/ServiceProvider abstraction.
- **Text domain:** `mcp-oauth`. **Global prefix:** `wpmedia` / `wpmedia_mcp_oauth_`. **Object-cache
  group:** none. **PHPCS ruleset:** `phpcs.xml.dist` (WordPress + `PHPCompatibility testVersion 7.4-`,
  minimum WP 6.6).
- **WordPress floor is 6.6**, so guard 6.9-only APIs (e.g. `function_exists( 'wp_register_ability' )`).

## Pipeline conventions

- **Grooming — always HIGH risk regardless of effort:** any change to authentication, the OAuth
  flow, token/JWT issuance or validation, secret storage, or the consent/authorize/token endpoints.
  This is an auth library — treat its security surface as high-risk by default.
- **Review focus:** OAuth/JWT correctness, exact-string assertions at native-call seams, output
  escaping in `inc/Views/`, and the PHP 7.4 syntax floor (see *PHP version matrix*). Standard
  secure-coding checklist otherwise.
- **Local run / browser QA: none.** Headless library with no UI — `qa-engineer` does API/analysis
  validation only, and `e2e-qa-tester` (Playwright) has no surface here. Do not boot a browser or a
  dev server.

## Session learnings (project-specific gotchas)

- **`curl_setopt()` and `extension_loaded()` are not mockable** under this repo's Brain\Monkey
  unit setup (Patchwork redefinable-internals limitation). To unit-test code that calls them,
  isolate the native call behind a `protected` seam (e.g. `connect_and_get_ip()`) and
  partial-mock that seam; assert the exact args/string at the boundary rather than the native
  call itself.
- **`@runInSeparateProcess` does not reliably re-isolate `define()`d WP constants**
  (`ABSPATH`/`WPINC`) in the `wp-env` unit setup ("already defined") — prefer an integration
  test for logic that depends on those constants.
- **`ReflectionMethod` on a non-public method needs `setAccessible(true)` on PHP < 8.1.**
  From 8.1 it is a no-op (private members are always reflectable), so it is tempting to omit —
  but omitting it throws `ReflectionException: Trying to invoke private method …` on the 7.4/8.0
  matrix legs. Guard it: `if ( PHP_VERSION_ID < 80100 ) { $method->setAccessible( true ); }`.
- **Editing byte-literal strings** (e.g. `"\x00\xff…"` prefixes) with automated edit tools is
  error-prone because `\x` escapes get reinterpreted — anchor edits on adjacent escape-free
  lines, or wrap byte literals in a named helper/constant.
- **Knowledge graph:** a symbol/dependency graph lives at
  `.claude/graph/dependency-graph.json`. Refresh it before symbol lookups with the
  knowledge-graph skill's builder (`build-graph.js`); it scans `inc/` and is incremental.

## Known local template deviations

The agents/skills under `.claude/` are copies of the `gas-delivery-pipeline-templates` (2.0.0) and
`gas-wordpress-engineering` (0.1.0) plugins. A few were edited locally to match this repo;
**re-apply these after re-copying from an updated plugin** (or, better, fix them upstream):

- `.claude/skills/dod/SKILL.md` — base-branch guard defaults to `main` (was `develop`).
- `.claude/skills/orchestrator/SKILL.md` — test-only branch prefix is `tests/<kebab-class>` (was
  `test`); the `Ready for review` transition no longer tries to remove a non-existent `In Progress`
  label.
- `.claude/skills/wordpress-phpunit-tests/SKILL.md` — integration tests extend the project base
  `TestCase`, never `WP_UnitTestCase` directly.

Everything else repo-specific is carried by this file rather than edited into the templates.
