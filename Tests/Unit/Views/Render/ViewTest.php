<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Views\Render;

use WPMedia\MCP\OAuth\Tests\Unit\TestCase;
use WPMedia\MCP\OAuth\Views\Render;

/**
 * Tests for WPMedia\MCP\OAuth\Views\Render::view
 *
 * Points `$templates_dir` at WP-function-free fixture templates under
 * `Tests/Fixtures/Views/Render/templates/` so Render can be tested without
 * bootstrapping WordPress.
 *
 * @covers \WPMedia\MCP\OAuth\Views\Render::view
 */
class ViewTest extends TestCase {

	/**
	 * Renders per config, asserting output or a thrown exception.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldRenderViewAccordingToConfig( array $config, array $expected ): void {
		$render = new Render( dirname( __DIR__, 3 ) . '/Fixtures/Views/Render/templates/' );

		if ( null !== $expected['exception'] ) {
			$this->expectException( $expected['exception'] );
		}

		ob_start();
		try {
			$render->view( $config['view'], $config['data'] );
		} finally {
			$output = ob_get_clean();
		}

		if ( null === $expected['exception'] ) {
			$this->assertSame( $expected['output'], $output );
		}
	}
}
