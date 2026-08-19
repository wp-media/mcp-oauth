<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit;

use WPMedia\PHPUnit\Unit\TestCase as BaseTestCase;

/**
 * Shared base class for unit tests.
 *
 * The test-data config layer (`configTestData()` / `loadTestDataConfig()`, used as a data
 * provider by the per-method tests) lives in `WPMedia\PHPUnit\TestCaseTrait` as of
 * wp-media/phpunit v3.3, so it is inherited here rather than redefined.
 *
 * When a test needs to exercise code paths gated by `define()`-once constants (e.g. WP_DEBUG,
 * WP_DEBUG_LOG), see `Tests/Unit/Logging/McpLogger/LogTest.php` for the reference pattern:
 * `@runInSeparateProcess` + `@preserveGlobalState disabled` per data set, so a constant defined by
 * one row never leaks into another.
 */
abstract class TestCase extends BaseTestCase {
}
