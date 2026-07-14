<?php
/**
 * Generic view renderer.
 *
 * Single choke-point for loading a named template file and executing it
 * with a `$data` array available in its scope, so no future feature needs
 * to re-invent the same "echo raw HTML from inside a PHP class" pattern.
 *
 * Usage:
 *   $render->view( 'consent-screen', [ 'state' => $state ] );
 */

declare(strict_types=1);

namespace WPMedia\MCP\OAuth\Views;

/**
 * Render.
 *
 * `view()` intentionally never calls `extract()`: templates read values via
 * `$data['key']` instead of individual extracted variables, so there is no
 * risk of a `$data` key (e.g. `data`, `view`, `path`) colliding with a local
 * variable used by this class. Do not "helpfully" add `extract()` here.
 *
 * `Render`'s own unit tests (`Tests/Unit/Views/Render/ViewTest.php`) point
 * the injectable `$templates_dir` constructor parameter at WP-function-free
 * fixture templates under `Tests/Fixtures/Views/Render/templates/`. That
 * directory must not be confused with `Tests/Fixtures/Views/Render/ViewTest.php`,
 * which is the `@dataProvider configTestData` data file consumed by the base
 * `Tests/Unit/TestCase` — a plain PHP array of `config`/`expected` pairs, not
 * a template. The two live at different depths under `Tests/Fixtures/` on
 * purpose so the literal template stubs never get mistaken for test data.
 */
class Render {

	/**
	 * Absolute path (with trailing slash) to the directory templates are loaded from.
	 *
	 * @var string
	 */
	private string $templates_dir;

	/**
	 * Constructor.
	 *
	 * @param string|null $templates_dir Absolute path to the templates directory
	 *                                   (trailing slash added if missing). Defaults
	 *                                   to this class's own `templates/` directory.
	 */
	public function __construct( ?string $templates_dir = null ) {
		$this->templates_dir = $templates_dir ?? __DIR__ . '/templates/';
	}

	/**
	 * Render a named template, making `$data` available in its scope.
	 *
	 * Echoes directly — deliberately not wrapped in its own
	 * `ob_start()`/`ob_get_clean()` — so behaviour matches a plain inline
	 * `require` byte-for-byte, and so an exception thrown mid-template (e.g.
	 * by a WP filter callback) never leaves a dangling inner output buffer
	 * behind for the caller to clean up.
	 *
	 * @param string               $view Template name, without the `.php` extension.
	 * @param array<string, mixed> $data Data made available to the template as `$data`.
	 * @return void
	 *
	 * @throws \RuntimeException When the named template file does not exist.
	 */
	public function view( string $view, array $data = [] ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $data is not read directly here; it is made available to the required template below via this method's local scope.
		// Defence in depth: view() is always called today with a fixed internal
		// literal, never with request-derived input, but basename() confines
		// resolution to $this->templates_dir regardless.
		$path = $this->templates_dir . basename( $view ) . '.php';

		if ( ! is_file( $path ) ) {
			throw new \RuntimeException( sprintf( 'View template not found: %s', $view ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output; Render is unit-tested without WordPress loaded, so no WP escaping function may be used here. $view is always a fixed internal literal, never request input.
		}

		require $path;
	}
}
