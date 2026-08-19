<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration;

use WPMedia\PHPUnit\Integration\TestCase as BaseTestCase;

/**
 * Shared base class for integration tests.
 *
 * The test-data config layer (`configTestData()` / `loadTestDataConfig()`, used as a data
 * provider by the per-method tests) lives in `WPMedia\PHPUnit\TestCaseTrait` as of
 * wp-media/phpunit v3.3, so it is inherited here rather than redefined.
 */
abstract class TestCase extends BaseTestCase {
}
