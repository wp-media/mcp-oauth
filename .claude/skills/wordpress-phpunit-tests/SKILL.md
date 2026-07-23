---
name: wordpress-phpunit-tests
description: >
  Write PHPUnit unit or integration tests for a WordPress plugin class or method. Decides unit vs
  integration based on whether any WordPress function is called in the method's call chain — pure
  PHP uses Brain\Monkey + Mockery (unit); anything touching WordPress uses WP_UnitTestCase
  (integration). Use when asked to add or fix tests for changed PHP code in a WordPress plugin.
---

# WordPress PHPUnit Tests

Write tests that follow the project's existing patterns precisely. Never invent a pattern when an
existing one already exists in the repo — read a comparable test first and mirror it.

This skill is vendor-neutral: test namespaces, base classes, config paths, and the base branch are
resolved per **Project conventions**, not hardcoded.

## Project conventions (resolve before writing)

Resolve each in order, first hit wins:

1. **`AGENTS.md` / `CLAUDE.md`** — base branch, unit/integration base classes & namespaces,
   PHPUnit config paths, stubs location, fixture/data-provider helper.
2. **Repo docs** — testing/contributing notes.
3. **The code itself**:
   - **Base branch** → `git symbolic-ref refs/remotes/origin/HEAD`, or the common `develop`/`trunk`/`main`.
   - **Base classes / namespaces** → read an existing test under `tests/Unit` and `tests/Integration`.
   - **PHPUnit config** → `phpunit.xml*`, `tests/**/phpunit.xml*`, or `composer.json` scripts.
   - **Stubs** → an existing `tests/Unit/stubs/` (or equivalent) directory.

## Before writing any tests

1. Diff against the base branch to see what changed:
   `git diff <base-branch>...HEAD -- '*.php'`.
2. From the diff, list every function/method **added or modified**, with file path and line numbers.
   **These are the only targets for new tests** — do not test untouched functions.
3. If functional context is provided (e.g. a PR description, issue, ticket, acceptance criteria), read it to understand the intended behaviors so we ensure the tests cover them.
4. Read each changed file in full for context.
5. For each changed function, **trace its call chain** to determine whether any WordPress function
   is invoked anywhere in that chain. This single fact decides unit vs integration.
6. Find an existing test for a similar class and read it in full — it is your style reference for
   namespace, base class, and structure.
7. Check the stubs directory for available stubs (unit tests only).

## Unit vs integration decision

- **Integration test** — when the method under test, or any function in its call chain, calls a
  WordPress function (`get_option`, `wp_insert_post`, `update_post_meta`, `sanitize_text_field`,
  `current_user_can`, `WP_Query`, `$wpdb->*`, …). WordPress must be loaded; the interaction is real.
  Faking WordPress at this depth with mocks is fragile.
- **Unit test** — when the method and its entire call chain are pure PHP, with no WordPress functions
  at any level. Brain\Monkey + Mockery is appropriate.

If in doubt, prefer an integration test. A slow passing test beats a fast wrong one.

## Unit tests (Brain\Monkey + Mockery)

Conventions (resolve the concrete namespace/base class from the repo's existing unit tests):

- **Location**: mirror the source path under the repo's unit test root (commonly `tests/Unit/`).
- **Namespace / base class**: match the repo's existing unit tests (e.g. a project `TestCase` base
  that boots Brain\Monkey in `setUp`/`tearDown`). Do not assume a specific namespace.
- **File header**: `declare(strict_types=1);` immediately after `<?php`.
- **`@covers`**: annotate the class or method under test.

Mocking rules:

- **WordPress functions** → Brain\Monkey:
  ```php
  use function Brain\Monkey\Functions\when;
  use function Brain\Monkey\Functions\expect;

  when( 'sanitize_text_field' )->returnArg( 1 );
  expect( 'current_user_can' )->once()->andReturn( true );
  ```
- **Collaborator classes** (services, runners, controllers) → Mockery:
  ```php
  $runner = Mockery::mock( My_Runner::class );
  $runner->shouldReceive( 'run' )->once()->with( $args )->andReturn( $result );
  ```
- **Never** mock the class under test itself.
- If a method calls a controller directly, mock that controller — do not mock `rest_do_request`.

Stubs:

- Load stubs in `setUp()` via `require_once`, guarded by `class_exists`/`function_exists`.
- If a needed stub doesn't exist, create it in the repo's stubs directory following the existing
  `if ( ! class_exists( ... ) )` guard pattern. Ensure any stub you add is a **superset** of other
  stubs defining the same symbol, so an earlier-loaded narrower stub doesn't shadow it.

## Integration tests

- **Location**: mirror the source path under the repo's integration test root (see `AGENTS.md`).
- **Base class**: extend the **project base `TestCase`**, never `WP_UnitTestCase` directly. In this
  repo that is `WPMedia\MCP\OAuth\Tests\Integration\TestCase` (which itself builds on
  `WP_UnitTestCase`) — see `AGENTS.md` › Test conventions. No Brain\Monkey here; WordPress is real.
- **File header**: `declare(strict_types=1);`. **`@covers`**: annotate the target.
- Build fixtures with the WordPress factories: `self::factory()->post->create()`, `wp_insert_term()`,
  `wp_set_current_user()`, etc.
- `WP_UnitTestCase` wraps each test in a transaction; `parent::tear_down()` rolls back DB state.
- Only mock **external HTTP** (`add_filter( 'pre_http_request', ... )` or `WP_HTTP_TestCase`).

## Fixtures & data providers

For 3+ similar cases, prefer a data provider over repeated test methods.

- If the repo's test base provides a fixture/data-provider helper (e.g. a `configTestData()` that
  loads a fixture file mirroring the test path), use it — read an existing fixture to match the format.
- Otherwise use a standard PHPUnit `@dataProvider`. Each row: a case name → positional args
  (conventionally `config` input and `expected` output).

For 1–2 cases, inline the data in the test method.

## What to test (minimum coverage)

Prefer testing functional behavior over implementation details.

- **Happy path** — one case with valid input that asserts the full return shape.
- **Error paths** — every branch that throws or returns early.
- **Input normalization** — clamping, casting, sanitization, default logic in the method under test.
- **Dependency forwarding** — injected args/params are passed to collaborators unchanged.

Do **not** test: framework internals (WordPress core, Mockery), private/protected methods directly
(test through the public surface), or behavior already covered by a collaborator's own suite.

## Workflow

1. Diff against the base branch and gather context about expected behaviors; list every added/modified function with path + lines.
2. Read each changed file; trace each function's call chain → decide unit vs integration.
3. Read an analogous existing test as the style reference; resolve base class/namespace/config from it.
4. Determine needed stubs (unit only); create any missing ones.
5. For 3+ similar cases, set up a data provider (repo helper or standard `@dataProvider`).
6. Write the test file following the resolved conventions.
7. Run the tests using the repo's PHPUnit config(s) and `--filter <TestClassName>`. Discover the
   command from `composer.json` scripts or `phpunit.xml*` (e.g.
   `composer dump-autoload && vendor/bin/phpunit -c <config> --filter <TestClassName>`).
8. Fix failures and re-run until green.
9. Report: number of tests, number of assertions, pass/fail.
