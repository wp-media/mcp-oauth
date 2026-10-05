<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Bootstrap;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Composer\Autoload\ClassLoader;
use ReflectionClass;
use WP\MCP\Core\McpAdapter;
use WPMedia\MCP\OAuth\Bootstrap;
use WPMedia\PHPUnit\Unit\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Bootstrap::register
 *
 * @covers \WPMedia\MCP\OAuth\Bootstrap::register
 */
class RegisterTest extends TestCase {

	/**
	 * PSR-4 prefix of the MCP Adapter classes.
	 */
	private const ADAPTER_PREFIX = 'WP\\MCP\\';

	/**
	 * Composer loader that maps the adapter prefix, and its original paths.
	 *
	 * @var array{0: ClassLoader, 1: string[]}|null
	 */
	private $adapter_mapping;

	/**
	 * Hides the MCP Adapter classes from the autoloader.
	 *
	 * @return void
	 */
	protected function set_up() {
		parent::set_up();

		foreach ( ClassLoader::getRegisteredLoaders() as $loader ) {
			$prefixes = $loader->getPrefixesPsr4();

			if ( isset( $prefixes[ self::ADAPTER_PREFIX ] ) ) {
				$this->adapter_mapping = [ $loader, $prefixes[ self::ADAPTER_PREFIX ] ];
				$loader->setPsr4( self::ADAPTER_PREFIX, [] );
			}
		}
	}

	/**
	 * Restores the MCP Adapter autoload mapping.
	 *
	 * @return void
	 */
	protected function tear_down() {
		if ( null !== $this->adapter_mapping ) {
			$this->adapter_mapping[0]->setPsr4( self::ADAPTER_PREFIX, $this->adapter_mapping[1] );
		}

		parent::tear_down();
	}

	/**
	 * Wires no hook and stays uninitialized when the MCP Adapter is not loaded.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldWireNothingWhenAdapterIsMissing( array $config, array $expected ): void {
		$this->assertFalse(
			class_exists( McpAdapter::class ),
			'Precondition: the MCP Adapter must not be loadable (an optimized classmap defeats the PSR-4 removal).'
		);

		foreach ( $expected['actions'] as $hook ) {
			Actions\expectAdded( $hook )->never();
		}

		foreach ( $expected['filters'] as $hook ) {
			Filters\expectAdded( $hook )->never();
		}

		$ref         = new ReflectionClass( Bootstrap::class );
		$bootstrap   = $ref->newInstanceWithoutConstructor();
		$initialized = $ref->getProperty( 'initialized' );
		if ( PHP_VERSION_ID < 80100 ) {
			$initialized->setAccessible( true );
		}
		$initialized->setValue( null, $config['initialized'] );

		$bootstrap->register();

		$this->assertSame( $expected['initialized'], $initialized->getValue() );
	}
}
