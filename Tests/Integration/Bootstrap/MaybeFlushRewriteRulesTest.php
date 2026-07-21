<?php
declare( strict_types=1 );

namespace WPMedia\MCP\OAuth\Tests\Integration\Bootstrap;

use ReflectionClass;
use WPMedia\MCP\OAuth\Bootstrap;
use WPMedia\MCP\OAuth\Context;
use WPMedia\MCP\OAuth\Auth\Rewrite;
use WPMedia\MCP\OAuth\Tests\Integration\TestCase;

/**
 * Tests for WPMedia\MCP\OAuth\Bootstrap::maybe_flush_rewrite_rules
 *
 * @covers \WPMedia\MCP\OAuth\Bootstrap::maybe_flush_rewrite_rules
 */
class MaybeFlushRewriteRulesTest extends TestCase {

	/**
	 * Bootstrap REWRITE_OPTION constant value.
	 *
	 * @var string
	 */
	private $option;

	/**
	 * Bootstrap REWRITE_VERSION constant value.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Number of times a rewrite-rules flush was triggered during the test.
	 *
	 * @var int
	 */
	private $flush_count = 0;

	/**
	 * Resets rewrite state and wires the flush spy.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$ref           = new ReflectionClass( Bootstrap::class );
		$this->option  = $ref->getConstant( 'REWRITE_OPTION' );
		$this->version = $ref->getConstant( 'REWRITE_VERSION' );

		global $wp_rewrite;
		$wp_rewrite->extra_rules_top = [];

		$this->flush_count = 0;
		add_filter(
			'rewrite_rules_array',
			function ( $rules ) {
				++$this->flush_count;

				return $rules;
			}
		);
	}

	/**
	 * Enables the OAuth server through the primary filter.
	 *
	 * @param bool $enabled Whether the server is enabled.
	 * @return void
	 */
	private function set_enabled( bool $enabled ): void {
		add_filter(
			'wpmedia_mcp_oauth_server_enabled',
			static function () use ( $enabled ) {
				return $enabled;
			}
		);
	}

	/**
	 * Switches the site to pretty permalinks and registers the OAuth rules.
	 *
	 * @return void
	 */
	private function use_pretty_permalinks_with_oauth_rules(): void {
		global $wp_rewrite;

		update_option( 'permalink_structure', '/%postname%/' );
		$wp_rewrite->init();
		( new Rewrite() )->register_oauth_rewrite_rules();
	}

	/**
	 * Builds an isolated Bootstrap instance with an injected Context.
	 *
	 * @return Bootstrap
	 */
	private function make_bootstrap(): Bootstrap {
		$ref       = new ReflectionClass( Bootstrap::class );
		$bootstrap = $ref->newInstanceWithoutConstructor();

		$context = $ref->getProperty( 'context' );
		$context->setAccessible( true );
		$context->setValue( $bootstrap, new Context() );

		return $bootstrap;
	}

	/**
	 * Flushes when the version flag matches but our rule is missing from the persisted set.
	 *
	 * @return void
	 */
	public function testShouldFlushWhenOauthRuleMissingDespiteMatchingVersionFlag(): void {
		$this->set_enabled( true );
		$this->use_pretty_permalinks_with_oauth_rules();

		update_option( $this->option, $this->version );
		update_option( 'rewrite_rules', [ '^foo$' => 'index.php?foo=1' ] );

		$this->make_bootstrap()->maybe_flush_rewrite_rules();

		$this->assertSame( 1, $this->flush_count );
		$this->assertArrayHasKey( Rewrite::AUTHORIZE_RULE, (array) get_option( 'rewrite_rules' ) );
		$this->assertSame( $this->version, get_option( $this->option ) );
	}

	/**
	 * Does not flush when the version flag matches and our rule is already persisted.
	 *
	 * @return void
	 */
	public function testShouldNotFlushWhenVersionFlagAndOauthRulePresent(): void {
		$this->set_enabled( true );
		$this->use_pretty_permalinks_with_oauth_rules();

		update_option( $this->option, $this->version );
		update_option( 'rewrite_rules', [ Rewrite::AUTHORIZE_RULE => 'index.php?mcp_oauth_endpoint=authorize' ] );

		$this->make_bootstrap()->maybe_flush_rewrite_rules();

		$this->assertSame( 0, $this->flush_count );
	}

	/**
	 * Does not flush on every request under plain permalinks once the flag is set.
	 *
	 * @return void
	 */
	public function testShouldNotFlushUnderPlainPermalinksWhenVersionFlagMatches(): void {
		$this->set_enabled( true );

		update_option( 'permalink_structure', '' );
		update_option( $this->option, $this->version );
		update_option( 'rewrite_rules', [] );

		$this->make_bootstrap()->maybe_flush_rewrite_rules();

		$this->assertSame( 0, $this->flush_count );
	}

	/**
	 * Does not flush when the OAuth server is disabled.
	 *
	 * @return void
	 */
	public function testShouldNotFlushWhenServerDisabled(): void {
		$this->set_enabled( false );
		$this->use_pretty_permalinks_with_oauth_rules();

		delete_option( $this->option );
		update_option( 'rewrite_rules', [] );

		$this->make_bootstrap()->maybe_flush_rewrite_rules();

		$this->assertSame( 0, $this->flush_count );
		$this->assertFalse( get_option( $this->option ) );
	}
}
