<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Unit\Views\Render;

use WPMedia\MCP\OAuth\Tests\Unit\TestCase;
use WPMedia\MCP\OAuth\Views\Render;

/**
 * Tests for WPMedia\MCP\OAuth\Views\Render::view
 *
 * Exercises Render entirely in isolation from WordPress by pointing the
 * injectable `$templates_dir` constructor parameter at the WP-function-free
 * fixture templates under `Tests/Fixtures/Views/Render/templates/`, instead
 * of the real `inc/Views/templates/` (which calls WordPress functions — that
 * template is covered as integration-only via
 * `Tests/Integration/Auth/AuthorizeCallback/HandleRequestTest.php`).
 *
 * This class's own `@dataProvider configTestData` fixture lives at
 * `Tests/Fixtures/Views/Render/ViewTest.php` — a plain config/expected array,
 * per the base `Tests/Unit/TestCase::loadTestDataConfig()` convention (every
 * other unit test class in this repo follows it). That data-provider fixture
 * is intentionally kept separate from the literal template stub files, which
 * live one directory level deeper under `Tests/Fixtures/Views/Render/templates/`,
 * so the two are never mistaken for each other.
 *
 * @covers \WPMedia\MCP\OAuth\Views\Render::view
 */
class ViewTest extends TestCase {

	/**
	 * Renders a fixture template according to the given configuration,
	 * asserting either the echoed output or a thrown \RuntimeException.
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
