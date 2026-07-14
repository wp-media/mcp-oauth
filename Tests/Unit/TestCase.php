<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit;

use ReflectionObject;
use WPMedia\PHPUnit\Unit\TestCase as BaseTestCase;

/**
 * Shared base class for unit tests.
 *
 * When a test needs to exercise code paths gated by `define()`-once
 * constants (e.g. WP_DEBUG, WP_DEBUG_LOG), see
 * `Tests/Unit/Logging/McpLogger/LogTest.php` for the reference pattern:
 * `@runInSeparateProcess` + `@preserveGlobalState disabled` per data set,
 * so a constant defined by one row never leaks into another.
 */
abstract class TestCase extends BaseTestCase {
	/**
	 * Configuration for the test data.
	 *
	 * @var array{'test_data'?: array<string, mixed>}
	 */
	protected $config;

	/**
	 * Setup method for the test case.
	 *
	 * @return void
	 */
	protected function set_up() {
		parent::set_up();

		if ( empty( $this->config ) ) {
			$this->loadTestDataConfig();
		}
	}

	/**
	 * Get the test data configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function configTestData(): array {
		if ( empty( $this->config ) ) {
			$this->loadTestDataConfig();
		}

		return isset( $this->config['test_data'] )
			? $this->config['test_data']
			: $this->config;
	}

	/**
	 * Load test data configuration.
	 *
	 * @return void
	 */
	protected function loadTestDataConfig(): void {
		$obj      = new ReflectionObject( $this );
		$filename = $obj->getFileName();

		if ( false === $filename ) {
			return;
		}

		$this->config = $this->getTestData( dirname( $filename ), basename( $filename, '.php' ) );
	}
}
