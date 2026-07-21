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
	 * Resolves a symbolic flag state ('match'/'missing') to a real option value.
	 *
	 * @param string $state Symbolic flag state.
	 * @return string|null Real version value, or null to mean "option absent".
	 */
	private function resolve_flag( string $state ): ?string {
		return 'match' === $state ? $this->version : null;
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
	 * Flushes the rewrite rules only when the version flag or the persisted OAuth rule requires it.
	 *
	 * @dataProvider configTestData
	 *
	 * @param array<string, mixed> $config   Test configuration.
	 * @param array<string, mixed> $expected Expected outcome.
	 */
	public function testShouldFlushAccordingToState( array $config, array $expected ): void {
		global $wp_rewrite;

		add_filter(
			'wpmedia_mcp_oauth_server_enabled',
			static function () use ( $config ) {
				return $config['enabled'];
			}
		);

		update_option( 'permalink_structure', $config['permalink_structure'] );

		if ( '' !== $config['permalink_structure'] ) {
			$wp_rewrite->init();
		}

		if ( $config['register_oauth_rules'] ) {
			( new Rewrite() )->register_oauth_rewrite_rules();
		}

		update_option( 'rewrite_rules', $config['initial_rewrite_rules'] );

		$initial_flag = $this->resolve_flag( $config['initial_flag'] );
		if ( null === $initial_flag ) {
			delete_option( $this->option );
		} else {
			update_option( $this->option, $initial_flag );
		}

		$this->make_bootstrap()->maybe_flush_rewrite_rules();

		$this->assertSame( $expected['flush_count'], $this->flush_count );

		$expected_flag = $this->resolve_flag( $expected['flag_after'] );
		$this->assertSame( $expected_flag ?? false, get_option( $this->option ) );

		if ( null !== $expected['rule_after'] ) {
			$this->assertSame(
				$expected['rule_after'],
				array_key_exists( Rewrite::AUTHORIZE_RULE, (array) get_option( 'rewrite_rules' ) )
			);
		}
	}
}
